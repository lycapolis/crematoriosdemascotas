<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FUNCIONES HELPER PARA CONSULTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Proyecto: Crematorios de Mascotas
 * Autor: Lycapolis LLC
 */

// ═══════════════════════════════════════════════════════════
// HELPERS DE ASSETS — cache-busting
// ═══════════════════════════════════════════════════════════

/**
 * Devuelve la URL absoluta de un asset con `?v=<filemtime>` para cache-busting.
 * Cuando el archivo cambia, el browser baja la versión nueva sin hard reload.
 *
 * Uso:
 *   <link rel="stylesheet" href="<?php echo assetUrl('assets/css/componentes.css'); ?>">
 *   <script src="<?php echo assetUrl('assets/js/lead-capture.js'); ?>"></script>
 *
 * @param string $rutaRelativa Path relativo desde la raíz del proyecto (ej. 'assets/css/x.css')
 * @return string URL completa con `?v=...`
 */
function assetUrl(string $rutaRelativa): string {
    $rutaRelativa = ltrim($rutaRelativa, '/');
    $rutaAbsoluta = ROOT_PATH . '/' . $rutaRelativa;
    $version = is_file($rutaAbsoluta) ? filemtime($rutaAbsoluta) : 0;
    return BASE_URL . '/' . $rutaRelativa . '?v=' . $version;
}

// ═══════════════════════════════════════════════════════════
// PRECIOS — formateo de ítems de precios_json
// ═══════════════════════════════════════════════════════════

/**
 * Formatea el monto de un ítem de precio (precios_json) según su tipo.
 * Devuelve string vacío para tipo 'custom' (esos no muestran monto).
 * Usado en ficha.php (ficha pública) y en el mensaje WhatsApp del asistente.
 *
 * @param array $p Ítem de precios_json: {tipo, nombre, descripcion, min, max, destacado}
 */
function formatearPrecioItem(array $p): string {
    $simbolo = defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : '€';
    $fmt = function ($n) use ($simbolo) {
        return number_format((float)$n, (floor((float)$n) == (float)$n ? 0 : 2), ',', '.') . ' ' . $simbolo;
    };
    $min = $p['min'] ?? null;
    $max = $p['max'] ?? null;
    switch ($p['tipo'] ?? 'custom') {
        case 'fijo':  return ($min !== null && $min !== '') ? $fmt($min) : '';
        case 'desde': return ($min !== null && $min !== '') ? 'Desde ' . $fmt($min) : '';
        case 'rango':
            if ($min !== null && $min !== '' && $max !== null && $max !== '') return $fmt($min) . ' – ' . $fmt($max);
            if ($min !== null && $min !== '') return 'Desde ' . $fmt($min);
            return '';
        default: return ''; // custom
    }
}

/**
 * Elige el ítem de precio "representativo" de una lista (precios_json ya
 * decodificado y filtrado — ver ficha.php $precios_lista). Criterio:
 * el ítem marcado 'destacado' si existe; si no, el de menor 'min' entre
 * los que no son tipo 'custom' (esos no tienen monto comparable).
 *
 * @param array $preciosLista Lista de ítems ya decodificados (con 'nombre' no vacío).
 * @return array|null El ítem elegido, o null si la lista está vacía o todos son 'custom'.
 */
function elegirPrecioRepresentativo(array $preciosLista): ?array {
    if (empty($preciosLista)) return null;

    foreach ($preciosLista as $p) {
        if (!empty($p['destacado'])) return $p;
    }

    $conMonto = array_values(array_filter($preciosLista, function ($p) {
        return ($p['tipo'] ?? 'custom') !== 'custom' && $p['min'] !== null && $p['min'] !== '';
    }));
    if (empty($conMonto)) return null;

    usort($conMonto, fn($a, $b) => (float)$a['min'] <=> (float)$b['min']);
    return $conMonto[0];
}

// ═══════════════════════════════════════════════════════════
// MENSAJE WHATSAPP — plantilla automática (sin IA)
// ═══════════════════════════════════════════════════════════
// Ver admin/migrations/add_mensaje_whatsapp.sql para el esquema completo.
// Genera el mensaje "auto" a partir de datos YA cargados en la ficha —
// determinístico, sin llamadas a IA. Cada línea se omite si falta el dato.

/**
 * Genera el mensaje pre-formateado para WhatsApp de un negocio (versión "auto").
 *
 * @param array $cr Fila de `crematorios` — requiere al menos: nombre, ciudad,
 *                   provincia_nombre (join con provincias), telefono, rating,
 *                   reviews_total, recogida_domicilio, atencion_24h,
 *                   precios_json, cremacion_individual, cremacion_colectiva.
 * @return string Mensaje formateado (líneas separadas por \n), o '' si falta
 *                el dato mínimo (nombre).
 */
function generarMensajeWhatsappAuto(array $cr): string
{
    $nombre = trim((string) ($cr['nombre'] ?? ''));
    if ($nombre === '') return '';

    $lineas = [];

    // 🐾 Nombre — siempre presente
    $lineas[] = '🐾 ' . $nombre;

    // 📍 Ciudad + provincia
    $ciudad    = trim((string) ($cr['ciudad'] ?? ''));
    $provincia = trim((string) ($cr['provincia_nombre'] ?? ''));
    $ubicacion = $ciudad;
    if ($provincia !== '' && $provincia !== $ciudad) {
        $ubicacion = $ubicacion !== '' ? ($ubicacion . ', ' . $provincia) : $provincia;
    }
    if ($ubicacion !== '') $lineas[] = '📍 ' . $ubicacion;

    // 📞 Teléfono (flat, ya sincronizado desde telefonos_json por sincronizarCamposFlat())
    $telefono = trim((string) ($cr['telefono'] ?? ''));
    if ($telefono !== '') $lineas[] = '📞 ' . $telefono;

    // ⭐️ Rating + reseñas — se omite si no hay rating real
    $rating      = $cr['rating'] ?? null;
    $tieneRating = !($rating === null || $rating === '' || (float) $rating <= 0);
    if ($tieneRating) {
        $revTotal = (int) ($cr['reviews_total'] ?? 0);
        $linea = '⭐️ ' . number_format((float) $rating, 1);
        if ($revTotal > 0) $linea .= ' (' . $revTotal . ' reseña' . ($revTotal === 1 ? '' : 's') . ')';
        $lineas[] = $linea;
    }

    // 🚗 Recogida a domicilio · 🕐 Atención 24h — línea combinada, solo si al menos uno es true
    $extras = [];
    if (!empty($cr['recogida_domicilio'])) $extras[] = '🚗 Recogida a domicilio';
    if (!empty($cr['atencion_24h']))       $extras[] = '🕐 24h';
    if (!empty($extras)) $lineas[] = implode(' · ', $extras);

    // 💰 Precio representativo (destacado, sino el más barato) de precios_json
    if (!empty($cr['precios_json'])) {
        $decoded = json_decode((string) $cr['precios_json'], true);
        if (is_array($decoded)) {
            $preciosLista = array_values(array_filter($decoded, function ($p) {
                return is_array($p) && !empty(trim($p['nombre'] ?? ''));
            }));
            $elegido = elegirPrecioRepresentativo($preciosLista);
            if ($elegido) {
                $monto = formatearPrecioItem($elegido);
                if ($monto !== '') $lineas[] = '💰 ' . $monto;
            }
        }
    }

    // 🔥 Cremación individual / colectiva
    $tiposCremacion = [];
    if (!empty($cr['cremacion_individual'])) $tiposCremacion[] = 'individual';
    if (!empty($cr['cremacion_colectiva']))  $tiposCremacion[] = 'colectiva';
    if (!empty($tiposCremacion)) $lineas[] = '🔥 Cremación ' . implode(' y ', $tiposCremacion);

    return implode("\n", $lineas);
}

/**
 * Regenera y persiste la versión "auto" del mensaje WhatsApp de un crematorio,
 * SOLO si la versión actualmente activa en mensajes_whatsapp_json es de
 * origen 'auto' (o si no hay ninguna versión todavía). Si el admin activó una
 * versión 'manual' o 'ia', no se toca — se respeta su elección.
 *
 * Pensada para llamarse después de guardar cambios en editar-ficha-negocio.php,
 * así el mensaje se mantiene al día con teléfono/precio/rating sin intervención
 * manual, salvo que el admin haya optado por una versión distinta.
 *
 * @param PDO $pdo
 * @param int $crematorioId
 * @return bool true si se regeneró/actualizó, false si no había nada que hacer.
 */
function regenerarMensajeWhatsappAutoSiCorresponde(PDO $pdo, int $crematorioId): bool
{
    $stmt = $pdo->prepare("
        SELECT c.nombre, c.ciudad, c.telefono, c.rating, c.reviews_total,
               c.recogida_domicilio, c.atencion_24h, c.precios_json,
               c.cremacion_individual, c.cremacion_colectiva,
               c.mensajes_whatsapp_json,
               p.nombre AS provincia_nombre
        FROM crematorios c
        LEFT JOIN provincias p ON p.id = c.provincia_id
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $crematorioId]);
    $cr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cr) return false;

    $versiones = json_decode((string) ($cr['mensajes_whatsapp_json'] ?? ''), true);
    if (!is_array($versiones)) $versiones = [];

    $activa = null;
    foreach ($versiones as $v) {
        if (!empty($v['activo'])) { $activa = $v; break; }
    }

    // Si hay una versión activa y NO es 'auto', respetar la elección del admin.
    if ($activa !== null && ($activa['origen'] ?? 'auto') !== 'auto') return false;

    $nuevoTexto = generarMensajeWhatsappAuto($cr);
    if ($nuevoTexto === '') return false;

    $ahora = date('Y-m-d H:i:s');
    if ($activa !== null) {
        // Actualizar la entrada 'auto' existente in-place
        foreach ($versiones as &$v) {
            if (!empty($v['activo'])) {
                $v['valor']      = $nuevoTexto;
                $v['editado_at'] = $ahora;
                break;
            }
        }
        unset($v);
    } else {
        // Sin versiones todavía — crear la primera, activa
        $versiones[] = [
            'id'         => 'w' . substr(bin2hex(random_bytes(4)), 0, 8),
            'origen'     => 'auto',
            'valor'      => $nuevoTexto,
            'activo'     => true,
            'creado_at'  => $ahora,
            'editado_at' => null,
        ];
    }

    $upd = $pdo->prepare("UPDATE crematorios SET mensajes_whatsapp_json = :j, mensaje_whatsapp = :flat WHERE id = :id");
    $upd->execute([
        ':j'    => json_encode($versiones, JSON_UNESCAPED_UNICODE),
        ':flat' => $nuevoTexto,
        ':id'   => $crematorioId,
    ]);

    return true;
}

// ═══════════════════════════════════════════════════════════
// GEOCODING — Google Geocoding API
// ═══════════════════════════════════════════════════════════

/**
 * Geocodifica una dirección usando Google Geocoding API.
 *
 * Llamada de UNA SOLA VEZ por ficha: una vez que se obtienen lat/lng se
 * guardan en BD y no se vuelve a llamar. Costo: $5 / 1000 requests, gratis
 * dentro del free credit mensual de $200 de Google Maps Platform.
 *
 * @param string $direccion  Dirección completa (calle, número, ciudad...)
 * @param string $ciudad     Ciudad (para sesgar la búsqueda)
 * @param string $pais       Código ISO de país (default 'ES')
 * @return array {
 *   ok:        bool,
 *   lat:       float|null,
 *   lng:       float|null,
 *   place_id:  string|null,  // bonus: lo guardamos en google_place_id si vino vacío
 *   formatted: string|null,  // dirección normalizada por Google
 *   error:     string|null
 * }
 */
function geocodificarDireccion(string $direccion, string $ciudad = '', string $pais = 'ES'): array {
    $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    if (empty($apiKey)) {
        return ['ok' => false, 'error' => 'GOOGLE_MAPS_API_KEY no configurada'];
    }

    // Armar query: dirección + ciudad si la dirección no la incluye
    $query = trim($direccion);
    if ($ciudad !== '' && stripos($query, $ciudad) === false) {
        $query .= ', ' . $ciudad;
    }
    if ($query === '') {
        return ['ok' => false, 'error' => 'Dirección vacía'];
    }

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address'    => $query,
        'components' => 'country:' . strtoupper($pais),
        'language'   => 'es',
        'region'     => strtolower($pais),
        'key'        => $apiKey,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'error' => 'cURL: ' . $err];
    }
    if ($code !== 200) {
        return ['ok' => false, 'error' => "HTTP $code"];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Respuesta JSON inválida'];
    }

    $status = $data['status'] ?? '';
    if ($status !== 'OK' || empty($data['results'])) {
        return ['ok' => false, 'error' => 'Sin resultados (' . $status . ')'];
    }

    $r = $data['results'][0];
    $loc = $r['geometry']['location'] ?? null;
    if (!$loc || !isset($loc['lat'], $loc['lng'])) {
        return ['ok' => false, 'error' => 'Resultado sin coordenadas'];
    }

    // Guard: si Google no reconoce la dirección/ciudad, en vez de devolver
    // ZERO_RESULTS suele hacer fallback silencioso al país completo (types
    // = ['country','political'], location_type 'APPROXIMATE', partial_match
    // true), con coordenadas del centroide de España. Sin este chequeo,
    // ciudades inexistentes/mal escritas devolverían "resultados cerca de
    // Madrid" como si fueran reales. Rechazamos cualquier match que resuelva
    // solo a nivel país (nunca es un resultado útil para dirección o ciudad).
    $types = $r['types'] ?? [];
    if (in_array('country', $types, true)) {
        return ['ok' => false, 'error' => 'Google solo reconoció el país, no la dirección/ciudad (' . $query . ')'];
    }

    $cp = null;
    foreach ($r['address_components'] ?? [] as $comp) {
        if (in_array('postal_code', $comp['types'] ?? [], true)) { $cp = $comp['long_name'] ?? null; break; }
    }

    return [
        'ok'            => true,
        'lat'           => (float)$loc['lat'],
        'lng'           => (float)$loc['lng'],
        'place_id'      => $r['place_id']         ?? null,
        'formatted'     => $r['formatted_address'] ?? null,
        'codigo_postal' => $cp,
        'error'         => null,
    ];
}

/**
 * Geocodifica una ciudad con caché en BD (tabla `geocoding_cache`) para no
 * gastar cuota de Google en cada request. Las coordenadas de una ciudad no
 * cambian, así que el caché no tiene expiración.
 *
 * Usada por api-ai/asistente/recomendar-crematorios.php para poder filtrar
 * por radio_km real (Haversine, mismo patrón que cerca.php/cerca-mapa.php)
 * en vez de solo texto libre contra c.ciudad / c.ciudades_cobertura.
 *
 * @param string $ciudad Nombre de ciudad tal cual lo manda el usuario/IA.
 * @return array{lat:float,lng:float}|null null si no se pudo geocodificar
 *         (sin API key, sin resultados, error de red, etc.) — el llamador
 *         debe hacer fallback a búsqueda por texto en ese caso.
 */
