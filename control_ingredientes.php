<?php
require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

$pdo = Conexion::ConexionBD();

// --- PAGINATION SETUP ---
$limite_por_pagina = 15; // Número de ingredientes por página
$pagina_actual = $_GET['pagina'] ?? 1;
$offset = ($pagina_actual - 1) * $limite_por_pagina;

function getNivelAlertaConfig() {
    return 10; 
}
$nivel_alerta = getNivelAlertaConfig();

// --- 1. Funciones para obtener conteo total y listado completo ---

function getTotalIngredientesCount(PDO $pdo) {
    $sql = "SELECT COUNT(*) FROM ingredientes i WHERE i.activo = true";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getStockIngredientesFull(PDO $pdo) {
    $sql = "SELECT 
                i.id_ingrediente,
                i.nombre_ingrediente,
                i.descripcion,
                i.costo_unitario,
                i.stock,
                i.precio_extra,
                i.es_extra,
                (i.costo_unitario * i.stock) as valor_inventario,
                CASE 
                    WHEN i.es_extra THEN (i.precio_extra - i.costo_unitario)
                    ELSE 0 
                END as margen_extra
            FROM ingredientes i
            WHERE i.activo = true
            ORDER BY i.stock ASC, i.nombre_ingrediente";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 2. Función para obtener el listado Paginado ---
function getStockIngredientes(PDO $pdo, $limit, $offset) {
    $sql = "SELECT 
                i.id_ingrediente,
                i.nombre_ingrediente,
                i.descripcion,
                i.costo_unitario,
                i.stock,
                i.precio_extra,
                i.es_extra,
                (i.costo_unitario * i.stock) as valor_inventario,
                CASE 
                    WHEN i.es_extra THEN (i.precio_extra - i.costo_unitario)
                    ELSE 0 
                END as margen_extra
            FROM ingredientes i
            WHERE i.activo = true
            ORDER BY i.stock ASC, i.nombre_ingrediente
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getIngredientesMasUtilizados($pdo, $year = 2025, $month = null) {
    $sql = "SELECT 
                i.nombre_ingrediente,
                SUM(dve.cantidad) as cantidad_utilizada,
                SUM(dve.precio_extra * dve.cantidad) as total_ventas_extras
            FROM det_ven_extras dve
            JOIN ingredientes i ON dve.id_ingrediente = i.id_ingrediente
            JOIN det_ventas dv ON dve.id_det_venta = dv.id_det_venta
            JOIN ventas v ON dv.id_venta = v.id_venta
            WHERE EXTRACT(YEAR FROM v.fecha_venta) = ?";

    $params = [$year];

    if ($month) {
        $sql .= " AND EXTRACT(MONTH FROM v.fecha_venta) = ?";
        $params[] = $month;
    }

    $sql .= " GROUP BY i.nombre_ingrediente
              ORDER BY cantidad_utilizada DESC
              LIMIT 10"; 

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 3. Ejecución de consultas ---

// Para el total de la tarjeta (debe ser el total de todos los ingredientes activos, sin paginación)
$full_stock_ingredientes = getStockIngredientesFull($pdo);
$total_valor_inventario = array_sum(array_column($full_stock_ingredientes, 'valor_inventario'));
$total_ingredientes_activos = count($full_stock_ingredientes); 

// Para la tabla (paginado)
$stock_ingredientes = getStockIngredientes($pdo, $limite_por_pagina, $offset);

// Cálculo de paginación
$total_ingredientes_paginables = $total_ingredientes_activos; // Ya que no hay filtros de busqueda en esta tabla
$total_paginas = ceil($total_ingredientes_paginables / $limite_por_pagina);


$year_utilizados = isset($_POST['year_utilizados']) ? (int)$_POST['year_utilizados'] : date('Y');
$month_utilizados = isset($_POST['month_utilizados']) && $_POST['month_utilizados'] != '' ? (int)$_POST['month_utilizados'] : null;

$ingredientes_utilizados = getIngredientesMasUtilizados($pdo, $year_utilizados, $month_utilizados);

// Helper para generar query string de paginación (sin el parámetro 'pagina')
function buildQueryStringControl($exclude_params = ['pagina']) {
    $params = $_GET;
    // Eliminar los parámetros a excluir
    foreach ($exclude_params as $param) {
        unset($params[$param]);
    }
    // Filtrar valores vacíos
    $params = array_filter($params, function($value) {
        return $value !== '';
    });
    return http_build_query($params);
}
$current_filters_no_page_ingredientes = buildQueryStringControl(['pagina']);


$currentPage = 'control_ingredientes';
include 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Ingredientes - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .row-critical {
            background-color: #f8d7da !important; 
            font-weight: bold;
        }
        .row-alert {
            background-color: #fff3cd !important; 
        }
    </style>
</head>

<body>
    
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="h3 mb-0">
                <i class="bi bi-list-check text-primary me-2"></i> Control de Stock de Ingredientes
            </h2>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>

        <div class="card shadow-sm mb-4 border-start border-5 border-info">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h5 class="text-info"><i class="bi bi-box-fill me-2"></i> Ingredientes Activos</h5>
                        <p class="display-4 fw-bold mb-0 text-info"><?= $total_ingredientes_activos ?></p> 
                    </div>
                    <div class="col-md-4 border-start border-end">
                        <h5 class="text-success"><i class="bi bi-cash-stack me-2"></i> Valor Total Inventario</h5>
                        <p class="display-4 fw-bold mb-0 text-success">$<?= number_format($total_valor_inventario, 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <h5 class="text-secondary"><i class="bi bi-bell-fill me-2"></i> Nivel de Alerta</h5>
                        <p class="display-4 fw-bold mb-0 text-secondary"><?= $nivel_alerta ?> unidades</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-clipboard-data text-primary"></i> Stock Actual y Costos
                    <span class="badge bg-secondary ms-2"><?= $total_ingredientes_paginables ?></span>
                    <small class="text-muted ms-2">(Página <?= $pagina_actual ?> de <?= $total_paginas ?>)</small>
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($stock_ingredientes)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h5 class="text-muted">No hay ingredientes activos o página fuera de rango.</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th class="text-start">INGREDIENTE</th>
                                    <th>STOCK (unid.)</th>
                                    <th>COSTO UNITARIO</th>
                                    <th>PRECIO EXTRA (venta)</th>
                                    <th>MARGEN EXTRA</th>
                                    <th>VALOR INVENTARIO</th>
                                    <th>TIPO</th>
                                    <th>ESTADO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock_ingredientes as $ingrediente): 
                                    $row_class = '';
                                    if ($ingrediente['stock'] <= 3) {
                                        $row_class = 'row-critical'; 
                                    } elseif ($ingrediente['stock'] <= $nivel_alerta) {
                                        $row_class = 'row-alert'; 
                                    }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td class="text-start fw-bold"><?= htmlspecialchars($ingrediente['nombre_ingrediente']) ?></td>
                                    <td class="text-end"><?= number_format($ingrediente['stock']) ?></td>
                                    <td class="text-end">$<?= number_format($ingrediente['costo_unitario'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($ingrediente['precio_extra'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($ingrediente['margen_extra'], 2) ?></td>
                                    <td class="text-end fw-bold text-success">$<?= number_format($ingrediente['valor_inventario'], 2) ?></td>
                                    <td>
                                        <?php if ($ingrediente['es_extra']): ?>
                                            <span class="badge bg-success">EXTRA</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">BASE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ingrediente['stock'] <= 3): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-octagon-fill me-1"></i> CRÍTICO</span>
                                        <?php elseif ($ingrediente['stock'] <= $nivel_alerta): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i> ALERTA</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> NORMAL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <tr class="table-primary fw-bold">
                                    <td colspan="5" class="text-start"><strong>TOTAL INVENTARIO</strong></td>
                                    <td class="text-end text-success"><strong>$<?= number_format($total_valor_inventario, 2) ?></strong></td>
                                    <td colspan="2" class="text-end"><strong><?= $total_ingredientes_activos ?> ingredientes diferentes</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="card-footer bg-light border-top">
                    <nav aria-label="Paginación de ingredientes">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo $current_filters_no_page_ingredientes; ?>&pagina=<?php echo $pagina_actual - 1; ?>" 
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
                                       href="?<?php echo $current_filters_no_page_ingredientes; ?>&pagina=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo $current_filters_no_page_ingredientes; ?>&pagina=<?php echo $pagina_actual + 1; ?>" 
                                   aria-label="Siguiente">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-star-fill text-warning"></i> Top 10 Ingredientes Extras Más Vendidos</h6>
            </div>
            <div class="card-body">
                
                <form method="POST" class="row g-3 align-items-center mb-3">
                    <div class="col-md-3">
                        <label for="year_utilizados" class="form-label">Año</label>
                        <input type="number" id="year_utilizados" name="year_utilizados" class="form-control" value="<?= $year_utilizados ?>" min="2020" max="2030" required>
                    </div>

                    <div class="col-md-3">
                        <label for="month_utilizados" class="form-label">Mes (opcional)</label>
                        <select id="month_utilizados" name="month_utilizados" class="form-select">
                            <option value="">Todos los meses</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $month_utilizados == $i ? 'selected' : '' ?>>
                                    <?= DateTime::createFromFormat('!m', $i)->format('F') ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-warning mt-4">
                            <i class="bi bi-search"></i> Filtrar Uso
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-secondary text-center">
                            <tr>
                                <th class="text-start">RANK</th>
                                <th class="text-start">INGREDIENTE</th>
                                <th>CANTIDAD UTILIZADA</th>
                                <th>TOTAL INGRESOS EXTRAS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingredientes_utilizados as $index => $ingrediente): ?>
                            <tr>
                                <td class="text-start fw-bold"><?= $index + 1 ?></td>
                                <td class="text-start"><?= htmlspecialchars($ingrediente['nombre_ingrediente']) ?></td>
                                <td class="text-end"><?= number_format($ingrediente['cantidad_utilizada']) ?></td>
                                <td class="text-end">$<?= number_format($ingrediente['total_ventas_extras'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ingredientes_utilizados)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No se registraron ventas de extras en este periodo.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>