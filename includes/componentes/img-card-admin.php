<?php
/**
 * Card de imagen admin — componente compartido.
 *   - imagenes-cola.php          → $cfg['modo'] = 'cola'
 *   - editar-ficha-negocio.php   → $cfg['modo'] = 'ficha'
 *
 * Orden de la card (ambos modos, adaptado por contexto):
 *   chips (categoría color + estado/Manual·Auto) · origen · [nombre (cola)] ·
 *   medidas/peso · [fecha (cola)] · excerpt alt · divisor ·
 *   [controles logo/portada (ficha)] · form único (Editar: tipo+cat+alt) ·
 *   acciones (activar/desactivar · eliminar) · [Ver ficha (cola)]
 *
 * Preserva ids/handlers/endpoints del JS de cada página:
 *   img-card-{id}/$card_id, cat-label-{id}, cat-form-{id}, cat-acciones-{id},
 *   alt-label-{id}, data-img-id/seleccionable/desactivada, .img-check,
 *   abrirEdicion/cancelarEdicion/seleccionarLogo/seleccionarPortada/
 *   cambiarVisibilidad/actualizarContador/confirmarForm, data-lbg-*.
 *   Form único postea a imagen-categoria.php (guarda tipo+categoria+alt_text).
 *
 * Estilos: .img-grid / .img-card* en admin.css. Pill categoría: etiquetaCategoria().
 */
if (!isset($img) || !isset($cfg) || empty($cfg['modo'])) { return; }

$modo    = $cfg['modo'];
$imgId   = (int) $img['id'];
$catsOpc = $cfg['categoriasOpciones'] ?? [];
$cremId  = $cfg['crematorio_id'] ?? ($img['crematorio_id'] ?? 0);
$redir   = $cfg['redir'] ?? '';
$cat     = $img['categoria'] ?? '';
$tipo    = $img['tipo'] ?? 'galeria';
$etiq    = etiquetaCategoria($cat);
$mo      = metaOrigenImagen($img['origen'] ?? 'desconocido');

// alt mostrado sin el prefijo de categoría (ej. "Logo Fune..." → "Fune...")
$altDisplay = $img['alt_text'] ?? '';
if ($etiq && $altDisplay) {
    $prefix = $etiq['texto'] . ' ';
    if (str_starts_with($altDisplay, $prefix)) {
        $altDisplay = substr($altDisplay, strlen($prefix));
    }
}

// Chip claro Manual/Auto (solo cuando ya hay categoría y no está pendiente).
$chipMA = null;
if ($cat !== '' && ($img['estado_llm'] ?? '') !== 'pendiente') {
    $o = $img['categoria_origen'] ?? null;
    if ($o === 'ia')        $chipMA = ['txt' => 'Auto',   'ic' => 'sparkles'];
    elseif ($o === 'admin') $chipMA = ['txt' => 'Manual', 'ic' => 'pencil'];
}

// Resolución de ruta + medidas/peso
if ($modo === 'cola') {
    $baseU   = $cfg['base_url'] ?? (defined('BASE_URL') ? BASE_URL : '');
    $esLocal = !str_starts_with($img['ruta'], 'http');
    $imgUrl  = $esLocal ? $baseU . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/') : $img['ruta'];
    $cardId  = 'img-card-' . $imgId;
    $editUrl = $baseU . '/admin/editar-ficha-negocio.php?id=' . $cremId;
    $fichaUrl = $baseU . '/' . $img['crematorio_slug'];
    $imgInfo = '';
    if ($esLocal) {
        $fp = dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/');
        if (is_file($fp)) {
            $sz = @getimagesize($fp);
            $kb = round(filesize($fp) / 1024);
            if ($sz) $imgInfo = $sz[0] . ' × ' . $sz[1] . ' px · ' . $kb . ' KB';
        }
    }
} else { // ficha
    $imgUrl  = $cfg['img_url'];
    $esLocal = $cfg['es_local'] ?? true;
    $cardId  = $cfg['card_id'] ?? ('img-card-' . $imgId);
    $imgInfo = $cfg['img_info'] ?? '';
}

