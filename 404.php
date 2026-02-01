<?php
/**
 * ═══════════════════════════════════════════════════════════
 * 404 - PÁGINA NO ENCONTRADA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 02
 * Fecha: Enero 2026
 *
 * Página de error 404
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Página no encontrada';
$pagina_actual = '';
$meta_robots = 'noindex, nofollow';
include 'includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO ERROR 404
         ═══════════════════════════════════════════════════════════ -->
    <main class="seccion" style="min-height: 70vh; display: flex; align-items: center; justify-content: center;">
        <div class="contenedor" style="text-align: center; max-width: 600px;">

            <i data-lucide="search-x" class="icono" style="width: 120px; height: 120px; color: var(--color-uno); opacity: 0.2; margin-bottom: var(--espacio-cinco);"></i>

            <h1 style="font-size: 8rem; font-weight: var(--peso-black); color: var(--color-uno); line-height: 1; margin-bottom: var(--espacio-cuatro);">404</h1>

            <h2 style="margin-bottom: var(--espacio-tres);">Página no encontrada</h2>

            <p class="seccion__descripcion" style="margin-bottom: var(--espacio-seis);">
                Lo sentimos, la página que buscas no existe o ha sido movida.
                Puedes volver al inicio o explorar nuestro directorio de crematorios.
            </p>

            <div style="display: flex; gap: var(--espacio-tres); justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo $base_url; ?>/" class="boton uno">
                    <i data-lucide="home" class="icono"></i>
                    Ir al inicio
                </a>
                <a href="<?php echo $base_url; ?>/paises/" class="boton dos">
                    <i data-lucide="map" class="icono"></i>
                    Ver directorio
                </a>
            </div>

        </div>
    </main>

    <!-- Responsive -->
    <style>
        @media (max-width: 768px) {
            main h1 { font-size: 5rem !important; }
            main > div > div:last-child {
                flex-direction: column;
            }
            main > div > div:last-child .boton {
                width: 100%;
            }
        }
    </style>

<?php include 'includes/footer.php'; ?>
