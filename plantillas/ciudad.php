<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CIUDAD - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 *
 * Lista crematorios de una ciudad
 * URL: /espana/madrid/getafe/
 * Ejemplo: Paracuellos de Jarama, Madrid
 * ═══════════════════════════════════════════════════════════
 */

// Obtener parámetros
$pais_slug = isset($_GET['pais']) ? $_GET['pais'] : 'espana';
$provincia_slug = isset($_GET['provincia']) ? $_GET['provincia'] : 'madrid';
$ciudad_slug = isset($_GET['ciudad']) ? $_GET['ciudad'] : 'paracuellos-de-jarama';

// Datos de ejemplo (en producción vendrían de la base de datos)
$pais_nombre = 'España';
$ciudad_nombre = 'Paracuellos de Jarama';
$provincia_nombre = 'Madrid';
$comunidad_nombre = 'Comunidad de Madrid';
$total_crematorios = 1;

$titulo_pagina = 'Crematorios de Mascotas en ' . $ciudad_nombre . ', ' . $provincia_nombre;
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
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo htmlspecialchars($provincia_nombre); ?></a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo htmlspecialchars($ciudad_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor" style="text-align: center;">
            <h1>Crematorios de Mascotas en <?php echo htmlspecialchars($ciudad_nombre); ?></h1>
            <p class="seccion__descripcion">
                Provincia de <?php echo htmlspecialchars($provincia_nombre); ?>, <?php echo htmlspecialchars($pais_nombre); ?>
            </p>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LISTADO DE CREMATORIOS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">

            <!-- Info resultados -->
            <div style="margin-bottom: var(--espacio-cinco); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                <p style="margin: 0; color: var(--color-seis); font-size: var(--fs-uno);"><?php echo $total_crematorios; ?> crematorio<?php echo $total_crematorios !== 1 ? 's' : ''; ?> encontrado<?php echo $total_crematorios !== 1 ? 's' : ''; ?></p>
            </div>

            <!-- Grid de crematorios -->
            <div class="grid-tarjetas">

                <!-- Crematorio 1 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img
                            src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&h=400&fit=crop"
                            alt="Funeraria San Antonio Abad"
                            loading="lazy"
                        >
                        <span class="tarjeta__destacado">Destacado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/funeraria-san-antonio-abad/">Funeraria San Antonio Abad | El Patrón de las Mascotas</a>
                        </h3>

                        <div class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span>Calle Real 123, <?php echo htmlspecialchars($ciudad_nombre); ?></span>
                        </div>

                        <p class="tarjeta__descripcion">
                            Servicios de cremación profesional y respetuoso. Con más de 10 años de experiencia,
                            ofrecemos cremación individual, urnas personalizadas y recogida a domicilio.
                        </p>

                        <div class="tarjeta__footer">
                            <div class="tarjeta__valoracion">
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <span>5.0</span>
                            </div>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">(12 reseñas)</span>
                        </div>

                        <a href="<?php echo $base_url; ?>/funeraria-san-antonio-abad/" class="boton uno" style="width: 100%; margin-top: var(--espacio-tres);">
                            Ver detalles
                        </a>
                    </div>
                </article>

            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO SEO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div style="max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de Cremación de Mascotas en <?php echo htmlspecialchars($ciudad_nombre); ?></h2>

                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-cuatro);">
                    Encuentra los mejores crematorios de mascotas en <?php echo htmlspecialchars($ciudad_nombre); ?>, <?php echo htmlspecialchars($provincia_nombre); ?>.
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
