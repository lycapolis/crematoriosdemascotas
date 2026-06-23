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
 * Versión: 04 — refresh Fase 6 (España operativo + teaser otros)
 *
 * Solo España es clickeable (es lo único operativo). Otros países
 * aparecen como teaser "próximamente" sin enlaces. Cuando se sumen
 * países reales, agregarlos al array $proximos pasándolos a operativos.
 *
 * URL: /paises/
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// España operativa: contar de la BD
$pdo = obtenerConexion();
$total_espana = (int) $pdo->query("SELECT COUNT(*) FROM crematorios")->fetchColumn();
$total_provincias = (int) $pdo->query("SELECT COUNT(DISTINCT provincia_id) FROM crematorios WHERE provincia_id IS NOT NULL")->fetchColumn();

// Países próximamente (sin enlaces — solo teaser visual)
$proximos = [
    'Europa'         => ['Portugal', 'Francia', 'Italia', 'Andorra'],
    'Latinoamérica'  => ['México', 'Argentina', 'Colombia', 'Chile', 'Perú', 'Ecuador', 'Uruguay', 'Brasil'],
];

$titulo_pagina = 'Países - Directorio de Crematorios de Mascotas';
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

<?php
$migas = [
    ['Inicio', BASE_URL . '/'],
    ['Países', null],
];
$tituloH1   = 'Directorio internacional de crematorios';
$badgeTotal = $total_espana . ' crematorio' . ($total_espana !== 1 ? 's' : '') . ' en España';
$descripcion = 'Por ahora solo cubrimos España. Estamos trabajando para sumar más países pronto.';
include ROOT_PATH . '/includes/componentes/encabezado-geo.php';
?>

<div class="contenedor seccion">

    <!-- ─── España (único operativo) ─── -->
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Disponible ahora</h2>
        <div class="lista-geo">
            <a href="<?php echo BASE_URL; ?>/espana/" class="lista-geo__item">
                <div>
                    <h3 class="lista-geo__item-titulo">España</h3>
                    <span class="lista-geo__item-meta"><?php echo $total_espana; ?> crematorio<?php echo $total_espana !== 1 ? 's' : ''; ?> en <?php echo $total_provincias; ?> provincia<?php echo $total_provincias !== 1 ? 's' : ''; ?></span>
                </div>
                <i data-lucide="chevron-right" class="icono lista-geo__item-flecha"></i>
            </a>
        </div>
    </section>

    <!-- ─── Próximamente (teaser sin enlaces) ─── -->
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Próximamente</h2>
        <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cuatro);">
            Estamos trabajando para sumar nuevos países al directorio. Si tenés un crematorio fuera de España y querés estar en la lista cuando habilitemos tu país, escribinos.
        </p>

        <?php foreach ($proximos as $region => $paises): ?>
        <div style="margin-bottom: var(--espacio-cuatro);">
            <h3 class="estilo-h5" style="color: var(--color-seis-claro); margin-bottom: var(--espacio-dos);"><?php echo $region; ?></h3>
            <div style="display: flex; flex-wrap: wrap; gap: var(--espacio-dos);">
                <?php foreach ($paises as $pais): ?>
                <span class="boton tres" style="opacity:.55; cursor:not-allowed; pointer-events:none;">
                    <?php echo limpiar($pais); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <a href="<?php echo BASE_URL; ?>/contacto.php" class="boton uno" style="display:inline-flex; align-items:center; gap:var(--espacio-uno);">
        <i data-lucide="mail" class="icono"></i>
        Solicitar que sumemos tu país
    </a>

</div>

<?php include 'includes/footer.php'; ?>
