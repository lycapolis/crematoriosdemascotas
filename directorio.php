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

$titulo_pagina = 'Directorio de Crematorios de Mascotas - España';
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     ESTILOS ESPECÍFICOS DE DIRECTORIO
     ═══════════════════════════════════════════════════════════ -->
<style>
    /* ═══════════════════════════════════════════════════════════
       MÓVIL (Base: 0-767px) - Filtros verticales arriba
       ═══════════════════════════════════════════════════════════ */

    /* Layout encabezado: en mobile stack vertical; en desktop 2 columnas 50/50
       (izquierda = directorio-encabezado, derecha = directorio-orden) */
    .directorio-encabezado-layout {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
        padding: var(--espacio-tres) var(--espacio-cuatro) 0;
    }

    /* Bloque derecho: orden — alineado al final (right) en su mitad */
    .directorio-orden {
        display: flex;
        align-items: center;
        gap: var(--espacio-dos);
    }

    .directorio-orden__label {
        font-size: var(--fs-uno);
        color: var(--color-seis-claro);
        white-space: nowrap;
        margin: 0;
    }

    .directorio-orden .ts-wrapper,
    .directorio-orden > select {
        min-width: 180px;
        max-width: 240px;
    }

    .directorio-layout {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
        padding: var(--espacio-tres) var(--espacio-cuatro);
    }

    .filtros-sidebar-01 {
        background: var(--color-ocho);
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-dos);
        padding: var(--espacio-tres);
    }

    .filtros-sidebar-01__borrar {
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-uno);
        font-family: inherit;
        font-size: var(--fs-uno);
        color: var(--color-uno);
        text-decoration: none;
        padding: var(--espacio-uno) var(--espacio-dos);
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-uno);
        background: var(--color-ocho);
        cursor: pointer;
        transition: all .15s ease;
        width: 100%;
        justify-content: center;
    }

    .filtros-sidebar-01__borrar:hover {
        background: var(--color-uno-claro);
        border-color: var(--color-uno);
    }

    .filtros-sidebar-01__borrar .icono {
        width: 14px;
        height: 14px;
    }

    .filtros-sidebar-01__form {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }

    /* "Cerca de mí" arriba del todo, sin separador */
    .filtros-sidebar-01__cerca {
        width: 100%;
        justify-content: center;
    }

    .directorio-main {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-cuatro);
    }

    .directorio-vacio {
        text-align: center;
        padding: var(--espacio-seis);
    }

    .directorio-vacio .icono {
        width: 48px;
        height: 48px;
        color: var(--color-cinco);
        margin-bottom: var(--espacio-tres);
    }

    .directorio-vacio p {
        font-size: var(--fs-cuatro);
        font-weight: var(--peso-negrita);
        color: var(--color-dos);
        margin-bottom: var(--espacio-cuatro);
    }

    .directorio-vacio .boton.dos {
        background: var(--color-uno);
        border-color: var(--color-uno);
        color: var(--color-ocho);
    }
    .directorio-vacio .boton.dos:hover {
        background: var(--color-dos);
        border-color: var(--color-dos);
        color: var(--color-ocho);
    }

    .paginacion__enlace.deshabilitado {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ═══════════════════════════════════════════════════════════
       TABLET (768px - 1023px) - Filtros en 3 filas centradas
       ═══════════════════════════════════════════════════════════ */
    @media (min-width: 768px) {
        .directorio-encabezado-layout {
            padding: var(--espacio-tres) 0 0;
        }

        .directorio-layout {
            padding: var(--espacio-tres) 0;
        }

        /* Tarjeta sin esquinas ni bordes laterales */
        .filtros-sidebar-01 {
            border-radius: 0;
            border-left: none;
            border-right: none;
        }

        /* Form en columna centrada */
        .filtros-sidebar-01__form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--espacio-tres);
        }

        /* Input búsqueda más ancho (fila 1) */
        .filtros-sidebar-01__form > .field:first-child {
            width: 100%;
            max-width: 450px;
        }

        /* Contenedor dropdowns centrados (fila 2) */
        .filtros-sidebar-01__dropdowns {
            display: flex;
            justify-content: center;
            gap: var(--espacio-tres);
            flex-wrap: wrap;
        }

        .filtros-sidebar-01 .field__label {
            display: none;
        }

        .filtros-sidebar-01__cerca {
            max-width: 220px;
            margin: 0 auto;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       DESKTOP (1024px+) - Sidebar lateral
       ═══════════════════════════════════════════════════════════ */
    @media (min-width: 1024px) {
        /* 50/50: izquierda = encabezado, derecha = orden right-aligned.
           Asegura que el orden quede en su propia mitad → no se sobresale más
           a la derecha que las tarjetas. */
        .directorio-encabezado-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--espacio-cuatro);
            align-items: center;
        }

        .directorio-orden {
            justify-content: flex-end;
        }

        .directorio-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: var(--espacio-cuatro);
        }

        .filtros-sidebar-01 {
            height: fit-content;
            border-radius: var(--radio-dos);
            border: 1px solid var(--color-cinco);
        }

        .filtros-sidebar-01__form {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-tres);
        }

        .filtros-sidebar-01 .field__label {
            display: block;
        }

        .filtros-sidebar-01__form .field {
            width: 100%;
        }

        .filtros-sidebar-01__cerca {
            max-width: none;
            margin: 0;
        }
    }
