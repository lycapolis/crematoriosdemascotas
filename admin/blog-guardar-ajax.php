<?php
/**
 * Guardar / eliminar / duplicar artículos del blog (AJAX, JSON).
 *
 * accion=guardar  → crea o actualiza (id=0 crea). Devuelve id, slug, url, estado.
 * accion=eliminar → borra el artículo (los términos y el contexto caen en cascada).
 * accion=duplicar → copia como borrador.
 */

require_once __DIR__ . '/_blog-comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blogJson(['ok' => false, 'mensaje' => 'Método no permitido'], 405);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) blogJson(['ok' => false, 'mensaje' => 'Datos inválidos'], 400);
$_POST['csrf_token'] = $in['csrf_token'] ?? '';
blogValidarCsrf();

$pdo    = obtenerConexion();
$accion = $in['accion'] ?? 'guardar';
$id     = (int) ($in['id'] ?? 0);
$admin  = obtenerAdminActual();

// ─── Eliminar ────────────────────────────────────────────────
if ($accion === 'eliminar') {
    if (!$id) blogJson(['ok' => false, 'mensaje' => 'Falta el artículo'], 400);
    $pdo->prepare("DELETE FROM blog_articulos WHERE id = :id")->execute([':id' => $id]);
    blogJson(['ok' => true, 'mensaje' => 'Artículo eliminado']);
}

