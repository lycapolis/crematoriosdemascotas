<?php
/**
 * ═══════════════════════════════════════════════════════════
 * 404 - PÁGINA NO ENCONTRADA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Página no encontrada';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';
?>

<style>
    .err-404 {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--espacio-seis) var(--espacio-cuatro);
    }
    .err-404__inner {
        text-align: center;
        max-width: 580px;
    }
    .err-404__icono {
        width: 96px;
        height: 96px;
        color: var(--color-uno);
        opacity: .18;
        margin-bottom: var(--espacio-cuatro);
    }
    .err-404__num {
        font-family: var(--fuente-titulo);
        font-size: 7rem;
        font-weight: 800;
        color: var(--color-uno);
        line-height: 1;
        margin: 0 0 var(--espacio-tres);
        letter-spacing: -.04em;
    }
    .err-404__titulo {
        font-family: var(--fuente-titulo);
        font-size: var(--fs-cinco);
        color: var(--color-dos);
        margin: 0 0 var(--espacio-tres);
        letter-spacing: -.01em;
    }
    .err-404__texto {
        color: var(--color-seis);
        line-height: 1.65;
        margin: 0 auto var(--espacio-cinco);
        max-width: 460px;
    }
    .err-404__acciones {
        display: flex;
        gap: var(--espacio-tres);
        justify-content: center;
        flex-wrap: wrap;
    }
    @media (max-width: 600px) {
        .err-404__num    { font-size: 5rem; }
        .err-404__icono  { width: 72px; height: 72px; }
        .err-404__acciones .boton { width: 100%; }
    }
</style>

<main class="err-404">
    <div class="err-404__inner">
        <i data-lucide="search-x" class="icono err-404__icono"></i>
        <h1 class="err-404__num">404</h1>
        <h2 class="err-404__titulo">Página no encontrada</h2>
        <p class="err-404__texto">
            Lo sentimos, la página que buscas no existe o ha sido movida.
            Puedes volver al inicio o explorar nuestro directorio de crematorios.
        </p>
        <div class="err-404__acciones">
            <a href="<?php echo BASE_URL; ?>/" class="boton uno">
                <i data-lucide="home" class="icono"></i>
                Ir al inicio
            </a>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton dos">
                <i data-lucide="map" class="icono"></i>
                Ver directorio
            </a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
