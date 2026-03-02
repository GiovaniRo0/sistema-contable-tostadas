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

$query = "SELECT * FROM vista_historial_productos WHERE 1=1";
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

$cabeceras = ['Fecha', 'Tipo', 'Producto', 'Categoria', 'Precio', 'Descripcion'];
$datos = [];

foreach ($historial as $registro) {
    $datos[] = [
        date('d/m/Y H:i', strtotime($registro['fecha_cambio'])),
        $registro['tipo_registro_desc'],
        substr($registro['nombre_producto'], 0, 25),
        substr($registro['nombre_categoria'], 0, 15),
        '$' . number_format($registro['precio_base'], 2),
        substr($registro['descripcion'], 0, 35)
    ];
}

$anchos = [25, 25, 35, 20, 20, 65];
$alineaciones = ['C', 'C', 'L', 'L', 'R', 'L'];

$pdf = new PDFGenerator();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->setTitulo('HISTORIAL DE PRODUCTOS');
$pdf->setSubtitulo('Periodo: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)));

$filtros = [
    'fecha desde' => date('d/m/Y', strtotime($fecha_desde)),
    'fecha hasta' => date('d/m/Y', strtotime($fecha_hasta)),
    'tipo de registro' => $tipo_registro ?: 'Todos'
];

$pdf->agregarFiltros($filtros);

$total_registros = count($historial);
$nuevos_productos = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'nuevo_producto'; }));
$actualizaciones = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'actualizacion_producto'; }));
$eliminaciones = count(array_filter($historial, function($h) { return $h['tipo_registro'] === 'eliminacion_producto'; }));

$resumen = [
    'Total de registros' => $total_registros,
    'Productos nuevos' => $nuevos_productos,
    'Actualizaciones' => $actualizaciones,
    'Eliminaciones' => $eliminaciones
];

$pdf->agregarResumen($resumen);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Detalle del Historial', 0, 1);
$pdf->crearTabla($cabeceras, $datos, $anchos, $alineaciones);

$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, 'Total de registros encontrados: ' . $total_registros, 0, 1);

$pdf->Output('I', 'Historial_Productos_' . date('Y-m-d') . '.pdf');
?>