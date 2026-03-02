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

$datos_auditoria = [];

$query_resumen_general = "
    SELECT 
        -- Total registros en historial
        (SELECT COUNT(*) FROM historial WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as total_registros,
        
        -- Registros por módulo
        (SELECT COUNT(*) FROM historial WHERE tabla_referencia = 'productos' AND fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as registros_productos,
        (SELECT COUNT(*) FROM historial WHERE tabla_referencia = 'ingredientes' AND fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as registros_ingredientes,
        (SELECT COUNT(*) FROM historial WHERE tabla_referencia = 'ventas' AND fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as registros_ventas,
        
        -- Usuarios activos
        (SELECT COUNT(DISTINCT id_usuario) FROM historial WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as usuarios_activos,
        
        -- Fecha del primer y último registro
        (SELECT MIN(fecha_cambio) FROM historial WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as primera_actividad,
        (SELECT MAX(fecha_cambio) FROM historial WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23) as ultima_actividad
";

$stmt_resumen = $db->prepare($query_resumen_general);
$stmt_resumen->bindValue(':fecha_desde', $fecha_desde);
$stmt_resumen->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_resumen->execute();
$datos_auditoria['resumen_general'] = $stmt_resumen->fetch(PDO::FETCH_ASSOC);

$query_actividad_modulos = "
    SELECT 
        tabla_referencia as modulo,
        COUNT(*) as total_registros,
        COUNT(DISTINCT id_referencia) as elementos_afectados,
        COUNT(DISTINCT id_usuario) as usuarios_involucrados,
        MIN(fecha_cambio) as primera_actividad,
        MAX(fecha_cambio) as ultima_actividad
    FROM historial 
    WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY tabla_referencia
    ORDER BY total_registros DESC
";

$stmt_modulos = $db->prepare($query_actividad_modulos);
$stmt_modulos->bindValue(':fecha_desde', $fecha_desde);
$stmt_modulos->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_modulos->execute();
$datos_auditoria['modulos'] = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);

$query_usuarios_activos = "
    SELECT 
        h.id_usuario,
        u.nombre as nombre_usuario,
        COUNT(h.id_historial) as total_acciones,
        COUNT(DISTINCT h.tabla_referencia) as modulos_utilizados,
        MIN(h.fecha_cambio) as primera_accion,
        MAX(h.fecha_cambio) as ultima_accion
    FROM historial h
    LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
    WHERE h.fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    AND h.id_usuario IS NOT NULL
    GROUP BY h.id_usuario, u.nombre
    ORDER BY total_acciones DESC
    LIMIT 10
";

$stmt_usuarios = $db->prepare($query_usuarios_activos);
$stmt_usuarios->bindValue(':fecha_desde', $fecha_desde);
$stmt_usuarios->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_usuarios->execute();
$datos_auditoria['usuarios_activos'] = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

$query_tipos_operacion = "
    SELECT 
        tipo_registro,
        tabla_referencia as modulo,
        COUNT(*) as total_operaciones,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM historial WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23)), 1) as porcentaje
    FROM historial 
    WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY tipo_registro, tabla_referencia
    ORDER BY total_operaciones DESC
    LIMIT 15
";

$stmt_operaciones = $db->prepare($query_tipos_operacion);
$stmt_operaciones->bindValue(':fecha_desde', $fecha_desde);
$stmt_operaciones->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_operaciones->execute();
$datos_auditoria['tipos_operacion'] = $stmt_operaciones->fetchAll(PDO::FETCH_ASSOC);

$query_actividad_diaria = "
    SELECT 
        TO_CHAR(fecha_cambio, 'Day') as dia_semana,
        EXTRACT(DOW FROM fecha_cambio) as num_dia,
        COUNT(*) as total_operaciones,
        ROUND(AVG(COUNT(*)) OVER (), 1) as promedio_general
    FROM historial 
    WHERE fecha_cambio BETWEEN :fecha_desde AND :fecha_hasta_23
    GROUP BY TO_CHAR(fecha_cambio, 'Day'), EXTRACT(DOW FROM fecha_cambio)
    ORDER BY num_dia
";

$stmt_diaria = $db->prepare($query_actividad_diaria);
$stmt_diaria->bindValue(':fecha_desde', $fecha_desde);
$stmt_diaria->bindValue(':fecha_hasta_23', $fecha_hasta . ' 23:59:59');
$stmt_diaria->execute();
$datos_auditoria['actividad_diaria'] = $stmt_diaria->fetchAll(PDO::FETCH_ASSOC);

$query_estado_inventario = "
    SELECT 
        -- Productos activos
        (SELECT COUNT(*) FROM productos WHERE activo = true) as total_productos_activos,
        (SELECT COUNT(*) FROM productos WHERE activo = false) as total_productos_inactivos,
        
        -- Ingredientes activos
        (SELECT COUNT(*) FROM ingredientes WHERE activo = true) as total_ingredientes_activos,
        (SELECT COUNT(*) FROM ingredientes WHERE activo = false) as total_ingredientes_inactivos,
        
        -- Alertas de stock
        (SELECT COUNT(*) FROM ingredientes WHERE stock = 0 AND activo = true) as ingredientes_sin_stock,
        (SELECT COUNT(*) FROM ingredientes WHERE stock < 5 AND stock > 0 AND activo = true) as ingredientes_stock_bajo,
        
        -- Ventas recientes (últimos 7 días)
        (SELECT COUNT(*) FROM ventas WHERE fecha_venta >= CURRENT_DATE - INTERVAL '7 days') as ventas_ultima_semana,
        (SELECT COALESCE(SUM(total_venta), 0) FROM ventas WHERE fecha_venta >= CURRENT_DATE - INTERVAL '7 days') as ingresos_ultima_semana
";

$stmt_inventario = $db->prepare($query_estado_inventario);
$stmt_inventario->execute();
$datos_auditoria['estado_inventario'] = $stmt_inventario->fetch(PDO::FETCH_ASSOC);

$query_tendencia_mensual = "
    SELECT 
        TO_CHAR(fecha_cambio, 'YYYY-MM') as mes,
        TO_CHAR(fecha_cambio, 'Month YYYY') as mes_nombre,
        COUNT(*) as total_operaciones,
        COUNT(DISTINCT id_usuario) as usuarios_activos,
        COUNT(DISTINCT tabla_referencia) as modulos_utilizados
    FROM historial 
    WHERE fecha_cambio >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '5 months')
    GROUP BY TO_CHAR(fecha_cambio, 'YYYY-MM'), TO_CHAR(fecha_cambio, 'Month YYYY')
    ORDER BY mes DESC
";

$stmt_tendencia = $db->prepare($query_tendencia_mensual);
$stmt_tendencia->execute();
$datos_auditoria['tendencia_mensual'] = $stmt_tendencia->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDFGenerator();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->setTitulo('AUDITORIA GENERAL DEL SISTEMA');
$pdf->setSubtitulo('Periodo: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)));

$filtros = [
    'fecha desde' => date('d/m/Y', strtotime($fecha_desde)),
    'fecha hasta' => date('d/m/Y', strtotime($fecha_hasta)),
    'fecha de generacion' => date('d/m/Y H:i:s')
];

$pdf->agregarFiltros($filtros);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 12, 'RESUMEN EJECUTIVO', 0, 1, 'C');
$pdf->SetTextColor(0);
$pdf->Ln(5);

$resumen = $datos_auditoria['resumen_general'];
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Actividad General del Sistema:', 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(100, 7, '- Total de registros de actividad:', 0, 0);
$pdf->Cell(0, 7, number_format($resumen['total_registros']), 0, 1);

$pdf->Cell(100, 7, '- Usuarios activos en el periodo:', 0, 0);
$pdf->Cell(0, 7, $resumen['usuarios_activos'], 0, 1);

$pdf->Cell(100, 7, '- Primera actividad registrada:', 0, 0);
$pdf->Cell(0, 7, $resumen['primera_actividad'] ? date('d/m/Y H:i', strtotime($resumen['primera_actividad'])) : 'N/A', 0, 1);

$pdf->Cell(100, 7, '- Ultima actividad registrada:', 0, 0);
$pdf->Cell(0, 7, $resumen['ultima_actividad'] ? date('d/m/Y H:i', strtotime($resumen['ultima_actividad'])) : 'N/A', 0, 1);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '2. ACTIVIDAD POR MODULO', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_auditoria['modulos'])) {
    $cabeceras_modulos = ['Modulo', 'Total Reg.', 'Elem. Afect.', 'Usuarios', 'Primera Act.', 'Ultima Act.'];
    $datos_modulos = [];
    
    foreach ($datos_auditoria['modulos'] as $modulo) {
        $datos_modulos[] = [
            ucfirst($modulo['modulo']),
            number_format($modulo['total_registros']),
            number_format($modulo['elementos_afectados']),
            $modulo['usuarios_involucrados'],
            date('d/m/y', strtotime($modulo['primera_actividad'])),
            date('d/m/y', strtotime($modulo['ultima_actividad']))
        ];
    }
    
    $anchos_modulos = [25, 20, 20, 20, 30, 30];
    $alineaciones_modulos = ['L', 'C', 'C', 'C', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_modulos, $datos_modulos, $anchos_modulos, $alineaciones_modulos);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '3. TOP 10 USUARIOS MAS ACTIVOS', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_auditoria['usuarios_activos'])) {
    $cabeceras_usuarios = ['ID Usuario', 'Nombre', 'Total Acciones', 'Modulos Usados', 'Primera Accion', 'Ultima Accion'];
    $datos_usuarios = [];
    
    foreach ($datos_auditoria['usuarios_activos'] as $usuario) {
        $nombre = $usuario['nombre_usuario'] ?: 'Usuario #' . $usuario['id_usuario'];
        $datos_usuarios[] = [
            $usuario['id_usuario'],
            substr($nombre, 0, 20),
            number_format($usuario['total_acciones']),
            $usuario['modulos_utilizados'],
            date('d/m/y', strtotime($usuario['primera_accion'])),
            date('d/m/y', strtotime($usuario['ultima_accion']))
        ];
    }
    
    $anchos_usuarios = [18, 35, 22, 20, 25, 25];
    $alineaciones_usuarios = ['C', 'L', 'C', 'C', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_usuarios, $datos_usuarios, $anchos_usuarios, $alineaciones_usuarios);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '4. TIPOS DE OPERACION MAS FRECUENTES', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_auditoria['tipos_operacion'])) {
    $cabeceras_operaciones = ['Tipo Operacion', 'Modulo', 'Total', 'Porcentaje'];
    $datos_operaciones = [];
    
    foreach ($datos_auditoria['tipos_operacion'] as $operacion) {
        $datos_operaciones[] = [
            ucfirst(str_replace('_', ' ', $operacion['tipo_registro'])),
            ucfirst($operacion['modulo']),
            number_format($operacion['total_operaciones']),
            $operacion['porcentaje'] . '%'
        ];
    }
    
    $anchos_operaciones = [45, 25, 20, 20];
    $alineaciones_operaciones = ['L', 'L', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_operaciones, $datos_operaciones, $anchos_operaciones, $alineaciones_operaciones);
}

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '5. ESTADO ACTUAL DEL INVENTARIO', 0, 1);
$pdf->SetTextColor(0);

