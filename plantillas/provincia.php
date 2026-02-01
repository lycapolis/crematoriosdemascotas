<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PROVINCIA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 *
 * Lista las ciudades de una provincia
 * URL: /espana/madrid/
 * Ejemplo: Provincia de Madrid
 * ═══════════════════════════════════════════════════════════
 */

// Obtener parámetros
$pais_slug = isset($_GET['pais']) ? $_GET['pais'] : 'espana';
$provincia_slug = isset($_GET['slug']) ? $_GET['slug'] : 'madrid';

// Datos de ejemplo (en producción vendrían de la base de datos)
$pais_nombre = 'España';
$provincia_nombre = 'Madrid';
$comunidad_nombre = 'Comunidad de Madrid';
$total_crematorios = 8;
$total_ciudades = 4;

$titulo_pagina = 'Crematorios de Mascotas en ' . $provincia_nombre;
$pagina_actual = 'directorio';
include '../includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         BREADCRUMBS
         ═══════════════════════════════════════════════════════════ -->
    <nav class="breadcrumbs" aria-label="Breadcrumb" style="padding: var(--espacio-tres) 0; background: var(--color-cinco);">
        <div class="contenedor">
            <ol style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--espacio-dos); list-style: none; padding: 0; margin: 0; font-size: var(--fs-uno);">
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/" style="color: var(--color-seis-claro); text-decoration: none;">Inicio</a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/paises/" style="color: var(--color-seis-claro); text-decoration: none;">Países</a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo htmlspecialchars($pais_nombre); ?></a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <?php if ($comunidad_nombre !== $provincia_nombre): ?>
                <!-- Comunidad autónoma (solo en breadcrumbs, no en URL) -->
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <span style="color: var(--color-seis-claro);"><?php echo htmlspecialchars($comunidad_nombre); ?></span>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <?php endif; ?>
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo htmlspecialchars($provincia_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor" style="text-align: center;">
            <h1>Crematorios de Mascotas en <?php echo htmlspecialchars($provincia_nombre); ?></h1>
            <p class="seccion__descripcion">
                <?php echo $total_crematorios; ?> crematorios disponibles en <?php echo $total_ciudades; ?> ciudades
            </p>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LISTADO DE CIUDADES
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudades en <?php echo htmlspecialchars($provincia_nombre); ?></h2>
                <p class="seccion__descripcion">
                    Selecciona tu ciudad para ver los crematorios disponibles
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--espacio-cuatro);">

                <!-- Ciudad: Madrid -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/madrid/" class="item-tres" style="text-decoration: none; display: block;">
                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-uno); margin-bottom: var(--espacio-dos);">Madrid</h3>
                    <p style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno); margin: 0;">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        5 crematorios
                    </p>
                </a>

                <!-- Ciudad: Paracuellos de Jarama -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/paracuellos-de-jarama/" class="item-tres" style="text-decoration: none; display: block;">
                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-uno); margin-bottom: var(--espacio-dos);">Paracuellos de Jarama</h3>
                    <p style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno); margin: 0;">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        1 crematorio
                    </p>
                </a>

                <!-- Ciudad: Alcobendas -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/alcobendas/" class="item-tres" style="text-decoration: none; display: block;">
                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-uno); margin-bottom: var(--espacio-dos);">Alcobendas</h3>
                    <p style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno); margin: 0;">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        1 crematorio
                    </p>
                </a>

                <!-- Ciudad: Getafe -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/getafe/" class="item-tres" style="text-decoration: none; display: block;">
                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-uno); margin-bottom: var(--espacio-dos);">Getafe</h3>
                    <p style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno); margin: 0;">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        1 crematorio
                    </p>
                </a>

            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO SEO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div style="max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de Cremación de Mascotas en <?php echo htmlspecialchars($provincia_nombre); ?></h2>

                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-cuatro);">
                    Encuentra los mejores crematorios de mascotas en la provincia de <?php echo htmlspecialchars($provincia_nombre); ?>.
                    Todos los centros en nuestro directorio ofrecen servicios profesionales y respetuosos
                    para despedir a tu compañero fiel con la dignidad que merece.
                </p>

                <p style="color: var(--color-seis); line-height: 1.7; margin: 0;">
                    Los servicios típicos incluyen cremación individual, urnas conmemorativas, recogida
                    a domicilio y asesoramiento durante todo el proceso. Compara reseñas, servicios y
                    contacta directamente con el crematorio que mejor se adapte a tus necesidades.
                </p>
            </div>
        </div>
    </section>

<?php include '../includes/footer.php'; ?>