// Bloque thumb (común): botón con data-lbg-* → lightbox compartido
$lbgGroup = ($modo === 'cola') ? 'cola' : 'negocio';
ob_start(); ?>
            <button type="button"
                    data-lbg-src="<?php echo htmlspecialchars($imgUrl); ?>"
                    data-lbg-group="<?php echo $lbgGroup; ?>"
                    data-lbg-alt="<?php echo htmlspecialchars($img['alt_text'] ?? ''); ?>"
                    data-lbg-nombre="<?php echo htmlspecialchars(basename($img['ruta'] ?? '')); ?>"
                    data-lbg-id="<?php echo $imgId; ?>"
                    data-lbg-del="<?php echo $esLocal ? '1' : '0'; ?>"
                    data-lbg-card="<?php echo $cardId; ?>"
                    aria-label="Ampliar imagen"
                    style="all:unset; cursor:zoom-in; display:block; line-height:0; width:100%;">
                <img src="<?php echo htmlspecialchars($imgUrl); ?>"
                     alt="<?php echo htmlspecialchars($img['alt_text'] ?? ''); ?>"
                     class="img-card__thumb" loading="lazy"
                     style="pointer-events:none;"
                     onerror="this.parentElement.outerHTML='<div class=\'img-card__thumb--ph\'><i data-lucide=\'image-off\' style=\'width:22px; height:22px;\'></i><span style=\'font-size:.7rem; font-weight:600;\'>URL no disponible</span></div>'">
            </button>
<?php $thumbHtml = ob_get_clean();

// Chip Manual/Auto (markup reutilizable)
ob_start();
if ($chipMA): ?>
                    <span class="admin-pill" style="background:var(--admin-papel-alt); color:var(--admin-tinta-suave); border:0; font-weight:500;">
                        <i data-lucide="<?php echo $chipMA['ic']; ?>" style="width:11px;height:11px;"></i> <?php echo $chipMA['txt']; ?>
                    </span>
<?php endif; $chipMAHtml = ob_get_clean();

// Pill de origen (markup reutilizable)
ob_start(); ?>
                    <span class="admin-pill" style="background:<?php echo $mo['bg']; ?>; color:<?php echo $mo['color']; ?>; border:0;" title="<?php echo htmlspecialchars($mo['lbl']); ?>">
                        <?php echo $mo['icono']; ?> <?php echo htmlspecialchars($mo['lbl']); ?>
                    </span>
