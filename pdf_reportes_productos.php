<?php
require_once('pdf_generator.php');
require_once('Conexion.php');
require_once('auth.php');

if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'general';

$database = new Conexion();
$db = Conexion::ConexionBD();

$datos_reporte = [];

$query_general = "
    SELECT 
        COUNT(*) as total_registros,
        COUNT(DISTINCT id_referencia) as productos_afectados,
        COUNT(DISTINCT id_usuario) as usuarios_involucrados,
        SUM(CASE WHEN tipo_registro = 'nuevo_producto' THEN 1 ELSE 0 END) as nuevos_productos,
        SUM(CASE WHEN tipo_registro = 'actualizacion_producto' THEN 1 ELSE 0 END) as actualizaciones,
        SUM(CASE WHEN tipo_registro = 'eliminacion_producto' THEN 1 ELSE 0 END) as eliminaciones
    FROM historial 
    WHERE tabla_referencia = 'productos' 
    AND fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
";

$stmt_general = $db->prepare($query_general);
$stmt_general->bindValue(':fecha_desde', $fecha_desde);
$stmt_general->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_general->execute();
$datos_reporte['general'] = $stmt_general->fetch(PDO::FETCH_ASSOC);

$query_categorias = "
    SELECT 
        c.nombre_categoria,
        COUNT(h.id_historial) as total_operaciones,
        COUNT(DISTINCT h.id_referencia) as productos_afectados
    FROM historial h
    INNER JOIN productos p ON h.id_referencia = p.id_producto
    INNER JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE h.tabla_referencia = 'productos'
    AND h.fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY c.id_categoria, c.nombre_categoria
    ORDER BY total_operaciones DESC
";

$stmt_categorias = $db->prepare($query_categorias);
$stmt_categorias->bindValue(':fecha_desde', $fecha_desde);
$stmt_categorias->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_categorias->execute();
$datos_reporte['categorias'] = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

$query_top_productos = "
    SELECT 
        p.nombre_producto,
        c.nombre_categoria,
        COUNT(h.id_historial) as total_modificaciones,
        SUM(CASE WHEN h.tipo_registro = 'actualizacion_producto' THEN 1 ELSE 0 END) as actualizaciones,
        p.precio_base
    FROM historial h
    INNER JOIN productos p ON h.id_referencia = p.id_producto
    INNER JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE h.tabla_referencia = 'productos'
    AND h.fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY p.id_producto, p.nombre_producto, c.nombre_categoria, p.precio_base
    ORDER BY total_modificaciones DESC
    LIMIT 10
";

$stmt_top = $db->prepare($query_top_productos);
$stmt_top->bindValue(':fecha_desde', $fecha_desde);
$stmt_top->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_top->execute();
$datos_reporte['top_productos'] = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

$query_mensual = "
    SELECT 
        TO_CHAR(fecha_cambio, 'YYYY-MM') as mes,
        TO_CHAR(fecha_cambio, 'Month YYYY') as mes_nombre,
        COUNT(*) as total_operaciones,
        COUNT(DISTINCT id_referencia) as productos_afectados,
        SUM(CASE WHEN tipo_registro = 'nuevo_producto' THEN 1 ELSE 0 END) as nuevos,
        SUM(CASE WHEN tipo_registro = 'actualizacion_producto' THEN 1 ELSE 0 END) as actualizaciones,
        SUM(CASE WHEN tipo_registro = 'eliminacion_producto' THEN 1 ELSE 0 END) as eliminaciones
    FROM historial 
    WHERE tabla_referencia = 'productos'
    AND fecha_cambio >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '5 months')
    GROUP BY TO_CHAR(fecha_cambio, 'YYYY-MM'), TO_CHAR(fecha_cambio, 'Month YYYY')
    ORDER BY mes DESC
";

$stmt_mensual = $db->prepare($query_mensual);
$stmt_mensual->execute();
$datos_reporte['mensual'] = $stmt_mensual->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDFGenerator();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->setTitulo('REPORTES DE PRODUCTOS');
$pdf->setSubtitulo('Periodo: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)));

$filtros = [
    'fecha desde' => date('d/m/Y', strtotime($fecha_desde)),
    'fecha hasta' => date('d/m/Y', strtotime($fecha_hasta)),
    'tipo de reporte' => 'General'
];

$pdf->agregarFiltros($filtros);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '1. RESUMEN GENERAL', 0, 1);
$pdf->SetTextColor(0);
$pdf->Ln(2);

$general = $datos_reporte['general'];
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Estadisticas de Actividad:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, '- Total de registros:', 0, 0);
$pdf->Cell(0, 6, $general['total_registros'], 0, 1);

