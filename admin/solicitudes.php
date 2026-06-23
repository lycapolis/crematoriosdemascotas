<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PANEL DE SOLICITUDES DE REGISTRO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$admin = obtenerAdminActual();
$pdo = obtenerConexion();

// Filtro de estado
$estados_validos = ['pendiente', 'aprobada', 'rechazada', 'todas'];
$estado_explicito = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos, true);
$filtro_estado = $estado_explicito ? $_GET['estado'] : 'pendiente';

// Si el admin NO eligió pestaña y no hay solicitudes pendientes, abrir
// directamente en "Aprobadas" para que el panel nunca se vea vacío por defecto.
if (!$estado_explicito) {
    $hayPendientes = (int) $pdo->query("SELECT COUNT(*) FROM solicitudes_registro WHERE estado = 'pendiente'")->fetchColumn();
    if ($hayPendientes === 0) {
        $filtro_estado = 'aprobada';
    }
}

// Paginación
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = $filtro_estado !== 'todas' ? "WHERE s.estado = :estado" : "";
$params = $filtro_estado !== 'todas' ? [':estado' => $filtro_estado] : [];

// Contar total
$sql_count = "SELECT COUNT(*) FROM solicitudes_registro s $where";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Obtener solicitudes (con slug del crematorio para aprobadas)
$sql = "SELECT s.*, c.slug AS crematorio_slug
        FROM solicitudes_registro s
        LEFT JOIN crematorios c ON s.crematorio_id = c.id
        $where
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$solicitudes = $stmt->fetchAll();

// Contadores para tabs
$sql_contadores = "SELECT
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) AS aprobadas,
    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) AS rechazadas,
    COUNT(*) AS total
    FROM solicitudes_registro";
$contadores = $pdo->query($sql_contadores)->fetch();

// Set de IDs de solicitudes que este admin ya revisó (vista de detalle)
$vistasIds = isset($_SESSION['solicitudes_vistas']) && is_array($_SESSION['solicitudes_vistas'])
    ? array_keys($_SESSION['solicitudes_vistas'])
    : [];

$titulo_pagina = 'Solicitudes de Registro - Admin';
include 'header.php';
?>

<?php
// Pill class por estado
function solEstadoPill(string $estado): string {
    return match ($estado) {
        'pendiente' => 'admin-pill--alerta',
        'aprobada'  => 'admin-pill--exito',
        'rechazada' => 'admin-pill--error',
        default     => '',
    };
}
?>

