<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once 'conexion.php';

// Solo usuarios autenticados
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "No autenticado"]);
    exit();
}

// Solo el administrador puede exportar todas las entradas
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Acceso denegado"]);
    exit();
}

// Se listan solo las columnas necesarias (no SELECT *)
$sql = "SELECT id, tipo_entrada, precio, dni_cliente, usuario_id, fecha_hora, estado
        FROM entradas
        ORDER BY fecha_hora DESC";
$resultado = $conn->query($sql);

$datos = [];
if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        $datos[] = $row;
    }
}

echo json_encode($datos);

$conn->close();
?>
