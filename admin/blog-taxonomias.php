<?php
/**
 * BLOG — categorías, etiquetas y taxonomías libres (tipo WordPress)
 *
 * También atiende el AJAX del editor: {accion: 'crear_termino', taxonomia_id, nombre}
 */

require_once __DIR__ . '/_blog-comun.php';

$pdo = obtenerConexion();

// ─── AJAX (editor): crear término rápido ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    blogValidarCsrf();
    if (($in['accion'] ?? '') !== 'crear_termino') blogJson(['ok' => false, 'mensaje' => 'Acción no válida'], 400);
    $taxId = (int) ($in['taxonomia_id'] ?? 0);
    $nombre = mb_substr(trim((string) ($in['nombre'] ?? '')), 0, 150);
    if (!$taxId || $nombre === '') blogJson(['ok' => false, 'mensaje' => 'Falta el nombre'], 422);
    $slug = blogSlugUnico('blog_terminos', $nombre, 0, ['taxonomia_id' => $taxId]);
    $pdo->prepare("INSERT INTO blog_terminos (taxonomia_id, nombre, slug) VALUES (:t, :n, :s)")->execute([':t' => $taxId, ':n' => $nombre, ':s' => $slug]);
    blogJson(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'nombre' => $nombre, 'slug' => $slug]);
}

$taxonomias = blogTaxonomias();
$taxActual = null;
foreach ($taxonomias as $t) if ((int) $t['id'] === (int) ($_GET['tax'] ?? 0)) $taxActual = $t;
if (!$taxActual) $taxActual = $taxonomias[0] ?? null;
$volver = fn(string $tipo, string $msg, array $extra = []) => header('Location: blog-taxonomias.php?' . http_build_query(array_merge(['tax' => $taxActual['id'] ?? ''], $extra, [$tipo => $msg]))) || exit;

