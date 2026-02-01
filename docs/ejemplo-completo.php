<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PÁGINA COMPLETA DE EJEMPLO
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Integración completa: Header + Hero + Tarjetas + Footer
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Crematorios de Mascotas - Directorio España';
$pagina_actual = 'inicio';
include '../includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         HERO - Sección Principal
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero">
        <div class="contenedor" style="max-width: var(--contenedor-dos); text-align: center;">
            <h1 style="font-size: var(--fs-cuatro); margin-bottom: var(--espacio-cuatro); color: var(--color-blanco);">Encuentra el Mejor Crematorio para tu Mascota</h1>
            <p style="font-size: var(--fs-tres); margin-bottom: var(--espacio-cinco); opacity: 0.9;">Servicios verificados en toda España. Disponibilidad 24 horas.</p>

            <form style="background: var(--color-blanco); padding: var(--espacio-cuatro); border-radius: var(--radio-dos); border: 1px solid var(--color-cinco); display: flex; flex-direction: column; gap: var(--espacio-tres);">
                <div style="display: flex; gap: var(--espacio-tres); flex-wrap: wrap;">
                    <input type="text" class="campo" placeholder="¿Dónde buscas? (Madrid, Barcelona...)" style="flex: 1; min-width: 200px;">

                    <select class="seleccion" style="flex: 1; min-width: 200px;">
                        <option value="">Tipo de servicio</option>
                        <option value="individual">Cremación Individual</option>
                        <option value="colectiva">Cremación Colectiva</option>
                        <option value="urgencia">Servicio 24h</option>
                    </select>
                </div>

                <button type="submit" class="boton uno grande">
                    <i data-lucide="search" class="icono"></i>
                    Buscar Crematorio
                </button>
            </form>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CARACTERÍSTICAS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <h2 style="font-size: var(--fs-tres); margin-bottom: var(--espacio-tres); color: var(--color-dos);">¿Por qué confiar en nosotros?</h2>
                <p class="seccion__descripcion">
                    El directorio más completo y confiable de servicios de cremación para mascotas en España.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--espacio-cuatro);">
                <article class="item-cuatro">
                    <div class="caracteristica__icono" style="width: 64px; height: 64px; margin-bottom: var(--espacio-tres);">
                        <i data-lucide="shield-check" class="icono"></i>
                    </div>
                    <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Crematorios Verificados</h3>
                    <p style="color: var(--color-seis-claro); margin: 0;">
                        Todos los crematorios pasan por un proceso riguroso de verificación para garantizar la calidad del servicio.
                    </p>
                </article>

                <article class="item-cuatro">
                    <div class="caracteristica__icono" style="width: 64px; height: 64px; margin-bottom: var(--espacio-tres);">
                        <i data-lucide="star" class="icono"></i>
                    </div>
                    <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Reseñas Reales</h3>
                    <p style="color: var(--color-seis-claro); margin: 0;">
                        Lee experiencias auténticas de otras familias para tomar la mejor decisión en este momento difícil.
                    </p>
                </article>

                <article class="item-cuatro">
                    <div class="caracteristica__icono" style="width: 64px; height: 64px; margin-bottom: var(--espacio-tres);">
                        <i data-lucide="clock" class="icono"></i>
                    </div>
                    <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Disponibilidad 24/7</h3>
                    <p style="color: var(--color-seis-claro); margin: 0;">
                        Encuentra crematorios con servicio de urgencia disponible las 24 horas del día, los 7 días de la semana.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CREMATORIOS DESTACADOS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <h2 style="font-size: var(--fs-tres); margin-bottom: var(--espacio-tres); color: var(--color-dos);">Crematorios Destacados</h2>
                <p class="seccion__descripcion">
                    Los crematorios mejor valorados y con mayor experiencia en cada comunidad autónoma.
                </p>
            </div>

            <div class="grid-tarjetas">
                <!-- Tarjeta 1 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&h=400&fit=crop" alt="Crematorio Madrid">
                        <span class="tarjeta__destacado">Verificado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/crematorio/1">Crematorio Mascotas Madrid Centro</a>
                        </h3>

                        <p class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            Madrid, Comunidad de Madrid
                        </p>

                        <p class="tarjeta__descripcion">
                            Servicio profesional y compasivo para la cremación de mascotas. Disponibilidad 24 horas, recogida a domicilio incluida en toda la comunidad.
                        </p>

                        <div class="tarjeta__footer">
                            <div class="tarjeta__valoracion">
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <span>(48 reseñas)</span>
                            </div>

                            <div class="tarjeta__servicios">
                                <i data-lucide="clock" class="icono" title="24 horas"></i>
                                <i data-lucide="home" class="icono" title="Recogida a domicilio"></i>
                                <i data-lucide="heart" class="icono" title="Cremación individual"></i>
                            </div>
                        </div>

                        <button data-chatwith="cw-open" class="boton uno" style="width: 100%; margin-top: var(--espacio-tres);">
                            <i data-lucide="message-circle" class="icono"></i>
                            Contactar Ahora
                        </button>
                    </div>
                </article>

                <!-- Tarjeta 2 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img src="https://images.unsplash.com/photo-1415369629372-26f2fe60c467?w=600&h=400&fit=crop" alt="Crematorio Barcelona">
                        <span class="tarjeta__destacado">Verificado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/crematorio/2">Crematorio Pets Barcelona</a>
                        </h3>

                        <p class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            Barcelona, Cataluña
                        </p>

                        <p class="tarjeta__descripcion">
                            Más de 15 años ofreciendo servicios de cremación con dignidad. Instalaciones modernas y personal especializado disponible siempre.
                        </p>

                        <div class="tarjeta__footer">
                            <div class="tarjeta__valoracion">
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono"></i>
                                <span>(32 reseñas)</span>
                            </div>

                            <div class="tarjeta__servicios">
                                <i data-lucide="clock" class="icono" title="24 horas"></i>
                                <i data-lucide="home" class="icono" title="Recogida a domicilio"></i>
                                <i data-lucide="church" class="icono" title="Velatorio"></i>
                            </div>
                        </div>

                        <button data-chatwith="cw-open" class="boton uno" style="width: 100%; margin-top: var(--espacio-tres);">
                            <i data-lucide="message-circle" class="icono"></i>
                            Contactar Ahora
                        </button>
                    </div>
                </article>

                <!-- Tarjeta 3 -->
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <img src="https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=600&h=400&fit=crop" alt="Crematorio Valencia">
                        <span class="tarjeta__destacado">Verificado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo $base_url; ?>/crematorio/3">Memorial Mascotas Valencia</a>
                        </h3>

                        <p class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            Valencia, Comunidad Valenciana
                        </p>

                        <p class="tarjeta__descripcion">
                            Servicio completo de cremación individual. Urnas personalizadas y memorial online. Atención personalizada en español y valenciano.
                        </p>

                        <div class="tarjeta__footer">
                            <div class="tarjeta__valoracion">
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <i data-lucide="star" class="icono"></i>
                                <span>(27 reseñas)</span>
                            </div>

                            <div class="tarjeta__servicios">
                                <i data-lucide="heart" class="icono" title="Cremación individual"></i>
                                <i data-lucide="home" class="icono" title="Recogida a domicilio"></i>
                                <i data-lucide="award" class="icono" title="Urnas personalizadas"></i>
                            </div>
                        </div>

                        <button data-chatwith="cw-open" class="boton uno" style="width: 100%; margin-top: var(--espacio-tres);">
                            <i data-lucide="message-circle" class="icono"></i>
                            Contactar Ahora
                        </button>
                    </div>
                </article>
            </div>

            <div style="text-align: center; margin-top: var(--espacio-seis);">
                <a href="<?php echo $base_url; ?>/directorio.php" class="boton dos grande" role="button">
                    Ver Todos los Crematorios
                    <i data-lucide="arrow-right" class="icono"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CTA - Llamada a la Acción
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion tres" style="text-align: center;">
        <div class="contenedor">
            <h2 style="font-size: var(--fs-tres); margin-bottom: var(--espacio-tres); color: var(--color-blanco);">¿Necesitas ayuda para encontrar un crematorio?</h2>
            <p style="font-size: var(--fs-dos); margin-bottom: var(--espacio-cinco); opacity: 0.9;">
                Nuestro equipo está disponible para ayudarte en este momento difícil. Contáctanos por chat o teléfono.
            </p>
            <div style="display: flex; gap: var(--espacio-tres); justify-content: center; flex-wrap: wrap;">
                <button data-chatwith="cw-open" class="boton uno grande">
                    <i data-lucide="message-circle" class="icono"></i>
                    Chat en Vivo
                </button>
                <a href="tel:+34900000000" class="boton dos grande" role="button">
                    <i data-lucide="phone" class="icono"></i>
                    Llamar Ahora
                </a>
            </div>
        </div>
    </section>

<?php include '../includes/footer.php'; ?>
