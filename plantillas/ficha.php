<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FICHA CREMATORIO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 *
 * Detalle de un crematorio específico
 * URL: /funeraria-san-antonio-abad (raíz)
 * ═══════════════════════════════════════════════════════════
 */

// Obtener slug del crematorio
$crematorio_slug = isset($_GET['slug']) ? $_GET['slug'] : 'funeraria-san-antonio-abad';

// Datos de ejemplo (en producción vendrían de la base de datos según el slug)
$crematorio_nombre = 'Funeraria San Antonio Abad | El Patrón de las Mascotas';
$pais_nombre = 'España';
$pais_slug = 'espana';
$ciudad_nombre = 'Paracuellos de Jarama';
$ciudad_slug = 'paracuellos-de-jarama';
$provincia_nombre = 'Madrid';
$provincia_slug = 'madrid';
$comunidad_nombre = 'Comunidad de Madrid';

$titulo_pagina = $crematorio_nombre . ' - Crematorios de Mascotas';
$pagina_actual = 'directorio';
include '../includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         BREADCRUMBS + HERO
         ═══════════════════════════════════════════════════════════ -->
    <section style="background: var(--color-cuatro); padding: var(--espacio-cuatro) 0 var(--espacio-tres);">
        <div class="contenedor">
            <!-- Breadcrumbs -->
            <nav aria-label="Breadcrumb" style="margin-bottom: var(--espacio-cuatro);">
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
                    <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                        <a href="<?php echo $base_url; ?>/<?php echo $pais_slug; ?>/<?php echo $provincia_slug; ?>/<?php echo $ciudad_slug; ?>/" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo htmlspecialchars($ciudad_nombre); ?></a>
                        <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                    </li>
                    <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                        <span>Funeraria San Antonio Abad</span>
                    </li>
                </ol>
            </nav>

            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--espacio-cuatro); flex-wrap: wrap;">
                <div>
                    <h1 style="font-size: var(--fs-cuatro); color: var(--color-dos); margin-bottom: var(--espacio-dos);"><?php echo htmlspecialchars($crematorio_nombre); ?></h1>
                    <div style="display: flex; align-items: center; gap: var(--espacio-uno); color: var(--color-seis-claro); font-size: var(--fs-uno);">
                        <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                        <?php echo htmlspecialchars($ciudad_nombre); ?>, <?php echo htmlspecialchars($provincia_nombre); ?>
                    </div>
                </div>
                <span style="background: var(--color-uno); color: var(--color-ocho); padding: var(--espacio-uno) var(--espacio-tres); border-radius: var(--radio-full); font-size: var(--fs-uno); font-weight: var(--peso-medio); display: flex; align-items: center; gap: var(--espacio-uno);">
                    <i data-lucide="award" class="icono" style="width: 14px; height: 14px;"></i>
                    Destacado
                </span>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor" style="padding: var(--espacio-seis) 0;">
        <div style="display: grid; grid-template-columns: 1fr 350px; gap: var(--espacio-cinco);">

            <!-- COLUMNA PRINCIPAL -->
            <main>

                <!-- Descripción -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Sobre el Crematorio</h2>
                    <div style="color: var(--color-seis); line-height: 1.8;">
                        <p style="margin-bottom: var(--espacio-tres);">
                            En Funeraria San Antonio Abad llevamos más de 10 años ofreciendo servicios de cremación para mascotas
                            con el máximo respeto y profesionalismo. Entendemos que tu mascota es un miembro más de la familia,
                            y por eso nos comprometemos a brindar un servicio digno y compasivo en estos momentos difíciles.
                        </p>
                        <p style="margin-bottom: var(--espacio-tres);">
                            Nuestras instalaciones cuentan con tecnología de última generación para garantizar una cremación
                            individual y respetuosa. Ofrecemos recogida a domicilio, salas de velatorio privadas, y una amplia
                            selección de urnas y recuerdos personalizados.
                        </p>
                        <p style="margin: 0;">
                            Nuestro equipo está formado por profesionales con vocación de servicio que te acompañarán en todo
                            el proceso, brindándote el apoyo emocional que necesitas mientras cuidamos de tu compañero fiel
                            con la dignidad que merece.
                        </p>
                    </div>
                </section>

                <!-- Ubicación / Mapa -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Ubicación</h2>
                    <div>
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3037.8799999999996!2d-3.5888!3d40.4965!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDDCsDI5JzQ3LjQiTiAzwrAzNScxOS43Ilc!5e0!3m2!1ses!2ses!4v1234567890"
                            width="100%"
                            height="400"
                            style="border:0; border-radius: var(--radio-dos); margin-bottom: var(--espacio-tres);"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        <p style="display: flex; align-items: center; gap: var(--espacio-dos); color: var(--color-seis); font-size: var(--fs-uno); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos); margin: 0;">
                            <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px; color: var(--color-uno);"></i>
                            Calle Real 123, Paracuellos de Jarama, 28860 Madrid
                        </p>
                    </div>
                </section>

                <!-- Servicios -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Servicios</h2>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--espacio-tres);">
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Cremación individual</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Cremación colectiva</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Recogida a domicilio</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Sala de velatorio</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Urnas personalizadas</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Servicio 24 horas</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Ceremonia de despedida</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--espacio-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <i data-lucide="check-circle" class="icono" style="color: var(--color-uno); width: 20px; height: 20px;"></i>
                            <span style="color: var(--color-dos); font-size: var(--fs-uno);">Entrega de cenizas</span>
                        </div>
                    </div>
                </section>

                <!-- Reseñas -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Reseñas (5)</h2>

                    <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                        <!-- Reseña 1 -->
                        <article style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-dos);">
                                <div>
                                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos);">María García</div>
                                    <div class="tarjeta__valoracion" style="margin-top: var(--espacio-uno);">
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                    </div>
                                </div>
                                <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Hace 2 semanas</span>
                            </div>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                Excelente servicio. El trato fue muy humano y profesional en un momento tan difícil.
                                Nos ayudaron con todo el proceso y la ceremonia fue muy emotiva. Totalmente recomendable.
                            </p>
                        </article>

                        <!-- Reseña 2 -->
                        <article style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-dos);">
                                <div>
                                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos);">Carlos Rodríguez</div>
                                    <div class="tarjeta__valoracion" style="margin-top: var(--espacio-uno);">
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                    </div>
                                </div>
                                <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Hace 1 mes</span>
                            </div>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                Muy agradecido por el servicio recibido. Las instalaciones están impecables y el personal
                                es muy empático. La recogida a domicilio fue puntual y todo se realizó con mucha delicadeza.
                            </p>
                        </article>

                        <!-- Reseña 3 -->
                        <article style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-dos);">
                                <div>
                                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos);">Ana Martínez</div>
                                    <div class="tarjeta__valoracion" style="margin-top: var(--espacio-uno);">
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                        <i data-lucide="star" class="icono icono--llena"></i>
                                    </div>
                                </div>
                                <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Hace 2 meses</span>
                            </div>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                Un servicio excepcional. Nos acompañaron en todo momento y nos dieron el tiempo que
                                necesitábamos para despedirnos. La urna que elegimos es preciosa. Muchas gracias.
                            </p>
                        </article>

                    </div>
                </section>

                <!-- Formulario de reseña -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Dejar una Reseña</h2>

                    <form id="form-resena" onsubmit="enviarResena(event)">

                        <!-- Calificación con estrellas -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta">Calificación *</label>
                            <div id="calificacion-estrellas" class="estrellas" style="cursor: pointer;" onmouseleave="restaurarEstrellas()">
                                <span class="estrella llena" onmouseenter="previewEstrellas(1)" onclick="seleccionarEstrellas(1)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(2)" onclick="seleccionarEstrellas(2)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(3)" onclick="seleccionarEstrellas(3)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(4)" onclick="seleccionarEstrellas(4)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(5)" onclick="seleccionarEstrellas(5)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                            </div>
                            <input type="hidden" id="calificacion" name="calificacion" value="5">
                        </div>

                        <!-- Nombre y Email -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                            <div class="formulario-grupo">
                                <label class="formulario-etiqueta" for="nombre-resena">Nombre *</label>
                                <input
                                    type="text"
                                    id="nombre-resena"
                                    name="nombre"
                                    class="campo"
                                    required
                                    placeholder="Tu nombre"
                                >
                            </div>

                            <div class="formulario-grupo">
                                <label class="formulario-etiqueta" for="email-resena">Email *</label>
                                <input
                                    type="email"
                                    id="email-resena"
                                    name="email"
                                    class="campo"
                                    required
                                    placeholder="tu@email.com"
                                >
                            </div>
                        </div>

                        <!-- Comentario -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="comentario-resena">Comentario *</label>
                            <textarea
                                id="comentario-resena"
                                name="comentario"
                                class="area-texto"
                                required
                                placeholder="Cuéntanos sobre tu experiencia..."
                                rows="5"
                            ></textarea>
                        </div>

                        <!-- Botón -->
                        <button type="submit" class="boton uno">
                            Enviar Reseña
                        </button>

                        <p style="font-size: var(--fs-uno); color: var(--color-seis-claro); margin-top: var(--espacio-tres);">
                            Tu reseña será revisada antes de ser publicada.
                        </p>
                    </form>
                </section>

            </main>

            <!-- SIDEBAR -->
            <aside>

                <!-- Contacto -->
                <div class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cinco); position: sticky; top: 100px;">
                    <h3 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Información de Contacto</h3>

                    <!-- Dirección -->
                    <div style="display: flex; align-items: flex-start; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="map-pin" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            Calle Real 123<br>
                            Paracuellos de Jarama, Madrid 28860
                        </span>
                    </div>

                    <!-- Teléfono -->
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="phone" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="tel:+34600123456" style="color: var(--color-uno); text-decoration: none;">+34 600 123 456</a>
                        </span>
                    </div>

                    <!-- Email -->
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="mail" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="mailto:info@funeraria-sanantonio.com" style="color: var(--color-uno); text-decoration: none;">info@funeraria-sanantonio.com</a>
                        </span>
                    </div>

                    <!-- Web -->
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0;">
                        <i data-lucide="globe" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="https://ejemplo.com" target="_blank" rel="noopener" style="color: var(--color-uno); text-decoration: none;">Visitar sitio web</a>
                        </span>
                    </div>

                    <!-- Botones de acción -->
                    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres); margin-top: var(--espacio-cuatro);">
                        <a href="tel:+34600123456" class="boton uno">
                            <i data-lucide="phone" class="icono"></i>
                            Llamar ahora
                        </a>

                        <a href="https://wa.me/34600123456" class="boton dos" target="_blank" style="background: var(--color-nueve); border-color: var(--color-nueve); color: var(--color-ocho);">
                            <i data-lucide="message-circle" class="icono"></i>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Horarios -->
                <div class="tarjeta simple" style="padding: var(--espacio-cuatro);">
                    <h3 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Horarios de Atención</h3>

                    <div style="display: flex; flex-direction: column; gap: var(--espacio-dos);">
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Lunes</span>
                            <span style="color: var(--color-seis);">9:00 - 18:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Martes</span>
                            <span style="color: var(--color-seis);">9:00 - 18:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Miércoles</span>
                            <span style="color: var(--color-seis);">9:00 - 18:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Jueves</span>
                            <span style="color: var(--color-seis);">9:00 - 18:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Viernes</span>
                            <span style="color: var(--color-seis);">9:00 - 18:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Sábado</span>
                            <span style="color: var(--color-seis);">9:00 - 14:00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: var(--espacio-dos) 0; font-size: var(--fs-uno);">
                            <span style="font-weight: var(--peso-medio); color: var(--color-dos);">Domingo</span>
                            <span style="color: var(--color-seis-claro); font-style: italic;">Cerrado</span>
                        </div>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <!-- Script específico de la página -->
    <script>
        // Actualizar visualización de estrellas
        function actualizarEstrellas(valor) {
            const estrellas = document.querySelectorAll('#calificacion-estrellas .estrella');
            estrellas.forEach((span, index) => {
                if (index < valor) {
                    span.classList.add('llena');
                    span.classList.remove('vacia');
                } else {
                    span.classList.add('vacia');
                    span.classList.remove('llena');
                }
            });
        }

        // Preview en hover
        function previewEstrellas(valor) {
            actualizarEstrellas(valor);
        }

        // Restaurar al valor seleccionado
        function restaurarEstrellas() {
            const valorActual = parseInt(document.getElementById('calificacion').value);
            actualizarEstrellas(valorActual);
        }

        // Seleccionar estrellas (clic)
        function seleccionarEstrellas(valor) {
            document.getElementById('calificacion').value = valor;
            actualizarEstrellas(valor);
        }

        // Enviar reseña
        function enviarResena(event) {
            event.preventDefault();

            const nombre = document.getElementById('nombre-resena').value.trim();
            const email = document.getElementById('email-resena').value.trim();
            const comentario = document.getElementById('comentario-resena').value.trim();
            const calificacion = document.getElementById('calificacion').value;

            if (!nombre || !email || !comentario) {
                alert('Por favor completa todos los campos.');
                return;
            }

            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Por favor ingresa un email válido.');
                return;
            }

            // Simular envío exitoso
            alert('¡Gracias por tu reseña! Será publicada después de ser revisada.');
            document.getElementById('form-resena').reset();

            // Resetear estrellas a 5
            seleccionarEstrellas(5);
        }
    </script>

    <!-- Media query para responsive -->
    <style>
        @media (max-width: 1024px) {
            .contenedor > div[style*="grid-template-columns: 1fr 350px"] {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 768px) {
            div[style*="grid-template-columns: repeat(2, 1fr)"] {
                grid-template-columns: 1fr !important;
            }

            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>

<?php include '../includes/footer.php'; ?>
