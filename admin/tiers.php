<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$pdo = obtenerConexion();

$msg = '';
if (isset($_GET['ok']))    $msg = ['tipo' => 'ok',    'texto' => htmlspecialchars(urldecode($_GET['ok']))];
if (isset($_GET['error'])) $msg = ['tipo' => 'error', 'texto' => htmlspecialchars(urldecode($_GET['error']))];

// Toggle activo
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $tierId = trim($_GET['id']);
    $row = $pdo->prepare("SELECT activo FROM tiers WHERE id = :id");
    $row->execute([':id' => $tierId]);
    $t = $row->fetch();
    if ($t) {
        $nuevo = $t['activo'] ? 0 : 1;
        $pdo->prepare("UPDATE tiers SET activo = :a WHERE id = :id")
            ->execute([':a' => $nuevo, ':id' => $tierId]);
        header('Location: tiers.php?ok=' . urlencode($nuevo ? 'Plan activado' : 'Plan desactivado'));
        exit;
    }
}

// Stats: crematorios por tier
$stats = $pdo->query(
    "SELECT tier, COUNT(*) as total FROM crematorios GROUP BY tier"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Todos los tiers (incluso inactivos)
$tiers = $pdo->query("SELECT * FROM tiers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Planes de Servicio — Admin';
include 'header.php';
?>

<div class="admin-page">

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap;">
            <h1 class="admin-page-title">Planes de Servicio</h1>
            <a href="editar-tier.php" class="boton uno pequeno">
                <i data-lucide="plus" class="icono" style="width:14px; height:14px;"></i>
                Nuevo plan
            </a>
        </div>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo count($tiers); ?></span> definidos
            <span class="admin-dash"></span>
            Define qué contenido se muestra en cada nivel
        </p>
    </header>

    <!-- Feedback ?ok/?error → toast (puente en footer). Banner inline removido. -->

    <!-- ═══ Lista de tiers ═══ -->
    <?php if (empty($tiers)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon">
            <i data-lucide="layers" class="icono"></i>
        </div>
        <h3 class="admin-empty__titulo">No hay planes configurados</h3>
        <p class="admin-empty__texto">Creá el primero para empezar a clasificar las fichas por nivel de suscripción.</p>
        <a href="editar-tier.php" class="boton uno pequeno" style="margin-top: var(--espacio-tres);">
            <i data-lucide="plus" class="icono" style="width:14px; height:14px;"></i>
            Crear primer plan
        </a>
    </div>
    <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
    <?php foreach ($tiers as $tier):
        $cremCount = $stats[$tier['id']] ?? 0;
        $logoF     = json_decode($tier['logo_fuentes'], true) ?? [];
        $portF     = json_decode($tier['portada_fuentes'], true) ?? [];
        $galPF     = json_decode($tier['galeria_principal_fuentes'], true) ?? [];
        $galCF     = json_decode($tier['galeria_categorias_fuentes'], true) ?? [];
        $inactivo  = !$tier['activo'];
    ?>
    <article style="background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); overflow: hidden; box-shadow: var(--admin-sombra-suave); <?php echo $inactivo ? 'opacity: .6;' : ''; ?>">

        <!-- ─── Header del tier ─── -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--espacio-tres); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--admin-papel-alt); border-bottom: 1px solid var(--admin-linea);">
            <div style="display: flex; align-items: center; gap: var(--espacio-tres); min-width: 0;">
                <span style="font-size: 1.75rem; font-weight: 700; color: var(--admin-tinta-fuerte); letter-spacing: -0.02em; min-width: 2.5rem; text-align: center; font-variant-numeric: tabular-nums;">
                    <?php echo htmlspecialchars($tier['id']); ?>
                </span>
                <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="font-size: var(--admin-body); font-weight: 700; color: var(--admin-tinta-fuerte);">
                            <?php echo htmlspecialchars($tier['nombre']); ?>
                        </span>
                        <?php if ($inactivo): ?>
                        <span class="admin-pill">inactivo</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($tier['descripcion']): ?>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin-top: 0.2rem;">
                        <?php echo htmlspecialchars($tier['descripcion']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: var(--espacio-cuatro); flex-wrap: wrap;">
                <!-- Precio -->
                <div style="text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--admin-brand); font-variant-numeric: tabular-nums; line-height: 1;">
                        <?php echo $tier['precio_mensual'] !== null ? '€' . number_format($tier['precio_mensual'], 0) : '—'; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--admin-tinta-suave); margin-top: 0.25rem;">/ mes</div>
                </div>
                <!-- Negocios -->
                <div style="text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums; line-height: 1;"><?php echo $cremCount; ?></div>
                    <div style="font-size: 0.75rem; color: var(--admin-tinta-suave); margin-top: 0.25rem;">negocios</div>
                </div>
                <!-- Acciones -->
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="editar-tier.php?id=<?php echo urlencode($tier['id']); ?>" class="boton dos pequeno">
                        <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                        Editar
                    </a>
                    <a href="tiers.php?toggle=1&id=<?php echo urlencode($tier['id']); ?>"
                       onclick="return confirmarLink(this, { titulo: '<?php echo $tier['activo'] ? 'Desactivar plan' : 'Activar plan'; ?>', mensaje: '<?php echo $tier['activo'] ? '¿Desactivar este plan? Dejará de aplicarse a nuevas fichas.' : '¿Activar este plan?'; ?>', textoOK: '<?php echo $tier['activo'] ? 'Desactivar' : 'Activar'; ?>' })"
                       class="boton dos pequeno">
                        <i data-lucide="<?php echo $tier['activo'] ? 'pause' : 'play'; ?>" class="icono" style="width:14px; height:14px;"></i>
                        <?php echo $tier['activo'] ? 'Desactivar' : 'Activar'; ?>
                    </a>
                    <?php if ($cremCount === 0): ?>
                    <a href="eliminar-tier.php?id=<?php echo urlencode($tier['id']); ?>"
                       onclick="return confirmarLink(this, { titulo: 'Eliminar plan', mensaje: '¿Eliminar el plan <strong><?php echo htmlspecialchars($tier['id']); ?></strong>? Esta acción no se puede deshacer.', textoOK: 'Eliminar', peligroso: true })"
                       class="boton pequeno"
                       style="background: transparent; color: var(--admin-tone-error-fg); border: 1px solid var(--admin-tone-error-bord);">
                        <i data-lucide="trash-2" class="icono" style="width:14px; height:14px;"></i>
                        Eliminar
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ─── Reglas de contenido ─── -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
            <?php
            $secciones = [
                'logo'               => ['label' => 'Logo',                  'mostrar' => $tier['logo_mostrar'],               'fuentes' => $logoF],
                'portada'            => ['label' => 'Portada',               'mostrar' => $tier['portada_mostrar'],            'fuentes' => $portF],
                'galeria_principal'  => ['label' => 'Galería principal',     'mostrar' => $tier['galeria_principal_mostrar'],  'fuentes' => $galPF],
                'galeria_categorias' => ['label' => 'Galerías por categoría','mostrar' => $tier['galeria_categorias_mostrar'], 'fuentes' => $galCF],
            ];
            foreach ($secciones as $sec):
                $on = (bool) $sec['mostrar'];
            ?>
            <div style="padding: var(--espacio-tres) var(--espacio-cuatro); border-right: 1px solid var(--admin-linea); border-bottom: 1px solid var(--admin-linea);">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.45rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $on ? 'var(--admin-tone-exito-fg)' : 'var(--admin-linea-fuerte)'; ?>; flex-shrink: 0;"></span>
                    <span style="font-size: var(--admin-body-sm); font-weight: 600; color: var(--admin-tinta-fuerte);">
                        <?php echo $sec['label']; ?>
                    </span>
                </div>
                <?php if ($on && !empty($sec['fuentes'])): ?>
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap; padding-left: 1rem;">
                    <?php foreach ($sec['fuentes'] as $f): ?>
                    <span class="admin-pill admin-pill--<?php echo $f === 'local' ? 'info' : 'alerta'; ?>">
                        <?php echo $f === 'local' ? 'Local' : 'URL'; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($on): ?>
                <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); padding-left: 1rem; font-style: italic;">sin fuentes</div>
                <?php else: ?>
                <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); padding-left: 1rem;">No incluido</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </article>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