<div class="admin-page">

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Solicitudes de registro</h1>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo $total; ?></span> solicitud<?php echo $total !== 1 ? 'es' : ''; ?>
            <span class="admin-dash"></span>
            <?php echo $filtro_estado === 'todas' ? 'todas las solicitudes' : 'filtrando ' . htmlspecialchars($filtro_estado) . 's'; ?>
        </p>
    </header>

    <!-- ═══ Tabs de filtro ═══ -->
    <nav class="admin-tabs">
        <a href="?estado=pendiente" class="admin-tab<?php echo $filtro_estado === 'pendiente' ? ' admin-tab--activo' : ''; ?>">
            Pendientes
            <span class="admin-tab__count"><?php echo (int)($contadores['pendientes'] ?? 0); ?></span>
        </a>
        <a href="?estado=aprobada" class="admin-tab<?php echo $filtro_estado === 'aprobada' ? ' admin-tab--activo' : ''; ?>">
            Aprobadas
            <span class="admin-tab__count"><?php echo (int)($contadores['aprobadas'] ?? 0); ?></span>
        </a>
        <a href="?estado=rechazada" class="admin-tab<?php echo $filtro_estado === 'rechazada' ? ' admin-tab--activo' : ''; ?>">
            Rechazadas
            <span class="admin-tab__count"><?php echo (int)($contadores['rechazadas'] ?? 0); ?></span>
        </a>
        <a href="?estado=todas" class="admin-tab<?php echo $filtro_estado === 'todas' ? ' admin-tab--activo' : ''; ?>">
            Todas
            <span class="admin-tab__count"><?php echo (int)($contadores['total'] ?? 0); ?></span>
        </a>
    </nav>

    <!-- ═══ Lista de solicitudes ═══ -->
    <?php if (empty($solicitudes)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon">
            <i data-lucide="inbox" class="icono"></i>
        </div>
        <h3 class="admin-empty__titulo">
            <?php if ($filtro_estado === 'pendiente'): ?>
                Sin solicitudes pendientes
            <?php elseif ($filtro_estado === 'todas'): ?>
                Sin solicitudes todavía
            <?php else: ?>
                No hay solicitudes <?php echo htmlspecialchars($filtro_estado); ?>s
            <?php endif; ?>
        </h3>
        <p class="admin-empty__texto">
            <?php if ($filtro_estado === 'pendiente'): ?>
                Todo al día. Las nuevas solicitudes desde el form público van a aparecer acá.
            <?php else: ?>
                Probá con otro filtro de estado.
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
        <?php foreach ($solicitudes as $solicitud): ?>
        <article style="background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); padding: var(--espacio-cuatro); box-shadow: var(--admin-sombra-suave);" id="solicitud-<?php echo $solicitud['id']; ?>">

            <!-- Header de la solicitud -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres); flex-wrap: wrap;">
                <div style="min-width: 0; flex: 1;">
                    <div style="display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; margin-bottom: .2rem;">
                        <span style="font-weight: 700; color: var(--admin-tinta-fuerte); font-size: var(--admin-h6); line-height: 1.3;">
                            <?php echo htmlspecialchars($solicitud['nombre_negocio']); ?>
                        </span>
                        <span class="admin-pill <?php echo solEstadoPill($solicitud['estado']); ?>">
                            <?php echo ucfirst($solicitud['estado']); ?>
                        </span>
                    </div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); display: inline-flex; align-items: center; gap: .35rem;">
                        <i data-lucide="map-pin" class="icono" style="width: 14px; height: 14px;"></i>
                        <?php echo htmlspecialchars($solicitud['ciudad']); ?>, <?php echo htmlspecialchars($solicitud['provincia']); ?>
                        <?php if ($solicitud['pais'] !== 'España'): ?>
                        <span class="admin-dash"></span><?php echo htmlspecialchars($solicitud['pais']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); font-variant-numeric: tabular-nums; white-space: nowrap;">
                    #<?php echo (int)$solicitud['id']; ?>
                    <span class="admin-dash"></span>
                    <?php echo date('d/m/Y H:i', strtotime($solicitud['created_at'])); ?>
                </div>
            </div>

            <!-- Info de contacto -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--espacio-tres); margin-bottom: var(--espacio-tres); padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm);">
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .2rem;">Contacto comercial</div>
                    <div style="font-weight: 600; color: var(--admin-tinta-fuerte); font-size: var(--admin-body-sm);"><?php echo htmlspecialchars($solicitud['contacto_nombre']); ?></div>
                    <div style="font-size: var(--admin-body-sm); margin-top: .15rem;">
                        <a href="mailto:<?php echo htmlspecialchars($solicitud['contacto_email']); ?>" class="admin-link">
                            <?php echo htmlspecialchars($solicitud['contacto_email']); ?>
                        </a>
                    </div>
                </div>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .2rem;">Teléfono contacto</div>
                    <div style="font-size: var(--admin-body-sm); font-variant-numeric: tabular-nums;">
                        <a href="tel:<?php echo htmlspecialchars($solicitud['contacto_telefono']); ?>" class="admin-link">
                            <?php echo htmlspecialchars($solicitud['contacto_telefono']); ?>
                        </a>
                    </div>
                </div>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .2rem;">Teléfono clientes</div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;">
                        <?php echo htmlspecialchars($solicitud['telefono_clientes'] ?? '—'); ?>
                    </div>
                </div>
            </div>

            <!-- Descripción (truncada) -->
            <?php
            $desc = $solicitud['descripcion'];
            $descCorta = mb_strlen($desc) > 200 ? mb_substr($desc, 0, 200) . '…' : $desc;
            ?>
            <p style="color: var(--admin-tinta); line-height: 1.6; margin: 0 0 var(--espacio-tres) 0; font-size: var(--admin-body-sm);">
                <?php echo htmlspecialchars($descCorta); ?>
            </p>

            <!-- Acciones -->
            <div style="display: flex; justify-content: flex-end; gap: .5rem; padding-top: var(--espacio-tres); border-top: 1px solid var(--admin-linea); flex-wrap: wrap;">
                <a href="solicitud-ver.php?id=<?php echo $solicitud['id']; ?>" class="boton dos pequeno">
                    <i data-lucide="eye" class="icono" style="width:14px; height:14px;"></i>
                    Ver detalle
                </a>
                <?php if ($solicitud['estado'] === 'aprobada' && !empty($solicitud['crematorio_slug'])): ?>
                <a href="<?php echo BASE_URL . '/' . urlencode($solicitud['crematorio_slug']); ?>" target="_blank" class="boton tres pequeno">
                    <i data-lucide="external-link" class="icono" style="width:14px; height:14px;"></i>
                    Ver ficha pública
                </a>
                <a href="editar-ficha-negocio.php?id=<?php echo (int)$solicitud['crematorio_id']; ?>" class="boton dos pequeno">
                    <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                    Editar ficha
                </a>
                <?php endif; ?>
                <?php if ($solicitud['estado'] === 'pendiente'):
                    $yaVista = in_array((int)$solicitud['id'], array_map('intval', $vistasIds), true);
                ?>
                <a href="solicitud-editar.php?id=<?php echo $solicitud['id']; ?>" class="boton dos pequeno">
                    <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                    Editar
                </a>
                <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'aprobar', <?php echo $yaVista ? '1' : '0'; ?>)" class="boton tres pequeno">
                    <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                    Aprobar
                </button>
                <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'rechazar', <?php echo $yaVista ? '1' : '0'; ?>)" class="boton pequeno"
                        style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                    <i data-lucide="x" class="icono" style="width:14px; height:14px;"></i>
                    Rechazar
                </button>
                <?php endif; ?>
                <?php if ($solicitud['estado'] === 'rechazada'): ?>
                <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'reevaluar')" class="boton dos pequeno"
                        title="Devolver a pendiente para reevaluar">
                    <i data-lucide="rotate-ccw" class="icono" style="width:14px; height:14px;"></i>
                    Reevaluar
                </button>
                <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'eliminar')" class="boton pequeno"
                        style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);"
                        title="Eliminar definitivamente esta solicitud y sus imágenes">
                    <i data-lucide="trash-2" class="icono" style="width:14px; height:14px;"></i>
                    Eliminar definitivamente
                </button>
                <?php endif; ?>
            </div>

        </article>
        <?php endforeach; ?>
    </div>

    <!-- ═══ Paginación ═══ -->
    <?php if ($total_paginas > 1): ?>
    <div style="display: flex; justify-content: center; gap: .35rem; margin-top: var(--espacio-cinco); flex-wrap: wrap;">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $i; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno"
           style="min-width: 38px; text-align: center; padding: .35rem .7rem; font-variant-numeric: tabular-nums;">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function accionSolicitud(id, accion, yaVista) {
    yaVista = yaVista === 1 || yaVista === '1' || yaVista === true;
    const body = new URLSearchParams({ id: id, accion: accion });

    function enviar() {
        fetch('<?php echo BASE_URL; ?>/admin/solicitud-accion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (accion === 'aprobar' && data.slug) {
                    toast.ok('Solicitud aprobada. Ficha creada: ' + data.slug);
                    setTimeout(function () { location.reload(); }, 1100);
                } else {
                    location.reload();
                }
            } else {
                toast.error(data.mensaje || 'Error al procesar');
            }
        })
        .catch(() => toast.error('Error de conexión'));
    }

    // Paso 2 — confirmación específica por acción, luego enviar()
    function paso2() {
        if (accion === 'rechazar') {
            // NOTA: prompt() nativo se mantiene por ahora — reemplazarlo requiere
            // un modal con input (mejora futura, fuera de scope 6b/6c).
            const motivo = prompt('Motivo del rechazo (opcional):');
            if (motivo === null) return;
            if (motivo) body.append('motivo', motivo);
            enviar();

        } else if (accion === 'aprobar') {
            confirmar({
                titulo: 'Aprobar solicitud',
                mensaje: '¿Aprobar esta solicitud y crear la ficha en el directorio?',
                textoOK: 'Aprobar',
                onOK: enviar
            });

        } else if (accion === 'reevaluar') {
            confirmar({
                titulo: 'Reevaluar solicitud',
                mensaje: 'Vuelve a la cola de pendientes, se borra el motivo de rechazo anterior y podés volver a aprobarla o rechazarla.',
                textoOK: 'Reevaluar',
                onOK: enviar
            });

        } else if (accion === 'eliminar') {
            confirmar({
                titulo: 'Eliminar solicitud definitivamente',
                mensaje: 'Se borra la fila completa (todos los datos) y todas las imágenes adjuntas (archivos + BD). No afecta a ninguna ficha pública.<br><br>⚠ Acción irreversible.',
                textoOK: 'Eliminar',
                peligroso: true,
                onOK: function () { body.append('confirmar', '1'); enviar(); }
            });
        }
    }

    // Paso 1 — advertencia si no abrió el detalle (solo aprobar/rechazar)
    if (!yaVista && (accion === 'aprobar' || accion === 'rechazar')) {
        confirmar({
            titulo: 'No revisaste el detalle',
            mensaje: 'No abriste "Ver detalle" de esta solicitud todavía. Es recomendable revisarla antes de actuar.<br><br>¿' +
                     (accion === 'aprobar' ? 'Aprobarla' : 'Rechazarla') + ' sin revisar?',
            textoOK: (accion === 'aprobar' ? 'Aprobar igual' : 'Rechazar igual'),
            peligroso: true,
            onOK: paso2
        });
    } else {
        paso2();
    }
}
</script>

<?php include 'footer.php'; ?>
