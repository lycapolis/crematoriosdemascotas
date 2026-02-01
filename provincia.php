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
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Lista las ciudades de una provincia desde la base de datos
 * URL: /espana/madrid/
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración y funciones
require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// Obtener parámetros
$pais_slug = isset($_GET['pais']) ? $_GET['pais'] : 'espana';
$provincia_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Obtener provincia de la base de datos
$provincia = obtenerProvinciaSlug($provincia_slug);

// Si no existe, mostrar 404
if (!$provincia) {
    http_response_code(404);
    $titulo_pagina = 'Provincia no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Provincia no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">La provincia que buscas no existe o no está disponible.</p>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton uno">Ver todas las provincias</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// Variables para facilitar el uso
$pais_nombre = 'España';
$provincia_nombre = $provincia['nombre'];
$comunidad_nombre = $provincia['comunidad_nombre'] ?? '';
$comunidad_slug = $provincia['comunidad_slug'] ?? '';

// Obtener ciudades de la provincia
$ciudades = obtenerCiudadesProvincia($provincia['id']);
$total_ciudades = count($ciudades);
$total_crematorios_ciudades = array_sum(array_column($ciudades, 'total_crematorios'));

// Paginación para crematorios
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

// Obtener crematorios de la provincia con paginación
$crematorios = obtenerCrematorios(['provincia_id' => $provincia['id']], $pagina);
$total_crematorios = $crematorios['total'];

$titulo_pagina = 'Crematorios de Mascotas en ' . $provincia_nombre;
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
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo limpiar($provincia_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor" style="text-align: center;">
            <h1>Crematorios de Mascotas en <?php echo limpiar($provincia_nombre); ?></h1>
            <p class="seccion__descripcion">
                <?php echo $total_crematorios; ?> crematorio<?php echo $total_crematorios != 1 ? 's' : ''; ?> disponible<?php echo $total_crematorios != 1 ? 's' : ''; ?> en <?php echo $total_ciudades; ?> ciudad<?php echo $total_ciudades != 1 ? 'es' : ''; ?>
            </p>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LISTADO DE CIUDADES
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <?php if ($total_ciudades > 0): ?>
            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudades en <?php echo limpiar($provincia_nombre); ?></h2>
                <p class="seccion__descripcion">
                    Selecciona tu ciudad para ver los crematorios disponibles
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--espacio-cuatro);">

                <?php foreach ($ciudades as $ciudad): ?>
                <a href="<?php echo generarUrl('ciudad', $ciudad['slug'], $provincia_slug); ?>" class="item-tres" style="text-decoration: none; display: block;">
                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-uno); margin-bottom: var(--espacio-dos);"><?php echo limpiar($ciudad['nombre']); ?></h3>
                    <p style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno); margin: 0;">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        <?php echo $ciudad['total_crematorios']; ?> crematorio<?php echo $ciudad['total_crematorios'] != 1 ? 's' : ''; ?>
                    </p>
                </a>
                <?php endforeach; ?>

            </div>
            <?php else: ?>
            <!-- Sin ciudades -->
            <div style="text-align: center; padding: var(--espacio-siete) 0;">
                <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">Sin crematorios disponibles</h2>
                <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">
                    Actualmente no hay crematorios registrados en <?php echo limpiar($provincia_nombre); ?>.
                </p>
                <a href="<?php echo $base_url; ?>/espana/" class="boton uno">Ver otras provincias</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LISTADO DE CREMATORIOS
         ═══════════════════════════════════════════════════════════ -->
    <?php if ($total_crematorios > 0): ?>
    <section class="seccion">
        <div class="contenedor">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-cinco); flex-wrap: wrap; gap: var(--espacio-tres);">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin: 0;">
                    Crematorios en <?php echo limpiar($provincia_nombre); ?>
                </h2>
                <p style="color: var(--color-uno); margin: 0; font-size: var(--fs-dos); padding: var(--espacio-dos) var(--espacio-tres); background: var(--color-uno-claro); border-radius: var(--radio-uno);">
                    <?php echo $total_crematorios; ?> crematorio<?php echo $total_crematorios != 1 ? 's' : ''; ?> encontrado<?php echo $total_crematorios != 1 ? 's' : ''; ?>
                </p>
            </div>

            <!-- Grid de crematorios -->
            <div class="grid-tarjetas">
                <?php foreach ($crematorios['datos'] as $crem): ?>
                    <?php include ROOT_PATH . '/includes/componentes/tarjeta-crematorio.php'; ?>
                <?php endforeach; ?>
            </div>

            <!-- Paginación -->
            <?php if ($crematorios['paginas'] > 1): ?>
            <nav style="display: flex; justify-content: center; gap: var(--espacio-dos); margin-top: var(--espacio-seis);">
                <?php for ($i = 1; $i <= $crematorios['paginas']; $i++): ?>
                <a href="?pagina=<?php echo $i; ?>" class="boton <?php echo $i == $pagina ? 'uno' : 'dos'; ?> pequeno">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </nav>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO SEO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div style="max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de Cremación de Mascotas en <?php echo limpiar($provincia_nombre); ?></h2>

                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-cuatro);">
                    Encuentra los mejores crematorios de mascotas en la provincia de <?php echo limpiar($provincia_nombre); ?>.
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
