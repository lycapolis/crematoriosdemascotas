<?php
/**
 * Panel Admin — Fichas de demostración.
 *
 * Una ficha por plan (00, 01, 02, 03), ocultas al público y a Google (estado «demo»),
 * que se abren solo por URL directa. Sirven para enseñar a clientes potenciales cómo se
 * ve cada plan y para probar conversiones y avisos. Aquí se listan y se copian los enlaces.
 * Se crean/borran con admin/migrations/seed_fichas_demo.php.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$pdo = obtenerConexion();
$e   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$fichas = $pdo->query("SELECT id, slug, tier, nombre, ciudad,
                              (SELECT COUNT(*) FROM crematorio_imagenes i WHERE i.crematorio_id = crematorios.id AND i.tipo = 'galeria') AS fotos,
                              (SELECT COUNT(*) FROM resenas r WHERE r.crematorio_id = crematorios.id AND r.estado = 'aprobada') AS resenas
                       FROM crematorios WHERE estado = 'demo' ORDER BY tier")->fetchAll(PDO::FETCH_ASSOC);
$planes = obtenerTierRules();

$titulo_pagina = 'Fichas de demostración — Admin';
include 'header.php';
?>

<div class="admin-page">

    <p style="margin-bottom:var(--espacio-dos);">
        <a href="configuracion.php" class="admin-text-muted">← Configuración</a>
    </p>

    <header class="admin-page-header">
        <h1 class="admin-page-title">Fichas de demostración</h1>
        <p class="admin-page-subtitle">
            Una ficha de ejemplo por plan para enseñar a clientes potenciales cómo se ve cada uno.
            Están ocultas al público y a Google: solo se abren con su enlace. Reflejan cada plan tal como esté
            configurado en Planes. Para editar fichas reales, ve a <a href="fichas-negocios.php">Fichas de negocios</a>.
        </p>
    </header>

    <?php if (!$fichas): ?>
    <div class="admin-empty">
        <div class="admin-empty__titulo">Todavía no hay fichas de demostración</div>
        <div class="admin-empty__texto">Se crean por SSH con <code>php admin/migrations/seed_fichas_demo.php</code>.</div>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap" style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
        <tr>
            <th>Plan</th>
            <th>Ciudad</th>
            <th style="text-align:center;">Fotos</th>
            <th style="text-align:center;">Reseñas</th>
            <th>Enlace para compartir</th>
            <th class="admin-table__acciones"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fichas as $f):
            $url    = BASE_URL . '/' . $f['slug'];
            $plan   = preg_replace('/^Plan\s+\d+\s*[—-]\s*/u', '', $planes[$f['tier']]['nombre'] ?? $f['tier']);
        ?>
        <tr>
            <td><strong><?= $e($plan) ?></strong></td>
            <td><?= $e($f['ciudad']) ?></td>
            <td style="text-align:center;"><?= (int)$f['fotos'] ?></td>
            <td style="text-align:center;"><?= (int)$f['resenas'] ?></td>
            <td><a href="<?= $e($url) ?>" target="_blank" rel="noopener" style="word-break:break-all;"><?= $e($url) ?></a></td>
            <td class="admin-table__acciones">
                <button type="button" class="boton dos pequeno js-copiar-demo" data-url="<?= $e($url) ?>">Copiar enlace</button>
                <a href="editar-ficha-negocio.php?id=<?= (int)$f['id'] ?>" class="boton dos pequeno">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.js-copiar-demo').forEach(function (b) {
    b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.dataset.url).then(function () {
            var t = b.textContent; b.textContent = '¡Copiado!';
            setTimeout(function () { b.textContent = t; }, 1500);
        });
    });
});
</script>

<?php include 'footer.php'; ?>
