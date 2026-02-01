<?php
/**
 * ═══════════════════════════════════════════════════════════
 * COMUNIDAD - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 01
 * Fecha: Enero 2026
 *
 * Basado en: comunidad.php del sitio actual
 * Ejemplo: Comunidad de Madrid con sus provincias
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Crematorios de Mascotas en Comunidad de Madrid';
$pagina_actual = 'directorio';
include '../includes/header.php';
?>

    <!-- Estilos específicos de esta página de ejemplo -->
    <style>
        /* ═══════════════════════════════════════════════════════════
           ESTILOS ESPECÍFICOS - COMUNIDAD (solo para documentación)
           ═══════════════════════════════════════════════════════════ */

        /* Breadcrumbs */
        .breadcrumbs {
            padding: var(--espacio-tres) var(--espacio-cinco);
            background: var(--color-cinco);
        }

        .breadcrumbs__lista {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--espacio-dos);
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: var(--fs-uno);
            max-width: var(--contenedor-cuatro);
            margin: 0 auto;
        }

        .breadcrumbs__item {
            display: flex;
            align-items: center;
            color: var(--color-seis-claro);
        }

        .breadcrumbs__item a {
            color: var(--color-seis-claro);
            text-decoration: none;
        }

        .breadcrumbs__item a:hover {
            color: var(--color-uno);
        }

        .breadcrumbs__item .icono {
            width: 14px;
            height: 14px;
            margin: 0 var(--espacio-uno);
        }

        .breadcrumbs__item--activo {
            color: var(--color-seis);
            font-weight: var(--peso-medio);
        }

        /* Página geo */
        .pagina-geo {
            padding: var(--espacio-seis) var(--espacio-cinco);
        }

        .pagina-geo__header {
            text-align: center;
            margin-bottom: var(--espacio-siete);
        }

        .pagina-geo__titulo {
            font-size: var(--fs-tres);
            color: var(--color-dos);
            margin-bottom: var(--espacio-dos);
        }

        .pagina-geo__subtitulo {
            font-size: var(--fs-dos);
            color: var(--color-seis-claro);
        }

        /* Grid de regiones (provincias) */
        .grid-regiones {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--espacio-cuatro);
            margin-bottom: var(--espacio-siete);
        }

        .tarjeta-region {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--espacio-cuatro);
            background: var(--color-ocho);
            border: 1px solid var(--color-cinco);
            border-radius: var(--radio-dos);
            text-decoration: none;
            transition: all var(--transicion);
        }

        .tarjeta-region:hover {
            border-color: var(--color-uno);
            transform: translateY(-2px);
        }

        .tarjeta-region__contenido {
            flex: 1;
        }

        .tarjeta-region__nombre {
            font-size: var(--fs-dos);
            font-weight: var(--peso-negrita);
            color: var(--color-dos);
            margin-bottom: var(--espacio-uno);
        }

        .tarjeta-region__conteo {
            font-size: var(--fs-uno);
            color: var(--color-seis-claro);
        }

        .tarjeta-region__flecha {
            color: var(--color-uno);
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        /* Info SEO */
        .pagina-geo__info {
            background: var(--color-cinco);
            padding: var(--espacio-seis);
            border-radius: var(--radio-dos);
        }

        .pagina-geo__info h2 {
            font-size: var(--fs-dos);
            color: var(--color-dos);
            margin-bottom: var(--espacio-cuatro);
        }

        .pagina-geo__info p {
            color: var(--color-seis);
            line-height: 1.7;
            margin-bottom: var(--espacio-tres);
        }

        .pagina-geo__info p:last-child {
            margin-bottom: 0;
        }
    </style>

    <!-- ═══════════════════════════════════════════════════════════
         BREADCRUMBS
         ═══════════════════════════════════════════════════════════ -->
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ul class="breadcrumbs__lista">
            <li class="breadcrumbs__item">
                <a href="<?php echo $base_url; ?>/">Inicio</a>
                <i data-lucide="chevron-right" class="icono"></i>
            </li>
            <li class="breadcrumbs__item">
                <a href="<?php echo $base_url; ?>/espana.php">España</a>
                <i data-lucide="chevron-right" class="icono"></i>
            </li>
            <li class="breadcrumbs__item breadcrumbs__item--activo">
                <span>Comunidad de Madrid</span>
            </li>
        </ul>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <main class="pagina-geo">
        <div class="contenedor">

            <!-- Header -->
            <header class="pagina-geo__header">
                <h1 class="pagina-geo__titulo">Crematorios de Mascotas en Comunidad de Madrid</h1>
                <p class="pagina-geo__subtitulo">
                    8 crematorios en 1 provincia
                </p>
            </header>

            <!-- Grid de provincias -->
            <div class="grid-regiones">

                <!-- Provincia 1 -->
                <a href="<?php echo $base_url; ?>/espana/madrid/" class="tarjeta-region">
                    <div class="tarjeta-region__contenido">
                        <h2 class="tarjeta-region__nombre">Madrid</h2>
                        <span class="tarjeta-region__conteo">8 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="tarjeta-region__flecha"></i>
                </a>

            </div>

            <!-- Información SEO -->
            <section class="pagina-geo__info">
                <h2>Crematorios de mascotas en Comunidad de Madrid</h2>
                <p>
                    Encuentra el crematorio ideal para despedir a tu mascota en Comunidad de Madrid.
                    Todos los servicios de nuestro directorio ofrecen atención profesional y respetuosa.
                </p>
                <p>
                    Selecciona la provincia donde necesitas el servicio para ver los crematorios disponibles.
                </p>
            </section>

        </div>
    </main>

<?php include '../includes/footer.php'; ?>
