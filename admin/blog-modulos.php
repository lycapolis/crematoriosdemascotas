<?php
/**
 * BLOG — biblioteca de módulos especiales + reglas de inserción automática
 *
 * Un módulo se crea una vez y se inserta en cualquier artículo (arrastrándolo
 * en el editor). Si se edita acá, cambia en todos los artículos a la vez.
 * Reglas: "después del párrafo N, en todos los artículos / en los de X".
 */

require_once __DIR__ . '/_blog-comun.php';

$pdo   = obtenerConexion();
$tipos = blogTiposModulo();
$csrf  = generarTokenCSRF();
$ir = function (array $q) { header('Location: blog-modulos.php?' . http_build_query($q)); exit; };

// ─── Acciones ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) $ir(['error' => 'La sesión expiró, intentá de nuevo']);
    $accion = $_POST['accion'] ?? '';
    $mid    = (int) ($_POST['id'] ?? 0);

    if ($accion === 'guardar') {
        $tipo = $_POST['tipo'] ?? '';
        if (!isset($tipos[$tipo])) $ir(['error' => 'Tipo de módulo no válido']);
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
        if ($nombre === '') $ir(['error' => 'El módulo necesita un nombre interno', 'nuevo' => $tipo]);
        $c = fn($k, $max = 500) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        $config = match ($tipo) {
            'cta' => ['titulo' => $c('titulo', 150), 'texto' => $c('texto', 400), 'boton_texto' => $c('boton_texto', 60),
                      'boton_url' => $c('boton_url'), 'estilo' => $_POST['estilo'] === 'intenso' ? 'intenso' : 'suave',
                      'icono' => preg_replace('/[^a-z0-9-]/', '', $c('icono', 40)) ?: 'search'],
            'banner' => ['imagen' => $c('imagen'), 'imagen_mobile' => $c('imagen_mobile'), 'url' => $c('url'),
                         'alt' => $c('alt', 200), 'etiqueta' => $c('etiqueta', 40)],
            'ficha_destacada' => ['modo' => $_POST['modo'] === 'fija' ? 'fija' : 'auto', 'ficha_id' => (int) ($_POST['ficha_id'] ?? 0),
                                  'titulo' => $c('titulo', 100), 'etiqueta' => $c('etiqueta', 40) ?: 'Destacado'],
            'lead' => ['titulo' => $c('titulo', 150), 'texto' => $c('texto', 400), 'boton_texto' => $c('boton_texto', 60),
                       'mensaje_wa' => $c('mensaje_wa', 300)],
        };
        if ($tipo === 'banner' && $config['imagen'] === '') $ir(['error' => 'Sube la imagen del banner', $mid ? 'editar' : 'nuevo' => $mid ?: $tipo]);
        if ($tipo === 'ficha_destacada' && $config['modo'] === 'fija' && !$config['ficha_id']) $ir(['error' => 'Elige el negocio a mostrar', $mid ? 'editar' : 'nuevo' => $mid ?: $tipo]);

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $activo = !empty($_POST['activo']) ? 1 : 0;
        if ($mid) {
            $pdo->prepare("UPDATE blog_modulos SET nombre=:n, config_json=:c, activo=:a WHERE id=:id")->execute([':n' => $nombre, ':c' => $json, ':a' => $activo, ':id' => $mid]);
        } else {
            $pdo->prepare("INSERT INTO blog_modulos (nombre, tipo, config_json, activo) VALUES (:n, :t, :c, :a)")->execute([':n' => $nombre, ':t' => $tipo, ':c' => $json, ':a' => $activo]);
            $mid = (int) $pdo->lastInsertId();
        }
        $ir(['editar' => $mid, 'ok' => 'Módulo guardado']);
    }
    if ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM blog_modulos WHERE id = :id")->execute([':id' => $mid]);
        $ir(['ok' => 'Módulo eliminado. En los artículos donde estaba insertado ya no se muestra.']);
    }
    if ($accion === 'regla_crear') {
        $parrafo = max(1, min(50, (int) ($_POST['parrafo'] ?? 3)));
        $termino = (int) ($_POST['termino_id'] ?? 0);
        $pdo->prepare("INSERT INTO blog_modulo_reglas (modulo_id, parrafo, ambito, termino_id) VALUES (:m, :p, :a, :t)")
            ->execute([':m' => $mid, ':p' => $parrafo, ':a' => $termino ? 'termino' : 'todos', ':t' => $termino ?: null]);
        $ir(['editar' => $mid, 'ok' => 'Regla añadida']);
    }
    if ($accion === 'regla_eliminar') {
        $pdo->prepare("DELETE FROM blog_modulo_reglas WHERE id = :r AND modulo_id = :m")->execute([':r' => (int) $_POST['regla_id'], ':m' => $mid]);
        $ir(['editar' => $mid, 'ok' => 'Regla eliminada']);
    }
}