$inventario = $datos_auditoria['estado_inventario'];
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Productos:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 6, '- Productos activos:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['total_productos_activos']), 0, 1);

$pdf->Cell(100, 6, '- Productos inactivos:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['total_productos_inactivos']), 0, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Ingredientes:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 6, '- Ingredientes activos:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['total_ingredientes_activos']), 0, 1);

$pdf->Cell(100, 6, '- Ingredientes inactivos:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['total_ingredientes_inactivos']), 0, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(255, 0, 0);
$pdf->Cell(0, 8, 'Alertas de Stock:', 0, 1);
$pdf->SetTextColor(0);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 6, '- Ingredientes sin stock:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['ingredientes_sin_stock']), 0, 1);

$pdf->Cell(100, 6, '- Ingredientes con stock bajo:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['ingredientes_stock_bajo']), 0, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Ventas Recientes (Ultima semana):', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 6, '- Total de ventas:', 0, 0);
$pdf->Cell(0, 6, number_format($inventario['ventas_ultima_semana']), 0, 1);

$pdf->Cell(100, 6, '- Ingresos generados:', 0, 0);
$pdf->Cell(0, 6, '$' . number_format($inventario['ingresos_ultima_semana'], 2), 0, 1);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 10, '6. TENDENCIA MENSUAL (ULTIMOS 6 MESES)', 0, 1);
$pdf->SetTextColor(0);