// ─── Acciones de formulario ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) $volver('error', 'La sesión expiró, intentá de nuevo');
    $accion = $_POST['accion'] ?? '';
    try {
        switch ($accion) {
            case 'guardar_termino':
                $tid    = (int) ($_POST['id'] ?? 0);
                $taxId  = (int) $taxActual['id'];
                $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
                if ($nombre === '') $volver('error', 'El nombre es obligatorio');
                $slug   = blogSlugUnico('blog_terminos', trim($_POST['slug'] ?? '') ?: $nombre, $tid, ['taxonomia_id' => $taxId]);
                $parent = $taxActual['jerarquica'] ? ((int) ($_POST['parent_id'] ?? 0) ?: null) : null;
                if ($tid && $parent && in_array($parent, blogTerminoConDescendientes($tid), true)) $volver('error', 'Una categoría no puede ser hija de sí misma ni de sus subcategorías');
                $datos = [
                    ':n' => $nombre, ':s' => $slug, ':p' => $parent,
                    ':d' => trim($_POST['descripcion'] ?? '') ?: null,
                    ':mt' => mb_substr(trim($_POST['meta_title'] ?? ''), 0, 200) ?: null,
                    ':md' => mb_substr(trim($_POST['meta_description'] ?? ''), 0, 300) ?: null,
                    ':o' => (int) ($_POST['orden'] ?? 0),
                ];
                if ($tid) {
                    $pdo->prepare("UPDATE blog_terminos SET nombre=:n, slug=:s, parent_id=:p, descripcion=:d, meta_title=:mt, meta_description=:md, orden=:o WHERE id=:id AND taxonomia_id=:t")
                        ->execute($datos + [':id' => $tid, ':t' => $taxId]);
                    $volver('ok', 'Término actualizado');
                }
                $pdo->prepare("INSERT INTO blog_terminos (taxonomia_id, nombre, slug, parent_id, descripcion, meta_title, meta_description, orden) VALUES (:t, :n, :s, :p, :d, :mt, :md, :o)")
                    ->execute($datos + [':t' => $taxId]);
                $volver('ok', 'Término creado');

            case 'eliminar_termino':
                $tid = (int) ($_POST['id'] ?? 0);
                $st = $pdo->prepare("SELECT parent_id FROM blog_terminos WHERE id = :id");
                $st->execute([':id' => $tid]);
                $padre = $st->fetchColumn();
                // Los hijos suben un nivel; los artículos pierden solo este término
                $pdo->prepare("UPDATE blog_terminos SET parent_id = :p WHERE parent_id = :id")->execute([':p' => $padre ?: null, ':id' => $tid]);
                $pdo->prepare("DELETE FROM blog_terminos WHERE id = :id")->execute([':id' => $tid]);
                $volver('ok', 'Término eliminado');

            case 'guardar_taxonomia':
                $xid = (int) ($_POST['id'] ?? 0);
                $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
                if ($nombre === '') $volver('error', 'El nombre de la taxonomía es obligatorio');
                $singular = mb_substr(trim($_POST['nombre_singular'] ?? ''), 0, 100) ?: $nombre;
                $datos = [':n' => $nombre, ':ns' => $singular, ':d' => trim($_POST['descripcion'] ?? '') ?: null,
                          ':j' => !empty($_POST['jerarquica']) ? 1 : 0, ':p' => !empty($_POST['publica']) ? 1 : 0];
                if ($xid) {
                    $pdo->prepare("UPDATE blog_taxonomias SET nombre=:n, nombre_singular=:ns, descripcion=:d, jerarquica=IF(sistema=1, jerarquica, :j), publica=:p WHERE id=:id")
                        ->execute($datos + [':id' => $xid]);
                    $volver('ok', 'Taxonomía actualizada', ['tax' => $xid]);
                }
                $slug = blogSlugUnico('blog_taxonomias', $nombre);
                if (in_array($slug, ['pagina', 'page', 'feed'], true)) $slug .= '-tax';
                $pdo->prepare("INSERT INTO blog_taxonomias (nombre, nombre_singular, slug, descripcion, jerarquica, publica, sistema, orden) VALUES (:n, :ns, :s, :d, :j, :p, 0, 10)")
                    ->execute($datos + [':s' => $slug]);
                $nuevo = (int) $pdo->lastInsertId();
                header('Location: blog-taxonomias.php?tax=' . $nuevo . '&ok=' . urlencode('Taxonomía creada. Ahora añade sus términos.'));
                exit;

            case 'eliminar_taxonomia':
                $xid = (int) ($_POST['id'] ?? 0);
                $pdo->prepare("DELETE FROM blog_taxonomias WHERE id = :id AND sistema = 0")->execute([':id' => $xid]);
                header('Location: blog-taxonomias.php?ok=' . urlencode('Taxonomía eliminada'));
                exit;
        }
    } catch (PDOException $e) {
        $volver('error', 'No se pudo guardar: ' . $e->getMessage());
    }
}

$terminos = $taxActual ? blogTerminos((int) $taxActual['id'], true) : [];
$editar = null;
foreach ($terminos as $t) if ((int) $t['id'] === (int) ($_GET['editar'] ?? 0)) $editar = $t;
$csrf = generarTokenCSRF();

$titulo_pagina = 'Categorías y etiquetas — Blog — Admin';
$head_extra = '<link rel="stylesheet" href="' . assetUrl('assets/css/admin-blog.css') . '">';
include 'header.php';
?>

