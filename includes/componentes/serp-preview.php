<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MAQUETA DE GOOGLE (.serp-preview) — cómo podría verse la ficha en un resultado de búsqueda
 * ═══════════════════════════════════════════════════════════
 *
 * Uso:   require_once __DIR__ . '/serp-preview.php';
 *        serpPreviewRender('mi-id', $titulo, $descripcion, 'crematoriosdemascotas.com › slug', ['contadores' => true]);
 * JS:    window.serpPreviewActualizar('mi-id', titulo, descripcion)  → repinta (recorta y actualiza contadores).
 *
 * Clases: .serp-preview, __cabecera, __url, __titulo, __descripcion, __contadores, __aviso.
 * Es orientativa: Google reescribe títulos y descripciones.
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('serpPreviewRender')) {

    function serpPreviewRender(string $id, string $titulo, string $descripcion, string $urlVisible, array $opts = []): void
    {
        static $estilosPintados = false;
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $contadores = !empty($opts['contadores']);
        $aviso = $opts['aviso'] ?? 'Es una maqueta aproximada, no una garantía: Google puede mostrar tu resultado de otra forma.';
        $tMax = defined('SEO_FICHA_TITULO_MAX') ? SEO_FICHA_TITULO_MAX : 60;
        $dMax = defined('SEO_FICHA_DESC_MAX') ? SEO_FICHA_DESC_MAX : 155;

        if (!$estilosPintados) {
            $estilosPintados = true; ?>
<style>
    .serp-preview { text-align: left; }
    .serp-preview__cabecera { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #70757a; }
    /* Solo lo que ocupa el resultado de Google va dentro de la caja con borde; cabecera, contadores y aviso quedan fuera */
    .serp-preview__caja { background: #fff; border: 1px solid #dadce0; border-radius: 10px; padding: 14px 16px; font-family: Arial, Helvetica, sans-serif; }
    .serp-preview__marca { display: flex; align-items: center; gap: 10px; margin: 0 0 6px; min-width: 0; }
    .serp-preview__icono { flex: none; width: 26px; height: 26px; border-radius: 50%; background: #f1f3f4; object-fit: contain; padding: 3px; box-sizing: border-box; }
    .serp-preview__marca-textos { min-width: 0; }
    .serp-preview__sitio { margin: 0; font-size: 14px; line-height: 1.3; color: #202124; }
    .serp-preview__url { margin: 0; font-size: 12px; line-height: 1.4; color: #4d5156; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .serp-preview__titulo { margin: 2px 0 3px; font-size: 18px; line-height: 1.3; font-weight: 400; color: #1a0dab; overflow-wrap: anywhere; }
    .serp-preview__descripcion { margin: 0; font-size: 13px; line-height: 1.5; color: #202124; overflow-wrap: anywhere; }
    .serp-preview__contadores { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 10px 0 0; font-size: 12px; color: #70757a; }
    .serp-preview__contador--exceso { color: var(--admin-tone-error-fg, #b42318); font-weight: 600; }
    .serp-preview__aviso { margin: 10px 0 0; font-size: 12px; line-height: 1.45; color: #70757a; }
</style>
<script>
window.serpPreviewActualizar = function (id, titulo, descripcion) {
    var raiz = document.getElementById(id);
    if (!raiz) return;
    var tMax = <?= (int)$tMax ?>, dMax = <?= (int)$dMax ?>;
    function recortar(s, max) {
        s = (s || '').trim();
        if (s.length <= max) return s;
        var c = s.slice(0, max - 1), sp = c.lastIndexOf(' ');
        if (sp > max * 0.6) c = c.slice(0, sp);
        return c.replace(/[\s,;:.–-]+$/, '') + '…';
    }
    var t = raiz.querySelector('.serp-preview__titulo'), d = raiz.querySelector('.serp-preview__descripcion');
    t.textContent = recortar(titulo, tMax) || 'Título de tu ficha';
    d.textContent = recortar(descripcion, dMax) || 'Aquí aparecerá la descripción cuando haya datos suficientes.';
    var ct = raiz.querySelector('[data-contador="titulo"]'), cd = raiz.querySelector('[data-contador="descripcion"]');
    if (ct) { var nt = (titulo || '').trim().length; ct.textContent = 'Título: ' + nt + ' / ' + tMax; ct.classList.toggle('serp-preview__contador--exceso', nt > tMax); }
    if (cd) { var nd = (descripcion || '').trim().length; cd.textContent = 'Descripción: ' + nd + ' / ' + dMax; cd.classList.toggle('serp-preview__contador--exceso', nd > dMax); }
};
</script>
<?php   } ?>
<div class="serp-preview" id="<?= $e($id) ?>">
    <p class="serp-preview__cabecera">Así podría verse en Google</p>
    <div class="serp-preview__caja">
    <div class="serp-preview__marca">
        <img class="serp-preview__icono" src="<?= $e((defined('BASE_URL') ? BASE_URL : '') . '/assets/img/favicon/favicon-96x96.png') ?>" alt="" width="26" height="26">
        <div class="serp-preview__marca-textos">
            <p class="serp-preview__sitio">crematoriosdemascotas.com</p>
            <p class="serp-preview__url"><?= $e($urlVisible) ?></p>
        </div>
    </div>
    <h3 class="serp-preview__titulo"><?= $e(mb_strlen($titulo) > $tMax ? mb_substr($titulo, 0, $tMax - 1) . '…' : $titulo) ?></h3>
    <p class="serp-preview__descripcion"><?= $e(mb_strlen($descripcion) > $dMax ? mb_substr($descripcion, 0, $dMax - 1) . '…' : $descripcion) ?></p>
    </div><!-- /.serp-preview__caja -->
    <?php if ($contadores): ?>
    <div class="serp-preview__contadores">
        <span data-contador="titulo">Título: <?= mb_strlen($titulo) ?> / <?= (int)$tMax ?></span>
        <span data-contador="descripcion">Descripción: <?= mb_strlen($descripcion) ?> / <?= (int)$dMax ?></span>
    </div>
    <?php endif; ?>
    <?php if ($aviso !== ''): ?><p class="serp-preview__aviso"><?= $e($aviso) ?></p><?php endif; ?>
</div>
<?php
    }
}
