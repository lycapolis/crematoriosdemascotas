<?php
/**
 * ═══════════════════════════════════════════════════════════
 * HOME - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 04
 * Fecha: Enero 2026
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Crematorios de Mascotas - Directorio España';
$pagina_actual = 'inicio';
include 'includes/header.php';

// ═══════════════════════════════════════════════════════════
// OBTENER DATOS DINÁMICOS
// ═══════════════════════════════════════════════════════════
$comunidades = obtenerComunidades();
$provincias = obtenerProvincias();
$destacados = obtenerDestacados(DESTACADOS_HOME);

// Obtener ciudades únicas con crematorios (limitado a 20)
$pdo = obtenerConexion();
$ciudades = [];
if ($pdo) {
    $sqlCiudades = "SELECT DISTINCT
                        ciudad AS nombre,
                        LOWER(REPLACE(REPLACE(ciudad, ' ', '-'), ',', '')) AS slug,
                        p.slug AS provincia_slug,
                        COUNT(*) AS total
                    FROM crematorios c
                    LEFT JOIN provincias p ON c.provincia_id = p.id
                    WHERE ciudad IS NOT NULL AND ciudad != ''
                    GROUP BY ciudad, p.slug
                    ORDER BY total DESC, ciudad ASC
                    LIMIT 20";
    $ciudades = $pdo->query($sqlCiudades)->fetchAll();
}
?>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero">
        <div class="contenedor">
            <p class="seccion__subtitulo">Un adiós con amor y dignidad</p>

            <h1>
                Encuentra el lugar perfecto para despedir a tu mascota
            </h1>

            <h2 class="seccion__descripcion estilo-h5 seis">
                Conectamos familias con crematorios de mascotas de confianza.
                Servicios profesionales, respetuosos y llenos de compasión para
                honrar la memoria de tu compañero fiel.
            </h2>

            <form action="directorio.php" method="GET" class="buscador" style="max-width: 700px; width: 100%; display: flex; align-items: center; gap: var(--espacio-dos); margin: var(--espacio-cinco) auto 0;">
                <input
                    type="text"
                    name="busqueda"
                    class="campo"
                    style="border-radius: var(--radio-full); flex: 1; height: 48px;"
                    placeholder="Buscar por nombre o ciudad..."
                    aria-label="Buscar crematorios"
                >
                <button type="submit" class="boton uno" style="border-radius: var(--radio-full); height: 48px;">
                    <i data-lucide="search" class="icono"></i>
                    Buscar
                </button>
            </form>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FILTROS AVANZADOS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <form action="directorio.php" method="GET">
                <!-- Filtros superiores -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <!-- Comunidad Autónoma -->
                    <div class="formulario-grupo" style="margin-bottom: 0;">
                        <label for="comunidad" class="formulario-etiqueta">Comunidad Autónoma</label>
                        <div class="seleccion-contenedor">
                            <select name="comunidad_id" id="comunidad" class="seleccion">
                                <option value="">Todas las comunidades</option>
                                <?php foreach ($comunidades as $com): ?>
                                <option value="<?php echo $com['id']; ?>"><?php echo limpiar($com['nombre']); ?> (<?php echo $com['total_crematorios']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Provincia -->
                    <div class="formulario-grupo" style="margin-bottom: 0;">
                        <label for="provincia" class="formulario-etiqueta">Provincia</label>
                        <div class="seleccion-contenedor">
                            <select name="provincia_id" id="provincia" class="seleccion">
                                <option value="">Todas las provincias</option>
                                <?php foreach ($provincias as $prov): ?>
                                <option value="<?php echo $prov['id']; ?>"><?php echo limpiar($prov['nombre']); ?> (<?php echo $prov['total_crematorios']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Valoración Mínima -->
                    <div class="formulario-grupo" style="margin-bottom: 0;">
                        <label for="valoracion" class="formulario-etiqueta">Valoración Mínima</label>
                        <div class="seleccion-contenedor">
                            <select name="valoracion_minima" id="valoracion" class="seleccion">
                                <option value="">Todas las valoraciones</option>
                                <option value="5">5 estrellas</option>
                                <option value="4">4+ estrellas</option>
                                <option value="3">3+ estrellas</option>
                                <option value="2">2+ estrellas</option>
                                <option value="1">1+ estrellas</option>
                            </select>
                        </div>
                    </div>

                    <!-- Ordenar por -->
                    <div class="formulario-grupo" style="margin-bottom: 0;">
                        <label for="orden" class="formulario-etiqueta">Ordenar Por</label>
                        <div class="seleccion-contenedor">
                            <select name="orden" id="orden" class="seleccion">
                                <option value="">Mejor valorados</option>
                                <option value="nombre">Nombre A-Z</option>
                                <option value="calificacion">Calificación</option>
                                <option value="recientes">Más recientes</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Filtros de servicios -->
                <div style="margin-bottom: var(--espacio-cuatro);">
                    <div class="formulario-etiqueta">Servicios</div>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--espacio-dos);">
                        <label class="casilla-verificacion">
                            <input type="checkbox" name="servicio_cremacion" value="1">
                            <span class="casilla-verificacion__texto">Cremación</span>
                        </label>

                        <label class="casilla-verificacion">
                            <input type="checkbox" name="servicio_24h" value="1">
                            <span class="casilla-verificacion__texto">24 Horas</span>
                        </label>

                        <label class="casilla-verificacion">
                            <input type="checkbox" name="servicio_velatorio" value="1">
                            <span class="casilla-verificacion__texto">Velatorio</span>
                        </label>

                        <label class="casilla-verificacion">
                            <input type="checkbox" name="servicio_recogida" value="1">
                            <span class="casilla-verificacion__texto">Recogida a domicilio</span>
                        </label>

                        <label class="casilla-verificacion">
                            <input type="checkbox" name="servicio_cementerio" value="1">
                            <span class="casilla-verificacion__texto">Cementerio</span>
                        </label>
                    </div>
                </div>

                <!-- Acciones -->
                <div style="display: flex; gap: var(--espacio-tres); justify-content: center;">
                    <button type="submit" class="boton uno">
                        <i data-lucide="search" class="icono"></i>
                        Buscar
                    </button>

                    <button type="button" class="boton cuatro" onclick="limpiarFiltros()">
                        Limpiar filtros
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CREMATORIOS DESTACADOS
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div class="seccion__encabezado">
                <p class="seccion__subtitulo">Recomendados</p>
                <h2 class="seccion__titulo">Crematorios Destacados</h2>
                <p class="seccion__descripcion">
                    Los crematorios mejor valorados por las familias que han confiado en sus servicios.
                </p>
            </div>

            <div class="grid-tarjetas">
                <?php if (empty($destacados)): ?>
                <p style="text-align: center; grid-column: 1 / -1;">No hay crematorios destacados disponibles.</p>
                <?php else: ?>
                <?php foreach ($destacados as $crem): ?>
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <?php if (!empty($crem['foto_principal'])): ?>
                        <img
                            src="<?php echo limpiar($crem['foto_principal']); ?>"
                            alt="<?php echo limpiar($crem['nombre']); ?>"
                            loading="lazy"
                            onerror="this.parentElement.innerHTML='<div class=\'tarjeta__imagen--placeholder\'><i data-lucide=\'heart\' class=\'icono\'></i></div><span class=\'tarjeta__destacado\'>Destacado</span>'; lucide.createIcons();"
                        >
                        <?php else: ?>
                        <div class="tarjeta__imagen--placeholder">
                            <i data-lucide="heart" class="icono"></i>
                        </div>
                        <?php endif; ?>
                        <span class="tarjeta__destacado">Destacado</span>
                    </div>

                    <div class="tarjeta__contenido">
                        <h3 class="tarjeta__titulo">
                            <a href="<?php echo generarUrl('crematorio', $crem['slug']); ?>">
                                <?php echo limpiar($crem['nombre']); ?>
                            </a>
                        </h3>

                        <p class="tarjeta__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            <?php echo limpiar($crem['ciudad']); ?>, <?php echo limpiar($crem['provincia_nombre']); ?>
                        </p>

                        <p class="tarjeta__descripcion">
                            <?php echo limpiar($crem['descripcion_corta'] ?? 'Servicios de cremación de mascotas profesional y respetuoso.'); ?>
                        </p>

                        <div class="tarjeta__footer">
                            <?php if ($crem['rating'] > 0): ?>
                            <div class="tarjeta__valoracion">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i data-lucide="star" class="icono <?php echo $i <= round($crem['rating']) ? 'icono--llena' : ''; ?>"></i>
                                <?php endfor; ?>
                                <span><?php echo number_format($crem['rating'], 1); ?></span>
                            </div>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">(<?php echo $crem['reviews_total'] ?? 0; ?> reseñas)</span>
                            <?php else: ?>
                            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Sin valoraciones</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Botón ver todos -->
            <div style="text-align: center; margin-top: var(--espacio-seis);">
                <a href="directorio.php" class="boton dos grande">
                    Ver todos los crematorios
                    <i data-lucide="arrow-right" class="icono"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         POR QUÉ USAR NUESTRO DIRECTORIO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div class="seccion__encabezado">
                <p class="seccion__subtitulo">Nuestro compromiso</p>
                <h2 class="seccion__titulo">¿Por qué usar nuestro directorio?</h2>
            </div>

            <div class="grid-tarjetas">
                <!-- Característica 1 -->
                <article class="tarjeta">
                    <div class="tarjeta__contenido" style="text-align: center;">
                        <div class="caracteristica__icono">
                            <i data-lucide="shield-check" class="icono"></i>
                        </div>
                        <h3 class="tarjeta__titulo">Crematorios Verificados</h3>
                        <p class="tarjeta__descripcion">
                            Todos los crematorios en nuestro directorio pasan por un proceso de verificación
                            para garantizar la calidad y profesionalismo de sus servicios.
                        </p>
                    </div>
                </article>

                <!-- Característica 2 -->
                <article class="tarjeta">
                    <div class="tarjeta__contenido" style="text-align: center;">
                        <div class="caracteristica__icono">
                            <i data-lucide="star" class="icono"></i>
                        </div>
                        <h3 class="tarjeta__titulo">Reseñas Reales</h3>
                        <p class="tarjeta__descripcion">
                            Lee las experiencias de otras familias para tomar la mejor decisión.
                            Todas las reseñas son de personas que han utilizado los servicios.
                        </p>
                    </div>
                </article>

                <!-- Característica 3 -->
                <article class="tarjeta">
                    <div class="tarjeta__contenido" style="text-align: center;">
                        <div class="caracteristica__icono">
                            <i data-lucide="clock" class="icono"></i>
                        </div>
                        <h3 class="tarjeta__titulo">Disponibilidad 24/7</h3>
                        <p class="tarjeta__descripcion">
                            Muchos de nuestros crematorios ofrecen servicio las 24 horas.
                            Encuentra ayuda cuando más la necesitas, sin importar la hora.
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CREMATORIOS POR CIUDAD
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div class="seccion__encabezado">
                <p class="seccion__subtitulo">Cobertura</p>
                <h2 class="seccion__titulo">Crematorios por Ciudad</h2>
                <p class="seccion__descripcion">
                    Encuentra crematorios de mascotas cerca de ti.
                </p>
            </div>

            <div class="ciudades-grid">
                <?php if (empty($ciudades)): ?>
                <p style="text-align: center; grid-column: 1 / -1;">No hay ciudades disponibles.</p>
                <?php else: ?>
                <?php foreach ($ciudades as $ciudad): ?>
                <a href="<?php echo generarUrl('ciudad', $ciudad['slug'], $ciudad['provincia_slug']); ?>" class="boton tres">
                    <?php echo limpiar($ciudad['nombre']); ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CTA FINAL
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion tres">
        <div class="contenedor" style="text-align: center;">
            <h2 style="color: var(--color-ocho); margin-bottom: var(--espacio-cuatro);">
                ¿Tienes un crematorio de mascotas?
            </h2>
            <p class="seccion__descripcion" style="color: var(--color-ocho-claro); max-width: 600px; margin: 0 auto var(--espacio-seis);">
                Únete a nuestro directorio y conecta con familias que buscan
                servicios de cremación para sus mascotas. Es gratis y fácil de usar.
            </p>
            <a href="registrar-negocio.php" class="boton tres grande">
                Registrar mi Crematorio
                <i data-lucide="arrow-right" class="icono"></i>
            </a>
        </div>
    </section>

    <!-- Script específico de la página -->
    <script>
        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('comunidad').value = '';
            document.getElementById('provincia').value = '';
            document.getElementById('valoracion').value = '';
            document.getElementById('orden').value = '';

            // Desmarcar checkboxes
            const checkboxes = document.querySelectorAll('.casilla-verificacion input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        }
    </script>

<?php include 'includes/footer.php'; ?>