// ─── Duplicar ────────────────────────────────────────────────
if ($accion === 'duplicar') {
    $orig = blogArticuloPorId($id);
    if (!$orig) blogJson(['ok' => false, 'mensaje' => 'No existe el artículo'], 404);
    $pdo->beginTransaction();
    $slug = blogSlugUnico('blog_articulos', $orig['slug'] . '-copia');
    $pdo->prepare("INSERT INTO blog_articulos
        (titulo, slug, extracto, contenido_json, portada_ruta, portada_alt, autor_id, revisor_id, categoria_principal_id,
         estado, publicado_at, destacado, mostrar_indice, meta_title, meta_description, keyword_principal, noindex, palabras, lectura_min, created_by)
        SELECT CONCAT(titulo, ' (copia)'), :slug, extracto, contenido_json, portada_ruta, portada_alt, autor_id, revisor_id, categoria_principal_id,
               'borrador', NULL, 0, mostrar_indice, meta_title, meta_description, keyword_principal, noindex, palabras, lectura_min, :by
        FROM blog_articulos WHERE id = :id")
        ->execute([':slug' => $slug, ':by' => $admin['id'] ?? null, ':id' => $id]);
    $nuevo = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO blog_articulo_termino (articulo_id, termino_id) SELECT :n, termino_id FROM blog_articulo_termino WHERE articulo_id = :id")
        ->execute([':n' => $nuevo, ':id' => $id]);
    $pdo->prepare("INSERT INTO blog_articulo_contexto (articulo_id, tipo, valor) SELECT :n, tipo, valor FROM blog_articulo_contexto WHERE articulo_id = :id")
        ->execute([':n' => $nuevo, ':id' => $id]);
    $pdo->commit();
    blogJson(['ok' => true, 'id' => $nuevo, 'mensaje' => 'Artículo duplicado como borrador']);
}

// ─── Guardar ─────────────────────────────────────────────────
$titulo = trim((string) ($in['titulo'] ?? ''));
if ($titulo === '') blogJson(['ok' => false, 'mensaje' => 'El artículo necesita un título.', 'campo' => 'titulo'], 422);
$titulo = mb_substr($titulo, 0, 255);

$anterior = $id ? blogArticuloPorId($id) : null;
if ($id && !$anterior) blogJson(['ok' => false, 'mensaje' => 'El artículo ya no existe.'], 404);

// Contenido (JSON de Editor.js). El render público lo sanea; acá solo validamos forma.
$contenido = $in['contenido'] ?? ['blocks' => []];
if (!is_array($contenido) || !is_array($contenido['blocks'] ?? null)) $contenido = ['blocks' => []];
$bloquesOk = [];
foreach ($contenido['blocks'] as $b) {
    if (!is_array($b) || empty($b['type'])) continue;
    $bloquesOk[] = ['id' => (string) ($b['id'] ?? ''), 'type' => (string) $b['type'], 'data' => is_array($b['data'] ?? null) ? $b['data'] : []];
}
$contenidoJson = json_encode(['time' => (int) ($contenido['time'] ?? 0), 'blocks' => $bloquesOk, 'version' => (string) ($contenido['version'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (strlen($contenidoJson) > 4 * 1024 * 1024) blogJson(['ok' => false, 'mensaje' => 'El contenido es demasiado largo.'], 422);

$palabras = blogContarPalabras($bloquesOk);
$lectura  = max(1, (int) ceil($palabras / 200));

// Slug: el que escribió el admin o derivado del título
$slugPedido = trim((string) ($in['slug'] ?? '')) ?: $titulo;
$slug = blogSlugUnico('blog_articulos', $slugPedido, $id);

// Estado + fecha de publicación (hora de PHP, ver nota de timezone en blog.php)
$estado = in_array($in['estado'] ?? '', ['borrador', 'programado', 'publicado'], true) ? $in['estado'] : 'borrador';
$fecha  = null;
if (!empty($in['publicado_at'])) {
    $ts = strtotime(str_replace('T', ' ', (string) $in['publicado_at']));
    if ($ts) $fecha = date('Y-m-d H:i:s', $ts);
}
$ahora = blogAhora();
if ($estado === 'publicado') {
    if (!$fecha) $fecha = $ahora;
    if ($fecha > $ahora) $estado = 'programado';
} elseif ($estado === 'programado') {
    if (!$fecha) blogJson(['ok' => false, 'mensaje' => 'Elegí la fecha y hora en que debe publicarse.', 'campo' => 'publicado_at'], 422);
    if ($fecha <= $ahora) $estado = 'publicado';
}

// Validaciones mínimas para publicar
$portadaRuta = trim((string) ($in['portada_ruta'] ?? '')) ?: null;
$portadaAlt  = trim((string) ($in['portada_alt'] ?? '')) ?: null;
if ($estado !== 'borrador') {
    if ($palabras < 30) blogJson(['ok' => false, 'mensaje' => 'El artículo tiene muy poco texto para publicarse.'], 422);
    if ($portadaRuta && !$portadaAlt) blogJson(['ok' => false, 'mensaje' => 'Falta el texto alternativo (alt) de la imagen de portada.', 'campo' => 'portada_alt'], 422);
}

// "Actualizado el…" solo cuando cambia el contenido de algo ya publicado
$actualizadoContenido = $anterior['actualizado_contenido_at'] ?? null;
if ($anterior && $anterior['estado'] === 'publicado' && $anterior['publicado_at'] && $anterior['publicado_at'] <= $ahora
    && md5((string) $anterior['contenido_json']) !== md5($contenidoJson)) {
    $actualizadoContenido = $ahora;
}

$idONull = fn($v) => ((int) $v) > 0 ? (int) $v : null;
$texto   = fn($k, $max) => ($v = trim((string) ($in[$k] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;

$datos = [
    ':titulo'   => $titulo,
    ':slug'     => $slug,
    ':extracto' => $texto('extracto', 600),
    ':contenido'=> $contenidoJson,
    ':portada'  => $portadaRuta,
    ':palt'     => $portadaAlt ? mb_substr($portadaAlt, 0, 255) : null,
    ':autor'    => $idONull($in['autor_id'] ?? 0),
    ':revisor'  => $idONull($in['revisor_id'] ?? 0),
    ':catp'     => $idONull($in['categoria_principal_id'] ?? 0),
    ':estado'   => $estado,
    ':pub'      => $fecha,
    ':actc'     => $actualizadoContenido,
    ':dest'     => !empty($in['destacado']) ? 1 : 0,
    ':toc'      => isset($in['mostrar_indice']) ? (!empty($in['mostrar_indice']) ? 1 : 0) : 1,
    ':mt'       => $texto('meta_title', 200),
    ':md'       => $texto('meta_description', 300),
    ':kw'       => $texto('keyword_principal', 150),
    ':canon'    => $texto('canonical_url', 500),
    ':noindex'  => !empty($in['noindex']) ? 1 : 0,
    ':palabras' => $palabras,
    ':lectura'  => $lectura,
];

try {
    $pdo->beginTransaction();

    if ($id) {
        $pdo->prepare("UPDATE blog_articulos SET
            titulo=:titulo, slug=:slug, extracto=:extracto, contenido_json=:contenido, portada_ruta=:portada, portada_alt=:palt,
            autor_id=:autor, revisor_id=:revisor, categoria_principal_id=:catp, estado=:estado, publicado_at=:pub,
            actualizado_contenido_at=:actc, destacado=:dest, mostrar_indice=:toc, meta_title=:mt, meta_description=:md,
            keyword_principal=:kw, canonical_url=:canon, noindex=:noindex, palabras=:palabras, lectura_min=:lectura
            WHERE id = :id")->execute($datos + [':id' => $id]);
    } else {
        $pdo->prepare("INSERT INTO blog_articulos
            (titulo, slug, extracto, contenido_json, portada_ruta, portada_alt, autor_id, revisor_id, categoria_principal_id,
             estado, publicado_at, actualizado_contenido_at, destacado, mostrar_indice, meta_title, meta_description,
             keyword_principal, canonical_url, noindex, palabras, lectura_min, created_by)
            VALUES (:titulo, :slug, :extracto, :contenido, :portada, :palt, :autor, :revisor, :catp,
             :estado, :pub, :actc, :dest, :toc, :mt, :md, :kw, :canon, :noindex, :palabras, :lectura, :by)")
            ->execute($datos + [':by' => $admin['id'] ?? null]);
        $id = (int) $pdo->lastInsertId();
    }

    // ── Términos (categorías, etiquetas, taxonomías libres) ──
    // Formato: { "<taxonomia_id>": [12, 15, "nuevo:Nombre del término"] }
    $taxValidas = array_column(blogTaxonomias(), null, 'id');
    $terminoIds = [];
    $creados = [];
    foreach (($in['terminos'] ?? []) as $taxId => $lista) {
        $taxId = (int) $taxId;
        if (!isset($taxValidas[$taxId]) || !is_array($lista)) continue;
        foreach ($lista as $valor) {
            if (is_numeric($valor)) { $terminoIds[] = (int) $valor; continue; }
            if (is_string($valor) && str_starts_with($valor, 'nuevo:')) {
                $nombre = mb_substr(trim(substr($valor, 6)), 0, 150);
                if ($nombre === '') continue;
                $slugT = slugificar($nombre) ?: 'termino';
                $existente = blogTerminoPorSlug($taxId, $slugT);
                if ($existente) { $terminoIds[] = (int) $existente['id']; continue; }
                $pdo->prepare("INSERT INTO blog_terminos (taxonomia_id, nombre, slug) VALUES (:t, :n, :s)")
                    ->execute([':t' => $taxId, ':n' => $nombre, ':s' => $slugT]);
                $nuevoId = (int) $pdo->lastInsertId();
                $terminoIds[] = $nuevoId;
                $creados[$valor] = $nuevoId;
            }
        }
    }
    // La categoría principal siempre queda asignada también como término
    if ($datos[':catp']) $terminoIds[] = $datos[':catp'];
    $terminoIds = array_values(array_unique(array_filter($terminoIds)));

    $pdo->prepare("DELETE FROM blog_articulo_termino WHERE articulo_id = :id")->execute([':id' => $id]);
    if ($terminoIds) {
        $st = $pdo->prepare("INSERT IGNORE INTO blog_articulo_termino (articulo_id, termino_id)
                             SELECT :a, id FROM blog_terminos WHERE id = :t");
        foreach ($terminoIds as $t) $st->execute([':a' => $id, ':t' => $t]);
    }

    // ── Contexto (dónde se muestra como relacionado) ──
    $pdo->prepare("DELETE FROM blog_articulo_contexto WHERE articulo_id = :id")->execute([':id' => $id]);
    $servicios = array_keys(blogServiciosDisponibles());
    $st = $pdo->prepare("INSERT IGNORE INTO blog_articulo_contexto (articulo_id, tipo, valor) VALUES (:a, :t, :v)");
    foreach (($in['contexto'] ?? []) as $tipo => $valores) {
        if (!in_array($tipo, ['ficha', 'ciudad', 'provincia', 'comunidad', 'servicio'], true) || !is_array($valores)) continue;
        foreach ($valores as $v) {
            $v = $tipo === 'servicio' ? (string) $v : (string) (int) $v;
            if ($tipo === 'servicio' && !in_array($v, $servicios, true)) continue;
            if ($tipo !== 'servicio' && $v === '0') continue;
            $st->execute([':a' => $id, ':t' => $tipo, ':v' => $v]);
        }
    }

    // Imágenes subidas sin artículo todavía (artículo nuevo) → asociarlas
    $rutas = [];
    foreach ($bloquesOk as $b) if ($b['type'] === 'image' && !empty($b['data']['file']['ruta'])) $rutas[] = $b['data']['file']['ruta'];
    if ($portadaRuta) $rutas[] = $portadaRuta;
    if ($rutas) {
        $ph = implode(',', array_fill(0, count($rutas), '?'));
        $pdo->prepare("UPDATE blog_imagenes SET articulo_id = ? WHERE articulo_id IS NULL AND ruta IN ($ph)")
            ->execute(array_merge([$id], $rutas));
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('blog-guardar: ' . $e->getMessage());
    blogJson(['ok' => false, 'mensaje' => 'No se pudo guardar: ' . $e->getMessage()], 500);
}

$mensajes = [
    'borrador'   => 'Borrador guardado',
    'programado' => $fecha ? 'Programado para el ' . blogFecha($fecha) . ' a las ' . date('H:i', strtotime($fecha)) : 'Programado',
    'publicado'  => 'Artículo publicado',
];

blogJson([
    'ok'           => true,
    'id'           => $id,
    'slug'         => $slug,
    'url'          => blogUrlArticulo($slug),
    'estado'       => $estado,
    'publicado_at' => $fecha ? date('Y-m-d\TH:i', strtotime($fecha)) : '',
    'palabras'     => $palabras,
    'lectura_min'  => $lectura,
    'creados'      => $creados,
    'mensaje'      => $mensajes[$estado],
]);
