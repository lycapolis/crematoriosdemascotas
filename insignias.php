<?php
/**
 * ═══════════════════════════════════════════════════════════
 * GLOSARIO DE INSIGNIAS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 * Explica en lenguaje simple qué significa cada insignia y cómo se consigue.
 * Lee del mismo catálogo que las tarjetas y fichas (tabla `insignias`), así que
 * lo que se edita en admin/insignias.php se actualiza aquí. Cada insignia tiene
 * un ancla (#slug) a la que enlazan los iconos de las tarjetas y fichas.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/insignias.php';

$pdo = obtenerConexion();
$insignias = array_values(array_filter(
    insigniasCatalogo($pdo, true),
    fn($i) => (int) $i['visible_tarjeta'] === 1 || (int) $i['visible_ficha'] === 1
));

/**
 * «Cómo se consigue»: texto en lenguaje simple generado desde la regla, para que el
 * glosario no se desincronice si el equipo cambia los umbrales en el panel.
 */
function glosarioComoSeConsigue(array $i): string
{
    $r = $i['regla_arr'];
    if ($i['tipo'] === 'manual') {
        return $i['slug'] === 'promocionado'
            ? 'Lo contrata el propio negocio. Siempre se indica como publicidad.'
            : 'La concede nuestro equipo tras comprobarlo personalmente (por ejemplo, llamando por teléfono al negocio).';
    }
    switch ($r['tipo'] ?? '') {
        case 'columna':
        case 'columna_o':
            return $i['slug'] === 'registrado'
                ? 'Se concede automáticamente al negocio que se registra por sí mismo en nuestro directorio.'
                : 'Se concede automáticamente cuando el negocio indica este dato en su ficha.';
        case 'completitud':
            return 'Se concede automáticamente cuando la ficha tiene completo al menos el ' . (int) ($r['min'] ?? 90) . ' % de sus datos.';
        case 'rating':
            $t = 'Se concede automáticamente con una valoración media de ' . str_replace('.', ',', (string) ($r['min_rating'] ?? 4))
               . ' estrellas o más y al menos ' . (int) ($r['min_resenas'] ?? 5) . ' opiniones (Google y portal sumadas).';
            if (isset($r['completitud_min'])) $t .= ' Además, la ficha debe estar completa al menos al ' . (int) $r['completitud_min'] . ' %.';
            if (!empty($r['requiere'])) $t .= ' Y haber sido verificada por una persona de nuestro equipo.';
            return $t;
        case 'popularidad':
            $dias = (int) ($r['dias'] ?? 90);
            [$p1, $p2] = $r['percentiles'] ?? [50, 80];
            return "Se mide cuánta gente contacta con el negocio desde nuestra web en los últimos {$dias} días (llamadas, WhatsApp, mapa y web) y se compara con los demás negocios de su provincia. "
                 . 'Popular: está por encima del ' . (int) $p1 . ' % de su provincia. Muy popular: por encima del ' . (int) $p2 . ' %. Más popular: es el que más contactos recibe. '
                 . 'Hacen falta un mínimo de ' . (int) ($r['min_contactos'] ?? 3) . ' contactos para evitar resultados por casualidad.';
        case 'resena_reciente':
            return 'Se concede automáticamente cuando el negocio recibe una opinión de ' . (int) ($r['min_calificacion'] ?? 4)
                 . ' estrellas o más en nuestro portal, y dura ' . (int) ($r['dias'] ?? 30) . ' días desde ese momento. Mientras la tiene, también aparece mejor posicionado.';
    }
    return '';
}

$titulo_pagina    = 'Qué significan las insignias de los crematorios - Crematorios de Mascotas';
$meta_descripcion = 'Guía sencilla de las insignias de nuestro directorio de crematorios de mascotas: qué significa cada una, cómo se consigue y cómo influye en el orden de los resultados.';
$pagina_actual    = 'insignias';
include 'includes/header.php';
?>

