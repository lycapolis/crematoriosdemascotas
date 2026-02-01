<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PAÍSES - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 *
 * Lista todos los países disponibles
 * URL: /paises/
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Crematorios de Mascotas - Directorio Internacional';
$pagina_actual = 'directorio';
include 'includes/header.php';

// Datos de ejemplo (en producción vendrían de la base de datos)
$total_paises = 15;
$total_crematorios = 156;
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
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span>Países</span>
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
                <h1 style="font-size: var(--fs-cuatro); color: var(--color-dos); margin-bottom: var(--espacio-dos);">Directorio Internacional de Crematorios</h1>
                <p class="seccion__descripcion">
                    <?php echo $total_crematorios; ?> crematorios en <?php echo $total_paises; ?> países
                </p>
            </header>

            <!-- Europa -->
            <section style="margin-bottom: var(--espacio-siete);">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-dos); border-bottom: 2px solid var(--color-cinco);">Europa</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--espacio-cuatro);">

                    <!-- España -->
                    <a href="<?php echo $base_url; ?>/espana/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">España</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">24 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Portugal -->
                    <a href="<?php echo $base_url; ?>/portugal/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Portugal</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">8 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Francia -->
                    <a href="<?php echo $base_url; ?>/francia/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Francia</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">12 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Italia -->
                    <a href="<?php echo $base_url; ?>/italia/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Italia</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">10 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Andorra -->
                    <a href="<?php echo $base_url; ?>/andorra/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Andorra</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">1 crematorio</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                </div>
            </section>

            <!-- Latinoamérica -->
            <section style="margin-bottom: var(--espacio-siete);">
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-dos); border-bottom: 2px solid var(--color-cinco);">Latinoamérica</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--espacio-cuatro);">

                    <!-- México -->
                    <a href="<?php echo $base_url; ?>/mexico/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">México</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">18 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Argentina -->
                    <a href="<?php echo $base_url; ?>/argentina/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Argentina</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">15 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Colombia -->
                    <a href="<?php echo $base_url; ?>/colombia/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Colombia</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">12 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Chile -->
                    <a href="<?php echo $base_url; ?>/chile/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Chile</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">9 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Perú -->
                    <a href="<?php echo $base_url; ?>/peru/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Perú</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">7 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Ecuador -->
                    <a href="<?php echo $base_url; ?>/ecuador/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Ecuador</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">5 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Venezuela -->
                    <a href="<?php echo $base_url; ?>/venezuela/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Venezuela</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">6 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Uruguay -->
                    <a href="<?php echo $base_url; ?>/uruguay/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Uruguay</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">4 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Brasil -->
                    <a href="<?php echo $base_url; ?>/brasil/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Brasil</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">22 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                    <!-- Costa Rica -->
                    <a href="<?php echo $base_url; ?>/costa-rica/" class="item-tres" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">Costa Rica</h3>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">3 crematorios</span>
                        </div>
                        <i data-lucide="chevron-right" class="icono" style="color: var(--color-uno); width: 24px; height: 24px;"></i>
                    </a>

                </div>
            </section>

            <!-- Información SEO -->
            <section style="background: var(--color-cinco); padding: var(--espacio-seis); border-radius: var(--radio-dos);">
                <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de cremación de mascotas en el mundo</h2>
                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-tres);">
                    En nuestro directorio internacional encontrarás los mejores crematorios de mascotas en Europa y Latinoamérica.
                    Todos los servicios listados ofrecen un trato digno y respetuoso para tu compañero fiel.
                </p>
                <p style="color: var(--color-seis); line-height: 1.7; margin: 0;">
                    Selecciona tu país para ver las provincias y ciudades con crematorios disponibles en tu zona.
                </p>
            </section>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>
