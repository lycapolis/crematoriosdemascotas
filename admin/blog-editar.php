<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BLOG — editor de artículo (Editor.js por bloques)
 * ═══════════════════════════════════════════════════════════
 * Columna izquierda: módulos especiales (arrastrar) + Estructura del artículo.
 * Centro: portada, título, entradilla y cuerpo (pegar desde Google Docs/Word).
 * Derecha: publicación, categorías, etiquetas, contexto (dónde se muestra) y SEO.
 * Guarda por AJAX (blog-guardar-ajax.php). Vista previa: blog-preview.php.
 */

require_once __DIR__ . '/_blog-comun.php';

$pdo = obtenerConexion();
$id  = (int) ($_GET['id'] ?? 0);
$art = $id ? blogArticuloPorId($id) : null;
if ($id && !$art) { header('Location: blog-articulos.php?error=' . urlencode('El artículo no existe')); exit; }

$autores = $pdo->query("SELECT id, nombre, cargo FROM blog_autores WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if (!$art) {
    $art = [
        'id' => 0, 'titulo' => '', 'slug' => '', 'extracto' => '', 'contenido_json' => '', 'portada_ruta' => '', 'portada_alt' => '',
        'autor_id' => $autores[0]['id'] ?? null, 'revisor_id' => null, 'categoria_principal_id' => null,
        'estado' => 'borrador', 'publicado_at' => null, 'destacado' => 0, 'mostrar_indice' => 1,
        'meta_title' => '', 'meta_description' => '', 'keyword_principal' => '', 'canonical_url' => '', 'noindex' => 0,
        'updated_at' => null,
    ];
}

