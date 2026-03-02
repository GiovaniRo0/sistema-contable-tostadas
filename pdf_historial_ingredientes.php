<?php
require_once('pdf_generator.php');
require_once('Conexion.php');
require_once('auth.php');

if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_registro = $_GET['tipo_registro'] ?? '';

$database = new Conexion();
$db = Conexion::ConexionBD();

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

$tipo_display = 'Todos';
switch($tipo_registro) {
    case 'ENTRADA_STOCK':
        $tipo_display = 'Entrada de Stock';
        break;
    case 'SALIDA_STOCK':
        $tipo_display = 'Salida de Stock';
        break;
    case 'ACTUALIZACION_PRECIO':
        $tipo_display = 'Actualización de Precio';
        break;
}

$cabeceras = ['FECHA', 'TIPO', 'INGREDIENTE', 'STOCK', 'Costo Unit.', 'P. Extra', 'DESCRIPCIÓN'];
$datos = [];

foreach ($historial as $registro) {
    $stock_indicador = $registro['stock'] . ' und';
    if ($registro['stock'] < 5 && $registro['stock'] > 0) {
        $stock_indicador = $registro['stock'] . ' und (BAJO)';
    } elseif ($registro['stock'] == 0) {
        $stock_indicador = $registro['stock'] . ' und (SIN STOCK)';
    }
    
    $datos[] = [
        date('d/m/Y', strtotime($registro['fecha_cambio'])), 
        $registro['tipo_registro_desc'],
        substr($registro['nombre_ingrediente'], 0, 30), 
        $stock_indicador,
        '$' . number_format($registro['costo_unitario'], 2),
        '$' . number_format($registro['precio_extra'], 2),
        substr($registro['descripcion'], 0, 35)
    ];
}


$anchos = [18, 25, 40, 18, 20, 22, 42]; 
$alineaciones = ['C', 'L', 'L', 'C', 'R', 'R', 'L'];

$pdf = new PDFGenerator();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->setTitulo('HISTORIAL DE INGREDIENTES');
$pdf->setSubtitulo('Periodo: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)));

$filtros = [
    'fecha desde' => date('d/m/Y', strtotime($fecha_desde)),
    'fecha hasta' => date('d/m/Y', strtotime($fecha_hasta)),
    'tipo de registro' => $tipo_display 
];

$pdf->agregarFiltros($filtros);

$total_registros = count($historial);
$entradas_stock = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'ENTRADA_STOCK'; }));
$salidas_stock = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'SALIDA_STOCK'; }));
$cambios_precio = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'ACTUALIZACION_PRECIO'; }));

$resumen = [
    'Total de registros' => $total_registros,
    'Entradas de stock' => $entradas_stock,
    'Salidas de stock' => $salidas_stock,
    'Cambios de precio' => $cambios_precio
];

$pdf->agregarResumen($resumen);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Detalle del Historial', 0, 1);
$pdf->crearTabla($cabeceras, $datos, $anchos, $alineaciones);

$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, 'Total de registros encontrados: ' . $total_registros, 0, 1);

$ingredientes_bajo_stock = array_filter($historial, function($h) { return $h['stock'] < 5 && $h['stock'] > 0; });
$ingredientes_sin_stock = array_filter($historial, function($h) { return $h['stock'] == 0; });

if (count($ingredientes_bajo_stock) > 0 || count($ingredientes_sin_stock) > 0) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, 'ALERTAS DE STOCK:', 0, 1);
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 9);
    
    if (count($ingredientes_sin_stock) > 0) {
        $pdf->Cell(0, 6, '- Ingredientes sin stock: ' . count($ingredientes_sin_stock), 0, 1);
    }
    if (count($ingredientes_bajo_stock) > 0) {
        $pdf->Cell(0, 6, '- Ingredientes con stock bajo: ' . count($ingredientes_bajo_stock), 0, 1);
    }
}

$pdf->Output('I', 'Historial_Ingredientes_' . date('Y-m-d') . '.pdf');
?>