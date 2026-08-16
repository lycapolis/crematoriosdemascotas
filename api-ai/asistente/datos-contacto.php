<?php
/**
 * API IA — Obtener datos del contacto (lead B2C)
 * 
 * Uso: GET /api-ai/asistente/datos-contacto.php
 *   ?telefono=34613151558
 *   &email=cliente@email.com   (opcional, fallback si no se encuentra por teléfono)
 * 
 * Auth: Header "Authorization: Bearer ***"
 * Rate limit: ver ASISTENTE_RATE_LIMIT_POR_MINUTO en includes/config.php.
 */

declare(strict_types=1);

/* ========== CONFIG ========== */
// Credenciales y API key se cargan desde includes/config.php + .env (nunca hardcodear acá).
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';

/* ========== HEADERS ========== */
header('Content-Type: application/json; charset=utf-8');

/* ========== AUTH=*** */
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headers['Authorization'] ?? '';
$token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
$token = $token !== '' ? $token : ($_GET['key'] ?? '');

if (!hash_equals(ASISTENTE_API_KEY, $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

/* ========== RATE LIMIT ========== */
if (!asistenteRateLimitOk('datos-contacto')) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiadas solicitudes. Probá de nuevo en unos segundos.']);
    exit;
}

/* ========== PARAMS ========== */
$telefono = preg_replace('/[^0-9]/', '', $_GET['telefono'] ?? '');
$email    = trim($_GET['email'] ?? '');

if ($telefono === '' && $email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta "telefono" o "email"']);
    exit;
}

/* ========== DB ========== */
$pdo = obtenerConexion();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

/* ========== BUSCAR LEAD ========== */

// 1. Buscar por whatsapp_number (normalizado, sin + ni espacios)
$lead = null;
if ($telefono !== '') {
    // El número puede estar guardado como "34613151558" o "+34 613 15 15 58"
    $stmt = $pdo->prepare("
        SELECT id, nombre, email, whatsapp_number, ciudad_lead, servicio,
               mascota_tamano, mensaje, created_at, estado
        FROM leads_b2c
        WHERE REPLACE(REPLACE(whatsapp_number, '+', ''), ' ', '') LIKE :tel
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(':tel', '%' . $telefono . '%');
    $stmt->execute();
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Fallback por email
if (!$lead && $email !== '') {
    $stmt = $pdo->prepare("
        SELECT id, nombre, email, whatsapp_number, ciudad_lead, servicio,
               mascota_tamano, mensaje, created_at, estado
        FROM leads_b2c
        WHERE email = :email
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Sin resultado
if (!$lead) {
    echo json_encode([
        'ok'       => false,
        'error'    => 'Contacto no encontrado',
        'telefono' => $telefono,
        'email'    => $email,
    ]);
    exit;
}

echo json_encode(['ok' => true, 'contacto' => $lead], JSON_UNESCAPED_UNICODE);