</style>

<?php
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

// Geo unificado (dropdown con optgroups: "ccaa:N" o "prov:N")
// Retrocompatible con links que traigan ?comunidad_id / ?provincia_id sueltos
$comunidadId = (int)($_GET['comunidad_id'] ?? 0);
$provinciaId = (int)($_GET['provincia_id'] ?? 0);

$geoRaw = trim($_GET['geo'] ?? '');
if ($geoRaw !== '') {
    if (str_starts_with($geoRaw, 'ccaa:')) {
        $comunidadId = (int)substr($geoRaw, 5);
        $provinciaId = 0;
    } elseif (str_starts_with($geoRaw, 'prov:')) {
        $provinciaId = (int)substr($geoRaw, 5);
        $comunidadId = 0;
    }
}

if ($comunidadId > 0) $filtros['comunidad_id'] = $comunidadId;
if ($provinciaId > 0) $filtros['provincia_id'] = $provinciaId;

// Valor actual del dropdown geo (para el `selected`)
$geoActual = '';
if ($provinciaId > 0)       $geoActual = 'prov:' . $provinciaId;
elseif ($comunidadId > 0)   $geoActual = 'ccaa:' . $comunidadId;

// Filtro ciudad (texto libre, valor = nombre exacto)
$ciudadFiltro = trim($_GET['ciudad'] ?? '');
if ($ciudadFiltro !== '') $filtros['ciudad'] = $ciudadFiltro;

// Filtro de valoración mínima
$valoracionMin = (int)($_GET['valoracion_minima'] ?? 0);
if ($valoracionMin > 0) {
    $filtros['valoracion_min'] = $valoracionMin;
}

// Filtros de servicios (booleanos) — nombres = columnas en BD
$serviciosFiltrables = [
    'verificado',
    'cremacion_individual', 'cremacion_colectiva',
    'atencion_24h', 'sala_velatoria',
    'recogida_domicilio', 'entrega_domicilio',
    'urna', 'souvenires', 'carta', 'molde',
];
foreach ($serviciosFiltrables as $sk) {
    if (!empty($_GET[$sk])) $filtros[$sk] = 1;
}

// Filtro "abiertos ahora" (PHP-side, lee horarios JSON de cada ficha)
if (!empty($_GET['abiertos_ahora'])) $filtros['abiertos_ahora'] = 1;

// Orden — pasamos directo el value del dropdown ('', 'nombre', 'calificacion', 'recientes', 'mas_resenas')
$ordenActual = $_GET['orden'] ?? '';
if ($ordenActual !== '') $filtros['orden'] = $ordenActual;

// ═══════════════════════════════════════════════════════════
// OBTENER DATOS
// ═══════════════════════════════════════════════════════════
$comunidades = obtenerComunidades();
$provincias = obtenerProvincias();
$ciudades = obtenerCiudadesGlobal();

// Provincias agrupadas por CCAA para los <optgroup> del dropdown unificado
$provinciasPorCCAA = [];
foreach ($provincias as $prov) {
    $cid = (int)($prov['comunidad_id'] ?? 0);
    if ($cid > 0) $provinciasPorCCAA[$cid][] = $prov;
}