$pdf->Cell(95, 6, '- Productos afectados:', 0, 0);
$pdf->Cell(0, 6, $general['productos_afectados'], 0, 1);

$pdf->Cell(95, 6, '- Usuarios involucrados:', 0, 0);
$pdf->Cell(0, 6, $general['usuarios_involucrados'], 0, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Distribucion por Tipo de Operacion:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, '- Productos nuevos:', 0, 0);
$pdf->Cell(0, 6, $general['nuevos_productos'] . ' (' . round(($general['nuevos_productos'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Cell(95, 6, '- Actualizaciones:', 0, 0);
$pdf->Cell(0, 6, $general['actualizaciones'] . ' (' . round(($general['actualizaciones'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Cell(95, 6, '- Eliminaciones:', 0, 0);
$pdf->Cell(0, 6, $general['eliminaciones'] . ' (' . round(($general['eliminaciones'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '2. ACTIVIDAD POR CATEGORIA', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['categorias'])) {
    $cabeceras_categorias = ['Categoria', 'Operaciones', 'Productos Afectados', '% del Total'];
    $datos_categorias = [];
    
    $total_operaciones = $general['total_registros'];
    
    foreach ($datos_reporte['categorias'] as $categoria) {
        $porcentaje = $total_operaciones > 0 ? round(($categoria['total_operaciones'] / $total_operaciones) * 100, 1) : 0;
        $datos_categorias[] = [
            substr($categoria['nombre_categoria'], 0, 25),
            $categoria['total_operaciones'],
            $categoria['productos_afectados'],
            $porcentaje . '%'
        ];
    }
    
    $anchos_categorias = [70, 35, 45, 40];
    $alineaciones_categorias = ['L', 'C', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_categorias, $datos_categorias, $anchos_categorias, $alineaciones_categorias);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay datos de categorias para el periodo seleccionado.', 0, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '3. TOP 10 PRODUCTOS MAS MODIFICADOS', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['top_productos'])) {
    $cabeceras_top = ['Producto', 'Categoria', 'Modificaciones', 'Actualizaciones', 'Precio'];
    $datos_top = [];
    
    foreach ($datos_reporte['top_productos'] as $producto) {
        $datos_top[] = [
            substr($producto['nombre_producto'], 0, 25),
            substr($producto['nombre_categoria'], 0, 20),
            $producto['total_modificaciones'],
            $producto['actualizaciones'],
            '$' . number_format($producto['precio_base'], 2)
        ];
    }
    
    $anchos_top = [55, 40, 30, 30, 25];
    $alineaciones_top = ['L', 'L', 'C', 'C', 'R'];
    
    $pdf->crearTabla($cabeceras_top, $datos_top, $anchos_top, $alineaciones_top);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay datos de productos para el periodo seleccionado.', 0, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '4. TENDENCIA MENSUAL (ULTIMOS 6 MESES)', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['mensual'])) {
    $cabeceras_mensual = ['Mes', 'Total Operaciones', 'Nuevos', 'Actualizaciones', 'Eliminaciones'];
    $datos_mensual = [];
    
    foreach ($datos_reporte['mensual'] as $mes) {
        $datos_mensual[] = [
            $mes['mes_nombre'],
            $mes['total_operaciones'],
            $mes['nuevos'],
            $mes['actualizaciones'],
            $mes['eliminaciones']
        ];
    }
    
    $anchos_mensual = [50, 35, 25, 35, 25];
    $alineaciones_mensual = ['L', 'C', 'C', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_mensual, $datos_mensual, $anchos_mensual, $alineaciones_mensual);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay datos mensuales disponibles.', 0, 1);
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'CONCLUSIONES:', 0, 1);

$pdf->SetFont('Arial', '', 10);
if ($general['total_registros'] > 0) {
    $producto_mas_activo = !empty($datos_reporte['top_productos']) ? $datos_reporte['top_productos'][0]['nombre_producto'] : 'N/A';
    $categoria_mas_activa = !empty($datos_reporte['categorias']) ? $datos_reporte['categorias'][0]['nombre_categoria'] : 'N/A';
    
    $pdf->Cell(0, 6, '- Producto mas activo: ' . $producto_mas_activo, 0, 1);
    $pdf->Cell(0, 6, '- Categoria mas activa: ' . $categoria_mas_activa, 0, 1);
    $pdf->Cell(0, 6, '- Tasa de nuevos productos: ' . round(($general['nuevos_productos'] / max(1, $general['total_registros'])) * 100, 1) . '%', 0, 1);
} else {
    $pdf->Cell(0, 6, '- No hay actividad registrada en el periodo seleccionado.', 0, 1);
}

$pdf->Output('I', 'Reportes_Productos_' . date('Y-m-d') . '.pdf');
?>