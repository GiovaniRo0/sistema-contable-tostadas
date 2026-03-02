<?php
require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

$pdo = Conexion::ConexionBD();

function getMetricasRapidas($pdo, $year = null, $month = null) {
    if (!$year) $year = date('Y');
    if (!$month) $month = date('n');
    
    $sql_ventas = "SELECT 
                    COUNT(*) as total_ventas,
                    SUM(total_venta) as ingresos_totales,
                    AVG(total_venta) as promedio_venta
                   FROM ventas 
                   WHERE EXTRACT(YEAR FROM fecha_venta) = ? 
                   AND EXTRACT(MONTH FROM fecha_venta) = ?";
    
    $stmt = $pdo->prepare($sql_ventas);
    $stmt->execute([$year, $month]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql_egresos = "SELECT SUM(monto) as egresos_totales
                    FROM egresos 
                    WHERE EXTRACT(YEAR FROM fecha_egreso) = ? 
                    AND EXTRACT(MONTH FROM fecha_egreso) = ?";
    
    $stmt = $pdo->prepare($sql_egresos);
    $stmt->execute([$year, $month]);
    $egresos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql_productos = "SELECT COUNT(*) as total_productos
                      FROM det_ventas dv
                      JOIN ventas v ON dv.id_venta = v.id_venta
                      WHERE EXTRACT(YEAR FROM v.fecha_venta) = ? 
                      AND EXTRACT(MONTH FROM v.fecha_venta) = ?";
    
    $stmt = $pdo->prepare($sql_productos);
    $stmt->execute([$year, $month]);
    $productos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql_stock = "SELECT COUNT(*) as bajo_stock 
                  FROM ingredientes 
                  WHERE stock <= 10 AND activo = true";
    
    $stmt = $pdo->prepare($sql_stock);
    $stmt->execute();
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'ventas' => $ventas,
        'egresos' => $egresos,
        'productos' => $productos,
        'stock' => $stock,
        'utilidad' => ($ventas['ingresos_totales'] ?? 0) - ($egresos['egresos_totales'] ?? 0)
    ];
}

