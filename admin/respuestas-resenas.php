<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PANEL DE RESPUESTAS DE NEGOCIOS A RESEÑAS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 * Cola de moderación de las respuestas que los negocios envían desde
 * responder-resena.php. Nada se publica en la ficha hasta que se aprueba aquí.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('moderacion');

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

$estados_validos  = ['pendiente', 'aprobada', 'rechazada', 'todas'];
$estado_explicito = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos, true);
$filtro_estado    = $estado_explicito ? $_GET['estado'] : 'pendiente';

$contadores = $pdo->query("SELECT
    SUM(estado = 'pendiente') AS pendientes,
    SUM(estado = 'aprobada')  AS aprobadas,
    SUM(estado = 'rechazada') AS rechazadas,
    COUNT(*) AS total
    FROM resenas_respuestas")->fetch();

// Sin pestaña elegida y sin pendientes: abrir en Aprobadas para que el panel no se vea vacío
if (!$estado_explicito && (int)($contadores['pendientes'] ?? 0) === 0 && (int)($contadores['aprobadas'] ?? 0) > 0) {
    $filtro_estado = 'aprobada';
}

$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset     = ($pagina - 1) * $por_pagina;

$where  = $filtro_estado !== 'todas' ? 'WHERE p.estado = :estado' : '';
$params = $filtro_estado !== 'todas' ? [':estado' => $filtro_estado] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM resenas_respuestas p $where");
$stmt->execute($params);
$total         = (int)$stmt->fetchColumn();
$total_paginas = (int)ceil($total / $por_pagina);

$stmt = $pdo->prepare("SELECT p.*, r.nombre AS resena_autor, r.comentario AS resena_comentario,
                              r.calificacion, r.created_at AS resena_fecha,
                              c.nombre AS crematorio_nombre, c.slug AS crematorio_slug
                       FROM resenas_respuestas p
                       JOIN resenas r ON r.id = p.resena_id
                       JOIN crematorios c ON c.id = r.crematorio_id
                       $where
                       ORDER BY p.created_at DESC
                       LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$respuestas = $stmt->fetchAll();

function respEstadoPill(string $estado): string {
    return match ($estado) {
        'pendiente' => 'admin-pill--alerta',
        'aprobada'  => 'admin-pill--exito',
        'rechazada' => 'admin-pill--error',
        default     => '',
    };
}

$titulo_pagina = 'Respuestas a reseñas — Panel';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <h1 class="admin-page-title">Respuestas a reseñas</h1>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo $total; ?></span> respuesta<?php echo $total !== 1 ? 's' : ''; ?>
            <span class="admin-dash"></span>
            Los negocios responden desde el enlace del email; nada se publica sin tu aprobación.
        </p>
    </header>

    <nav class="admin-tabs">
        <a href="resenas.php" class="admin-tab">
            <i data-lucide="arrow-left" class="icono" style="width:14px;height:14px;"></i> Reseñas
        </a>
        <a href="?estado=pendiente" class="admin-tab<?php echo $filtro_estado === 'pendiente' ? ' admin-tab--activo' : ''; ?>">
            Pendientes <span class="admin-tab__count"><?php echo (int)($contadores['pendientes'] ?? 0); ?></span>
        </a>
        <a href="?estado=aprobada" class="admin-tab<?php echo $filtro_estado === 'aprobada' ? ' admin-tab--activo' : ''; ?>">
            Aprobadas <span class="admin-tab__count"><?php echo (int)($contadores['aprobadas'] ?? 0); ?></span>
        </a>
        <a href="?estado=rechazada" class="admin-tab<?php echo $filtro_estado === 'rechazada' ? ' admin-tab--activo' : ''; ?>">
            Rechazadas <span class="admin-tab__count"><?php echo (int)($contadores['rechazadas'] ?? 0); ?></span>
        </a>
        <a href="?estado=todas" class="admin-tab<?php echo $filtro_estado === 'todas' ? ' admin-tab--activo' : ''; ?>">
            Todas <span class="admin-tab__count"><?php echo (int)($contadores['total'] ?? 0); ?></span>
        </a>
    </nav>

    <?php if (empty($respuestas)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon"><i data-lucide="message-square-reply" class="icono"></i></div>
        <h3 class="admin-empty__titulo"><?php echo $filtro_estado === 'pendiente' ? 'Sin respuestas pendientes' : 'No hay respuestas en esta vista'; ?></h3>
        <p class="admin-empty__texto">Cuando un negocio responda a una reseña desde el enlace del email, aparecerá aquí para que la revises.</p>
    </div>
    <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
        <?php foreach ($respuestas as $r): ?>
        <article id="respuesta-<?php echo (int)$r['id']; ?>"
                 style="background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); padding: var(--espacio-cuatro); box-shadow: var(--admin-sombra-suave);">

            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres); flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: .55rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($r['crematorio_slug']); ?>#lista-resenas" target="_blank" class="admin-link"
                       style="display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; font-size: var(--admin-h6);">
                        <i data-lucide="building-2" class="icono" style="width: 14px; height: 14px;"></i>
                        <?php echo htmlspecialchars($r['crematorio_nombre']); ?>
                    </a>
                    <span class="admin-pill <?php echo respEstadoPill($r['estado']); ?>"><?php echo ucfirst($r['estado']); ?></span>
                </div>
                <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); font-variant-numeric: tabular-nums;">
                    Enviada el <?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?>
                </div>
            </div>

            <!-- Reseña original -->
            <div style="margin-bottom: var(--espacio-tres);">
                <div style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin-bottom: .3rem;">
                    <span>Reseña de <strong style="color: var(--admin-tinta);"><?php echo htmlspecialchars($r['resena_autor']); ?></strong></span>
                    <span><?php echo generarEstrellas($r['calificacion']); ?></span>                </div>
                <blockquote style="margin: 0; padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); color: var(--admin-tinta); font-size: var(--admin-body-sm); line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;">
                    <?php echo nl2br(htmlspecialchars($r['resena_comentario'])); ?>
                </blockquote>
            </div>

            <!-- Respuesta del negocio (tal como se verá en la ficha) -->
            <div style="margin-bottom: var(--espacio-tres);">
                <div style="font-size: var(--admin-kicker); letter-spacing: .04em; text-transform: uppercase; color: var(--admin-tinta-suave); margin-bottom: .3rem;">
                    Respuesta del negocio
                </div>
                <blockquote style="margin: 0; padding: var(--espacio-tres); border-left: 3px solid var(--admin-brand); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); color: var(--admin-tinta); font-size: var(--admin-body-sm); line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;">
                    <?php echo nl2br(htmlspecialchars($r['texto'])); ?>
                </blockquote>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: .5rem; padding-top: var(--espacio-tres); border-top: 1px solid var(--admin-linea); flex-wrap: wrap; align-items: center;">
                <?php if ($r['estado'] === 'pendiente'): ?>
                    <button onclick="accionRespuesta(<?php echo (int)$r['id']; ?>, 'aprobar')" class="boton tres pequeno">
                        <i data-lucide="check" class="icono" style="width: 14px; height: 14px;"></i> Aprobar
                    </button>
                    <button onclick="accionRespuesta(<?php echo (int)$r['id']; ?>, 'rechazar')" class="boton pequeno"
                            style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                        <i data-lucide="x" class="icono" style="width: 14px; height: 14px;"></i> Rechazar
                    </button>
                <?php elseif ($r['estado'] === 'aprobada'): ?>
                    <button onclick="accionRespuesta(<?php echo (int)$r['id']; ?>, 'pausar')" class="boton dos pequeno"
                            title="La devuelve a pendiente y la oculta de la ficha">
                        <i data-lucide="pause" class="icono" style="width: 14px; height: 14px;"></i> Pausar para revisión
                    </button>
                <?php else: ?>
                    <button onclick="accionRespuesta(<?php echo (int)$r['id']; ?>, 'pausar')" class="boton dos pequeno"
                            title="La devuelve a pendiente para reevaluarla">
                        <i data-lucide="rotate-ccw" class="icono" style="width: 14px; height: 14px;"></i> Reevaluar
                    </button>
                    <button onclick="accionRespuesta(<?php echo (int)$r['id']; ?>, 'eliminar')" class="boton pequeno"
                            style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                        <i data-lucide="trash-2" class="icono" style="width: 14px; height: 14px;"></i> Eliminar definitivamente
                    </button>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div style="display: flex; justify-content: center; gap: .35rem; margin-top: var(--espacio-cinco); flex-wrap: wrap;">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $i; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno"
           style="min-width: 38px; text-align: center; padding: .35rem .7rem; font-variant-numeric: tabular-nums;"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function accionRespuesta(id, accion) {
    function ejecutar() {
        const body = new URLSearchParams({ id: id, accion: accion });
        if (accion === 'eliminar') body.append('confirmar', '1');
        fetch('<?php echo BASE_URL; ?>/admin/respuesta-accion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => data.ok ? location.reload() : toast.error(data.mensaje || 'Error al procesar'))
        .catch(() => toast.error('Error de conexión'));
    }

    if (accion === 'aprobar') {
        confirmar({
            titulo: 'Aprobar respuesta',
            mensaje: 'Se publica bajo la reseña en la ficha pública y se avisa por email al negocio.',
            textoOK: 'Aprobar',
            onOK: ejecutar
        });
    } else if (accion === 'rechazar') {
        confirmar({
            titulo: 'Rechazar respuesta',
            mensaje: 'No se publica. El negocio podrá enviar otra pasadas 24 h desde el envío. No se le avisa del rechazo.',
            textoOK: 'Rechazar',
            onOK: ejecutar
        });
    } else if (accion === 'pausar') {
        confirmar({
            titulo: 'Devolver a pendiente',
            mensaje: 'Vuelve a la cola de pendientes y deja de mostrarse en la ficha hasta que la apruebes de nuevo.',
            textoOK: 'Confirmar',
            onOK: ejecutar
        });
    } else {
        confirmar({
            titulo: 'Eliminar respuesta definitivamente',
            mensaje: 'Se borra el texto de la respuesta.<br><br>⚠ Acción irreversible.',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: ejecutar
        });
    }
}
</script>

<?php include 'footer.php'; ?>
