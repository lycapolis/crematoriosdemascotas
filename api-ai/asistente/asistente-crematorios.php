<?php
/**
 * API IA — Asistente Crematorios (endpoint consolidado)
 * 
 * Busca crematorios por ciudad (con geocodificación) o por coordenadas directas.
 * Es el mismo patrón que recomendar-crematorios.php pero acepta lat+lon.
 * 
 * Uso: GET /api-ai/asistente/asistente-crematorios.php
 *   ?ciudad=Madrid          (texto, con geocodificación automática)
 *   &lat=39.86&lon=-4.02    (coordenadas directas, opcional)
 *   &radio_km=30            (radio, default 25)
 *   &limit=3                (máx resultados, default 3)
 * 
 * Auth: Header "Authorization: Bearer ***" o ?key=***
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';

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
if (!asistenteRateLimitOk('asistente-crematorios')) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiadas solicitudes. Probá de nuevo en unos segundos.']);
    exit;
}

/* ========== PARAMS ========== */
$ciudad  = trim($_GET['ciudad'] ?? '');
$lat     = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lon     = isset($_GET['lon']) && is_numeric($_GET['lon']) ? (float)$_GET['lon'] : null;
$radioKm = min(max((float)($_GET['radio_km'] ?? 25), 1), 100);
$limit   = min(max((int)($_GET['limit'] ?? 3), 1), 5);

if ($ciudad === '' && ($lat === null || $lon === null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta "ciudad" o "lat+lon"']);
    exit;
}

/* ========== DB ========== */
$pdo = obtenerConexion();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

// Si vienen coordenadas directas, las usamos como si fueran el resultado de geocodificación
if ($lat !== null && $lon !== null) {
    $geo = ['lat' => $lat, 'lng' => $lon];
} elseif ($ciudad !== '') {
    $geo = geocodificarCiudadCache($ciudad);
} else {
    $geo = null;
}

if ($geo !== null) {
    $sql = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
                   c.telefono, c.whatsapp, c.website, c.email, c.email_clientes,
                   c.direccion_completa, c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.destacado, c.mensaje_whatsapp, c.horario_texto, c.zona_cobertura,
                   c.atencion_24h, c.recogida_domicilio, c.entrega_domicilio, 
                   c.cremacion_individual, c.cremacion_colectiva, c.sala_velatoria, 
                   c.urna, c.carta, c.molde, c.souvenires,
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
        
        // --- MICRO-RAG PARA LA IA ---
        $f['datos_extra'] = [
            // Contacto y enlaces
            'telefono_llamadas'    => $f['telefono'],
            'numero_whatsapp'      => !empty($f['whatsapp']) ? $f['whatsapp'] : false,
            'email_contacto'       => !empty($f['email_clientes']) ? $f['email_clientes'] : $f['email'],
            'sitio_web'            => $f['website'],
            
            // Info operativa
            'horarios'             => $f['horario_texto'],
            'zona_cobertura'       => $f['zona_cobertura'],
            
            // Servicios (Booleanos: true/false)
            'atencion_24h'         => (bool)$f['atencion_24h'],
            'recogida_domicilio'   => (bool)$f['recogida_domicilio'],
            'entrega_domicilio'    => (bool)$f['entrega_domicilio'],
            'cremacion_individual' => (bool)$f['cremacion_individual'],
            'cremacion_colectiva'  => (bool)$f['cremacion_colectiva'],
            'sala_velatoria'       => (bool)$f['sala_velatoria'],
            
            // Extras incluidos (Booleanos: true/false)
            'incluye_urna'         => (bool)$f['urna'],
            'incluye_carta_despedida'=> (bool)$f['carta'],
            'incluye_molde_huella' => (bool)$f['molde'],
            'incluye_souvenires'   => (bool)$f['souvenires']
        ];
    }
    unset($f);

    $metodo = 'geo';
    // --- NUEVO: RED DE SEGURIDAD PARA BÚSQUEDA EXTENDIDA ---
    if (count($fichas) === 0) {
        // Si no encontró nada en el radio de 100km, buscamos la 1 (UNA) opción más cercana en toda España
        $sqlFallback = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
                               c.telefono, c.whatsapp, c.website, c.email, c.email_clientes,
                               c.direccion_completa, c.latitud, c.longitud, c.rating, c.reviews_total,
                               c.destacado, c.mensaje_whatsapp, c.horario_texto, c.zona_cobertura,
                               c.atencion_24h, c.recogida_domicilio, c.entrega_domicilio, 
                               c.cremacion_individual, c.cremacion_colectiva, c.sala_velatoria, 
                               c.urna, c.carta, c.molde, c.souvenires,
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
                        ORDER BY distancia_km ASC
                        LIMIT 1"; // Traemos solo la más cercana para no abrumar

        $stmtFb = $pdo->prepare($sqlFallback);
        $stmtFb->bindValue(':lat1', $geo['lat']);
        $stmtFb->bindValue(':lat2', $geo['lat']);
        $stmtFb->bindValue(':lng1', $geo['lng']);
        $stmtFb->execute();
        $fichas = $stmtFb->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fichas as &$f) {
        $f['distancia_km'] = round((float) $f['distancia_km'], 1);
        
        // --- MICRO-RAG PARA LA IA ---
        $f['datos_extra'] = [
            // Contacto y enlaces
            'telefono_llamadas'    => $f['telefono'],
            'numero_whatsapp'      => !empty($f['whatsapp']) ? $f['whatsapp'] : false,
            'email_contacto'       => !empty($f['email_clientes']) ? $f['email_clientes'] : $f['email'],
            'sitio_web'            => $f['website'],
            
            // Info operativa
            'horarios'             => $f['horario_texto'],
            'zona_cobertura'       => $f['zona_cobertura'],
            
            // Servicios (Booleanos: true/false)
            'atencion_24h'         => (bool)$f['atencion_24h'],
            'recogida_domicilio'   => (bool)$f['recogida_domicilio'],
            'entrega_domicilio'    => (bool)$f['entrega_domicilio'],
            'cremacion_individual' => (bool)$f['cremacion_individual'],
            'cremacion_colectiva'  => (bool)$f['cremacion_colectiva'],
            'sala_velatoria'       => (bool)$f['sala_velatoria'],
            
            // Extras incluidos (Booleanos: true/false)
            'incluye_urna'         => (bool)$f['urna'],
            'incluye_carta_despedida'=> (bool)$f['carta'],
            'incluye_molde_huella' => (bool)$f['molde'],
            'incluye_souvenires'   => (bool)$f['souvenires']
        ];
    }
    unset($f);

        $metodo = 'geo_extendido'; // Etiqueta para saber que nos salimos del radio
    }
    // --- FIN NUEVA RED DE SEGURIDAD ---
} else {
    // Fallback: búsqueda por texto
    $sql = "SELECT c.id, c.nombre, c.slug, c.ciudad, p.nombre AS provincia,
                   c.telefono, c.whatsapp, c.website, c.direccion_completa,
                   c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.destacado, c.mensaje_whatsapp
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

    $metodo = 'texto';
}

echo json_encode([
    'ok'    => true,
    'ciudad' => $ciudad,
    'radio_km' => $radioKm,
    'metodo' => $metodo,
    'total' => count($fichas),
    'fichas' => $fichas,
], JSON_UNESCAPED_UNICODE);