if (!empty($datos_auditoria['tendencia_mensual'])) {
    $cabeceras_tendencia = ['Mes', 'Total Operaciones', 'Usuarios Activos', 'Modulos Utilizados'];
    $datos_tendencia = [];
    
    foreach ($datos_auditoria['tendencia_mensual'] as $mes) {
        $datos_tendencia[] = [
            $mes['mes_nombre'],
            number_format($mes['total_operaciones']),
            $mes['usuarios_activos'],
            $mes['modulos_utilizados']
        ];
    }
    
    $anchos_tendencia = [50, 35, 30, 30];
    $alineaciones_tendencia = ['L', 'C', 'C', 'C'];
    
    $pdf->crearTabla($cabeceras_tendencia, $datos_tendencia, $anchos_tendencia, $alineaciones_tendencia);
}

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 128);
$pdf->Cell(0, 12, 'CONCLUSIONES Y RECOMENDACIONES', 0, 1, 'C');
$pdf->SetTextColor(0);
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'PUNTOS DESTACADOS:', 0, 1);

$pdf->SetFont('Arial', '', 11);
$modulo_mas_activo = !empty($datos_auditoria['modulos']) ? ucfirst($datos_auditoria['modulos'][0]['modulo']) : 'N/A';
$usuario_mas_activo = !empty($datos_auditoria['usuarios_activos']) ? ($datos_auditoria['usuarios_activos'][0]['nombre_usuario'] ?: 'Usuario #' . $datos_auditoria['usuarios_activos'][0]['id_usuario']) : 'N/A';
$operacion_mas_comun = !empty($datos_auditoria['tipos_operacion']) ? ucfirst(str_replace('_', ' ', $datos_auditoria['tipos_operacion'][0]['tipo_registro'])) : 'N/A';

