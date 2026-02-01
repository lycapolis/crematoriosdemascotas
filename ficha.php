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
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Detalle de un crematorio específico
 * URL: /nombre-del-crematorio (raíz)
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración para acceso a funciones (sin header aún)
require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// ═══════════════════════════════════════════════════════════
// OBTENER CREMATORIO POR SLUG
// ═══════════════════════════════════════════════════════════
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Obtener crematorio de la base de datos
$crematorio = obtenerCrematorioSlug($slug);

// Si no existe, mostrar 404
if (!$crematorio) {
    http_response_code(404);
    $titulo_pagina = 'Crematorio no encontrado';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="search-x" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Crematorio no encontrado</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">El crematorio que buscas no existe o ha sido eliminado.</p>
            <a href="<?php echo BASE_URL; ?>/directorio.php" class="boton uno">Ver todos los crematorios</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// Variables para facilitar el uso en la plantilla
$crematorio_nombre = $crematorio['nombre'];
$crematorio_slug = $crematorio['slug'];
$ciudad_nombre = $crematorio['ciudad'] ?? '';
$ciudad_slug = strtolower(str_replace([' ', ','], ['-', ''], $ciudad_nombre));
$provincia_nombre = $crematorio['provincia_nombre'] ?? '';
$provincia_slug = $crematorio['provincia_slug'] ?? '';
$comunidad_nombre = $crematorio['comunidad_nombre'] ?? '';
$comunidad_slug = $crematorio['comunidad_slug'] ?? '';

// Datos de contacto
$direccion = $crematorio['direccion_completa'] ?? '';
$telefono = $crematorio['telefono'] ?? '';
$email = $crematorio['email'] ?? '';
$web = $crematorio['website'] ?? '';
$valoracion = $crematorio['rating'] ?? 0;
$num_resenas = $crematorio['reviews_total'] ?? 0;
$descripcion = $crematorio['descripcion'] ?? '';
$destacado = !empty($crematorio['destacado']);

// Título de página y header
$titulo_pagina = $crematorio_nombre . ' - Crematorios de Mascotas';
$pagina_actual = 'directorio';
include 'includes/header.php';
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
                        <a href="<?php echo $base_url; ?>/espana/" style="color: var(--color-seis-claro); text-decoration: none;">España</a>
                        <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                    </li>
                    <?php if ($comunidad_nombre && $comunidad_nombre !== $provincia_nombre): ?>
                    <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                        <a href="<?php echo generarUrl('comunidad', $comunidad_slug); ?>" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($comunidad_nombre); ?></a>
                        <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                    </li>
                    <?php endif; ?>
                    <?php if ($provincia_nombre): ?>
                    <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                        <a href="<?php echo generarUrl('provincia', $provincia_slug); ?>" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($provincia_nombre); ?></a>
                        <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                    </li>
                    <?php endif; ?>
                    <?php if ($ciudad_nombre): ?>
                    <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                        <a href="<?php echo generarUrl('ciudad', $ciudad_slug, $provincia_slug); ?>" style="color: var(--color-seis-claro); text-decoration: none;"><?php echo limpiar($ciudad_nombre); ?></a>
                        <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                    </li>
                    <?php endif; ?>
                    <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                        <span><?php echo limpiar($crematorio_nombre); ?></span>
                    </li>
                </ol>
            </nav>

            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--espacio-cuatro); flex-wrap: wrap;">
                <div>
                    <h1 style="font-size: var(--fs-cuatro); color: var(--color-dos); margin-bottom: var(--espacio-dos);"><?php echo limpiar($crematorio_nombre); ?></h1>
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); color: var(--color-seis-claro); font-size: var(--fs-uno);">
                        <span style="display: flex; align-items: center; gap: var(--espacio-uno);">
                            <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px;"></i>
                            <?php echo limpiar($ciudad_nombre); ?><?php echo $provincia_nombre ? ', ' . limpiar($provincia_nombre) : ''; ?>
                        </span>
                        <?php if ($valoracion > 0): ?>
                        <span style="display: flex; align-items: center; gap: var(--espacio-uno);">
                            <span style="color: var(--color-diez); font-size: var(--fs-tres);">&#9733;</span>
                            <?php echo number_format($valoracion, 1); ?> (<?php echo $num_resenas; ?> reseñas)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($destacado): ?>
                <span style="background: var(--color-uno); color: var(--color-ocho); padding: var(--espacio-uno) var(--espacio-tres); border-radius: var(--radio-full); font-size: var(--fs-uno); font-weight: var(--peso-medio); display: flex; align-items: center; gap: var(--espacio-uno);">
                    <i data-lucide="award" class="icono" style="width: 14px; height: 14px;"></i>
                    Destacado
                </span>
                <?php endif; ?>
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

                <!-- Imagen Principal -->
                <?php $foto = $crematorio['foto_principal'] ?? ''; ?>
                <section class="ficha__imagen" style="margin-bottom: var(--espacio-cinco);">
                    <?php if (!empty($foto)): ?>
                    <img
                        src="<?php echo limpiar($foto); ?>"
                        alt="<?php echo limpiar($crematorio_nombre); ?>"
                        loading="lazy"
                        style="width: 100%; max-height: 400px; object-fit: cover; border-radius: var(--radio-dos);"
                        onerror="this.parentElement.innerHTML='<div class=\'ficha__imagen--placeholder\' style=\'display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--color-cinco) 0%,var(--color-cuatro) 100%);width:100%;height:300px;border-radius:var(--radio-dos);\'><i data-lucide=\'heart\' style=\'width:64px;height:64px;color:var(--color-seis-claro);opacity:0.6;\'></i></div>'; lucide.createIcons();"
                    >
                    <?php else: ?>
                    <div class="ficha__imagen--placeholder" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--color-cinco) 0%, var(--color-cuatro) 100%); width: 100%; height: 300px; border-radius: var(--radio-dos);">
                        <i data-lucide="heart" style="width: 64px; height: 64px; color: var(--color-seis-claro); opacity: 0.6;"></i>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Descripción -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Sobre el Crematorio</h2>
                    <div style="color: var(--color-seis); line-height: 1.8;">
                        <?php if ($descripcion): ?>
                        <?php echo nl2br(limpiar($descripcion)); ?>
                        <?php else: ?>
                        <p style="margin: 0;">
                            <?php echo limpiar($crematorio_nombre); ?> ofrece servicios de cremación de mascotas con el máximo
                            respeto y profesionalismo. Entendemos que tu mascota es un miembro más de la familia, y por eso nos
                            comprometemos a brindar un servicio digno y compasivo en estos momentos difíciles.
                        </p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Ubicación / Mapa -->
                <?php if ($direccion || $ciudad_nombre): ?>
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-seis);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Ubicación</h2>
                    <div>
                        <?php
                        // Construir dirección para mapa
                        $direccion_mapa = urlencode($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre . ', España');
                        ?>
                        <iframe
                            src="https://www.google.com/maps?q=<?php echo $direccion_mapa; ?>&output=embed"
                            width="100%"
                            height="400"
                            style="border:0; border-radius: var(--radio-dos); margin-bottom: var(--espacio-tres);"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        <p style="display: flex; align-items: center; gap: var(--espacio-dos); color: var(--color-seis); font-size: var(--fs-uno); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos); margin: 0;">
                            <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px; color: var(--color-uno);"></i>
                            <?php echo limpiar($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre); ?>
                        </p>
                    </div>
                </section>
                <?php endif; ?>

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
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">
                        Reseñas
                        <?php
                        $total_resenas_aprobadas = contarResenasAprobadas($crematorio['id']);
                        if ($total_resenas_aprobadas > 0):
                        ?>
                        <span style="font-weight: normal; color: var(--color-seis); opacity: 0.7; font-size: var(--fs-uno);">
                            (<?php echo $total_resenas_aprobadas; ?>)
                        </span>
                        <?php endif; ?>
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                        <?php
                        $resenas_aprobadas = obtenerResenasAprobadas($crematorio['id'], 10);

                        if (empty($resenas_aprobadas)):
                        ?>
                        <!-- Sin reseñas aún -->
                        <article style="padding: var(--espacio-cinco); background: var(--color-cinco); border-radius: var(--radio-dos); text-align: center;">
                            <i data-lucide="message-square" style="width: 48px; height: 48px; color: var(--color-seis-claro); margin-bottom: var(--espacio-tres);"></i>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                Aún no hay reseñas para este crematorio.<br>
                                <strong style="color: var(--color-uno);">¡Sé el primero en compartir tu experiencia!</strong>
                            </p>
                        </article>

                        <?php else: ?>

                        <?php foreach ($resenas_aprobadas as $resena): ?>
                        <article style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-tres); flex-wrap: wrap; gap: var(--espacio-dos);">
                                <div>
                                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos);">
                                        <?php echo limpiar($resena['nombre']); ?>
                                    </div>
                                    <div class="tarjeta__valoracion" style="margin-top: var(--espacio-uno);">
                                        <?php echo generarEstrellas($resena['calificacion']); ?>
                                    </div>
                                </div>
                                <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">
                                    <?php echo date('d/m/Y', strtotime($resena['created_at'])); ?>
                                </span>
                            </div>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                <?php echo nl2br(limpiar($resena['comentario'])); ?>
                            </p>
                        </article>
                        <?php endforeach; ?>

                        <?php endif; ?>

                    </div>
                </section>

                <!-- Formulario de reseña -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro);">
                    <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">Dejar una Reseña</h2>

                    <!-- Mensaje de alerta -->
                    <div id="alerta-resena" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

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
                    <?php if ($direccion || $ciudad_nombre): ?>
                    <div style="display: flex; align-items: flex-start; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="map-pin" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <?php echo limpiar($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Teléfono -->
                    <?php if ($telefono): ?>
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="phone" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $telefono); ?>" style="color: var(--color-uno); text-decoration: none;"><?php echo formatearTelefono($telefono); ?></a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Email -->
                    <?php if ($email): ?>
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0; border-bottom: 1px solid var(--color-cinco);">
                        <i data-lucide="mail" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="mailto:<?php echo limpiar($email); ?>" style="color: var(--color-uno); text-decoration: none;"><?php echo limpiar($email); ?></a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Web -->
                    <?php if ($web): ?>
                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) 0;">
                        <i data-lucide="globe" class="icono" style="color: var(--color-uno); width: 20px; height: 20px; flex-shrink: 0;"></i>
                        <span style="color: var(--color-seis); font-size: var(--fs-uno);">
                            <a href="<?php echo limpiar($web); ?>" target="_blank" rel="noopener" style="color: var(--color-uno); text-decoration: none;">Visitar sitio web</a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Botones de acción -->
                    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres); margin-top: var(--espacio-cuatro);">
                        <?php if ($telefono): ?>
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $telefono); ?>" class="boton uno">
                            <i data-lucide="phone" class="icono"></i>
                            Llamar ahora
                        </a>

                        <a href="<?php echo generarWhatsApp($telefono, 'Hola, me gustaría obtener información sobre sus servicios.'); ?>" class="boton dos" target="_blank" style="background: var(--color-nueve); border-color: var(--color-nueve); color: var(--color-ocho);">
                            <i data-lucide="message-circle" class="icono"></i>
                            WhatsApp
                        </a>
                        <?php else: ?>
                        <p style="color: var(--color-seis-claro); font-size: var(--fs-uno); text-align: center;">No hay información de contacto disponible</p>
                        <?php endif; ?>
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
        // Mostrar mensaje de alerta
        function mostrarAlertaResena(mensaje, tipo) {
            const alerta = document.getElementById('alerta-resena');
            alerta.textContent = mensaje;
            alerta.className = 'alerta ' + tipo;
            alerta.style.display = 'flex';
            alerta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

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
                mostrarAlertaResena('Por favor completa todos los campos.', 'error');
                return;
            }

            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                mostrarAlertaResena('Por favor ingresa un email válido.', 'error');
                return;
            }

            // Enviar vía AJAX
            const boton = document.querySelector('#form-resena button[type="submit"]');
            boton.disabled = true;
            boton.textContent = 'Enviando...';

            const formData = new FormData();
            formData.append('tipo', 'resena');
            formData.append('nombre', nombre);
            formData.append('email', email);
            formData.append('comentario', comentario);
            formData.append('calificacion', calificacion);
            formData.append('crematorio', '<?php echo addslashes($crematorio_nombre); ?>');
            formData.append('crematorio_slug', '<?php echo addslashes($crematorio_slug); ?>');
            formData.append('page_url', window.location.href);

            fetch('<?php echo BASE_URL; ?>/procesar-formulario.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    dataLayer.push({
                        'event': 'form_submit_success',
                        'form_id': data.form_id,
                        'form_name': data.form_name
                    });
                    mostrarAlertaResena('¡Gracias por tu reseña! Será publicada después de ser revisada.', 'exito');
                    document.getElementById('form-resena').reset();
                    seleccionarEstrellas(5);
                } else {
                    mostrarAlertaResena(data.mensaje || 'Error al enviar. Inténtalo de nuevo.', 'error');
                }
                boton.disabled = false;
                boton.textContent = 'Enviar Reseña';
            })
            .catch(() => {
                mostrarAlertaResena('Error de conexión. Inténtalo de nuevo.', 'error');
                boton.disabled = false;
                boton.textContent = 'Enviar Reseña';
            });
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

<?php include 'includes/footer.php'; ?>