<?php $origenHtml = ob_get_clean();
?>
<?php if ($modo === 'cola'): ?>
        <div class="img-card" id="<?php echo $cardId; ?>">
            <?php echo $thumbHtml; ?>
            <div class="img-card__body">

                <!-- Chips: categoría (+ portada) + origen -->
                <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
                    <?php if ($etiq): ?>
                    <span class="admin-pill" style="background:<?php echo $etiq['color']; ?>; color:#fff; border:0;"><?php echo $etiq['texto']; ?></span>
                    <?php endif; ?>
                    <?php if ($tipo === 'portada'): ?>
                    <span class="admin-pill admin-pill--exito"><i data-lucide="star" style="width:11px; height:11px;"></i> Portada</span>
                    <?php endif; ?>
                    <?php echo $origenHtml; ?>
                </div>

                <!-- Nombre del negocio (→ ficha pública) -->
                <a href="<?php echo htmlspecialchars($fichaUrl); ?>" target="_blank"
                   class="img-card__nombre" style="text-decoration:none; color:var(--admin-tinta-fuerte); display:block;"
                   title="Ver ficha pública de <?php echo htmlspecialchars($img['crematorio_nombre']); ?>">
                    <?php echo htmlspecialchars($img['crematorio_nombre']); ?>
                </a>

                <!-- Meta: medidas·peso · fecha de carga -->
                <div class="img-card__meta">
                    <?php if ($imgInfo): ?>
                    <span><?php echo htmlspecialchars($imgInfo); ?></span>
                    <span style="opacity:.6;">—</span>
                    <?php endif; ?>
                    <span><?php echo date('d/m/Y', strtotime($img['created_at'])); ?></span>
                </div>

                <?php $altCfg = ['imgId' => $imgId, 'altDisplay' => $altDisplay]; include __DIR__ . '/_img-card-alt.php'; ?>

                <hr class="img-card__divider">

                <div id="cat-label-<?php echo $imgId; ?>"></div>

                <?php
                $catCfg = ['imgId' => $imgId, 'cremId' => $cremId, 'redir' => $redir,
                           'cat' => $cat, 'tipo' => $tipo, 'catsOpc' => $catsOpc,
                           'conTipo' => true, 'altRaw' => $img['alt_text'] ?? ''];
                include __DIR__ . '/_img-card-catform.php';
                ?>

                <div id="cat-acciones-<?php echo $imgId; ?>"
                     style="display:flex; flex-wrap:wrap; gap:.6rem .8rem; align-items:center; padding-top:.2rem;">
                    <button type="button" onclick="abrirEdicion(<?php echo $imgId; ?>)"
                            style="font-size: var(--admin-body-sm); background:none; border:none; color: var(--admin-brand); cursor:pointer; padding:0; text-decoration:underline; font-weight:500; display:inline-flex; align-items:center; gap:.3rem;">
                        <i data-lucide="pencil" style="width:12px; height:12px;"></i>
                        Editar
                    </button>
                    <?php if ($esLocal): ?>
                    <form method="POST" action="imagen-eliminar.php" style="margin:0;"
                          onsubmit="return confirmarForm(this, { titulo: 'Eliminar imagen', mensaje: '¿Eliminar esta imagen? No se puede deshacer.', textoOK: 'Eliminar', peligroso: true });">
                        <input type="hidden" name="imagen_id"     value="<?php echo $imgId; ?>">
                        <input type="hidden" name="crematorio_id" value="<?php echo $cremId; ?>">
                        <?php if ($redir !== ''): ?><input type="hidden" name="redir" value="<?php echo htmlspecialchars($redir); ?>"><?php endif; ?>
                        <button type="submit"
                                style="font-size: var(--admin-body-sm); background:none; border:none; color: var(--admin-tone-error-fg); cursor:pointer; padding:0; text-decoration:underline; display:inline-flex; align-items:center; gap:.3rem;">
                            <i data-lucide="trash-2" style="width:12px; height:12px;"></i>
                            Eliminar
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="admin-pill" title="URL externa — gestionada desde editar ficha">
                        <i data-lucide="link" style="width:11px; height:11px;"></i> URL externa
                    </span>
                    <?php endif; ?>
                </div>

                <hr class="img-card__divider">

                <a href="<?php echo htmlspecialchars($editUrl); ?>"
                   style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); text-decoration:underline; font-weight: 500; display:inline-flex; align-items:center; gap:.3rem;"
                   title="Ir a editar los detalles de este negocio">
                    <i data-lucide="pencil" style="width:12px; height:12px;"></i>
                    Editar ficha
                </a>
            </div>
        </div>