$pdf->Cell(0, 6, '- Modulo mas activo: ' . $modulo_mas_activo, 0, 1);
$pdf->Cell(0, 6, '- Usuario mas activo: ' . $usuario_mas_activo, 0, 1);
$pdf->Cell(0, 6, '- Operacion mas comun: ' . $operacion_mas_comun, 0, 1);
$pdf->Cell(0, 6, '- Total de alertas de stock: ' . ($inventario['ingredientes_sin_stock'] + $inventario['ingredientes_stock_bajo']), 0, 1);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'RECOMENDACIONES:', 0, 1);

$pdf->SetFont('Arial', '', 11);
if ($inventario['ingredientes_sin_stock'] > 0) {
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, '- URGENTE: Reabastecer ' . $inventario['ingredientes_sin_stock'] . ' ingredientes sin stock', 0, 1);
    $pdf->SetTextColor(0);
}

if ($inventario['ingredientes_stock_bajo'] > 0) {
    $pdf->Cell(0, 6, '- Planificar compra para ' . $inventario['ingredientes_stock_bajo'] . ' ingredientes con stock bajo', 0, 1);
}

if ($resumen['usuarios_activos'] < 3) {
    $pdf->Cell(0, 6, '- Considerar capacitacion para aumentar usuarios activos en el sistema', 0, 1);
}

if (!empty($datos_auditoria['modulos']) && count($datos_auditoria['modulos']) < 2) {
    $pdf->Cell(0, 6, '- Promover el uso de otros modulos del sistema', 0, 1);
}

$pdf->Cell(0, 6, '- Revisar periodicamente este reporte de auditoria', 0, 1);
$pdf->Cell(0, 6, '- Mantener actualizados los precios y stock del inventario', 0, 1);

$pdf->Ln(10);

$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 6, 'Reporte generado automaticamente por el Sistema Tostadas Jela', 0, 1, 'C');
$pdf->Cell(0, 6, 'Fecha de generacion: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

$pdf->Output('I', 'Auditoria_General_' . date('Y-m-d') . '.pdf');
?>