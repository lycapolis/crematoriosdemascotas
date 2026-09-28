<?php
/**
 * BLOG — autores (E-E-A-T: quién escribe y con qué experiencia)
 */

require_once __DIR__ . '/_blog-comun.php';

$pdo  = obtenerConexion();
$csrf = generarTokenCSRF();
$ir = function (array $q) { header('Location: blog-autores.php?' . http_build_query($q)); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) $ir(['error' => 'La sesión expiró, intentá de nuevo']);
    $aid = (int) ($_POST['id'] ?? 0);
    if (($_POST['accion'] ?? '') === 'eliminar') {
        $pdo->prepare("DELETE FROM blog_autores WHERE id = :id")->execute([':id' => $aid]);
        $ir(['ok' => 'Autor eliminado. Sus artículos quedan sin autor.']);
    }
    $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
    if ($nombre === '') $ir(['error' => 'El nombre es obligatorio', 'editar' => $aid]);
    $datos = [
        ':n'  => $nombre,
        ':s'  => blogSlugUnico('blog_autores', $nombre, $aid),
        ':c'  => mb_substr(trim($_POST['cargo'] ?? ''), 0, 150) ?: null,
        ':b'  => trim($_POST['bio'] ?? '') ?: null,
        ':cr' => mb_substr(trim($_POST['credenciales'] ?? ''), 0, 500) ?: null,
        ':f'  => trim($_POST['foto'] ?? '') ?: null,
        ':w'  => filter_var(trim($_POST['web'] ?? ''), FILTER_VALIDATE_URL) ?: null,
        ':a'  => !empty($_POST['activo']) ? 1 : 0,
    ];
    if ($aid) {
        $pdo->prepare("UPDATE blog_autores SET nombre=:n, slug=:s, cargo=:c, bio=:b, credenciales=:cr, foto=:f, web=:w, activo=:a WHERE id=:id")->execute($datos + [':id' => $aid]);
    } else {
        $pdo->prepare("INSERT INTO blog_autores (nombre, slug, cargo, bio, credenciales, foto, web, activo) VALUES (:n, :s, :c, :b, :cr, :f, :w, :a)")->execute($datos);
    }
    $ir(['ok' => 'Autor guardado']);
}

$autores = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM blog_articulos WHERE autor_id = a.id) AS total FROM blog_autores a ORDER BY a.activo DESC, a.nombre")->fetchAll(PDO::FETCH_ASSOC);
$editar = null;
foreach ($autores as $a) if ((int) $a['id'] === (int) ($_GET['editar'] ?? 0)) $editar = $a;

$titulo_pagina = 'Autores — Blog — Admin';
$head_extra = '<link rel="stylesheet" href="' . assetUrl('assets/css/admin-blog.css') . '">';
include 'header.php';
?>