function getTopProductos($pdo, $year = null, $month = null, $limit = 5) {
    if (!$year) $year = date('Y');
    if (!$month) $month = date('n');
    
    $sql = "SELECT 
                p.nombre_producto,
                COUNT(dv.id_det_venta) as cantidad,
                SUM(dv.subtotal) as total_ventas
            FROM det_ventas dv
            JOIN productos p ON dv.id_producto = p.id_producto
            JOIN ventas v ON dv.id_venta = v.id_venta
            WHERE EXTRACT(YEAR FROM v.fecha_venta) = ? 
            AND EXTRACT(MONTH FROM v.fecha_venta) = ?
            GROUP BY p.nombre_producto
            ORDER BY cantidad DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVentasUltimosMeses($pdo, $months = 6) {
    $sql = "SELECT 
                TO_CHAR(fecha_venta, 'YYYY-MM') as mes,
                EXTRACT(YEAR FROM fecha_venta) as año,
                EXTRACT(MONTH FROM fecha_venta) as mes_num,
                COUNT(*) as total_ventas,
                SUM(total_venta) as ingresos
            FROM ventas 
            WHERE fecha_venta >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '{$months} months')
            GROUP BY TO_CHAR(fecha_venta, 'YYYY-MM'), EXTRACT(YEAR FROM fecha_venta), EXTRACT(MONTH FROM fecha_venta)
            ORDER BY año, mes_num";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$metricas = getMetricasRapidas($pdo);
$top_productos = getTopProductos($pdo);
$ventas_mensuales = getVentasUltimosMeses($pdo);


$meses_grafico = [];
$ventas_grafico = [];
$ingresos_grafico = [];

foreach ($ventas_mensuales as $mes) {
    $mes_nombre = DateTime::createFromFormat('!m', $mes['mes_num'])->format('M');
    $meses_grafico[] = "'{$mes_nombre}'";
    $ventas_grafico[] = $mes['total_ventas'];
    $ingresos_grafico[] = $mes['ingresos'];
}

$meses_js = '[' . implode(', ', $meses_grafico) . ']';
$ventas_js = '[' . implode(', ', $ventas_grafico) . ']';
$ingresos_js = '[' . implode(', ', $ingresos_grafico) . ']';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Principal - Tostadas Jela</title>
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
        .metric-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .card a:hover {
            color: inherit !important;
        }
    </style>
</head>
<body>
    <?php
    $currentPage = 'dashboard'; 
    include 'menu.php';
    ?>
    
    <div class="container py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-5 border-bottom pb-2">
            <h2 class="h3 mb-0">
                <i class="bi bi-speedometer2 text-primary me-2"></i> Dashboard Principal
            </h2>
            <span class="badge bg-secondary">Vista ejecutiva del mes de <?= date('F Y') ?></span>
        </div>

        <div class="row g-4 mb-5">
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-start border-success border-4 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-dollar text-success display-4 mb-3"></i>
                        <div class="metric-number text-success">$<?= number_format($metricas['ventas']['ingresos_totales'] ?? 0, 2) ?></div>
                        <div class="text-muted text-uppercase fw-bold small">Ingresos del Mes</div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-start border-primary border-4 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-receipt text-primary display-4 mb-3"></i>
                        <div class="metric-number text-primary"><?= number_format($metricas['ventas']['total_ventas'] ?? 0) ?></div>
                        <div class="text-muted text-uppercase fw-bold small">Ventas Realizadas</div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-start border-info border-4 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-box-seam text-info display-4 mb-3"></i>
                        <div class="metric-number text-info"><?= number_format($metricas['productos']['total_productos'] ?? 0) ?></div>
                        <div class="text-muted text-uppercase fw-bold small">Productos Vendidos</div>
                    </div>
                </div>
            </div>
            
            <?php $utilidad_color = ($metricas['utilidad'] ?? 0) >= 0 ? 'success' : 'danger'; ?>
            <div class="col-lg-3 col-md-6">
                <div class="card border-start border-<?= $utilidad_color ?> border-4 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-piggy-bank text-<?= $utilidad_color ?> display-4 mb-3"></i>
                        <div class="metric-number text-<?= $utilidad_color ?>">
                            $<?= number_format($metricas['utilidad'] ?? 0, 2) ?>
                        </div>
                        <div class="text-muted text-uppercase fw-bold small">Utilidad Neta</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="bi bi-graph-up text-primary me-2"></i> Tendencia de Ventas - Últimos 6 Meses</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ventasChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i> Top 5 Productos del Mes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Ventas</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_productos as $i => $producto): ?>
                                    <tr class="<?= $i === 0 ? 'table-warning fw-bold' : '' ?>">
                                        <td><?= htmlspecialchars($producto['nombre_producto']) ?></td>
                                        <td class="text-end"><?= number_format($producto['cantidad']) ?></td>
                                        <td class="text-end text-success">$<?= number_format($producto['total_ventas'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-lightning-charge-fill text-danger me-2"></i> Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="row row-cols-2 row-cols-md-4 g-4">
                    
                    <div class="col">
                        <a href="balance_mensual.php" class="card text-center text-decoration-none h-100 border-primary shadow-sm bg-light">
                            <div class="card-body">
                                <i class="bi bi-bar-chart-fill display-5 text-primary"></i>
                                <h6 class="card-title mt-2 mb-0">Balance Mensual</h6>
                                <small class="text-muted">Ver reporte completo</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col">
                        <a href="conteo_productos.php" class="card text-center text-decoration-none h-100 border-info shadow-sm bg-light">
                            <div class="card-body">
                                <i class="bi bi-cup-hot display-5 text-info"></i>
                                <h6 class="card-title mt-2 mb-0">Conteo de Productos</h6>
                                <small class="text-muted">Análisis detallado</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col">
                        <a href="control_ingredientes.php" class="card text-center text-decoration-none h-100 border-success shadow-sm bg-light">
                            <div class="card-body">
                                <i class="bi bi-cart-fill display-5 text-success"></i>
                                <h6 class="card-title mt-2 mb-0">Control de Ingredientes</h6>
                                <small class="text-muted">Stock y alertas</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col">
                        <a href="balance_semanal.php" class="card text-center text-decoration-none h-100 border-warning shadow-sm bg-light">
                            <div class="card-body">
                                <i class="bi bi-calendar-week display-5 text-warning"></i>
                                <h6 class="card-title mt-2 mb-0">Balance Semanal</h6>
                                <small class="text-muted">Control detallado</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-clipboard-check-fill text-success me-2"></i> Resumen Ejecutivo</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="border-start border-4 border-success ps-3">
                            <h4 class="text-success mb-3"><i class="bi bi-cash-stack"></i> Rentabilidad</h4>
                            <p class="mb-1"><strong>Ingresos:</strong> <span class="text-success">$<?= number_format($metricas['ventas']['ingresos_totales'] ?? 0, 2) ?></span></p>
                            <p class="mb-1"><strong>Egresos:</strong> <span class="text-danger">$<?= number_format($metricas['egresos']['egresos_totales'] ?? 0, 2) ?></span></p>
                            <p class="mb-1"><strong>Utilidad:</strong> 
                                <span class="fw-bold <?= ($metricas['utilidad'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                    $<?= number_format($metricas['utilidad'] ?? 0, 2) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="border-start border-4 border-primary ps-3">
                            <h4 class="text-primary mb-3"><i class="bi bi-bar-chart-line-fill"></i> Métricas de Ventas</h4>
                            <p class="mb-1"><strong>Total ventas:</strong> <?= number_format($metricas['ventas']['total_ventas'] ?? 0) ?></p>
                            <p class="mb-1"><strong>Productos vendidos:</strong> <?= number_format($metricas['productos']['total_productos'] ?? 0) ?></p>
                            <p class="mb-1"><strong>Ticket promedio:</strong> $<?= number_format($metricas['ventas']['promedio_venta'] ?? 0, 2) ?></p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="border-start border-4 border-warning ps-3">
                            <h4 class="text-warning mb-3"><i class="bi bi-bell-fill"></i> Alertas</h4>
                            <?php $stock_class = ($metricas['stock']['bajo_stock'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>
                            <p class="mb-1"><strong>Ingredientes bajo stock:</strong> 
                                <span class="fw-bold <?= $stock_class ?>">
                                    <?= $metricas['stock']['bajo_stock'] ?? 0 ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Egresos del mes:</strong> <span class="text-danger">$<?= number_format($metricas['egresos']['egresos_totales'] ?? 0, 2) ?></span></p>
                            <p class="mb-1"><strong>Estado general:</strong> 
                                <span class="text-success">✅ Optimo</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('ventasChart').getContext('2d');
        const ventasChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= $meses_js ?>,
                datasets: [
                    {
                        label: 'Ingresos ($)',
                        data: <?= $ingresos_js ?>,
                        borderColor: '#0d6efd', 
                        backgroundColor: 'rgba(13, 110, 253, 0.1)', 
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'N° de Ventas',
                        data: <?= $ventas_js ?>,
                        borderColor: '#0dcaf0', 
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, 
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false, 
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>