function geocodificarCiudadCache(string $ciudad): ?array {
    $query = mb_strtolower(trim($ciudad));
    if ($query === '') return null;

    $pdo = function_exists('obtenerConexion') ? obtenerConexion() : null;
    if (!$pdo) {
        // Sin conexión no podemos cachear, pero igual intentamos geocodificar.
        $resultado = geocodificarDireccion($ciudad, '', 'ES');
        return $resultado['ok'] ? ['lat' => $resultado['lat'], 'lng' => $resultado['lng']] : null;
    }

    try {
        $st = $pdo->prepare("SELECT lat, lng FROM geocoding_cache WHERE query = :q LIMIT 1");
        $st->execute([':q' => $query]);
        $cache = $st->fetch(PDO::FETCH_ASSOC);
        if ($cache) {
            return ['lat' => (float)$cache['lat'], 'lng' => (float)$cache['lng']];
        }
    } catch (PDOException $e) {
        error_log('geocoding_cache lectura error: ' . $e->getMessage());
        // Seguimos e intentamos geocodificar igual, aunque no podamos cachear el resultado.
    }

    $resultado = geocodificarDireccion($ciudad, '', 'ES');
    if (!$resultado['ok']) return null;

    try {
        $pdo->prepare("INSERT INTO geocoding_cache (query, lat, lng) VALUES (:q, :lat, :lng)
                       ON DUPLICATE KEY UPDATE lat = :lat2, lng = :lng2")
            ->execute([
                ':q'    => $query,
                ':lat'  => $resultado['lat'],
                ':lng'  => $resultado['lng'],
                ':lat2' => $resultado['lat'],
                ':lng2' => $resultado['lng'],
            ]);
    } catch (PDOException $e) {
        error_log('geocoding_cache escritura error: ' . $e->getMessage());
    }

    return ['lat' => $resultado['lat'], 'lng' => $resultado['lng']];
}

// ═══════════════════════════════════════════════════════════
// RATE LIMIT — api-ai/asistente/*.php
// ═══════════════════════════════════════════════════════════

/**
 * Rate-limit por IP + endpoint para los endpoints de api-ai/asistente/*.php.
 * Mismo patrón que solicitudes_rate_limit / resenas_rate_limit (ventana fija
 * + contador en BD, falla abierta si la BD no responde para no bloquear al
 * asistente por un problema de infraestructura).
 *
 * Ventana de 1 minuto (más corta que el patrón de formularios humanos, que
 * usa ventanas de 1 hora) porque un asistente automatizado puede necesitar
 * varias llamadas por conversación en poco tiempo.
 *
 * @param string $endpoint     Nombre corto del endpoint (se limita por separado).
 * @param int    $maxPorMinuto Máximo de requests permitidos por IP en la ventana.
 * @return bool true si puede continuar, false si superó el límite.
 */
function asistenteRateLimitOk(string $endpoint, int $maxPorMinuto = ASISTENTE_RATE_LIMIT_POR_MINUTO): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') return true; // sin IP no podemos limitar, fallar abierto

    try {
        $pdo = function_exists('obtenerConexion') ? obtenerConexion() : null;
        if (!$pdo) return true;

        $ipHash  = hash('sha256', $ip);
        $ventana = date('Y-m-d H:i:00'); // ventana de 1 minuto

        $pdo->prepare("INSERT INTO api_asistente_rate_limit (ip_hash, endpoint, ventana, intentos)
                       VALUES (:h, :e, :v, 1)
                       ON DUPLICATE KEY UPDATE intentos = intentos + 1")
            ->execute([':h' => $ipHash, ':e' => $endpoint, ':v' => $ventana]);

        $st = $pdo->prepare("SELECT intentos FROM api_asistente_rate_limit
                              WHERE ip_hash = :h AND endpoint = :e AND ventana = :v");
        $st->execute([':h' => $ipHash, ':e' => $endpoint, ':v' => $ventana]);
        $intentos = (int) $st->fetchColumn();

        // Limpieza ocasional de ventanas viejas (no hace falta cron dedicado).
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM api_asistente_rate_limit WHERE actualizado_en < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
        }

        return $intentos <= $maxPorMinuto;
    } catch (PDOException $e) {
        error_log('rate-limit asistente error: ' . $e->getMessage());
        return true; // falla abierta: no bloquear al asistente por un problema de BD
    }
}

/**
 * Limpia las referencias `portada_principal_id` y `logo_principal_id` en la
 * tabla `crematorios` para imágenes que fueron borradas.
 *
 * Debe llamarse DESPUÉS de eliminar filas de `crematorio_imagenes`.
 *
 * Si no se llama, la columna queda apuntando a un ID que ya no existe y la
 * lógica de auto-asignación de portada/logo activo (que solo aplica cuando
 * la columna es NULL) no entra → ficha sin portada/logo activo visible.
 *
 * @param array $imagenIds  IDs de imágenes recién borradas
 */
function limpiarReferenciasImagenesBorradas(array $imagenIds): void {
    if (empty($imagenIds)) return;
    $pdo = obtenerConexion();
    if (!$pdo) return;

    $imagenIds = array_values(array_filter(array_map('intval', $imagenIds), fn($i) => $i > 0));
    if (empty($imagenIds)) return;

    $placeholders = implode(',', array_fill(0, count($imagenIds), '?'));
    $pdo->prepare("UPDATE crematorios SET portada_principal_id = NULL WHERE portada_principal_id IN ($placeholders)")
        ->execute($imagenIds);
    $pdo->prepare("UPDATE crematorios SET logo_principal_id    = NULL WHERE logo_principal_id    IN ($placeholders)")
        ->execute($imagenIds);
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE CREMATORIOS
// ═══════════════════════════════════════════════════════════

/**
 * Obtiene crematorios con filtros opcionales y paginación
 *
 * @param array $filtros ['provincia_id', 'comunidad_id', 'valoracion_min', 'busqueda']
 * @param int $pagina Número de página (desde 1)
 * @param int $porPagina Items por página
 * @return array ['datos' => [], 'total' => int, 'paginas' => int]
 */
function obtenerCrematorios($filtros = [], $pagina = 1, $porPagina = ITEMS_POR_PAGINA) {
    $pdo = obtenerConexion();
    if (!$pdo) return ['datos' => [], 'total' => 0, 'paginas' => 0];

    $where = ['1=1'];
    $params = [];

    // Solo fichas activas en el público. El admin puede pasar
    // $filtros['incluir_inactivas'] = true para ver todas.
    if (empty($filtros['incluir_inactivas'])) {
        $where[] = "c.estado = 'activa'";
    }

    // Filtro por provincia / comunidad: los negocios de esa zona + los que la tienen en su zona de servicio
    $zonaGeoNivel = ''; $zonaGeoId = 0;
    if (!empty($filtros['provincia_id'])) {
        $idsX = negociosQueSirvenGeo('provincia', (int) $filtros['provincia_id']);
        $where[] = $idsX ? '(c.provincia_id = :provincia_id OR c.id IN (' . implode(',', $idsX) . '))' : 'c.provincia_id = :provincia_id';
        $params[':provincia_id'] = $filtros['provincia_id'];
        $zonaGeoNivel = 'provincia'; $zonaGeoId = (int) $filtros['provincia_id'];
    }
    if (!empty($filtros['comunidad_id'])) {
        $idsX = negociosQueSirvenGeo('comunidad', (int) $filtros['comunidad_id']);
        $where[] = $idsX ? '(p.comunidad_id = :comunidad_id OR c.id IN (' . implode(',', $idsX) . '))' : 'p.comunidad_id = :comunidad_id';
        $params[':comunidad_id'] = $filtros['comunidad_id'];
        if ($zonaGeoNivel === '') { $zonaGeoNivel = 'comunidad'; $zonaGeoId = (int) $filtros['comunidad_id']; }
    }

    // Filtro por ciudad. Si es una ciudad del catálogo → todos los negocios que la sirven
    // (los de la ciudad + cobertura + 20 km), igual que su página. Si no → texto exacto, como antes.
    $zonaCiudadId = 0; $zonaCiudadNombre = '';
    if (!empty($filtros['ciudad'])) {
        $slugFiltro = slugificar((string) $filtros['ciudad']);
        $cands = array_values(array_filter(catalogoCiudadesVisibles(), fn($c) => $c['slug'] === $slugFiltro || in_array($slugFiltro, $c['alias'], true)));
        if (!empty($filtros['provincia_id'])) {
            $enProv = array_values(array_filter($cands, fn($c) => $c['provincia_id'] === (int) $filtros['provincia_id']));
            if ($enProv) $cands = $enProv;
        }
        if ($cands) {
            $zonaCiudadId     = $cands[0]['id'];
            $zonaCiudadNombre = $cands[0]['nombre'];
            $ids = array_map('intval', array_column(negociosQueSirvenCiudad($cands[0]), 'id'));
            $where[] = $ids ? 'c.id IN (' . implode(',', $ids) . ')' : '0';
            // la provincia/comunidad elegidas en otro filtro no deben esconder a los de la zona
            $where = array_values(array_filter($where, fn($w) => strpos($w, 'c.provincia_id = :provincia_id') === false && strpos($w, 'p.comunidad_id = :comunidad_id') === false));
            unset($params[':provincia_id'], $params[':comunidad_id']);
            $zonaGeoNivel = ''; $zonaGeoId = 0;
        } else {
            $where[] = 'LOWER(c.ciudad) = LOWER(:ciudad)';
            $params[':ciudad'] = $filtros['ciudad'];
        }
    }

    // Filtro por valoración mínima
    if (!empty($filtros['valoracion_min'])) {
        $where[] = 'c.rating >= :valoracion_min';
        $params[':valoracion_min'] = $filtros['valoracion_min'];
    }

    // Filtros por servicios (booleanos tinyint(1)=1 en crematorios)
    // Las claves usan EL MISMO nombre de la columna → sin mapeo, fácil de extender
    $serviciosBool = [
        'verificado',
        'cremacion_individual', 'cremacion_colectiva',
        'atencion_24h', 'sala_velatoria',
        'recogida_domicilio', 'entrega_domicilio',
        'urna', 'souvenires', 'carta', 'molde',
    ];
    foreach ($serviciosBool as $col) {
        if (!empty($filtros[$col])) {
            $where[] = "c.$col = 1";
        }
    }

    // Filtro "abiertos ahora": NO se puede en SQL puro (horarios es JSON con
    // rangos string). Se filtra en PHP DESPUÉS del WHERE → camino aparte
    // para mantener el paginado correcto.
    $filtroAbiertosAhora = !empty($filtros['abiertos_ahora']);

    // Filtro por búsqueda flexible (múltiples campos)
    if (!empty($filtros['busqueda'])) {
        $termino = '%' . $filtros['busqueda'] . '%';
        // Si lo escrito coincide (entero o por el comienzo de una palabra, mínimo 3 letras) con ciudades del
        // catálogo, también entran los negocios que las sirven (zona): «getaf» → Getafe, «polin» → Polinyà.
        $busqZonaSql = '';
        $slugBusq = slugificar((string) $filtros['busqueda']);
        if (strlen($slugBusq) >= 3) {
            $patron = '/(^|-)' . preg_quote($slugBusq, '/') . '/';
            $candsBusq = [];
            foreach (catalogoCiudadesVisibles() as $cc) {
                foreach (array_merge([$cc['slug']], $cc['alias']) as $sl) {
                    if ($sl === $slugBusq || preg_match($patron, $sl)) { $candsBusq[] = $cc; break; }
                }
            }
            $candsBusq = array_slice($candsBusq, 0, 5);
            foreach ($candsBusq as $cc) {
                $idsB = array_map('intval', array_column(negociosQueSirvenCiudad($cc), 'id'));
                if ($idsB) $busqZonaSql .= ' OR c.id IN (' . implode(',', $idsB) . ')';
            }
            // Con una sola ciudad coincidente se comporta como el filtro de ciudad (orden y etiqueta de zona)
            if (count($candsBusq) === 1 && !$zonaCiudadId) {
                $zonaCiudadId     = $candsBusq[0]['id'];
                $zonaCiudadNombre = $candsBusq[0]['nombre'];
            }
        }
        $where[] = '(c.nombre LIKE :busq_nombre
                    OR c.ciudad LIKE :busq_ciudad
                    OR c.descripcion LIKE :busq_desc
                    OR c.descripcion_google LIKE :busq_desc_g
                    OR c.prestaciones LIKE :busq_prest
                    OR c.servicios LIKE :busq_serv
                    OR c.facilidades LIKE :busq_fac
                    OR c.accesibilidad LIKE :busq_acc
                    OR c.direccion_completa LIKE :busq_dir
                    OR c.calle LIKE :busq_calle
                    OR c.distrito LIKE :busq_dist
                    OR c.subtypes LIKE :busq_sub
                    OR p.nombre LIKE :busq_prov' . $busqZonaSql . ')';
        $params[':busq_nombre'] = $termino;
        $params[':busq_ciudad'] = $termino;
        $params[':busq_desc'] = $termino;
        $params[':busq_desc_g'] = $termino;
        $params[':busq_prest'] = $termino;
        $params[':busq_serv'] = $termino;
        $params[':busq_fac'] = $termino;
        $params[':busq_acc'] = $termino;
        $params[':busq_dir'] = $termino;
        $params[':busq_calle'] = $termino;
        $params[':busq_dist'] = $termino;
        $params[':busq_sub'] = $termino;
        $params[':busq_prov'] = $termino;
    }

    $whereSQL = implode(' AND ', $where);

    // Orden — soporta los valores expuestos en el dropdown público
    // Default: puntaje de ranking (insignias + calidad, ver includes/insignias.php). `destacado`
    // y `puntaje_ranking` ya van alineados: el motor de insignias escribe ambos.
    $ordenSql = "c.puntaje_ranking DESC, c.rating DESC, c.nombre ASC"; // default "Mejor valorados"
    switch ($filtros['orden'] ?? '') {
        case 'nombre':       $ordenSql = "c.nombre ASC"; break;
        case 'calificacion': $ordenSql = "c.rating DESC, c.reviews_total DESC"; break;
        case 'recientes':    $ordenSql = "c.created_at DESC, c.id DESC"; break;
        case 'mas_resenas':  $ordenSql = "c.reviews_total DESC, c.rating DESC"; break;
    }

    // Con una ciudad filtrada y orden por defecto: primero los negocios de la ciudad, luego los de la zona.
    if ($zonaCiudadId && empty($filtros['orden'])) {
        $ordenSql = "(c.ciudad_id = " . (int) $zonaCiudadId . ") DESC, " . $ordenSql;
    } elseif ($zonaGeoNivel && empty($filtros['orden'])) {
        // Provincia/región elegida: primero los negocios de esa zona, luego los que la tienen en su zona de servicio
        $ordenSql = ($zonaGeoNivel === 'provincia' ? "(c.provincia_id = " : "(p.comunidad_id = ") . $zonaGeoId . ") DESC, " . $ordenSql;
    }

    $selectBase = "SELECT c.*,
                          p.comunidad_id AS geo_comunidad_id,
                          p.nombre AS provincia_nombre,
                          p.slug AS provincia_slug,
                          ca.nombre AS comunidad_nombre,
                          ca.slug AS comunidad_slug
                   FROM crematorios c
                   LEFT JOIN provincias p ON c.provincia_id = p.id
                   LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
                   WHERE $whereSQL
                   ORDER BY $ordenSql";

    // Camino A: filtro "abiertos ahora" — traer TODO lo que pasa los demás
    // filtros, filtrar en PHP por horarios, y paginar el resultado filtrado.
    if ($filtroAbiertosAhora) {
        $stmt = $pdo->prepare($selectBase);
        $stmt->execute($params);
        $todos = $stmt->fetchAll();
        $filtrados = array_values(array_filter($todos, function ($c) {
            return estaAbiertoAhora($c['horarios'] ?? null);
        }));
        $total = count($filtrados);
        $paginas = $porPagina ? (int) ceil($total / $porPagina) : 0;
        $offset = ($pagina - 1) * $porPagina;
        $datos = array_slice($filtrados, $offset, $porPagina);
        enriquecerConFotoLocal($datos);
        if ($zonaCiudadId) marcarNegociosDeZona($datos, $zonaCiudadId, $zonaCiudadNombre);
        elseif ($zonaGeoNivel) marcarNegociosDeZonaGeo($datos, $zonaGeoNivel, $zonaGeoId);
        return ['datos' => $datos, 'total' => $total, 'paginas' => $paginas, 'zona_ciudad' => $zonaCiudadNombre];
    }

    // Camino B (default): count + LIMIT/OFFSET en SQL.
    $sqlCount = "SELECT COUNT(*) FROM crematorios c
                 LEFT JOIN provincias p ON c.provincia_id = p.id
                 WHERE $whereSQL";
    $stmt = $pdo->prepare($sqlCount);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $paginas = (int) ceil($total / $porPagina);
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare($selectBase . " LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $datos = $stmt->fetchAll();
    enriquecerConFotoLocal($datos);
    if ($zonaCiudadId) marcarNegociosDeZona($datos, $zonaCiudadId, $zonaCiudadNombre);
    elseif ($zonaGeoNivel) marcarNegociosDeZonaGeo($datos, $zonaGeoNivel, $zonaGeoId);

    return [
        'datos' => $datos,
        'total' => $total,
        'paginas' => $paginas,
        'zona_ciudad' => $zonaCiudadNombre,
    ];
}

/** Marca los negocios que no están en la provincia/región filtrada pero la tienen en su zona de servicio. */
function marcarNegociosDeZonaGeo(array &$datos, string $nivel, int $id): void
{
    $pdo = obtenerConexion();
    if (!$pdo) return;
    $q = $pdo->prepare($nivel === 'provincia' ? "SELECT nombre FROM provincias WHERE id = ?" : "SELECT nombre FROM comunidades_autonomas WHERE id = ?");
    $q->execute([$id]);
    $nombre = (string) $q->fetchColumn();
    if ($nombre === '') return;
    foreach ($datos as &$d) {
        $propio = $nivel === 'provincia' ? ((int) ($d['provincia_id'] ?? 0) === $id) : ((int) ($d['geo_comunidad_id'] ?? 0) === $id);
        if (!$propio) $d['zona_ciudad'] = $nombre;
    }
    unset($d);
}

/** Marca los negocios que no están en la ciudad filtrada pero la sirven (para la etiqueta «También da servicio en…»). */
function marcarNegociosDeZona(array &$datos, int $ciudadId, string $ciudadNombre): void
{
    foreach ($datos as &$d) {
        if ((int) ($d['ciudad_id'] ?? 0) !== $ciudadId) $d['zona_ciudad'] = $ciudadNombre;
    }
    unset($d);
}

/**
 * ¿Está abierto AHORA según los horarios JSON de la ficha?
 * Shape esperado: {"lv": "09:30-14:00 y 15:30-19:00", "s": "08:00-21:00", "d": null}
 * o el día puede ser "24h" o null. Multi-rangos separados por " y ".
 * Soporta rangos que cruzan medianoche (fin < ini).
 *
 * Nota: usa el reloj de PHP (timezone del server). Hay un pendiente para
 * alinear TZ PHP/MySQL globalmente (ver [[project_pendientes_flujo_alta_ficha]]).
 */
function estaAbiertoAhora($horariosJson) {
    if (empty($horariosJson)) return false;
    $h = is_array($horariosJson) ? $horariosJson : json_decode($horariosJson, true);
    if (!is_array($h)) return false;

    // Día de la semana → clave: lv (lun-vie), s (sáb), d (dom)
    $dow = (int) date('N'); // 1=Mon..7=Sun
    $key = ($dow >= 1 && $dow <= 5) ? 'lv' : (($dow === 6) ? 's' : 'd');
    $rango = $h[$key] ?? null;
    if (empty($rango)) return false;
    $rango = trim((string) $rango);
    if (strcasecmp($rango, '24h') === 0) return true;

    $ahoraMin = ((int) date('G')) * 60 + (int) date('i');
    $partes   = preg_split('/\s+y\s+/i', $rango);
    foreach ($partes as $p) {
        if (preg_match('/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', $p, $m)) {
            $ini = (int) $m[1] * 60 + (int) $m[2];
            $fin = (int) $m[3] * 60 + (int) $m[4];
            if ($fin >= $ini) {
                if ($ahoraMin >= $ini && $ahoraMin <= $fin) return true;
            } else {
                // cruza medianoche
                if ($ahoraMin >= $ini || $ahoraMin <= $fin) return true;
            }
        }
    }
    return false;
}

/**
 * Obtiene un crematorio por su slug
 *
 * @param string $slug
 * @return array|null
 */
function obtenerCrematorioSlug($slug) {
    $pdo = obtenerConexion();
    if (!$pdo) return null;

    $sql = "SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE c.slug = :slug
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);

    return $stmt->fetch() ?: null;
}

/**
 * Obtiene crematorios destacados para la home
 * Si no hay suficientes destacados manuales, completa con los mejor valorados
 * de distintas provincias (variedad geográfica + rotación aleatoria)
 *
 * @param int $limite
 * @return array
 */
function obtenerDestacados($limite = DESTACADOS_HOME) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    // 1. Primero obtener destacados manuales
    $sql = "SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.destacado = 1 AND c.estado = 'activa'
            ORDER BY c.puntaje_ranking DESC, c.rating DESC
            LIMIT :limite";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $destacados = $stmt->fetchAll();

    // 2. Si hay suficientes destacados manuales, devolverlos
    if (count($destacados) >= $limite) {
        return array_slice($destacados, 0, $limite);
    }

    // 3. Calcular cuántos faltan
    $faltan = $limite - count($destacados);
    $provinciasUsadas = array_column($destacados, 'provincia_id');

    // 4. Completar con mejor valorados de otras provincias
    $sqlFallback = "SELECT c.*,
                           p.nombre AS provincia_nombre,
                           p.slug AS provincia_slug
                    FROM crematorios c
                    LEFT JOIN provincias p ON c.provincia_id = p.id
                    WHERE c.destacado = 0 AND c.estado = 'activa'
                      AND c.rating IS NOT NULL
                      AND c.rating > 0";

    $params = [];

    // Excluir provincias ya usadas por destacados manuales
    if (!empty($provinciasUsadas)) {
        $placeholders = [];
        foreach ($provinciasUsadas as $i => $provId) {
            $key = ':prov' . $i;
            $placeholders[] = $key;
            $params[$key] = $provId;
        }
        $sqlFallback .= " AND c.provincia_id NOT IN (" . implode(',', $placeholders) . ")";
    }

    $sqlFallback .= " ORDER BY c.provincia_id, c.puntaje_ranking DESC, c.rating DESC";

    $stmt = $pdo->prepare($sqlFallback);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $candidatos = $stmt->fetchAll();

    // 5. Agrupar por provincia y tomar el mejor de cada una
    $porProvincia = [];
    foreach ($candidatos as $crem) {
        $provId = $crem['provincia_id'];
        if (!isset($porProvincia[$provId])) {
            $porProvincia[$provId] = $crem;
        }
    }

    // 6. Mezclar aleatoriamente y tomar los que faltan
    $fallback = array_values($porProvincia);
    shuffle($fallback);
    $fallback = array_slice($fallback, 0, $faltan);

    // 7. Combinar destacados manuales + fallback
    $resultado = array_merge($destacados, $fallback);
    enriquecerConFotoLocal($resultado);
    return $resultado;
}