<div class="admin-page">
    <header class="admin-page-header">
        <h1 class="admin-page-title">Autores</h1>
        <p class="admin-page-subtitle">Google valora saber quién escribe y qué experiencia tiene. Completa la bio y las credenciales reales de cada autor.</p>
    </header>

    <?php blogSubnav('autores'); ?>

    <div class="blog-admin-2col">
        <section class="blog-admin-card">
            <div class="blog-admin-card__cabecera"><h2 class="blog-admin-card__titulo"><i data-lucide="users" class="icono"></i> Autores <span class="admin-pill"><?php echo count($autores); ?></span></h2></div>
            <?php if (!$autores): ?>
            <div class="blog-admin-card__cuerpo" style="color:var(--admin-tinta-tenue);">Todavía no hay autores.</div>
            <?php else: ?>
            <div class="admin-table-wrap" style="border:0; box-shadow:none;">
                <table class="admin-table">
                    <thead><tr><th>Autor</th><th class="admin-table__num">Artículos</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($autores as $a): ?>
                        <tr>
                            <td>
                                <div class="blog-admin-lista__fila" style="grid-template-columns: 40px 1fr;">
                                    <span class="blog-admin-lista__mini" style="width:40px;height:40px;border-radius:50%;">
                                        <?php if ($a['foto']): ?><img src="<?php echo limpiar(blogUrlArchivo($a['foto'])); ?>" alt=""><?php else: ?><strong style="color:var(--admin-brand);"><?php echo limpiar(mb_substr($a['nombre'], 0, 1)); ?></strong><?php endif; ?>
                                    </span>
                                    <div><strong><?php echo limpiar($a['nombre']); ?></strong><div class="blog-admin-lista__sub"><?php echo limpiar($a['cargo'] ?? ''); ?></div></div>
                                </div>
                            </td>
                            <td class="admin-table__num"><?php echo (int) $a['total']; ?></td>
                            <td><span class="admin-pill <?php echo $a['activo'] ? 'admin-pill--exito' : ''; ?>"><?php echo $a['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                            <td><div class="blog-admin-acciones"><a class="boton dos pequeno" href="?editar=<?php echo $a['id']; ?>#form-autor"><i data-lucide="pencil" class="icono"></i></a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="blog-admin-card" id="form-autor">
            <div class="blog-admin-card__cabecera">
                <h2 class="blog-admin-card__titulo"><i data-lucide="<?php echo $editar ? 'pencil' : 'user-plus'; ?>" class="icono"></i> <?php echo $editar ? 'Editar autor' : 'Nuevo autor'; ?></h2>
                <?php if ($editar): ?><a href="blog-autores.php" class="boton dos pequeno">Cancelar</a><?php endif; ?>
            </div>
            <form method="post" class="blog-admin-card__cuerpo blog-admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int) ($editar['id'] ?? 0); ?>">
                <div class="field"><label class="field__label" for="a-n">Nombre <span class="field__req">*</span></label><input class="field__input" id="a-n" name="nombre" required maxlength="150" value="<?php echo limpiar($editar['nombre'] ?? ''); ?>"></div>
                <div class="field"><label class="field__label" for="a-c">Cargo o profesión</label><input class="field__input" id="a-c" name="cargo" maxlength="150" value="<?php echo limpiar($editar['cargo'] ?? ''); ?>" placeholder="Ej.: Veterinaria, especialista en duelo"></div>
                <div class="field">
                    <span class="field__label">Foto</span>
                    <div class="blog-admin-imagen-campo" id="a-foto-campo">
                        <img src="<?php echo limpiar(blogUrlArchivo($editar['foto'] ?? '')); ?>" alt="" style="width:64px;height:64px;border-radius:50%;"<?php echo empty($editar['foto']) ? ' hidden' : ''; ?>>
                        <input type="hidden" name="foto" value="<?php echo limpiar($editar['foto'] ?? ''); ?>">
                        <label class="boton dos pequeno" style="cursor:pointer;"><i data-lucide="upload" class="icono" style="width:14px;height:14px;"></i> Subir foto<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
                    </div>
                </div>
                <div class="field"><label class="field__label" for="a-b">Biografía breve</label><textarea class="field__textarea" id="a-b" name="bio" rows="4" placeholder="2-3 frases sobre su experiencia con mascotas, el duelo o el sector"><?php echo limpiar($editar['bio'] ?? ''); ?></textarea></div>
                <div class="field"><label class="field__label" for="a-cr">Credenciales</label><input class="field__input" id="a-cr" name="credenciales" maxlength="500" value="<?php echo limpiar($editar['credenciales'] ?? ''); ?>" placeholder="Ej.: Colegiada nº… · 10 años en clínica veterinaria"><p class="field__hint">Solo datos reales y verificables.</p></div>
                <div class="field"><label class="field__label" for="a-w">Web o perfil profesional</label><input class="field__input" type="url" id="a-w" name="web" value="<?php echo limpiar($editar['web'] ?? ''); ?>" placeholder="https://…"></div>
                <label class="field__opcion"><input type="checkbox" class="field__check" name="activo" value="1"<?php echo ($editar['activo'] ?? 1) ? ' checked' : ''; ?>><span>Activo (aparece en el editor)</span></label>
                <div><button type="submit" class="boton uno"><?php echo $editar ? 'Guardar cambios' : 'Crear autor'; ?></button></div>
            </form>
            <?php if ($editar): ?>
            <form method="post" style="padding: 0 var(--espacio-cuatro) var(--espacio-cuatro);" onsubmit="return confirmarForm(this, { titulo: 'Eliminar autor', mensaje: 'Sus <?php echo (int) $editar['total']; ?> artículos quedarán sin autor.', textoOK: 'Eliminar', peligroso: true })">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar($csrf); ?>">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?php echo (int) $editar['id']; ?>">
                <button type="submit" class="boton pequeno" style="background:transparent;color:var(--admin-tone-error-fg);border:1px solid var(--admin-tone-error-bord);"><i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar autor</button>
            </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
(function () {
    var cont = document.getElementById('a-foto-campo');
    var file = cont.querySelector('input[type=file]');
    file.addEventListener('change', function () {
        if (!file.files[0]) return;
        var fd = new FormData();
        fd.append('image', file.files[0]);
        fd.append('uso', 'autor');
        fd.append('nombre', 'autor-' + (document.getElementById('a-n').value || 'blog'));
        fetch('<?php echo BASE_URL; ?>/admin/blog-subir-imagen-ajax.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': <?php echo json_encode($csrf); ?> } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.mensaje || 'No se pudo subir');
                cont.querySelector('input[type=hidden]').value = d.file.ruta;
                var img = cont.querySelector('img'); img.src = d.file.url; img.hidden = false;
                toast.ok('Foto subida. Recuerda guardar.');
            }).catch(function (e) { toast.error(e.message); });
        file.value = '';
    });
})();
</script>

<?php include 'footer.php'; ?>
