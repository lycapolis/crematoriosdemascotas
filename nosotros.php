<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SOBRE NOSOTROS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 * Página institucional: historia, valores, misión/visión/compromiso.
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Sobre Nosotros - Crematorios de Mascotas';
$pagina_actual = 'nosotros';
include 'includes/header.php';
?>

<style>
    /* ─── Página Nosotros ──────────────────────────────────── */
    /* Padding vertical uniforme en las 3 secciones (override del global). */
    body .seccion {
        padding-top: 30px;
        padding-bottom: 30px;
    }

    .nos-hero__kicker {
        display: inline-block;
        font-size: .75rem;
        color: var(--color-uno);
        text-transform: uppercase;
        letter-spacing: .12em;
        font-weight: 600;
        margin-bottom: var(--espacio-dos);
    }

    /* Grid de pilares (misión/visión/compromiso) — fondo marrón oscuro
       sobre crema, contrasta con las cards de valores (que son blancas). */
    .nos-pilares {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--espacio-tres);
    }
    @media (max-width: 900px) { .nos-pilares { grid-template-columns: 1fr; } }

    .nos-pilar {
        background: var(--color-uno);
        border: 1px solid var(--color-uno);
        border-radius: var(--radio-dos);
        padding: var(--espacio-cuatro);
        text-align: center;
        color: var(--color-ocho);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .nos-pilar:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(184, 112, 79, .35);
    }
    .nos-pilar__icono {
        width: 56px; height: 56px;
        border-radius: 50%;
        background: var(--color-ocho);
        color: var(--color-dos);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: var(--espacio-tres);
    }
    .nos-pilar__icono .icono { width: 26px; height: 26px; }
    .nos-pilar h3 {
        font-size: 1.1rem;
        color: var(--color-ocho);
        margin: 0 0 var(--espacio-dos);
        font-weight: 600;
    }
    .nos-pilar p {
        color: var(--color-ocho);
        opacity: .9;
        line-height: 1.6;
        margin: 0;
        font-size: .95rem;
    }

    /* Bloque historia (texto + imagen) — primera sección, agrandada */
    .nos-historia {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--espacio-cinco);
        align-items: center;
    }
    @media (max-width: 900px) {
        .nos-historia { grid-template-columns: 1fr; gap: var(--espacio-cuatro); }
    }
    .nos-historia__kicker {
        display: inline-block;
        font-size: .9rem;
        color: var(--color-uno);
        text-transform: uppercase;
        letter-spacing: .14em;
        font-weight: 700;
        margin-bottom: var(--espacio-tres);
    }
    .nos-historia__texto h2 {
        font-family: var(--fuente-titulo);
        font-size: var(--fs-siete);
        color: var(--color-dos);
        margin: 0 0 var(--espacio-cuatro);
        letter-spacing: -.02em;
        line-height: 1.15;
    }
    .nos-historia__texto p {
        color: var(--color-seis);
        line-height: 1.75;
        margin: 0 0 var(--espacio-tres);
        font-size: 1.05rem;
    }
    .nos-historia__imagen {
        position: relative;
    }
    .nos-historia__imagen img {
        width: 100%;
        height: auto;
        border-radius: var(--radio-dos);
        display: block;
        box-shadow: 0 6px 24px rgba(90, 62, 47, .12);
    }
    .nos-historia__imagen::before {
        content: "";
        position: absolute;
        inset: 16px -16px -16px 16px;
        border: 2px solid var(--color-uno);
        border-radius: var(--radio-dos);
        z-index: -1;
        opacity: .35;
    }

    /* Valores numerados */
    .nos-valores-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--espacio-cuatro);
        max-width: 880px;
        margin: 0 auto;
    }
    @media (max-width: 720px) { .nos-valores-grid { grid-template-columns: 1fr; } }

    .nos-valor {
        display: flex;
        gap: var(--espacio-tres);
        padding: var(--espacio-cuatro);
        background: #fff;
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-dos);
        align-items: center;
        transition: border-color .15s ease;
    }
    .nos-valor:hover { border-color: var(--color-uno); }
    .nos-valor__icono {
        width: 48px; height: 48px;
        border-radius: 50%;
        background: var(--color-uno-claro, rgba(184, 112, 79, .12));
        color: var(--color-uno);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .nos-valor__icono .icono { width: 22px; height: 22px; }
    .nos-valor h3 {
        font-size: 1.1rem;
        color: var(--color-dos);
        margin: 0 0 6px;
        font-weight: 600;
    }
    .nos-valor p {
        color: var(--color-seis);
        line-height: 1.6;
        margin: 0;
        font-size: .95rem;
    }

    /* Sección con fondo crema */
    .seccion--crema {
        background: var(--color-cuatro);
    }
</style>

<!-- ═══ 1. POR QUÉ EXISTIMOS (historia + imagen) ═══ -->
<section class="seccion">
    <div class="contenedor">
        <div class="nos-historia">
            <div class="nos-historia__texto">
                <span class="nos-historia__kicker">¿Por qué existimos?</span>
                <h2>Te ayudamos a elegir bien, cuando más importa</h2>
                <p>Perder una mascota es un momento delicado, y tomar decisiones en ese momento no debería ser una carga más. Nuestro trabajo es darte información detallada y verificada sobre cada crematorio, para que elijas con tranquilidad el que mejor se adapte a tu necesidad.</p>
                <p>Puedes explorar el directorio por tu cuenta o escribirnos por WhatsApp: nuestro asistente virtual te guiará y te presentará todas tus opciones, de forma sencilla y útil.</p>
                <p>El servicio no tiene ningún costo para ti: no pedimos registro, no cobramos nada y no recibirás publicidad. Solo información clara cuando la necesitas.</p>
<a href="https://wa.me/<?php echo WHATSAPP_SOPORTE_ES_B2C; ?>?text=Hola%2C+me+gustar%C3%ADa+recibir+ayuda+para+elegir+un+crematorio+para+mi+mascota."
   class="boton uno" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.5rem; margin-top: var(--espacio-tres);"
   data-lead-capture="wa"
   data-destino="https://wa.me/<?php echo WHATSAPP_SOPORTE_ES_B2C; ?>?text=Hola%2C+me+gustar%C3%ADa+recibir+ayuda+para+elegir+un+crematorio+para+mi+mascota."
   data-no-skip="1"
   data-phone-agent="<?php echo WHATSAPP_SOPORTE_ES_B2C; ?>">
    <svg class="icono" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="width:18px;height:18px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"></path></svg>
    Hablar con nosotros por WhatsApp
</a>

            </div>
            <div class="nos-historia__imagen">
                <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&h=500&fit=crop" alt="Mascota descansando" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ═══ 2. NUESTROS VALORES ═══ -->
<section class="seccion seccion--crema">
    <div class="contenedor">
        <div class="seccion__encabezado">
            <span class="nos-hero__kicker">Lo que nos guía</span>
            <h2 class="seccion__titulo">Nuestros Valores</h2>
        </div>

        <div class="nos-valores-grid">
            <article class="nos-valor">
                <span class="nos-valor__icono"><i data-lucide="hand-heart" class="icono"></i></span>
                <div>
                    <h3>Empatía</h3>
                    <p>Entendemos el dolor de perder a una mascota. Cada interacción está guiada por la comprensión y el respeto.</p>
                </div>
            </article>
            <article class="nos-valor">
                <span class="nos-valor__icono"><i data-lucide="eye" class="icono"></i></span>
                <div>
                    <h3>Transparencia</h3>
                    <p>Mostramos información clara y honesta sobre cada crematorio, incluyendo precios, servicios y reseñas reales.</p>
                </div>
            </article>
            <article class="nos-valor">
                <span class="nos-valor__icono"><i data-lucide="badge-check" class="icono"></i></span>
                <div>
                    <h3>Calidad</h3>
                    <p>Solo incluimos crematorios que cumplen con estándares de profesionalismo y servicio de calidad.</p>
                </div>
            </article>
            <article class="nos-valor">
                <span class="nos-valor__icono"><i data-lucide="accessibility" class="icono"></i></span>
                <div>
                    <h3>Accesibilidad</h3>
                    <p>Hacemos que la información sea fácil de encontrar y entender, disponible cuando más se necesita.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ═══ 3. MISIÓN / VISIÓN / COMPROMISO ═══ -->
<section class="seccion">
    <div class="contenedor">
        <div class="nos-pilares">
            <article class="nos-pilar">
                <span class="nos-pilar__icono"><i data-lucide="target" class="icono"></i></span>
                <h3>Nuestra Misión</h3>
                <p>Conectar familias con crematorios de mascotas confiables y profesionales, facilitando el proceso de despedida en momentos difíciles.</p>
            </article>
            <article class="nos-pilar">
                <span class="nos-pilar__icono"><i data-lucide="eye" class="icono"></i></span>
                <h3>Nuestra Visión</h3>
                <p>Ser el directorio de referencia en servicios de cremación para mascotas, reconocido por la calidad y confiabilidad de la información que ofrecemos.</p>
            </article>
            <article class="nos-pilar">
                <span class="nos-pilar__icono"><i data-lucide="heart" class="icono"></i></span>
                <h3>Nuestro Compromiso</h3>
                <p>Tratamos cada caso con la sensibilidad que merece. Sabemos que perder una mascota es perder un miembro de la familia.</p>
            </article>
        </div>
    </div>
</section>

<!-- Bloque eliminado -->

<?php include 'includes/footer.php'; ?>