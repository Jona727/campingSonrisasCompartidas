<?php
// No mostrar errores en pantalla y responder siempre JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/seguridad.php';
iniciar_sesion_segura();
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

validar_csrf();

// Validar datos recibidos
if (!isset($_POST['usuario']) || !isset($_POST['contraseña'])
    || !is_string($_POST['usuario']) || !is_string($_POST['contraseña'])) {
    echo json_encode(["success" => false, "message" => "Datos incompletos."]);
    exit();
}

$usuario = trim($_POST['usuario']);
$contraseña = $_POST['contraseña'];

// Límite de intentos fallidos por IP
if (login_bloqueado($conn)) {
    http_response_code(429);
    echo json_encode([
        "success" => false,
        "message" => "Demasiados intentos fallidos. Esperá " . LOGIN_VENTANA_MINUTOS . " minutos e intentá de nuevo."
    ]);
    $conn->close();
    exit();
}

$mensaje_error = "Usuario o contraseña incorrectos.";

// Hash de relleno: así tarda lo mismo aunque el usuario no exista
$hash_relleno = '$2y$10$usesomesillystringforsaleOaZ7cwq0aiRbPk2ZIbkXgjqCuzMEe';

$stmt = $conn->prepare("SELECT id, usuario, password, nombre FROM usuarios WHERE usuario = ?");

if (!$stmt) {
    error_log('login.php prepare: ' . $conn->error);
    echo json_encode(["success" => false, "message" => "Error en la base de datos."]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario_db = $resultado->fetch_assoc();
$stmt->close();

$password_valida = false;

if ($usuario_db) {
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
} else {
    password_verify($contraseña, $hash_relleno);
}

if (!$password_valida) {
    login_registrar_fallo($conn, $usuario);
    echo json_encode(["success" => false, "message" => $mensaje_error]);
    $conn->close();
    exit();
}

// Login correcto
login_limpiar_fallos($conn);

// Nuevo ID de sesión y nuevo token CSRF (evita fijación de sesión)
session_regenerate_id(true);
unset($_SESSION['csrf_token']);

$_SESSION['usuario_id'] = $usuario_db['id'];
$_SESSION['usuario'] = $usuario_db['usuario'];
$_SESSION['rol'] = strtolower($usuario_db['nombre']); // El campo "nombre" se usa como rol
$_SESSION['ultima_actividad'] = time();

$redirect = ($_SESSION['rol'] === 'administrador') ? 'admin.php' : 'index.php';
echo json_encode(["success" => true, "redirect" => $redirect]);

$conn->close();
