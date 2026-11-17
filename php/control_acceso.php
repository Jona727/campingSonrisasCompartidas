<?php
// Registra el ingreso de una persona (una entrada)
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/seguridad.php';
iniciar_sesion_segura();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/precios.php';
$conn->query("SET time_zone = '-03:00'");

// Solo usuarios autenticados
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "No autenticado", "error" => "No autenticado"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

validar_csrf();

$dni = trim((string)($_POST['dni'] ?? ''));
$tipo_entrada = (string)($_POST['tipo_entrada'] ?? '');

// El DNI es opcional, pero si viene debe tener entre 6 y 9 dígitos
if ($dni !== '' && !preg_match('/^\d{6,9}$/', $dni)) {
    echo json_encode(["success" => false, "message" => "DNI inválido."]);
    exit();
}

// El precio SIEMPRE lo define el servidor (nunca se confía en el navegador)
$precios = precios_entradas();
if (!isset($precios[$tipo_entrada])) {
    echo json_encode(["success" => false, "message" => "Tipo de entrada inválido."]);
    exit();
}
$precio = $precios[$tipo_entrada];
$usuario_id = (int)$_SESSION['usuario_id'];

$sql = "INSERT INTO entradas (tipo_entrada, precio, dni_cliente, usuario_id, estado)
        VALUES (?, ?, ?, ?, 'activo')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sdsi", $tipo_entrada, $precio, $dni, $usuario_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Ingreso registrado correctamente."]);
} else {
    error_log('control_acceso: ' . $stmt->error);
    echo json_encode(["success" => false, "message" => "Error al registrar el ingreso."]);
}

$stmt->close();
$conn->close();