$modulos = blogModulos();
$editar = null;
if (!empty($_GET['editar'])) $editar = blogModuloPorId((int) $_GET['editar']);
$nuevoTipo = isset($tipos[$_GET['nuevo'] ?? '']) ? $_GET['nuevo'] : null;
$form = $editar ?: ($nuevoTipo ? ['id' => 0, 'nombre' => '', 'tipo' => $nuevoTipo, 'activo' => 1, 'config' => []] : null);
$cfg = $form['config'] ?? [];

// Usos (inserciones manuales) y reglas
$usos = [];
foreach ($modulos as $m) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM blog_articulos WHERE contenido_json LIKE :p");
    $st->execute([':p' => '%"modulo_id":' . (int) $m['id'] . '%']);
    $usos[$m['id']] = (int) $st->fetchColumn();
}
$reglas = [];
if ($editar) {
    $st = $pdo->prepare("SELECT r.*, t.nombre AS termino_nombre, tx.nombre_singular AS tax_nombre FROM blog_modulo_reglas r
                         LEFT JOIN blog_terminos t ON t.id = r.termino_id LEFT JOIN blog_taxonomias tx ON tx.id = t.taxonomia_id
                         WHERE r.modulo_id = :m ORDER BY r.parrafo");
    $st->execute([':m' => $editar['id']]);
    $reglas = $st->fetchAll(PDO::FETCH_ASSOC);
}
$terminosRegla = $pdo->query("SELECT t.id, t.nombre, tx.nombre_singular AS tax FROM blog_terminos t JOIN blog_taxonomias tx ON tx.id = t.taxonomia_id ORDER BY tx.orden, t.nombre")->fetchAll(PDO::FETCH_ASSOC);
$fichas = ($form && $form['tipo'] === 'ficha_destacada')
    ? $pdo->query("SELECT id, nombre, ciudad, destacado FROM crematorios WHERE estado = 'activa' ORDER BY destacado DESC, nombre")->fetchAll(PDO::FETCH_ASSOC) : [];

$iconosCta = ['search' => 'Lupa', 'map-pin' => 'Ubicación', 'heart' => 'Corazón', 'paw-print' => 'Huella', 'phone' => 'Teléfono',
              'megaphone' => 'Megáfono', 'star' => 'Estrella', 'building-2' => 'Negocio', 'book-open' => 'Libro', 'gift' => 'Regalo'];

$titulo_pagina = 'Módulos especiales — Blog — Admin';
$head_extra = '<link rel="stylesheet" href="' . assetUrl('assets/css/admin-blog.css') . '">'
            . '<link rel="stylesheet" href="' . assetUrl('assets/css/blog.css') . '">';
include 'header.php';
?>

<div class="admin-page">
    <header class="admin-page-header">
        <h1 class="admin-page-title">Módulos especiales</h1>
        <p class="admin-page-subtitle">Bloques ya diseñados (llamadas a la acción, banners, negocio destacado, WhatsApp) para insertar en medio de los artículos sin romper el diseño.</p>
    </header>

    <?php blogSubnav('modulos'); ?>

    <?php if (!$form): ?>
    <!-- ═══ Crear: elegir tipo ═══ -->
    <section class="blog-admin-card" style="margin-bottom: var(--espacio-cuatro);">
        <div class="blog-admin-card__cabecera"><h2 class="blog-admin-card__titulo"><i data-lucide="plus" class="icono"></i> Crear un módulo nuevo</h2></div>
        <div class="blog-admin-card__cuerpo">
            <div class="blog-admin-tipos">
                <?php foreach ($tipos as $clave => $t): ?>
                <a class="blog-admin-tipo" href="?nuevo=<?php echo $clave; ?>" style="text-decoration:none;">
                    <span><strong><i data-lucide="<?php echo $t['icono']; ?>" class="icono"></i><?php echo $t['label']; ?></strong><?php echo $t['descripcion']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══ Biblioteca ═══ -->
    <?php if (!$modulos): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon"><i data-lucide="layout-template" class="icono"></i></div>
        <h3 class="admin-empty__titulo">La biblioteca está vacía</h3>
        <p class="admin-empty__texto">Crea tu primer módulo con las opciones de arriba.</p>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Módulo</th><th>Tipo</th><th class="admin-table__num">Artículos</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($modulos as $m): $t = $tipos[$m['tipo']]; ?>
                <tr>
                    <td><a class="blog-admin-lista__titulo" href="?editar=<?php echo $m['id']; ?>"><?php echo limpiar($m['nombre']); ?></a></td>
                    <td><span class="admin-pill"><i data-lucide="<?php echo $t['icono']; ?>" class="icono" style="width:12px;height:12px;vertical-align:-1px;"></i> <?php echo $t['label']; ?></span></td>
                    <td class="admin-table__num"><?php echo $usos[$m['id']]; ?></td>
                    <td><span class="admin-pill <?php echo $m['activo'] ? 'admin-pill--exito' : ''; ?>"><?php echo $m['activo'] ? 'Activo' : 'Pausado'; ?></span></td>
                    <td><div class="blog-admin-acciones"><a class="boton dos pequeno" href="?editar=<?php echo $m['id']; ?>"><i data-lucide="pencil" class="icono"></i></a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php else: $t = $tipos[$form['tipo']]; ?>
    <!-- ═══ Formulario del módulo ═══ -->
    <p style="margin:0 0 var(--espacio-tres);"><a href="blog-modulos.php" class="blog-editor__volver"><i data-lucide="arrow-left" class="icono"></i> Todos los módulos</a></p>

    <div class="blog-admin-2col">
        <section class="blog-admin-card">
            <div class="blog-admin-card__cabecera">
                <h2 class="blog-admin-card__titulo"><i data-lucide="<?php echo $t['icono']; ?>" class="icono"></i> <?php echo $form['id'] ? 'Editar' : 'Nuevo'; ?>: <?php echo $t['label']; ?></h2>
            </div>
            <form method="post" class="blog-admin-card__cuerpo blog-admin-form" id="form-modulo">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
                <input type="hidden" name="tipo" value="<?php echo limpiar($form['tipo']); ?>">

                <div class="field">
                    <label class="field__label" for="m-nombre">Nombre interno <span class="field__req">*</span></label>
                    <input class="field__input" id="m-nombre" name="nombre" required maxlength="150" value="<?php echo limpiar($form['nombre']); ?>" placeholder="Ej.: CTA buscar crematorio">
                    <p class="field__hint">Solo lo ves tú, para reconocerlo en el editor.</p>
                </div>

                <?php if ($form['tipo'] === 'cta'): ?>
                <div class="field"><label class="field__label" for="m-titulo">Título</label><input class="field__input" id="m-titulo" name="titulo" maxlength="150" value="<?php echo limpiar($cfg['titulo'] ?? ''); ?>"></div>
                <div class="field"><label class="field__label" for="m-texto">Texto</label><textarea class="field__textarea" id="m-texto" name="texto" rows="2" maxlength="400"><?php echo limpiar($cfg['texto'] ?? ''); ?></textarea></div>
                <div class="blog-admin-grid2">
                    <div class="field"><label class="field__label" for="m-bt">Texto del botón</label><input class="field__input" id="m-bt" name="boton_texto" maxlength="60" value="<?php echo limpiar($cfg['boton_texto'] ?? ''); ?>"></div>
                    <div class="field"><label class="field__label" for="m-bu">Enlace del botón</label><input class="field__input" id="m-bu" name="boton_url" value="<?php echo limpiar($cfg['boton_url'] ?? ''); ?>" placeholder="/directorio.php o https://…"></div>
                </div>
                <div class="blog-admin-grid2">
                    <div class="field"><label class="field__label" for="m-estilo">Estilo</label>
                        <select class="field__select" id="m-estilo" name="estilo">
                            <option value="suave"<?php echo ($cfg['estilo'] ?? '') !== 'intenso' ? ' selected' : ''; ?>>Suave (fondo arena)</option>
                            <option value="intenso"<?php echo ($cfg['estilo'] ?? '') === 'intenso' ? ' selected' : ''; ?>>Intenso (fondo marrón)</option>
                        </select></div>
                    <div class="field"><label class="field__label" for="m-icono">Icono</label>
                        <select class="field__select" id="m-icono" name="icono">
                            <?php foreach ($iconosCta as $v => $l): ?><option value="<?php echo $v; ?>"<?php echo ($cfg['icono'] ?? 'search') === $v ? ' selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <p class="field__hint">Los enlaces que empiezan por «/» son páginas de este sitio. Los externos llevan UTM automáticamente.</p>

                <?php elseif ($form['tipo'] === 'banner'): ?>
                <?php foreach (['imagen' => 'Imagen del banner (escritorio) *', 'imagen_mobile' => 'Imagen para móvil (opcional)'] as $campo => $label): ?>
                <div class="field">
                    <span class="field__label"><?php echo $label; ?></span>
                    <div class="blog-admin-imagen-campo" data-campo-imagen="<?php echo $campo; ?>">
                        <img src="<?php echo limpiar(blogUrlArchivo($cfg[$campo] ?? '')); ?>" alt=""<?php echo empty($cfg[$campo]) ? ' hidden' : ''; ?>>
                        <input type="hidden" name="<?php echo $campo; ?>" value="<?php echo limpiar($cfg[$campo] ?? ''); ?>">
                        <label class="boton dos pequeno" style="cursor:pointer;"><i data-lucide="upload" class="icono" style="width:14px;height:14px;"></i> Subir imagen<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label>
                        <button type="button" class="boton dos pequeno" data-quitar<?php echo empty($cfg[$campo]) ? ' hidden' : ''; ?>>Quitar</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <p class="field__hint">Tamaño recomendado: 1520 × 400 px (escritorio) y 800 × 600 px (móvil).</p>
                <div class="field"><label class="field__label" for="m-url">Enlace al hacer clic</label><input class="field__input" id="m-url" name="url" value="<?php echo limpiar($cfg['url'] ?? ''); ?>" placeholder="https://…"></div>
                <div class="blog-admin-grid2">
                    <div class="field"><label class="field__label" for="m-alt">Texto alternativo</label><input class="field__input" id="m-alt" name="alt" maxlength="200" value="<?php echo limpiar($cfg['alt'] ?? ''); ?>"></div>
                    <div class="field"><label class="field__label" for="m-etq">Etiqueta visible</label><input class="field__input" id="m-etq" name="etiqueta" maxlength="40" value="<?php echo limpiar($cfg['etiqueta'] ?? 'Publicidad'); ?>"><p class="field__hint">Por transparencia, los anuncios muestran «Publicidad».</p></div>
                </div>

                <?php elseif ($form['tipo'] === 'ficha_destacada'): ?>
                <div class="field"><label class="field__label" for="m-modo">¿Qué negocio se muestra?</label>
                    <select class="field__select" id="m-modo" name="modo">
                        <option value="auto"<?php echo ($cfg['modo'] ?? 'auto') === 'auto' ? ' selected' : ''; ?>>Automático: un destacado de la zona del artículo</option>
                        <option value="fija"<?php echo ($cfg['modo'] ?? '') === 'fija' ? ' selected' : ''; ?>>Siempre el mismo negocio</option>
                    </select>
                    <p class="field__hint">Automático usa la zona de «Dónde mostrarlo» del artículo; si no hay, elige un destacado cualquiera.</p>
                </div>
                <div class="field" id="m-ficha-wrap"><label class="field__label" for="m-ficha">Negocio</label>
                    <select class="field__select field__select--enhanced" id="m-ficha" name="ficha_id">
                        <option value="">— Elegir —</option>
                        <?php foreach ($fichas as $f): ?><option value="<?php echo $f['id']; ?>"<?php echo (int) ($cfg['ficha_id'] ?? 0) === (int) $f['id'] ? ' selected' : ''; ?>><?php echo limpiar(($f['destacado'] ? '★ ' : '') . $f['nombre'] . ($f['ciudad'] ? ' — ' . $f['ciudad'] : '')); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="blog-admin-grid2">
                    <div class="field"><label class="field__label" for="m-titulo">Texto superior (opcional)</label><input class="field__input" id="m-titulo" name="titulo" maxlength="100" value="<?php echo limpiar($cfg['titulo'] ?? ''); ?>" placeholder="Ej.: Crematorio recomendado"></div>
                    <div class="field"><label class="field__label" for="m-etq">Etiqueta visible</label><input class="field__input" id="m-etq" name="etiqueta" maxlength="40" value="<?php echo limpiar($cfg['etiqueta'] ?? 'Destacado'); ?>"></div>
                </div>

                <?php elseif ($form['tipo'] === 'lead'): ?>
                <div class="field"><label class="field__label" for="m-titulo">Título</label><input class="field__input" id="m-titulo" name="titulo" maxlength="150" value="<?php echo limpiar($cfg['titulo'] ?? '¿Necesitas ayuda ahora?'); ?>"></div>
                <div class="field"><label class="field__label" for="m-texto">Texto</label><textarea class="field__textarea" id="m-texto" name="texto" rows="2" maxlength="400"><?php echo limpiar($cfg['texto'] ?? ''); ?></textarea></div>
                <div class="field"><label class="field__label" for="m-bt">Texto del botón</label><input class="field__input" id="m-bt" name="boton_texto" maxlength="60" value="<?php echo limpiar($cfg['boton_texto'] ?? 'Escríbenos por WhatsApp'); ?>"></div>
                <div class="field"><label class="field__label" for="m-wa">Mensaje precargado de WhatsApp</label><textarea class="field__textarea" id="m-wa" name="mensaje_wa" rows="2" maxlength="300"><?php echo limpiar($cfg['mensaje_wa'] ?? 'Hola, me gustaría recibir ayuda para elegir un crematorio para mi mascota.'); ?></textarea>
                    <p class="field__hint">Al pulsar el botón se abre el formulario de contacto del sitio (nombre, email y teléfono) y después WhatsApp de soporte.</p></div>
                <?php endif; ?>

                <label class="field__opcion"><input type="checkbox" class="field__check" name="activo" value="1"<?php echo $form['activo'] ? ' checked' : ''; ?>><span>Activo (si lo pausas, desaparece de todos los artículos)</span></label>
                <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <button type="submit" class="boton uno">Guardar módulo</button>
                    <a href="blog-modulos.php" class="boton dos">Cancelar</a>
                </div>
            </form>
            <?php if ($form['id']): ?>
            <form method="post" style="padding: 0 var(--espacio-cuatro) var(--espacio-cuatro);" onsubmit="return confirmarForm(this, { titulo: 'Eliminar módulo', mensaje: 'Se elimina de la biblioteca y deja de mostrarse en los <?php echo $usos[$form['id']] ?? 0; ?> artículos donde está insertado.', textoOK: 'Eliminar', peligroso: true })">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
                <button type="submit" class="boton pequeno" style="background:transparent;color:var(--admin-tone-error-fg);border:1px solid var(--admin-tone-error-bord);"><i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar módulo</button>
            </form>
            <?php endif; ?>
        </section>

        <div style="display:flex; flex-direction:column; gap:var(--espacio-cuatro);">
            <?php if ($editar): ?>
            <section class="blog-admin-card">
                <div class="blog-admin-card__cabecera"><h2 class="blog-admin-card__titulo"><i data-lucide="eye" class="icono"></i> Así se ve</h2></div>
                <div class="blog-admin-card__cuerpo">
                    <div class="blog-admin-preview-modulo articulo-contenido">
                        <?php $html = blogRenderModulo(array_merge($editar, ['activo' => 1]), ['contexto' => []]);
                              echo $html ?: '<p style="color:var(--admin-tinta-tenue);">Completa los datos para ver la vista previa.</p>'; ?>
                    </div>
                </div>
            </section>

            <section class="blog-admin-card">
                <div class="blog-admin-card__cabecera"><h2 class="blog-admin-card__titulo"><i data-lucide="wand-2" class="icono"></i> Inserción automática</h2></div>
                <div class="blog-admin-card__cuerpo blog-admin-form">
                    <p class="bpanel__hint" style="font-size:var(--admin-body-sm);">Muestra este módulo solo, sin tener que insertarlo a mano. Si un artículo ya lo tiene insertado, no se repite.</p>
                    <?php foreach ($reglas as $r): ?>
                    <form method="post" style="display:flex; align-items:center; gap:.5rem; justify-content:space-between; padding:.5rem .7rem; background:var(--admin-papel-alt); border-radius:var(--admin-r-sm); font-size:var(--admin-body-sm);">
                        <span>Después del párrafo <strong><?php echo (int) $r['parrafo']; ?></strong> en <strong><?php echo $r['ambito'] === 'todos' ? 'todos los artículos' : limpiar(($r['tax_nombre'] ?? '') . ': ' . ($r['termino_nombre'] ?? '(borrado)')); ?></strong></span>
                        <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                        <input type="hidden" name="accion" value="regla_eliminar">
                        <input type="hidden" name="id" value="<?php echo (int) $editar['id']; ?>">
                        <input type="hidden" name="regla_id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="boton dos pequeno" title="Quitar regla"><i data-lucide="x" class="icono" style="width:14px;height:14px;"></i></button>
                    </form>
                    <?php endforeach; ?>
                    <form method="post" class="blog-admin-form" style="border-top:1px solid var(--admin-linea); padding-top:var(--espacio-tres);">
                        <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                        <input type="hidden" name="accion" value="regla_crear">
                        <input type="hidden" name="id" value="<?php echo (int) $editar['id']; ?>">
                        <div class="blog-admin-grid2">
                            <div class="field"><label class="field__label" for="r-p">Después del párrafo nº</label><input class="field__input" type="number" id="r-p" name="parrafo" min="1" max="50" value="3"></div>
                            <div class="field"><label class="field__label" for="r-t">En los artículos de</label>
                                <select class="field__select field__select--enhanced" id="r-t" name="termino_id">
                                    <option value="">Todos los artículos</option>
                                    <?php foreach ($terminosRegla as $tr): ?><option value="<?php echo $tr['id']; ?>"><?php echo limpiar($tr['tax'] . ': ' . $tr['nombre']); ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                        <div><button type="submit" class="boton uno pequeno"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir regla</button></div>
                    </form>
                </div>
            </section>
            <?php else: ?>
            <div class="blog-admin-ayuda"><i data-lucide="info" class="icono"></i><span>Guarda el módulo para ver la vista previa y configurar la inserción automática.</span></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var csrf = <?php echo json_encode($csrf); ?>;
    // Subida de imágenes del banner
    document.querySelectorAll('[data-campo-imagen]').forEach(function (cont) {
        var file = cont.querySelector('input[type=file]');
        var hidden = cont.querySelector('input[type=hidden]');
        var img = cont.querySelector('img');
        var quitar = cont.querySelector('[data-quitar]');
        file.addEventListener('change', function () {
            if (!file.files[0]) return;
            var fd = new FormData();
            fd.append('image', file.files[0]);
            fd.append('nombre', 'banner-' + (document.getElementById('m-nombre').value || 'blog'));
            fetch('<?php echo BASE_URL; ?>/admin/blog-subir-imagen-ajax.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.success) throw new Error(d.mensaje || 'No se pudo subir');
                    hidden.value = d.file.ruta; img.src = d.file.url; img.hidden = false; quitar.hidden = false;
                    toast.ok('Imagen subida. Recuerda guardar el módulo.');
                }).catch(function (e) { toast.error(e.message); });
            file.value = '';
        });
        quitar.addEventListener('click', function () { hidden.value = ''; img.hidden = true; quitar.hidden = true; });
    });
    // Negocio fijo solo si modo = fija
    var modo = document.getElementById('m-modo');
    if (modo) {
        var wrap = document.getElementById('m-ficha-wrap');
        var pintar = function () { wrap.hidden = modo.value !== 'fija'; };
        modo.addEventListener('change', pintar); pintar();
    }
})();
</script>

<?php include 'footer.php'; ?>
