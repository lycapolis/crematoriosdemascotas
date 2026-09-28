<?php
/**
 * BLOG — listado de artículos (admin)
 */

require_once __DIR__ . '/_blog-comun.php';

$pdo = obtenerConexion();

// Interruptor "ocultar el blog de Google" (noindex global)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noindex_global'])) {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
        header('Location: blog-articulos.php?error=' . urlencode('La sesión expiró, intentá de nuevo'));
        exit;
    }
    blogConfigGuardar('noindex_global', $_POST['noindex_global'] === '1' ? '1' : '0');
    header('Location: blog-articulos.php?ok=' . urlencode($_POST['noindex_global'] === '1' ? 'El blog queda oculto para Google (noindex)' : 'El blog ya es visible para Google'));
    exit;
}

$fEstado = in_array($_GET['estado'] ?? '', ['borrador', 'programado', 'publicado'], true) ? $_GET['estado'] : '';
$fCat    = (int) ($_GET['categoria'] ?? 0);
$fQ      = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];
if ($fEstado) { $where[] = 'a.estado = :estado'; $params[':estado'] = $fEstado; }
if ($fCat)    { $where[] = 'a.id IN (SELECT articulo_id FROM blog_articulo_termino WHERE termino_id = :cat)'; $params[':cat'] = $fCat; }
if ($fQ !== '') { $where[] = '(a.titulo LIKE :q OR a.slug LIKE :q OR a.keyword_principal LIKE :q)'; $params[':q'] = '%' . $fQ . '%'; }