/**
 * Negocios (fichas activas) que sirven a una ciudad del catálogo: los que están en ella, los que
 * la tienen en su cobertura y los que quedan a menos de 20 km. Primero los de la ciudad.
 * Cada fila trae `en_ciudad` (bool). Sin fotos enriquecidas (eso lo hace el que pinta).
 *
 * @param array $ciudad fila del catálogo (ver catalogoCiudadesVisibles / obtenerCiudadPagina)
 */
function negociosQueSirvenCiudad(array $ciudad): array
{
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    // El prefiltro SQL es una caja holgada; la distancia exacta y la cobertura se comprueban en PHP.
    $where  = ['c.ciudad_id = :cid'];
    $params = [':cid' => $ciudad['id']];
    $nombres = array_unique(array_merge([$ciudad['nombre']], $ciudad['alias'] ?? []));
    foreach (array_values($nombres) as $i => $n) {
        $where[] = "c.ciudades_cobertura LIKE :cob$i";
        $params[":cob$i"] = '%' . $n . '%';
    }
    $tieneCoords = $ciudad['lat'] !== null && $ciudad['lng'] !== null;
    if ($tieneCoords) {
        $where[] = '(c.latitud BETWEEN :la1 AND :la2 AND c.longitud BETWEEN :lo1 AND :lo2)';
        $params[':la1'] = $ciudad['lat'] - 0.3; $params[':la2'] = $ciudad['lat'] + 0.3;
        $params[':lo1'] = $ciudad['lng'] - 0.4; $params[':lo2'] = $ciudad['lng'] + 0.4;
    }

    $stmt = $pdo->prepare("SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE c.estado = 'activa' AND (" . implode(' OR ', $where) . ")
            ORDER BY c.puntaje_ranking DESC, c.rating DESC");
    $stmt->execute($params);

    $slugsCiudad = array_merge([$ciudad['slug']], $ciudad['alias'] ?? []);
    $propias = []; $zona = [];
    foreach ($stmt->fetchAll() as $c) {
        if ((int) $c['ciudad_id'] === (int) $ciudad['id']) { $c['en_ciudad'] = true; $propias[] = $c; continue; }

        $sirve = false;
        foreach (preg_split('/[,;]+/', (string) $c['ciudades_cobertura']) as $tok) {
            if (in_array(slugificar($tok), $slugsCiudad, true)) { $sirve = true; break; }
        }
        if (!$sirve && $tieneCoords && (float) $c['latitud'] != 0 && (float) $c['longitud'] != 0) {
            $sirve = distanciaKmEntre((float) $ciudad['lat'], (float) $ciudad['lng'], (float) $c['latitud'], (float) $c['longitud']) <= 20.0;
        }
        if ($sirve) {
            $c['en_ciudad'] = false;
            $c['distancia_ciudad_km'] = ($tieneCoords && (float) $c['latitud'] != 0)
                ? distanciaKmEntre((float) $ciudad['lat'], (float) $ciudad['lng'], (float) $c['latitud'], (float) $c['longitud']) : 9999;
            $zona[] = $c;
        }
    }
    // En la zona: mismo ranking de siempre; a igualdad, la más cercana primero (el orden previo se conserva por ser estable)
    usort($zona, fn($x, $y) => [$y['puntaje_ranking'], $y['rating'], $x['distancia_ciudad_km']] <=> [$x['puntaje_ranking'], $x['rating'], $y['distancia_ciudad_km']]);

    return array_merge($propias, $zona);
}

function obtenerCrematoriosCiudad($ciudadSlug, $provinciaSlug) {
    $ciudad = obtenerCiudadPagina((string) $ciudadSlug, (string) $provinciaSlug);
    if (!$ciudad) return [];
    $crematorios = negociosQueSirvenCiudad($ciudad);
    enriquecerConFotoLocal($crematorios);
    return $crematorios;
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE GEOGRAFÍA
// ═══════════════════════════════════════════════════════════

/**
 * Obtiene todas las comunidades autónomas
 *
 * @return array
 */
function obtenerComunidades() {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    // El filtro estado='activa' va en el ON del LEFT JOIN para no perder
    // comunidades sin fichas activas (un WHERE lo convertiría en INNER).
    $sql = "SELECT ca.*,
                   COUNT(DISTINCT p.id) AS total_provincias,
                   COUNT(DISTINCT c.id) AS total_crematorios
            FROM comunidades_autonomas ca
            LEFT JOIN provincias p ON p.comunidad_id = ca.id
            LEFT JOIN crematorios c ON c.provincia_id = p.id AND c.estado = 'activa'
            GROUP BY ca.id
            ORDER BY ca.nombre";

    return $pdo->query($sql)->fetchAll();
}

/**
 * Obtiene una comunidad por slug
 *
 * @param string $slug
 * @return array|null
 */
function obtenerComunidadSlug($slug) {
    $pdo = obtenerConexion();
    if (!$pdo) return null;

    $sql = "SELECT * FROM comunidades_autonomas WHERE slug = :slug LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);

    return $stmt->fetch() ?: null;
}

/**
 * Obtiene todas las provincias, opcionalmente filtradas por comunidad
 *
 * @param int|null $comunidadId
 * @return array
 */
function obtenerProvincias($comunidadId = null) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT p.*,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug,
                   COUNT(c.id) AS total_crematorios
            FROM provincias p
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            LEFT JOIN crematorios c ON c.provincia_id = p.id AND c.estado = 'activa'";

    $params = [];
    if ($comunidadId) {
        $sql .= " WHERE p.comunidad_id = :comunidad_id";
        $params[':comunidad_id'] = $comunidadId;
    }

    $sql .= " GROUP BY p.id ORDER BY p.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Obtiene una provincia por slug
 *
 * @param string $slug
 * @return array|null
 */
function obtenerProvinciaSlug($slug) {
    $pdo = obtenerConexion();
    if (!$pdo) return null;

    $sql = "SELECT p.*,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM provincias p
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE p.slug = :slug
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);

    return $stmt->fetch() ?: null;
}

function obtenerCiudadesGlobal() {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT
                ci.nombre AS nombre,
                ci.slug AS slug,
                ci.provincia_id,
                p.comunidad_id,
                COUNT(*) AS total_crematorios
            FROM crematorios c
            JOIN ciudades ci ON ci.id = c.ciudad_id
            LEFT JOIN provincias p ON ci.provincia_id = p.id
            WHERE ci.activo = 1 AND ci.estado_revision <> 'pendiente'
              AND c.estado = 'activa'
            GROUP BY ci.id, ci.nombre, ci.slug, ci.provincia_id, p.comunidad_id
            ORDER BY ci.nombre";

    $stmt = $pdo->query($sql);
    return $stmt ? $stmt->fetchAll() : [];
}

function obtenerCiudadesProvincia($provinciaId) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT ci.nombre AS nombre, ci.slug AS slug, COUNT(*) AS total_crematorios
            FROM crematorios c
            JOIN ciudades ci ON ci.id = c.ciudad_id
            WHERE ci.provincia_id = :provincia_id
              AND ci.activo = 1 AND ci.estado_revision <> 'pendiente'
              AND c.estado = 'activa'
            GROUP BY ci.id, ci.nombre, ci.slug
            ORDER BY ci.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':provincia_id' => $provinciaId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Normaliza un texto a slug URL-safe (ASCII, lowercase, sin acentos, sin ñ).
 * Usado para ciudades, provincias y cualquier valor que vaya a una URL.
 *
 * Ejemplos:
 *   "Polinyà"               → "polinya"
 *   "Sant Fruitós de Bages" → "sant-fruitos-de-bages"
 *   "L'Hospitalet"          → "l-hospitalet"
 *   "Ñuñoa"                 → "nunoa"
 */
function slugificar(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') return '';

    // Transliterar a ASCII (quita acentos, ñ→n, etc.)
    if (function_exists('transliterator_transliterate')) {
        $texto = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $texto);
    } else {
        // Fallback sin intl: tabla manual completa.
        // Usar mb_strtolower (UTF-8-aware) ANTES del strtolower nativo, porque
        // strtolower() de PHP NO maneja multi-byte (deja 'Ú' como 'Ú' en vez de 'ú').
        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }
        $texto = strtr($texto, [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            'ñ'=>'n','ç'=>'c',
            // Defensa por si mb_strtolower no estaba disponible y quedó alguna mayúscula tildada
            'Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a','Ã'=>'a','Å'=>'a',
            'É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e',
            'Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i',
            'Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o','Õ'=>'o',
            'Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u',
            'Ñ'=>'n','Ç'=>'c',
        ]);
    }
    // Reemplazos finales: cualquier no-alfanumérico → guión, colapsar guiones
    $texto = preg_replace('/[^a-z0-9]+/i', '-', $texto);
    $texto = trim($texto, '-');
    return strtolower($texto);
}

