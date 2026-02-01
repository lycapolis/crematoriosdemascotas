<?php
/**
 * ═══════════════════════════════════════════════════════════
 * DIRECTORIO - CREMATORIOS DE MASCOTAS
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

$titulo_pagina = 'Directorio de Crematorios - Crematorios de Mascotas';
$pagina_actual = 'directorio';
include 'includes/header.php';

// ═══════════════════════════════════════════════════════════
// PROCESAR PARÁMETROS DE FILTRADO
// ═══════════════════════════════════════════════════════════
$filtros = [];
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

// Filtro de búsqueda
$busqueda = trim($_GET['busqueda'] ?? '');
if ($busqueda !== '') {
    $filtros['busqueda'] = $busqueda;
}

// Filtro de comunidad
$comunidadId = (int)($_GET['comunidad_id'] ?? 0);
if ($comunidadId > 0) {
    $filtros['comunidad_id'] = $comunidadId;
}

// Filtro de provincia
$provinciaId = (int)($_GET['provincia_id'] ?? 0);
if ($provinciaId > 0) {
    $filtros['provincia_id'] = $provinciaId;
}

// Filtro de valoración mínima
$valoracionMin = (int)($_GET['valoracion_minima'] ?? 0);
if ($valoracionMin > 0) {
    $filtros['valoracion_min'] = $valoracionMin;
}

// ═══════════════════════════════════════════════════════════
// OBTENER DATOS
// ═══════════════════════════════════════════════════════════
$comunidades = obtenerComunidades();
$provincias = obtenerProvincias();
$resultado = obtenerCrematorios($filtros, $pagina, ITEMS_POR_PAGINA);

$crematorios = $resultado['datos'];
$totalCrematorios = $resultado['total'];
$totalPaginas = $resultado['paginas'];
?>

    <!-- ═══════════════════════════════════════════════════════════
         HEADER DIRECTORIO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor">
            <h1>Directorio de Crematorios</h1>
            <h2 class="seccion__descripcion estilo-h5 seis">
                Encuentra el crematorio ideal para despedir a tu mascota con dignidad
            </h2>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LAYOUT PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor" style="display: grid; grid-template-columns: 280px 1fr; gap: var(--espacio-cinco); padding: var(--espacio-cinco) 0;">

        <!-- SIDEBAR - Filtros -->
        <aside class="tarjeta simple" style="padding: var(--espacio-cuatro); height: fit-content; position: sticky; top: 100px; background: var(--color-ocho); border: 1px solid var(--color-cinco);">
            <h2 style="font-size: var(--fs-tres); border-bottom: 2px solid var(--color-uno); padding-bottom: var(--espacio-tres); margin-bottom: var(--espacio-cuatro);">Filtros</h2>

            <form action="directorio.php" method="GET">
                <!-- Búsqueda -->
                <div class="formulario-grupo">
                    <label for="busqueda" class="formulario-etiqueta">Buscar</label>
                    <input
                        type="text"
                        id="busqueda"
                        name="busqueda"
                        class="campo"
                        placeholder="Nombre o ciudad..."
                        value="<?php echo limpiar($busqueda); ?>"
                    >
                </div>

                <!-- Comunidad Autónoma -->
                <div class="formulario-grupo">
                    <label for="comunidad" class="formulario-etiqueta">Comunidad Autónoma</label>
                    <div class="seleccion-contenedor">
                        <select id="comunidad" name="comunidad_id" class="seleccion">
                            <option value="">Todas las comunidades</option>
                            <?php foreach ($comunidades as $com): ?>
                            <option value="<?php echo $com['id']; ?>" <?php echo $comunidadId == $com['id'] ? 'selected' : ''; ?>>
                                <?php echo limpiar($com['nombre']); ?> (<?php echo $com['total_crematorios']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Provincia -->
                <div class="formulario-grupo">
                    <label for="provincia" class="formulario-etiqueta">Provincia</label>
                    <div class="seleccion-contenedor">
                        <select id="provincia" name="provincia_id" class="seleccion">
                            <option value="">Todas las provincias</option>
                            <?php foreach ($provincias as $prov): ?>
                            <option value="<?php echo $prov['id']; ?>"
                                    data-comunidad-id="<?php echo $prov['comunidad_id'] ?? ''; ?>"
                                    <?php echo $provinciaId == $prov['id'] ? 'selected' : ''; ?>>
                                <?php echo limpiar($prov['nombre']); ?> (<?php echo $prov['total_crematorios']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Valoración Mínima -->
                <div class="formulario-grupo">
                    <label for="valoracion" class="formulario-etiqueta">Valoración Mínima</label>
                    <div class="seleccion-contenedor">
                        <select id="valoracion" name="valoracion_minima" class="seleccion">
                            <option value="">Todas las valoraciones</option>
                            <option value="5" <?php echo $valoracionMin == 5 ? 'selected' : ''; ?>>5 estrellas</option>
                            <option value="4" <?php echo $valoracionMin == 4 ? 'selected' : ''; ?>>4+ estrellas</option>
                            <option value="3" <?php echo $valoracionMin == 3 ? 'selected' : ''; ?>>3+ estrellas</option>
                            <option value="2" <?php echo $valoracionMin == 2 ? 'selected' : ''; ?>>2+ estrellas</option>
                            <option value="1" <?php echo $valoracionMin == 1 ? 'selected' : ''; ?>>1+ estrellas</option>
                        </select>
                    </div>
                </div>

                <!-- Ordenar -->
                <div class="formulario-grupo">
                    <label for="orden" class="formulario-etiqueta">Ordenar por</label>
                    <div class="seleccion-contenedor">
                        <select id="orden" name="orden" class="seleccion">
                            <option value="">Mejor valorados</option>
                            <option value="nombre">Nombre A-Z</option>
                            <option value="calificacion">Calificación</option>
                            <option value="recientes">Más recientes</option>
                        </select>
                    </div>
                </div>

                <!-- Botones -->
                <div style="display: flex; flex-direction: column; gap: var(--espacio-dos); margin-top: var(--espacio-cuatro); padding-top: var(--espacio-cuatro); border-top: 1px solid var(--color-cinco);">
                    <button type="submit" class="boton uno">
                        <i data-lucide="filter" class="icono"></i>
                        Aplicar filtros
                    </button>

                    <a href="directorio.php" class="boton dos">
                        <i data-lucide="x" class="icono"></i>
                        Limpiar filtros
                    </a>
                </div>
            </form>
        </aside>

        <!-- MAIN - Resultados -->
        <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

            <!-- Header de resultados -->
            <div style="padding-bottom: var(--espacio-cuatro); border-bottom: 1px solid var(--color-cinco);">
                <p style="color: var(--color-seis-claro); font-size: var(--fs-tres);">
                    <strong><?php echo $totalCrematorios; ?> crematorio<?php echo $totalCrematorios !== 1 ? 's' : ''; ?></strong> encontrado<?php echo $totalCrematorios !== 1 ? 's' : ''; ?>
                    <?php if (!empty($filtros)): ?>
                    <span style="font-size: var(--fs-dos);">(filtrado)</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Lista de crematorios -->
            <div class="grid-tarjetas">
                <?php if (empty($crematorios)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--espacio-seis);">
                    <i data-lucide="search-x" style="width: 48px; height: 48px; color: var(--color-cinco); margin-bottom: var(--espacio-tres);"></i>
                    <p style="font-size: var(--fs-tres); color: var(--color-seis-claro);">No se encontraron crematorios con los filtros seleccionados.</p>
                    <a href="directorio.php" class="boton dos" style="margin-top: var(--espacio-cuatro);">Ver todos los crematorios</a>
                </div>
                <?php else: ?>
                <?php foreach ($crematorios as $crem): ?>
                <article class="tarjeta">
                    <div class="tarjeta__imagen">
                        <?php if (!empty($crem['foto_principal'])): ?>
                        <img
                            src="<?php echo limpiar($crem['foto_principal']); ?>"
                            alt="<?php echo limpiar($crem['nombre']); ?>"
                            loading="lazy"
                            onerror="this.parentElement.innerHTML='<div class=\'tarjeta__imagen--placeholder\'><i data-lucide=\'heart\' class=\'icono\'></i></div><?php if (!empty($crem['destacado'])): ?><span class=\'tarjeta__destacado\'>Destacado</span><?php endif; ?>'; lucide.createIcons();"
                        >
                        <?php else: ?>
                        <div class="tarjeta__imagen--placeholder">
                            <i data-lucide="heart" class="icono"></i>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($crem['destacado'])): ?>
                        <span class="tarjeta__destacado">Destacado</span>
                        <?php endif; ?>
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

                        <p class="tarjeta__descripcion corta">
                            <?php echo limpiar($crem['descripcion_corta'] ?? 'Servicios de cremación de mascotas profesional y respetuoso.'); ?>
                        </p>

                        <div class="tarjeta__footer">
                            <div class="tarjeta__valoracion">
                                <i data-lucide="star" class="icono icono--llena"></i>
                                <span><?php echo number_format($crem['rating'] ?? 0, 1); ?></span>
                            </div>
                            <a href="<?php echo generarUrl('crematorio', $crem['slug']); ?>" class="boton uno pequeno">
                                Ver detalles
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
            <?php
            // Construir query string para mantener filtros
            $queryParams = $_GET;
            unset($queryParams['pagina']);
            $queryString = http_build_query($queryParams);
            $baseUrl = 'directorio.php' . ($queryString ? '?' . $queryString . '&' : '?');
            ?>
            <nav class="paginacion" aria-label="Paginación">
                <!-- Anterior -->
                <?php if ($pagina > 1): ?>
                <a href="<?php echo $baseUrl; ?>pagina=<?php echo $pagina - 1; ?>" class="paginacion__enlace">
                    <i data-lucide="chevron-left" class="icono"></i>
                </a>
                <?php else: ?>
                <span class="paginacion__enlace" style="opacity: 0.5; cursor: not-allowed;">
                    <i data-lucide="chevron-left" class="icono"></i>
                </span>
                <?php endif; ?>

                <!-- Números de página -->
                <?php
                $rango = 2;
                $inicio = max(1, $pagina - $rango);
                $fin = min($totalPaginas, $pagina + $rango);

                if ($inicio > 1): ?>
                <a href="<?php echo $baseUrl; ?>pagina=1" class="paginacion__enlace">1</a>
                <?php if ($inicio > 2): ?>
                <span class="paginacion__enlace">...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                <a href="<?php echo $baseUrl; ?>pagina=<?php echo $i; ?>" class="paginacion__enlace <?php echo $i === $pagina ? 'activo' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($fin < $totalPaginas): ?>
                <?php if ($fin < $totalPaginas - 1): ?>
                <span class="paginacion__enlace">...</span>
                <?php endif; ?>
                <a href="<?php echo $baseUrl; ?>pagina=<?php echo $totalPaginas; ?>" class="paginacion__enlace"><?php echo $totalPaginas; ?></a>
                <?php endif; ?>

                <!-- Siguiente -->
                <?php if ($pagina < $totalPaginas): ?>
                <a href="<?php echo $baseUrl; ?>pagina=<?php echo $pagina + 1; ?>" class="paginacion__enlace">
                    <i data-lucide="chevron-right" class="icono"></i>
                </a>
                <?php else: ?>
                <span class="paginacion__enlace" style="opacity: 0.5; cursor: not-allowed;">
                    <i data-lucide="chevron-right" class="icono"></i>
                </span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        </div>
    </div>

    <!-- Script específico de la página -->
    <script>
        // Filtrado dinámico de provincias según comunidad
        document.addEventListener('DOMContentLoaded', function() {
            const comunidadSelect = document.getElementById('comunidad');
            const provinciaSelect = document.getElementById('provincia');

            if (comunidadSelect && provinciaSelect) {
                // Guardar todas las provincias
                const todasProvincias = Array.from(provinciaSelect.options).slice(1);

                comunidadSelect.addEventListener('change', function() {
                    const comunidadId = this.value;

                    // Limpiar provincias
                    provinciaSelect.innerHTML = '<option value="">Todas las provincias</option>';

                    if (comunidadId) {
                        // Filtrar provincias de la comunidad seleccionada
                        todasProvincias.forEach(option => {
                            if (option.dataset.comunidadId === comunidadId) {
                                provinciaSelect.appendChild(option.cloneNode(true));
                            }
                        });

                        // Si no hay provincias, mostrar todas
                        if (provinciaSelect.options.length === 1) {
                            todasProvincias.forEach(option => {
                                provinciaSelect.appendChild(option.cloneNode(true));
                            });
                        }
                    } else {
                        // Restaurar todas
                        todasProvincias.forEach(option => {
                            provinciaSelect.appendChild(option.cloneNode(true));
                        });
                    }
                });
            }
        });
    </script>

<?php include 'includes/footer.php'; ?>
