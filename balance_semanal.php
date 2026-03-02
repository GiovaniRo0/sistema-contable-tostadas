<?php
require_once 'auth.php';
require_once __DIR__ . '/Conexion.php';

$pdo = Conexion::ConexionBD();

function getBalanceSemanal($pdo, $year = 2025)
{
    $sql_ingresos = "SELECT EXTRACT(WEEK FROM fecha_venta) as semana,
                            SUM(total_venta) as ingreso
                     FROM ventas 
                     WHERE EXTRACT(YEAR FROM fecha_venta) = ?
                     GROUP BY EXTRACT(WEEK FROM fecha_venta)
                     ORDER BY semana";

    $stmt_ingresos = $pdo->prepare($sql_ingresos);
    $stmt_ingresos->execute([$year]);
    $ingresos = $stmt_ingresos->fetchAll(PDO::FETCH_ASSOC);

    $sql_egresos = "SELECT EXTRACT(WEEK FROM fecha_egreso) as semana,
                           SUM(monto) as egreso
                    FROM egresos 
                    WHERE EXTRACT(YEAR FROM fecha_egreso) = ?
                    GROUP BY EXTRACT(WEEK FROM fecha_egreso)
                    ORDER BY semana";

    $stmt_egresos = $pdo->prepare($sql_egresos);
    $stmt_egresos->execute([$year]);
    $egresos = $stmt_egresos->fetchAll(PDO::FETCH_ASSOC);

    $balance = [];
    $saldo_acumulado = 0;

    $semanas_ingresos = array_column($ingresos, 'semana') ?: [0];
    $semanas_egresos = array_column($egresos, 'semana') ?: [0];
    $max_semana = max(max($semanas_ingresos), max($semanas_egresos), 52);

    for ($semana = 1; $semana <= $max_semana; $semana++) {
        $ingreso = 0;
        $egreso = 0;

        foreach ($ingresos as $ing) {
            if ($ing['semana'] == $semana) {
                $ingreso = (float)$ing['ingreso'];
                break;
            }
        }

        foreach ($egresos as $egr) {
            if ($egr['semana'] == $semana) {
                $egreso = (float)$egr['egreso'];
                break;
            }
        }

        if ($ingreso > 0 || $egreso > 0 || $saldo_acumulado !== 0) {
            $saldo_semanal = $ingreso - $egreso;
            $saldo_acumulado += $saldo_semanal;

            $balance[] = [
                'semana' => $semana,
                'ingreso' => $ingreso,
                'egreso' => $egreso,
                'saldo_semanal' => $saldo_semanal,
                'saldo_acumulado' => $saldo_acumulado
            ];
        }
    }

    return $balance;
}

$year = isset($_POST['year']) ? (int)$_POST['year'] : 2025;
$balance_semanal = getBalanceSemanal($pdo, $year);

$total_ingresos = array_sum(array_column($balance_semanal, 'ingreso'));
$total_egresos = array_sum(array_column($balance_semanal, 'egreso'));
$total_saldo = $total_ingresos - $total_egresos;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Semanal - Tostadas Jela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 0.75rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <?php
    $currentPage = 'balance_semanal'; 
    include 'menu.php';
    ?>
    
    <div class="container py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="h3 mb-0">
                <i class="bi bi-currency-dollar text-success me-2"></i> Balance Semanal del Año <?=$year?>
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
                            <i class="bi bi-graph-up"></i> Generar Reporte Semanal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-table text-primary"></i> Detalle por Semana</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>SEMANA</th>
                                <th>INGRESO</th>
                                <th>EGRESO</th>
                                <th>SALDO SEMANAL</th>
                                <th>SALDO ACUMULADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balance_semanal as $fila):
                                $clase_saldo = $fila['saldo_semanal'] >= 0 ? 'text-success' : 'text-danger';
                                $clase_acumulado = $fila['saldo_acumulado'] >= 0 ? 'text-success' : 'text-danger';
                            ?>
                                <tr>
                                    <td class="text-center"><strong>Semana <?= $fila['semana'] ?></strong></td>
                                    <td class="text-end">$<?= number_format($fila['ingreso'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($fila['egreso'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $clase_saldo ?>">$<?= number_format($fila['saldo_semanal'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $clase_acumulado ?>">$<?= number_format($fila['saldo_acumulado'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="table-primary fw-bold">
                                <td class="text-center">TOTAL <?= $year ?></td>
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


        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-clipboard-data text-primary"></i> Resumen Anual</h6>
            </div>
            <div class="card-body">
                <p><strong>Semanas con actividad:</strong> <span class="badge bg-secondary"><?= count($balance_semanal) ?></span></p>
                <p><strong>Ingresos Totales:</strong> <span class="text-success fw-bold">$<?= number_format($total_ingresos, 2) ?></span></p>
                <p><strong>Egresos Totales:</strong> <span class="text-danger fw-bold">$<?= number_format($total_egresos, 2) ?></span></p>
                <p class="h4 mt-3">
                    <strong>Utilidad Neta:</strong>
                    <span class="ms-3 fw-bold <?= $total_saldo >= 0 ? 'text-success' : 'text-danger' ?>">
                        $<?= number_format($total_saldo, 2) ?>
                    </span>
                </p>
                <p class="text-muted mt-2">Promedio semanal de ingresos: $<?= number_format($total_ingresos / max(1, count($balance_semanal)), 2) ?></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>