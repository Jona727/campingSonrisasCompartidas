<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/seguridad.php';
iniciar_sesion_segura();
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "No autenticado"]);
    exit();
}

// Los reportes financieros son solo para el administrador
requerir_admin_json();

try {
    $fecha_desde = fecha_get('fecha_desde', date('Y-m-01'));
    $fecha_hasta = fecha_get('fecha_hasta', date('Y-m-d'));

    // Obtener datos agrupados por fecha
    $sql = "SELECT
                DATE(fecha_hora) as fecha,
                COALESCE(SUM(precio), 0) as ingresos_entradas,
                COALESCE(SUM(precio), 0) as total
            FROM entradas
            WHERE DATE(fecha_hora) BETWEEN ? AND ?
            AND estado = 'activo'
            GROUP BY DATE(fecha_hora)
            ORDER BY fecha ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_desde, $fecha_hasta);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $por_fecha = [];
    $total_ingresos_entradas = 0;

    while ($row = $resultado->fetch_assoc()) {
        $ingresos = (float)$row['ingresos_entradas'];
        $por_fecha[] = [
            'fecha' => date('Y-m-d', strtotime($row['fecha'])),
            'ingresos_entradas' => $ingresos,
            'total' => $ingresos
        ];
        $total_ingresos_entradas += $ingresos;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'por_fecha' => $por_fecha,
            'totales' => [
                'ingresos_entradas' => $total_ingresos_entradas,
                'total' => $total_ingresos_entradas
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log(__FILE__ . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al generar reporte'
    ]);
}

$conn->close();
?>
