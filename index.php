<?php
/**
 * ═══════════════════════════════════════════════════════════
 * HOME - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Crematorios de Mascotas - Directorio España';
$pagina_actual = 'inicio';
include 'includes/header.php';

// ═══════════════════════════════════════════════════════════
// DATOS DINÁMICOS
// ═══════════════════════════════════════════════════════════
$comunidades = obtenerComunidades();
$provincias  = obtenerProvincias();
$destacados  = obtenerDestacados(DESTACADOS_HOME);
$ciudadesDropdown = obtenerCiudadesGlobal();

// Provincias agrupadas por CCAA para los <optgroup> del dropdown unificado
$provinciasPorCCAA = [];
foreach ($provincias as $prov) {
    $cid = (int)($prov['comunidad_id'] ?? 0);
    if ($cid > 0) $provinciasPorCCAA[$cid][] = $prov;
}

// La nube de ciudades ahora la maneja el partial includes/componentes/nube-ciudades.php
// (se incluye más abajo en este mismo archivo)
?>

<style>
    /* Hero con tarjeta de búsqueda consolidada (estilo idealista) */
    .home-hero {
        background: linear-gradient(135deg, var(--color-cinco) 0%, var(--color-cuatro) 100%);
        padding: var(--espacio-cinco) var(--espacio-cuatro) var(--espacio-seis);
        text-align: center;
    }
    .home-hero h1 { margin: 0 0 var(--espacio-cuatro); }

    /* Tarjeta de búsqueda */
    .filtros-horizontal-01 {
        max-width: 980px;
        margin: var(--espacio-cuatro) auto 0;
        padding: var(--espacio-cuatro);
        background: var(--admin-superficie);
        border: 1px solid var(--admin-linea);
        border-radius: var(--radio-dos);
        box-shadow: var(--admin-sombra-suave);
        text-align: left;
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }
    .filtros-horizontal-01__row { display: grid; gap: var(--espacio-tres); grid-template-columns: 1fr; }

    /* Input de búsqueda con icono prefijo */
    .filtros-horizontal-01__buscar { position: relative; }
    .filtros-horizontal-01__buscar input {
        width: 100%;
        padding: 0.75rem 0.9rem 0.75rem 2.6rem;
        border: 1px solid var(--admin-linea-fuerte);
        border-radius: var(--admin-r-sm);
        font-family: var(--fuente-texto);
        font-size: var(--admin-body-sm);
        color: var(--admin-tinta-fuerte);
        background: var(--admin-superficie);
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }
    .filtros-horizontal-01__buscar input:focus {
        outline: none;
        border-color: var(--admin-brand);
        box-shadow: var(--admin-sombra-foco);
    }
    .filtros-horizontal-01__buscar .icono {
        position: absolute; top: 50%; left: var(--espacio-tres);
        transform: translateY(-50%);
        width: 18px; height: 18px;
        color: var(--color-seis-claro);
        pointer-events: none;
    }

    .filtros-horizontal-01__chips { display: flex; flex-wrap: wrap; gap: var(--espacio-dos); }

    /* (Estilo de la nube de ciudades ahora vive en componentes.css como .nube-ciudades — reusable en home + páginas geo + ficha) */

    @media (min-width: 600px) {
        .filtros-horizontal-01__row--principal { grid-template-columns: 1fr auto; align-items: center; }
    }
    @media (min-width: 768px) {
        .filtros-horizontal-01__row--dropdowns { grid-template-columns: repeat(2, 1fr); }
        .filtros-horizontal-01__row--extras    { grid-template-columns: auto 1fr; align-items: center; }
    }
    @media (min-width: 1024px) {
        .filtros-horizontal-01__row--dropdowns { grid-template-columns: repeat(4, 1fr); }
    }

</style>

<!-- ═══════════════════════════════════════════════════════════
     HERO  —  Búsqueda + filtros + cerca de mí, todo consolidado
     ═══════════════════════════════════════════════════════════ -->
