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
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Lista crematorios de una ciudad desde la base de datos
 * URL: /espana/madrid/getafe/
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración y funciones
require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// Obtener parámetros
$pais_slug = isset($_GET['pais']) ? $_GET['pais'] : 'espana';
$provincia_slug = isset($_GET['provincia']) ? trim($_GET['provincia']) : '';
$ciudad_slug = isset($_GET['ciudad']) ? trim($_GET['ciudad']) : '';

// Validar provincia
$provincia = obtenerProvinciaSlug($provincia_slug);
if (!$provincia) {
    http_response_code(404);
    $titulo_pagina = 'Ciudad no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudad no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">La ciudad que buscas no existe o no está disponible.</p>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton uno">Ver todas las provincias</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// Obtener crematorios de la ciudad
$crematorios = obtenerCrematoriosCiudad($ciudad_slug, $provincia_slug);

// Si no hay crematorios, mostrar 404
if (empty($crematorios)) {
    http_response_code(404);
    $titulo_pagina = 'Ciudad no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudad no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">No hay crematorios registrados en esta ciudad.</p>
            <a href="<?php echo generarUrl('provincia', $provincia_slug); ?>" class="boton uno">Ver ciudades en <?php echo limpiar($provincia['nombre']); ?></a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// Extraer datos del primer crematorio
$pais_nombre = 'España';
$ciudad_nombre = $crematorios[0]['ciudad'];
$provincia_nombre = $crematorios[0]['provincia_nombre'];
$comunidad_nombre = $crematorios[0]['comunidad_nombre'] ?? '';
$comunidad_slug = $crematorios[0]['comunidad_slug'] ?? '';
$total_crematorios = count($crematorios);

$titulo_pagina = 'Crematorios de Mascotas en ' . $ciudad_nombre . ', ' . $provincia_nombre;
$pagina_actual = 'directorio';
include 'includes/header.php';
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
                    <a href="<?php echo $base_url; ?>/espana/" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($pais_nombre); ?></a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <?php if ($comunidad_nombre && $comunidad_nombre !== $provincia_nombre): ?>
                <!-- Comunidad autónoma (con link) -->
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo generarUrl('comunidad', $comunidad_slug); ?>" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($comunidad_nombre); ?></a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <?php endif; ?>
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo generarUrl('provincia', $provincia_slug); ?>" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($provincia_nombre); ?></a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo limpiar($ciudad_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor" style="text-align: center;">
            <h1>Crematorios de Mascotas en <?php echo limpiar($ciudad_nombre); ?></h1>
            <p class="seccion__descripcion">
                Provincia de <?php echo limpiar($provincia_nombre); ?>, <?php echo limpiar($pais_nombre); ?>
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
                <p style="margin: 0; color: var(--color-seis); font-size: var(--fs-uno);"><?php echo $total_crematorios; ?> crematorio<?php echo $total_crematorios != 1 ? 's' : ''; ?> encontrado<?php echo $total_crematorios != 1 ? 's' : ''; ?></p>
            </div>

            <!-- Grid de crematorios -->
            <div class="grid-tarjetas">
                <?php foreach ($crematorios as $crem): ?>
                    <?php include ROOT_PATH . '/includes/componentes/tarjeta-crematorio.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO SEO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div style="max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de Cremación de Mascotas en <?php echo limpiar($ciudad_nombre); ?></h2>

                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-cuatro);">
                    Encuentra los mejores crematorios de mascotas en <?php echo limpiar($ciudad_nombre); ?>, <?php echo limpiar($provincia_nombre); ?>.
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

<?php include 'includes/footer.php'; ?>
