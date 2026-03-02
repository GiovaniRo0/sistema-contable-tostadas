<?php
$currentPage = 'reportes_productos';
include 'menu.php';
include 'Conexion.php';
require_once 'auth.php';

$database = new Conexion();
$db = Conexion::ConexionBD();

$query_actividad = "SELECT * FROM vista_reporte_actividad_productos 
                   WHERE fecha >= CURRENT_DATE - INTERVAL '30 days'
                   ORDER BY fecha DESC, tipo_registro";
$stmt_actividad = $db->prepare($query_actividad);
$stmt_actividad->execute();
$actividad_diaria = $stmt_actividad->fetchAll(PDO::FETCH_ASSOC);

$query_mensual = "SELECT * FROM vista_resumen_mensual_productos 
                 ORDER BY mes DESC";
$stmt_mensual = $db->prepare($query_mensual);
$stmt_mensual->execute();
$resumen_mensual = $stmt_mensual->fetchAll(PDO::FETCH_ASSOC);

// Función de ayuda para clases de badge (La misma que tenías al final)
function getBadgeClass($tipo)
{
    $classes = [
        'nuevo_producto' => 'bg-success',
        'actualizacion_producto' => 'bg-info',
        'eliminacion_producto' => 'bg-warning'
    ];
    return $classes[$tipo] ?? 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Productos - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card {
            border-radius: 0.75rem; /* Bordes más suaves */
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px); /* Efecto hover sutil */
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
                        <i class="bi bi-graph-up text-primary me-2"></i> Reportes de Productos
                    </h2>
                    <div>
                        <a href="historial_productos.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-clock-history"></i> Historial
                        </a>
                        <button class="btn btn-danger" onclick="generarPDF()">
                            <i class="bi bi-file-pdf"></i> Generar Reporte PDF
                        </button>
                    </div>
                </div>

                <?php
                $total_nuevos = 0;
                $total_actualizaciones = 0;
                $total_eliminaciones = 0;

                foreach ($resumen_mensual as $mes) {
                    if ($mes['tipo_registro'] === 'nuevo_producto') $total_nuevos += $mes['total_operaciones'];
                    if ($mes['tipo_registro'] === 'actualizacion_producto') $total_actualizaciones += $mes['total_operaciones'];
                    if ($mes['tipo_registro'] === 'eliminacion_producto') $total_eliminaciones += $mes['total_operaciones'];
                }
                ?>
                <div class="row mb-5">
                    
                    <div class="col-md-3">
                        <div class="card border-primary text-primary shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_nuevos + $total_actualizaciones + $total_eliminaciones; ?></h3>
                                <p class="mb-0 text-muted">Total Operaciones</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-success text-success shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_nuevos; ?></h3>
                                <p class="mb-0 text-muted">Productos Nuevos</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-info text-info shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_actualizaciones; ?></h3>
                                <p class="mb-0 text-muted">Actualizaciones</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-warning text-warning shadow-sm h-100">
                            <div class="card-body text-center">
                                <h3 class="display-5 fw-bold"><?php echo $total_eliminaciones; ?></h3>
                                <p class="mb-0 text-muted">Eliminaciones</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom">
                                <h6 class="mb-0"><i class="bi bi-bar-chart text-primary"></i> Actividad de los Últimos 30 Días</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoActividad" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
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
                        <h6 class="mb-0"><i class="bi bi-calendar-week text-primary"></i> Actividad Diaria (Últimos 30 Días)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo de Registro</th>
                                        <th>Cantidad</th>
                                        <th>Productos Afectados</th>
                                        <th>Usuarios Involucrados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($actividad_diaria as $actividad): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($actividad['fecha'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo getBadgeClass($actividad['tipo_registro']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $actividad['tipo_registro'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $actividad['cantidad']; ?></td>
                                            <td><?php echo $actividad['productos_afectados']; ?></td>
                                            <td><?php echo $actividad['usuarios_involucrados']; ?></td>
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
                                        <th>Productos Afectados</th>
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
                                            <td><?php echo $mes['productos_afectados']; ?></td>
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
        // ... (Tu código de Chart.js y PHP se mantiene igual aquí, ya que es funcional)
        
        const ctxActividad = document.getElementById('graficoActividad').getContext('2d');
        new Chart(ctxActividad, {
            type: 'line',
            data: {
                labels: [<?php
                            $fechas = array_unique(array_column($actividad_diaria, 'fecha'));
                            sort($fechas);
                            echo '"' . implode('","', array_map(function ($f) {
                                return date('d/m', strtotime($f));
                            }, $fechas)) . '"';
                            ?>],
                datasets: [{
                        label: 'Nuevos Productos',
                        data: [<?php
                                $data = [];
                                foreach ($fechas as $fecha) {
                                    $valor = 0;
                                    foreach ($actividad_diaria as $act) {
                                        if ($act['fecha'] === $fecha && $act['tipo_registro'] === 'nuevo_producto') {
                                            $valor = $act['cantidad'];
                                            break;
                                        }
                                    }
                                    $data[] = $valor;
                                }
                                echo implode(',', $data);
                                ?>],
                        borderColor: '#198754', // success
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Actualizaciones',
                        data: [<?php
                                $data = [];
                                foreach ($fechas as $fecha) {
                                    $valor = 0;
                                    foreach ($actividad_diaria as $act) {
                                        if ($act['fecha'] === $fecha && $act['tipo_registro'] === 'actualizacion_producto') {
                                            $valor = $act['cantidad'];
                                            break;
                                        }
                                    }
                                    $data[] = $valor;
                                }
                                echo implode(',', $data);
                                ?>],
                        borderColor: '#0dcaf0', // info
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Permite que 'height' de canvas sea más efectivo
                plugins: {
                    title: {
                        display: false, // Desactivamos el título ya que lo pusimos en el card-header
                        text: 'Actividad Diaria de Productos'
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });


        const ctxDistribucion = document.getElementById('graficoDistribucion').getContext('2d');
        new Chart(ctxDistribucion, {
            type: 'doughnut',
            data: {
                labels: ['Nuevos Productos', 'Actualizaciones', 'Eliminaciones'],
                datasets: [{
                    data: [<?php echo "$total_nuevos, $total_actualizaciones, $total_eliminaciones"; ?>],
                    backgroundColor: ['#198754', '#0dcaf0', '#ffc107'] // success, info, warning
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
            // Se mantiene igual, pero los inputs de fecha ya no son necesarios en este diseño
            const fecha_desde = '<?php echo date("Y-m-d", strtotime("-30 days")); ?>'; // Usamos la fecha de la consulta por defecto
            const fecha_hasta = '<?php echo date("Y-m-d"); ?>';

            const url = `pdf_reportes_productos.php?fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;

            window.open(url, '_blank');
        }
    </script>
</body>

</html>