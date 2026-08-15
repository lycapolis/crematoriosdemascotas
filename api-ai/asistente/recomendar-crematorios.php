<?php
/**
 * API IA — Recomendar crematorios por ciudad
 * 
 * Uso: GET /api-ai/asistente/recomendar-crematorios.php
 *   ?ciudad=Madrid
 *   &radio_km=30            (opcional, para búsqueda por cercanía)
 *   &limit=5                (opcional, máx 10)
 * 
 * Cada ficha devuelve "mensaje_whatsapp": texto pre-formateado y listo para
 * enviar tal cual por WhatsApp (emojis + datos clave, ver
 * generarMensajeWhatsappAuto() en includes/funciones.php). N8N no necesita
 * armar el mensaje — solo tomarlo y enviarlo.
 * 
 * Auth: Header "Authorization: Bearer ***"
 */

declare(strict_types=1);

/* ========== CONFIG ========== */
// Credenciales y API key se cargan desde includes/config.php + .env (nunca hardcodear acá).
require_once dirname(__DIR__, 2) . '/includes/config.php';

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

/* ========== PARAMS ========== */
$ciudad   = trim($_GET['ciudad'] ?? '');
$radioKm  = min(max((float)($_GET['radio_km'] ?? 25), 1), 100);
$limit    = min(max((int)($_GET['limit'] ?? 5), 1), 10);

if ($ciudad === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta parámetro "ciudad"']);
    exit;
}

/* ========== DB ========== */
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

/* ========== BUSCAR POR CIUDAD ========== */
$sql = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
               c.telefono, c.whatsapp, c.website, c.direccion_completa,
               c.latitud, c.longitud, c.rating, c.reviews_total,
               c.destacado, c.atencion_24h, c.recogida_domicilio,
               c.cremacion_individual, c.cremacion_colectiva, c.urna,
               c.rango_precios, c.descripcion, c.mensaje_whatsapp
        FROM crematorios c
        LEFT JOIN provincias p ON c.provincia_id = p.id
        WHERE c.activo = 1 AND c.estado = 'activa'
          AND (c.ciudad LIKE :ciudad OR c.ciudades_cobertura LIKE :ciudad)
        ORDER BY c.destacado DESC, c.rating DESC, c.reviews_total DESC
        LIMIT :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':ciudad', '%' . $ciudad . '%');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========== RESPONSE ========== */
echo json_encode([
    'ok'    => true,
    'ciudad' => $ciudad,
    'total' => count($fichas),
    'fichas' => $fichas,
], JSON_UNESCAPED_UNICODE);
