<?php
/**
 * ═══════════════════════════════════════════════════════════
 * AUTENTICACIÓN ADMIN - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/conexion_db.php';
require_once dirname(__DIR__) . '/includes/permisos.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
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

    // Cargar rol/etiquetas si las columnas existen (post-migración 17)
    $cols = array_column($pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $tieneRol = in_array('rol', $cols, true) && in_array('etiquetas', $cols, true);
    $selectExtra = $tieneRol ? ', rol, etiquetas' : '';

    $stmt = $pdo->prepare("SELECT id, nombre, password_hash{$selectExtra} FROM admins WHERE email = :email AND activo = 1 LIMIT 1");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return ['ok' => false, 'mensaje' => 'Credenciales inválidas'];
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return ['ok' => false, 'mensaje' => 'Credenciales inválidas'];
    }

    // Login exitoso - Crear sesión
    $_SESSION['admin_id']     = $admin['id'];
    $_SESSION['admin_nombre'] = $admin['nombre'];
    $_SESSION['admin_email']  = $email;
    $_SESSION['admin_rol']    = $admin['rol'] ?? 'admin';
    $_SESSION['admin_etiquetas'] = $admin['etiquetas'] ?? null;

    // Actualizar último login
    $stmt = $pdo->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = :id");
    $stmt->execute([':id' => $admin['id']]);

    return ['ok' => true, 'mensaje' => 'Login exitoso'];
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
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
