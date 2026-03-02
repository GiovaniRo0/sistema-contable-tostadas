<?php
require_once('pdf_generator.php');
require_once('Conexion.php');
require_once('auth.php');

if (!isset($_SESSION['id_usuario'])) {
    die('Acceso no autorizado');
}

$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); 
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$database = new Conexion();
$db = Conexion::ConexionBD();

$datos_reporte = [];

$query_general = "
    SELECT 
        COUNT(*) as total_registros,
        COUNT(DISTINCT id_referencia) as ingredientes_afectados,
        COUNT(DISTINCT id_usuario) as usuarios_involucrados,
        SUM(CASE WHEN tipo_registro = 'ENTRADA_STOCK' THEN 1 ELSE 0 END) as entradas_stock,
        SUM(CASE WHEN tipo_registro = 'SALIDA_STOCK' THEN 1 ELSE 0 END) as salidas_stock,
        SUM(CASE WHEN tipo_registro = 'ACTUALIZACION_PRECIO' THEN 1 ELSE 0 END) as cambios_precio
    FROM historial 
    WHERE tabla_referencia = 'ingredientes' 
    AND fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
";

$stmt_general = $db->prepare($query_general);
$stmt_general->bindValue(':fecha_desde', $fecha_desde);
$stmt_general->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_general->execute();
$datos_reporte['general'] = $stmt_general->fetch(PDO::FETCH_ASSOC);

$query_movimientos = "
    SELECT 
        i.nombre_ingrediente,
        i.stock as stock_actual,
        i.costo_unitario,
        i.precio_extra,
        SUM(CASE WHEN h.tipo_registro = 'ENTRADA_STOCK' THEN 1 ELSE 0 END) as total_entradas,
        SUM(CASE WHEN h.tipo_registro = 'SALIDA_STOCK' THEN 1 ELSE 0 END) as total_salidas,
        COUNT(h.id_historial) as total_movimientos
    FROM historial h
    INNER JOIN ingredientes i ON h.id_referencia = i.id_ingrediente
    WHERE h.tabla_referencia = 'ingredientes'
    AND h.fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY i.id_ingrediente, i.nombre_ingrediente, i.stock, i.costo_unitario, i.precio_extra
    ORDER BY total_movimientos DESC
";

$stmt_movimientos = $db->prepare($query_movimientos);
$stmt_movimientos->bindValue(':fecha_desde', $fecha_desde);
$stmt_movimientos->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_movimientos->execute();
$datos_reporte['movimientos'] = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);

$query_stock_critico = "
    SELECT 
        nombre_ingrediente,
        stock,
        costo_unitario,
        precio_extra,
        CASE 
            WHEN stock = 0 THEN 'SIN STOCK'
            WHEN stock < 5 THEN 'STOCK BAJO'
            ELSE 'STOCK NORMAL'
        END as estado_stock
    FROM ingredientes 
    WHERE activo = true
    AND stock < 10
    ORDER BY stock ASC, nombre_ingrediente
";

$stmt_stock = $db->prepare($query_stock_critico);
$stmt_stock->execute();
$datos_reporte['stock_critico'] = $stmt_stock->fetchAll(PDO::FETCH_ASSOC);

$query_cambios_precio = "
    SELECT 
        i.nombre_ingrediente,
        h.fecha_cambio,
        h.descripcion,
        (regexp_match(h.valor_anterior, 'Costo: \\$([0-9.]+)'))[1]::numeric(10,2) as costo_anterior,
        (regexp_match(h.valor_nuevo, 'Costo: \\$([0-9.]+)'))[1]::numeric(10,2) as costo_nuevo,
        (regexp_match(h.valor_anterior, 'Precio Extra: \\$([0-9.]+)'))[1]::numeric(10,2) as precio_extra_anterior,
        (regexp_match(h.valor_nuevo, 'Precio Extra: \\$([0-9.]+)'))[1]::numeric(10,2) as precio_extra_nuevo
    FROM historial h
    INNER JOIN ingredientes i ON h.id_referencia = i.id_ingrediente
    WHERE h.tabla_referencia = 'ingredientes'
    AND h.tipo_registro = 'ACTUALIZACION_PRECIO'
    AND h.fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    ORDER BY h.fecha_cambio DESC
    LIMIT 15