<style>
    .ins-glosario { max-width: 860px; margin: 0 auto; }
    .ins-glosario__intro { color: var(--color-seis); line-height: 1.7; font-size: 1rem; margin-bottom: var(--espacio-cuatro); }
    .ins-toggle-todas { background: none; border: 0; color: var(--color-uno); font: inherit; font-size: .9rem; font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0; }

    /* Acordeón: una <details> por insignia */
    .ins-item {
        background: #fff; border: 1px solid var(--color-cinco, #e7e0d8); border-radius: var(--radio-dos);
        margin-bottom: var(--espacio-tres); scroll-margin-top: 110px; /* el header es fijo */
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ins-item[open] { border-color: var(--insignia-color); }
    .ins-item.ins-item--destacada { box-shadow: 0 0 0 3px color-mix(in srgb, var(--insignia-color) 22%, white); }
    .ins-item__cabecera {
        display: flex; align-items: center; gap: var(--espacio-tres);
        padding: var(--espacio-tres) var(--espacio-cuatro); cursor: pointer; list-style: none;
    }
    .ins-item__cabecera::-webkit-details-marker { display: none; }
    .ins-item__cabecera:focus-visible { outline: 2px solid var(--insignia-color); outline-offset: 2px; border-radius: var(--radio-dos); }
    .ins-item .insignia { min-width: 44px; height: 44px; cursor: inherit; flex-shrink: 0; }
    .ins-item .insignia .icono { width: 24px; height: 24px; }
    .ins-item__titulos { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
    .ins-item__nombre { font-size: 1.1rem; font-weight: 600; color: var(--color-dos); }
    .ins-item__resumen { font-size: .88rem; color: var(--color-seis); line-height: 1.4; }
    .ins-item[open] .ins-item__resumen { display: none; } /* abierta: el cuerpo ya lo explica */
    .ins-item__chevron { width: 22px; height: 22px; color: var(--color-seis); flex-shrink: 0; transition: transform .2s ease; }
    .ins-item[open] .ins-item__chevron { transform: rotate(180deg); }
    .ins-item__cuerpo { padding: 0 var(--espacio-cuatro) var(--espacio-cuatro) calc(var(--espacio-cuatro) + 44px + var(--espacio-tres)); }
    .ins-item__cuerpo p { color: var(--color-seis); line-height: 1.65; margin: 0 0 10px; font-size: .95rem; }
    .ins-item__como { background: var(--color-cuatro); border-radius: var(--radio-uno); padding: 10px 14px; font-size: .9rem; color: var(--color-seis); line-height: 1.55; }
    .ins-item__como strong { color: var(--color-dos); }
    .ins-item__niveles { margin: 0 0 10px; padding-left: 1.1rem; color: var(--color-seis); font-size: .9rem; line-height: 1.6; }

    /* CTA intercalado: habla a las familias */
    .ins-cta {
        display: flex; align-items: center; gap: var(--espacio-tres); flex-wrap: wrap;
        background: var(--color-uno); color: var(--color-ocho); border-radius: var(--radio-dos);
        padding: var(--espacio-cuatro); margin: var(--espacio-cuatro) 0;
    }
    .ins-cta__icono { width: 48px; height: 48px; border-radius: 50%; background: var(--color-ocho); color: var(--color-dos); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ins-cta__icono .icono { width: 24px; height: 24px; }
    .ins-cta__texto { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 2px; line-height: 1.5; }
    .ins-cta__texto strong { font-size: 1.05rem; }
    .ins-cta__texto span { font-size: .92rem; opacity: .92; }
    .ins-cta .boton { background: var(--color-ocho); color: var(--color-dos); border-color: var(--color-ocho); }

    /* Variante WhatsApp: tarjeta centrada y apilada, verde suave (distinta del CTA terracota) */
    .ins-cta--wa {
        flex-direction: column; text-align: center; gap: var(--espacio-three, 14px);
        background: #ecf8f1; color: #14532d; border: 2px solid #86d3a8; padding: var(--espacio-cinco, 2rem) var(--espacio-cuatro);
    }
    .ins-cta--wa .ins-cta__icono { width: 64px; height: 64px; background: #25a55f; color: #fff; box-shadow: 0 0 0 6px #d3f0e0; }
    .ins-cta--wa .ins-cta__icono .icono { width: 32px; height: 32px; }
    .ins-cta--wa .ins-cta__texto { flex: none; min-width: 0; max-width: 520px; align-items: center; gap: 6px; }
    .ins-cta--wa .ins-cta__texto strong { font-size: 1.35rem; color: #14532d; }
    .ins-cta--wa .ins-cta__texto span { font-size: 1rem; opacity: 1; color: #1f6b3e; }
    .ins-cta--wa .boton { background: #25a55f; border-color: #25a55f; color: #fff; display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; font-size: 1.05rem; border-radius: 999px; box-shadow: 0 4px 14px rgba(37,165,95,.35); }
    .ins-cta--wa .boton .icono { width: 20px; height: 20px; }
    .ins-cta--wa .boton:hover { background: #1d8a4e; border-color: #1d8a4e; }

    .ins-ranking { background: var(--color-cuatro); border-radius: var(--radio-dos); padding: var(--espacio-cuatro); margin-top: var(--espacio-cuatro); }
    .ins-ranking h2 { font-size: 1.2rem; color: var(--color-dos); margin: 0 0 8px; }
    .ins-ranking p { color: var(--color-seis); line-height: 1.7; margin: 0 0 8px; font-size: .95rem; }

    /* Cierre: mantiene a la familia navegando */
    .ins-cierre { margin-top: var(--espacio-cinco); text-align: center; }
    .ins-cierre h2 { font-size: 1.4rem; color: var(--color-dos); margin: 0 0 6px; }
    .ins-cierre > p { color: var(--color-seis); margin: 0 0 var(--espacio-cuatro); }
    .ins-cierre__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--espacio-tres); text-align: left; }
    .ins-cierre__card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid var(--color-cinco, #e7e0d8); border-radius: var(--radio-dos); padding: var(--espacio-cuatro); text-decoration: none; transition: border-color .15s ease, transform .15s ease; }
    .ins-cierre__card:hover { border-color: var(--color-uno); transform: translateY(-2px); }
    .ins-cierre__card .icono { width: 26px; height: 26px; color: var(--color-uno); }
    .ins-cierre__card strong { color: var(--color-dos); font-size: 1.05rem; }
    .ins-cierre__card span { color: var(--color-seis); font-size: .9rem; line-height: 1.5; }
    .ins-cierre__card .ins-cierre__boton {
        margin-top: auto; align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
        background: var(--color-uno); color: var(--color-ocho); font-size: .92rem; font-weight: 600; line-height: 1;
        padding: 10px 16px; border-radius: var(--radio-full, 999px); transition: background .15s ease, gap .15s ease;
    }
    .ins-cierre__card .ins-cierre__boton .icono { width: 16px; height: 16px; color: inherit; }
    .ins-cierre__card:hover .ins-cierre__boton { background: var(--color-dos); gap: 12px; }
    .ins-cierre__card span:not(.ins-cierre__boton) { margin-bottom: 10px; }

    @media (max-width: 720px) {
        .ins-cierre__grid { grid-template-columns: 1fr; }
        .ins-item__cuerpo { padding-left: var(--espacio-cuatro); }
    }
</style>

<section class="seccion">
    <div class="contenedor">
        <div class="ins-glosario">
            <?php
            $migas = [['Inicio', BASE_URL . '/'], ['Insignias', null]];
            include ROOT_PATH . '/includes/componentes/breadcrumb.php';
            ?>
            <h1>Qué significan las insignias</h1>
            <p class="ins-glosario__intro">
                En cada ficha verás pequeños iconos de colores. Son <strong>insignias</strong>: señales rápidas que te ayudan a comparar crematorios.
                Algunas las concede nuestro equipo tras comprobarlas personalmente; otras se calculan solas a partir de los datos de la ficha y de las opiniones.
                Toca cada una para ver qué significa y cómo se consigue.
            </p>

            <p style="margin:0 0 var(--espacio-tres); text-align:right;">
                <button type="button" class="ins-toggle-todas" id="ins-toggle-todas" aria-pressed="false">Abrir todas</button>
            </p>

            <?php
            // Texto del bloque CTA que se intercala cada 4 insignias (habla a las familias, no a los negocios)
            $ctas = [
                ['icono' => 'message-circle-heart', 'titulo' => '¿Aún no sabes cuál elegir?',
                 'texto' => 'Envíanos un mensaje por WhatsApp y te ayudaremos sin coste alguno.',
                 'boton' => 'Hablar con el Asistente', 'wa' => true],
                ['icono' => 'map-pin', 'titulo' => 'Mira qué hay cerca de ti',
                 'texto' => 'Ahora que sabes interpretar las insignias, encuentra los crematorios más próximos a tu ubicación.',
                 'boton' => 'Ver crematorios cerca de mí', 'url' => BASE_URL . '/cerca.php'],
            ];
            $waTexto = 'Hola, me gustaría recibir ayuda para elegir un crematorio para mi mascota.';
            $waUrl   = 'https://wa.me/' . WHATSAPP_SOPORTE_ES_B2C . '?text=' . rawurlencode($waTexto);
            $nCta = 0;
            foreach ($insignias as $idx => $i):
            ?>
            <details class="ins-item" id="<?php echo htmlspecialchars($i['slug']); ?>" style="--insignia-color: <?php echo htmlspecialchars($i['color']); ?>;">
                <summary class="ins-item__cabecera">
                    <span class="insignia" style="--insignia-color: <?php echo htmlspecialchars($i['color']); ?>;">
                        <i data-lucide="<?php echo htmlspecialchars($i['icono']); ?>" class="icono"></i>
                    </span>
                    <span class="ins-item__titulos">
                        <span class="ins-item__nombre"><?php echo limpiar($i['nombre']); ?></span>
                        <span class="ins-item__resumen"><?php echo limpiar($i['tooltip']); ?></span>
                    </span>
                    <i data-lucide="chevron-down" class="icono ins-item__chevron" aria-hidden="true"></i>
                </summary>
                <div class="ins-item__cuerpo">
                    <p><?php echo limpiar($i['glosario']); ?></p>
                    <?php if (!empty($i['niveles_arr'])): ?>
                    <ul class="ins-item__niveles">
                        <?php foreach ($i['niveles_arr'] as $n): ?>
                        <li><strong><?php echo limpiar($n['nombre'] ?? ''); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="ins-item__como"><strong>Cómo se consigue:</strong> <?php echo limpiar(glosarioComoSeConsigue($i)); ?></div>
                </div>
            </details>

            <?php if (($idx + 1) % 4 === 0 && $idx + 1 < count($insignias)):
                $cta = $ctas[$nCta % count($ctas)]; $nCta++; ?>
            <aside class="ins-cta<?php echo !empty($cta["wa"]) ? " ins-cta--wa" : ""; ?>">
                <span class="ins-cta__icono"><i data-lucide="<?php echo $cta['icono']; ?>" class="icono"></i></span>
                <div class="ins-cta__texto">
                    <strong><?php echo limpiar($cta['titulo']); ?></strong>
                    <span><?php echo limpiar($cta['texto']); ?></span>
                </div>
                <?php if (!empty($cta['wa'])): ?>
                <a href="<?php echo htmlspecialchars($waUrl); ?>" class="boton uno" target="_blank" rel="noopener"
                   data-lead-capture="wa" data-destino="<?php echo htmlspecialchars($waUrl); ?>"
                   data-no-skip="1" data-phone-agent="<?php echo WHATSAPP_SOPORTE_ES_B2C; ?>"><i data-lucide="message-circle" class="icono"></i> <?php echo limpiar($cta["boton"]); ?></a>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($cta['url']); ?>" class="boton uno"><?php echo limpiar($cta['boton']); ?></a>
                <?php endif; ?>
            </aside>
            <?php endif; ?>
            <?php endforeach; ?>

            <section class="ins-ranking" id="ranking">
                <h2>¿Influyen las insignias en el orden de los resultados?</h2>
                <p>
                    Sí, y te lo contamos con transparencia. Cada insignia suma unos puntos, y a esos puntos se añade la calidad de la ficha
                    (su valoración media, sumando Google y las opiniones de nuestro portal, y lo completa que está la información).
                    Los negocios con más puntos aparecen antes en los listados.
                </p>
                <p>
                    Nunca se puede comprar una insignia de calidad: «Verificado por humano», «Destacado» o «Bien valorado» no se pagan.
                    Si algún día un negocio contrata publicidad, se mostrará siempre rotulado como tal.
                </p>
            </section>

            <section class="ins-cierre">
                <h2>¿Y ahora? Sigue explorando</h2>
                <p>Con las insignias ya sabes en qué fijarte. Elige por dónde quieres seguir:</p>
                <div class="ins-cierre__grid">
                    <a class="ins-cierre__card" href="<?php echo BASE_URL; ?>/cerca.php">
                        <i data-lucide="map-pin" class="icono"></i>
                        <strong>Cerca de ti</strong>
                        <span>Te mostramos los crematorios más próximos a tu ubicación, con sus insignias.</span>
                        <span class="ins-cierre__boton">Ver los más cercanos <i data-lucide="arrow-right" class="icono"></i></span>
                    </a>
                    <a class="ins-cierre__card" href="<?php echo BASE_URL; ?>/espana/">
                        <i data-lucide="map" class="icono"></i>
                        <strong>Por provincia</strong>
                        <span>Recorre España por comunidades y provincias y compara con calma.</span>
                        <span class="ins-cierre__boton">Explorar provincias <i data-lucide="arrow-right" class="icono"></i></span>
                    </a>
                    <a class="ins-cierre__card" href="<?php echo BASE_URL; ?>/directorio.php">
                        <i data-lucide="list" class="icono"></i>
                        <strong>Todo el directorio</strong>
                        <span>Filtra por servicios, valoración o si atienden las 24 horas.</span>
                        <span class="ins-cierre__boton">Ir al directorio <i data-lucide="arrow-right" class="icono"></i></span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</section>

<?php
// Cierre editorial: los mejores artículos del blog (destacados primero, después los más recientes).
// Mismo partial que la home; no pinta nada si todavía no hay artículos publicados.
require_once ROOT_PATH . '/includes/blog.php';
$blogArticulos        = blogArticulosHome(3);
$blogSeccionTitulo    = 'Guías para acompañarte';
$blogSeccionSubtitulo = 'Artículos que te ayudan a decidir con calma y a despedirte de tu mascota';
$blogSeccionArena     = true;
include ROOT_PATH . '/includes/componentes/blog-relacionados.php';

// Última salida antes del footer: nube de ciudades (enlazado interno + atajo para quien ya sabe dónde busca)
$nubeScope  = 'todas';
$nubeTitulo = 'Encuentra un crematorio en tu ciudad';
$nubeLimite = 30;
include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
?>

<script>
(function () {
    var items = Array.prototype.slice.call(document.querySelectorAll('details.ins-item'));
    var btn = document.getElementById('ins-toggle-todas');

    // Abre (y resalta) solo la insignia del ancla; el resto queda cerrado
    function abrirDesdeAncla() {
        var id = decodeURIComponent((location.hash || '').replace('#', ''));
        if (!id) return;
        var el = document.getElementById(id);
        if (!el || !el.classList.contains('ins-item')) return;
        items.forEach(function (d) { d.classList.remove('ins-item--destacada'); });
        el.open = true;
        el.classList.add('ins-item--destacada');
        setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
    }
    abrirDesdeAncla();
    window.addEventListener('hashchange', abrirDesdeAncla);

    // «Abrir todas» / «Cerrar todas»
    if (btn) btn.addEventListener('click', function () {
        var abrir = btn.getAttribute('aria-pressed') !== 'true';
        items.forEach(function (d) { d.open = abrir; });
        btn.setAttribute('aria-pressed', abrir ? 'true' : 'false');
        btn.textContent = abrir ? 'Cerrar todas' : 'Abrir todas';
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
