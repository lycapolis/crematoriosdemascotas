<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BÚSQUEDA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 02
 * Fecha: Enero 2026
 *
 * Página de resultados de búsqueda
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Resultados de búsqueda';
$pagina_actual = '';
include 'includes/header.php';

// Obtener término de búsqueda
$termino = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : 'Madrid';
?>

    <!-- ═══════════════════════════════════════════════════════════
         HERO BÚSQUEDA
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero" style="background: var(--color-cuatro); padding: var(--espacio-seis) var(--espacio-cinco);">
        <div class="contenedor" style="max-width: var(--contenedor-tres);">

            <h1 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">Buscar crematorios</h1>

            <form class="buscador" action="<?php echo $base_url; ?>/buscador.php" method="get" style="background: var(--color-blanco); padding: var(--espacio-dos); border-radius: var(--radio-dos); border: 1px solid var(--color-cinco);">
                <input
                    type="search"
                    name="q"
                    class="campo"
                    placeholder="Buscar por nombre, ciudad o servicio..."
                    id="termino-busqueda"
                    value="<?php echo $termino; ?>"
                    style="flex: 1; padding: var(--espacio-tres) var(--espacio-cuatro);"
                >
                <button type="submit" class="boton uno" style="padding: var(--espacio-tres) var(--espacio-seis);">
                    <i data-lucide="search" class="icono"></i>
                    Buscar
                </button>
            </form>

        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         RESULTADOS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">

            <!-- Info de resultados -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-cinco); padding-bottom: var(--espacio-cuatro); border-bottom: 1px solid var(--color-cinco);">
                <div style="font-size: var(--fs-dos); color: var(--color-seis);">
                    <strong style="color: var(--color-dos); font-weight: var(--peso-bold);">8</strong> crematorios encontrados para
                    <span style="color: var(--color-uno);">"<?php echo $termino; ?>"</span>
                </div>
                <a href="<?php echo $base_url; ?>/paises/" class="boton dos pequeno">
                    <i data-lucide="x" class="icono"></i>
                    Limpiar búsqueda
                </a>
            </div>

            <!-- Grid de resultados -->
            <div class="grid-tarjetas" style="grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: var(--espacio-seis);">

                <!-- Crematorio 1 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img
                            src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&h=400&fit=crop"
                            alt="Crematorio ejemplo"
                            loading="lazy"
                        >
                        <span class="tarjeta__destacado">Destacado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/funeraria-san-antonio-abad">Funeraria San Antonio Abad</a>
                        </h3>

                        <div class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span>Madrid, Madrid</span>
                        </div>

                        <p class="tarjeta__descripcion">
                            Servicios de cremación profesional con más de 10 años de experiencia.
                            Cremación individual, urnas personalizadas y recogida a domicilio.
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
                            <span style="color: var(--color-seis-claro); font-size: var(--fs-uno);">(12 reseñas)</span>
                        </div>

                        <a href="<?php echo $base_url; ?>/funeraria-san-antonio-abad" class="boton uno pequeno" style="width: 100%; margin-top: var(--espacio-tres);">
                            Ver Crematorio
                        </a>
                    </div>
                </article>

                <!-- Crematorio 2 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img
                            src="https://images.unsplash.com/photo-1415369629372-26f2fe60c467?w=600&h=400&fit=crop"
                            alt="Crematorio ejemplo"
                            loading="lazy"
                        >
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/pets-eternity-crematorio">Pets Eternity Crematorio</a>
                        </h3>

                        <div class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span>San Fernando de Henares, Madrid</span>
                        </div>

                        <p class="tarjeta__descripcion">
                            Centro especializado en cremación de mascotas. Ofrecemos servicios 24 horas,
                            sala de velatorio y atención personalizada.
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
                            <span style="color: var(--color-seis-claro); font-size: var(--fs-uno);">(8 reseñas)</span>
                        </div>

                        <a href="<?php echo $base_url; ?>/pets-eternity-crematorio" class="boton uno pequeno" style="width: 100%; margin-top: var(--espacio-tres);">
                            Ver Crematorio
                        </a>
                    </div>
                </article>

            </div>

            <!-- Sin resultados (oculto por defecto) -->
            <div id="sin-resultados" style="display: none; text-align: center; padding: var(--espacio-seis);">
                <i data-lucide="search-x" class="icono" style="width: 80px; height: 80px; color: var(--color-seis-suave); margin: 0 auto var(--espacio-cuatro); display: block;"></i>
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">No se encontraron resultados</h2>
                <p class="seccion__descripcion" style="margin-bottom: var(--espacio-cinco);">
                    No encontramos crematorios que coincidan con tu búsqueda.
                    Intenta con otros términos o explora nuestro directorio completo.
                </p>
                <a href="<?php echo $base_url; ?>/paises/" class="boton uno">
                    Ver todo el directorio
                </a>
            </div>

        </div>
    </section>

    <!-- Responsive -->
    <style>
        @media (max-width: 768px) {
            .buscador {
                flex-direction: column;
            }
            .grid-tarjetas {
                grid-template-columns: 1fr !important;
            }
            section > div > div:first-child {
                flex-direction: column;
                gap: var(--espacio-tres);
                align-items: flex-start;
            }
        }
    </style>

<?php include 'includes/footer.php'; ?>