<?php elseif ($modo === 'ficha'): ?>
        <?php
        $esPendiente   = $cfg['es_pendiente'] ?? false;
        $esVisible     = $cfg['es_visible'] ?? true;
        $esSelec       = $cfg['es_seleccionable'] ?? false;
        $esLogoActivo  = $cfg['es_logo_activo'] ?? false;
        $esLogoPrinc   = $cfg['es_logo_principal'] ?? false;
        $esPortActiva  = $cfg['es_portada_activa'] ?? false;
        $esPortPrinc   = $cfg['es_portada_principal'] ?? false;
        $esListaPort   = $cfg['es_lista_para_portada'] ?? false;
        $ring          = $cfg['ring'] ?? '';
        $cardStyle     = '';
        if ($ring !== '') $cardStyle .= 'outline:2px solid ' . $ring . '; outline-offset:-2px;';
        if (!$esVisible)  $cardStyle .= 'opacity:.55;';
        $esUrl     = (($cfg['variante'] ?? '') === 'url');
        $esLogo    = ($tipo === 'logo') && !$esUrl;
        $esCliente = (($cfg['variante'] ?? '') === 'cliente');
        $visActual = $img['visibilidad'] ?? 'completa';
        ?>
        <div class="img-card" id="<?php echo $cardId; ?>"
             data-img-id="<?php echo $imgId; ?>"
             data-seleccionable="<?php echo $esSelec ? '1' : '0'; ?>"
             data-desactivada="<?php echo !$esVisible ? '1' : '0'; ?>"
             <?php echo $cardStyle ? 'style="' . $cardStyle . '"' : ''; ?>>

            <?php if ($esSelec): ?>
            <div class="checkbox-seleccion" style="display:none; position:absolute; top:8px; left:8px; z-index:10;">
                <input type="checkbox" class="img-check field__check" data-id="<?php echo $imgId; ?>"
                       onchange="actualizarContador()">
            </div>
            <?php endif; ?>
            <?php if (!$esVisible): ?>
            <div class="admin-pill" style="position:absolute; top:8px; right:8px; z-index:10;">
                <i data-lucide="eye-off" style="width:11px;height:11px;"></i> Desactivada
            </div>
            <?php endif; ?>

            <?php echo $thumbHtml; ?>

            <div class="img-card__body">

                <!-- Chips: 1 fuerte de categoría (color) + 1 claro de estado -->
                <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
                    <?php if ($etiq): ?>
                    <span class="admin-pill" style="background:<?php echo $etiq['color']; ?>; color:#fff; border:0;"><?php echo $etiq['texto']; ?></span>
                    <?php endif; ?>
                    <?php if (($esLogo && $esLogoActivo) || $esPortActiva): ?>
                    <span class="admin-pill" style="background:var(--admin-papel-alt); color:var(--admin-tinta-suave); border:0; font-weight:500;">
                        <i data-lucide="check" style="width:11px;height:11px;"></i> Activo
                    </span>
                    <?php elseif ($esPendiente): ?>
                    <span class="admin-pill admin-pill--alerta">pendiente</span>
                    <?php else: ?>
                    <?php echo $chipMAHtml; ?>
                    <?php endif; ?>
                    <?php echo $origenHtml; ?>
                </div>

                <!-- Meta: medidas y peso -->
                <?php if ($imgInfo): ?>
                <div class="img-card__meta"><span><?php echo htmlspecialchars($imgInfo); ?></span></div>
                <?php endif; ?>

                <?php $altCfg = ['imgId' => $imgId, 'altDisplay' => $altDisplay]; include __DIR__ . '/_img-card-alt.php'; ?>

                <hr class="img-card__divider">

                <!-- Controles logo / portada (botones; el estado va en el chip "Activo") -->
                <?php
                $btnUsar   = 'border:0; border-radius:var(--admin-r-sm); padding:.4rem .7rem; cursor:pointer; font-weight:600; width:100%; font-size:var(--admin-body-sm);';
                $btnQuitar = 'background:var(--admin-tone-alerta-bg); color:var(--admin-tone-alerta-fg); ' . $btnUsar;
                ?>
                <?php if ($esLogo): ?>
                    <?php if (!$esLogoActivo): ?>
                    <button type="button" onclick="seleccionarLogo(<?php echo $cremId; ?>, <?php echo $imgId; ?>)"
                            style="background:var(--admin-brand-soft); color:var(--admin-brand); <?php echo $btnUsar; ?>">
                        Usar este logo
                    </button>
                    <?php elseif ($esLogoPrinc): ?>
                    <button type="button" onclick="seleccionarLogo(<?php echo $cremId; ?>, 0)" style="<?php echo $btnQuitar; ?>">
                        No usar como logo
                    </button>
                    <?php endif; ?>
                <?php elseif ($esPortActiva): ?>
                    <?php if ($esPortPrinc): ?>
                    <button type="button" onclick="seleccionarPortada(<?php echo $cremId; ?>, 0)" style="<?php echo $btnQuitar; ?>">
                        No usar como portada
                    </button>
                    <?php endif; ?>
                <?php elseif ($esListaPort): ?>
                    <button type="button" onclick="seleccionarPortada(<?php echo $cremId; ?>, <?php echo $imgId; ?>)"
                            style="background:var(--admin-tone-exito-bg); color:var(--admin-tone-exito-fg); <?php echo $btnUsar; ?>">
                        Usar como portada
                    </button>
                <?php elseif ($esCliente): ?>
                    <!-- Visibilidad pública (4 niveles, ver migración add_visibilidad_cliente) -->
                    <label class="field__label" style="margin-bottom:-.1rem;">Visibilidad pública</label>
                    <select class="field__select field__select--enhanced" data-ts-search="off"
                            onchange="cambiarVisibilidadCliente(<?php echo $imgId; ?>, this)"
                            aria-label="Visibilidad pública de la foto de cliente">
                        <option value="completa"              <?php echo $visActual === 'completa' ? 'selected' : ''; ?>>Visible en todo</option>
                        <option value="solo_galerias_cliente" <?php echo $visActual === 'solo_galerias_cliente' ? 'selected' : ''; ?>>No en galería del negocio</option>
                        <option value="solo_resena"           <?php echo $visActual === 'solo_resena' ? 'selected' : ''; ?>>Solo bajo su reseña</option>
                        <option value="oculta"                <?php echo $visActual === 'oculta' ? 'selected' : ''; ?>>Oculta del público</option>
                    </select>
                    <span id="vis-status-<?php echo $imgId; ?>" style="font-size:var(--admin-kicker); color:var(--admin-tinta-tenue);"></span>
                <?php endif; ?>

                <div id="cat-label-<?php echo $imgId; ?>"></div>

                <?php
                $catCfg = ['imgId' => $imgId, 'cremId' => $cremId, 'redir' => $redir,
                           'cat' => $cat, 'tipo' => $tipo, 'catsOpc' => $catsOpc,
                           'conTipo' => !$esCliente, 'altRaw' => $img['alt_text'] ?? ''];
                include __DIR__ . '/_img-card-catform.php';
                ?>

                <div id="cat-acciones-<?php echo $imgId; ?>"
                     style="display:flex; flex-wrap:wrap; gap:.6rem .8rem; align-items:center; margin-top:auto; padding-top:.4rem;">
                    <button type="button" onclick="abrirEdicion(<?php echo $imgId; ?>)"
                            style="font-size: var(--admin-body-sm); background:none; border:none; color: var(--admin-brand); cursor:pointer; padding:0; text-decoration:underline; font-weight:500; display:inline-flex; align-items:center; gap:.3rem;">
                        <i data-lucide="pencil" style="width:12px; height:12px;"></i>
                        Editar
                    </button>
                    <?php if ($esSelec && !$esPortActiva): ?>
                    <button type="button"
                            onclick="cambiarVisibilidad([<?php echo $imgId; ?>], <?php echo $esVisible ? 0 : 1; ?>)"
                            style="font-size: var(--admin-body-sm); background:none; border:none; color:<?php echo $esVisible ? 'var(--admin-tone-alerta-fg)' : 'var(--admin-tone-exito-fg)'; ?>; cursor:pointer; padding:0; text-decoration:underline; font-weight:500; display:inline-flex; align-items:center; gap:.3rem;">
                        <i data-lucide="<?php echo $esVisible ? 'eye-off' : 'eye'; ?>" style="width:12px; height:12px;"></i>
                        <?php echo $esVisible ? 'Desactivar' : 'Reactivar'; ?>
                    </button>
                    <?php endif; ?>
                    <?php if ($esLocal): ?>
                    <form method="POST" action="imagen-eliminar.php" style="margin:0; margin-left:auto;"
                          onsubmit="return confirmarForm(this, { titulo: 'Eliminar imagen', mensaje: '¿Eliminar esta imagen? No se puede deshacer.', textoOK: 'Eliminar', peligroso: true });">
                        <input type="hidden" name="imagen_id"     value="<?php echo $imgId; ?>">
                        <input type="hidden" name="crematorio_id" value="<?php echo $cremId; ?>">
                        <?php if ($redir !== ''): ?><input type="hidden" name="redir" value="<?php echo htmlspecialchars($redir); ?>"><?php endif; ?>
                        <button type="submit"
                                style="font-size: var(--admin-body-sm); background:none; border:none; color: var(--admin-tone-error-fg); cursor:pointer; padding:0; text-decoration:underline; display:inline-flex; align-items:center; gap:.3rem;">
                            <i data-lucide="trash-2" style="width:12px; height:12px;"></i>
                            Eliminar
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="admin-pill" style="margin-left:auto;" title="URL externa — gestionada desde editar ficha">
                        <i data-lucide="link" style="width:11px; height:11px;"></i> URL externa
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php else: ?>
        <?php echo '<!-- img-card-admin: modo "' . htmlspecialchars($modo) . '" no soportado -->'; ?>
<?php endif; ?>
