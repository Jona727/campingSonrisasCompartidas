<?php
// ============================================
// Utilidades de seguridad compartidas
// ============================================
// Uso:  require_once __DIR__ . '/seguridad.php';   (desde php/)
//       require_once __DIR__ . '/php/seguridad.php'; (desde la raíz)
//       iniciar_sesion_segura();
// ============================================

// Cabeceras de seguridad (también se envían desde .htaccess para archivos estáticos)
function enviar_cabeceras_seguridad(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Inicia la sesión con cookies endurecidas y expiración por inactividad
function iniciar_sesion_segura(): void
{
    enviar_cabeceras_seguridad();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,          // la cookie muere al cerrar el navegador
        'path'     => '/',
        'secure'   => $https,     // solo por HTTPS si el sitio usa HTTPS
        'httponly' => true,       // JavaScript no puede leer la cookie
        'samesite' => 'Lax',      // no se envía en peticiones cruzadas de otros sitios
    ]);

    session_start();

    // Cerrar la sesión tras 8 horas sin actividad
    $limite_inactividad = 8 * 60 * 60;
    if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limite_inactividad) {
        destruir_sesion();
        session_start();
    }
    $_SESSION['ultima_actividad'] = time();
}

// Destruye la sesión por completo (datos + cookie)
function destruir_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Corta la petición con 403 si el token CSRF falta o es incorrecto
function validar_csrf(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $esperado = $_SESSION['csrf_token'] ?? '';

    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "La sesión de seguridad expiró. Recargá la página e intentá de nuevo.",
            "error"   => "La sesión de seguridad expiró. Recargá la página e intentá de nuevo."
        ]);
        exit();
    }
}

// ---------- Roles ----------

function es_administrador(): bool
{
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador';
}

// Para endpoints JSON: exige rol administrador
function requerir_admin_json(): void
{
    if (!es_administrador()) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Acceso denegado"]);
        exit();
    }
}

// ---------- Validaciones ----------

// Lee una fecha (YYYY-MM-DD) de $_GET; si no viene usa el valor por defecto; si es inválida responde 400
function fecha_get(string $clave, string $por_defecto): string
{
    $valor = $_GET[$clave] ?? $por_defecto;
    $d = is_string($valor) ? DateTime::createFromFormat('!Y-m-d', $valor) : false;

    if (!$d || $d->format('Y-m-d') !== $valor) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Fecha inválida. Use el formato AAAA-MM-DD."]);
        exit();
    }
    return $valor;
}

// ---------- Límite de intentos de login ----------
// Usa la tabla login_intentos (ver migraciones/001_seguridad.sql).
// Si la tabla todavía no existe, no se bloquea nada (el login sigue funcionando).

const LOGIN_MAX_INTENTOS = 5;
const LOGIN_VENTANA_MINUTOS = 15;

function ip_cliente(): string
{
    // REMOTE_ADDR no se puede falsificar desde el navegador (X-Forwarded-For sí)
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function login_bloqueado(mysqli $conn): bool
{
    try {
        $ip = ip_cliente();
        $ventana = LOGIN_VENTANA_MINUTOS;
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS n FROM login_intentos
             WHERE ip = ? AND fecha > (NOW() - INTERVAL $ventana MINUTE)"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        return $n >= LOGIN_MAX_INTENTOS;
    } catch (Throwable $e) {
        error_log('login_bloqueado: ' . $e->getMessage());
        return false;
    }
}

function login_registrar_fallo(mysqli $conn, string $usuario): void
{
    try {
        $ip = ip_cliente();
        $usuario = substr($usuario, 0, 50);
        $stmt = $conn->prepare("INSERT INTO login_intentos (ip, usuario) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $ip, $usuario);
            $stmt->execute();
            $stmt->close();
        }
        // Limpieza de registros viejos
        $conn->query("DELETE FROM login_intentos WHERE fecha < (NOW() - INTERVAL 1 DAY)");
    } catch (Throwable $e) {
        error_log('login_registrar_fallo: ' . $e->getMessage());
    }
}

function login_limpiar_fallos(mysqli $conn): void
{
    try {
        $ip = ip_cliente();
        $stmt = $conn->prepare("DELETE FROM login_intentos WHERE ip = ?");
        if ($stmt) {
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('login_limpiar_fallos: ' . $e->getMessage());
    }
}
