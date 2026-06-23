<?php
/**
 * Panel Admin — Cola de imágenes pendientes de análisis LLM
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/conexion_db.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once __DIR__ . '/auth.php';

$titulo_pagina = 'Cola de Imágenes — LLM';
$pdo = obtenerConexion();

// ─── Contadores (solo imágenes locales — las URL se gestionan en editar-crematorio) ──
$contadores = $pdo->query("
    SELECT estado_llm, COUNT(*) AS total
    FROM crematorio_imagenes
    WHERE ruta NOT LIKE 'http%'
    GROUP BY estado_llm
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalPendiente = (int)($contadores['pendiente'] ?? 0);

// ─── Datos para alertas globales ─────────────────────────────────────────────

// Crematorios con imágenes locales en error (agrupados); URL images excluded
$erroresPorCrematorio = $pdo->query("
    SELECT c.id AS crematorio_id, c.nombre AS crematorio_nombre, COUNT(*) AS cnt
    FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    WHERE ci.estado_llm = 'error'
      AND ci.ruta NOT LIKE 'http%'
    GROUP BY c.id, c.nombre
    ORDER BY cnt DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
$totalProcesada = (int)($contadores['procesada'] ?? 0);
// Solo imágenes locales en error (las URL nunca se pueden procesar)
$totalError = (int)$pdo->query("
    SELECT COUNT(*) FROM crematorio_imagenes
    WHERE estado_llm = 'error' AND ruta NOT LIKE 'http%'
")->fetchColumn();
// Pendientes locales: las URL nunca se procesan con LLM, excluirlas del conteo accionable
$totalPendienteLocal = (int)$pdo->query("
    SELECT COUNT(*) FROM crematorio_imagenes
    WHERE estado_llm = 'pendiente' AND ruta NOT LIKE 'http%'
")->fetchColumn();

// Alt texts pendientes (sin alt o duplicados, solo locales con categoría)
$nSinAltGlobal = (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE (alt_text IS NULL OR alt_text='') AND categoria IS NOT NULL AND categoria!='' AND ruta NOT LIKE 'http%'")->fetchColumn();
$nDupAltGlobal = (int)$pdo->query("SELECT COUNT(DISTINCT ci.id) FROM crematorio_imagenes ci INNER JOIN (SELECT crematorio_id, alt_text FROM crematorio_imagenes WHERE alt_text IS NOT NULL AND alt_text!='' AND ruta NOT LIKE 'http%' GROUP BY crematorio_id, alt_text HAVING COUNT(*)>1) dups ON ci.crematorio_id=dups.crematorio_id AND ci.alt_text=dups.alt_text")->fetchColumn();
$nAltTotal = $nSinAltGlobal + $nDupAltGlobal;

// ─── Parámetros ──────────────────────────────────────────────────────────────
$defaultFiltro = $totalPendienteLocal > 0 ? 'pendiente' : 'procesada';
$filtro        = in_array($_GET['estado'] ?? '', ['pendiente','procesada','error'])
    ? $_GET['estado'] : $defaultFiltro;
$busqueda  = trim($_GET['q']         ?? '');
$filCiudad = trim($_GET['ciudad']    ?? '');
$filTipo   = trim($_GET['tipo']      ?? '');
$filCat    = trim($_GET['categoria'] ?? '');
$filOrigen = trim($_GET['origen']    ?? '');
$origenesValidos = array_keys(listarOrigenesImagen());
if (!in_array($filOrigen, $origenesValidos, true)) $filOrigen = '';
$porPagina = 24;
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$offset    = ($pagina - 1) * $porPagina;

// Tipos y categorías válidos
$tiposValidos = ['logo', 'galeria', 'portada'];
if (!in_array($filTipo, $tiposValidos)) $filTipo = '';

// ─── Listas para dropdowns ────────────────────────────────────────────────────
$ciudades = $pdo->query("
    SELECT DISTINCT c.ciudad FROM crematorios c
    INNER JOIN crematorio_imagenes ci ON ci.crematorio_id = c.id
    WHERE c.ciudad IS NOT NULL AND c.ciudad != ''
    ORDER BY c.ciudad
")->fetchAll(PDO::FETCH_COLUMN);

// ─── WHERE dinámico ───────────────────────────────────────────────────────────
// Imágenes URL se gestionan en editar-crematorio — esta cola es solo para locales
$where  = ['ci.estado_llm = :estado', "ci.ruta NOT LIKE 'http%'"];
$params = [':estado' => $filtro];

if ($busqueda !== '') {
    $where[]      = 'c.nombre LIKE :q';
    $params[':q'] = '%' . $busqueda . '%';
}
if ($filCiudad !== '') {
    $where[]           = 'c.ciudad = :ciudad';
    $params[':ciudad'] = $filCiudad;
}
if ($filTipo !== '') {
    $where[]         = 'ci.tipo = :tipo';
    $params[':tipo'] = $filTipo;
}
if ($filCat !== '') {
    $where[]        = 'ci.categoria = :cat';
    $params[':cat'] = $filCat;
}
if ($filOrigen !== '') {
    $where[]           = 'ci.origen = :origen';
    $params[':origen'] = $filOrigen;
}

$whereSQL = implode(' AND ', $where);

// ─── Conteo con filtros ───────────────────────────────────────────────────────
$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    WHERE $whereSQL
");
$stmtTotal->execute($params);
$totalFiltro  = (int)$stmtTotal->fetchColumn();
$totalPaginas = (int)ceil($totalFiltro / $porPagina);

// ─── Imágenes ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT ci.id, ci.crematorio_id, ci.tipo, ci.categoria, ci.alt_text,
           ci.nombre_archivo, ci.ruta, ci.url_original, ci.orden_negocio,
           ci.estado_llm, ci.origen, ci.created_at,
           c.nombre AS crematorio_nombre, c.slug AS crematorio_slug, c.ciudad AS crematorio_ciudad
    FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    WHERE $whereSQL
    ORDER BY ci.created_at DESC
    LIMIT :limite OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
$stmt->execute();
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Categorías ───────────────────────────────────────────────────────────────
$categoriasOpciones = [
    ''                       => '— Sin categoría (pendiente LLM)',
    'logo'                   => 'Logo',
    'exterior'               => 'Exterior',
    'interior_sala'          => 'Interior — Sala',
    'interior_recepcion'     => 'Interior — Recepción',
    'interior_amenities'     => 'Interior — Amenities',
    'produccion_tecnologia'  => 'Producción / Tecnología',
    'recuerdos_souvenires'   => 'Recuerdos / Souvenirs',
    'equipo_personas'        => 'Equipo / Personas',
    'fotos_clientes'         => 'Fotos de clientes',
    'otro'                   => 'Otro',
];

// El mapa categoría→color/label vive ahora en etiquetaCategoria() (includes/funciones.php),
// compartido con editar-ficha-negocio.php vía includes/componentes/img-card-admin.php.

// URL de retorno para acciones (vuelve a la misma vista conservando filtros)
$fpCola = array_filter([
    'estado'   => $filtro,
    'q'        => $busqueda,
    'ciudad'   => $filCiudad,
    'tipo'     => $filTipo,
    'categoria'=> $filCat,
    'origen'   => $filOrigen,
    'pagina'   => $pagina > 1 ? $pagina : null,
]);
$redirCola = BASE_URL . '/admin/imagenes-cola.php?' . http_build_query($fpCola);

$base_url = BASE_URL;
include __DIR__ . '/header.php';
?>

<style>
/* Grid + card de imagen: ahora son .img-grid / .img-card* en admin.css
   (compartidas con editar-ficha vía includes/componentes/img-card-admin.php). */

