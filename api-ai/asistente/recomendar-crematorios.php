<?php
/**
 * API IA — Recomendar crematorios por ciudad
 * 
 * Uso: GET /api-ai/asistente/recomendar-crematorios.php
 *   ?ciudad=Madrid
 *   &radio_km=30            (opcional, para búsqueda por cercanía, default 25, máx 100)
 *   &limit=5                (opcional, máx 10)
 * 
 * radio_km real: la ciudad se geocodifica (con caché en BD, ver
 * geocodificarCiudadCache() en includes/funciones.php) y se filtra por
 * distancia real (Haversine, mismo cálculo que cerca.php/cerca-mapa.php).
 * Si no se puede geocodificar, cae a búsqueda por texto libre contra
 * c.ciudad/c.ciudades_cobertura (radio_km se ignora en ese caso). La
 * respuesta indica qué método se usó en "metodo_busqueda" ('geo'|'texto').
 * 
 * Cada ficha devuelve "mensaje_whatsapp": texto pre-formateado y listo para
 * enviar tal cual por WhatsApp (emojis + datos clave, ver
 * generarMensajeWhatsappAuto() en includes/funciones.php). N8N no necesita
 * armar el mensaje — solo tomarlo y enviarlo.
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
if (!asistenteRateLimitOk('recomendar-crematorios')) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiadas solicitudes. Probá de nuevo en unos segundos.']);
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
$pdo = obtenerConexion();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

/* ========== BUSCAR POR CIUDAD ========== */
// Intentamos geocodificar la ciudad (con caché) para poder filtrar por
// radio_km real con Haversine (mismo cálculo que cerca.php/cerca-mapa.php).
// Si no se puede geocodificar (sin API key, ciudad no reconocida, error de
// red), caemos al comportamiento anterior: búsqueda por texto libre.
$geo = geocodificarCiudadCache($ciudad);

if ($geo !== null) {
    $sql = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
                   c.telefono, c.whatsapp, c.website, c.direccion_completa,
                   c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.destacado, c.atencion_24h, c.recogida_domicilio,
                   c.cremacion_individual, c.cremacion_colectiva, c.urna,
                   c.rango_precios, c.descripcion, c.mensaje_whatsapp,
                   (6371 * acos(
                       cos(radians(:lat1)) * cos(radians(c.latitud))
                       * cos(radians(c.longitud) - radians(:lng1))
                       + sin(radians(:lat2)) * sin(radians(c.latitud))
                   )) AS distancia_km
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.activo = 1 AND c.estado = 'activa'
              AND c.latitud IS NOT NULL AND c.latitud != 0
              AND c.longitud IS NOT NULL AND c.longitud != 0
            HAVING distancia_km <= :radio
            ORDER BY c.destacado DESC, distancia_km ASC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lat1', $geo['lat']);
    $stmt->bindValue(':lat2', $geo['lat']);
    $stmt->bindValue(':lng1', $geo['lng']);
    $stmt->bindValue(':radio', $radioKm);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fichas as &$f) {
        $f['distancia_km'] = round((float) $f['distancia_km'], 1);
    }
    unset($f);

    $metodoBusqueda = 'geo';
} else {
    $sql = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
                   c.telefono, c.whatsapp, c.website, c.direccion_completa,
                   c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.destacado, c.atencion_24h, c.recogida_domicilio,
                   c.cremacion_individual, c.cremacion_colectiva, c.urna,
                   c.rango_precios, c.descripcion, c.mensaje_whatsapp
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.activo = 1 AND c.estado = 'activa'
              AND (c.ciudad LIKE :ciudad1 OR c.ciudades_cobertura LIKE :ciudad2)
            ORDER BY c.destacado DESC, c.rating DESC, c.reviews_total DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':ciudad1', '%' . $ciudad . '%');
    $stmt->bindValue(':ciudad2', '%' . $ciudad . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $metodoBusqueda = 'texto';
}

/* ========== RESPONSE ========== */
echo json_encode([
    'ok'              => true,
    'ciudad'          => $ciudad,
    'radio_km'        => $radioKm,
    'metodo_busqueda' => $metodoBusqueda, // 'geo' (Haversine real) o 'texto' (fallback sin geocoding)
    'total'           => count($fichas),
    'fichas'          => $fichas,
], JSON_UNESCAPED_UNICODE);
