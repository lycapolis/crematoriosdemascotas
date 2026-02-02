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

    // Filtro por provincia
    if (!empty($filtros['provincia_id'])) {
        $where[] = 'c.provincia_id = :provincia_id';
        $params[':provincia_id'] = $filtros['provincia_id'];
    }

    // Filtro por comunidad
    if (!empty($filtros['comunidad_id'])) {
        $where[] = 'p.comunidad_id = :comunidad_id';
        $params[':comunidad_id'] = $filtros['comunidad_id'];
    }

    // Filtro por valoración mínima
    if (!empty($filtros['valoracion_min'])) {
        $where[] = 'c.rating >= :valoracion_min';
        $params[':valoracion_min'] = $filtros['valoracion_min'];
    }

    // Filtro por búsqueda flexible (múltiples campos)
    if (!empty($filtros['busqueda'])) {
        $termino = '%' . $filtros['busqueda'] . '%';
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
                    OR p.nombre LIKE :busq_prov)';
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

    // Contar total
    $sqlCount = "SELECT COUNT(*) FROM crematorios c
                 LEFT JOIN provincias p ON c.provincia_id = p.id
                 WHERE $whereSQL";
    $stmt = $pdo->prepare($sqlCount);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Calcular paginación
    $paginas = ceil($total / $porPagina);
    $offset = ($pagina - 1) * $porPagina;

    // Obtener datos
    $sql = "SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE $whereSQL
            ORDER BY c.destacado DESC, c.rating DESC, c.nombre ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'datos' => $stmt->fetchAll(),
        'total' => $total,
        'paginas' => $paginas
    ];
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
            WHERE c.destacado = 1
            ORDER BY c.rating DESC
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
                    WHERE c.destacado = 0
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

    $sqlFallback .= " ORDER BY c.provincia_id, c.rating DESC";

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
    return array_merge($destacados, $fallback);
}

/**
 * Obtiene crematorios por ciudad
 *
 * @param string $ciudadSlug
 * @param string $provinciaSlug
 * @return array
 */
function obtenerCrematoriosCiudad($ciudadSlug, $provinciaSlug) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE LOWER(REPLACE(REPLACE(c.ciudad, ' ', '-'), ',', '')) = :ciudad_slug
              AND p.slug = :provincia_slug
            ORDER BY c.rating DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ciudad_slug' => $ciudadSlug,
        ':provincia_slug' => $provinciaSlug
    ]);

    return $stmt->fetchAll();
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

    $sql = "SELECT ca.*,
                   COUNT(DISTINCT p.id) AS total_provincias,
                   COUNT(DISTINCT c.id) AS total_crematorios
            FROM comunidades_autonomas ca
            LEFT JOIN provincias p ON p.comunidad_id = ca.id
            LEFT JOIN crematorios c ON c.provincia_id = p.id
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
            LEFT JOIN crematorios c ON c.provincia_id = p.id";

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

/**
 * Obtiene ciudades únicas de una provincia (desde crematorios)
 *
 * @param int $provinciaId
 * @return array
 */
function obtenerCiudadesProvincia($provinciaId) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT DISTINCT
                ciudad AS nombre,
                LOWER(REPLACE(REPLACE(ciudad, ' ', '-'), ',', '')) AS slug,
                COUNT(*) AS total_crematorios
            FROM crematorios
            WHERE provincia_id = :provincia_id
              AND ciudad IS NOT NULL
              AND ciudad != ''
            GROUP BY ciudad
            ORDER BY ciudad";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':provincia_id' => $provinciaId]);

    return $stmt->fetchAll();
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

    $sql = "SELECT id, nombre, comentario, calificacion, created_at
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
