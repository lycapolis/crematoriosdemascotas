<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BLOG — núcleo compartido (público + admin)
 * ═══════════════════════════════════════════════════════════
 *
 * - Consultas de artículos / taxonomías / módulos
 * - Render de bloques Editor.js → HTML seguro (con TOC + FAQ)
 * - Render de módulos (ficha destacada, CTA, banner, lead) + reglas automáticas
 * - Prioridad contextual para fichas: ficha → ciudad → provincia →
 *   comunidad → servicios → generales
 * - SEO: URLs, schema JSON-LD, fechas
 *
 * Naming agnóstico de rubro: "ficha"/"negocio", no "crematorio"
 * (salvo las tablas heredadas del directorio).
 *
 * Requiere: config.php + conexion_db.php + funciones.php cargados.
 * Tablas: admin/migrations/create_blog.sql
 */

// ═══════════════════════════════════════════════════════════
// CONFIG + HELPERS BÁSICOS
// ═══════════════════════════════════════════════════════════

const BLOG_POR_PAGINA = 9;
const BLOG_DIR_UPLOADS = 'uploads/blog';

/** Servicios de ficha que pueden usarse como contexto de un artículo. */
function blogServiciosDisponibles(): array
{
    return [
        'cremacion_individual' => 'Cremación individual',
        'cremacion_colectiva'  => 'Cremación colectiva',
        'recogida_domicilio'   => 'Recogida a domicilio',
        'entrega_domicilio'    => 'Entrega a domicilio',
        'atencion_24h'         => 'Atención 24/7',
        'sala_velatoria'       => 'Sala velatoria',
        'urna'                 => 'Urna incluida',
        'souvenires'           => 'Souvenirs',
        'carta'                => 'Carta de condolencias',
        'molde'                => 'Molde de huella',
    ];
}

/** Tipos de módulo con su etiqueta legible e icono Lucide. */
function blogTiposModulo(): array
{
    return [
        'ficha_destacada' => ['label' => 'Negocio destacado',   'icono' => 'badge-check',    'descripcion' => 'Tarjeta de un negocio (fijo o automático según la zona).'],
        'cta'             => ['label' => 'Llamada a la acción', 'icono' => 'megaphone',      'descripcion' => 'Caja con título, texto y botón.'],
        'banner'          => ['label' => 'Banner con imagen',   'icono' => 'image',          'descripcion' => 'Imagen con enlace (pauta, promociones).'],
        'lead'            => ['label' => 'Captura de leads',    'icono' => 'message-circle', 'descripcion' => 'Botón de WhatsApp con el formulario de contacto del sitio.'],
    ];
}

function blogAhora(): string
{
    return date('Y-m-d H:i:s');
}

/** Condición SQL de "artículo visible al público". Usa :ahora como parámetro. */
function blogSqlPublicado(string $alias = 'a'): string
{
    return "$alias.estado IN ('publicado','programado') AND $alias.publicado_at IS NOT NULL AND $alias.publicado_at <= :ahora";
}

function blogConfig(string $clave, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $pdo = obtenerConexion();
            if ($pdo) $cache = $pdo->query("SELECT clave, valor FROM blog_config")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) { /* tabla puede no existir todavía */ }
    }
    return array_key_exists($clave, $cache) ? $cache[$clave] : $default;
}

