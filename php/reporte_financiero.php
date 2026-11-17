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

    // Total de entradas y sus ingresos
    $sql_entradas = "SELECT COUNT(*) as cantidad, COALESCE(SUM(precio), 0) as total
                     FROM entradas
                     WHERE DATE(fecha_hora) BETWEEN ? AND ?
                     AND estado = 'activo'";

    $stmt = $conn->prepare($sql_entradas);
    $stmt->bind_param("ss", $fecha_desde, $fecha_hasta);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $datos = $resultado->fetch_assoc();
    $total_entradas = (int)$datos['cantidad'];
    $ingresos_totales = (float)$datos['total'];
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'total_entradas' => $total_entradas,
            'ingresos_totales' => $ingresos_totales,
            'periodo' => [
                'desde' => $fecha_desde,
                'hasta' => $fecha_hasta
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
