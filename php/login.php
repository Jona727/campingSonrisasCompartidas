<?php
// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header before any output
header('Content-Type: application/json');

session_start();
require_once 'conexion.php';

// Validate POST data
if (!isset($_POST['usuario']) || !isset($_POST['contraseña'])) {
    echo json_encode(["success" => false, "message" => "Datos incompletos."]);
    exit();
}

// Obtener datos del formulario
$usuario = $_POST['usuario'];
$contraseña = $_POST['contraseña'];

// Consultar usuario en la base de datos
$sql = "SELECT * FROM usuarios WHERE usuario = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Error en la base de datos."]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $usuario_db = $resultado->fetch_assoc();

    // Verificar contraseña
    $password_valida = false;
    $hash_guardado = (string)$usuario_db['password'];

    if (password_get_info($hash_guardado)['algo'] !== null) {
        // Contraseña almacenada como hash: verificación normal
        $password_valida = password_verify($contraseña, $hash_guardado);

        // Actualizar el hash si el algoritmo por defecto de PHP mejoró
        if ($password_valida && password_needs_rehash($hash_guardado, PASSWORD_DEFAULT)) {
            $nuevo_hash = password_hash($contraseña, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $up->bind_param("si", $nuevo_hash, $usuario_db['id']);
            $up->execute();
            $up->close();
        }
    } elseif (hash_equals($hash_guardado, $contraseña)) {
        // MIGRACIÓN ÚNICA: la contraseña estaba guardada en texto plano.
        // Se acepta esta vez y se convierte a hash de inmediato.
        $password_valida = true;
        $nuevo_hash = password_hash($contraseña, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $up->bind_param("si", $nuevo_hash, $usuario_db['id']);
        $up->execute();
        $up->close();
    }

    if ($password_valida) {
        // Iniciar sesión
        $_SESSION['usuario_id'] = $usuario_db['id'];
        $_SESSION['usuario'] = $usuario_db['usuario'];
        $_SESSION['rol'] = strtolower($usuario_db['nombre']); // Convertir a minúsculas para compatibilidad

        // Redirigir según el rol
        if (strtolower($usuario_db['nombre']) === 'administrador') {
            echo json_encode(["success" => true, "redirect" => "admin.php"]);
        } else {
            echo json_encode(["success" => true, "redirect" => "index.php"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Usuario o contraseña incorrectos."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Usuario o contraseña incorrectos."]);
}

$stmt->close();
$conn->close();
?>