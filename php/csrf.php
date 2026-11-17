<?php
// Entrega el token CSRF a las páginas estáticas (login.html) y avisa si ya hay sesión.
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/seguridad.php';
iniciar_sesion_segura();

$respuesta = ["success" => true, "token" => csrf_token(), "autenticado" => false];

if (isset($_SESSION['usuario_id'])) {
    $respuesta["autenticado"] = true;
    $respuesta["redirect"] = es_administrador() ? "admin.php" : "index.php";
}

echo json_encode($respuesta);
