<?php
$currentPage = 'reportes_ingredientes';
include 'menu.php';
include 'Conexion.php';
require_once 'auth.php';

$database = new Conexion();
$db = Conexion::ConexionBD();

$query_actividad = "SELECT * FROM vista_reporte_actividad_ingredientes 
                   WHERE fecha >= CURRENT_DATE - INTERVAL '30 days'
                   ORDER BY fecha DESC, tipo_registro";
$stmt_actividad = $db->prepare($query_actividad);
$stmt_actividad->execute();
$actividad_diaria = $stmt_actividad->fetchAll(PDO::FETCH_ASSOC);

$query_mensual = "SELECT * FROM vista_resumen_mensual_ingredientes 
                 ORDER BY mes DESC";
$stmt_mensual = $db->prepare($query_mensual);
$stmt_mensual->execute();
$resumen_mensual = $stmt_mensual->fetchAll(PDO::FETCH_ASSOC);

$query_movimientos = "SELECT * FROM vista_reporte_movimientos_stock 
                     WHERE fecha >= CURRENT_DATE - INTERVAL '7 days'
                     ORDER BY fecha DESC, total_movimientos DESC";
$stmt_movimientos = $db->prepare($query_movimientos);
$stmt_movimientos->execute();
$movimientos_stock = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);

function getBadgeClass($tipo)
{
    $classes = [
        'ENTRADA_STOCK' => 'bg-success',
        'SALIDA_STOCK' => 'bg-danger',
        'ACTUALIZACION_PRECIO' => 'bg-info'
    ];
    return $classes[$tipo] ?? 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Ingredientes - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h2 class="h3 mb-0">
                        <i class="bi bi-graph-up text-primary me-2"></i> Reportes de Ingredientes
                    </h2>
                    <div>
                        <a href="historial_ingredientes.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-clock-history"></i> Historial
                        </a>
                        <button class="btn btn-danger" onclick="generarPDF()">
                            <i class="bi bi-file-pdf"></i> Generar Reporte PDF
                        </button>
                    </div>
                </div>

                <div class="row mb-5">
                    <?php
                    $total_entradas = 0;
                    $total_salidas = 0;
                    $total_precios = 0;

                    foreach ($resumen_mensual as $mes) {
                        if ($mes['tipo_registro'] === 'ENTRADA_STOCK') $total_entradas += $mes['total_operaciones'];
                        if ($mes['tipo_registro'] === 'SALIDA_STOCK') $total_salidas += $mes['total_operaciones'];
                        if ($mes['tipo_registro'] === 'ACTUALIZACION_PRECIO') $total_precios += $mes['total_operaciones'];
                    }
                    ?>
                    
                    <div class="col-md-3">
                        <div class="card border-primary text-primary shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_entradas + $total_salidas + $total_precios; ?></h3>
                                <p class="mb-0 text-muted">Total Operaciones</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-success text-success shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_entradas; ?></h3>
                                <p class="mb-0 text-muted">Entradas Stock</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-danger text-danger shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_salidas; ?></h3>
                                <p class="mb-0 text-muted">Salidas Stock</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-info text-info shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_precios; ?></h3>
                                <p class="mb-0 text-muted">Cambios Precio</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0"><i class="bi bi-bar-chart text-primary"></i> Actividad de Stock (Últimos 30 días)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoStock" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0"><i class="bi bi-pie-chart text-primary"></i> Distribución por Tipo</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoDistribucion" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0"><i class="bi bi-arrow-left-right text-primary"></i> Movimientos de Stock (Últimos 7 días)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Ingrediente</th>
                                        <th>Entradas</th>
                                        <th>Salidas</th>
                                        <th>Total Movimientos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos_stock as $movimiento): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($movimiento['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($movimiento['nombre_ingrediente']); ?></td>
                                            <td><span class="badge bg-success"><?php echo $movimiento['entradas']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $movimiento['salidas']; ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo $movimiento['total_movimientos']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0"><i class="bi bi-calendar-month text-primary"></i> Resumen Mensual</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Mes</th>
                                        <th>Tipo de Registro</th>
                                        <th>Total Operaciones</th>
                                        <th>Ingredientes Afectados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumen_mensual as $mes): ?>
                                        <tr>
                                            <td><?php echo $mes['mes']; ?></td>
                                            <td>
                                                <span class="badge <?php echo getBadgeClass($mes['tipo_registro']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $mes['tipo_registro'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $mes['total_operaciones']; ?></td>
                                            <td><?php echo $mes['ingredientes_afectados']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctxStock = document.getElementById('graficoStock').getContext('2d');
        new Chart(ctxStock, {
            type: 'bar',
            data: {
                labels: [<?php
                            $fechas = array_unique(array_column($actividad_diaria, 'fecha'));
                            sort($fechas);
                            echo '"' . implode('","', array_map(function ($f) {
                                return date('d/m', strtotime($f));
                            }, $fechas)) . '"';
                            ?>],
                datasets: [{
                        label: 'Entradas Stock',
                        data: [<?php
                                $data = [];
                                foreach ($fechas as $fecha) {
                                    $valor = 0;
                                    foreach ($actividad_diaria as $act) {
                                        if ($act['fecha'] === $fecha && $act['tipo_registro'] === 'ENTRADA_STOCK') {
                                            $valor = $act['cantidad'];
                                            break;
                                        }
                                    }
                                    $data[] = $valor;
                                }
                                echo implode(',', $data);
                                ?>],
                        backgroundColor: '#198754' 
                    },
                    {
                        label: 'Salidas Stock',
                        data: [<?php
                                $data = [];
                                foreach ($fechas as $fecha) {
                                    $valor = 0;
                                    foreach ($actividad_diaria as $act) {
                                        if ($act['fecha'] === $fecha && $act['tipo_registro'] === 'SALIDA_STOCK') {
                                            $valor = $act['cantidad'];
                                            break;
                                        }
                                    }
                                    $data[] = $valor;
                                }
                                echo implode(',', $data);
                                ?>],
                        backgroundColor: '#dc3545' 
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: false, 
                        text: 'Movimientos de Stock Diarios'
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        const ctxDistribucion = document.getElementById('graficoDistribucion').getContext('2d');
        new Chart(ctxDistribucion, {
            type: 'doughnut',
            data: {
                labels: ['Entradas Stock', 'Salidas Stock', 'Cambios Precio'],
                datasets: [{
                    data: [<?php echo "$total_entradas, $total_salidas, $total_precios"; ?>],
                    backgroundColor: ['#198754', '#dc3545', '#0dcaf0'] 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function generarPDF() {
            const fecha_desde = '<?php echo date("Y-m-d", strtotime("-30 days")); ?>'; 
            const fecha_hasta = '<?php echo date("Y-m-d"); ?>';

            const url = `pdf_reportes_ingredientes.php?fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;

            window.open(url, '_blank');
        }
    </script>
</body>

</html>