$resultado = obtenerCrematorios($filtros, $pagina, ITEMS_POR_PAGINA);

$crematorios = $resultado['datos'];
$totalCrematorios = $resultado['total'];
$totalPaginas = $resultado['paginas'];

// ¿Hay filtros activos? (para mostrar "Borrar filtros")
$hayFiltros = !empty($filtros) || !empty($_GET['orden']);
?>

    <!-- ═══════════════════════════════════════════════════════════
         ENCABEZADO 2 COLUMNAS (50/50 desktop, stack mobile):
         IZQUIERDA: directorio-encabezado (breadcrumb + título + badge + subtítulo)
         DERECHA:   directorio-orden        (label + dropdown right-aligned)
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor directorio-encabezado-layout">
        <div class="directorio-encabezado">
            <?php
            $migas = [
                ['Inicio',     BASE_URL . '/'],
                ['Directorio', null],
            ];
            include ROOT_PATH . '/includes/componentes/breadcrumb.php';
            ?>
            <div class="directorio-encabezado__fila">
                <h1 class="directorio-encabezado__titulo">Directorio de crematorios de mascotas</h1>
                <span class="directorio-encabezado__badge"><?php echo $totalCrematorios; ?> crematorio<?php echo $totalCrematorios !== 1 ? 's' : ''; ?> encontrado<?php echo $totalCrematorios !== 1 ? 's' : ''; ?></span>
            </div>
            <p class="directorio-encabezado__descripcion">Encuentra el crematorio ideal para despedir a tu mascota con dignidad.</p>
        </div>

        <div class="directorio-orden">
            <label for="orden" class="directorio-orden__label">Ordenar por:</label>
            <select id="orden" name="orden"
                    form="form-filtros-directorio"
                    class="field__select field__select--enhanced"
                    data-ts-search="off" data-ts-autosubmit="1">
                <option value=""             <?php echo $ordenActual === '' ? 'selected' : ''; ?>>Mejor valorados</option>
                <option value="mas_resenas"  <?php echo $ordenActual === 'mas_resenas' ? 'selected' : ''; ?>>Más reseñas</option>
                <option value="calificacion" <?php echo $ordenActual === 'calificacion' ? 'selected' : ''; ?>>Calificación</option>
                <option value="recientes"    <?php echo $ordenActual === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                <option value="nombre"       <?php echo $ordenActual === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
            </select>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         LAYOUT PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor directorio-layout">

        <!-- Filtros -->
        <div class="filtros-sidebar-01">
            <form action="<?php echo BASE_URL; ?>/directorio.php" method="GET" class="filtros-sidebar-01__form" id="form-filtros-directorio">
                <!-- Acción rápida arriba del todo (no es un filtro, lleva a /cerca) -->
                <button type="button" id="btn-cerca-dir" class="boton dos filtros-sidebar-01__cerca" onclick="irACerca(this)">
                    <i data-lucide="map-pin" class="icono"></i>
                    Cerca de mí
                </button>

                <?php if ($hayFiltros): ?>
                <!-- Borrar filtros: navega forzosamente a la URL limpia (sin params).
                     type="button" para que no submitea el form; window.location asegura
                     que el href absoluto no pueda ser sobrescrito por nada. -->
                <button type="button" class="filtros-sidebar-01__borrar"
                        onclick="window.location.href='<?php echo BASE_URL; ?>/directorio.php'; return false;">
                    <i data-lucide="x" class="icono"></i>
                    Borrar filtros
                </button>
                <?php endif; ?>

                <!-- Búsqueda -->
                <div class="field" style="margin-bottom: 0;">
                    <label for="busqueda" class="field__label">Buscar</label>
                    <div class="field__clear-wrap<?php echo $busqueda !== '' ? ' field__clear-wrap--has-value' : ''; ?>">
                        <input
                            type="text"
                            id="busqueda"
                            name="busqueda"
                            class="field__input"
                            placeholder="Nombre, ciudad o servicio…"
                            value="<?php echo limpiar($busqueda); ?>"
                        >
                        <button type="button" class="field__clear-btn" id="busqueda-clear" aria-label="Limpiar búsqueda" title="Limpiar búsqueda">
                            <i data-lucide="x" class="icono"></i>
                        </button>
                    </div>
                </div>

                <!-- Dropdowns en fila (tablet) -->
                <div class="filtros-sidebar-01__dropdowns">

                <!-- Geo: CCAA + Provincia en un solo dropdown con optgroups -->
                <div class="field" style="margin-bottom: 0;">
                    <label for="geo" class="field__label">Comunidad o provincia</label>
                    <select id="geo" name="geo" class="field__select field__select--enhanced" data-placeholder="Toda España" data-ts-autosubmit="1">
                        <option value="">Toda España</option>
                        <?php foreach ($comunidades as $com): ?>
                        <optgroup label="<?php echo limpiar($com['nombre']); ?>">
                            <option value="ccaa:<?php echo $com['id']; ?>"
                                    <?php echo $geoActual === 'ccaa:' . $com['id'] ? 'selected' : ''; ?>>
                                Toda <?php echo limpiar($com['nombre']); ?> (<?php echo $com['total_crematorios']; ?>)
                            </option>
                            <?php foreach ($provinciasPorCCAA[$com['id']] ?? [] as $prov): ?>
                            <option value="prov:<?php echo $prov['id']; ?>"
                                    data-comunidad-id="<?php echo $com['id']; ?>"
                                    <?php echo $geoActual === 'prov:' . $prov['id'] ? 'selected' : ''; ?>>
                                <?php echo limpiar($prov['nombre']); ?> (<?php echo $prov['total_crematorios']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ciudad — más granular, en cascada con geo -->
                <div class="field" style="margin-bottom: 0;">
                    <label for="ciudad" class="field__label">Ciudad</label>
                    <select id="ciudad" name="ciudad" class="field__select field__select--enhanced" data-placeholder="Todas las ciudades" data-ts-autosubmit="1">
                        <option value="">Todas las ciudades</option>
                        <?php foreach ($ciudades as $ciu):
                            // Cascade server-side: si ya hay geo elegida, filtrar
                            if ($provinciaId > 0 && (int)$ciu['provincia_id'] !== $provinciaId) continue;
                            if ($comunidadId > 0 && (int)$ciu['comunidad_id'] !== $comunidadId) continue;
                        ?>
                        <option value="<?php echo htmlspecialchars($ciu['nombre'], ENT_QUOTES); ?>"
                                data-comunidad-id="<?php echo (int)($ciu['comunidad_id'] ?? 0); ?>"
                                data-provincia-id="<?php echo (int)($ciu['provincia_id'] ?? 0); ?>"
                                <?php echo $ciudadFiltro === $ciu['nombre'] ? 'selected' : ''; ?>>
                            <?php echo limpiar($ciu['nombre']); ?> (<?php echo $ciu['total_crematorios']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label for="valoracion" class="field__label">Valoración mínima</label>
                    <select id="valoracion" name="valoracion_minima" class="field__select field__select--enhanced" data-ts-search="off" data-ts-autosubmit="1">
                        <option value="">Todas las valoraciones</option>
                        <option value="5" <?php echo $valoracionMin == 5 ? 'selected' : ''; ?>>5 estrellas</option>
                        <option value="4" <?php echo $valoracionMin == 4 ? 'selected' : ''; ?>>4+ estrellas</option>
                        <option value="3" <?php echo $valoracionMin == 3 ? 'selected' : ''; ?>>3+ estrellas</option>
                        <option value="2" <?php echo $valoracionMin == 2 ? 'selected' : ''; ?>>2+ estrellas</option>
                        <option value="1" <?php echo $valoracionMin == 1 ? 'selected' : ''; ?>>1+ estrellas</option>
                    </select>
                </div>

                </div><!-- /.filtros-sidebar-01__dropdowns -->

                <!-- Chips de servicios (los 10 booleanos de BD + Abiertos ahora) -->
                <div class="filtros-sidebar-01__chips">
                    <div class="field__label" style="margin-bottom: var(--espacio-dos);">Servicios</div>
                    <div style="display:flex; flex-wrap:wrap; gap:var(--espacio-dos);">
                        <?php
                        // Chips servicios: cada uno es [label, tooltip]. tooltip='' = sin tooltip
                        $chipsServicios = [
                            'abiertos_ahora'       => ['Abiertos ahora',        ''],
                            'verificado'           => ['Verificado',            'Un miembro del equipo se contactó con el crematorio para verificar la información publicada (contactos, servicios, dirección).'],
                            'cremacion_individual' => ['Cremación individual',  ''],
                            'cremacion_colectiva'  => ['Cremación colectiva',   ''],
                            'atencion_24h'         => ['Atención 24/7',         ''],
                            'sala_velatoria'       => ['Sala velatoria',        ''],
                            'recogida_domicilio'   => ['Recogida a domicilio',  ''],
                            'entrega_domicilio'    => ['Entrega a domicilio',   ''],
                            'urna'                 => ['Urna incluida',         ''],
                            'souvenires'           => ['Souvenirs',             ''],
                            'carta'                => ['Carta de condolencias', ''],
                            'molde'                => ['Molde de huella',       ''],
                        ];
                        foreach ($chipsServicios as $name => [$label, $tooltip]):
                            $checked = !empty($_GET[$name]) ? 'checked' : '';
                            // Bug fix: NO repetir atributo class — concatenar dentro del único class=
                            $clases = 'field__opcion' . ($tooltip ? ' tiene-tooltip' : '');
                            $dataTip = $tooltip ? ' data-tooltip="' . htmlspecialchars($tooltip, ENT_QUOTES) . '"' : '';
                        ?>
                        <label class="<?php echo $clases; ?>"<?php echo $dataTip; ?>>
                            <input type="checkbox" class="field__check" name="<?php echo $name; ?>" value="1" <?php echo $checked; ?> onchange="document.getElementById('form-filtros-directorio').submit()">
                            <span><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </form>
        </div>

        <!-- MAIN - Resultados -->
        <div class="directorio-main">

            <!-- Lista de crematorios -->
            <div class="grid-tarjetas <?php echo claseGridTarjetas(count($crematorios), 3); ?>">
                <?php if (empty($crematorios)): ?>
                <div class="directorio-vacio">
                    <i data-lucide="search-x" class="icono"></i>
                    <p>No se encontraron crematorios de mascotas con los filtros seleccionados.</p>
                    <a href="directorio.php" class="boton dos">Ver todos los crematorios de mascotas</a>
                </div>
                <?php else: ?>
                <?php foreach ($crematorios as $crem): ?>
                    <?php include __DIR__ . '/includes/componentes/tarjeta-crematorio.php'; ?>
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
                <span class="paginacion__enlace deshabilitado">
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
                <span class="paginacion__enlace deshabilitado">
                    <i data-lucide="chevron-right" class="icono"></i>
                </span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        </div>
    </div>

    <!-- Script específico de la página -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Debounced auto-submit del input de búsqueda (estilo Amazon: enter o pausa)
            // + Botón X para limpiar el input rápidamente
            const form = document.getElementById('form-filtros-directorio');
            const inputBusqueda = document.getElementById('busqueda');
            const clearBtn = document.getElementById('busqueda-clear');
            const wrapBusqueda = inputBusqueda ? inputBusqueda.closest('.field__clear-wrap') : null;

            function toggleClearBtn() {
                if (wrapBusqueda) {
                    wrapBusqueda.classList.toggle('field__clear-wrap--has-value', inputBusqueda.value.length > 0);
                }
            }

            if (form && inputBusqueda) {
                let debounceId;
                inputBusqueda.addEventListener('input', function() {
                    toggleClearBtn();
                    clearTimeout(debounceId);
                    debounceId = setTimeout(() => form.submit(), 600);
                });
                inputBusqueda.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(debounceId);
                        form.submit();
                    }
                });
                // Click en la X → limpia y refresca resultados
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        inputBusqueda.value = '';
                        toggleClearBtn();
                        clearTimeout(debounceId);
                        form.submit();
                    });
                }
            }

            // "Cerca de mí" usa la función global irACerca() definida en header.php
            // (única implementación para los 4 botones del sitio).
        });
    </script>

<?php include 'includes/footer.php'; ?>