/**
 * Busca una ciudad del catálogo por nombre (o slug, o alias) dentro de una provincia.
 * No crea nada. Devuelve la fila de `ciudades` o null.
 */
function obtenerCiudadPorNombre(string $nombre, int $provinciaId): ?array
{
    $slug = slugificar($nombre);
    $pdo  = obtenerConexion();
    if (!$pdo || $slug === '' || $provinciaId <= 0) return null;

    $st = $pdo->prepare("SELECT * FROM ciudades WHERE provincia_id = :p AND slug = :s LIMIT 1");
    $st->execute([':p' => $provinciaId, ':s' => $slug]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if ($fila) return $fila;

    // Alias (escrituras antiguas o alternativas: «elche» → Elx)
    $st = $pdo->prepare("SELECT * FROM ciudades WHERE provincia_id = :p AND slug_alias_json LIKE :a LIMIT 1");
    $st->execute([':p' => $provinciaId, ':a' => '%"' . $slug . '"%']);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Dado el texto de ciudad que escribió un negocio, devuelve el id de la ciudad del catálogo.
 * Si no la conocemos, la crea como «pendiente» (sin coordenadas) para que el admin la revise;
 * una ciudad pendiente no genera página pública. Devuelve null si falta texto o provincia.
 */
function resolverCiudadFicha(string $texto, int $provinciaId): ?int
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    if ($texto === '' || $provinciaId <= 0) return null;

    $existente = obtenerCiudadPorNombre($texto, $provinciaId);
    if ($existente) return (int) $existente['id'];

    $pdo = obtenerConexion();
    if (!$pdo) return null;
    $slug = slugificar($texto);
    if ($slug === '') return null;

    $st = $pdo->prepare("INSERT INTO ciudades (provincia_id, nombre, slug, estado_revision, nota)
                         VALUES (:p, :n, :s, 'pendiente', 'Propuesta por un negocio; revisar.')");
    $st->execute([':p' => $provinciaId, ':n' => mb_substr($texto, 0, 100), ':s' => $slug]);
    return (int) $pdo->lastInsertId();
}

/**
 * Enlaza una ficha con su ciudad del catálogo a partir del texto `crematorios.ciudad`
 * y su provincia. Se llama después de cualquier alta/edición que toque la ciudad.
 *  - Ciudad conocida → guarda ciudad_id y deja `ciudad` con el nombre canónico.
 *  - Ciudad desconocida → se crea «pendiente» (el admin la revisa); `ciudad` queda como se escribió.
 * `ciudad_original` guarda la primera escritura. Nunca lanza: si falta la migración, solo registra.
 */
function asignarCiudadFicha(PDO $pdo, int $fichaId): void
{
    try {
        $st = $pdo->prepare("SELECT ciudad, provincia_id, ciudad_original FROM crematorios WHERE id = :id");
        $st->execute([':id' => $fichaId]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        if (!$f) return;

        $texto = trim((string) $f['ciudad']);
        $cid   = resolverCiudadFicha($texto, (int) $f['provincia_id']);

        $nombre = null;
        if ($cid) {
            $q = $pdo->prepare("SELECT nombre, estado_revision FROM ciudades WHERE id = :id");
            $q->execute([':id' => $cid]);
            $c = $q->fetch(PDO::FETCH_ASSOC);
            if ($c && $c['estado_revision'] !== 'pendiente') $nombre = $c['nombre'];
        }

        $pdo->prepare("UPDATE crematorios
                          SET ciudad_id = :cid,
                              ciudad_original = COALESCE(ciudad_original, NULLIF(:orig, '')),
                              ciudad = COALESCE(:nombre, ciudad)
                        WHERE id = :id")
            ->execute([':cid' => $cid, ':orig' => $texto, ':nombre' => $nombre, ':id' => $fichaId]);
    } catch (Throwable $e) {
        error_log('[asignarCiudadFicha] ' . $e->getMessage());
    }
}

/**
 * Catálogo de ciudades visibles (aprobadas o dudosas, activas), cargado una vez por petición.
 * Cada fila trae provincia_slug/provincia_nombre y `alias` (array de slugs alternativos).
 * Las ciudades «pendientes» no se incluyen: no generan página ni enlaces públicos.
 */
function catalogoCiudadesVisibles(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $pdo = obtenerConexion();
    if (!$pdo) return $cache;
    try {
        $filas = $pdo->query("SELECT ci.id, ci.provincia_id, ci.nombre, ci.slug, ci.slug_alias_json, ci.lat, ci.lng, ci.estado_revision,
                                     p.slug AS provincia_slug, p.nombre AS provincia_nombre
                                FROM ciudades ci
                                JOIN provincias p ON p.id = ci.provincia_id
                               WHERE ci.activo = 1 AND ci.estado_revision <> 'pendiente'
                               ORDER BY ci.nombre")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return $cache; // migración sin correr: sin catálogo
    }
    foreach ($filas as $c) {
        $c['id']    = (int) $c['id'];
        $c['alias'] = json_decode((string) $c['slug_alias_json'], true) ?: [];
        $cache[]    = $c;
    }
    return $cache;
}

/** Distancia en km entre dos puntos (fórmula de Haversine). */
function distanciaKmEntre(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return 2 * 6371 * asin(min(1, sqrt($a)));
}

/**
 * Ciudades visibles del catálogo a menos de $km de un punto (por defecto 20 km, la zona de
 * servicio mínima de una ficha). Devuelve filas del catálogo + distancia_ciudad_km, de menor a mayor.
 */
function ciudadesEnRadio(float $lat, float $lng, float $km = 20.0): array
{
    $res = [];
    foreach (catalogoCiudadesVisibles() as $c) {
        if ($c['lat'] === null || $c['lng'] === null) continue;
        $d = distanciaKmEntre($lat, $lng, (float) $c['lat'], (float) $c['lng']);
        if ($d <= $km) { $c['distancia_ciudad_km'] = $d; $res[] = $c; }
    }
    usort($res, fn($a, $b) => $a['distancia_ciudad_km'] <=> $b['distancia_ciudad_km']);
    return $res;
}

/**
 * Busca la ciudad de una página pública (/espana/{provincia}/{ciudad}) por slug o alias.
 * Devuelve la fila del catálogo (+ via_alias=true si el slug pedido era un alias → hay que
 * redirigir al canónico) o null si no existe o está pendiente.
 */
function obtenerCiudadPagina(string $ciudadSlug, string $provinciaSlug): ?array
{
    $slug = slugificar($ciudadSlug);
    if ($slug === '') return null;
    foreach (catalogoCiudadesVisibles() as $c) {
        if ($c['provincia_slug'] !== $provinciaSlug) continue;
        if ($c['slug'] === $slug)                     return $c + ['via_alias' => false];
        if (in_array($slug, $c['alias'], true))       return $c + ['via_alias' => true];
    }
    return null;
}

/**
 * Provincias y regiones (con alias de nombres antiguos) para reconocer lo que escribe un negocio.
 * Jerarquía: país → región (comunidades_autonomas) → provincia → ciudad.
 */
function catalogoGeoZonas(): array
{
    static $geo = null;
    if ($geo !== null) return $geo;
    $geo = ['prov' => [], 'reg' => [], 'regPorId' => []];
    $pdo = obtenerConexion();
    if (!$pdo) return $geo;

    // Nombres antiguos o alternativos (slug → slug canónico). Ampliable.
    $aliasProv = ['gerona' => 'girona', 'orense' => 'ourense', 'la-coruna' => 'a-coruna', 'coruna' => 'a-coruna',
                  'vizcaya' => 'bizkaia', 'guipuzcoa' => 'gipuzkoa', 'lerida' => 'lleida', 'araba' => 'alava',
                  'baleares' => 'illes-balears', 'islas-baleares' => 'illes-balears'];
    $aliasReg  = ['catalunya' => 'cataluna', 'euskadi' => 'pais-vasco', 'asturias' => 'principado-de-asturias',
                  'murcia' => 'region-de-murcia', 'navarra' => 'comunidad-foral-de-navarra', 'valencia' => 'comunidad-valenciana'];
    try {
        foreach ($pdo->query("SELECT id, nombre, slug, comunidad_id FROM provincias WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $geo['prov'][$r['slug']] = ['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'slug' => $r['slug'], 'region_id' => (int) $r['comunidad_id']];
        }
        foreach ($pdo->query("SELECT id, nombre, slug FROM comunidades_autonomas WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $geo['reg'][$r['slug']] = ['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'slug' => $r['slug']];
            $geo['regPorId'][(int) $r['id']] = $r['slug'];
        }
    } catch (PDOException $e) { return $geo; }
    foreach ($aliasProv as $viejo => $canon) if (isset($geo['prov'][$canon]) && !isset($geo['prov'][$viejo])) $geo['prov'][$viejo] = $geo['prov'][$canon];
    foreach ($aliasReg  as $viejo => $canon) if (isset($geo['reg'][$canon])  && !isset($geo['reg'][$viejo]))  $geo['reg'][$viejo]  = $geo['reg'][$canon];
    return $geo;
}

/**
 * Clasifica un texto de cobertura escrito por el negocio.
 * Devuelve ['tipo' => ciudad|provincia|region|pais|texto|descartado|vacio, 'nombre' => canónico, 'url' => ..., 'id' => ...].
 *  - $prefer: 'ciudad' (texto de la caja de ciudades) o 'zona' (texto de la caja de provincias/regiones): decide qué se
 *    prueba primero cuando un nombre es ciudad y provincia a la vez («Barcelona»).
 *  - 'texto' = nombre limpio que no está en el catálogo (se muestra sin enlace). 'descartado' = frase o dato sucio
 *    («Madrid y comunidades limítrofes», «Sopelana (Bizkaia)»): no se muestra al público.
 */
function clasificarZonaToken(string $tok, int $provId = 0, string $prefer = 'ciudad'): array
{
    $tok = trim($tok);
    $s   = slugificar($tok);
    if ($s === '') return ['tipo' => 'vacio'];

    $geo = catalogoGeoZonas();
    $cat = catalogoCiudadesVisibles();

    $buscaCiudad = function () use ($s, $provId, $cat) {
        $hall = null;
        foreach ($cat as $c) {
            if ($c['provincia_id'] === $provId && ($c['slug'] === $s || in_array($s, $c['alias'], true))) { $hall = $c; break; }
        }
        if (!$hall) {
            $cand = array_values(array_filter($cat, fn($c) => $c['slug'] === $s || in_array($s, $c['alias'], true)));
            if (count($cand) === 1) $hall = $cand[0];
        }
        return $hall ? ['tipo' => 'ciudad', 'id' => $hall['id'], 'nombre' => $hall['nombre'], 'provincia_id' => $hall['provincia_id'],
                        'url' => generarUrl('ciudad', $hall['slug'], $hall['provincia_slug'])] : null;
    };
    $buscaProv = fn() => isset($geo['prov'][$s]) ? ['tipo' => 'provincia', 'id' => $geo['prov'][$s]['id'], 'nombre' => $geo['prov'][$s]['nombre'],
                        'region_id' => $geo['prov'][$s]['region_id'], 'url' => generarUrl('provincia', $geo['prov'][$s]['slug'])] : null;
    $buscaReg  = fn() => isset($geo['reg'][$s]) ? ['tipo' => 'region', 'id' => $geo['reg'][$s]['id'], 'nombre' => $geo['reg'][$s]['nombre'],
                        'url' => generarUrl('comunidad', $geo['reg'][$s]['slug'])] : null;

    $orden = $prefer === 'zona' ? [$buscaProv, $buscaReg, $buscaCiudad] : [$buscaCiudad, $buscaProv, $buscaReg];
    foreach ($orden as $fn) { if ($r = $fn()) return $r; }

    if (in_array($s, ['espana', 'portugal', 'francia', 'andorra'], true)) return ['tipo' => 'pais', 'nombre' => $tok, 'url' => null];

    $sucio = mb_strlen($tok) > 40 || preg_match('/[()\/]|\by\b|limitrof|principales|alrededores|areas?\b|mas de|nivel|servicio/i', $s . ' ' . $tok);
    return ['tipo' => $sucio ? 'descartado' : 'texto', 'nombre' => $tok, 'url' => null];
}

/**
 * Zona de servicio de una ficha para la ficha pública y el admin: su ciudad + lo que el negocio indicó
 * (ciudades, provincias, regiones) + todas las ciudades del catálogo a menos de $km de su dirección.
 * Cada elemento: ['nombre', 'url' (o null), 'tipo' => ciudad|provincia|region|pais|texto|descartado]. Orden alfabético, sin
 * duplicados. Los «descartados» (frases sucias) solo salen si $incluirDescartados (para que el admin los vea).
 * La provincia y la región propias de la ficha no se repiten: ya se muestran en su tarjeta.
 */
function zonaServicioFicha(array $ficha, float $km = 20.0, bool $incluirDescartados = false): array
{
    $geo    = catalogoGeoZonas();
    $provId = (int) ($ficha['provincia_id'] ?? 0);
    $propiaProv = null; $propiaReg = null;
    foreach ($geo['prov'] as $pv) if ($pv['id'] === $provId) { $propiaProv = $pv['id']; $propiaReg = $pv['region_id']; break; }

    $out = [];
    $poner = function (array $c, string $k) use (&$out) { if (!isset($out[$k])) $out[$k] = ['nombre' => $c['nombre'], 'url' => $c['url'] ?? null, 'tipo' => $c['tipo']]; };

    // 1) La ciudad de la ficha
    $cidFicha = (int) ($ficha['ciudad_id'] ?? 0);
    foreach (catalogoCiudadesVisibles() as $c) {
        if ($c['id'] === $cidFicha) { $poner(['tipo' => 'ciudad', 'nombre' => $c['nombre'], 'url' => generarUrl('ciudad', $c['slug'], $c['provincia_slug'])], 'c' . $c['id']); break; }
    }

    // 2) Lo que indicó el negocio (las dos cajas históricas)
    foreach ([['zona_cobertura', 'zona'], ['ciudades_cobertura', 'ciudad']] as [$col, $pref]) {
        foreach (preg_split('/[,;]+/', (string) ($ficha[$col] ?? '')) as $tok) {
            $c = clasificarZonaToken($tok, $provId, $pref);
            switch ($c['tipo']) {
                case 'vacio': break;
                case 'ciudad':    $poner($c, 'c' . $c['id']); break;
                case 'provincia': if ($c['id'] !== $propiaProv) $poner($c, 'p' . $c['id']); break;
                case 'region':    if ($c['id'] !== $propiaReg)  $poner($c, 'r' . $c['id']); break;
                case 'descartado': if ($incluirDescartados) $poner($c, 'd' . slugificar($c['nombre'])); break;
                default:          $poner($c, 'x' . slugificar($c['nombre']));
            }
        }
    }

    // 3) Mínimo garantizado: todo lo que esté a menos de $km de la ficha
    $lat = (float) ($ficha['latitud'] ?? 0);
    $lng = (float) ($ficha['longitud'] ?? 0);
    if ($lat != 0 && $lng != 0) {
        foreach (ciudadesEnRadio($lat, $lng, $km) as $c) {
            $poner(['tipo' => 'ciudad', 'nombre' => $c['nombre'], 'url' => generarUrl('ciudad', $c['slug'], $c['provincia_slug'])], 'c' . $c['id']);
        }
    }

    $lista = array_values($out);
    usort($lista, fn($a, $b) => strcoll(mb_strtolower($a['nombre']), mb_strtolower($b['nombre'])));
    return $lista;
}

/**
 * IDs de negocios (activos) que NO están en una provincia/región pero la tienen en su zona de servicio.
 * $nivel = 'provincia' | 'comunidad' (región). Una provincia indicada también cuenta para su región.
 */
function negociosQueSirvenGeo(string $nivel, int $id): array
{
    static $filas = null;
    $pdo = obtenerConexion();
    if (!$pdo) return [];
    if ($filas === null) {
        $filas = $pdo->query("SELECT c.id, c.provincia_id, c.zona_cobertura, c.ciudades_cobertura, p.comunidad_id
                                FROM crematorios c LEFT JOIN provincias p ON p.id = c.provincia_id
                               WHERE c.estado = 'activa'
                                 AND ((c.zona_cobertura IS NOT NULL AND c.zona_cobertura <> '')
                                   OR (c.ciudades_cobertura IS NOT NULL AND c.ciudades_cobertura <> ''))")->fetchAll(PDO::FETCH_ASSOC);
    }
    $ids = [];
    foreach ($filas as $fila) {
        if ($nivel === 'provincia' && (int) $fila['provincia_id'] === $id) continue;
        if ($nivel === 'comunidad' && (int) $fila['comunidad_id'] === $id) continue;
        foreach ([['zona_cobertura', 'zona'], ['ciudades_cobertura', 'ciudad']] as [$col, $pref]) {
            foreach (preg_split('/[,;]+/', (string) $fila[$col]) as $tok) {
                $c = clasificarZonaToken($tok, (int) $fila['provincia_id'], $pref);
                $ok = $nivel === 'provincia'
                    ? ($c['tipo'] === 'provincia' && $c['id'] === $id)
                    : (($c['tipo'] === 'region' && $c['id'] === $id) || ($c['tipo'] === 'provincia' && (int) $c['region_id'] === $id));
                if ($ok) { $ids[] = (int) $fila['id']; break 2; }
            }
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Devuelve la clase modificadora para .grid-tarjetas según la cantidad
 * de cards a mostrar, optimizando el balance visual del grid.
 *
 * Modo default ($maxCols = 4, para páginas full-width: home + geo):
 *   1-2   → 2 columnas
 *   3,5,6 → 3 columnas
 *   4 y 7+ → 4 columnas
 *
 * Modo directorio ($maxCols = 3, hay sidebar, no entran 4 cols):
 *   1, 2, 4 → 2 columnas
 *   3, 5+   → 3 columnas
 *
 * @param int $total    cantidad de cards a mostrar
 * @param int $maxCols  cap de columnas (3 = con sidebar, 4 = full-width)
 * @return string class string (ej: "grid-tarjetas--cols-4")
 */
function claseGridTarjetas(int $total, int $maxCols = 4): string {
    if ($maxCols <= 3) {
        // Directorio (con sidebar): cap en 3 columnas
        if ($total === 1 || $total === 2 || $total === 4) return 'grid-tarjetas--cols-2';
        return 'grid-tarjetas--cols-3'; // 3, 5+
    }
    // Default (sin sidebar): cap en 4 columnas
    if ($total <= 2) return 'grid-tarjetas--cols-2';
    if ($total === 3 || $total === 5 || $total === 6) return 'grid-tarjetas--cols-3';
    return 'grid-tarjetas--cols-4'; // 4, 7, 8, 9, ...
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE UTILIDAD
// ═══════════════════════════════════════════════════════════

/**
 * Genera HTML de estrellas de valoración
 *
 * @param float $valoracion (0-5)
 * @param int $tamano Tamaño en pixels (default: 20)
 * @return string HTML
 */
function generarEstrellas($valoracion, $tamano = 20) {
    $html = '<span class="estrellas">';
    $valoracion = max(0, min(5, $valoracion));

    for ($i = 1; $i <= 5; $i++) {
        $clase = ($i <= $valoracion) ? 'llena' : 'vacia';
        $html .= '<span class="estrella ' . $clase . '">';
        $html .= '<i data-lucide="star" style="width: ' . $tamano . 'px; height: ' . $tamano . 'px;"></i>';
        $html .= '</span>';
    }

    $html .= '</span>';
    return $html;
}

/**
 * Limpia y sanitiza texto para mostrar
 *
 * @param string $texto
 * @return string
 */
function limpiar($texto) {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Render seguro de la descripción pública: escapa TODO y recién después
 * convierte la convención de subtítulos **Título** en <strong> (solo negrita).
 * Como opera sobre texto ya escapado, el único HTML que se introduce es el
 * <strong> que controlamos → no se cuela HTML del LLM (sin XSS). Compatible
 * hacia atrás: texto sin ** no cambia.
 *
 * @param string|null $texto
 * @return string  HTML listo para echo (no re-escapar)
 */
function formatearDescripcionPublica(?string $texto): string {
    $e = htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
    $e = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $e);
    return nl2br($e);
}

/**
 * Normaliza una ruta de archivo para URLs (convierte backslashes a forward slashes)
 * @param string $ruta
 * @return string
 */
function normalizarRutaImagen($ruta) {
    return str_replace('\\', '/', $ruta ?? '');
}

/**
 * Genera URL amigable
 *
 * @param string $tipo ('crematorio', 'provincia', 'ciudad', 'comunidad')
 * @param string $slug
 * @param string|null $provinciaSlug Para ciudades
 * @return string
 */
function generarUrl($tipo, $slug, $provinciaSlug = null) {
    switch ($tipo) {
        case 'crematorio':
            return BASE_URL . '/' . $slug;
        case 'provincia':
            return BASE_URL . '/espana/' . $slug;
        case 'ciudad':
            return BASE_URL . '/espana/' . $provinciaSlug . '/' . $slug;
        case 'comunidad':
            return BASE_URL . '/espana/comunidad/' . $slug;
        default:
            return BASE_URL;
    }
}

/**
 * Formatea teléfono para mostrar
 *
 * @param string $telefono
 * @return string
 */
function formatearTelefono($telefono) {
    $limpio = preg_replace('/[^0-9+]/', '', $telefono);
    if (strlen($limpio) === 9) {
        return substr($limpio, 0, 3) . ' ' . substr($limpio, 3, 3) . ' ' . substr($limpio, 6);
    }
    return $telefono;
}

/**
 * Genera enlace de WhatsApp
 *
 * @param string $telefono
 * @param string $mensaje
 * @return string
 */
function generarWhatsApp($telefono, $mensaje = '') {
    $limpio = preg_replace('/[^0-9]/', '', $telefono);
    if (substr($limpio, 0, 2) !== '34' && strlen($limpio) === 9) {
        $limpio = '34' . $limpio;
    }
    $url = 'https://wa.me/' . $limpio;
    if ($mensaje) {
        $url .= '?text=' . urlencode($mensaje);
    }
    return $url;
}

// ═══════════════════════════════════════════════════════════
// RESOLUCIÓN DE WHATSAPP POR TIER / CONTEXTO
// ═══════════════════════════════════════════════════════════

/**
 * Resuelve el WhatsApp de soporte B2C según país.
 * Por ahora solo España está operativo; se escala agregando 'PE', 'MX', etc.
 *
 * @param string $pais Código ISO ('ES' | 'PE' | '')
 * @return string Número (solo dígitos) o '' si no hay canal
 */
function resolverWaSoportePais(string $pais): string {
    $mapa = [
        'ES' => defined('WHATSAPP_SOPORTE_ES_B2C') ? WHATSAPP_SOPORTE_ES_B2C : '',
        // 'PE' => defined('WHATSAPP_SOPORTE_PE_B2C') ? WHATSAPP_SOPORTE_PE_B2C : '',
    ];
    $key = strtoupper($pais);
    $num = $mapa[$key] ?? ($mapa['ES'] ?? '');
    return preg_replace('/[^0-9]/', '', $num);
}

/**
 * Devuelve las contacto_reglas de un tier, con caché estático por request.
 * Lee TODOS los tiers (incluidos inactivos): desactivar un plan solo impide
 * asignarlo a fichas nuevas; las fichas existentes conservan su ruteo.
 *
 * @param string $tierId  Id del tier ('00', '01', ...)
 * @return array          Reglas decodificadas (['sidebar'=>..., 'burbuja'=>...]) o []
 */
function obtenerTierContactoReglas(string $tierId): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $pdo = obtenerConexion();
        if ($pdo) {
            try {
                $rows = $pdo->query("SELECT id, contacto_reglas FROM tiers")->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($rows as $id => $json) {
                    $cache[(string)$id] = json_decode((string)$json, true) ?: [];
                }
            } catch (\Throwable) { /* tabla/columna ausente → se usa el fallback por defecto */ }
        }
    }
    return $cache[$tierId] ?? [];
}

/**
 * Determina el destino del WhatsApp según contexto y tier del negocio.
 * Lee contacto_reglas del tier (tabla tiers; si el array no la trae, la busca
 * ahí vía obtenerTierContactoReglas). Si no hay regla explícita, aplica fallback:
 *   sidebar → soporte solo si tier '00'
 *   burbuja → soporte si tier ∈ ['00','01','02']
 * Si el destino es 'negocio' pero la ficha no tiene WhatsApp cargado, cae a
 * soporte B2C (decisión de negocio: no ocultar el CTA, no perder el lead).
 *
 * @param array  $crematorio  Array con 'tier' y 'whatsapp'.
 * @param string $contexto    'sidebar' | 'burbuja'
 * @return string             Número destino (solo dígitos).
 */
function resolverWaDestino(array $crematorio, string $contexto): string {
    $negWa  = preg_replace('/[^0-9]/', '', $crematorio['whatsapp'] ?? '');
    $tier   = (string)($crematorio['tier'] ?? '01');
    $reglas = [];

    if (!empty($crematorio['contacto_reglas'])) {
        $reglas = json_decode((string)$crematorio['contacto_reglas'], true) ?: [];
    } else {
        // La columna vive en la tabla tiers, no en crematorios: si el caller no
        // la incluyó en el array, la resolvemos acá (máx. 1 query por request).
        $reglas = obtenerTierContactoReglas($tier);
    }

    $defaults = [
        'sidebar' => ($tier === '00') ? 'soporte' : 'negocio',
        'burbuja' => in_array($tier, ['00','01','02'], true) ? 'soporte' : 'negocio',
    ];
    $dest = $reglas[$contexto] ?? $defaults[$contexto] ?? 'negocio';

    if ($dest === 'soporte' || $negWa === '') {
        // TODO: resolver país a partir de comunidad/provincia del negocio.
        return resolverWaSoportePais('ES');
    }
    return $negWa;
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE RESEÑAS
// ═══════════════════════════════════════════════════════════

/**
 * Obtiene reseñas aprobadas de un crematorio
 *
 * @param int $crematorioId
 * @param int $limite
 * @return array
 */
function obtenerResenasAprobadas($crematorioId, $limite = 10) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT id, nombre, comentario, calificacion, fuente, created_at
            FROM resenas
            WHERE crematorio_id = :crematorio_id AND estado = 'aprobada'
            ORDER BY created_at DESC
            LIMIT :limite";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':crematorio_id', $crematorioId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Respuestas del negocio ya aprobadas, indexadas por id de reseña.
 *
 * @param int[] $resenaIds
 * @return array<int, array{texto:string, fecha:string}>
 */
function obtenerRespuestasAprobadas(array $resenaIds): array {
    $resenaIds = array_values(array_filter(array_map('intval', $resenaIds)));
    if (!$resenaIds) return [];
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    try {
        $ph = implode(',', array_fill(0, count($resenaIds), '?'));
        $stmt = $pdo->prepare("SELECT resena_id, texto, COALESCE(moderado_en, created_at) AS fecha
                               FROM resenas_respuestas
                               WHERE estado = 'aprobada' AND resena_id IN ($ph)
                               ORDER BY id ASC");
        $stmt->execute($resenaIds);
        $mapa = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa[(int)$r['resena_id']] = ['texto' => $r['texto'], 'fecha' => $r['fecha']];
        }
        return $mapa;
    } catch (PDOException $e) {
        error_log('obtenerRespuestasAprobadas: ' . $e->getMessage());
        return [];
    }
}

/**
 * Cuenta reseñas aprobadas de un crematorio
 *
 * @param int $crematorioId
 * @return int
 */
function contarResenasAprobadas($crematorioId) {
    $pdo = obtenerConexion();
    if (!$pdo) return 0;

    $sql = "SELECT COUNT(*) FROM resenas WHERE crematorio_id = :id AND estado = 'aprobada'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $crematorioId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Obtiene promedio de calificación de reseñas aprobadas
 *
 * @param int $crematorioId
 * @return float
 */
function promedioResenasAprobadas($crematorioId) {
    $pdo = obtenerConexion();
    if (!$pdo) return 0;

    $sql = "SELECT AVG(calificacion) FROM resenas WHERE crematorio_id = :id AND estado = 'aprobada'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $crematorioId]);

    return round((float) $stmt->fetchColumn(), 1);
}

function obtenerCoordenadasProvincia($provinciaId) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];
    $sql = "SELECT c.id, c.nombre, c.slug, c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.ciudad, c.foto_principal, c.verificado, c.destacado, c.origen,
                   p.nombre AS provincia_nombre
            FROM crematorios c
            JOIN provincias p ON c.provincia_id = p.id
            WHERE c.provincia_id = :id
              AND c.estado = 'activa'
              AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
              AND c.latitud != 0 AND c.longitud != 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $provinciaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    enriquecerConFotoLocal($rows);
    return $rows;
}

/**
 * Calcula el centro geográfico (promedio lat/lng) de una región a partir
 * de sus crematorios activos. Usado por `region-mapa.php` y por el botón
 * "Ver con mapa" de las páginas geo.
 *
 * Devuelve ['lat'=>float, 'lng'=>float, 'total'=>int, 'nombre'=>string,
 *          'provincia_nombre'=>string|null, 'comunidad_nombre'=>string|null]
 * o `null` si no hay crematorios activos en esa región.
 *
 * @param string      $nivel          'espana' | 'comunidad' | 'provincia' | 'ciudad'
 * @param string|null $slug           slug de la región (no aplica para 'espana')
 * @param string|null $provinciaSlug  solo para 'ciudad' (slug de la provincia que contiene la ciudad)
 */
function centroRegion(string $nivel, ?string $slug = null, ?string $provinciaSlug = null): ?array
{
    $pdo = obtenerConexion();
    if (!$pdo) return null;

    $base = "FROM crematorios c
             LEFT JOIN provincias p ON c.provincia_id = p.id
             LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
             WHERE c.estado = 'activa'
               AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
               AND c.latitud != 0 AND c.longitud != 0";

    $params = [];
    $nombre = '';
    $provNombre = null;
    $comNombre = null;

    switch ($nivel) {
        case 'espana':
            $nombre = 'España';
            break;

        case 'comunidad':
            if (!$slug) return null;
            $base .= " AND ca.slug = :s";
            $params[':s'] = $slug;
            $stmtNom = $pdo->prepare("SELECT nombre FROM comunidades_autonomas WHERE slug = :s LIMIT 1");
            $stmtNom->execute([':s' => $slug]);
            $nombre = (string)$stmtNom->fetchColumn();
            $comNombre = $nombre;
            if ($nombre === '') return null;
            break;

        case 'provincia':
            if (!$slug) return null;
            $base .= " AND p.slug = :s";
            $params[':s'] = $slug;
            $stmtNom = $pdo->prepare("SELECT p.nombre, ca.nombre AS comunidad
                                      FROM provincias p
                                      LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
                                      WHERE p.slug = :s LIMIT 1");
            $stmtNom->execute([':s' => $slug]);
            $row = $stmtNom->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $nombre = (string)$row['nombre'];
            $provNombre = $nombre;
            $comNombre  = $row['comunidad'] ?? null;
            break;

        case 'ciudad':
            if (!$slug || !$provinciaSlug) return null;
            // No filtramos por ciudad en SQL (slug en SQL no normaliza acentos);
            // traemos los de la provincia y filtramos por slugificar() en PHP abajo.
            $base .= " AND p.slug = :s";
            $params[':s'] = $provinciaSlug;
            break;

        default:
            return null;
    }

    if ($nivel === 'ciudad') {
        // Traer crematorios de la provincia, filtrar por slug de ciudad en PHP
        $sql = "SELECT c.latitud, c.longitud, c.ciudad, p.nombre AS prov_nombre, ca.nombre AS com_nombre $base";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $slugCiudad = slugificar($slug);
        $filtrados = array_values(array_filter($rows, function ($r) use ($slugCiudad) {
            return slugificar((string)$r['ciudad']) === $slugCiudad;
        }));
        if (empty($filtrados)) return null;

        $lat = array_sum(array_column($filtrados, 'latitud'))  / count($filtrados);
        $lng = array_sum(array_column($filtrados, 'longitud')) / count($filtrados);
        $nombre     = $filtrados[0]['ciudad'];
        $provNombre = $filtrados[0]['prov_nombre'] ?? null;
        $comNombre  = $filtrados[0]['com_nombre']  ?? null;

        return ['lat' => $lat, 'lng' => $lng, 'total' => count($filtrados),
                'nombre' => $nombre, 'provincia_nombre' => $provNombre,
                'comunidad_nombre' => $comNombre];
    }

    // España / comunidad / provincia: AVG en SQL
    $sql = "SELECT AVG(c.latitud) AS lat, AVG(c.longitud) AS lng, COUNT(*) AS total $base";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['total'] === 0) return null;

    return [
        'lat' => (float)$row['lat'],
        'lng' => (float)$row['lng'],
        'total' => (int)$row['total'],
        'nombre' => $nombre,
        'provincia_nombre' => $provNombre,
        'comunidad_nombre' => $comNombre,
    ];
}

/**
 * Coordenadas + datos para mapa nacional (toda España).
 * Usado por espana.php.
 */
function obtenerCoordenadasEspana() {
    $pdo = obtenerConexion();
    if (!$pdo) return [];
    $sql = "SELECT c.id, c.nombre, c.slug, c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.ciudad, c.foto_principal, c.verificado, c.destacado, c.origen,
                   p.nombre AS provincia_nombre
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.estado = 'activa'
              AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
              AND c.latitud != 0 AND c.longitud != 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    enriquecerConFotoLocal($rows);
    return $rows;
}

function obtenerCoordenadasCiudad($ciudadSlug, $provinciaSlug) {
    // Mismas fichas que muestra la página de la ciudad (la suya + las que la cubren), con coordenadas.
    return array_values(array_filter(
        obtenerCrematoriosCiudad($ciudadSlug, $provinciaSlug),
        fn($c) => (float) ($c['latitud'] ?? 0) != 0 && (float) ($c['longitud'] ?? 0) != 0
    ));
}

function obtenerCoordenadasComunidad($comunidadId) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];
    $sql = "SELECT c.id, c.nombre, c.slug, c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.ciudad, c.foto_principal, c.verificado, c.destacado, c.origen,
                   p.nombre AS provincia_nombre
            FROM crematorios c
            JOIN provincias p ON c.provincia_id = p.id
            WHERE p.comunidad_id = :id
              AND c.estado = 'activa'
              AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
              AND c.latitud != 0 AND c.longitud != 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $comunidadId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    enriquecerConFotoLocal($rows);
    return $rows;
}

function obtenerDesgloseResenas($crematorioId) {
    $pdo = obtenerConexion();
    if (!$pdo) return null;

    $sql = "SELECT calificacion, COUNT(*) AS cnt
            FROM resenas
            WHERE crematorio_id = :id AND estado = 'aprobada'
            GROUP BY calificacion";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $crematorioId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) return null;

    $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($rows as $row) {
        $counts[(int) $row['calificacion']] = (int) $row['cnt'];
    }
    $total = array_sum($counts);
    $suma  = 0;
    foreach ($counts as $stars => $cnt) $suma += $stars * $cnt;
    $media = $total > 0 ? round($suma / $total, 1) : 0;

    return ['counts' => $counts, 'total' => $total, 'media' => $media];
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES DE INDEXACIÓN (IndexNow)
// ═══════════════════════════════════════════════════════════

/**
 * Notifica a Bing y Yandex sobre una URL nueva o actualizada via IndexNow
 *
 * @param string|array $urls URL única o array de URLs (máx 10.000)
 * @return array Resultado con status de cada motor
 *
 * Uso:
 *   notificarIndexNow('https://crematoriosdemascotas.com/mi-crematorio');
 *   notificarIndexNow(['https://...url1', 'https://...url2']);
 */
function notificarIndexNow($urls) {
    // Solo ejecutar en producción
    if (!defined('INDEXNOW_ENABLED') || !INDEXNOW_ENABLED) {
        return ['skipped' => true, 'reason' => 'IndexNow deshabilitado en este entorno'];
    }

    if (!defined('INDEXNOW_KEY') || empty(INDEXNOW_KEY)) {
        return ['error' => 'INDEXNOW_KEY no configurada'];
    }

    // Normalizar a array
    $urls = is_array($urls) ? $urls : [$urls];

    // Validar que sean URLs absolutas
    foreach ($urls as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => "URL inválida: $url"];
        }
    }

    $key = INDEXNOW_KEY;
    $host = parse_url($urls[0], PHP_URL_HOST);
    $keyLocation = "https://{$host}/{$key}.txt";

    $resultados = [];

    // Motores que soportan IndexNow
    $motores = [
        'bing'   => 'https://www.bing.com/indexnow',
        'yandex' => 'https://yandex.com/indexnow',
    ];

    // Si es una sola URL, usar GET (más simple)
    if (count($urls) === 1) {
        $url = $urls[0];

        foreach ($motores as $nombre => $endpoint) {
            $params = http_build_query([
                'url' => $url,
                'key' => $key,
                'keyLocation' => $keyLocation
            ]);

            $fullUrl = "{$endpoint}?{$params}";

            $contexto = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'ignore_errors' => true,
                ]
            ]);

            $response = @file_get_contents($fullUrl, false, $contexto);
            $httpCode = 0;

            if (isset($http_response_header[0])) {
                preg_match('/\d{3}/', $http_response_header[0], $matches);
                $httpCode = (int)($matches[0] ?? 0);
            }

            $resultados[$nombre] = [
                'status' => ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'error',
                'http_code' => $httpCode,
            ];
        }
    }
    // Si son múltiples URLs, usar POST con JSON
    else {
        $payload = json_encode([
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => $urls
        ]);

        foreach ($motores as $nombre => $endpoint) {
            $contexto = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ]
            ]);

            $response = @file_get_contents($endpoint, false, $contexto);
            $httpCode = 0;

            if (isset($http_response_header[0])) {
                preg_match('/\d{3}/', $http_response_header[0], $matches);
                $httpCode = (int)($matches[0] ?? 0);
            }

            $resultados[$nombre] = [
                'status' => ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'error',
                'http_code' => $httpCode,
                'urls_enviadas' => count($urls)
            ];
        }
    }

    return $resultados;
}

/**
 * Genera la URL completa de un crematorio para IndexNow
 *
 * @param string $slug
 * @return string
 */
function urlCrematorio($slug) {
    return 'https://crematoriosdemascotas.com/' . $slug;
}

/**
 * Genera la URL completa de una provincia para IndexNow
 *
 * @param string $slug
 * @return string
 */
function urlProvincia($slug) {
    return 'https://crematoriosdemascotas.com/espana/' . $slug;
}

/**
 * Genera la URL completa de una ciudad para IndexNow
 *
 * @param string $provinciaSlug
 * @param string $ciudadSlug
 * @return string
 */
function urlCiudad($provinciaSlug, $ciudadSlug) {
    return 'https://crematoriosdemascotas.com/espana/' . $provinciaSlug . '/' . $ciudadSlug;
}

/**
 * Obtiene las imágenes de un crematorio
 *
 * @param int $crematorioId
 * @return array ['logo' => array|null, 'galeria' => array]
 */
/**
 * Carga las reglas de todos los tiers activos desde la BD.
 * Resultado cacheado en memoria para la petición actual.
 * Retorna array indexado por id de tier, compatible con el uso anterior de TIER_RULES.
 */
function obtenerTierRules(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $rows = $pdo->query(
        "SELECT * FROM tiers WHERE activo = 1 ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $cache = [];
    foreach ($rows as $row) {
        $cache[$row['id']] = [
            'nombre'      => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio'      => $row['precio_mensual'],
            'logo' => [
                'mostrar' => (bool) $row['logo_mostrar'],
                'fuentes' => json_decode($row['logo_fuentes'], true) ?? [],
            ],
            'portada' => [
                'mostrar' => (bool) $row['portada_mostrar'],
                'fuentes' => json_decode($row['portada_fuentes'], true) ?? [],
            ],
            'galeria_principal' => [
                'mostrar' => (bool) $row['galeria_principal_mostrar'],
                'fuentes' => json_decode($row['galeria_principal_fuentes'], true) ?? [],
            ],
            'galeria_categorias' => [
                'mostrar' => (bool) $row['galeria_categorias_mostrar'],
                'fuentes' => json_decode($row['galeria_categorias_fuentes'], true) ?? [],
            ],
        ];
    }
    return $cache;
}

function obtenerImagenesCrematorio($crematorioId) {
    $pdo = obtenerConexion();
    if (!$pdo) return ['logos' => [], 'portada' => null, 'galeria' => [], 'clientes' => []];

    $stmtPins = $pdo->prepare("SELECT logo_principal_id, portada_principal_id FROM crematorios WHERE id = :id");
    $stmtPins->execute([':id' => $crematorioId]);
    $pins = $stmtPins->fetch(PDO::FETCH_ASSOC) ?: [];
    $logoPrincipalId    = (int)($pins['logo_principal_id']    ?? 0);
    $portadaPrincipalId = (int)($pins['portada_principal_id'] ?? 0);

    // Joineamos resenas para que las fotos de clientes incluyan info de la reseña (autor, etc.)
    $sql = "SELECT ci.*,
                   r.nombre   AS resena_nombre,
                   r.comentario AS resena_comentario,
                   r.calificacion AS resena_calificacion,
                   r.created_at AS resena_fecha,
                   r.estado   AS resena_estado
            FROM crematorio_imagenes ci
            LEFT JOIN resenas r ON r.id = ci.resena_id
            WHERE ci.crematorio_id = :id
              AND (ci.visible = 1 OR ci.tipo IN ('logo', 'portada'))
            ORDER BY
                CASE ci.tipo WHEN 'logo' THEN 0 WHEN 'portada' THEN 1 ELSE 2 END ASC,
                ci.created_at DESC,
                ci.orden ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $crematorioId]);
    $imagenes = $stmt->fetchAll();

    $resultado = ['logos' => [], 'portada' => null, 'galeria' => [], 'clientes' => []];
    $logosPinned = [];
    $logosResto  = [];

    foreach ($imagenes as $img) {
        $img['ruta'] = str_replace('\\', '/', $img['ruta']);
        if ($img['tipo'] === 'logo') {
            $img['es_principal'] = ($logoPrincipalId && (int)$img['id'] === $logoPrincipalId);
            if ($img['es_principal']) {
                $logosPinned[] = $img;
            } else {
                $logosResto[] = $img;
            }
        } elseif ($img['tipo'] === 'portada') {
            $img['es_principal'] = ($portadaPrincipalId > 0 && (int)$img['id'] === $portadaPrincipalId);
            if ($img['es_principal']) {
                $resultado['portada'] = $img; // pin siempre gana
            } elseif ($resultado['portada'] === null && !$portadaPrincipalId) {
                $resultado['portada'] = $img; // auto: primera por created_at DESC
            }
        } elseif ($img['tipo'] === 'cliente') {
            // Solo se muestra si la reseña vinculada está aprobada
            // (si la reseña fue rechazada o eliminada, la foto NO aparece pública)
            if (($img['resena_estado'] ?? '') !== 'aprobada') continue;
            // Nivel de visibilidad (4 niveles anidados — ver migración add_visibilidad_cliente):
            //   oculta                → no se muestra en ningún lado
            //   solo_resena           → solo bajo su reseña (#4)
            //   solo_galerias_cliente → fuera de galerías del negocio (#1/#2), sí #3 + #4
            //   completa (default)    → en todos lados
            $vis = $img['visibilidad'] ?? 'completa';
            if ($vis === 'oculta') continue;
            $img['visibilidad'] = $vis;
            // Siempre disponible para #3 (Fotos de clientes) y #4 (bajo su reseña).
            // ficha.php decide #3 según $vis; #4 los muestra todos (menos 'oculta').
            $resultado['clientes'][] = $img;
            // Galerías del negocio (#1 principal + #2 por categoría, mismo pool):
            // solo si la visibilidad es completa.
            if ($vis === 'completa') {
                $imgConFlag = $img;
                $imgConFlag['desde_cliente'] = true;
                $resultado['galeria'][] = $imgConFlag;
            }
        } else {
            $img['es_portada_pinned'] = ($portadaPrincipalId > 0 && (int)$img['id'] === $portadaPrincipalId);
            if ($img['es_portada_pinned'] && $resultado['portada'] === null) {
                $resultado['portada'] = $img; // galeria pinned como portada
            } else {
                $resultado['galeria'][] = $img;
            }
        }
    }

    // Logo pinned primero; si no hay, el más reciente queda primero (orden ya DESC)
    $resultado['logos'] = array_merge($logosPinned, $logosResto);

    return $resultado;
}

