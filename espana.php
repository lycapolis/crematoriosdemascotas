<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ESPAÑA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Lista las provincias de España desde la base de datos
 * URL: /espana/
 * Archivo específico (no usa plantilla genérica)
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración y funciones
require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// Datos de España
$pais_slug = 'espana';
$pais_nombre = 'España';

// Obtener provincias desde la base de datos
$provincias = obtenerProvincias();
$total_provincias = count($provincias);
$total_crematorios_provincias = array_sum(array_column($provincias, 'total_crematorios'));

// Paginación para crematorios
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

// Obtener todos los crematorios con paginación
$crematorios = obtenerCrematorios([], $pagina);
$total_crematorios = $crematorios['total'];

$titulo_pagina = 'Crematorios de Mascotas en ' . $pais_nombre;
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
                    <a href="<?php echo $base_url; ?>/paises/" style="color: var(--color-seis-claro); text-decoration: none;">Países</a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo htmlspecialchars($pais_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <main class="seccion">
        <div class="contenedor">

            <!-- Header -->
            <header style="text-align: center; margin-bottom: var(--espacio-siete);">
                <h1 style="font-size: var(--fs-cuatro); color: var(--color-dos); margin-bottom: var(--espacio-dos);">Crematorios de Mascotas en <?php echo htmlspecialchars($pais_nombre); ?></h1>
                <p class="seccion__descripcion">
                    <?php echo $total_crematorios; ?> crematorios en <?php echo $total_provincias; ?> provincias
                </p>
            </header>

            <!-- Grid de provincias -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--espacio-cuatro); margin-bottom: var(--espacio-siete);">

                <?php foreach ($provincias as $prov): ?>
                <a href="<?php echo generarUrl('provincia', $prov['slug']); ?>" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);"><?php echo limpiar($prov['nombre']); ?></h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);"><?php echo $prov['total_crematorios']; ?> crematorio<?php echo $prov['total_crematorios'] != 1 ? 's' : ''; ?></span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>
                <?php endforeach; ?>

            </div>

            <!-- Listado de crematorios -->
            <?php if ($total_crematorios > 0): ?>
            <section style="margin-bottom: var(--espacio-siete);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-cinco); flex-wrap: wrap; gap: var(--espacio-tres);">
                    <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin: 0;">
                        Todos los Crematorios en <?php echo limpiar($pais_nombre); ?>
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
            </section>
            <?php endif; ?>

            <!-- Información SEO -->
            <section style="background: var(--color-cinco); padding: var(--espacio-seis); border-radius: var(--radio-dos);">
                <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de cremación de mascotas en <?php echo htmlspecialchars($pais_nombre); ?></h2>
                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-tres);">
                    En nuestro directorio encontrarás los mejores crematorios de mascotas en toda <?php echo htmlspecialchars($pais_nombre); ?>.
                    Todos los servicios listados ofrecen un trato digno y respetuoso para tu compañero fiel.
                </p>
                <p style="color: var(--color-seis); line-height: 1.7; margin: 0;">
                    Selecciona tu provincia para ver las ciudades con crematorios disponibles en tu zona.
                </p>
            </section>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>
