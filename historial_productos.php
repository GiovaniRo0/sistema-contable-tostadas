<?php
$currentPage = 'historial_productos';
include 'menu.php';
include 'Conexion.php';
require_once 'auth.php';

$database = new Conexion();
$db = Conexion::ConexionBD();

// --- Paginación: Configuración ---
$limite_por_pagina = 5; // Número de registros por página
$pagina_actual = $_GET['pagina'] ?? 1;
$offset = ($pagina_actual - 1) * $limite_por_pagina;

// --- Filtros ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_registro = $_GET['tipo_registro'] ?? '';

// --- Consulta Base ---
$query_base = "FROM historial h
          LEFT JOIN productos p ON h.id_referencia = p.id_producto
          LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
          WHERE h.tabla_referencia = 'productos'
            AND h.tipo_registro IN ('nuevo_producto', 'actualizacion_producto', 'eliminacion_producto')";

$params = [];

// --- Aplicación de Filtros a la Condición WHERE ---
$condiciones = "";

if (!empty($fecha_desde)) {
    $condiciones .= " AND fecha_cambio >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $condiciones .= " AND fecha_cambio <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

// Nota: El filtro principal de la tabla (tipo_registro) sí debe aplicarse
// para la paginación y para el conteo general, si el usuario lo seleccionó.
if (!empty($tipo_registro)) {
    $condiciones .= " AND tipo_registro = :tipo_registro";
    $params[':tipo_registro'] = $tipo_registro;
}


// ----------------------------------------------------
// --- 1. Consultas para el Conteo Total (Contadores) ---
// ----------------------------------------------------

$params_count = $params; // Los parámetros base de fecha son los mismos

// Función de ayuda para ejecutar el conteo
function getTotalCount(PDO $db, $query_base, $condiciones, $tipo, $params_base) {
    $query = "SELECT COUNT(*) " . $query_base . $condiciones;
    $params = $params_base;
    
    // Si el filtro principal NO está activo o es diferente, agregamos el filtro de tipo
    if (empty($params_base[':tipo_registro']) || ($params_base[':tipo_registro'] !== $tipo && isset($params_base[':tipo_registro']))) {
        // Si el filtro principal SÍ está activo, solo cuenta si coincide, si no, usa una condición alternativa.
        // Pero lo más limpio es ejecutar las consultas separadamente.
        
        // Removemos el filtro general de tipo de registro si existe, para que las tarjetas cuenten TODOS.
        // En este escenario, si el usuario filtra por 'nuevo_producto', las tarjetas deberían mostrar
        // el total de nuevos, actualizaciones y eliminaciones bajo los FILTROS DE FECHA,
        // no solo el que seleccionó.
        
        // Opción limpia: Eliminamos la condición 'tipo_registro' del string y la aplicamos aquí.
        $temp_condiciones = str_replace(" AND tipo_registro = :tipo_registro", "", $condiciones);
        unset($params[':tipo_registro']);
        
        $query = "SELECT COUNT(*) " . $query_base . $temp_condiciones . " AND tipo_registro = :tipo_card";
        $params[':tipo_card'] = $tipo;

    } else {
        // Si el usuario SÍ filtro por este tipo (ej: tipo_registro = nuevo_producto)
        // La condición de tipo ya está en $condiciones, y el conteo será el mismo que el total_registros.
        $query = "SELECT COUNT(*) " . $query_base . $condiciones;
    }

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchColumn();
}


// Contadores para las tarjetas (siempre usando solo filtros de fecha, si no se aplicó un filtro general)

// Si el usuario *no* ha aplicado un filtro de tipo (tipo_registro está vacío), contamos los 3 totales.
if (empty($tipo_registro)) {
    $total_nuevos = getTotalCount($db, $query_base, $condiciones, 'nuevo_producto', $params);
    $total_actualizaciones = getTotalCount($db, $query_base, $condiciones, 'actualizacion_producto', $params);
    $total_eliminaciones = getTotalCount($db, $query_base, $condiciones, 'eliminacion_producto', $params);
} else {
    // Si el usuario SÍ aplicó un filtro de tipo, solo ese contador tendrá el total. Los demás serán 0.
    $total_nuevos = ($tipo_registro == 'nuevo_producto') ? 1 : 0;
    $total_actualizaciones = ($tipo_registro == 'actualizacion_producto') ? 1 : 0;
    $total_eliminaciones = ($tipo_registro == 'eliminacion_producto') ? 1 : 0;
    
    // Se corrige esta lógica para que muestren el conteo real del filtro activo:
    // Al filtrar por un solo tipo, solo ese contador debe ser el total de registros encontrados.
    $total_registros_encontrados = 0;
}


// --------------------------------------------------------------------------------------
// --- 2. Consulta para el Conteo Total de Registros MOSTRADOS (para Paginación) ---
// --------------------------------------------------------------------------------------

// Este conteo debe reflejar los filtros DE FECHA Y DE TIPO seleccionados por el usuario.
$query_count = "SELECT COUNT(*) " . $query_base . $condiciones;
$stmt_count = $db->prepare($query_count);
foreach ($params as $key => $value) {
    // Excluimos el bindeo si la clave es :tipo_registro y está vacía
    if ($key === ':tipo_registro' && empty($value)) continue;
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetchColumn(); // TOTAL DE REGISTROS CON TODOS LOS FILTROS
$total_paginas = ceil($total_registros / $limite_por_pagina);


// -----------------------------------------------------------------
// --- 3. Consulta para los Registros de la Página Actual (LIMIT) ---
// -----------------------------------------------------------------
$query_select = "SELECT 
            h.id_historial,
            h.fecha_cambio,
            h.tipo_registro,
            CASE 
                WHEN h.tipo_registro = 'nuevo_producto' THEN 'Nuevo Producto'
                WHEN h.tipo_registro = 'actualizacion_producto' THEN 'Actualización'
                WHEN h.tipo_registro = 'eliminacion_producto' THEN 'Eliminación'
                ELSE h.tipo_registro
            END as tipo_registro_desc,
            h.descripcion,
            h.valor_anterior,
            h.valor_nuevo,
            h.id_usuario,
            COALESCE(p.id_producto, (h.valor_anterior::json->>'id_producto')::integer) as id_producto,
            COALESCE(p.nombre_producto, h.valor_anterior::json->>'nombre_producto') as nombre_producto,
            COALESCE(p.precio_base, (h.valor_anterior::json->>'precio_base')::numeric) as precio_base,
            COALESCE(p.descripcion, h.valor_anterior::json->>'descripcion') as descripcion_producto,
            COALESCE(c.nombre_categoria, 'Categoría no disponible') as nombre_categoria,
            COALESCE(p.activo, false) as producto_activo,
            'productos' as origen_tabla
          " . $query_base . $condiciones;

$query_select .= " ORDER BY fecha_cambio DESC LIMIT :limite OFFSET :offset";

$stmt = $db->prepare($query_select);

// Bindeo de parámetros (incluyendo LIMIT/OFFSET)
foreach ($params as $key => $value) {
    // Excluimos el bindeo si la clave es :tipo_registro y está vacía
    if ($key === ':tipo_registro' && empty($value)) continue;
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', $limite_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Asignamos el total de registros al contador correspondiente si se aplicó un filtro de tipo
if (!empty($tipo_registro)) {
    if ($tipo_registro == 'nuevo_producto') $total_nuevos = $total_registros;
    if ($tipo_registro == 'actualizacion_producto') $total_actualizaciones = $total_registros;
    if ($tipo_registro == 'eliminacion_producto') $total_eliminaciones = $total_registros;
}


$tipos_registro = [
    'nuevo_producto' => 'Nuevos Productos',
    'actualizacion_producto' => 'Actualizaciones',
    'eliminacion_producto' => 'Eliminaciones'
];

// Función de ayuda para clases de badge (se puede definir aquí o en menu.php si es global)
function getBadgeClass($tipo)
{
    $classes = [
        'nuevo_producto' => 'bg-success',
        'actualizacion_producto' => 'bg-info',
        'eliminacion_producto' => 'bg-danger' // Se cambió a danger para consistencia
    ];
    return $classes[$tipo] ?? 'bg-secondary';
}

/**
 * Función para generar los parámetros de URL para los enlaces de paginación
 * y filtros, excluyendo el parámetro de la página.
 * @param array $exclude_params Parámetros a excluir (por defecto 'pagina').
 * @return string Cadena de consulta (query string).
 */
function buildQueryString($exclude_params = ['pagina']) {
    $params = $_GET;
    // Eliminar la página para el enlace
    if (in_array('pagina', $exclude_params)) {
        unset($params['pagina']);
    }
    // Eliminar otros parámetros si es necesario
    foreach ($exclude_params as $param) {
        if ($param !== 'pagina') {
            unset($params[$param]);
        }
    }
    // Filtrar valores vacíos
    $params = array_filter($params, function($value) {
        return $value !== '';
    });

    return http_build_query($params);
}

// Reconstruir los parámetros de filtro actuales para los enlaces de paginación
$current_filters_no_page = buildQueryString(['pagina']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Productos - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Se elimina CSS personalizado redundante y se agrega estilo para card moderno */
        .card {
            border-radius: 0.75rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .display-5 {
            font-size: 2.5rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, .03); /* Más sutil */
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h2 class="h3 mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i> Historial de Productos
                    </h2>
                    <div>
                        <a href="reportes_productos.php" class="btn btn-outline-secondary">
                            <i class="bi bi-graph-up"></i> Ver Reportes
                        </a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0"><i class="bi bi-funnel text-primary"></i> Filtros</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Fecha Desde</label>
                                <input type="date" name="fecha_desde" class="form-control"
                                    value="<?php echo htmlspecialchars($fecha_desde); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control"
                                    value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Registro</label>
                                <select name="tipo_registro" class="form-select">
                                    <option value="">Todos los tipos</option>
                                    <?php foreach ($tipos_registro as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php echo $tipo_registro == $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-filter"></i> Filtrar
                                </button>
                                <a href="historial_productos.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                                </a>
                                <input type="hidden" name="pagina" value="1"> 
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-5">
                    
                    <div class="col-md-4">
                        <div class="card border-success text-success shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted">Nuevos Productos</p>
                                        <h3 class="display-5 fw-bold"><?php echo $total_nuevos; ?></h3>
                                    </div>
                                    <i class="bi bi-plus-circle display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-info text-info shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted">Actualizaciones</p>
                                        <h3 class="display-5 fw-bold"><?php echo $total_actualizaciones; ?></h3>
                                    </div>
                                    <i class="bi bi-pencil-square display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-danger text-danger shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted">Eliminaciones</p>
                                        <h3 class="display-5 fw-bold"><?php echo $total_eliminaciones; ?></h3>
                                    </div>
                                    <i class="bi bi-trash display-4 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="mb-0">
                            <i class="bi bi-list-ul text-primary"></i> Registros de Historial
                            <span class="badge bg-secondary ms-2"><?php echo $total_registros; ?></span>
                            <small class="text-muted ms-2">(Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)</small>
                        </h6>
                        <div>
                            <button class="btn btn-sm btn-danger" onclick="generarPDF()">
                                <i class="bi bi-file-pdf"></i> Generar PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($historial) && $total_registros > 0): ?>
                             <div class="text-center py-5">
                                <i class="bi bi-exclamation-octagon display-1 text-warning"></i>
                                <h5 class="text-muted">Página fuera de rango</h5>
                                <p class="text-muted">La página solicitada no contiene registros.</p>
                                <a href="?<?php echo $current_filters_no_page; ?>&pagina=1" class="btn btn-sm btn-warning">Ir a la primera página</a>
                            </div>
                        <?php elseif (empty($historial) && $total_registros == 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h5 class="text-muted">No hay registros de historial</h5>
                                <p class="text-muted">No se encontraron registros con los filtros aplicados.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Tipo</th>
                                            <th>Producto</th>
                                            <th>Descripción</th>
                                            <th>Usuario</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial as $registro): ?>
                                            <tr>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($registro['fecha_cambio'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Se utiliza la función getBadgeClass para obtener la clase directamente
                                                    $badge_class = getBadgeClass($registro['tipo_registro']);
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo $registro['tipo_registro_desc']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($registro['nombre_producto']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($registro['nombre_categoria']); ?>
                                                        • $<?php echo number_format($registro['precio_base'], 2); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($registro['descripcion']); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        ID: <?php echo $registro['id_usuario'] ?? 'Sistema'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info"
                                                        onclick="verDetalles(<?php echo htmlspecialchars(json_encode($registro)); ?>)">
                                                        <i class="bi bi-eye"></i> Detalles
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_paginas > 1): ?>
                        <div class="card-footer bg-light border-top">
                            <nav aria-label="Paginación de historial">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo $current_filters_no_page; ?>&pagina=<?php echo $pagina_actual - 1; ?>" 
                                           aria-label="Anterior">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php
                                        // Definir el rango de páginas a mostrar alrededor de la página actual
                                        $inicio = max(1, $pagina_actual - 2);
                                        $fin = min($total_paginas, $pagina_actual + 2);

                                        // Ajuste para mostrar 5 números si es posible
                                        if ($inicio === 1) {
                                            $fin = min($total_paginas, 5);
                                        }
                                        if ($fin === $total_paginas) {
                                            $inicio = max(1, $total_paginas - 4);
                                        }
                                    ?>
                                    
                                    <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                                            <a class="page-link" 
                                               href="?<?php echo $current_filters_no_page; ?>&pagina=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo $current_filters_no_page; ?>&pagina=<?php echo $pagina_actual + 1; ?>" 
                                           aria-label="Siguiente">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Registro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDetallesContent">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // La función getBadgeClass es necesaria en JS para el modal
        function getBadgeClass(tipo) {
            const classes = {
                'nuevo_producto': 'bg-success',
                'actualizacion_producto': 'bg-info',
                'eliminacion_producto': 'bg-danger'
            };
            return classes[tipo] || 'bg-secondary';
        }
        
        function verDetalles(registro) {
            let contenido = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información Principal</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Producto:</strong></td>
                                <td>${registro.nombre_producto}</td>
                            </tr>
                            <tr>
                                <td><strong>Categoría:</strong></td>
                                <td>${registro.nombre_categoria}</td>
                            </tr>
                            <tr>
                                <td><strong>Precio:</strong></td>
                                <td>$${parseFloat(registro.precio_base).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td><strong>Fecha:</strong></td>
                                <td>${new Date(registro.fecha_cambio).toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Detalles del Cambio</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Tipo:</strong></td>
                                <td><span class="badge ${getBadgeClass(registro.tipo_registro)}">${registro.tipo_registro_desc}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Usuario:</strong></td>
                                <td>ID: ${registro.id_usuario || 'Sistema'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Descripción</h6>
                        <div class="alert alert-info">
                            ${registro.descripcion}
                        </div>
                    </div>
                </div>`;

            if (registro.valor_anterior || registro.valor_nuevo) {
                // Función auxiliar para formatear JSON para visualización
                const formatJson = (jsonString) => {
                    if (!jsonString || jsonString === 'N/A') return 'N/A';
                    try {
                        // Intenta parsear y stringify con indentación si parece JSON
                        const obj = JSON.parse(jsonString);
                        return JSON.stringify(obj, null, 2);
                    } catch (e) {
                        return jsonString; // Devuelve el string original si no es JSON válido
                    }
                };
                
                contenido += `
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Valor Anterior</h6>
                        <div class="bg-light p-2 rounded">
                            <pre class="mb-0" style="font-size: 0.8rem;">${formatJson(registro.valor_anterior || 'N/A')}</pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Valor Nuevo</h6>
                        <div class="bg-light p-2 rounded">
                            <pre class="mb-0" style="font-size: 0.8rem;">${formatJson(registro.valor_nuevo || 'N/A')}</pre>
                        </div>
                    </div>
                </div>`;
            }

            document.getElementById('modalDetallesContent').innerHTML = contenido;
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }


        function generarPDF() {
            const fecha_desde = document.querySelector('input[name="fecha_desde"]').value;
            const fecha_hasta = document.querySelector('input[name="fecha_hasta"]').value;
            const tipo_registro = document.querySelector('select[name="tipo_registro"]').value;
            // Se puede agregar la página actual al PDF si fuera relevante, pero generalmente no se hace.

            let url = 'pdf_historial_productos.php?fecha_desde=' + fecha_desde + '&fecha_hasta=' + fecha_hasta;
            if (tipo_registro) {
                url += '&tipo_registro=' + tipo_registro;
            }

            window.open(url, '_blank');
        }
    </script>
</body>

</html>