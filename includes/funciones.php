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

    // Filtro por ciudad (texto libre — match case-insensitive)
    if (!empty($filtros['ciudad'])) {
        $where[] = 'LOWER(c.ciudad) = LOWER(:ciudad)';
        $params[':ciudad'] = $filtros['ciudad'];
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

    // Orden — soporta los valores expuestos en el dropdown público
    $ordenSql = "c.destacado DESC, c.rating DESC, c.nombre ASC"; // default "Mejor valorados"
    switch ($filtros['orden'] ?? '') {
        case 'nombre':       $ordenSql = "c.nombre ASC"; break;
        case 'calificacion': $ordenSql = "c.rating DESC, c.reviews_total DESC"; break;
        case 'recientes':    $ordenSql = "c.created_at DESC, c.id DESC"; break;
        case 'mas_resenas':  $ordenSql = "c.reviews_total DESC, c.rating DESC"; break;
    }

    $selectBase = "SELECT c.*,
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
        return ['datos' => $datos, 'total' => $total, 'paginas' => $paginas];
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

    return [
        'datos' => $datos,
        'total' => $total,
        'paginas' => $paginas,
    ];
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
    $resultado = array_merge($destacados, $fallback);
    enriquecerConFotoLocal($resultado);
    return $resultado;
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

    // Traemos TODOS los crematorios de la provincia y filtramos por slug en PHP.
    // Motivo: MySQL no normaliza acentos en LOWER(REPLACE(...)), entonces "Polinyà"
    // queda como "polinyà" en BD pero la URL viene como "polinya" (ASCII) — mismatch.
    // Filtrar en PHP con slugificar() resuelve el problema definitivamente.
    $sql = "SELECT c.*,
                   p.nombre AS provincia_nombre,
                   p.slug AS provincia_slug,
                   ca.nombre AS comunidad_nombre,
                   ca.slug AS comunidad_slug
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
            WHERE p.slug = :provincia_slug
              AND c.estado = 'activa'
              AND c.ciudad IS NOT NULL AND c.ciudad != ''
            ORDER BY c.rating DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':provincia_slug' => $provinciaSlug]);
    $todos = $stmt->fetchAll();

    $crematorios = array_values(array_filter($todos, function ($c) use ($ciudadSlug) {
        return slugificar($c['ciudad']) === slugificar($ciudadSlug);
    }));

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

/**
 * Obtiene TODAS las ciudades del directorio con al menos 1 crematorio.
 * Devuelve provincia_id + comunidad_id de cada una → permite cascade JS
 * (data-attrs en cada <option>) sin queries extra.
 *
 * @return array de filas {nombre, provincia_id, comunidad_id, total_crematorios}
 */
function obtenerCiudadesGlobal() {
    $pdo = obtenerConexion();
    if (!$pdo) return [];

    $sql = "SELECT
                c.ciudad AS nombre,
                c.provincia_id,
                p.comunidad_id,
                COUNT(*) AS total_crematorios
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.ciudad IS NOT NULL AND c.ciudad != ''
              AND c.estado = 'activa'
            GROUP BY c.ciudad, c.provincia_id, p.comunidad_id
            ORDER BY c.ciudad";

    $stmt = $pdo->query($sql);
    return $stmt ? $stmt->fetchAll() : [];
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
                COUNT(*) AS total_crematorios
            FROM crematorios
            WHERE provincia_id = :provincia_id
              AND estado = 'activa'
              AND ciudad IS NOT NULL
              AND ciudad != ''
            GROUP BY ciudad
            ORDER BY ciudad";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':provincia_id' => $provinciaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Slug calculado en PHP — normaliza acentos/ñ para que URLs sean ASCII puro
    // (evita mismatches utf-8 cuando navegador encodea %C3%A0 etc.)
    foreach ($rows as &$r) {
        $r['slug'] = slugificar($r['nombre']);
    }
    return $rows;
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

/**
 * Coordenadas + datos para mapa a nivel ciudad.
 * Filtra por slug normalizado (slugificar) para manejar acentos.
 */
function obtenerCoordenadasCiudad($ciudadSlug, $provinciaSlug) {
    $pdo = obtenerConexion();
    if (!$pdo) return [];
    $sql = "SELECT c.id, c.nombre, c.slug, c.latitud, c.longitud, c.rating, c.reviews_total,
                   c.ciudad, c.foto_principal, c.verificado, c.destacado, c.origen,
                   p.nombre AS provincia_nombre
            FROM crematorios c
            INNER JOIN provincias p ON c.provincia_id = p.id
            WHERE c.estado = 'activa'
              AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
              AND c.latitud != 0 AND c.longitud != 0
              AND p.slug = :prov
              AND c.ciudad IS NOT NULL AND c.ciudad != ''";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':prov' => $provinciaSlug]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Filtro por ciudad en PHP — slug normalizado (acentos, ñ)
    $slugCiu = slugificar($ciudadSlug);
    $rows = array_values(array_filter($rows, function ($r) use ($slugCiu) {
        return slugificar((string)$r['ciudad']) === $slugCiu;
    }));
    enriquecerConFotoLocal($rows);
    return $rows;
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
        "SELECT telefonos_json, emails_json, descripciones_json, metas_json
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