/**
 * Enriquece un array de crematorios con su primera imagen local procesada.
 * Añade la clave 'foto_local' (URL absoluta) a cada elemento cuando existe.
 * Hace UNA sola query batch — no N queries.
 *
 * @param array $crematorios  Array de filas de crematorios (por referencia)
 */
function enriquecerConFotoLocal(array &$crematorios): void {
    if (empty($crematorios)) return;

    $pdo = obtenerConexion();
    if (!$pdo) return;

    $ids = array_column($crematorios, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT ci.crematorio_id, ci.ruta
         FROM crematorio_imagenes ci
         JOIN crematorios c ON c.id = ci.crematorio_id
         WHERE ci.estado_llm != 'error'
           AND ci.tipo IN ('galeria', 'portada', 'logo')
           AND (ci.visible = 1 OR ci.tipo IN ('logo', 'portada'))
           AND ci.crematorio_id IN ($placeholders)
         ORDER BY ci.crematorio_id,
             CASE
                 WHEN c.portada_principal_id IS NOT NULL AND ci.id = c.portada_principal_id THEN 0
                 WHEN ci.tipo = 'portada' AND ci.ruta NOT LIKE 'http%' THEN 1
                 WHEN ci.tipo = 'galeria' AND ci.ruta NOT LIKE 'http%' THEN 2
                 WHEN ci.tipo = 'logo'    AND ci.ruta NOT LIKE 'http%' THEN 3
                 WHEN ci.tipo = 'portada' AND ci.ruta LIKE 'http%'     THEN 4
                 WHEN ci.tipo = 'logo'    AND ci.ruta LIKE 'http%'     THEN 5
                 ELSE 6
             END ASC,
             ci.created_at DESC,
             ci.orden ASC,
             ci.id ASC"
    );
    $stmt->execute($ids);

    // Primera imagen por crematorio
    $fotoMap = [];
    while ($row = $stmt->fetch()) {
        if (!isset($fotoMap[$row['crematorio_id']])) {
            $ruta = str_replace('\\', '/', $row['ruta']);
            $fotoMap[$row['crematorio_id']] = str_starts_with($ruta, 'http')
                ? $ruta
                : BASE_URL . '/' . $ruta;
        }
    }

    foreach ($crematorios as &$crem) {
        if (isset($fotoMap[$crem['id']])) {
            $crem['foto_local'] = $fotoMap[$crem['id']];
        }
    }
    unset($crem);
}