/* Paginación */
.cola-paginacion { display:flex; gap:.4rem; justify-content:center; margin-top:var(--espacio-cuatro); flex-wrap:wrap; }
</style>

<div class="admin-page">

    <!-- Page header -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Cola de imágenes — LLM</h1>
        <p class="admin-page-subtitle">
            <?php
            $partes = [];
            if ($totalPendienteLocal > 0) $partes[] = $totalPendienteLocal . ' pendiente' . ($totalPendienteLocal === 1 ? '' : 's');
            if ($totalProcesada     > 0) $partes[] = $totalProcesada     . ' procesada' . ($totalProcesada     === 1 ? '' : 's');
            if ($totalError         > 0) $partes[] = $totalError         . ' con error';
            echo $partes ? htmlspecialchars(implode(' — ', $partes)) : 'Sin imágenes en cola';
            ?>
        </p>
    </header>

    <!-- Mensajes de acción (POST redirect) ?img_ok/?img_error → toast (puente en footer) -->

    <!-- Stats — 5 columnas fijas (alineado con dashboard) -->
    <div class="admin-grid-stats" style="grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: var(--espacio-cuatro);">
        <?php if ($totalPendienteLocal > 0): ?>
        <div class="admin-stat-card">
            <div class="admin-stat-card__top">
                <span class="admin-stat-card__icon" style="background: var(--admin-tone-alerta-bg); color: var(--admin-tone-alerta-fg);">
                    <i data-lucide="clock" style="width:14px; height:14px;"></i>
                </span>
                <span class="admin-stat-card__kicker">Pendientes</span>
            </div>
            <div class="admin-stat-card__cifra"><?php echo $totalPendienteLocal; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($totalProcesada > 0): ?>
        <div class="admin-stat-card">
            <div class="admin-stat-card__top">
                <span class="admin-stat-card__icon" style="background: var(--admin-tone-exito-bg); color: var(--admin-tone-exito-fg);">
                    <i data-lucide="check-circle-2" style="width:14px; height:14px;"></i>
                </span>
                <span class="admin-stat-card__kicker">Procesadas</span>
            </div>
            <div class="admin-stat-card__cifra"><?php echo $totalProcesada; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($totalError > 0): ?>
        <div class="admin-stat-card">
            <div class="admin-stat-card__top">
                <span class="admin-stat-card__icon" style="background: var(--admin-tone-error-bg); color: var(--admin-tone-error-fg);">
                    <i data-lucide="alert-octagon" style="width:14px; height:14px;"></i>
                </span>
                <span class="admin-stat-card__kicker">Con error</span>
            </div>
            <div class="admin-stat-card__cifra"><?php echo $totalError; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($nAltTotal > 0): ?>
        <div class="admin-stat-card">
            <div class="admin-stat-card__top">
                <span class="admin-stat-card__icon" style="background: var(--admin-tone-info-bg); color: var(--admin-tone-info-fg);">
                    <i data-lucide="type" style="width:14px; height:14px;"></i>
                </span>
                <span class="admin-stat-card__kicker">Alt pendientes</span>
            </div>
            <div class="admin-stat-card__cifra"><?php echo $nAltTotal; ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Banner: Alt texts con IA -->
    <?php if ($nAltTotal > 0): ?>
    <div class="admin-banner admin-banner--info" style="margin-bottom: var(--espacio-tres); align-items: center;">
        <i data-lucide="type" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content" style="display:flex; align-items:center; gap:var(--espacio-tres); flex-wrap:wrap;">
            <div style="flex:1; min-width: 220px;">
                <strong style="font-size: var(--admin-body);"><?php echo $nAltTotal; ?> imágenes con alt text pendiente</strong>
                <div style="font-size: var(--admin-body-sm); opacity:.85; margin-top:.15rem;">
                    <?php echo $nSinAltGlobal; ?> sin alt — <?php echo $nDupAltGlobal; ?> duplicados
                </div>
            </div>
            <label style="font-size: var(--admin-body-sm); display:flex; align-items:center; gap:.5rem;">
                Por lote
                <select id="alt-limite" onchange="document.getElementById('alt-lote-n').textContent=this.value"
                        class="field__select field__select--enhanced"
                        data-ts-search="off"
                        style="width: 100px;">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </label>
            <button id="btn-alt-global" type="button" onclick="generarAltGlobal()" class="boton uno"
                    style="display:inline-flex; align-items:center; gap:.4rem;">
                <i data-lucide="sparkles" class="icono" style="width:15px; height:15px;"></i>
                Generar <span id="alt-lote-n">20</span> alt texts con IA
            </button>
        </div>
    </div>
    <div id="alt-resultado-global" style="display:none; margin-bottom: var(--espacio-tres);"></div>
    <?php endif; ?>

    <!-- Banner: Procesar lote con IA -->
    <?php if ($totalPendienteLocal > 0): ?>
    <div class="admin-banner admin-banner--warning" style="margin-bottom: var(--espacio-cuatro); align-items: center;">
        <i data-lucide="sparkles" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content" style="display:flex; align-items:center; gap:var(--espacio-tres); flex-wrap:wrap;">
            <div style="flex:1; min-width: 220px;">
                <strong style="font-size: var(--admin-body);">Procesar lote con IA</strong>
                <div style="font-size: var(--admin-body-sm); opacity:.85; margin-top:.15rem;">
                    <?php echo $totalPendienteLocal; ?> imágenes pendientes — categorización con Claude Vision
                </div>
            </div>
            <label style="font-size: var(--admin-body-sm); display:flex; align-items:center; gap:.5rem;">
                Límite
                <select id="lote-limite"
                        class="field__select field__select--enhanced"
                        data-ts-search="off"
                        style="width: 100px;">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </label>
            <button id="btn-lote-global" type="button" onclick="procesarLoteGlobal()" class="boton uno"
                    style="display:inline-flex; align-items:center; gap:.4rem;">
                <i data-lucide="play" class="icono" style="width:15px; height:15px;"></i>
                Procesar lote
            </button>
        </div>
    </div>
    <div id="lote-resultado" style="display:none; margin-bottom: var(--espacio-cuatro);"></div>
    <?php endif; ?>

    <!-- Alerta errores LLM agrupada por negocio -->
    <?php if (!empty($erroresPorCrematorio)): ?>
    <div class="admin-banner admin-banner--error" style="margin-bottom: var(--espacio-cuatro);">
        <i data-lucide="alert-triangle" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            <strong style="font-size: var(--admin-body); display:block; margin-bottom:.4rem;">
                Errores LLM en <?php echo count($erroresPorCrematorio); ?> negocio<?php echo count($erroresPorCrematorio) === 1 ? '' : 's'; ?>
            </strong>
            <ul style="margin:0; padding-left: 1.1rem; font-size: var(--admin-body-sm); max-height: 220px; overflow-y: auto;">
                <?php foreach ($erroresPorCrematorio as $ec): ?>
                <li style="margin-bottom:.25rem;">
                    <a href="editar-ficha-negocio.php?id=<?php echo $ec['crematorio_id']; ?>#seccion-imagenes"
                       style="color: var(--admin-tone-error-fg); font-weight: 600; text-decoration: underline;">
                        <?php echo htmlspecialchars($ec['crematorio_nombre']); ?>
                    </a>
                    <span style="opacity:.75;"> — <?php echo $ec['cnt']; ?> imagen<?php echo $ec['cnt'] === 1 ? '' : 'es'; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs estado (preservan filtros activos) -->
    <?php
    $fpBase = array_filter(['q' => $busqueda, 'ciudad' => $filCiudad, 'tipo' => $filTipo, 'categoria' => $filCat, 'origen' => $filOrigen]);
    ?>
    <div class="admin-tabs" style="margin-bottom: var(--espacio-cuatro);">
        <a href="?<?php echo http_build_query(array_merge($fpBase, ['estado' => 'pendiente'])); ?>"
           class="admin-tab <?php echo $filtro === 'pendiente' ? 'admin-tab--activo' : ''; ?>">
            Pendientes <span class="admin-tab__count"><?php echo $totalPendienteLocal; ?></span>
        </a>
        <a href="?<?php echo http_build_query(array_merge($fpBase, ['estado' => 'procesada'])); ?>"
           class="admin-tab <?php echo $filtro === 'procesada' ? 'admin-tab--activo' : ''; ?>">
            Procesadas <span class="admin-tab__count"><?php echo $totalProcesada; ?></span>
        </a>
        <?php if ($totalError > 0): ?>
        <a href="?<?php echo http_build_query(array_merge($fpBase, ['estado' => 'error'])); ?>"
           class="admin-tab <?php echo $filtro === 'error' ? 'admin-tab--activo' : ''; ?>">
            Con error <span class="admin-tab__count"><?php echo $totalError; ?></span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <form method="GET" class="admin-filtros">
        <input type="hidden" name="estado" value="<?php echo $filtro; ?>">

        <div class="admin-filtros__campos">
            <div class="field">
                <label class="field__label">Negocio</label>
                <div class="field__group">
                    <span class="field__prefix"><i data-lucide="search"></i></span>
                    <input type="text" name="q" class="field__input" placeholder="Buscar nombre… (Enter)"
                           value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
                    <button type="button" class="field__clear" aria-label="Limpiar búsqueda"
                            onclick="var i=this.closest('.field__group').querySelector('input'); i.value=''; if(i.form){i.form.submit();}else{i.focus();}">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>

            <div class="field">
                <label class="field__label">Ciudad</label>
                <select name="ciudad" class="field__select field__select--enhanced"
                        data-placeholder="Todas las ciudades"
                        onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($ciudades as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filCiudad === $c ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Tipo</label>
                <select name="tipo" class="field__select field__select--enhanced"
                        data-placeholder="Todos los tipos"
                        data-ts-search="off"
                        onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="logo"    <?php echo $filTipo === 'logo'    ? 'selected' : ''; ?>>Logo</option>
                    <option value="portada" <?php echo $filTipo === 'portada' ? 'selected' : ''; ?>>Portada</option>
                    <option value="galeria" <?php echo $filTipo === 'galeria' ? 'selected' : ''; ?>>Galería</option>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Categoría</label>
                <select name="categoria" class="field__select field__select--enhanced"
                        data-placeholder="Todas las categorías"
                        data-ts-search="off"
                        onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($categoriasOpciones as $val => $label):
                        if ($val === '') continue; ?>
                    <option value="<?php echo $val; ?>" <?php echo $filCat === $val ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Origen</label>
                <select name="origen" class="field__select field__select--enhanced"
                        data-placeholder="Todos los orígenes"
                        data-ts-search="off"
                        onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach (listarOrigenesImagen() as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filOrigen === $val ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($busqueda || $filCiudad || $filTipo || $filCat || $filOrigen): ?>
        <div class="admin-filtros__acciones">
            <a href="?estado=<?php echo $filtro; ?>" class="boton dos pequeno" style="display:inline-flex; align-items:center; gap:.35rem;">
                <i data-lucide="x" class="icono" style="width:14px; height:14px;"></i>
                Limpiar
            </a>
            <?php if ($totalFiltro !== ($contadores[$filtro] ?? 0)): ?>
            <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-variant-numeric: tabular-nums;">
                <?php echo $totalFiltro; ?> resultado<?php echo $totalFiltro !== 1 ? 's' : ''; ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

    <!-- Grid -->
    <?php if (empty($imagenes)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon">
            <i data-lucide="image-off" style="width: 28px; height: 28px;"></i>
        </div>
        <div class="admin-empty__titulo">
            <?php echo match ($filtro) {
                'pendiente' => 'No hay imágenes pendientes',
                'procesada' => 'No hay imágenes procesadas',
                'error'     => 'No hay imágenes con error',
                default     => 'No hay imágenes en este estado',
            }; ?>
        </div>
        <div class="admin-empty__texto">
            <?php if ($busqueda || $filCiudad || $filTipo || $filCat || $filOrigen): ?>
                Probá ajustar o limpiar los filtros para ver más resultados.
            <?php elseif ($filtro === 'pendiente'): ?>
                Cuando se suban imágenes nuevas aparecerán acá listas para categorizar con IA.
            <?php else: ?>
                Nada que mostrar con el filtro actual.
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="img-grid">
        <?php
        $cfgColaCard = [
            'modo'               => 'cola',
            'categoriasOpciones' => $categoriasOpciones,
            'base_url'           => $base_url,
            'redir'              => $redirCola,
        ];
        foreach ($imagenes as $img):
            $cfg = $cfgColaCard;
            include __DIR__ . '/../includes/componentes/img-card-admin.php';
        endforeach;
        ?>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
    <div class="cola-paginacion">
        <?php
        $fpPag = array_filter(['estado' => $filtro, 'q' => $busqueda, 'ciudad' => $filCiudad, 'tipo' => $filTipo, 'categoria' => $filCat, 'origen' => $filOrigen]);
        for ($i = 1; $i <= $totalPaginas; $i++):
            $qs = http_build_query(array_merge($fpPag, ['pagina' => $i]));
        ?>
        <a href="?<?php echo $qs; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno"
           style="min-width: 36px; text-align: center; font-variant-numeric: tabular-nums;">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
function abrirEdicion(id) {
    document.getElementById('cat-label-'    + id).style.display = 'none';
    document.getElementById('cat-acciones-' + id).style.display = 'none';
    document.getElementById('cat-form-'     + id).style.display = 'flex';
}
function cancelarEdicion(id) {
    document.getElementById('cat-form-'     + id).style.display = 'none';
    document.getElementById('cat-label-'    + id).style.display = '';
    document.getElementById('cat-acciones-' + id).style.display = 'flex';
}
function abrirAlt(id) {
    document.getElementById('alt-label-'  + id).style.display = 'none';
    document.getElementById('btn-alt-'    + id).style.display = 'none';
    document.getElementById('alt-form-'   + id).style.display = 'flex';
}
function cancelarAlt(id) {
    document.getElementById('alt-form-'   + id).style.display = 'none';
    document.getElementById('alt-label-'  + id).style.display = '';
    document.getElementById('btn-alt-'    + id).style.display = '';
}
// Lightbox: ver includes/componentes/lightbox-galeria.php (partial compartido,
// IIFE aislado con borrado inline). Los thumbs llevan data-lbg-*.
</script>

<?php include __DIR__ . '/../includes/componentes/lightbox-galeria.php'; ?>

<script>
function generarAltGlobal() {
    var btn = document.getElementById('btn-alt-global');
    var res = document.getElementById('alt-resultado-global');
    var lim = document.getElementById('alt-limite').value;
    if (!btn || !res) return;

    btn.disabled = true;
    btn.textContent = 'Generando…';
    res.style.display = 'none';

    var fd = new FormData();
    fd.append('modo', 'global');
    fd.append('limite', lim);

    fetch('generar-alt-ajax.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var data;
            try { data = JSON.parse(text); } catch(e) { data = {ok:false, error:'JSON inválido: '+text.substring(0,150)}; }

            var clase = (!data.ok || data.actualizadas === 0) ? 'admin-banner--error'
                      : (data.errores > 0 ? 'admin-banner--warning' : 'admin-banner--ok');
            var icono = (!data.ok || data.actualizadas === 0) ? 'alert-circle'
                      : (data.errores > 0 ? 'alert-triangle' : 'check-circle-2');

            var html = '<div class="admin-banner ' + clase + '">';
            html    += '<i data-lucide="' + icono + '" class="icono admin-banner__icon"></i>';
            html    += '<div class="admin-banner__content">';
            if (!data.ok) {
                html += '<strong>Error: ' + (data.error || 'desconocido') + '</strong>';
            } else if (data.mensaje) {
                html += '<strong>' + data.mensaje + '</strong>';
            } else {
                html += '<strong>' + data.actualizadas + ' alt text' + (data.actualizadas === 1 ? '' : 's') + ' generado' + (data.actualizadas === 1 ? '' : 's');
                if (data.errores > 0) html += ' — ' + data.errores + ' error' + (data.errores === 1 ? '' : 'es');
                html += ' de ' + data.total + ' imágenes</strong>';
            }
            if ((data.sin_categoria || 0) > 0) {
                html += '<div style="margin-top:.4rem; font-size: var(--admin-body-sm); opacity:.9;">' + data.sin_categoria + ' imagen' + (data.sin_categoria === 1 ? '' : 'es') + ' omitida' + (data.sin_categoria === 1 ? '' : 's') + ' por no tener categoría — procesá el lote con IA primero.</div>';
            }
            html += '</div></div>';
            res.innerHTML = html;
            res.style.display = 'block';
            if (window.lucide) lucide.createIcons();

            if (data.ok && data.actualizadas > 0) {
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="rotate-cw" class="icono" style="width:15px;height:15px;"></i> Reintentar';
                if (window.lucide) lucide.createIcons();
            }
        })
        .catch(function(e) {
            res.innerHTML = '<div class="admin-banner admin-banner--error"><i data-lucide="wifi-off" class="icono admin-banner__icon"></i><div class="admin-banner__content"><strong>Error de red:</strong> ' + e.message + '</div></div>';
            res.style.display = 'block';
            if (window.lucide) lucide.createIcons();
            btn.disabled = false;
            btn.textContent = 'Reintentar';
        });
}

function procesarLoteGlobal() {
    var btn    = document.getElementById('btn-lote-global');
    var res    = document.getElementById('lote-resultado');
    var limite = document.getElementById('lote-limite').value;
    if (!btn || !res) return;

    btn.disabled = true;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Procesando…';
    res.style.display = 'none';

    fetch('procesar-llm-lote-ajax.php', {
        method: 'POST',
        body: new URLSearchParams({ limite: limite })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var clase = !data.ok ? 'admin-banner--error'
                  : (data.errores > 0 ? 'admin-banner--warning' : 'admin-banner--ok');
        var icono = !data.ok ? 'alert-circle'
                  : (data.errores > 0 ? 'alert-triangle' : 'check-circle-2');

        var html  = '<div class="admin-banner ' + clase + '">';
        html     += '<i data-lucide="' + icono + '" class="icono admin-banner__icon"></i>';
        html     += '<div class="admin-banner__content">';
        if (!data.ok) {
            html += '<strong>Error: ' + (data.error || 'desconocido') + '</strong>';
        } else {
            html += '<strong>' + data.procesadas + ' procesada' + (data.procesadas === 1 ? '' : 's');
            if (data.errores > 0) html += ' — ' + data.errores + ' error' + (data.errores === 1 ? '' : 'es');
            html += ' de ' + data.total + ' imágenes</strong>';
        }
        html += '</div></div>';
        res.innerHTML = html;
        res.style.display = 'block';
        if (window.lucide) lucide.createIcons();

        if (data.ok && data.procesadas > 0) {
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="rotate-cw" class="icono" style="width:15px;height:15px;"></i> Reintentar';
            if (window.lucide) lucide.createIcons();
        }
    })
    .catch(function(e) {
        res.innerHTML = '<div class="admin-banner admin-banner--error"><i data-lucide="wifi-off" class="icono admin-banner__icon"></i><div class="admin-banner__content"><strong>Error de red:</strong> ' + e.message + '</div></div>';
        res.style.display = 'block';
        if (window.lucide) lucide.createIcons();
        btn.disabled = false;
        btn.innerHTML = 'Reintentar';
    });
}
</script>
<style>@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }</style>

<?php include __DIR__ . '/footer.php'; ?>