function blogConfigGuardar(string $clave, string $valor): void
{
    obtenerConexion()->prepare("INSERT INTO blog_config (clave, valor) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
        ->execute([':c' => $clave, ':v' => $valor]);
}

/** Mientras los artículos sean de ejemplo, todo /blog va con noindex. */
function blogNoindexGlobal(): bool
{
    return blogConfig('noindex_global', '1') === '1';
}

// ─── URLs ───────────────────────────────────────────────────

function blogUrlIndice(): string
{
    return BASE_URL . '/blog/';
}

function blogUrlArticulo(string $slug): string
{
    return BASE_URL . '/blog/' . rawurlencode($slug);
}

function blogUrlTermino(string $taxSlug, string $termSlug): string
{
    return BASE_URL . '/blog/' . rawurlencode($taxSlug) . '/' . rawurlencode($termSlug);
}

/** Convierte una ruta del sitio en URL absoluta (canonical, OG, schema). */
function blogUrlAbsoluta(string $url): string
{
    if (preg_match('#^https?://#i', $url)) return $url;
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $esquema . '://' . $host . '/' . ltrim($url, '/');
}

/** URL pública de un archivo subido (ruta relativa a la raíz del proyecto). */
function blogUrlArchivo(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta === '') return '';
    if (preg_match('#^https?://#i', $ruta)) return $ruta;
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/**
 * Normaliza un enlace escrito por el admin en un módulo/artículo:
 *   "/directorio.php" → BASE_URL . "/directorio.php"
 *   "https://…"       → tal cual (+ UTM si es externo)
 */
function blogNormalizarEnlace(string $url, bool $conUtm = true): string
{
    $url = trim($url);
    if ($url === '') return '';
    if ($url[0] === '/' && !str_starts_with($url, '//')) {
        $base = rtrim(BASE_URL, '/');
        if ($base !== '' && !preg_match('#^https?://#', $base) && str_starts_with($url, $base . '/')) return $url;
        return $base . $url;
    }
    if (preg_match('#^https?://#i', $url) && $conUtm) {
        return urlConUtm($url, ['utm_campaign' => 'blog']);
    }
    return $url;
}

function blogEsEnlaceExterno(string $url): bool
{
    if (!preg_match('#^https?://#i', $url)) return false;
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    $base = parse_url(BASE_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
    return $host !== '' && strcasecmp($host, $base) !== 0;
}

// ─── Fechas ─────────────────────────────────────────────────

function blogFecha(?string $fecha): string
{
    if (!$fecha) return '';
    $ts = strtotime($fecha);
    if (!$ts) return '';
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function blogFechaIso(?string $fecha): string
{
    $ts = $fecha ? strtotime($fecha) : false;
    return $ts ? date('c', $ts) : '';
}

// ═══════════════════════════════════════════════════════════
// TAXONOMÍAS
// ═══════════════════════════════════════════════════════════

function blogTaxonomias(): array
{
    return obtenerConexion()->query("SELECT * FROM blog_taxonomias ORDER BY orden, id")->fetchAll(PDO::FETCH_ASSOC);
}

function blogTaxonomiaPorSlug(string $slug): ?array
{
    $st = obtenerConexion()->prepare("SELECT * FROM blog_taxonomias WHERE slug = :s LIMIT 1");
    $st->execute([':s' => $slug]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Términos de una taxonomía, ordenados como árbol (padre → hijos) con
 * 'nivel' (0, 1, 2…) y 'total' de artículos publicados.
 */
function blogTerminos(int $taxonomiaId, bool $conTotales = false): array
{
    $pdo = obtenerConexion();
    if ($conTotales) {
        $st = $pdo->prepare("
            SELECT t.*, (
                SELECT COUNT(*) FROM blog_articulo_termino at
                JOIN blog_articulos a ON a.id = at.articulo_id
                WHERE at.termino_id = t.id AND " . blogSqlPublicado('a') . "
            ) AS total
            FROM blog_terminos t WHERE t.taxonomia_id = :tax ORDER BY t.orden, t.nombre");
        $st->execute([':tax' => $taxonomiaId, ':ahora' => blogAhora()]);
    } else {
        $st = $pdo->prepare("SELECT t.*, 0 AS total FROM blog_terminos t WHERE t.taxonomia_id = :tax ORDER BY t.orden, t.nombre");
        $st->execute([':tax' => $taxonomiaId]);
    }
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $hijos = [];
    foreach ($filas as $f) $hijos[(int) ($f['parent_id'] ?? 0)][] = $f;

    $salida = [];
    $recorrer = function (int $padre, int $nivel) use (&$recorrer, &$salida, $hijos) {
        foreach ($hijos[$padre] ?? [] as $f) {
            $f['nivel'] = $nivel;
            $salida[] = $f;
            if ($nivel < 5) $recorrer((int) $f['id'], $nivel + 1);
        }
    };
    $recorrer(0, 0);

    // Huérfanos (padre borrado): al final, nivel 0
    $ids = array_column($salida, 'id');
    foreach ($filas as $f) {
        if (!in_array($f['id'], $ids)) { $f['nivel'] = 0; $salida[] = $f; }
    }
    return $salida;
}

function blogTerminoPorSlug(int $taxonomiaId, string $slug): ?array
{
    $st = obtenerConexion()->prepare("SELECT * FROM blog_terminos WHERE taxonomia_id = :t AND slug = :s LIMIT 1");
    $st->execute([':t' => $taxonomiaId, ':s' => $slug]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Términos asignados a un artículo, agrupados por slug de taxonomía. */
function blogTerminosDeArticulo(int $articuloId): array
{
    $st = obtenerConexion()->prepare("
        SELECT t.*, tx.slug AS tax_slug, tx.nombre AS tax_nombre, tx.publica AS tax_publica
        FROM blog_articulo_termino at
        JOIN blog_terminos t ON t.id = at.termino_id
        JOIN blog_taxonomias tx ON tx.id = t.taxonomia_id
        WHERE at.articulo_id = :id
        ORDER BY tx.orden, t.nombre");
    $st->execute([':id' => $articuloId]);
    $agrupados = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) $agrupados[$t['tax_slug']][] = $t;
    return $agrupados;
}

/** Devuelve [id, ...ids de todos los descendientes] de un término. */
function blogTerminoConDescendientes(int $terminoId): array
{
    $pdo = obtenerConexion();
    $ids = [$terminoId];
    $pendientes = [$terminoId];
    for ($i = 0; $i < 6 && $pendientes; $i++) {
        $ph = implode(',', array_fill(0, count($pendientes), '?'));
        $st = $pdo->prepare("SELECT id FROM blog_terminos WHERE parent_id IN ($ph)");
        $st->execute($pendientes);
        $pendientes = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $ids = array_merge($ids, $pendientes);
    }
    return array_values(array_unique($ids));
}

// ═══════════════════════════════════════════════════════════
// ARTÍCULOS — CONSULTAS
// ═══════════════════════════════════════════════════════════

function blogSqlBaseArticulos(): string
{
    return "SELECT a.*,
                   cp.nombre AS categoria_nombre, cp.slug AS categoria_slug,
                   au.nombre AS autor_nombre, au.slug AS autor_slug, au.cargo AS autor_cargo,
                   au.foto AS autor_foto, au.bio AS autor_bio, au.credenciales AS autor_credenciales,
                   au.web AS autor_web,
                   rv.nombre AS revisor_nombre, rv.cargo AS revisor_cargo, rv.credenciales AS revisor_credenciales
            FROM blog_articulos a
            LEFT JOIN blog_terminos cp ON cp.id = a.categoria_principal_id
            LEFT JOIN blog_autores  au ON au.id = a.autor_id
            LEFT JOIN blog_autores  rv ON rv.id = a.revisor_id";
}

function blogArticuloPorSlug(string $slug, bool $soloPublicado = true): ?array
{
    $sql = blogSqlBaseArticulos() . " WHERE a.slug = :slug";
    $params = [':slug' => $slug];
    if ($soloPublicado) {
        $sql .= " AND " . blogSqlPublicado('a');
        $params[':ahora'] = blogAhora();
    }
    $st = obtenerConexion()->prepare($sql . " LIMIT 1");
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function blogArticuloPorId(int $id): ?array
{
    $st = obtenerConexion()->prepare(blogSqlBaseArticulos() . " WHERE a.id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Listado paginado de artículos publicados.
 * $opts: terminos (int[] — OR), excluir (int[]), pagina, por_pagina, destacados_primero
 * @return array{items: array, total: int}
 */
function blogListar(array $opts = []): array
{
    $pdo = obtenerConexion();
    $where  = [blogSqlPublicado('a')];
    $params = [':ahora' => blogAhora()];

    if (!empty($opts['terminos'])) {
        $ids = array_map('intval', $opts['terminos']);
        $ph = [];
        foreach ($ids as $i => $id) { $ph[] = ":t$i"; $params[":t$i"] = $id; }
        $where[] = "a.id IN (SELECT articulo_id FROM blog_articulo_termino WHERE termino_id IN (" . implode(',', $ph) . "))";
    }
    if (!empty($opts['excluir'])) {
        $ph = [];
        foreach (array_map('intval', $opts['excluir']) as $i => $id) { $ph[] = ":x$i"; $params[":x$i"] = $id; }
        $where[] = "a.id NOT IN (" . implode(',', $ph) . ")";
    }

    $whereSql = implode(' AND ', $where);
    $st = $pdo->prepare("SELECT COUNT(*) FROM blog_articulos a WHERE $whereSql");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $porPagina = max(1, (int) ($opts['por_pagina'] ?? BLOG_POR_PAGINA));
    $pagina    = max(1, (int) ($opts['pagina'] ?? 1));
    $offset    = ($pagina - 1) * $porPagina;
    $orden     = !empty($opts['destacados_primero']) ? 'a.destacado DESC, a.publicado_at DESC' : 'a.publicado_at DESC';

    $st = $pdo->prepare(blogSqlBaseArticulos() . " WHERE $whereSql ORDER BY $orden, a.id DESC LIMIT $porPagina OFFSET $offset");
    $st->execute($params);
    return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
}

/** Artículos para la home: destacados primero, después los más recientes. */
function blogArticulosHome(int $limite = 3): array
{
    return blogListar(['por_pagina' => $limite, 'destacados_primero' => true])['items'];
}

/** Relacionados de un artículo: comparten términos; completa con recientes. */
function blogRelacionadosDeArticulo(array $articulo, int $limite = 3): array
{
    $pdo = obtenerConexion();
    $st = $pdo->prepare("
        SELECT a2.id, COUNT(*) AS comunes
        FROM blog_articulo_termino t1
        JOIN blog_articulo_termino t2 ON t2.termino_id = t1.termino_id AND t2.articulo_id <> t1.articulo_id
        JOIN blog_articulos a2 ON a2.id = t2.articulo_id
        WHERE t1.articulo_id = :id AND " . blogSqlPublicado('a2') . "
        GROUP BY a2.id ORDER BY comunes DESC, MAX(a2.publicado_at) DESC LIMIT $limite");
    $st->execute([':id' => $articulo['id'], ':ahora' => blogAhora()]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $items = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(blogSqlBaseArticulos() . " WHERE a.id IN ($ph)");
        $st->execute($ids);
        $porId = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) $porId[$f['id']] = $f;
        foreach ($ids as $id) if (isset($porId[$id])) $items[] = $porId[$id];
    }
    if (count($items) < $limite) {
        $extra = blogListar(['por_pagina' => $limite - count($items), 'excluir' => array_merge([$articulo['id']], $ids)])['items'];
        $items = array_merge($items, $extra);
    }
    return $items;
}

/**
 * Artículos para mostrar en una ficha de negocio.
 * Prioridad: asignado a la ficha (6) → ciudad (5) → provincia (4) →
 * comunidad (3) → servicios que ofrece (2) → generales sin contexto (1).
 * Los artículos atados a OTRA zona/ficha se excluyen. Si no alcanza,
 * completa con los más recientes para que el bloque nunca quede vacío.
 */
function blogArticulosParaFicha(array $ficha, int $limite = 3): array
{
    $pdo = obtenerConexion();

    $fichaId     = (string) (int) ($ficha['id'] ?? 0);
    $provinciaId = (string) (int) ($ficha['provincia_id'] ?? 0);
    $comunidadId = (string) (int) ($ficha['comunidad_id'] ?? 0);
    if ($comunidadId === '0' && $provinciaId !== '0') {
        $st = $pdo->prepare("SELECT comunidad_id FROM provincias WHERE id = :p");
        $st->execute([':p' => $provinciaId]);
        $comunidadId = (string) (int) $st->fetchColumn();
    }
    $ciudadId = '0';
    if (!empty($ficha['ciudad']) && $provinciaId !== '0') {
        $st = $pdo->prepare("SELECT id FROM ciudades WHERE provincia_id = :p AND (nombre = :n OR slug = :s) LIMIT 1");
        $st->execute([':p' => $provinciaId, ':n' => $ficha['ciudad'], ':s' => slugificar($ficha['ciudad'])]);
        $ciudadId = (string) (int) $st->fetchColumn();
    }
    $servicios = [];
    foreach (array_keys(blogServiciosDisponibles()) as $col) {
        if (!empty($ficha[$col])) $servicios[] = $col;
    }

    $params = [
        ':ahora' => blogAhora(), ':fid' => $fichaId, ':cid' => $ciudadId,
        ':pid' => $provinciaId, ':caid' => $comunidadId,
    ];
    $servSql = '0';
    if ($servicios) {
        $ph = [];
        foreach ($servicios as $i => $s) { $ph[] = ":sv$i"; $params[":sv$i"] = $s; }
        $servSql = "ctx.tipo = 'servicio' AND ctx.valor IN (" . implode(',', $ph) . ")";
    }

    // Subconsulta: MariaDB no permite usar alias de agregados dentro de CASE en ORDER BY
    $sql = "
        SELECT x.id FROM (
            SELECT a.id, a.destacado, a.publicado_at,
                   COUNT(ctx.id) AS nctx,
                   MAX(CASE
                       WHEN ctx.tipo = 'ficha'     AND ctx.valor = :fid  THEN 6
                       WHEN ctx.tipo = 'ciudad'    AND ctx.valor = :cid  THEN 5
                       WHEN ctx.tipo = 'provincia' AND ctx.valor = :pid  THEN 4
                       WHEN ctx.tipo = 'comunidad' AND ctx.valor = :caid THEN 3
                       WHEN $servSql THEN 2
                       ELSE 0 END) AS prioridad
            FROM blog_articulos a
            LEFT JOIN blog_articulo_contexto ctx ON ctx.articulo_id = a.id
            WHERE " . blogSqlPublicado('a') . "
            GROUP BY a.id, a.destacado, a.publicado_at
        ) x
        WHERE x.prioridad > 0 OR x.nctx = 0
        ORDER BY (CASE WHEN x.nctx = 0 THEN 1 ELSE x.prioridad END) DESC, x.destacado DESC, x.publicado_at DESC
        LIMIT " . (int) $limite;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $items = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(blogSqlBaseArticulos() . " WHERE a.id IN ($ph)");
        $st->execute($ids);
        $porId = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) $porId[$f['id']] = $f;
        foreach ($ids as $id) if (isset($porId[$id])) $items[] = $porId[$id];
    }
    if (count($items) < $limite) {
        $items = array_merge($items, blogListar(['por_pagina' => $limite - count($items), 'excluir' => $ids])['items']);
    }
    return $items;
}

// ═══════════════════════════════════════════════════════════
// MÓDULOS
// ═══════════════════════════════════════════════════════════

function blogModulos(bool $soloActivos = false): array
{
    $sql = "SELECT * FROM blog_modulos" . ($soloActivos ? " WHERE activo = 1" : "") . " ORDER BY nombre";
    $filas = obtenerConexion()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as &$f) $f['config'] = json_decode($f['config_json'] ?? '', true) ?: [];
    return $filas;
}

function blogModuloPorId(int $id): ?array
{
    static $cache = [];
    if (array_key_exists($id, $cache)) return $cache[$id];
    $st = obtenerConexion()->prepare("SELECT * FROM blog_modulos WHERE id = :id");
    $st->execute([':id' => $id]);
    $m = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($m) $m['config'] = json_decode($m['config_json'] ?? '', true) ?: [];
    return $cache[$id] = $m;
}

/** Reglas automáticas que aplican a un artículo (según sus términos). */
function blogReglasParaArticulo(array $terminoIds): array
{
    $filas = obtenerConexion()->query("
        SELECT r.* FROM blog_modulo_reglas r
        JOIN blog_modulos m ON m.id = r.modulo_id AND m.activo = 1
        WHERE r.activo = 1 ORDER BY r.parrafo, r.id")->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_filter($filas, function ($r) use ($terminoIds) {
        return $r['ambito'] === 'todos' || in_array((int) $r['termino_id'], $terminoIds, true);
    }));
}

/**
 * Elige el negocio a mostrar en un módulo "ficha destacada".
 * Modo fijo → la ficha configurada. Modo automático → un destacado de la
 * zona del artículo (ficha/ciudad/provincia/comunidad del contexto) y, si no
 * hay, un destacado cualquiera.
 * TODO fase 2: priorizar anunciantes según las ciudades pagadas en su plan.
 */
function blogElegirFichaDestacada(array $config, array $contexto): ?array
{
    $pdo = obtenerConexion();
    $base = "SELECT c.*, p.nombre AS provincia_nombre, p.slug AS provincia_slug
             FROM crematorios c LEFT JOIN provincias p ON p.id = c.provincia_id
             WHERE c.estado = 'activa'";

    if (($config['modo'] ?? 'auto') === 'fija' && !empty($config['ficha_id'])) {
        $st = $pdo->prepare("$base AND c.id = :id");
        $st->execute([':id' => (int) $config['ficha_id']]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if ($fila) { $arr = [$fila]; enriquecerConFotoLocal($arr); return $arr[0]; }
        return null;
    }

    $porTipo = [];
    foreach ($contexto as $c) $porTipo[$c['tipo']][] = (int) $c['valor'];

    $intentos = [];
    if (!empty($porTipo['ficha']))     $intentos[] = ['c.id IN', $porTipo['ficha']];
    if (!empty($porTipo['ciudad'])) {
        $ph = implode(',', array_map('intval', $porTipo['ciudad']));
        $nombres = $pdo->query("SELECT nombre, provincia_id FROM ciudades WHERE id IN ($ph)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($nombres as $n) $intentos[] = ['ciudad', $n];
    }
    if (!empty($porTipo['provincia'])) $intentos[] = ['c.provincia_id IN', $porTipo['provincia']];
    if (!empty($porTipo['comunidad'])) $intentos[] = ['p.comunidad_id IN', $porTipo['comunidad']];
    $intentos[] = ['todos', []];

    foreach ($intentos as [$tipo, $valores]) {
        if ($tipo === 'ciudad') {
            $st = $pdo->prepare("$base AND c.destacado = 1 AND c.ciudad = :n AND c.provincia_id = :p ORDER BY RAND() LIMIT 1");
            $st->execute([':n' => $valores['nombre'], ':p' => $valores['provincia_id']]);
        } elseif ($tipo === 'todos') {
            $st = $pdo->query("$base AND c.destacado = 1 ORDER BY RAND() LIMIT 1");
        } else {
            $ids = implode(',', array_map('intval', $valores)) ?: '0';
            $st = $pdo->query("$base AND c.destacado = 1 AND $tipo ($ids) ORDER BY RAND() LIMIT 1");
        }
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if ($fila) { $arr = [$fila]; enriquecerConFotoLocal($arr); return $arr[0]; }
    }
    return null;
}

/** HTML de un módulo. $ctx: ['contexto' => filas de blog_articulo_contexto]. */
function blogRenderModulo(array $modulo, array $ctx = []): string
{
    if (empty($modulo['activo'])) return '';
    $c = $modulo['config'] ?? [];
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    switch ($modulo['tipo']) {
        case 'cta':
            $url    = blogNormalizarEnlace($c['boton_url'] ?? '');
            $ext    = blogEsEnlaceExterno($url);
            $estilo = in_array($c['estilo'] ?? '', ['suave', 'intenso'], true) ? $c['estilo'] : 'suave';
            $icono  = preg_replace('/[^a-z0-9-]/', '', $c['icono'] ?? 'search') ?: 'search';
            $h  = '<aside class="blog-modulo blog-modulo--cta blog-modulo--cta-' . $estilo . '">';
            $h .= '<span class="blog-modulo__icono"><i data-lucide="' . $icono . '" class="icono"></i></span>';
            $h .= '<div class="blog-modulo__cuerpo">';
            if (!empty($c['titulo'])) $h .= '<p class="blog-modulo__titulo">' . $e($c['titulo']) . '</p>';
            if (!empty($c['texto']))  $h .= '<p class="blog-modulo__texto">' . $e($c['texto']) . '</p>';
            $h .= '</div>';
            if ($url !== '' && !empty($c['boton_texto'])) {
                $h .= '<a class="boton ' . ($estilo === 'intenso' ? 'dos' : 'uno') . ' blog-modulo__boton" href="' . $e($url) . '"'
                    . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $e($c['boton_texto'])
                    . ' <i data-lucide="arrow-right" class="icono"></i></a>';
            }
            return $h . '</aside>';

        case 'banner':
            $img = blogUrlArchivo($c['imagen'] ?? '');
            if ($img === '') return '';
            $imgMob = blogUrlArchivo($c['imagen_mobile'] ?? '');
            $url = blogNormalizarEnlace($c['url'] ?? '');
            $ext = blogEsEnlaceExterno($url);
            $etiqueta = trim($c['etiqueta'] ?? 'Publicidad');
            $pic  = '<picture>';
            if ($imgMob !== '') $pic .= '<source media="(max-width: 600px)" srcset="' . $e($imgMob) . '">';
            $pic .= '<img src="' . $e($img) . '" alt="' . $e($c['alt'] ?? '') . '" loading="lazy" decoding="async"></picture>';
            $h = '<aside class="blog-modulo blog-modulo--banner">';
            if ($etiqueta !== '') $h .= '<span class="blog-modulo__etiqueta">' . $e($etiqueta) . '</span>';
            $h .= $url !== ''
                ? '<a href="' . $e($url) . '"' . ($ext ? ' target="_blank" rel="sponsored noopener"' : '') . '>' . $pic . '</a>'
                : $pic;
            return $h . '</aside>';

        case 'lead':
            $wa = defined('WHATSAPP_SOPORTE_ES_B2C') ? WHATSAPP_SOPORTE_ES_B2C : '';
            if ($wa === '') return '';
            $msg = trim($c['mensaje_wa'] ?? '') ?: 'Hola, me gustaría recibir ayuda para elegir un crematorio para mi mascota.';
            $dest = 'https://wa.me/' . $wa . '?text=' . rawurlencode($msg);
            $h  = '<aside class="blog-modulo blog-modulo--lead">';
            $h .= '<div class="blog-modulo__cuerpo">';
            $h .= '<p class="blog-modulo__titulo">' . $e($c['titulo'] ?? '¿Necesitas ayuda ahora?') . '</p>';
            if (!empty($c['texto'])) $h .= '<p class="blog-modulo__texto">' . $e($c['texto']) . '</p>';
            $h .= '</div>';
            $h .= '<a class="boton uno blog-modulo__boton blog-modulo__boton--wa" href="' . $e($dest) . '" target="_blank" rel="noopener"'
                . ' data-lead-capture="wa" data-destino="' . $e($dest) . '" data-no-skip="1" data-phone-agent="' . $e($wa) . '">'
                . '<i data-lucide="message-circle" class="icono"></i> ' . $e($c['boton_texto'] ?? 'Escríbenos por WhatsApp') . '</a>';
            return $h . '</aside>';

        case 'ficha_destacada':
            $f = blogElegirFichaDestacada($c, $ctx['contexto'] ?? []);
            if (!$f) return '';
            $url  = generarUrl('crematorio', $f['slug']);
            $foto = $f['foto_local'] ?? $f['foto_principal'] ?? '';
            $ubic = trim(($f['ciudad'] ?? '') . (!empty($f['provincia_nombre']) ? ', ' . $f['provincia_nombre'] : ''), ', ');
            $rating = (float) ($f['rating'] ?? 0);
            $h  = '<aside class="blog-modulo blog-modulo--ficha">';
            $h .= '<span class="blog-modulo__etiqueta">' . $e($c['etiqueta'] ?? 'Destacado') . '</span>';
            $h .= '<a class="blog-modulo__ficha" href="' . $e($url) . '">';
            $h .= '<span class="blog-modulo__ficha-foto">' . ($foto
                    ? '<img src="' . $e(normalizarRutaImagen($foto)) . '" alt="' . $e($f['nombre']) . '" loading="lazy" decoding="async">'
                    : '<i data-lucide="paw-print" class="icono"></i>') . '</span>';
            $h .= '<span class="blog-modulo__ficha-datos">';
            if (!empty($c['titulo'])) $h .= '<span class="blog-modulo__kicker">' . $e($c['titulo']) . '</span>';
            $h .= '<span class="blog-modulo__ficha-nombre">' . $e($f['nombre']) . '</span>';
            if ($ubic !== '') $h .= '<span class="blog-modulo__ficha-ubic"><i data-lucide="map-pin" class="icono"></i>' . $e($ubic) . '</span>';
            if ($rating > 0) {
                $h .= '<span class="blog-modulo__ficha-rating"><i data-lucide="star" class="icono"></i>' . number_format($rating, 1, ',', '')
                    . (!empty($f['reviews_total']) ? ' <span>(' . (int) $f['reviews_total'] . ' reseñas)</span>' : '') . '</span>';
            }
            $h .= '</span>';
            $h .= '<span class="boton uno pequeno blog-modulo__ficha-cta">Ver ficha</span>';
            $h .= '</a></aside>';
            return $h;
    }
    return '';
}

// ═══════════════════════════════════════════════════════════
// RENDER DE CONTENIDO (Editor.js → HTML)
// ═══════════════════════════════════════════════════════════

/**
 * Sanea HTML inline de Editor.js. Permite: b/strong, i/em, u, a[href], mark,
 * code, br, sub, sup. Todo lo demás se "desenvuelve" (queda el texto).
 * Enlaces externos → target _blank + rel noopener + UTM.
 */
function blogSanitizarInline(?string $html): string
{
    $html = (string) $html;
    if ($html === '' ) return '';
    if (strpos($html, '<') === false) return str_replace('&nbsp;', ' ', $html);

    $permitidas = ['b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u',
                   'a' => 'a', 'mark' => 'mark', 'code' => 'code', 'br' => 'br', 'sub' => 'sub', 'sup' => 'sup'];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $raiz = $doc->getElementById('__r');
    if (!$raiz) return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');

    $salida = '';
    $recorrer = function (DOMNode $nodo) use (&$recorrer, $permitidas): string {
        $out = '';
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof DOMText) {
                $out .= htmlspecialchars($hijo->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!($hijo instanceof DOMElement)) continue;
            $tag = strtolower($hijo->tagName);
            if (!isset($permitidas[$tag])) { $out .= $recorrer($hijo); continue; }
            $t = $permitidas[$tag];
            if ($t === 'br') { $out .= '<br>'; continue; }
            if ($t === 'a') {
                $href = trim($hijo->getAttribute('href'));
                if (!preg_match('#^(https?://|/|mailto:|tel:|\#)#i', $href)) { $out .= $recorrer($hijo); continue; }
                $ext  = blogEsEnlaceExterno($href);
                $href = blogNormalizarEnlace($href, true);
                $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>'
                      . $recorrer($hijo) . '</a>';
                continue;
            }
            $out .= "<$t>" . $recorrer($hijo) . "</$t>";
        }
        return $out;
    };
    $salida = $recorrer($raiz);
    return str_replace(["\u{00A0}"], ' ', $salida);
}

function blogTextoPlano(?string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', (string) $html)), ENT_QUOTES, 'UTF-8')));
}

/** Decodifica el JSON de Editor.js a lista de bloques. */
function blogBloques(?string $json): array
{
    $data = json_decode((string) $json, true);
    return is_array($data['blocks'] ?? null) ? $data['blocks'] : [];
}

/** Cuenta palabras del texto visible del artículo. */
function blogContarPalabras(array $bloques): int
{
    $texto = '';
    foreach ($bloques as $b) {
        $d = $b['data'] ?? [];
        switch ($b['type'] ?? '') {
            case 'paragraph': case 'header': case 'aviso':
                $texto .= ' ' . blogTextoPlano($d['text'] ?? ''); break;
            case 'quote':
                $texto .= ' ' . blogTextoPlano($d['text'] ?? ''); break;
            case 'list':
                $texto .= ' ' . blogTextoPlano(implode(' ', blogListaTextos($d['items'] ?? []))); break;
            case 'faq':
                foreach ($d['items'] ?? [] as $it) $texto .= ' ' . blogTextoPlano(($it['pregunta'] ?? '') . ' ' . ($it['respuesta'] ?? ''));
                break;
        }
    }
    return count(preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY));
}

function blogListaTextos(array $items): array
{
    $out = [];
    foreach ($items as $it) {
        if (is_string($it)) { $out[] = $it; continue; }
        $out[] = $it['content'] ?? '';
        if (!empty($it['items'])) $out = array_merge($out, blogListaTextos($it['items']));
    }
    return $out;
}

function blogRenderLista(array $items, string $estilo): string
{
    $tag = $estilo === 'ordered' ? 'ol' : 'ul';
    $cls = $estilo === 'checklist' ? ' class="blog-lista-check"' : '';
    $h = "<$tag$cls>";
    foreach ($items as $it) {
        if (is_string($it)) { $h .= '<li>' . blogSanitizarInline($it) . '</li>'; continue; }
        $check = '';
        if ($estilo === 'checklist') {
            $check = !empty($it['meta']['checked']) ? '<span class="blog-check blog-check--on" aria-hidden="true"></span>' : '<span class="blog-check" aria-hidden="true"></span>';
        }
        $h .= '<li>' . $check . blogSanitizarInline($it['content'] ?? '');
        if (!empty($it['items'])) $h .= blogRenderLista($it['items'], $estilo);
        $h .= '</li>';
    }
    return $h . "</$tag>";
}

/** URL de embed segura (solo YouTube / Vimeo). */
function blogEmbedSeguro(array $d): ?string
{
    $url = (string) ($d['embed'] ?? '');
    $svc = (string) ($d['service'] ?? '');
    if ($svc === 'youtube' && preg_match('#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,})#', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if ($svc === 'vimeo' && preg_match('#player\.vimeo\.com/video/(\d+)#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/**
 * Renderiza el artículo completo.
 * @param array $articulo  Fila de blog_articulos (contenido_json).
 * @param array $opts      ['contexto' => filas, 'terminos' => ids, 'reglas' => bool]
 * @return array{html: string, toc: array, faq: array, imagenes: array}
 */
function blogRenderContenido(array $articulo, array $opts = []): array
{
    $bloques = blogBloques($articulo['contenido_json'] ?? '');

    // ── Inserción automática de módulos (reglas) ──
    if (($opts['reglas'] ?? true) && $bloques) {
        $manuales = [];
        foreach ($bloques as $b) if (($b['type'] ?? '') === 'modulo') $manuales[] = (int) ($b['data']['modulo_id'] ?? 0);
        $reglas = blogReglasParaArticulo($opts['terminos'] ?? []);
        if ($reglas) {
            $porParrafo = [];
            foreach ($reglas as $r) {
                if (in_array((int) $r['modulo_id'], $manuales, true)) continue;
                $manuales[] = (int) $r['modulo_id'];
                $porParrafo[(int) $r['parrafo']][] = (int) $r['modulo_id'];
            }
            // Si justo después del párrafo N hay otro módulo (insertado a mano),
            // la inserción se corre al siguiente párrafo para no apilar módulos.
            $nuevos = [];
            $nParrafo = 0;
            $pendientes = [];
            $total = count($bloques);
            foreach ($bloques as $i => $b) {
                $nuevos[] = $b;
                if (($b['type'] ?? '') !== 'paragraph') continue;
                $nParrafo++;
                $pendientes = array_merge($pendientes, $porParrafo[$nParrafo] ?? []);
                $siguiente = $i + 1 < $total ? ($bloques[$i + 1]['type'] ?? '') : '';
                if ($pendientes && $siguiente !== 'modulo') {
                    foreach ($pendientes as $mid) $nuevos[] = ['type' => 'modulo', 'data' => ['modulo_id' => $mid, 'auto' => true]];
                    $pendientes = [];
                }
            }
            $bloques = $nuevos;
        }
    }

    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $html = '';
    $toc = [];
    $faq = [];
    $imagenes = [];
    $idsUsados = [];

    foreach ($bloques as $b) {
        $d = $b['data'] ?? [];
        switch ($b['type'] ?? '') {
            case 'paragraph':
                $txt = blogSanitizarInline($d['text'] ?? '');
                if (trim(strip_tags($txt, '<br>')) === '' || trim($txt) === '<br>') break;
                $html .= "<p>$txt</p>\n";
                break;

            case 'header':
                $nivel = max(2, min(4, (int) ($d['level'] ?? 2)));
                $txt = blogSanitizarInline($d['text'] ?? '');
                $plano = blogTextoPlano($txt);
                if ($plano === '') break;
                $id = slugificar($plano) ?: 'seccion';
                $base = $id; $n = 2;
                while (isset($idsUsados[$id])) $id = $base . '-' . $n++;
                $idsUsados[$id] = true;
                if ($nivel <= 3) $toc[] = ['id' => $id, 'texto' => $plano, 'nivel' => $nivel];
                $html .= "<h$nivel id=\"$id\">$txt</h$nivel>\n";
                break;

            case 'list':
                if (empty($d['items'])) break;
                $html .= blogRenderLista($d['items'], $d['style'] ?? 'unordered') . "\n";
                break;

            case 'image':
                $ruta = $d['file']['ruta'] ?? '';
                $src  = $ruta !== '' ? blogUrlArchivo($ruta) : ($d['file']['url'] ?? '');
                if ($src === '') break;
                $media = !empty($d['file']['media']) ? blogUrlArchivo($d['file']['media']) : '';
                $ancho = (int) ($d['file']['ancho'] ?? 0);
                $alto  = (int) ($d['file']['alto'] ?? 0);
                $caption = blogSanitizarInline($d['caption'] ?? '');
                $alt = trim($d['alt'] ?? '') ?: blogTextoPlano($caption);
                $cls = 'blog-figura' . (!empty($d['stretched']) ? ' blog-figura--ancha' : '')
                     . (!empty($d['withBorder']) ? ' blog-figura--borde' : '')
                     . (!empty($d['withBackground']) ? ' blog-figura--fondo' : '');
                $srcset = $media ? ' srcset="' . $e($media) . ' 800w, ' . $e($src) . ' ' . ($ancho ?: 1600) . 'w" sizes="(max-width: 800px) 100vw, 760px"' : '';
                $dims = ($ancho && $alto) ? ' width="' . $ancho . '" height="' . $alto . '"' : '';
                $html .= '<figure class="' . $cls . '"><img src="' . $e($src) . '"' . $srcset . $dims . ' alt="' . $e($alt) . '" loading="lazy" decoding="async">';
                if ($caption !== '') $html .= '<figcaption>' . $caption . '</figcaption>';
                $html .= "</figure>\n";
                $imagenes[] = blogUrlAbsoluta($src);
                break;

            case 'quote':
                $txt = blogSanitizarInline($d['text'] ?? '');
                if (trim(strip_tags($txt)) === '') break;
                $html .= '<blockquote class="blog-cita"><p>' . $txt . '</p>';
                if (!empty($d['caption'])) $html .= '<cite>' . blogSanitizarInline($d['caption']) . '</cite>';
                $html .= "</blockquote>\n";
                break;

            case 'delimiter':
                $html .= '<hr class="blog-separador">' . "\n";
                break;

            case 'table':
                $filas = $d['content'] ?? [];
                if (!$filas) break;
                $html .= '<div class="blog-tabla"><table>';
                foreach ($filas as $i => $fila) {
                    $esCab = !empty($d['withHeadings']) && $i === 0;
                    $html .= '<tr>';
                    foreach ($fila as $celda) {
                        $t = $esCab ? 'th' : 'td';
                        $html .= "<$t>" . blogSanitizarInline($celda) . "</$t>";
                    }
                    $html .= '</tr>';
                }
                $html .= "</table></div>\n";
                break;

            case 'embed':
                $src = blogEmbedSeguro($d);
                if (!$src) break;
                $html .= '<figure class="blog-video"><div class="blog-video__marco"><iframe src="' . $e($src) . '" title="' . $e(blogTextoPlano($d['caption'] ?? 'Vídeo')) . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
                if (!empty($d['caption'])) $html .= '<figcaption>' . blogSanitizarInline($d['caption']) . '</figcaption>';
                $html .= "</figure>\n";
                break;

            case 'aviso':
                $txt = blogSanitizarInline($d['text'] ?? '');
                if (trim(strip_tags($txt)) === '') break;
                $tipo = in_array($d['tipo'] ?? '', ['consejo', 'importante'], true) ? $d['tipo'] : 'consejo';
                $icono = $tipo === 'importante' ? 'alert-circle' : 'lightbulb';
                $titulo = trim(blogTextoPlano($d['titulo'] ?? '')) ?: ($tipo === 'importante' ? 'Importante' : 'Consejo');
                $html .= '<div class="blog-aviso blog-aviso--' . $tipo . '"><p class="blog-aviso__titulo"><i data-lucide="' . $icono . '" class="icono"></i>' . $e($titulo) . '</p><p>' . $txt . "</p></div>\n";
                break;

            case 'faq':
                $items = array_values(array_filter($d['items'] ?? [], fn($it) => blogTextoPlano($it['pregunta'] ?? '') !== '' && blogTextoPlano($it['respuesta'] ?? '') !== ''));
                if (!$items) break;
                $tituloFaq = blogTextoPlano($d['titulo'] ?? '') ?: 'Preguntas frecuentes';
                $id = slugificar($tituloFaq) ?: 'preguntas-frecuentes';
                $base = $id; $n = 2;
                while (isset($idsUsados[$id])) $id = $base . '-' . $n++;
                $idsUsados[$id] = true;
                $toc[] = ['id' => $id, 'texto' => $tituloFaq, 'nivel' => 2];
                $html .= '<section class="blog-faq"><h2 id="' . $id . '">' . $e($tituloFaq) . '</h2>';
                foreach ($items as $it) {
                    $p = blogTextoPlano($it['pregunta']);
                    $r = blogSanitizarInline($it['respuesta']);
                    $html .= '<details class="blog-faq__item"><summary>' . $e($p) . '</summary><div class="blog-faq__respuesta"><p>' . $r . '</p></div></details>';
                    $faq[] = ['pregunta' => $p, 'respuesta' => blogTextoPlano($r)];
                }
                $html .= "</section>\n";
                break;

            case 'modulo':
                $m = blogModuloPorId((int) ($d['modulo_id'] ?? 0));
                if ($m) $html .= blogRenderModulo($m, $opts) . "\n";
                break;
        }
    }

    return ['html' => $html, 'toc' => $toc, 'faq' => $faq, 'imagenes' => $imagenes];
}

/**
 * Antes de cargar el JSON en el editor: reconstruye las URLs de imagen a
 * partir de la ruta relativa (el JSON sobrevive a cambios de BASE_URL entre
 * local y producción).
 */
function blogPrepararJsonEditor(?string $json): string
{
    $data = json_decode((string) $json, true);
    if (!is_array($data) || !is_array($data['blocks'] ?? null)) return json_encode(['blocks' => []]);
    foreach ($data['blocks'] as &$b) {
        if (($b['type'] ?? '') === 'image' && !empty($b['data']['file']['ruta'])) {
            $b['data']['file']['url'] = blogUrlArchivo($b['data']['file']['ruta']);
        }
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ═══════════════════════════════════════════════════════════
// SEO — SCHEMA JSON-LD
// ═══════════════════════════════════════════════════════════

function blogSchemaArticulo(array $a, array $render, array $migas, string $urlCanonica): array
{
    $imagen = $a['portada_ruta'] ? blogUrlAbsoluta(blogUrlArchivo($a['portada_ruta'])) : null;
    $autor = !empty($a['autor_nombre'])
        ? array_filter([
            '@type'       => 'Person',
            'name'        => $a['autor_nombre'],
            'jobTitle'    => $a['autor_cargo'] ?: null,
            'description' => $a['autor_bio'] ? mb_substr(blogTextoPlano($a['autor_bio']), 0, 300) : null,
            'url'         => $a['autor_web'] ?: null,
          ])
        : ['@type' => 'Organization', 'name' => SITIO_NOMBRE];

    $post = array_filter([
        '@type'            => 'BlogPosting',
        '@id'              => $urlCanonica . '#articulo',
        'headline'         => mb_substr($a['titulo'], 0, 110),
        'description'      => $a['meta_description'] ?: $a['extracto'],
        'image'            => $imagen ? [$imagen] : ($render['imagenes'] ?: null),
        'datePublished'    => blogFechaIso($a['publicado_at']),
        'dateModified'     => blogFechaIso($a['actualizado_contenido_at'] ?: $a['updated_at']),
        'author'           => $autor,
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => SITIO_NOMBRE,
            'logo'  => ['@type' => 'ImageObject', 'url' => blogUrlAbsoluta(BASE_URL . '/assets/img/favicon/web-app-manifest-512x512.png')],
        ],
        'mainEntityOfPage' => $urlCanonica,
        'articleSection'   => $a['categoria_nombre'] ?? null,
        'keywords'         => $a['keyword_principal'] ?: null,
        'wordCount'        => (int) $a['palabras'] ?: null,
        'inLanguage'       => 'es-ES',
    ]);
    if (!empty($a['revisor_nombre'])) {
        $post['reviewedBy'] = ['@type' => 'Person', 'name' => $a['revisor_nombre']];
    }

    $grafo = [$post];

    $items = [];
    foreach ($migas as $i => [$label, $url]) {
        $items[] = array_filter([
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $label,
            'item'     => $url ? blogUrlAbsoluta($url) : $urlCanonica,
        ]);
    }
    $grafo[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];

    if (!empty($render['faq'])) {
        $grafo[] = [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question',
                'name'  => $f['pregunta'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['respuesta']],
            ], $render['faq']),
        ];
    }

    return ['@context' => 'https://schema.org', '@graph' => $grafo];
}
