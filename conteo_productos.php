<?php

require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

$pdo = Conexion::ConexionBD();

// --- Lógica de Paginación ---
$results_per_page = 5; // Número de productos por página
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $results_per_page;
// --------------------------

function getTotalProductosCount($pdo, $year = 2025, $month = null) {
    // Cuenta el total de productos distintos en el periodo para calcular las páginas
    $sql = "SELECT COUNT(DISTINCT dv.id_producto)
            FROM det_ventas dv
            JOIN ventas v ON dv.id_venta = v.id_venta
            WHERE EXTRACT(YEAR FROM v.fecha_venta) = ?";

    $params = [$year];

    if ($month) {
        $sql .= " AND EXTRACT(MONTH FROM v.fecha_venta) = ?";
        $params[] = $month;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}


function getProductosConExtras($pdo, $year = 2025, $month = null, $limit = null, $offset = 0) {
    $sql = "SELECT 
                p.nombre_producto,
                c.nombre_categoria,
                COUNT(dv.id_det_venta) as cantidad_ventas,
                SUM(dv.subtotal) as subtotal_producto,
                p.precio_base,
                
                SUM(dv.subtotal + COALESCE(extras.total_extras, 0)) as total_con_extras,
                
                COALESCE(SUM(extras.cantidad_extras), 0) as total_extras_vendidos,
                COALESCE(SUM(extras.total_extras), 0) as total_ingresos_extras
                
            FROM det_ventas dv
            JOIN productos p ON dv.id_producto = p.id_producto
            JOIN categorias c ON p.id_categoria = c.id_categoria
            JOIN ventas v ON dv.id_venta = v.id_venta
            
            LEFT JOIN (
                SELECT 
                    dve.id_det_venta,
                    COUNT(dve.id_det_extra) as cantidad_extras,
                    SUM(dve.precio_extra * dve.cantidad) as total_extras
                FROM det_ven_extras dve
                GROUP BY dve.id_det_venta
            ) extras ON dv.id_det_venta = extras.id_det_venta
            
            WHERE EXTRACT(YEAR FROM v.fecha_venta) = ?";
    
    $params = [$year];
    
    if ($month) {
        $sql .= " AND EXTRACT(MONTH FROM v.fecha_venta) = ?";
        $params[] = $month;
    }
    
    $sql .= " GROUP BY p.nombre_producto, c.nombre_categoria, p.precio_base
              ORDER BY c.nombre_categoria ASC, cantidad_ventas DESC"; 

    // Añadir LIMIT y OFFSET para la paginación
    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExtrasSolo($pdo, $year = 2025, $month = null) {
    // Esta función no se modifica ya que no afecta la tabla principal de productos.
    $sql = "SELECT 
                i.nombre_ingrediente,
                COUNT(dve.id_det_extra) as cantidad_vendida,
                SUM(dve.precio_extra * dve.cantidad) as total_ventas,
                i.precio_extra as precio_unitario
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
    
    $sql .= " GROUP BY i.nombre_ingrediente, i.precio_extra
              ORDER BY cantidad_vendida DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// *** MODIFICADO: Leer filtros de GET para consistencia con la paginación ***
$year = isset($_GET['year']) ? (int)$_GET['year'] : 2025;
$month = isset($_GET['month']) && $_GET['month'] != '' ? (int)$_GET['month'] : null;


// --- Ejecución de las funciones con la lógica de paginación ---
$total_products = getTotalProductosCount($pdo, $year, $month);
$total_pages = ceil($total_products / $results_per_page);

// Obtener solo los productos de la página actual
$productos = getProductosConExtras($pdo, $year, $month, $results_per_page, $offset);
$extras_solo = getExtrasSolo($pdo, $year, $month); // Esta función permanece igual.
// ---------------------------------------------------------------


$total_ventas_con_extras = array_sum(array_column($productos, 'total_con_extras'));
$total_ingresos_extras = array_sum(array_column($productos, 'total_ingresos_extras'));
$total_ventas_sin_extras = array_sum(array_column($productos, 'subtotal_producto'));

$categorias = [];
foreach ($productos as $producto) {
    $categoria = $producto['nombre_categoria'];
    if (!isset($categorias[$categoria])) {
        $categorias[$categoria] = [
            'ventas_con_extras' => 0,
            'ventas_sin_extras' => 0,
            'ingresos_extras' => 0,
            'productos' => 0
        ];
    }
    $categorias[$categoria]['ventas_con_extras'] += $producto['total_con_extras'];
    $categorias[$categoria]['ventas_sin_extras'] += $producto['subtotal_producto'];
    $categorias[$categoria]['ingresos_extras'] += $producto['total_ingresos_extras'];
    $categorias[$categoria]['productos']++;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Productos y Extras - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-extra-income {
            background-color: #e3f2fd; 
        }
    </style>
</head>
<body>
    <?php
    $currentPage = 'conteo_productos'; 
    include 'menu.php';
    ?>
    
    <div class="container py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="h3 mb-0">
                <i class="bi bi-cart-plus-fill text-info me-2"></i> Productos Vendidos (Incluye Extras)
            </h2>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-funnel text-primary"></i> Seleccionar Periodo</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label for="year" class="form-label">Año</label>
                        <input type="number" id="year" name="year" class="form-control" value="<?= $year ?>" min="2020" max="2030" required>
                    </div>

                    <div class="col-md-3">
                        <label for="month" class="form-label">Mes (opcional)</label>
                        <select id="month" name="month" class="form-select">
                            <option value="">Todos los meses</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $month == $i ? 'selected' : '' ?>>
                                    <?= DateTime::createFromFormat('!m', $i)->format('F') ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary mt-4">
                            <i class="bi bi-search"></i> Generar Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-list-ul text-info"></i> Detalle por Producto y Desglose de Extras
                    <span class="badge bg-secondary ms-2"><?= $total_products ?> productos en total</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th class="text-start">CATEGORÍA</th>
                                <th class="text-start">PRODUCTO</th>
                                <th>PRECIO BASE</th>
                                <th>VENTAS (unid.)</th>
                                <th>SUBTOTAL BASE</th>
                                <th class="table-info">INGRESOS EXTRAS</th>
                                <th>TOTAL CON EXTRAS</th>
                                <th>% EXTRAS / BASE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $categoria_actual = '';
                            foreach ($productos as $index => $producto): 
                                $porcentaje_extras = $producto['subtotal_producto'] > 0 ? 
                                    ($producto['total_ingresos_extras'] / $producto['subtotal_producto']) * 100 : 0;
                                
                                if ($categoria_actual != $producto['nombre_categoria']) {
                                    $categoria_actual = $producto['nombre_categoria'];
                            ?>
                            <tr class="table-primary fw-bold">
                                <td colspan="8" class="text-start">
                                    <i class="bi bi-folder-fill me-2"></i> CATEGORÍA: <?= htmlspecialchars($categoria_actual) ?>
                                </td>
                            </tr>
                            <?php } ?>
                            
                            <tr class="<?= $index < 3 ? 'table-warning' : '' ?>">
                                <td class="text-start"><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                <td class="text-start fw-bold"><?= htmlspecialchars($producto['nombre_producto']) ?></td>
                                <td class="text-end">$<?= number_format($producto['precio_base'], 2) ?></td>
                                <td class="text-end"><?= number_format($producto['cantidad_ventas']) ?></td>
                                <td class="text-end">$<?= number_format($producto['subtotal_producto'], 2) ?></td>
                                <td class="text-end table-extra-income fw-bold text-info">$<?= number_format($producto['total_ingresos_extras'], 2) ?></td>
                                <td class="text-end fw-bold text-success">$<?= number_format($producto['total_con_extras'], 2) ?></td>
                                <td class="text-end"><?= number_format($porcentaje_extras, 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <tr class="table-dark fw-bold">
                                <td colspan="4" class="text-end"><strong>TOTAL PÁGINA</strong></td>
                                <td class="text-end"><strong>$<?= number_format($total_ventas_sin_extras, 2) ?></strong></td>
                                <td class="text-end table-extra-income text-info"><strong>$<?= number_format($total_ingresos_extras, 2) ?></strong></td>
                                <td class="text-end text-success"><strong>$<?= number_format($total_ventas_con_extras, 2) ?></strong></td>
                                <td class="text-end"><strong>-</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-center">
                <nav aria-label="Navegación de Productos">
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?>&year=<?= $year ?>&month=<?= $month ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo; Anterior</span>
                            </a>
                        </li>
                        
                        <?php 
                        // Lógica para mostrar solo un rango de páginas
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);

                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&year=<?= $year ?>&month=<?= $month ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                        
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?>&year=<?= $year ?>&month=<?= $month ?>" aria-label="Next">
                                <span aria-hidden="true">Siguiente &raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-bar-chart-fill text-primary"></i> Resumen Agrupado por Categoría</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th class="text-start">CATEGORÍA</th>
                                <th>PRODUCTOS DIFERENTES</th>
                                <th>VENTAS BASE</th>
                                <th class="table-info">INGRESOS EXTRAS</th>
                                <th>TOTAL CON EXTRAS</th>
                                <th>% EXTRAS (del Total)</th>
                                <th>PROMEDIO EXTRAS/PRODUCTO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $categoria_nombre => $datos): 
                                $porcentaje_extras_total = $datos['ventas_con_extras'] > 0 ? 
                                    ($datos['ingresos_extras'] / $datos['ventas_con_extras']) * 100 : 0;
                                $promedio_extras = $datos['productos'] > 0 ? 
                                    $datos['ingresos_extras'] / $datos['productos'] : 0;
                            ?>
                            <tr>
                                <td class="text-start fw-bold"><?= htmlspecialchars($categoria_nombre) ?></td>
                                <td class="text-end"><?= number_format($datos['productos']) ?></td>
                                <td class="text-end">$<?= number_format($datos['ventas_sin_extras'], 2) ?></td>
                                <td class="text-end table-extra-income fw-bold text-info">$<?= number_format($datos['ingresos_extras'], 2) ?></td>
                                <td class="text-end fw-bold text-success">$<?= number_format($datos['ventas_con_extras'], 2) ?></td>
                                <td class="text-end"><?= number_format($porcentaje_extras_total, 1) ?>%</td>
                                <td class="text-end">$<?= number_format($promedio_extras, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-info">
            <div class="card-body bg-light">
                <h5 class="card-title text-info"><i class="bi bi-box-seam-fill me-2"></i> Resumen Ejecutivo del Periodo (Página Actual)</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="text-success"><i class="bi bi-cash-stack me-2"></i> Ingresos Totales</h4>
                        <p class="mb-1"><strong>Ventas base productos (Pág. Actual):</strong> <span class="fw-bold">$<?= number_format($total_ventas_sin_extras, 2) ?></span></p>
                        <p class="mb-1"><strong>Ingresos por extras (Pág. Actual):</strong> <span class="fw-bold text-info">$<?= number_format($total_ingresos_extras, 2) ?></span></p>
                        <p class="mb-1"><strong>Total con extras (Pág. Actual):</strong> <span class="fw-bold text-success display-6">$<?= number_format($total_ventas_con_extras, 2) ?></span></p>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="text-primary"><i class="bi bi-bar-chart-line-fill me-2"></i> Métricas de Eficiencia</h4>
                        <p class="mb-1"><strong>Productos diferentes vendidos (Pág. Actual):</strong> <span class="badge bg-secondary"><?= count($productos) ?></span></p>
                        <p class="mb-1"><strong>% que representan los extras (Pág. Actual):</strong> 
                            <span class="fw-bold text-info"><?= number_format(($total_ingresos_extras / max(1, $total_ventas_con_extras)) * 100, 1) ?>%</span> del total.
                        </p>
                        <p class="mb-1"><strong>Promedio Ingreso Extra/Producto (Pág. Actual):</strong> <span class="fw-bold">$<?= number_format($total_ingresos_extras / max(1, count($productos)), 2) ?></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>