// Taxonomías + términos + seleccionados
$taxonomias = blogTaxonomias();
$seleccionados = [];
if ($id) {
    $st = $pdo->prepare("SELECT termino_id FROM blog_articulo_termino WHERE articulo_id = :id");
    $st->execute([':id' => $id]);
    $seleccionados = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
$terminosPorTax = [];
foreach ($taxonomias as $tx) $terminosPorTax[$tx['id']] = blogTerminos((int) $tx['id']);
$taxCat = null; $taxEtq = null; $taxLibres = [];
foreach ($taxonomias as $tx) {
    if ($tx['slug'] === 'categoria') $taxCat = $tx;
    elseif ($tx['slug'] === 'etiqueta') $taxEtq = $tx;
    else $taxLibres[] = $tx;
}

// Contexto
$contexto = ['ficha' => [], 'ciudad' => [], 'provincia' => [], 'comunidad' => [], 'servicio' => []];
if ($id) {
    $st = $pdo->prepare("SELECT tipo, valor FROM blog_articulo_contexto WHERE articulo_id = :id");
    $st->execute([':id' => $id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $contexto[$c['tipo']][] = $c['valor'];
}
$fichas      = $pdo->query("SELECT id, nombre, ciudad FROM crematorios WHERE estado IN ('activa','pausada') ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$ciudades    = $pdo->query("SELECT c.id, c.nombre, p.nombre AS provincia FROM ciudades c JOIN provincias p ON p.id = c.provincia_id WHERE c.activo = 1 ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
$provincias  = $pdo->query("SELECT id, nombre FROM provincias WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$comunidades = $pdo->query("SELECT id, nombre FROM comunidades_autonomas WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$modulos     = array_map(fn($m) => ['id' => (int) $m['id'], 'nombre' => $m['nombre'], 'tipo' => $m['tipo']], blogModulos(true));
$tiposModulo = blogTiposModulo();

$pubLocal = $art['publicado_at'] ? date('Y-m-d\TH:i', strtotime($art['publicado_at'])) : '';
$yaPublicado = $art['estado'] !== 'borrador' && $art['publicado_at'];

$config = [
    'id'        => (int) $art['id'],
    'csrf'      => generarTokenCSRF(),
    'base'      => BASE_URL,
    'host'      => $_SERVER['HTTP_HOST'] ?? '',
    'urls'      => [
        'guardar' => BASE_URL . '/admin/blog-guardar-ajax.php',
        'subir'   => BASE_URL . '/admin/blog-subir-imagen-ajax.php',
        'imagenes'=> BASE_URL . '/admin/blog-imagenes-ajax.php',
        'preview' => BASE_URL . '/admin/blog-preview.php',
        'taxo'    => BASE_URL . '/admin/blog-taxonomias.php',
        'editar'  => BASE_URL . '/admin/blog-editar.php',
        'blog'    => blogUrlIndice(),
    ],
    'contenido'   => json_decode(blogPrepararJsonEditor($art['contenido_json']), true),
    'modulos'     => $modulos,
    'estadoGuardado' => $art['estado'],
    'actualizado' => $art['updated_at'] ? strtotime($art['updated_at']) * 1000 : 0,
    'taxCategoria'=> $taxCat ? (int) $taxCat['id'] : 0,
    'taxEtiqueta' => $taxEtq ? (int) $taxEtq['id'] : 0,
    'dominio'     => parse_url(blogUrlAbsoluta('/'), PHP_URL_HOST),
    'imagenes'    => [
        'pexels'  => defined('PEXELS_API_KEY') && PEXELS_API_KEY !== '',
        'pixabay' => defined('PIXABAY_API_KEY') && PIXABAY_API_KEY !== '',
        'logo'    => is_file(ROOT_PATH . '/' . BLOG_LOGO_MARCA),
    ],
];

$titulo_pagina = ($id ? 'Editar' : 'Nuevo') . ' artículo — Blog — Admin';
$head_extra = '<link rel="stylesheet" href="' . assetUrl('assets/css/admin-blog.css') . '">';
include 'header.php';

$sel = fn($v, $lista) => in_array((string) $v, array_map('strval', $lista), true) ? ' selected' : '';
?>

<div class="blog-editor-page">

    <!-- ═══ Barra superior fija ═══ -->
    <div class="blog-editor__barra">
        <a class="blog-editor__volver" href="blog-articulos.php"><i data-lucide="arrow-left" class="icono"></i> Artículos</a>
        <span class="admin-pill <?php echo $art['estado'] === 'publicado' ? 'admin-pill--exito' : ($art['estado'] === 'programado' ? 'admin-pill--info' : ''); ?>" id="bed-pill-estado">
            <?php echo ['borrador' => 'Borrador', 'programado' => 'Programado', 'publicado' => 'Publicado'][$art['estado']]; ?>
        </span>
        <span class="blog-editor__estado-guardado" id="bed-guardado"><?php echo $id ? 'Sin cambios' : 'Nuevo artículo'; ?></span>
        <div class="blog-editor__barra-acciones">
            <?php if ($id && $art['estado'] !== 'borrador'): ?>
            <a class="boton dos pequeno" id="bed-ver" href="<?php echo blogUrlArticulo($art['slug']); ?>" target="_blank"><i data-lucide="external-link" class="icono"></i> Ver publicado</a>
            <?php endif; ?>
            <button type="button" class="boton dos pequeno" id="bed-preview"><i data-lucide="eye" class="icono"></i> Vista previa</button>
            <button type="button" class="boton uno pequeno" id="bed-guardar"><i data-lucide="save" class="icono"></i> <span>Guardar</span></button>
        </div>
    </div>

    <div id="bed-recuperar" class="blog-editor__recuperar" hidden>
        <i data-lucide="life-buoy" class="icono" style="width:18px;height:18px;"></i>
        <span>Encontramos una copia sin guardar de este artículo en este navegador.</span>
        <button type="button" class="boton uno pequeno" id="bed-recuperar-si">Recuperarla</button>
        <button type="button" class="boton dos pequeno" id="bed-recuperar-no">Descartar</button>
    </div>

    <div class="blog-editor__grid">

        <!-- ═══════════ IZQUIERDA: módulos + estructura ═══════════ -->
        <div class="blog-editor__izq">
            <details class="bpanel" open>
                <summary><i data-lucide="layout-template" class="icono"></i> Bloques especiales <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <p class="bpanel__hint">Arrástralos al lugar exacto del artículo, o pulsa <strong>+</strong> para insertarlos donde está el cursor.</p>
                    <div class="bpaleta" id="bed-paleta">
                        <?php if ($modulos): ?>
                        <span class="bpaleta__sep">Módulos de la biblioteca</span>
                        <?php foreach ($modulos as $m): $t = $tiposModulo[$m['tipo']]; ?>
                        <div class="bpaleta__item" draggable="true" data-bloque="modulo" data-modulo-id="<?php echo $m['id']; ?>" title="<?php echo limpiar($t['descripcion']); ?>">
                            <span class="bpaleta__icono bpaleta__icono--modulo"><i data-lucide="<?php echo $t['icono']; ?>" class="icono"></i></span>
                            <span class="bpaleta__nombre"><?php echo limpiar($m['nombre']); ?><span class="bpaleta__tipo"><?php echo limpiar($t['label']); ?></span></span>
                            <button type="button" class="bpaleta__mas" aria-label="Insertar"><i data-lucide="plus" class="icono"></i></button>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="bpanel__hint">Todavía no hay módulos. <a href="blog-modulos.php">Crea el primero</a>.</p>
                        <?php endif; ?>

                        <span class="bpaleta__sep">Bloques de texto</span>
                        <?php foreach ([
                            ['header',    'heading-2',       'Subtítulo (H2)'],
                            ['image',     'image',           'Imagen'],
                            ['img-banco', 'images',          'Foto de banco'],
                            ['img-ia',    'wand-sparkles',   'Imagen con IA'],
                            ['aviso',     'lightbulb',       'Consejo / Importante'],
                            ['faq',       'circle-help',     'Preguntas frecuentes'],
                            ['list',      'list',            'Lista'],
                            ['quote',     'quote',           'Cita'],
                            ['table',     'table',           'Tabla'],
                            ['delimiter', 'minus',           'Separador'],
                        ] as [$tipo, $icono, $label]): ?>
                        <div class="bpaleta__item" draggable="true" data-bloque="<?php echo $tipo; ?>">
                            <span class="bpaleta__icono"><i data-lucide="<?php echo $icono; ?>" class="icono"></i></span>
                            <span class="bpaleta__nombre"><?php echo $label; ?></span>
                            <button type="button" class="bpaleta__mas" aria-label="Insertar"><i data-lucide="plus" class="icono"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <details class="bpanel" open>
                <summary><i data-lucide="list-tree" class="icono"></i> Estructura <span class="bpanel__badge" id="bed-num-bloques">0</span> <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <p class="bpanel__hint">Todos los bloques del artículo. Arrástralos para cambiar el orden; clic para ir a cada uno.</p>
                    <ul class="bestructura" id="bed-estructura"></ul>
                </div>
            </details>
        </div>

        <!-- ═══════════ CENTRO: portada + texto ═══════════ -->
        <div class="blog-editor__centro">
            <div class="blog-editor__portada" id="bed-portada" role="img" aria-label="Imagen de portada">
                <div class="blog-editor__portada-vacia" id="bed-portada-vacia">
                    <i data-lucide="image-plus" class="icono"></i>
                    <strong>Imagen de portada</strong><br>
                    Arrastra una imagen aquí o usa los botones de abajo (JPG, PNG o WebP, máx. 5 MB).<br>
                    Se encuadra a 16:9 sin cortar lo importante.
                </div>
                <img id="bed-portada-img" alt="" hidden>
                <div class="blog-editor__portada-acciones" id="bed-portada-acciones" hidden>
                    <button type="button" id="bed-portada-cambiar" aria-haspopup="true" aria-expanded="false"><i data-lucide="refresh-cw" class="icono"></i> Cambiar</button>
                    <button type="button" id="bed-portada-quitar"><i data-lucide="trash-2" class="icono"></i> Quitar</button>
                    <div class="blog-editor__portada-menu" id="bed-portada-menu" hidden>
                        <button type="button" data-origen="subir"><i data-lucide="upload" class="icono"></i> Subir archivo</button>
                        <button type="button" data-origen="banco"><i data-lucide="images" class="icono"></i> Buscar foto de banco</button>
                        <button type="button" data-origen="ia"><i data-lucide="wand-sparkles" class="icono"></i> Generar con IA</button>
                    </div>
                </div>
                <input type="file" id="bed-portada-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            </div>
            <div class="blog-editor__portada-fuentes">
                <button type="button" class="boton dos pequeno" id="bed-portada-subir"><i data-lucide="upload" class="icono"></i> Subir archivo</button>
                <button type="button" class="boton dos pequeno" id="bed-portada-banco"><i data-lucide="images" class="icono"></i> Buscar foto de banco</button>
                <button type="button" class="boton dos pequeno" id="bed-portada-ia"><i data-lucide="wand-sparkles" class="icono"></i> Generar con IA</button>
                <label class="field__opcion" id="bed-portada-logo-wrap" title="Se aplica al subir un archivo, en la portada y en las imágenes del texto (en banco e IA se elige en su ventana). Si la imagen ya lleva el logo, no se repite."><input type="checkbox" class="field__check" id="bed-portada-logo" checked><span>Añadir logo al subir archivos (portada y texto)</span></label>
            </div>
            <div class="field blog-editor__alt" id="bed-portada-alt-wrap" hidden>
                <label class="field__label" for="bed-portada-alt">Texto alternativo de la portada <span class="field__req">*</span></label>
                <input type="text" class="field__input" id="bed-portada-alt" maxlength="255" value="<?php echo limpiar($art['portada_alt']); ?>" placeholder="Ej.: Urna de cerámica junto a una foto de un perro">
            </div>

            <textarea class="blog-editor__titulo" id="bed-titulo" rows="1" maxlength="255" placeholder="Título del artículo"><?php echo limpiar($art['titulo']); ?></textarea>
            <textarea class="blog-editor__extracto" id="bed-extracto" rows="2" maxlength="600" placeholder="Entradilla: 1-2 frases que resumen el artículo (se ve debajo del título y en las tarjetas)"><?php echo limpiar($art['extracto']); ?></textarea>

            <div class="blog-editor__lienzo" id="bed-lienzo">
                <div id="editorjs"></div>
            </div>
        </div>

        <!-- ═══════════ DERECHA: ajustes ═══════════ -->
        <div class="blog-editor__der">

            <!-- Publicación -->
            <details class="bpanel" open>
                <summary><i data-lucide="send" class="icono"></i> Publicación <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <div class="bestado" role="radiogroup" aria-label="Estado">
                        <?php foreach (['borrador' => 'Borrador', 'publicado' => 'Publicado', 'programado' => 'Programado'] as $v => $l): ?>
                        <label><input type="radio" name="bed-estado" value="<?php echo $v; ?>"<?php echo $art['estado'] === $v ? ' checked' : ''; ?>><span><?php echo $l; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="field" id="bed-fecha-wrap">
                        <label class="field__label" for="bed-fecha">Fecha de publicación</label>
                        <input type="datetime-local" class="field__input" id="bed-fecha" value="<?php echo $pubLocal; ?>">
                        <p class="field__hint" id="bed-fecha-hint">Si la dejas vacía se usa la fecha y hora actuales.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-autor">Autor</label>
                        <select class="field__select" id="bed-autor">
                            <option value="">— Sin autor —</option>
                            <?php foreach ($autores as $a): ?>
                            <option value="<?php echo $a['id']; ?>"<?php echo (int) $art['autor_id'] === (int) $a['id'] ? ' selected' : ''; ?>><?php echo limpiar($a['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-revisor">Revisado por <span style="font-weight:400;color:var(--admin-tinta-tenue);">(opcional)</span></label>
                        <select class="field__select" id="bed-revisor">
                            <option value="">— Nadie —</option>
                            <?php foreach ($autores as $a): ?>
                            <option value="<?php echo $a['id']; ?>"<?php echo (int) $art['revisor_id'] === (int) $a['id'] ? ' selected' : ''; ?>><?php echo limpiar($a['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field__hint">Ej.: un veterinario que revisó el contenido. Suma confianza para Google.</p>
                    </div>
                    <label class="field__opcion"><input type="checkbox" class="field__check" id="bed-destacado"<?php echo $art['destacado'] ? ' checked' : ''; ?>><span>Destacado (sale primero en el blog y en la home)</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" id="bed-indice"<?php echo $art['mostrar_indice'] ? ' checked' : ''; ?>><span>Mostrar índice de contenidos</span></label>
                    <?php if ($id): ?>
                    <div style="display:flex; gap:.5rem; flex-wrap:wrap; padding-top:.3rem; border-top:1px solid var(--admin-linea);">
                        <button type="button" class="boton dos pequeno" id="bed-duplicar"><i data-lucide="copy" class="icono" style="width:14px;height:14px;"></i> Duplicar</button>
                        <button type="button" class="boton pequeno" id="bed-eliminar" style="background:transparent;color:var(--admin-tone-error-fg);border:1px solid var(--admin-tone-error-bord);"><i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar</button>
                    </div>
                    <?php endif; ?>
                </div>
            </details>

            <!-- Categorías -->
            <?php if ($taxCat): ?>
            <details class="bpanel" open>
                <summary><i data-lucide="folder" class="icono"></i> Categorías <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <div class="bcats" id="bed-categorias">
                        <?php foreach ($terminosPorTax[$taxCat['id']] as $t): ?>
                        <label class="field__opcion" style="padding-left: <?php echo (int) $t['nivel'] * 1.1; ?>rem;">
                            <input type="checkbox" class="field__check" value="<?php echo $t['id']; ?>" data-nombre="<?php echo limpiar($t['nombre']); ?>"<?php echo in_array((int) $t['id'], $seleccionados, true) ? ' checked' : ''; ?>>
                            <span><?php echo limpiar($t['nombre']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="bcats__nuevo">
                        <input type="text" class="field__input" id="bed-cat-nueva" placeholder="Nueva categoría">
                        <button type="button" class="boton dos pequeno" id="bed-cat-crear">Añadir</button>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-cat-principal">Categoría principal</label>
                        <select class="field__select" id="bed-cat-principal" data-actual="<?php echo (int) $art['categoria_principal_id']; ?>"></select>
                        <p class="field__hint">Aparece encima del título y en las migas de pan.</p>
                    </div>
                </div>
            </details>
            <?php endif; ?>

            <!-- Etiquetas + taxonomías libres -->
            <details class="bpanel" open>
                <summary><i data-lucide="tags" class="icono"></i> Etiquetas<?php echo $taxLibres ? ' y taxonomías' : ''; ?> <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <?php foreach (array_merge($taxEtq ? [$taxEtq] : [], $taxLibres) as $tx): ?>
                    <div class="field">
                        <label class="field__label" for="bed-tax-<?php echo $tx['id']; ?>"><?php echo limpiar($tx['nombre']); ?></label>
                        <select multiple class="bed-tax" id="bed-tax-<?php echo $tx['id']; ?>" data-tax="<?php echo $tx['id']; ?>" placeholder="Escribe para buscar o crear…">
                            <?php foreach ($terminosPorTax[$tx['id']] as $t): ?>
                            <option value="<?php echo $t['id']; ?>"<?php echo in_array((int) $t['id'], $seleccionados, true) ? ' selected' : ''; ?>><?php echo limpiar(str_repeat('— ', (int) $t['nivel']) . $t['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                    <p class="bpanel__hint">Escribe y pulsa Enter para crear una etiqueta nueva. <a href="blog-taxonomias.php">Gestionar taxonomías</a></p>
                </div>
            </details>

            <!-- Contexto -->
            <details class="bpanel"<?php echo array_filter($contexto) ? ' open' : ''; ?>>
                <summary><i data-lucide="map-pin" class="icono"></i> Dónde mostrarlo <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <p class="bpanel__hint">Decide en qué fichas de negocio aparece este artículo como recomendado. Prioridad: ficha concreta → ciudad → provincia → comunidad → servicios. <strong>Si lo dejas vacío, es un artículo general</strong> y puede salir en cualquier ficha.</p>
                    <div class="field">
                        <label class="field__label" for="bed-ctx-ficha">Fichas concretas</label>
                        <select multiple class="bed-ctx" id="bed-ctx-ficha" data-ctx="ficha" placeholder="Buscar negocio…">
                            <?php foreach ($fichas as $f): ?><option value="<?php echo $f['id']; ?>"<?php echo $sel($f['id'], $contexto['ficha']); ?>><?php echo limpiar($f['nombre'] . ($f['ciudad'] ? ' — ' . $f['ciudad'] : '')); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-ctx-ciudad">Ciudades</label>
                        <select multiple class="bed-ctx" id="bed-ctx-ciudad" data-ctx="ciudad" placeholder="Buscar ciudad…">
                            <?php foreach ($ciudades as $c): ?><option value="<?php echo $c['id']; ?>"<?php echo $sel($c['id'], $contexto['ciudad']); ?>><?php echo limpiar($c['nombre'] . ' (' . $c['provincia'] . ')'); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-ctx-provincia">Provincias</label>
                        <select multiple class="bed-ctx" id="bed-ctx-provincia" data-ctx="provincia" placeholder="Buscar provincia…">
                            <?php foreach ($provincias as $p): ?><option value="<?php echo $p['id']; ?>"<?php echo $sel($p['id'], $contexto['provincia']); ?>><?php echo limpiar($p['nombre']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-ctx-comunidad">Comunidades autónomas</label>
                        <select multiple class="bed-ctx" id="bed-ctx-comunidad" data-ctx="comunidad" placeholder="Buscar comunidad…">
                            <?php foreach ($comunidades as $c): ?><option value="<?php echo $c['id']; ?>"<?php echo $sel($c['id'], $contexto['comunidad']); ?>><?php echo limpiar($c['nombre']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <span class="field__label">Servicios relacionados</span>
                        <div style="display:flex; flex-direction:column; gap:.1rem;" id="bed-ctx-servicios">
                            <?php foreach (blogServiciosDisponibles() as $col => $label): ?>
                            <label class="field__opcion"><input type="checkbox" class="field__check" value="<?php echo $col; ?>"<?php echo in_array($col, $contexto['servicio'], true) ? ' checked' : ''; ?>><span><?php echo $label; ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="field__hint">Se mostrará en las fichas que ofrecen alguno de estos servicios.</p>
                    </div>
                </div>
            </details>

            <!-- SEO -->
            <details class="bpanel" open>
                <summary><i data-lucide="search-check" class="icono"></i> SEO <span class="bpanel__badge" id="bed-seo-badge">—</span> <i data-lucide="chevron-down" class="icono bpanel__flecha"></i></summary>
                <div class="bpanel__cuerpo">
                    <div class="field">
                        <label class="field__label" for="bed-keyword">Palabra clave principal</label>
                        <input type="text" class="field__input" id="bed-keyword" maxlength="150" value="<?php echo limpiar($art['keyword_principal']); ?>" placeholder="Ej.: cremación individual de mascotas">
                        <p class="field__hint">La búsqueda de Google por la que quieres aparecer.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-slug">URL del artículo</label>
                        <div class="bslug"><span class="bslug__prefijo">/blog/</span><input type="text" class="field__input" id="bed-slug" maxlength="200" value="<?php echo limpiar($art['slug']); ?>" data-manual="<?php echo $art['slug'] ? '1' : '0'; ?>"></div>
                        <?php if ($yaPublicado): ?><p class="field__hint" style="color:var(--admin-tone-alerta-fg);">Ya está publicado: si cambias la URL, los enlaces antiguos dejarán de funcionar.</p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-meta-title">Título para Google <span class="bseo-contador" id="bed-mt-cont"></span></label>
                        <input type="text" class="field__input" id="bed-meta-title" maxlength="200" value="<?php echo limpiar($art['meta_title']); ?>" placeholder="Si lo dejas vacío se usa el título">
                    </div>
                    <div class="field">
                        <label class="field__label" for="bed-meta-desc">Descripción para Google <span class="bseo-contador" id="bed-md-cont"></span></label>
                        <textarea class="field__textarea" id="bed-meta-desc" rows="3" maxlength="300" placeholder="Si la dejas vacía se usa la entradilla"><?php echo limpiar($art['meta_description']); ?></textarea>
                    </div>
                    <div class="bseo-google">
                        <div class="bseo-google__kicker">Así se verá en Google</div>
                        <div class="bseo-google__url" id="bed-g-url"></div>
                        <div class="bseo-google__titulo" id="bed-g-titulo"></div>
                        <div class="bseo-google__desc" id="bed-g-desc"></div>
                    </div>
                    <div class="bseo-puntaje"><span id="bed-seo-texto">Puntuación SEO</span><div class="bseo-puntaje__barra"><div class="bseo-puntaje__relleno" id="bed-seo-barra"></div></div></div>
                    <ul class="bseo-checks" id="bed-seo-checks"></ul>
                    <details>
                        <summary style="cursor:pointer; font-size:var(--admin-caption); font-weight:700; color:var(--admin-tinta-suave);">Opciones avanzadas</summary>
                        <div style="display:flex; flex-direction:column; gap:.7rem; margin-top:.7rem;">
                            <div class="field">
                                <label class="field__label" for="bed-canonical">URL canónica <span style="font-weight:400;">(solo si el contenido está publicado en otra web)</span></label>
                                <input type="url" class="field__input" id="bed-canonical" maxlength="500" value="<?php echo limpiar($art['canonical_url']); ?>" placeholder="https://…">
                            </div>
                            <label class="field__opcion"><input type="checkbox" class="field__check" id="bed-noindex"<?php echo $art['noindex'] ? ' checked' : ''; ?>><span>No indexar este artículo en Google (noindex)</span></label>
                        </div>
                    </details>
                </div>
            </details>
        </div>
    </div>
</div>

<!-- ═══ Modal: imagen desde banco de fotos o generada con IA ═══ -->
<div class="bimg-modal" id="bimg-modal" hidden>
    <div class="bimg-modal__overlay" data-cerrar></div>
    <div class="bimg-modal__card" role="dialog" aria-modal="true" aria-labelledby="bimg-titulo">
        <header class="bimg-modal__header">
            <h2 class="bimg-modal__titulo" id="bimg-titulo">Añadir imagen</h2>
            <span class="bimg-modal__destino" id="bimg-destino"></span>
            <button type="button" class="bimg-modal__cerrar" data-cerrar aria-label="Cerrar"><i data-lucide="x" class="icono"></i></button>
        </header>
        <div class="bimg-modal__tabs" role="tablist">
            <button type="button" class="bimg-modal__tab" role="tab" data-tab="banco"><i data-lucide="images" class="icono"></i> Banco de fotos</button>
            <button type="button" class="bimg-modal__tab" role="tab" data-tab="ia"><i data-lucide="wand-sparkles" class="icono"></i> Generar con IA</button>
        </div>

        <!-- Pestaña Banco -->
        <section class="bimg-modal__panel" id="bimg-panel-banco" data-panel="banco">
            <form class="bimg-modal__buscador" id="bimg-form-buscar">
                <input type="search" class="field__input" id="bimg-q" placeholder="Ej.: old dog garden sunset">
                <select class="field__select" id="bimg-fuente">
                    <option value="ambos">Pexels + Pixabay</option>
                    <option value="pexels">Solo Pexels</option>
                    <option value="pixabay">Solo Pixabay</option>
                </select>
                <select class="field__select" id="bimg-orientacion">
                    <option value="horizontal">Horizontal</option>
                    <option value="vertical">Vertical</option>
                    <option value="todas">Todas</option>
                </select>
                <button type="submit" class="boton uno pequeno"><i data-lucide="search" class="icono"></i> Buscar</button>
            </form>
            <p class="bimg-modal__hint">Las búsquedas en inglés dan muchos más resultados. Las fotos se descargan a nuestro servidor, se optimizan (WebP, sin metadatos) y se cita al autor.</p>
            <div class="bimg-modal__grid" id="bimg-grid"></div>
            <div class="bimg-modal__mas" id="bimg-mas-wrap" hidden><button type="button" class="boton dos pequeno" id="bimg-mas">Cargar más</button></div>
        </section>

        <!-- Pestaña IA -->
        <section class="bimg-modal__panel" id="bimg-panel-ia" data-panel="ia" hidden>
            <div class="field">
                <label class="field__label" for="bimg-prompt">Escena a generar <span style="font-weight:400;">(mejor en inglés)</span></label>
                <textarea class="field__textarea" id="bimg-prompt" rows="4" maxlength="1500" placeholder="Describe la escena: sujeto, entorno, luz…"></textarea>
                <button type="button" class="boton dos pequeno bimg-modal__proponer" id="bimg-proponer"><i data-lucide="sparkles" class="icono"></i> Proponer a partir del artículo</button>
            </div>
            <div class="bimg-modal__opciones">
                <div class="field">
                    <label class="field__label" for="bimg-estilo">Estilo</label>
                    <select class="field__select" id="bimg-estilo">
                        <option value="realista">Fotografía realista cálida</option>
                        <option value="acuarela">Ilustración acuarela</option>
                        <option value="minimalista">Ilustración minimalista</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="bimg-aspecto">Proporción</label>
                    <select class="field__select" id="bimg-aspecto">
                        <option value="16:9">16:9 (portada)</option>
                        <option value="4:3">4:3 (cuerpo)</option>
                        <option value="3:2">3:2</option>
                        <option value="1:1">1:1</option>
                    </select>
                </div>
                <button type="button" class="boton uno pequeno" id="bimg-generar"><i data-lucide="wand-sparkles" class="icono"></i> Generar</button>
            </div>
            <div class="bimg-modal__preview" id="bimg-preview" hidden>
                <img id="bimg-preview-img" alt="Vista previa de la imagen generada">
                <div class="bimg-modal__preview-acciones">
                    <button type="button" class="boton dos pequeno" id="bimg-otra"><i data-lucide="refresh-cw" class="icono"></i> Otra versión</button>
                    <button type="button" class="boton uno pequeno" id="bimg-usar-ia"><i data-lucide="check" class="icono"></i> Usar esta</button>
                </div>
            </div>
        </section>

        <footer class="bimg-modal__pie">
            <label class="field__opcion" id="bimg-logo-wrap"><input type="checkbox" class="field__check" id="bimg-logo"><span>Añadir logo</span></label>
            <label class="field__opcion" id="bimg-etiqueta-wrap" hidden><input type="checkbox" class="field__check" id="bimg-etiqueta" checked><span>Indicar «Imagen generada con IA» en el pie</span></label>
            <span class="bimg-modal__estado" id="bimg-estado" aria-live="polite"></span>
        </footer>
    </div>
</div>

<!-- Formulario oculto para la vista previa (se envía en una pestaña nueva) -->
<form id="bed-form-preview" action="blog-preview.php" method="post" target="blog-preview" hidden>
    <input type="hidden" name="csrf_token" value="<?php echo limpiar($config['csrf']); ?>">
    <input type="hidden" name="payload" id="bed-preview-payload">
</form>

<script>window.BLOG_EDITOR = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.BLOG_EDITOR.portada = <?php echo json_encode(['ruta' => $art['portada_ruta'] ?: '', 'url' => blogUrlArchivo($art['portada_ruta'])], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;</script>
<?php foreach (['editorjs-editorjs.umd.js', 'header-header.umd.js', 'list-editorjs-list.umd.js', 'image-image.umd.js', 'quote-quote.umd.js',
                'delimiter-delimiter.umd.js', 'table-table.umd.js', 'embed-embed.umd.js', 'marker-marker.umd.js', 'editorjs-undo-bundle.js'] as $lib): ?>
<script src="<?php echo BASE_URL; ?>/assets/librerias/editorjs/<?php echo $lib; ?>"></script>
<?php endforeach; ?>
<script src="<?php echo assetUrl('assets/js/blog-editor-tools.js'); ?>"></script>
<script src="<?php echo assetUrl('assets/js/blog-imagenes.js'); ?>"></script>
<script src="<?php echo assetUrl('assets/js/blog-editor.js'); ?>" defer></script>

<?php include 'footer.php'; ?>