$st = $pdo->prepare(blogSqlBaseArticulos() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY (a.estado = "borrador") DESC, COALESCE(a.publicado_at, a.updated_at) DESC');
$st->execute($params);
$articulos = $st->fetchAll(PDO::FETCH_ASSOC);

$conteo = $pdo->query("SELECT estado, COUNT(*) FROM blog_articulos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$taxCat = blogTaxonomiaPorSlug('categoria');
$categorias = $taxCat ? blogTerminos((int) $taxCat['id']) : [];
$noindex = blogNoindexGlobal();
$ahora = blogAhora();

$titulo_pagina = 'Blog — Admin';
$head_extra = '<link rel="stylesheet" href="' . assetUrl('assets/css/admin-blog.css') . '">';
include 'header.php';
?>

<div class="admin-page">
    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap;">
            <h1 class="admin-page-title">Blog</h1>
            <a href="blog-editar.php" class="boton uno pequeno">
                <i data-lucide="plus" class="icono" style="width:14px; height:14px;"></i>
                Nuevo artículo
            </a>
        </div>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo (int) ($conteo['publicado'] ?? 0); ?></span> publicados
            <span class="admin-dash"></span>
            <span class="admin-num"><?php echo (int) ($conteo['programado'] ?? 0); ?></span> programados
            <span class="admin-dash"></span>
            <span class="admin-num"><?php echo (int) ($conteo['borrador'] ?? 0); ?></span> borradores
        </p>
    </header>

    <?php blogSubnav('articulos'); ?>

    <form method="post" class="blog-admin-ayuda<?php echo $noindex ? ' blog-admin-ayuda--alerta' : ''; ?>" style="align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?php echo limpiar(generarTokenCSRF()); ?>">
        <i data-lucide="<?php echo $noindex ? 'eye-off' : 'globe'; ?>" class="icono"></i>
        <span style="flex:1; min-width:240px;">
            <?php if ($noindex): ?>
            <strong>El blog está oculto para Google (noindex).</strong> Se puede visitar y compartir, pero no aparece en buscadores ni en el sitemap. Actívalo cuando los artículos de ejemplo se reemplacen por los reales.
            <?php else: ?>
            <strong>El blog es visible para Google.</strong> Los artículos publicados aparecen en el sitemap y pueden indexarse.
            <?php endif; ?>
        </span>
        <input type="hidden" name="noindex_global" value="<?php echo $noindex ? '0' : '1'; ?>">
        <button type="submit" class="boton <?php echo $noindex ? 'uno' : 'dos'; ?> pequeno"
                onclick="return confirmarForm(this.form, { titulo: '<?php echo $noindex ? 'Hacer visible el blog' : 'Ocultar el blog'; ?>', mensaje: '<?php echo $noindex ? '¿Hacer visible el blog para Google? Asegúrate de que no queden artículos de ejemplo publicados.' : '¿Ocultar todo el blog de Google?'; ?>', textoOK: 'Confirmar' })">
            <?php echo $noindex ? 'Hacer visible para Google' : 'Ocultar de Google'; ?>
        </button>
    </form>

    <form method="get" class="admin-filtros" style="margin-bottom: var(--espacio-cuatro);">
        <div class="admin-filtros__campos" style="display:grid; gap:var(--espacio-tres); grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="field" style="margin:0;">
                <label class="field__label" for="f-q">Buscar</label>
                <input class="field__input" type="search" id="f-q" name="q" value="<?php echo limpiar($fQ); ?>" placeholder="Título, URL o palabra clave">
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="f-estado">Estado</label>
                <select class="field__select field__select--enhanced" id="f-estado" name="estado" data-ts-search="off" data-ts-autosubmit="1">
                    <option value="">Todos</option>
                    <?php foreach (['publicado' => 'Publicados', 'programado' => 'Programados', 'borrador' => 'Borradores'] as $v => $l): ?>
                    <option value="<?php echo $v; ?>"<?php echo $fEstado === $v ? ' selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="f-cat">Categoría</label>
                <select class="field__select field__select--enhanced" id="f-cat" name="categoria" data-ts-autosubmit="1">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?php echo $c['id']; ?>"<?php echo $fCat === (int) $c['id'] ? ' selected' : ''; ?>><?php echo limpiar(str_repeat('— ', (int) $c['nivel']) . $c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php if (empty($articulos)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon"><i data-lucide="book-open" class="icono"></i></div>
        <h3 class="admin-empty__titulo"><?php echo ($fEstado || $fCat || $fQ) ? 'Ningún artículo coincide con el filtro' : 'Todavía no hay artículos'; ?></h3>
        <p class="admin-empty__texto">Crea un artículo nuevo: pega el texto, añade la portada y publica.</p>
        <a href="blog-editar.php" class="boton uno pequeno" style="margin-top: var(--espacio-tres);"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Nuevo artículo</a>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Artículo</th>
                    <th>Estado</th>
                    <th>Categoría</th>
                    <th>Autor</th>
                    <th>Fecha</th>
                    <th class="admin-table__num">Palabras</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($articulos as $a):
                $visible = in_array($a['estado'], ['publicado', 'programado'], true) && $a['publicado_at'] && $a['publicado_at'] <= $ahora;
                $pill = $a['estado'] === 'publicado' ? 'admin-pill--exito' : ($a['estado'] === 'programado' ? 'admin-pill--info' : '');
                $label = ['borrador' => 'Borrador', 'programado' => 'Programado', 'publicado' => 'Publicado'][$a['estado']];
                if ($a['estado'] === 'programado' && $visible) { $label = 'Publicado'; $pill = 'admin-pill--exito'; }
            ?>
                <tr>
                    <td>
                        <div class="blog-admin-lista__fila">
                            <span class="blog-admin-lista__mini">
                                <?php if ($a['portada_ruta']): ?><img src="<?php echo limpiar(blogUrlArchivo($a['portada_ruta'])); ?>" alt=""><?php else: ?><i data-lucide="image" class="icono" style="width:18px;height:18px;"></i><?php endif; ?>
                            </span>
                            <div style="min-width:0;">
                                <a class="blog-admin-lista__titulo" href="blog-editar.php?id=<?php echo $a['id']; ?>"><?php echo limpiar($a['titulo']); ?></a>
                                <?php if ($a['destacado']): ?> <span class="admin-pill admin-pill--marca">Destacado</span><?php endif; ?>
                                <div class="blog-admin-lista__sub">/blog/<?php echo limpiar($a['slug']); ?><?php echo $a['keyword_principal'] ? ' · 🔑 ' . limpiar($a['keyword_principal']) : ''; ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="admin-pill <?php echo $pill; ?>"><?php echo $label; ?></span></td>
                    <td><?php echo limpiar($a['categoria_nombre'] ?? '—'); ?></td>
                    <td><?php echo limpiar($a['autor_nombre'] ?? '—'); ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($a['publicado_at']): ?>
                            <?php echo date('d/m/Y H:i', strtotime($a['publicado_at'])); ?>
                        <?php else: ?>
                            <span style="color:var(--admin-tinta-tenue);">Editado <?php echo date('d/m/Y', strtotime($a['updated_at'])); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="admin-table__num"><?php echo number_format((int) $a['palabras'], 0, ',', '.'); ?></td>
                    <td>
                        <div class="blog-admin-acciones">
                            <a class="boton dos pequeno" href="blog-editar.php?id=<?php echo $a['id']; ?>" title="Editar"><i data-lucide="pencil" class="icono"></i></a>
                            <?php if ($visible): ?>
                            <a class="boton dos pequeno" href="<?php echo blogUrlArticulo($a['slug']); ?>" target="_blank" title="Ver en el sitio"><i data-lucide="external-link" class="icono"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
