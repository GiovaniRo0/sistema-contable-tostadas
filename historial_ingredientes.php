<?php
$currentPage = 'historial_ingredientes';
include 'menu.php';
include 'Conexion.php';
require_once 'auth.php';

$database = new Conexion();
$db = Conexion::ConexionBD();

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_registro = $_GET['tipo_registro'] ?? '';

$query = "SELECT * FROM vista_historial_ingredientes WHERE 1=1";
$params = [];

if (!empty($fecha_desde)) {
    $query .= " AND fecha_cambio >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $query .= " AND fecha_cambio <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

if (!empty($tipo_registro)) {
    $query .= " AND tipo_registro = :tipo_registro";
    $params[':tipo_registro'] = $tipo_registro;
}

$query .= " ORDER BY fecha_cambio DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipos_registro = [
    'ENTRADA_STOCK' => 'Entradas Stock',
    'SALIDA_STOCK' => 'Salidas Stock',
    'ACTUALIZACION_PRECIO' => 'Cambios Precio'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ingredientes - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .badge-entrada {
            background-color: #198754;
        }

        .badge-salida {
            background-color: #dc3545;
        }

        .badge-precio {
            background-color: #0dcaf0;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, .075);
        }
    </style>
</head>

<body>
    <div class="container-fluid py-3">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="bi bi-clock-history"></i> Historial de Ingredientes
                    </h1>
                    <a href="reportes_ingredientes.php" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up"></i> Ver Reportes
                    </a>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
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
                                <a href="historial_ingredientes.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                                </a>
                            </div>
                        </form>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-danger" onclick="generarPDF()">
                                <i class="bi bi-file-pdf"></i> Generar PDF
                            </button>
                        </div>
                    </div>

                </div>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Entradas Stock</h6>
                                        <h4><?php echo count(array_filter($historial, function ($h) {
                                                return $h['tipo_registro'] === 'ENTRADA_STOCK';
                                            })); ?></h4>
                                    </div>
                                    <i class="bi bi-box-arrow-in-down display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Salidas Stock</h6>
                                        <h4><?php echo count(array_filter($historial, function ($h) {
                                                return $h['tipo_registro'] === 'SALIDA_STOCK';
                                            })); ?></h4>
                                    </div>
                                    <i class="bi bi-box-arrow-up display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Cambios Precio</h6>
                                        <h4><?php echo count(array_filter($historial, function ($h) {
                                                return $h['tipo_registro'] === 'ACTUALIZACION_PRECIO';
                                            })); ?></h4>
                                    </div>
                                    <i class="bi bi-currency-dollar display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Registros</h6>
                                        <h4><?php echo count($historial); ?></h4>
                                    </div>
                                    <i class="bi bi-list-ul display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-list-ul"></i> Registros de Historial
                            <span class="badge bg-secondary"><?php echo count($historial); ?></span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($historial)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h5 class="text-muted">No hay registros de historial</h5>
                                <p class="text-muted">No se encontraron registros con los filtros aplicados.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Tipo</th>
                                            <th>Ingrediente</th>
                                            <th>Descripción</th>
                                            <th>Stock Actual</th>
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
                                                    $badge_class = [
                                                        'ENTRADA_STOCK' => 'badge-entrada',
                                                        'SALIDA_STOCK' => 'badge-salida',
                                                        'ACTUALIZACION_PRECIO' => 'badge-precio'
                                                    ][$registro['tipo_registro']] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo $registro['tipo_registro_desc']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($registro['nombre_ingrediente']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Costo: $<?php echo number_format($registro['costo_unitario'], 2); ?>
                                                        • Extra: $<?php echo number_format($registro['precio_extra'], 2); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($registro['descripcion']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $registro['stock'] > 10 ? 'bg-success' : ($registro['stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                                        <?php echo $registro['stock']; ?> unidades
                                                    </span>
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
        function verDetalles(registro) {
            let contenido = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información del Ingrediente</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Ingrediente:</strong></td>
                                <td>${registro.nombre_ingrediente}</td>
                            </tr>
                            <tr>
                                <td><strong>Costo Unitario:</strong></td>
                                <td>$${parseFloat(registro.costo_unitario).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td><strong>Precio Extra:</strong></td>
                                <td>$${parseFloat(registro.precio_extra).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td><strong>Stock Actual:</strong></td>
                                <td>${registro.stock} unidades</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Detalles del Cambio</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Fecha:</strong></td>
                                <td>${new Date(registro.fecha_cambio).toLocaleString()}</td>
                            </tr>
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
                contenido += `
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Valor Anterior</h6>
                        <div class="bg-light p-2 rounded">
                            <pre class="mb-0" style="font-size: 0.8rem;">${registro.valor_anterior || 'N/A'}</pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Valor Nuevo</h6>
                        <div class="bg-light p-2 rounded">
                            <pre class="mb-0" style="font-size: 0.8rem;">${registro.valor_nuevo || 'N/A'}</pre>
                        </div>
                    </div>
                </div>`;
            }

            document.getElementById('modalDetallesContent').innerHTML = contenido;
            new bootstrap.Modal(document.getElementById('modalDetalles')).show();
        }

        function getBadgeClass(tipo) {
            const classes = {
                'ENTRADA_STOCK': 'bg-success',
                'SALIDA_STOCK': 'bg-danger',
                'ACTUALIZACION_PRECIO': 'bg-info'
            };
            return classes[tipo] || 'bg-secondary';
        }

        function generarPDF() {
            const fecha_desde = document.querySelector('input[name="fecha_desde"]').value;
            const fecha_hasta = document.querySelector('input[name="fecha_hasta"]').value;
            const tipo_registro = document.querySelector('select[name="tipo_registro"]').value;

            let url = 'pdf_historial_ingredientes.php?fecha_desde=' + fecha_desde + '&fecha_hasta=' + fecha_hasta;
            if (tipo_registro) {
                url += '&tipo_registro=' + tipo_registro;
            }

            window.open(url, '_blank');
        }
    </script>
</body>

</html>