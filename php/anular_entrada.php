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

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit();
}

validar_csrf();

try {
    $entrada_id = filter_var($_POST['entrada_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$entrada_id) {
        echo json_encode(["success" => false, "error" => "ID de entrada requerido"]);
        exit();
    }

    // Verificar que la entrada existe y es del día actual
    $sql_verificar = "SELECT id, fecha_hora, estado
                      FROM entradas
                      WHERE id = ? AND DATE(fecha_hora) = CURDATE()";

    $stmt = $conn->prepare($sql_verificar);
    $stmt->bind_param("i", $entrada_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "error" => "Entrada no encontrada o no es del día actual"
        ]);
        $stmt->close();
        exit();
    }

    $entrada = $resultado->fetch_assoc();
    $stmt->close();

    // Verificar que no esté ya anulada
    if ($entrada['estado'] === 'anulado') {
        echo json_encode([
            "success" => false,
            "error" => "Esta entrada ya fue anulada"
        ]);
        exit();
    }

    // Anular la entrada (se marca como anulada, no se elimina) y se registra quién y cuándo
    $usuario_id = (int)$_SESSION['usuario_id'];
    $ok = false;

    try {
        $sql_anular = "UPDATE entradas
                       SET estado = 'anulado', anulado_por = ?, fecha_anulacion = NOW()
                       WHERE id = ? AND estado <> 'anulado'";
        $stmt = $conn->prepare($sql_anular);
        $stmt->bind_param("ii", $usuario_id, $entrada_id);
        $ok = $stmt->execute();
    } catch (Throwable $e) {
        // La migración 001_seguridad.sql todavía no se ejecutó: anular sin auditoría
        error_log('anular_entrada (sin auditoría): ' . $e->getMessage());
        $sql_anular = "UPDATE entradas SET estado = 'anulado' WHERE id = ? AND estado <> 'anulado'";
        $stmt = $conn->prepare($sql_anular);
        $stmt->bind_param("i", $entrada_id);
        $ok = $stmt->execute();
    }

    if ($ok) {
        echo json_encode([
            "success" => true,
            "message" => "Entrada anulada correctamente"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Error al anular la entrada"
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log(__FILE__ . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al anular entrada'
    ]);
}

$conn->close();
?>