// ═══════════════════════════════════════════════════════════
// SYNC JSON → FLAT (source of truth: JSON multi-fuente)
// ═══════════════════════════════════════════════════════════

/**
 * Sincroniza las columnas flat con los valores activos/principales de las columnas JSON
 * multi-fuente. Las JSON son la source of truth; las flat son cache denormalizado para
 * que ficha.php y queries SQL viejas sigan funcionando sin parsear JSON.
 *
 * Regla: si un JSON está NULL/vacío/malformado, NO toca el flat correspondiente (preserva
 * el valor existente). Si el JSON está poblado, los flat reflejan lo que indica el JSON
 * (incluyendo NULL si la entrada esperada no está).
 *
 * Mapeo:
 *   telefonos_json   → telefono           (tipo='principal'.numero)
 *                    → telefono_clientes  (tipo='clientes'.numero)
 *   emails_json      → email              (tipo='general'.email)
 *                    → email_clientes     (tipo='clientes'.email)
 *   descripciones_json → descripcion           (entrada con activo:true → valor)
 *   metas_json         → meta_description_seo  (entrada con activo:true → valor)
 *   redes_json       → (no tiene flat counterpart, no se sincroniza)
 *
 * @param PDO $pdo          Conexión activa
 * @param int $crematorioId ID del crematorio
 * @return array<string,?string> Resumen de columnas flat actualizadas (para debug/log)
 */