<div class="admin-page">
    <header class="admin-page-header">
        <h1 class="admin-page-title">Categorías y etiquetas</h1>
        <p class="admin-page-subtitle">Organiza los artículos. Cada categoría y etiqueta pública tiene su propia página en el blog.</p>
    </header>

    <?php blogSubnav('taxonomias'); ?>

    <?php if ($taxActual): ?>
    <nav class="admin-tabs" style="margin-bottom: var(--espacio-cuatro); display:flex; gap:.4rem; flex-wrap:wrap;">
        <?php foreach ($taxonomias as $t): ?>
        <a href="?tax=<?php echo $t['id']; ?>" class="boton <?php echo (int) $t['id'] === (int) $taxActual['id'] ? 'uno' : 'dos'; ?> pequeno"><?php echo limpiar($t['nombre']); ?></a>
        <?php endforeach; ?>
        <a href="#nueva-taxonomia" class="boton dos pequeno"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Nueva taxonomía</a>
    </nav>

    <div class="blog-admin-2col">
        <!-- Lista de términos -->
        <section class="blog-admin-card">
            <div class="blog-admin-card__cabecera">
                <h2 class="blog-admin-card__titulo"><i data-lucide="tags" class="icono"></i> <?php echo limpiar($taxActual['nombre']); ?> <span class="admin-pill"><?php echo count($terminos); ?></span></h2>
                <?php if ($taxActual['publica']): ?><span class="admin-pill admin-pill--info">URL: /blog/<?php echo limpiar($taxActual['slug']); ?>/…</span><?php else: ?><span class="admin-pill">Solo interna</span><?php endif; ?>
            </div>
            <?php if (!$terminos): ?>
            <div class="blog-admin-card__cuerpo" style="color:var(--admin-tinta-tenue);">Todavía no hay términos. Añade el primero con el formulario.</div>
            <?php else: ?>
            <div class="admin-table-wrap" style="border:0; box-shadow:none;">
                <table class="admin-table">
                    <thead><tr><th>Nombre</th><th>URL</th><th class="admin-table__num">Artículos</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($terminos as $t): ?>
                        <tr>
                            <td>
                                <div class="blog-admin-arbol__fila">
                                    <?php if ($t['nivel'] > 0): ?><span class="blog-admin-arbol__nivel"><?php echo str_repeat('&nbsp;&nbsp;', (int) $t['nivel'] - 1); ?>└</span><?php endif; ?>
                                    <strong><?php echo limpiar($t['nombre']); ?></strong>
                                </div>
                                <?php if ($t['descripcion']): ?><div class="blog-admin-lista__sub"><?php echo limpiar(mb_strimwidth($t['descripcion'], 0, 90, '…')); ?></div><?php endif; ?>
                            </td>
                            <td><code style="font-size:.78rem;"><?php echo limpiar($t['slug']); ?></code></td>
                            <td class="admin-table__num"><?php echo (int) $t['total']; ?></td>
                            <td>
                                <div class="blog-admin-acciones">
                                    <?php if ($taxActual['publica'] && $t['total'] > 0): ?>
                                    <a class="boton dos pequeno" href="<?php echo blogUrlTermino($taxActual['slug'], $t['slug']); ?>" target="_blank" title="Ver página"><i data-lucide="external-link" class="icono"></i></a>
                                    <?php endif; ?>
                                    <a class="boton dos pequeno" href="?tax=<?php echo $taxActual['id']; ?>&editar=<?php echo $t['id']; ?>#form-termino" title="Editar"><i data-lucide="pencil" class="icono"></i></a>
                                    <form method="post" onsubmit="return confirmarForm(this, { titulo: 'Eliminar término', mensaje: '¿Eliminar <strong><?php echo limpiar(addslashes($t['nombre'])); ?></strong>? Los artículos no se borran, solo pierden este término.', textoOK: 'Eliminar', peligroso: true })">
                                        <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                                        <input type="hidden" name="accion" value="eliminar_termino">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="boton dos pequeno" title="Eliminar" style="color:var(--admin-tone-error-fg);"><i data-lucide="trash-2" class="icono"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Formulario de término -->
        <section class="blog-admin-card" id="form-termino">
            <div class="blog-admin-card__cabecera">
                <h2 class="blog-admin-card__titulo"><i data-lucide="<?php echo $editar ? 'pencil' : 'plus'; ?>" class="icono"></i> <?php echo $editar ? 'Editar' : 'Añadir'; ?> <?php echo limpiar(mb_strtolower($taxActual['nombre_singular'] ?: $taxActual['nombre'])); ?></h2>
                <?php if ($editar): ?><a href="?tax=<?php echo $taxActual['id']; ?>" class="boton dos pequeno">Cancelar</a><?php endif; ?>
            </div>
            <form method="post" class="blog-admin-card__cuerpo blog-admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                <input type="hidden" name="accion" value="guardar_termino">
                <input type="hidden" name="id" value="<?php echo (int) ($editar['id'] ?? 0); ?>">
                <div class="field">
                    <label class="field__label" for="t-nombre">Nombre <span class="field__req">*</span></label>
                    <input class="field__input" id="t-nombre" name="nombre" required maxlength="150" value="<?php echo limpiar($editar['nombre'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="t-slug">URL (slug)</label>
                    <input class="field__input" id="t-slug" name="slug" maxlength="160" value="<?php echo limpiar($editar['slug'] ?? ''); ?>" placeholder="Se genera sola a partir del nombre">
                </div>
                <?php if ($taxActual['jerarquica']): ?>
                <div class="field">
                    <label class="field__label" for="t-parent">Categoría superior</label>
                    <select class="field__select" id="t-parent" name="parent_id">
                        <option value="">— Ninguna (principal) —</option>
                        <?php foreach ($terminos as $t): if ($editar && (int) $t['id'] === (int) $editar['id']) continue; ?>
                        <option value="<?php echo $t['id']; ?>"<?php echo (int) ($editar['parent_id'] ?? 0) === (int) $t['id'] ? ' selected' : ''; ?>><?php echo limpiar(str_repeat('— ', (int) $t['nivel']) . $t['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="field">
                    <label class="field__label" for="t-desc">Descripción</label>
                    <textarea class="field__textarea" id="t-desc" name="descripcion" rows="3" placeholder="Se muestra como introducción en la página de la categoría"><?php echo limpiar($editar['descripcion'] ?? ''); ?></textarea>
                </div>
                <details>
                    <summary style="cursor:pointer; font-size:var(--admin-body-sm); font-weight:700; color:var(--admin-tinta-suave);">SEO y orden</summary>
                    <div class="blog-admin-form" style="margin-top:.8rem;">
                        <div class="field">
                            <label class="field__label" for="t-mt">Título para Google</label>
                            <input class="field__input" id="t-mt" name="meta_title" maxlength="200" value="<?php echo limpiar($editar['meta_title'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label class="field__label" for="t-md">Descripción para Google</label>
                            <textarea class="field__textarea" id="t-md" name="meta_description" rows="2" maxlength="300"><?php echo limpiar($editar['meta_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="field">
                            <label class="field__label" for="t-orden">Orden</label>
                            <input class="field__input" type="number" id="t-orden" name="orden" value="<?php echo (int) ($editar['orden'] ?? 0); ?>">
                            <p class="field__hint">Menor número = aparece antes.</p>
                        </div>
                    </div>
                </details>
                <button type="submit" class="boton uno"><?php echo $editar ? 'Guardar cambios' : 'Añadir'; ?></button>
            </form>
        </section>
    </div>

    <!-- Ajustes de la taxonomía actual -->
    <section class="blog-admin-card" style="margin-top: var(--espacio-cuatro);">
        <div class="blog-admin-card__cabecera">
            <h2 class="blog-admin-card__titulo"><i data-lucide="settings-2" class="icono"></i> Ajustes de «<?php echo limpiar($taxActual['nombre']); ?>»</h2>
            <?php if ($taxActual['sistema']): ?><span class="admin-pill">Del sistema — no se puede eliminar</span><?php endif; ?>
        </div>
        <form method="post" class="blog-admin-card__cuerpo blog-admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
            <input type="hidden" name="accion" value="guardar_taxonomia">
            <input type="hidden" name="id" value="<?php echo $taxActual['id']; ?>">
            <div class="blog-admin-grid2">
                <div class="field"><label class="field__label" for="x-n">Nombre (plural)</label><input class="field__input" id="x-n" name="nombre" required value="<?php echo limpiar($taxActual['nombre']); ?>"></div>
                <div class="field"><label class="field__label" for="x-ns">Nombre (singular)</label><input class="field__input" id="x-ns" name="nombre_singular" value="<?php echo limpiar($taxActual['nombre_singular']); ?>"></div>
            </div>
            <div class="field"><label class="field__label" for="x-d">Descripción interna</label><input class="field__input" id="x-d" name="descripcion" value="<?php echo limpiar($taxActual['descripcion']); ?>"></div>
            <div style="display:flex; gap:var(--espacio-cuatro); flex-wrap:wrap;">
                <?php if (!$taxActual['sistema']): ?>
                <label class="field__opcion"><input type="checkbox" class="field__check" name="jerarquica" value="1"<?php echo $taxActual['jerarquica'] ? ' checked' : ''; ?>><span>Admite subniveles (como las categorías)</span></label>
                <?php else: ?><input type="hidden" name="jerarquica" value="<?php echo (int) $taxActual['jerarquica']; ?>"><?php endif; ?>
                <label class="field__opcion"><input type="checkbox" class="field__check" name="publica" value="1"<?php echo $taxActual['publica'] ? ' checked' : ''; ?>><span>Tiene páginas públicas en el blog</span></label>
            </div>
            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                <button type="submit" class="boton uno pequeno">Guardar ajustes</button>
            </div>
        </form>
        <?php if (!$taxActual['sistema']): ?>
        <form method="post" style="padding: 0 var(--espacio-cuatro) var(--espacio-cuatro);" onsubmit="return confirmarForm(this, { titulo: 'Eliminar taxonomía', mensaje: 'Se eliminan la taxonomía y todos sus términos. Los artículos no se borran.', textoOK: 'Eliminar', peligroso: true })">
            <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
            <input type="hidden" name="accion" value="eliminar_taxonomia">
            <input type="hidden" name="id" value="<?php echo $taxActual['id']; ?>">
            <button type="submit" class="boton pequeno" style="background:transparent;color:var(--admin-tone-error-fg);border:1px solid var(--admin-tone-error-bord);"><i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar esta taxonomía</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Nueva taxonomía -->
    <section class="blog-admin-card" id="nueva-taxonomia" style="margin-top: var(--espacio-cuatro);">
        <div class="blog-admin-card__cabecera">
            <h2 class="blog-admin-card__titulo"><i data-lucide="plus-circle" class="icono"></i> Nueva taxonomía</h2>
        </div>
        <form method="post" class="blog-admin-card__cuerpo blog-admin-form">
            <p class="bpanel__hint" style="font-size:var(--admin-body-sm);">Una forma extra de clasificar artículos, además de categorías y etiquetas. Ejemplos: «Tipo de mascota» (perro, gato…), «Etapa» (antes, durante, después).</p>
            <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
            <input type="hidden" name="accion" value="guardar_taxonomia">
            <div class="blog-admin-grid2">
                <div class="field"><label class="field__label" for="n-n">Nombre (plural) <span class="field__req">*</span></label><input class="field__input" id="n-n" name="nombre" required placeholder="Tipos de mascota"></div>
                <div class="field"><label class="field__label" for="n-ns">Nombre (singular)</label><input class="field__input" id="n-ns" name="nombre_singular" placeholder="Tipo de mascota"></div>
            </div>
            <div style="display:flex; gap:var(--espacio-cuatro); flex-wrap:wrap;">
                <label class="field__opcion"><input type="checkbox" class="field__check" name="jerarquica" value="1"><span>Admite subniveles</span></label>
                <label class="field__opcion"><input type="checkbox" class="field__check" name="publica" value="1" checked><span>Tiene páginas públicas en el blog</span></label>
            </div>
            <div><button type="submit" class="boton uno pequeno">Crear taxonomía</button></div>
        </form>
    </section>
</div>

<?php include 'footer.php'; ?>