<section class="home-hero">
    <div class="contenedor">
        <h1>Encuentra el lugar perfecto para despedir a tu mascota</h1>

        <form action="directorio.php" method="GET" class="filtros-horizontal-01">
            <!-- Fila 1: buscador por palabra + botón principal -->
            <div class="filtros-horizontal-01__row filtros-horizontal-01__row--principal">
                <div class="filtros-horizontal-01__buscar">
                    <i data-lucide="search" class="icono"></i>
                    <input
                        type="text"
                        name="busqueda"
                        placeholder="Buscar por nombre, ciudad o servicio…"
                        aria-label="Buscar crematorios"
                    >
                </div>
                <button type="submit" class="boton uno" style="height:44px;">
                    <i data-lucide="search" class="icono"></i>
                    Buscar
                </button>
            </div>

            <!-- Fila 2: 4 dropdowns -->
            <div class="filtros-horizontal-01__row filtros-horizontal-01__row--dropdowns">
                <!-- Geo: CCAA + Provincia en un solo dropdown con optgroups -->
                <div class="field" style="margin-bottom:0;">
                    <label for="h-geo" class="field__label">Comunidad o provincia</label>
                    <select name="geo" id="h-geo" class="field__select field__select--enhanced" data-placeholder="Toda España">
                        <option value="">Toda España</option>
                        <?php foreach ($comunidades as $com): ?>
                        <optgroup label="<?php echo limpiar($com['nombre']); ?>">
                            <option value="ccaa:<?php echo $com['id']; ?>">Toda <?php echo limpiar($com['nombre']); ?> (<?php echo $com['total_crematorios']; ?>)</option>
                            <?php foreach ($provinciasPorCCAA[$com['id']] ?? [] as $prov): ?>
                            <option value="prov:<?php echo $prov['id']; ?>" data-comunidad-id="<?php echo $com['id']; ?>">
                                <?php echo limpiar($prov['nombre']); ?> (<?php echo $prov['total_crematorios']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Ciudad — más granular, en cascada con geo -->
                <div class="field" style="margin-bottom:0;">
                    <label for="h-ciudad" class="field__label">Ciudad</label>
                    <select name="ciudad" id="h-ciudad" class="field__select field__select--enhanced" data-placeholder="Todas">
                        <option value="">Todas las ciudades</option>
                        <?php foreach ($ciudadesDropdown as $ciu): ?>
                        <option value="<?php echo htmlspecialchars($ciu['nombre'], ENT_QUOTES); ?>"
                                data-comunidad-id="<?php echo (int)($ciu['comunidad_id'] ?? 0); ?>"
                                data-provincia-id="<?php echo (int)($ciu['provincia_id'] ?? 0); ?>">
                            <?php echo limpiar($ciu['nombre']); ?> (<?php echo $ciu['total_crematorios']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="h-valoracion" class="field__label">Valoración mín.</label>
                    <select name="valoracion_minima" id="h-valoracion" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="">Todas</option>
                        <option value="5">5 estrellas</option>
                        <option value="4">4+ estrellas</option>
                        <option value="3">3+ estrellas</option>
                        <option value="2">2+ estrellas</option>
                        <option value="1">1+ estrellas</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="h-orden" class="field__label">Ordenar por</label>
                    <select name="orden" id="h-orden" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="">Mejor valorados</option>
                        <option value="mas_resenas">Más reseñas</option>
                        <option value="calificacion">Calificación</option>
                        <option value="recientes">Más recientes</option>
                        <option value="nombre">Nombre A-Z</option>
                    </select>
                </div>
            </div>

            <!-- Fila 3: Cerca de mí (izquierda, diagonal con el del header) + servicios -->
            <div class="filtros-horizontal-01__row filtros-horizontal-01__row--extras">
                <button id="btn-cerca" type="button" onclick="irACerca(this)" class="boton dos">
                    <i data-lucide="map-pin" class="icono"></i>
                    Cerca de mí
                </button>
                <div class="filtros-horizontal-01__chips">
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="abiertos_ahora" value="1"><span>Abiertos ahora</span></label>
                    <label class="field__opcion tiene-tooltip" data-tooltip="Un miembro del equipo se contactó con el crematorio para verificar la información publicada (contactos, servicios, dirección)."><input type="checkbox" class="field__check" name="verificado" value="1"><span>Verificado</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="cremacion_individual" value="1"><span>Cremación individual</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="cremacion_colectiva" value="1"><span>Cremación colectiva</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="recogida_domicilio" value="1"><span>Recogida a domicilio</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="entrega_domicilio" value="1"><span>Entrega a domicilio</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="atencion_24h" value="1"><span>Atención 24/7</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="sala_velatoria" value="1"><span>Sala velatoria</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="urna" value="1"><span>Urna incluida</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="souvenires" value="1"><span>Souvenirs</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="carta" value="1"><span>Carta de condolencias</span></label>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="molde" value="1"><span>Molde de huella</span></label>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CREMATORIOS DESTACADOS  (usa el partial compartido)
     Filtros avanzados eliminados de home: duplicaban los de directorio.php
     donde tienen su contexto natural. Más espacio para que las tarjetas
     queden por encima del scroll inicial.
     ═══════════════════════════════════════════════════════════ -->
<section class="seccion">
    <div class="contenedor">
        <div class="seccion__encabezado">
            <h2 class="seccion__titulo">Crematorios de mascotas destacados</h2>
        </div>

        <div class="grid-tarjetas <?php echo claseGridTarjetas(count($destacados)); ?>">
            <?php if (empty($destacados)): ?>
            <p style="text-align: center; grid-column: 1 / -1;">No hay crematorios destacados disponibles.</p>
            <?php else: foreach ($destacados as $crem): ?>
                <?php include __DIR__ . '/includes/componentes/tarjeta-crematorio.php'; ?>
            <?php endforeach; endif; ?>
        </div>

        <div style="text-align: center; margin-top: var(--espacio-cinco);">
            <a href="directorio.php" class="boton cuatro grande">
                Ver todos los crematorios
                <i data-lucide="arrow-right" class="icono"></i>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     NUBE DE CIUDADES  —  Internal linking SEO + nav rápida
     (partial reusable: includes/componentes/nube-ciudades.php)
     ═══════════════════════════════════════════════════════════ -->
<?php
$nubeScope  = 'todas';
$nubeTitulo = 'Crematorios de mascotas por ciudad';
$nubeLimite = 30;
include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
?>

<?php include 'includes/footer.php'; ?>