function sincronizarCamposFlat(PDO $pdo, int $crematorioId): array
{
    $stmt = $pdo->prepare(
        "SELECT telefonos_json, emails_json, descripciones_json, metas_json, mensajes_whatsapp_json
         FROM crematorios
         WHERE id = :id"
    );
    $stmt->execute([':id' => $crematorioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];

    $decodificar = function(?string $json): array {
        if ($json === null || $json === '' || $json === 'null') return [];
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    };

    $valorPorTipo = function(array $arr, string $tipo, string $campo): ?string {
        foreach ($arr as $entry) {
            if (($entry['tipo'] ?? '') === $tipo) {
                $v = trim((string)($entry[$campo] ?? ''));
                return $v === '' ? null : $v;
            }
        }
        return null;
    };

    $valorActivo = function(array $arr): ?string {
        foreach ($arr as $entry) {
            if (!empty($entry['activo'])) {
                $v = trim((string)($entry['valor'] ?? ''));
                return $v === '' ? null : $v;
            }
        }
        return null;
    };

    $tels  = $decodificar($row['telefonos_json']);
    $mails = $decodificar($row['emails_json']);
    $descs = $decodificar($row['descripciones_json']);
    $metas = $decodificar($row['metas_json']);
    $whats = $decodificar($row['mensajes_whatsapp_json']);

    $updates = [];

    if (!empty($tels)) {
        $updates['telefono']          = $valorPorTipo($tels, 'principal', 'numero');
        $updates['telefono_clientes'] = $valorPorTipo($tels, 'clientes',  'numero');
    }
    if (!empty($mails)) {
        $updates['email']           = $valorPorTipo($mails, 'general',  'email');
        $updates['email_clientes']  = $valorPorTipo($mails, 'clientes', 'email');
    }
    if (!empty($descs)) {
        $v = $valorActivo($descs);
        if ($v !== null) $updates['descripcion'] = $v;
    }
    if (!empty($metas)) {
        $v = $valorActivo($metas);
        if ($v !== null) $updates['meta_description_seo'] = $v;
    }
    if (!empty($whats)) {
        $v = $valorActivo($whats);
        if ($v !== null) $updates['mensaje_whatsapp'] = $v;
    }

    if (empty($updates)) return [];

    $sets   = [];
    $params = [':id' => $crematorioId];
    foreach ($updates as $col => $val) {
        $key          = ':v_' . $col;
        $sets[]       = "$col = $key";
        $params[$key] = $val;
    }

    $sql = "UPDATE crematorios SET " . implode(', ', $sets) . " WHERE id = :id";
    $pdo->prepare($sql)->execute($params);

    return $updates;
}

// ═══════════════════════════════════════════════════════════
// BITÁCORA DE USO DE IA POR CREMATORIO (ia_log_json)
// ═══════════════════════════════════════════════════════════

/**
 * Registra que una sección de un crematorio fue procesada con IA.
 * Acumula entradas en la columna ia_log_json (objeto con clave por sección).
 *
 * Estructura resultante:
 *   { "horarios": {"fecha": "2026-05-12 14:30:22", "modelo": "claude-haiku-4-5"}, ... }
 *
 * Idempotente — re-llamar para la misma sección sobreescribe la entrada anterior.
 * El path SQL se sanitiza para evitar inyección (solo a-z y _).
 *
 * @param PDO $pdo
 * @param int $crematorioId
 * @param string $seccion  ej: 'imagenes' | 'contenido' | 'horarios' | 'cobertura' | 'servicios' | 'seo'
 * @param string $modelo   ej: 'claude-haiku-4-5-20251001' | 'claude-sonnet-4-6' | 'legacy'
 * @return bool
 */
function registrarUsoIA(PDO $pdo, int $crematorioId, string $seccion, string $modelo): bool
{
    $seccion = preg_replace('/[^a-z_]/', '', strtolower($seccion));
    if ($seccion === '' || $crematorioId <= 0) return false;

    $path = '$.' . $seccion;

    $sql = "UPDATE crematorios
            SET ia_log_json = JSON_SET(
                COALESCE(ia_log_json, JSON_OBJECT()),
                '$path',
                JSON_OBJECT('fecha', :fecha, 'modelo', :modelo)
            )
            WHERE id = :id";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':fecha'  => date('Y-m-d H:i:s'),
            ':modelo' => $modelo,
            ':id'     => $crematorioId,
        ]);
    } catch (PDOException $e) {
        error_log("registrarUsoIA falló para id=$crematorioId, seccion=$seccion: " . $e->getMessage());
        return false;
    }
}

/**
 * Devuelve la bitácora IA de un crematorio.
 *
 * @return array Mapa seccion => ['fecha' => 'Y-m-d H:i:s', 'modelo' => '...'].
 *               Array vacío si nunca se procesó nada o si la columna está NULL.
 */
function obtenerLogIA(PDO $pdo, int $crematorioId): array
{
    $stmt = $pdo->prepare("SELECT ia_log_json FROM crematorios WHERE id = :id");
    $stmt->execute([':id' => $crematorioId]);
    $raw = $stmt->fetchColumn();
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Helper genérico para llamar a la API de Claude (Anthropic Messages).
 *
 * @param string $prompt    El texto del prompt (rol "user").
 * @param string $modelo    Default 'claude-haiku-4-5-20251001' (barato y rápido).
 * @param int    $maxTokens Límite de tokens de respuesta. Default 1500.
 * @return array ['ok'=>bool, 'texto'=>string|null, 'error'=>string|null, 'modelo'=>string]
 */
function llamarClaudeApi(string $prompt, string $modelo = 'claude-haiku-4-5-20251001', int $maxTokens = 1500): array
{
    $apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
    if (empty($apiKey)) {
        return ['ok' => false, 'texto' => null, 'error' => 'CLAUDE_API_KEY no configurada', 'modelo' => $modelo];
    }

    $payload = [
        'model'      => $modelo,
        'max_tokens' => $maxTokens,
        'messages'   => [[
            'role'    => 'user',
            'content' => [['type' => 'text', 'text' => $prompt]],
        ]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'texto' => null, 'error' => 'cURL: ' . $curlErr, 'modelo' => $modelo];
    }
    if ($code !== 200) {
        $msg = "HTTP $code";
        $data = json_decode($resp, true);
        if (!empty($data['error']['message'])) $msg .= ' — ' . $data['error']['message'];
        return ['ok' => false, 'texto' => null, 'error' => $msg, 'modelo' => $modelo];
    }

    $data  = json_decode($resp, true);
    $texto = $data['content'][0]['text'] ?? null;
    if ($texto === null) {
        return ['ok' => false, 'texto' => null, 'error' => 'Respuesta vacía de Claude', 'modelo' => $modelo];
    }
    return ['ok' => true, 'texto' => $texto, 'error' => null, 'modelo' => $modelo];
}

// ═══════════════════════════════════════════════════════════
// LLM MULTI-PROVEEDOR (Claude | OpenRouter) — config por sección
// ═══════════════════════════════════════════════════════════
//
// admin/configuracion-ia.php (solo super_admin) permite elegir, por cada
// tarea IA del panel, qué proveedor y modelo usar (tabla ia_config_secciones).
// llamarLLM() es el punto de entrada único recomendado para llamadas IA
// nuevas — reemplaza tanto a llamarClaudeApi() (arriba, se mantiene por
// compatibilidad) como a las llamadas de visión que antes tenía cada
// endpoint duplicadas con su propio curl.

/**
 * Llama a la API de Claude (Anthropic Messages), con soporte opcional de imagen.
 * Uso interno de llamarLLM() — no llamar directo salvo necesidad puntual.
 *
 * @param string|null $imagenBase64 Imagen en base64, o null para solo texto.
 */
function llamarClaudeApiInterno(string $prompt, string $modelo, int $maxTokens, ?string $imagenBase64, string $mediaType): array
{
    $apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
    if (empty($apiKey)) {
        return ['ok' => false, 'texto' => null, 'error' => 'CLAUDE_API_KEY no configurada', 'modelo' => $modelo, 'proveedor' => 'claude'];
    }

    $content = [];
    if ($imagenBase64 !== null) {
        $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $imagenBase64]];
    }
    $content[] = ['type' => 'text', 'text' => $prompt];

    $payload = [
        'model'      => $modelo,
        'max_tokens' => $maxTokens,
        'messages'   => [['role' => 'user', 'content' => $content]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'texto' => null, 'error' => 'cURL: ' . $curlErr, 'modelo' => $modelo, 'proveedor' => 'claude'];
    }
    if ($code !== 200) {
        $msg  = "HTTP $code";
        $data = json_decode($resp, true);
        if (!empty($data['error']['message'])) $msg .= ' — ' . $data['error']['message'];
        return ['ok' => false, 'texto' => null, 'error' => $msg, 'modelo' => $modelo, 'proveedor' => 'claude'];
    }

    $data  = json_decode($resp, true);
    $texto = $data['content'][0]['text'] ?? null;
    if ($texto === null) {
        return ['ok' => false, 'texto' => null, 'error' => 'Respuesta vacía de Claude', 'modelo' => $modelo, 'proveedor' => 'claude'];
    }
    return ['ok' => true, 'texto' => $texto, 'error' => null, 'modelo' => $modelo, 'proveedor' => 'claude'];
}

/**
 * Llama a OpenRouter (formato OpenAI-compatible /chat/completions), con
 * soporte opcional de imagen (image_url con data URI base64).
 * Uso interno de llamarLLM().
 */
function llamarOpenRouterApiInterno(string $prompt, string $modelo, int $maxTokens, ?string $imagenBase64, string $mediaType): array
{
    $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
    if (empty($apiKey)) {
        return ['ok' => false, 'texto' => null, 'error' => 'OPENROUTER_API_KEY no configurada', 'modelo' => $modelo, 'proveedor' => 'openrouter'];
    }

    if ($imagenBase64 !== null) {
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mediaType . ';base64,' . $imagenBase64]],
            ['type' => 'text', 'text' => $prompt],
        ];
    } else {
        $content = $prompt; // texto puro — OpenAI/OpenRouter acepta string simple
    }

    $payload = [
        'model'      => $modelo,
        'max_tokens' => $maxTokens,
        'messages'   => [['role' => 'user', 'content' => $content]],
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : 'https://crematoriosdemascotas.com'),
            'X-Title: Crematorios de Mascotas — Admin IA',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'texto' => null, 'error' => 'cURL: ' . $curlErr, 'modelo' => $modelo, 'proveedor' => 'openrouter'];
    }
    if ($code !== 200) {
        $msg  = "HTTP $code";
        $data = json_decode($resp, true);
        if (!empty($data['error']['message'])) $msg .= ' — ' . $data['error']['message'];
        return ['ok' => false, 'texto' => null, 'error' => $msg, 'modelo' => $modelo, 'proveedor' => 'openrouter'];
    }

    $data  = json_decode($resp, true);
    $texto = $data['choices'][0]['message']['content'] ?? null;
    if ($texto === null) {
        return ['ok' => false, 'texto' => null, 'error' => 'Respuesta vacía de OpenRouter', 'modelo' => $modelo, 'proveedor' => 'openrouter'];
    }
    return ['ok' => true, 'texto' => $texto, 'error' => null, 'modelo' => $modelo, 'proveedor' => 'openrouter'];
}

/**
 * Config (proveedor/modelo/max_tokens) de una sección IA, desde
 * ia_config_secciones (editable en admin/configuracion-ia.php).
 * Cacheada en memoria por request (evita N queries si se llama varias veces).
 *
 * @return array{proveedor:string, modelo:string, max_tokens:int}
 */
