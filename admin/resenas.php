<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PANEL DE RESEÑAS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$admin = obtenerAdminActual();
$pdo = obtenerConexion();

// Filtro de estado
$filtro_estado = $_GET['estado'] ?? 'pendiente';
$estados_validos = ['pendiente', 'aprobada', 'rechazada', 'todas'];
if (!in_array($filtro_estado, $estados_validos)) {
    $filtro_estado = 'pendiente';
}

// Paginación
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = $filtro_estado !== 'todas' ? "WHERE r.estado = :estado" : "";
$params = $filtro_estado !== 'todas' ? [':estado' => $filtro_estado] : [];

// Contar total
$sql_count = "SELECT COUNT(*) FROM resenas r $where";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Obtener reseñas
$sql = "SELECT r.*, c.nombre AS crematorio_nombre, c.slug AS crematorio_slug
        FROM resenas r
        INNER JOIN crematorios c ON r.crematorio_id = c.id
        $where
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$resenas = $stmt->fetchAll();

// Contadores para tabs
$sql_contadores = "SELECT
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) AS aprobadas,
    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) AS rechazadas,
    COUNT(*) AS total
    FROM resenas";
$contadores = $pdo->query($sql_contadores)->fetch();

$titulo_pagina = 'Panel de Reseñas - Admin';
include 'header.php';
?>

<div class="contenedor" style="padding: var(--espacio-cinco) var(--espacio-cuatro); max-width: 1200px; margin: 0 auto;">

    <!-- Encabezado -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--espacio-cinco); flex-wrap: wrap; gap: var(--espacio-tres);">
        <h1 style="font-size: var(--fs-seis); color: var(--color-dos); margin: 0;">Moderación de Reseñas</h1>
        <span style="color: var(--color-seis); opacity: 0.7; font-size: var(--fs-dos);">
            <?php echo $total; ?> reseña<?php echo $total !== 1 ? 's' : ''; ?> encontrada<?php echo $total !== 1 ? 's' : ''; ?>
        </span>
    </div>

    <!-- Tabs de filtro -->
    <div style="display: flex; gap: var(--espacio-dos); margin-bottom: var(--espacio-cinco); border-bottom: 1px solid var(--color-cinco); padding-bottom: var(--espacio-tres); flex-wrap: wrap;">
        <a href="?estado=pendiente" class="boton <?php echo $filtro_estado === 'pendiente' ? 'uno' : 'dos'; ?> pequeno">
            Pendientes (<?php echo $contadores['pendientes'] ?? 0; ?>)
        </a>
        <a href="?estado=aprobada" class="boton <?php echo $filtro_estado === 'aprobada' ? 'uno' : 'dos'; ?> pequeno">
            Aprobadas (<?php echo $contadores['aprobadas'] ?? 0; ?>)
        </a>
        <a href="?estado=rechazada" class="boton <?php echo $filtro_estado === 'rechazada' ? 'uno' : 'dos'; ?> pequeno">
            Rechazadas (<?php echo $contadores['rechazadas'] ?? 0; ?>)
        </a>
        <a href="?estado=todas" class="boton <?php echo $filtro_estado === 'todas' ? 'uno' : 'dos'; ?> pequeno">
            Todas (<?php echo $contadores['total'] ?? 0; ?>)
        </a>
    </div>

    <!-- Lista de reseñas -->
    <?php if (empty($resenas)): ?>
    <div class="tarjeta simple" style="padding: var(--espacio-seis); text-align: center;">
        <i data-lucide="message-square" style="width: 48px; height: 48px; color: var(--color-seis); opacity: 0.5; margin-bottom: var(--espacio-tres);"></i>
        <p style="color: var(--color-seis);">No hay reseñas <?php echo $filtro_estado !== 'todas' ? $filtro_estado . 's' : ''; ?></p>
    </div>
    <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">
        <?php foreach ($resenas as $resena): ?>
        <article class="tarjeta simple" style="padding: var(--espacio-cuatro);" id="resena-<?php echo $resena['id']; ?>">

            <!-- Header de la reseña -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--espacio-tres); flex-wrap: wrap; gap: var(--espacio-dos);">
                <div>
                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno);">
                        <?php echo htmlspecialchars($resena['nombre']); ?>
                    </div>
                    <div style="font-size: var(--fs-uno); color: var(--color-seis); opacity: 0.7;">
                        <?php echo htmlspecialchars($resena['email']); ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="margin-bottom: var(--espacio-uno);">
                        <?php echo generarEstrellas($resena['calificacion']); ?>
                    </div>
                    <div style="font-size: var(--fs-uno); color: var(--color-seis); opacity: 0.7;">
                        <?php echo date('d/m/Y H:i', strtotime($resena['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Crematorio -->
            <div style="font-size: var(--fs-uno); color: var(--color-uno); margin-bottom: var(--espacio-tres);">
                <i data-lucide="building-2" class="icono" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($resena['crematorio_slug']); ?>" target="_blank" style="color: inherit; text-decoration: none;">
                    <?php echo htmlspecialchars($resena['crematorio_nombre']); ?>
                </a>
            </div>

            <!-- Comentario -->
            <p style="color: var(--color-seis); line-height: 1.6; margin-bottom: var(--espacio-cuatro); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-uno);">
                <?php echo nl2br(htmlspecialchars($resena['comentario'])); ?>
            </p>

            <!-- Estado actual y acciones -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--espacio-tres); border-top: 1px solid var(--color-cinco); flex-wrap: wrap; gap: var(--espacio-dos);">

                <!-- Badge de estado -->
                <span style="padding: var(--espacio-uno) var(--espacio-tres); border-radius: var(--radio-full); font-size: var(--fs-uno); font-weight: var(--peso-medio);
                    <?php
                    switch ($resena['estado']) {
                        case 'pendiente': echo 'background: var(--color-diez); color: white;'; break;
                        case 'aprobada': echo 'background: var(--color-tres); color: white;'; break;
                        case 'rechazada': echo 'background: var(--color-siete); color: white;'; break;
                    }
                    ?>">
                    <?php echo ucfirst($resena['estado']); ?>
                </span>

                <!-- Botones de acción -->
                <?php if ($resena['estado'] === 'pendiente'): ?>
                <div style="display: flex; gap: var(--espacio-dos);">
                    <button onclick="accionResena(<?php echo $resena['id']; ?>, 'aprobar')" class="boton tres pequeno">
                        <i data-lucide="check" class="icono" style="width: 16px; height: 16px;"></i>
                        Aprobar
                    </button>
                    <button onclick="accionResena(<?php echo $resena['id']; ?>, 'rechazar')" class="boton pequeno" style="background: var(--color-siete); color: white; border-color: var(--color-siete);">
                        <i data-lucide="x" class="icono" style="width: 16px; height: 16px;"></i>
                        Rechazar
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </article>
        <?php endforeach; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
    <div style="display: flex; justify-content: center; gap: var(--espacio-dos); margin-top: var(--espacio-cinco);">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $i; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function accionResena(id, accion) {
    if (accion === 'rechazar' && !confirm('¿Estás seguro de rechazar esta reseña?')) {
        return;
    }

    fetch('<?php echo BASE_URL; ?>/admin/resena-accion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&accion=' + accion
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            alert(data.mensaje || 'Error al procesar');
        }
    })
    .catch(() => alert('Error de conexión'));
}
</script>

<?php include 'footer.php'; ?>
