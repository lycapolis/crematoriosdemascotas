<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PAÍS - PLANTILLA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 01
 * Fecha: Enero 2026
 *
 * Plantilla genérica para listar provincias/regiones de un país
 * Ejemplo con datos de Argentina
 * ═══════════════════════════════════════════════════════════
 */

// Datos de ejemplo (Argentina)
$pais_slug = 'argentina';
$pais_nombre = 'Argentina';
$total_crematorios = 15;
$total_provincias = 24;

$titulo_pagina = 'Crematorios de Mascotas en ' . $pais_nombre;
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

                <!-- Buenos Aires -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/buenos-aires/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Buenos Aires</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">5 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Ciudad Autónoma de Buenos Aires -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/caba/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">CABA</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">3 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Catamarca -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/catamarca/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Catamarca</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Chaco -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/chaco/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Chaco</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Chubut -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/chubut/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Chubut</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Córdoba -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/cordoba/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Córdoba</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">2 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Corrientes -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/corrientes/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Corrientes</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Entre Ríos -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/entre-rios/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Entre Ríos</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">1 crematorio</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Formosa -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/formosa/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Formosa</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Jujuy -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/jujuy/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Jujuy</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- La Pampa -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/la-pampa/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">La Pampa</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- La Rioja -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/la-rioja/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">La Rioja</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Mendoza -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/mendoza/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Mendoza</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">1 crematorio</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Misiones -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/misiones/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Misiones</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Neuquén -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/neuquen/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Neuquén</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Río Negro -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/rio-negro/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Río Negro</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Salta -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/salta/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Salta</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">1 crematorio</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- San Juan -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/san-juan/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">San Juan</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- San Luis -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/san-luis/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">San Luis</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Santa Cruz -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/santa-cruz/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Santa Cruz</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Santa Fe -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/santa-fe/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Santa Fe</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">2 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Santiago del Estero -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/santiago-del-estero/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Santiago del Estero</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Tierra del Fuego -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/tierra-del-fuego/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Tierra del Fuego</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

                <!-- Tucumán -->
                <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/tucuman/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Tucumán</h2>
                        <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">0 crematorios</span>
                    </div>
                    <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                </a>

            </div>

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

<?php include '../includes/footer.php'; ?>