function obtenerConfigIA(PDO $pdo, string $seccion): array
{
    static $cache = [];
    if (isset($cache[$seccion])) return $cache[$seccion];

    $stmt = $pdo->prepare("SELECT proveedor, modelo, max_tokens FROM ia_config_secciones WHERE seccion = :s LIMIT 1");
    $stmt->execute([':s' => $seccion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback seguro si la sección no está en la tabla (no debería pasar
    // con el seed aplicado, pero evita fatal error si falta alguna).
    $cfg = $row ?: ['proveedor' => 'claude', 'modelo' => 'claude-haiku-4-5-20251001', 'max_tokens' => 1500];
    $cfg['max_tokens'] = (int) $cfg['max_tokens'];

    $cache[$seccion] = $cfg;
    return $cfg;
}

/**
 * Config de throttling del widget lead-capture, desde la tabla
 * formularios_config (editable en admin/configuracion-formularios.php,
 * solo super_admin). Se inyecta al front como window.LC_THROTTLE en
 * includes/footer.php y la consume assets/js/lead-capture.js.
 *
 * Cuando throttling_activo = 0 el JS abre el modal en CADA click
 * (sin cap de sesión ni silencios post-skip/post-submit). El resto de
 * valores son los límites que aplican al activarlo.
 *
 * Cacheada en memoria por request (evita query repetida).
 *
 * @return array{throttling_activo:bool, cap_global_sesion:int, skip_minutos:int, submit_horas:int, cookie_dias:int}
 */
function obtenerConfigFormularios(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    // Defaults = valores históricos hardcodeados en lead-capture.js.
    // Si la tabla/fila no existe (migración no corrida), el sitio sigue
    // funcionando con throttling DESACTIVADO — mismo criterio fail-safe
    // que el resto del sitio (no romper por config faltante).
    $cache = [
        'throttling_activo' => false,
        'cap_global_sesion' => 4,
        'skip_minutos'      => 10,
        'submit_horas'      => 24,
        'cookie_dias'       => 1,
    ];

    try {
        $row = $pdo->query("SELECT throttling_activo, cap_global_sesion, skip_minutos, submit_horas, cookie_dias
                            FROM formularios_config WHERE clave = 'lead_capture' LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cache = [
                'throttling_activo' => (bool) (int) $row['throttling_activo'],
                'cap_global_sesion' => max(1, (int) $row['cap_global_sesion']),
                'skip_minutos'      => max(0, (int) $row['skip_minutos']),
                'submit_horas'      => max(0, (int) $row['submit_horas']),
                'cookie_dias'       => max(1, (int) $row['cookie_dias']),
            ];
        }
    } catch (PDOException $e) { /* tabla puede no existir aún → defaults */ }

    return $cache;
}

/**
 * Punto de entrada único recomendado para llamadas IA (texto o visión).
 * Elige el proveedor/modelo configurado para $seccion en ia_config_secciones
 * y despacha a Claude u OpenRouter según corresponda.
 *
 * @param string      $seccion      Clave de ia_config_secciones (ej. 'horarios', 'vision_categoria', 'mensaje_whatsapp').
 * @param string      $prompt       Prompt de texto (rol "user").
 * @param string|null $imagenBase64 Imagen en base64, o null para solo texto.
 * @param string      $mediaType    Mime type de la imagen (ej. 'image/webp'), si aplica.
 * @return array ['ok'=>bool, 'texto'=>?string, 'error'=>?string, 'modelo'=>string, 'proveedor'=>string]
 */
function llamarLLM(PDO $pdo, string $seccion, string $prompt, ?string $imagenBase64 = null, string $mediaType = 'image/webp'): array
{
    $cfg = obtenerConfigIA($pdo, $seccion);

    if ($cfg['proveedor'] === 'openrouter') {
        return llamarOpenRouterApiInterno($prompt, $cfg['modelo'], $cfg['max_tokens'], $imagenBase64, $mediaType);
    }

    return llamarClaudeApiInterno($prompt, $cfg['modelo'], $cfg['max_tokens'], $imagenBase64, $mediaType);
}

/**
 * Genera una imagen con el modelo configurado para $seccion (tipo 'imagen'
 * en ia_config_secciones). Siempre vía OpenRouter (Claude no genera imágenes),
 * con la misma OPENROUTER_API_KEY que el resto de secciones.
 * Endpoint dedicado de OpenRouter: POST /api/v1/images → data[].b64_json.
 *
 * @param string $aspecto 16:9, 4:3, 1:1, 3:2…
 * @return array ['ok'=>bool, 'bin'=>?string, 'mime'=>?string, 'error'=>?string, 'modelo'=>string]
 */
function llamarLLMImagen(PDO $pdo, string $seccion, string $prompt, string $aspecto = '16:9'): array
{
    $cfg    = obtenerConfigIA($pdo, $seccion);
    $modelo = $cfg['modelo'];
    $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
    if (empty($apiKey)) {
        return ['ok' => false, 'bin' => null, 'mime' => null, 'error' => 'OPENROUTER_API_KEY no configurada', 'modelo' => $modelo];
    }

    $payload = [
        'model'        => $modelo,
        'prompt'       => $prompt,
        'aspect_ratio' => $aspecto,
        'resolution'   => '2K', // ≥1600px de ancho: la portada necesita ≥1200px para Discover
        'n'            => 1,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/images');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : 'https://crematoriosdemascotas.com'),
            'X-Title: Crematorios de Mascotas — Admin IA',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 150, // la generación tarda bastante más que el texto
    ]);

    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'bin' => null, 'mime' => null, 'error' => 'cURL: ' . $curlErr, 'modelo' => $modelo];
    }
    $data = json_decode((string) $resp, true);
    if ($code !== 200) {
        $msg = "HTTP $code";
        if (!empty($data['error']['message'])) $msg .= ' — ' . $data['error']['message'];
        return ['ok' => false, 'bin' => null, 'mime' => null, 'error' => $msg, 'modelo' => $modelo];
    }

    $b64 = $data['data'][0]['b64_json'] ?? null;
    $bin = $b64 ? base64_decode($b64, true) : false;
    if (!$bin) {
        return ['ok' => false, 'bin' => null, 'mime' => null, 'error' => 'El modelo no devolvió ninguna imagen', 'modelo' => $modelo];
    }
    return ['ok' => true, 'bin' => $bin, 'mime' => $data['data'][0]['media_type'] ?? 'image/png', 'error' => null, 'modelo' => $modelo];
}

/**
 * Extrae el primer bloque JSON válido de un texto (típicamente respuesta LLM).
 * Útil cuando el modelo a veces envuelve el JSON con prosa o code-fence.
 *
 * @return array|null El array decodificado o null si no se pudo parsear.
 */
function extraerJsonDeRespuesta(string $texto): ?array
{
    // Limpiar code fences si los hay
    $texto = preg_replace('/```(?:json)?\s*/m', '', $texto);
    $texto = preg_replace('/```\s*$/m', '', $texto);

    // Match del primer objeto JSON top-level (greedy hasta el último })
    if (preg_match('/\{[\s\S]*\}/', $texto, $m)) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}

/**
 * Metadata visual de un origen de imagen (badge).
 *
 * @param string $origen Valor de crematorio_imagenes.origen
 * @return array{icono:string, lbl:string, color:string, bg:string}
 */
function metaOrigenImagen(string $origen): array
{
    static $mapa = [
        'seed'           => ['icono' => '📦', 'lbl' => 'Semillado inicial',                'color' => '#1d4ed8', 'bg' => '#dbeafe'],
        'manual_admin'   => ['icono' => '✍️', 'lbl' => 'Subida manual del admin',          'color' => '#7c3aed', 'bg' => '#ede9fe'],
        'manual_negocio' => ['icono' => '🏢', 'lbl' => 'Carga del negocio al registrarse', 'color' => '#15803d', 'bg' => '#dcfce7'],
        'resena_cliente' => ['icono' => '⭐', 'lbl' => 'Foto de reseña de cliente',         'color' => '#b45309', 'bg' => '#fef3c7'],
        'desconocido'    => ['icono' => '❓', 'lbl' => 'Origen desconocido',               'color' => '#6b7280', 'bg' => '#f3f4f6'],
    ];
    return $mapa[$origen] ?? ['icono' => '❓', 'lbl' => 'Origen: ' . $origen, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
}

/**
 * Lista todos los orígenes posibles para selectores/filtros.
 * @return array<string,string> ['valor' => 'Etiqueta humana']
 */
function listarOrigenesImagen(): array
{
    return [
        'seed'           => '📦 Semillado inicial',
        'manual_admin'   => '✍️ Subida manual del admin',
        'manual_negocio' => '🏢 Carga del negocio al registrarse',
        'resena_cliente' => '⭐ Reseña de cliente',
        'desconocido'    => '❓ Desconocido',
    ];
}

/**
 * Etiqueta visual (texto + color) de una categoría de imagen, para el pill
 * de categoría de las cards admin. Fuente única compartida por imagenes-cola.php
 * y editar-ficha-negocio.php (vía includes/componentes/img-card-admin.php).
 *
 * @param string|null $cat Valor de crematorio_imagenes.categoria
 * @return array{texto:string,color:string}|null  null si la categoría está vacía
 */
function etiquetaCategoria(?string $cat): ?array
{
    static $mapa = [
        'logo'                  => ['texto' => 'Logo',           'color' => '#6366f1'],
        'exterior'              => ['texto' => 'Exterior',        'color' => '#0ea5e9'],
        'interior_sala'         => ['texto' => 'Sala despedida',  'color' => '#c0705a'],
        'interior_recepcion'    => ['texto' => 'Recepción',       'color' => '#8b5cf6'],
        'interior_amenities'    => ['texto' => 'Amenities',       'color' => '#10b981'],
        'produccion_tecnologia' => ['texto' => 'Producción/Tech', 'color' => '#f59e0b'],
        'recuerdos_souvenires'  => ['texto' => 'Recuerdos',       'color' => '#ec4899'],
        'equipo_personas'       => ['texto' => 'Equipo',          'color' => '#64748b'],
        'fotos_clientes'        => ['texto' => 'Clientes',        'color' => '#16a34a'],
        'otro'                  => ['texto' => 'Otro',            'color' => '#94a3b8'],
    ];
    if ($cat === null || $cat === '') return null;
    return $mapa[$cat] ?? $mapa['otro'];
}

/**
 * Añade parámetros UTM (+ custom cmas_*) a un link saliente.
 *
 * Política comercial:
 *   utm_source   = crematoriosdemascotas.com
 *   utm_medium   = referral
 *   utm_campaign = directorio
 *
 * Salta automáticamente:
 *   - Schemes no http(s): tel:, mailto:, wa.me, whatsapp://, geo:, sms:
 *   - URLs internas (mismo host del request actual)
 *   - URLs vacías / inválidas
 *
 * Respeta UTMs que ya vinieran en la URL (no los pisa).
 *
 * @param string|null $url   URL original
 * @param array       $extra Params extra cmas_* (ej: ['cmas_negocio_id'=>123,'cmas_tier'=>'destacado'])
 * @return string URL con UTMs (o la original sin tocar si no aplica)
 */
function urlConUtm(?string $url, array $extra = []): string
{
    $url = trim((string)$url);
    if ($url === '') return '';

    // Schemes especiales — no aplican UTMs
    if (preg_match('#^(tel:|mailto:|sms:|geo:|whatsapp://|wa\.me/)#i', $url)) {
        return $url;
    }

    // Sólo aplicamos a http(s)
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $parts = @parse_url($url);
    if (!$parts || empty($parts['host'])) return $url;

    // No aplicar a URLs internas (mismo host)
    $hostActual = $_SERVER['HTTP_HOST'] ?? '';
    if ($hostActual && strcasecmp($parts['host'], $hostActual) === 0) {
        return $url;
    }

    parse_str($parts['query'] ?? '', $qs);

    // Respeta UTMs existentes — no pisa
    if (!isset($qs['utm_source']))   $qs['utm_source']   = 'crematoriosdemascotas.com';
    if (!isset($qs['utm_medium']))   $qs['utm_medium']   = 'referral';
    if (!isset($qs['utm_campaign'])) $qs['utm_campaign'] = 'directorio';

    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') continue;
        $qs[$k] = $v;
    }

    $parts['query'] = http_build_query($qs);

    $resultado  = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
    if (!empty($parts['port']))     $resultado .= ':' . $parts['port'];
    if (isset($parts['path']))      $resultado .= $parts['path'];
    if (!empty($parts['query']))    $resultado .= '?' . $parts['query'];
    if (isset($parts['fragment']))  $resultado .= '#' . $parts['fragment'];

    return $resultado;
}

// ═══════════════════════════════════════════════════════════
// TELÉFONOS DE ESPAÑA + CAMBIO DESDE LA CAMPAÑA DE VERIFICACIÓN
// ═══════════════════════════════════════════════════════════

/**
 * Normaliza un teléfono de España (fijo o móvil): 9 dígitos que empiezan por 6, 7, 8 o 9,
 * con o sin prefijo +34 / 0034. Devuelve «+34 XXX XX XX XX» (el formato de las fichas) o null si no vale.
 */
function telefonoEspanaNormalizar(string $raw): ?string
{
    $s = preg_replace('/[\s().\-]/', '', trim($raw));
    if (str_starts_with($s, '+34'))      $s = substr($s, 3);
    elseif (str_starts_with($s, '0034')) $s = substr($s, 4);
    elseif (strlen($s) === 11 && str_starts_with($s, '34')) $s = substr($s, 2);
    if (!preg_match('/^[6789]\d{8}$/', $s)) return null;
    return '+34 ' . substr($s, 0, 3) . ' ' . substr($s, 3, 2) . ' ' . substr($s, 5, 2) . ' ' . substr($s, 7, 2);
}

/** Teléfonos de una ficha como array; si no hay JSON, se arma desde las columnas planas. */
function fichaTelefonosLista(array $cr): array
{
    $arr = json_decode((string)($cr['telefonos_json'] ?? ''), true);
    if (is_array($arr) && $arr) return $arr;
    $out = [];
    if (!empty($cr['telefono']))          $out[] = ['id'=>'p1','origen'=>'manual','tipo'=>'principal','label'=>'Teléfono principal','numero'=>$cr['telefono'],'visible'=>true];
    if (!empty($cr['telefono_clientes'])) $out[] = ['id'=>'p2','origen'=>'manual','tipo'=>'clientes','label'=>'Atención a clientes','numero'=>$cr['telefono_clientes'],'visible'=>true];
    return $out;
}

/** El teléfono que la ficha pública muestra primero (el primer visible con número), o null si no hay. */
function fichaTelefonoMostrado(array $cr): ?string
{
    foreach (fichaTelefonosLista($cr) as $t) {
        if (!empty($t['visible']) && trim((string)($t['numero'] ?? '')) !== '') return trim((string)$t['numero']);
    }
    return null;
}

/**
 * El negocio cambia su teléfono de atención al público desde el enlace firmado.
 * - El nuevo pasa a ser el primero visible (el que muestra la ficha), con origen «negocio» y la fecha.
 * - El que se mostraba NO se borra: queda con tipo «anterior», oculto y marcado «reemplazado por el negocio».
 * - Sin teléfono público previo, el nuevo se añade como principal.
 * Escribe en telefonos_json (fuente de verdad) y sincroniza las columnas planas.
 *
 * @param string $nuevo teléfono ya normalizado con telefonoEspanaNormalizar()
 * @return array{cambiado:bool, anterior:?string}
 */
function telefonoReemplazarPorNegocio(PDO $pdo, int $crematorioId, string $nuevo): array
{
    $st = $pdo->prepare("SELECT telefono, telefono_clientes, telefonos_json FROM crematorios WHERE id = ?");
    $st->execute([$crematorioId]);
    $cr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cr) return ['cambiado' => false, 'anterior' => null];

    $lista    = fichaTelefonosLista($cr);
    $anterior = fichaTelefonoMostrado($cr);
    $digitos  = fn($n) => preg_replace('/\D/', '', (string)$n);
    if ($anterior !== null && $digitos($anterior) === $digitos($nuevo)) {
        return ['cambiado' => false, 'anterior' => $anterior];
    }

    $ahora   = date('Y-m-d H:i:s');
    $tipoNew = 'principal';
    $labelNew = 'Teléfono principal';
    foreach ($lista as $i => $t) {
        if (!empty($t['visible']) && trim((string)($t['numero'] ?? '')) !== '') {
            // Hereda el tipo del que reemplaza, para ocupar su sitio también en las columnas planas
            $tipoNew  = $t['tipo'] ?? 'principal';
            $labelNew = $t['label'] ?? $labelNew;
            $lista[$i]['tipo']            = 'anterior';
            $lista[$i]['label']           = 'Teléfono anterior';
            $lista[$i]['visible']         = false;
            $lista[$i]['reemplazado_por'] = 'negocio';
            $lista[$i]['reemplazado_en']  = $ahora;
            $lista[$i]['tipo_original']   = $t['tipo'] ?? 'principal';
            break;
        }
    }
    array_unshift($lista, [
        'id'          => 'n' . substr(bin2hex(random_bytes(4)), 0, 7),
        'origen'      => 'negocio',
        'tipo'        => $tipoNew,
        'label'       => $labelNew,
        'numero'      => $nuevo,
        'visible'     => true,
        'cargado_en'  => $ahora,
    ]);

    $pdo->prepare("UPDATE crematorios SET telefonos_json = ? WHERE id = ?")
        ->execute([json_encode($lista, JSON_UNESCAPED_UNICODE), $crematorioId]);
    sincronizarCamposFlat($pdo, $crematorioId);
    return ['cambiado' => true, 'anterior' => $anterior];
}
