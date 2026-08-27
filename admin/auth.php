<?php
/**
 * ═══════════════════════════════════════════════════════════
 * AUTENTICACIÓN ADMIN - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/conexion_db.php';
require_once dirname(__DIR__) . '/includes/permisos.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

// ─── Umbrales de seguridad del login ─────────────────────────────────────────
const LOGIN_MAX_INTENTOS_CUENTA   = 5;   // intentos fallidos antes de bloquear la cuenta
const LOGIN_BLOQUEO_MINUTOS       = 15;  // duración del bloqueo por cuenta
const LOGIN_MAX_INTENTOS_IP       = 15;  // intentos fallidos por IP en la ventana
const LOGIN_VENTANA_IP_MINUTOS    = 10;  // tamaño de la ventana de rate-limit por IP

// Cookie de sesión endurecida (Secure solo en producción con HTTPS real)
// antes de iniciar la sesión.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL : '/',
        'domain'   => '',
        'secure'   => ENTORNO === 'produccion',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Verificar si el usuario está autenticado
 * @return bool
 */
function estaAutenticado() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

/**
 * Requerir autenticación - Redirige a login si no está autenticado
 */
function requerirAutenticacion() {
    if (!estaAutenticado()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Rate-limit por IP para /admin/login.php (mismo patrón que solicitudes_rate_limit
 * / resenas_rate_limit / api_asistente_rate_limit). Ventana corta porque es un
 * endpoint sensible a fuerza bruta, independiente de qué email se pruebe.
 * Falla abierta si la BD no responde (no queremos bloquear un login legítimo
 * por un problema de infraestructura).
 *
 * @param PDO $pdo
 * @return bool true si puede continuar, false si superó el límite por IP.
 */
function loginRateLimitIpOk(PDO $pdo): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') return true; // sin IP no podemos limitar, fallar abierto

    try {
        $ipHash = hash('sha256', $ip);

        // Ventana fija de LOGIN_VENTANA_IP_MINUTOS minutos.
        $minutos = (int) date('i');
        $bloque  = floor($minutos / LOGIN_VENTANA_IP_MINUTOS) * LOGIN_VENTANA_IP_MINUTOS;
        $ventana = date('Y-m-d H:') . sprintf('%02d', $bloque) . ':00';

        $pdo->prepare("INSERT INTO admin_login_rate_limit (ip_hash, ventana, intentos)
                       VALUES (:h, :v, 1)
                       ON DUPLICATE KEY UPDATE intentos = intentos + 1")
            ->execute([':h' => $ipHash, ':v' => $ventana]);

        $st = $pdo->prepare("SELECT intentos FROM admin_login_rate_limit WHERE ip_hash = :h AND ventana = :v");
        $st->execute([':h' => $ipHash, ':v' => $ventana]);
        $intentos = (int) $st->fetchColumn();

        // Limpieza ocasional de ventanas viejas (no hace falta cron dedicado).
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM admin_login_rate_limit WHERE actualizado_en < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
        }

        return $intentos <= LOGIN_MAX_INTENTOS_IP;
    } catch (PDOException $e) {
        error_log('rate-limit login IP error: ' . $e->getMessage());
        return true; // falla abierta
    }
}

/**
 * Intentar login con email y password
 * @param string $email
 * @param string $password
 * @return array ['ok' => bool, 'mensaje' => string]
 */
function intentarLogin($email, $password) {
    $pdo = obtenerConexion();
    if (!$pdo) {
        return ['ok' => false, 'mensaje' => 'Error de conexión'];
    }

    // Rate-limit por IP primero: no consulta la tabla admins si ya está excedido.
    if (!loginRateLimitIpOk($pdo)) {
        return ['ok' => false, 'mensaje' => 'Demasiados intentos desde tu conexión. Esperá unos minutos e intentá de nuevo.'];
    }

    // Cargar rol/etiquetas si las columnas existen (post-migración 17)
    $cols = array_column($pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $tieneRol = in_array('rol', $cols, true) && in_array('etiquetas', $cols, true);
    $selectExtra = $tieneRol ? ', rol, etiquetas' : '';

    $stmt = $pdo->prepare("SELECT id, nombre, password_hash{$selectExtra}, intentos_fallidos,
                                   (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS esta_bloqueado,
                                   GREATEST(TIMESTAMPDIFF(MINUTE, NOW(), bloqueado_hasta), 1) AS minutos_restantes
                            FROM admins WHERE email = :email AND activo = 1 LIMIT 1");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return ['ok' => false, 'mensaje' => 'Credenciales inválidas'];
    }

    // Cuenta bloqueada temporalmente por intentos fallidos previos.
    // Nota: la comparación se hace 100% en SQL (NOW() de MySQL) a propósito,
    // para no mezclarla con time()/strtotime() de PHP — el servidor de BD y
    // el de aplicación pueden tener zonas horarias distintas (visto en este
    // proyecto: MySQL en Europe/London vs PHP en Europe/Berlin) y comparar
    // "a mano" en PHP puede dar falsos negativos por ese desfasaje.
    if (!empty($admin['esta_bloqueado'])) {
        return ['ok' => false, 'mensaje' => "Cuenta bloqueada temporalmente por intentos fallidos. Probá de nuevo en {$admin['minutos_restantes']} min."];
    }

    if (!password_verify($password, $admin['password_hash'])) {
        // Incrementar contador de intentos fallidos y bloquear si llega al umbral.
        $intentos = (int) $admin['intentos_fallidos'] + 1;
        if ($intentos >= LOGIN_MAX_INTENTOS_CUENTA) {
            $stmt = $pdo->prepare("UPDATE admins SET intentos_fallidos = :i, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :min MINUTE) WHERE id = :id");
            $stmt->execute([':i' => $intentos, ':min' => LOGIN_BLOQUEO_MINUTOS, ':id' => $admin['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET intentos_fallidos = :i WHERE id = :id");
            $stmt->execute([':i' => $intentos, ':id' => $admin['id']]);
        }
        return ['ok' => false, 'mensaje' => 'Credenciales inválidas'];
    }

    // Login exitoso - Regenerar ID de sesión ANTES de escribir datos (evita session fixation).
    session_regenerate_id(true);

    $_SESSION['admin_id']     = $admin['id'];
    $_SESSION['admin_nombre'] = $admin['nombre'];
    $_SESSION['admin_email']  = $email;
    $_SESSION['admin_rol']    = $admin['rol'] ?? 'admin';
    $_SESSION['admin_etiquetas'] = $admin['etiquetas'] ?? null;

    // Resetear contador de intentos fallidos + actualizar último login
    $stmt = $pdo->prepare("UPDATE admins SET ultimo_login = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id");
    $stmt->execute([':id' => $admin['id']]);

    return ['ok' => true, 'mensaje' => 'Login exitoso'];
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    $_SESSION = [];

    // Expirar explícitamente la cookie de sesión en el navegador.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

/**
 * Obtener datos del admin actual
 * @return array|null
 */
function obtenerAdminActual() {
    if (!estaAutenticado()) {
        return null;
    }

    return [
        'id'        => $_SESSION['admin_id'],
        'nombre'    => $_SESSION['admin_nombre'],
        'email'     => $_SESSION['admin_email'],
        'rol'       => $_SESSION['admin_rol']       ?? 'admin',
        'etiquetas' => $_SESSION['admin_etiquetas'] ?? null,
    ];
}