";

$stmt_precios = $db->prepare($query_cambios_precio);
$stmt_precios->bindValue(':fecha_desde', $fecha_desde);
$stmt_precios->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_precios->execute();
$datos_reporte['cambios_precio'] = $stmt_precios->fetchAll(PDO::FETCH_ASSOC);

$query_mensual = "
    SELECT 
        TO_CHAR(fecha_cambio, 'YYYY-MM') as mes,
        TO_CHAR(fecha_cambio, 'Month YYYY') as mes_nombre,
        COUNT(*) as total_operaciones,
        COUNT(DISTINCT id_referencia) as ingredientes_afectados,
        SUM(CASE WHEN tipo_registro = 'ENTRADA_STOCK' THEN 1 ELSE 0 END) as entradas,
        SUM(CASE WHEN tipo_registro = 'SALIDA_STOCK' THEN 1 ELSE 0 END) as salidas,
        SUM(CASE WHEN tipo_registro = 'ACTUALIZACION_PRECIO' THEN 1 ELSE 0 END) as cambios_precio
    FROM historial 
    WHERE tabla_referencia = 'ingredientes'
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
$pdf->setTitulo('REPORTES DE INGREDIENTES');
$pdf->setSubtitulo('Periodo: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)));

$filtros = [
    'fecha desde' => date('d/m/Y', strtotime($fecha_desde)),
    'fecha hasta' => date('d/m/Y', strtotime($fecha_hasta))
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

$pdf->Cell(95, 6, '- Ingredientes afectados:', 0, 0);
$pdf->Cell(0, 6, $general['ingredientes_afectados'], 0, 1);

$pdf->Cell(95, 6, '- Usuarios involucrados:', 0, 0);
$pdf->Cell(0, 6, $general['usuarios_involucrados'], 0, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Distribucion por Tipo de Operacion:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, '- Entradas de stock:', 0, 0);
$pdf->Cell(0, 6, $general['entradas_stock'] . ' (' . round(($general['entradas_stock'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Cell(95, 6, '- Salidas de stock:', 0, 0);
$pdf->Cell(0, 6, $general['salidas_stock'] . ' (' . round(($general['salidas_stock'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Cell(95, 6, '- Cambios de precio:', 0, 0);
$pdf->Cell(0, 6, $general['cambios_precio'] . ' (' . round(($general['cambios_precio'] / max(1, $general['total_registros'])) * 100, 1) . '%)', 0, 1);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '2. MOVIMIENTOS DE STOCK POR INGREDIENTE', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['movimientos'])) {
    $cabeceras_movimientos = ['Ingrediente', 'Stock Actual', 'Entradas', 'Salidas', 'Total Mov', 'Costo', 'Precio Extra'];
    $datos_movimientos = [];
    
    foreach ($datos_reporte['movimientos'] as $movimiento) {
        $stock_indicador = $movimiento['stock_actual'];
        if ($movimiento['stock_actual'] == 0) {
            $stock_indicador .= ' (SIN STOCK)';
        } elseif ($movimiento['stock_actual'] < 5) {
            $stock_indicador .= ' (BAJO)';
        }
        
        $datos_movimientos[] = [
            substr($movimiento['nombre_ingrediente'], 0, 22),
            $stock_indicador,
            $movimiento['total_entradas'],
            $movimiento['total_salidas'],
            $movimiento['total_movimientos'],
            '$' . number_format($movimiento['costo_unitario'], 2),
            '$' . number_format($movimiento['precio_extra'], 2)
        ];
    }
    
    $anchos_movimientos = [40, 25, 18, 18, 18, 20, 25];
    $alineaciones_movimientos = ['L', 'C', 'C', 'C', 'C', 'R', 'R'];
    
    $pdf->crearTabla($cabeceras_movimientos, $datos_movimientos, $anchos_movimientos, $alineaciones_movimientos);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay movimientos de stock para el periodo seleccionado.', 0, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '3. ALERTAS DE STOCK CRITICO', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['stock_critico'])) {
    $cabeceras_stock = ['Ingrediente', 'Stock Actual', 'Estado', 'Costo Unitario', 'Precio Extra'];
    $datos_stock = [];
    
    foreach ($datos_reporte['stock_critico'] as $ingrediente) {
        $datos_stock[] = [
            substr($ingrediente['nombre_ingrediente'], 0, 25),
            $ingrediente['stock'] . ' und',
            $ingrediente['estado_stock'],
            '$' . number_format($ingrediente['costo_unitario'], 2),
            '$' . number_format($ingrediente['precio_extra'], 2)
        ];
    }
    
    $anchos_stock = [50, 25, 25, 30, 30];
    $alineaciones_stock = ['L', 'C', 'C', 'R', 'R'];
    
    $pdf->crearTabla($cabeceras_stock, $datos_stock, $anchos_stock, $alineaciones_stock);
    
    $sin_stock = count(array_filter($datos_reporte['stock_critico'], function($i) { return $i['stock'] == 0; }));
    $stock_bajo = count(array_filter($datos_reporte['stock_critico'], function($i) { return $i['stock'] > 0 && $i['stock'] < 5; }));
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, 'RESUMEN DE ALERTAS:', 0, 1);
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, '- Ingredientes sin stock: ' . $sin_stock, 0, 1);
    $pdf->Cell(0, 6, '- Ingredientes con stock bajo: ' . $stock_bajo, 0, 1);
    
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay alertas de stock critico. Todo en orden.', 0, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '4. CAMBIOS DE PRECIO RECIENTES', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['cambios_precio'])) {
    $cabeceras_precios = ['Ingrediente', 'Fecha', 'Costo Ant.', 'Costo Nuevo', 'P. Extra Ant.', 'P. Extra Nvo.'];
    $datos_precios = [];
    
    foreach ($datos_reporte['cambios_precio'] as $cambio) {
        $datos_precios[] = [
            substr($cambio['nombre_ingrediente'], 0, 20),
            date('d/m/Y', strtotime($cambio['fecha_cambio'])),
            '$' . number_format($cambio['costo_anterior'], 2),
            '$' . number_format($cambio['costo_nuevo'], 2),
            '$' . number_format($cambio['precio_extra_anterior'], 2),
            '$' . number_format($cambio['precio_extra_nuevo'], 2)
        ];
    }
    
    $anchos_precios = [35, 20, 25, 25, 30, 30];
    $alineaciones_precios = ['L', 'C', 'R', 'R', 'R', 'R'];
    
    $pdf->crearTabla($cabeceras_precios, $datos_precios, $anchos_precios, $alineaciones_precios);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No hay cambios de precio para el periodo seleccionado.', 0, 1);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '5. TENDENCIA MENSUAL (ULTIMOS 6 MESES)', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_reporte['mensual'])) {
    $cabeceras_mensual = ['Mes', 'Total Oper', 'Entradas', 'Salidas', 'Cambios Precio'];
    $datos_mensual = [];
    
    foreach ($datos_reporte['mensual'] as $mes) {
        $datos_mensual[] = [
            $mes['mes_nombre'],
            $mes['total_operaciones'],
            $mes['entradas'],
            $mes['salidas'],
            $mes['cambios_precio']
        ];
    }
    
    $anchos_mensual = [45, 25, 20, 20, 25];
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
    $ingrediente_mas_activo = !empty($datos_reporte['movimientos']) ? $datos_reporte['movimientos'][0]['nombre_ingrediente'] : 'N/A';
    $total_alertas = count($datos_reporte['stock_critico']);
    
    $pdf->Cell(0, 6, '- Ingrediente mas activo: ' . $ingrediente_mas_activo, 0, 1);
    $pdf->Cell(0, 6, '- Total de alertas de stock: ' . $total_alertas, 0, 1);
    $pdf->Cell(0, 6, '- Balance stock (Entradas/Salidas): ' . $general['entradas_stock'] . '/' . $general['salidas_stock'], 0, 1);
    
    if ($total_alertas > 0) {
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 6, '- ACCION REQUERIDA: Revisar stock de ingredientes criticos', 0, 1);
        $pdf->SetTextColor(0);
    }
} else {
    $pdf->Cell(0, 6, '- No hay actividad registrada en el periodo seleccionado.', 0, 1);
}

$pdf->Output('I', 'Reportes_Ingredientes_' . date('Y-m-d') . '.pdf');
?>