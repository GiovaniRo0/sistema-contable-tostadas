<?php

require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

$pdo = Conexion::ConexionBD();

function getBalanceMensual($pdo, $year = 2025)
{
    $sql_ingresos = "SELECT EXTRACT(MONTH FROM fecha_venta) as mes, 
                            SUM(total_venta) as ingreso
                     FROM ventas 
                     WHERE EXTRACT(YEAR FROM fecha_venta) = ?
                     GROUP BY EXTRACT(MONTH FROM fecha_venta)
                     ORDER BY mes";

    $stmt_ingresos = $pdo->prepare($sql_ingresos);
    $stmt_ingresos->execute([$year]);
    $ingresos = $stmt_ingresos->fetchAll(PDO::FETCH_ASSOC);

    $sql_egresos = "SELECT EXTRACT(MONTH FROM fecha_egreso) as mes, 
                           SUM(monto) as egreso
                    FROM egresos 
                    WHERE EXTRACT(YEAR FROM fecha_egreso) = ?
                    GROUP BY EXTRACT(MONTH FROM fecha_egreso)
                    ORDER BY mes";

    $stmt_egresos = $pdo->prepare($sql_egresos);
    $stmt_egresos->execute([$year]);
    $egresos = $stmt_egresos->fetchAll(PDO::FETCH_ASSOC);

    $balance = [];
    $saldo_acumulado = 0;

    for ($mes = 1; $mes <= 12; $mes++) {
        $ingreso = 0;
        $egreso = 0;

        foreach ($ingresos as $ing) {
            if ($ing['mes'] == $mes) {
                $ingreso = (float)$ing['ingreso'];
                break;
            }
        }

        foreach ($egresos as $egr) {
            if ($egr['mes'] == $mes) {
                $egreso = (float)$egr['egreso'];
                break;
            }
        }

        if ($ingreso > 0 || $egreso > 0 || $saldo_acumulado !== 0) {
            $saldo_mensual = $ingreso - $egreso;
            $saldo_acumulado += $saldo_mensual;

            $balance[] = [
                'mes' => $mes,
                'nombre_mes' => DateTime::createFromFormat('!m', $mes)->format('F'),
                'ingreso' => $ingreso,
                'egreso' => $egreso,
                'saldo_mensual' => $saldo_mensual,
                'saldo_acumulado' => $saldo_acumulado
            ];
        }
    }

    return $balance;
}

$year = isset($_POST['year']) ? (int)$_POST['year'] : 2025;
$balance_mensual = getBalanceMensual($pdo, $year);

$total_ingresos = array_sum(array_column($balance_mensual, 'ingreso'));
$total_egresos = array_sum(array_column($balance_mensual, 'egreso'));
$total_saldo = $total_ingresos - $total_egresos;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Mensual - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body>
    <?php
    $currentPage = 'balance_mensual';
    include 'menu.php';
    ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="h3 mb-0">
                <i class="bi bi-calendar-check text-primary me-2"></i> Balance Mensual del Año <?= $year ?>
            </h2>
            <a href="#" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </a>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-funnel text-primary"></i> Seleccionar Periodo</h6>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label for="year" class="form-label">Año a consultar</label>
                        <input type="number" id="year" name="year" class="form-control" value="<?= $year ?>" min="2020" max="2030" required>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary mt-4">
                            <i class="bi bi-graph-up"></i> Generar Reporte Mensual
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-table text-primary"></i> Detalle por Mes</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>MES</th>
                                <th>INGRESO</th>
                                <th>EGRESO</th>
                                <th>SALDO MENSUAL</th>
                                <th>SALDO ACUMULADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balance_mensual as $fila):
                                $clase_saldo = $fila['saldo_mensual'] >= 0 ? 'text-success' : 'text-danger';
                                $clase_acumulado = $fila['saldo_acumulado'] >= 0 ? 'text-success' : 'text-danger';
                            ?>
                                <tr>
                                    <td class="text-start"><strong><?= htmlspecialchars($fila['nombre_mes']) ?></strong></td>
                                    <td class="text-end">$<?= number_format($fila['ingreso'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($fila['egreso'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $clase_saldo ?>">$<?= number_format($fila['saldo_mensual'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $clase_acumulado ?>">$<?= number_format($fila['saldo_acumulado'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="table-primary fw-bold">
                                <td class="text-start">TOTAL <?= $year ?></td>
                                <td class="text-end">$<?= number_format($total_ingresos, 2) ?></td>
                                <td class="text-end">$<?= number_format($total_egresos, 2) ?></td>
                                <td class="text-end">$<?= number_format($total_saldo, 2) ?></td>
                                <td class="text-end">$<?= number_format($total_saldo, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <div class="card shadow-lg border-bottom border-5 border-success">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-clipboard-data text-success"></i> Resumen Ejecutivo Anual (<?= $year ?>)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h4 class="text-success"><i class="bi bi-arrow-up-circle-fill me-2"></i> Ingresos Totales</h4>
                        <p class="display-6 fw-bold">$<?= number_format($total_ingresos, 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-danger"><i class="bi bi-arrow-down-circle-fill me-2"></i> Egresos Totales</h4>
                        <p class="display-6 fw-bold">$<?= number_format($total_egresos, 2) ?></p>
                    </div>
                    <div class="col-md-4">
                        <h4 class="<?= $total_saldo >= 0 ? 'text-primary' : 'text-danger' ?>"><i class="bi bi-piggy-bank-fill me-2"></i> Utilidad Neta</h4>
                        <p class="display-6 fw-bold <?= $total_saldo >= 0 ? 'text-primary' : 'text-danger' ?>">
                            $<?= number_format($total_saldo, 2) ?>
                        </p>
                    </div>
                </div>
                <p class="text-muted mt-3">Meses con actividad registrados: <span class="badge bg-secondary"><?= count($balance_mensual) ?></span></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>