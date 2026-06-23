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
                <h2>Una necesidad real</h2>
                <p>Este proyecto nació de una experiencia personal. Cuando perdimos a nuestra mascota, nos dimos cuenta de lo difícil que era encontrar información confiable sobre servicios de cremación.</p>
                <p>Buscamos en internet, preguntamos a conocidos, y el proceso fue abrumador en un momento ya de por sí difícil. Fue entonces cuando decidimos crear este directorio.</p>
                <p>Nuestro objetivo es simple: que ninguna familia tenga que pasar por esa incertidumbre. Queremos que encontrar un crematorio de confianza sea fácil, rápido y transparente.</p>
            </div>
            <div class="nos-historia__imagen">
                <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&h=500&fit=crop"
                     alt="Mascota descansando" loading="lazy">
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

<?php include 'includes/footer.php'; ?>
