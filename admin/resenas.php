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
$estados_validos = ['pendiente', 'aprobada', 'rechazada', 'spam', 'todas'];
$estado_explicito = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos, true);
$filtro_estado = $estado_explicito ? $_GET['estado'] : 'pendiente';

// Si el admin NO eligió pestaña y no hay reseñas pendientes, abrir directamente
// en "Aprobadas" para que el panel nunca se vea vacío por defecto.
if (!$estado_explicito) {
    $hayPendientes = (int) $pdo->query("SELECT COUNT(*) FROM resenas WHERE estado = 'pendiente'")->fetchColumn();
    if ($hayPendientes === 0) {
        $filtro_estado = 'aprobada';
    }
}

// Sub-filtro de spam (solo aplica en estado 'rechazada')
// valores: 'todas' (default), 'solo_spam', 'sin_spam'
$filtro_spam = $_GET['spam'] ?? 'todas';
if (!in_array($filtro_spam, ['todas', 'solo_spam', 'sin_spam'])) $filtro_spam = 'todas';

// Paginación
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$condiciones = [];
$params = [];

if ($filtro_estado === 'spam') {
    // Atajo: tab "Spam" = rechazadas con es_spam=1
    $condiciones[] = "r.estado = 'rechazada'";
    $condiciones[] = "r.es_spam = 1";
} elseif ($filtro_estado !== 'todas') {
    $condiciones[] = "r.estado = :estado";
    $params[':estado'] = $filtro_estado;

    // Sub-filtro solo aplica en rechazadas
    if ($filtro_estado === 'rechazada') {
        if ($filtro_spam === 'solo_spam') $condiciones[] = "r.es_spam = 1";
        if ($filtro_spam === 'sin_spam')  $condiciones[] = "r.es_spam = 0";
    }
}

// Filtro opcional por ficha (link "Ver todas en moderación" desde editar-ficha-negocio)
$filtro_crematorio = isset($_GET['crematorio_id']) ? (int) $_GET['crematorio_id'] : 0;
$crematorio_nombre_filtro = '';
if ($filtro_crematorio > 0) {
    $condiciones[] = "r.crematorio_id = :cid";
    $params[':cid'] = $filtro_crematorio;
    $stmtCrem = $pdo->prepare("SELECT nombre FROM crematorios WHERE id = :cid");
    $stmtCrem->execute([':cid' => $filtro_crematorio]);
    $crematorio_nombre_filtro = (string) $stmtCrem->fetchColumn();
}
// Sufijo de query para preservar el filtro de ficha al cambiar de tab
$cremQS = $filtro_crematorio ? '&crematorio_id=' . $filtro_crematorio : '';

$where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

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

// Cargar imágenes vinculadas a estas reseñas (tipo='cliente' con resena_id) — una sola query
$imagenesPorResena = [];
if (!empty($resenas)) {
    $ids = array_column($resenas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlImgs = "SELECT id, resena_id, ruta, alt_text, estado_llm, categoria
                FROM crematorio_imagenes
                WHERE resena_id IN ($placeholders)
                ORDER BY resena_id ASC, id ASC";
    $stmtImgs = $pdo->prepare($sqlImgs);
    $stmtImgs->execute($ids);
    while ($img = $stmtImgs->fetch(PDO::FETCH_ASSOC)) {
        $rid = (int) $img['resena_id'];
        if (!isset($imagenesPorResena[$rid])) $imagenesPorResena[$rid] = [];
        $imagenesPorResena[$rid][] = $img;
    }
}

// Contadores para tabs
$sql_contadores = "SELECT
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) AS aprobadas,
    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) AS rechazadas,
    SUM(CASE WHEN estado = 'rechazada' AND es_spam = 1 THEN 1 ELSE 0 END) AS spam,
    COUNT(*) AS total
    FROM resenas";
$contadores = $pdo->query($sql_contadores)->fetch();

$nSpam = (int)($contadores['spam'] ?? 0);

// Pill class por estado de reseña
function resEstadoPill(string $estado): string {
    return match ($estado) {
        'pendiente' => 'admin-pill--alerta',
        'aprobada'  => 'admin-pill--exito',
        'rechazada' => 'admin-pill--error',
        default     => '',
    };
}

$titulo_pagina = 'Reseñas — Panel';
include 'header.php';
?>

<div class="admin-page">

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Reseñas</h1>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo $total; ?></span> reseña<?php echo $total !== 1 ? 's' : ''; ?>
            <span class="admin-dash"></span>
            <?php
            echo $filtro_estado === 'todas' ? 'todas las reseñas'
                : ($filtro_estado === 'spam' ? 'filtrando SPAM'
                : 'filtrando ' . htmlspecialchars($filtro_estado) . 's');
            ?>
        </p>
    </header>

    <?php if ($filtro_crematorio > 0): ?>
    <!-- Filtro activo por ficha (llegado desde editar-ficha-negocio) -->
    <div class="admin-banner admin-banner--info" style="margin-bottom:var(--espacio-tres);">
        <i data-lucide="filter" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            Mostrando solo reseñas de <strong><?php echo htmlspecialchars($crematorio_nombre_filtro ?: ('ficha #' . $filtro_crematorio)); ?></strong>.
        </div>
        <a href="?estado=<?php echo htmlspecialchars($filtro_estado); ?>" class="boton dos pequeno">
            <i data-lucide="x" class="icono" style="width:14px;height:14px;"></i>
            Quitar filtro
        </a>
    </div>
    <?php endif; ?>

    <!-- ═══ Tabs de filtro ═══ -->
    <nav class="admin-tabs">
        <a href="?estado=pendiente<?php echo $cremQS; ?>" class="admin-tab<?php echo $filtro_estado === 'pendiente' ? ' admin-tab--activo' : ''; ?>">
            Pendientes <span class="admin-tab__count"><?php echo (int)($contadores['pendientes'] ?? 0); ?></span>
        </a>
        <a href="?estado=aprobada<?php echo $cremQS; ?>" class="admin-tab<?php echo $filtro_estado === 'aprobada' ? ' admin-tab--activo' : ''; ?>">
            Aprobadas <span class="admin-tab__count"><?php echo (int)($contadores['aprobadas'] ?? 0); ?></span>
        </a>
        <a href="?estado=rechazada<?php echo $cremQS; ?>" class="admin-tab<?php echo $filtro_estado === 'rechazada' ? ' admin-tab--activo' : ''; ?>">
            Rechazadas <span class="admin-tab__count"><?php echo (int)($contadores['rechazadas'] ?? 0); ?></span>
        </a>
        <?php if ($nSpam > 0 || $filtro_estado === 'spam'): ?>
        <a href="?estado=spam<?php echo $cremQS; ?>"
           class="admin-tab<?php echo $filtro_estado === 'spam' ? ' admin-tab--activo' : ''; ?>"
           style="color: var(--admin-tone-error-fg);">
            <i data-lucide="alert-triangle" class="icono" style="width:14px; height:14px;"></i>
            SPAM <span class="admin-tab__count"><?php echo $nSpam; ?></span>
        </a>
        <?php endif; ?>
        <a href="?estado=todas<?php echo $cremQS; ?>" class="admin-tab<?php echo $filtro_estado === 'todas' ? ' admin-tab--activo' : ''; ?>">
            Todas <span class="admin-tab__count"><?php echo (int)($contadores['total'] ?? 0); ?></span>
        </a>
    </nav>

    <!-- Sub-filtro spam (solo en tab "Rechazadas") -->
    <?php if ($filtro_estado === 'rechazada' && $nSpam > 0): ?>
    <div style="display: flex; gap: .5rem; align-items: center; margin-bottom: var(--espacio-cuatro); padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); flex-wrap: wrap;">
        <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave);">Filtrar rechazadas:</span>
        <a href="?estado=rechazada&spam=todas<?php echo $cremQS; ?>" class="boton <?php echo $filtro_spam === 'todas' ? 'uno' : 'dos'; ?> pequeno">Todas</a>
        <a href="?estado=rechazada&spam=solo_spam<?php echo $cremQS; ?>" class="boton <?php echo $filtro_spam === 'solo_spam' ? 'uno' : 'dos'; ?> pequeno">Solo SPAM</a>
        <a href="?estado=rechazada&spam=sin_spam<?php echo $cremQS; ?>" class="boton <?php echo $filtro_spam === 'sin_spam' ? 'uno' : 'dos'; ?> pequeno">Sin SPAM</a>
    </div>
    <?php endif; ?>

    <!-- ═══ Banner de eliminación masiva de SPAM ═══ -->
    <?php if ($nSpam > 0 && in_array($filtro_estado, ['spam', 'rechazada'], true)): ?>
    <div id="banner-spam" class="admin-spam-alert">
        <span class="admin-spam-alert__icon">
            <i data-lucide="alert-triangle" class="icono"></i>
        </span>
        <div class="admin-spam-alert__contenido">
            <span class="admin-spam-alert__titulo">
                <?php echo $nSpam; ?> reseña<?php echo $nSpam === 1 ? '' : 's'; ?> marcada<?php echo $nSpam === 1 ? '' : 's'; ?> como SPAM
            </span>
            <span class="admin-spam-alert__texto">
                Ya rechazadas — listas para borrarse en lote.
            </span>
        </div>
        <div class="admin-spam-alert__acciones">
            <button type="button" onclick="eliminarTodoSpam()" id="btn-eliminar-spam"
                    class="boton pequeno"
                    style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                <i data-lucide="trash-2" class="icono" style="width: 14px; height: 14px;"></i>
                Eliminar todo el SPAM (<?php echo $nSpam; ?>)
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Lista de reseñas ═══ -->
    <?php if (empty($resenas)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon">
            <i data-lucide="message-square" class="icono"></i>
        </div>
        <h3 class="admin-empty__titulo">
            <?php if ($filtro_estado === 'spam'): ?>
                Sin reseñas marcadas como SPAM
            <?php elseif ($filtro_estado === 'pendiente'): ?>
                Sin reseñas pendientes
            <?php elseif ($filtro_estado === 'todas'): ?>
                Sin reseñas todavía
            <?php else: ?>
                No hay reseñas <?php echo $filtro_estado === 'aprobada' ? 'aprobadas' : 'rechazadas'; ?>
            <?php endif; ?>
        </h3>
        <p class="admin-empty__texto">
            <?php if ($filtro_estado === 'spam'): ?>
                Todo limpio. Las reseñas marcadas como spam aparecen acá.
            <?php elseif ($filtro_estado === 'pendiente'): ?>
                Todo al día. Cuando llegue una nueva del público va a aparecer acá.
            <?php else: ?>
                Probá con otro filtro de estado.
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
        <?php foreach ($resenas as $resena):
            $esSpam = !empty($resena['es_spam']);
        ?>
        <article id="resena-<?php echo $resena['id']; ?>"
                 style="background: var(--admin-superficie); border: 1px solid <?php echo $esSpam ? 'var(--admin-tone-error-bord)' : 'var(--admin-linea)'; ?>; border-radius: var(--admin-r-md); padding: var(--espacio-cuatro); box-shadow: var(--admin-sombra-suave); <?php echo $esSpam ? 'background: linear-gradient(180deg, var(--admin-tone-error-bg) 0%, var(--admin-superficie) 30%);' : ''; ?>">

            <!-- Header de la reseña -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres); flex-wrap: wrap;">
                <div style="min-width: 0; flex: 1;">
                    <!-- Autor + estado + spam -->
                    <div style="display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; margin-bottom: .2rem;">
                        <span style="font-weight: 700; color: var(--admin-tinta-fuerte); font-size: var(--admin-h6); line-height: 1.3;">
                            <?php echo htmlspecialchars($resena['nombre']); ?>
                        </span>
                        <span class="admin-pill <?php echo resEstadoPill($resena['estado']); ?>">
                            <?php echo ucfirst($resena['estado']); ?>
                        </span>
                        <?php if ($esSpam): ?>
                        <span class="admin-pill admin-pill--stamp admin-pill--error">
                            <i data-lucide="alert-triangle" class="icono" style="width: 11px; height: 11px;"></i>
                            SPAM
                        </span>
                        <?php endif; ?>
                    </div>
                    <!-- Email + negocio -->
                    <div style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; font-size: var(--admin-body-sm); color: var(--admin-tinta-suave);">
                        <span><?php echo htmlspecialchars($resena['email']); ?></span>
                        <span class="admin-dash"></span>
                        <a href="<?php echo BASE_URL . '/' . htmlspecialchars($resena['crematorio_slug']); ?>" target="_blank"
                           class="admin-link" style="display: inline-flex; align-items: center; gap: .3rem;">
                            <i data-lucide="building-2" class="icono" style="width: 13px; height: 13px;"></i>
                            <?php echo htmlspecialchars($resena['crematorio_nombre']); ?>
                        </a>
                    </div>
                </div>
                <!-- Rating + fecha -->
                <div style="text-align: right; flex-shrink: 0;">
                    <div style="margin-bottom: .25rem; line-height: 1;">
                        <?php echo generarEstrellas($resena['calificacion']); ?>
                    </div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); font-variant-numeric: tabular-nums;">
                        <?php echo date('d/m/Y H:i', strtotime($resena['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Comentario -->
            <blockquote style="margin: 0 0 var(--espacio-tres) 0; padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); color: var(--admin-tinta); font-size: var(--admin-body-sm); line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;">
                <?php echo nl2br(htmlspecialchars($resena['comentario'])); ?>
            </blockquote>

            <!-- Motivo de rechazo (si aplica) -->
            <?php if ($resena['estado'] === 'rechazada' && !empty($resena['motivo_rechazo'])): ?>
            <div style="padding: var(--espacio-dos) var(--espacio-tres); background: var(--admin-tone-error-bg); border-radius: var(--admin-r-sm); margin-bottom: var(--espacio-tres); font-size: var(--admin-body-sm); color: var(--admin-tone-error-fg);">
                <strong>Motivo de rechazo:</strong> <?php echo htmlspecialchars($resena['motivo_rechazo']); ?>
            </div>
            <?php endif; ?>

            <!-- Imágenes adjuntas -->
            <?php
            $imgsRes = $imagenesPorResena[$resena['id']] ?? [];
            if (!empty($imgsRes)):
            ?>
            <div class="resena-imgs" id="resena-imgs-<?php echo $resena['id']; ?>"
                 data-resena-id="<?php echo $resena['id']; ?>"
                 data-resena-estado="<?php echo htmlspecialchars($resena['estado']); ?>"
                 style="margin-bottom: var(--espacio-tres);">
                <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin-bottom: .5rem; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;">
                    <i data-lucide="images" class="icono" style="width: 14px; height: 14px;"></i>
                    <span><strong style="color: var(--admin-tinta); font-variant-numeric: tabular-nums;"><?php echo count($imgsRes); ?></strong> imagen<?php echo count($imgsRes) === 1 ? '' : 'es'; ?> adjunta<?php echo count($imgsRes) === 1 ? '' : 's'; ?></span>
                    <span style="opacity: .8; font-style: italic;">— clic para ampliar<?php echo $resena['estado'] === 'pendiente' ? ' y revisar' : ''; ?>.</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .5rem;">
                    <?php foreach ($imgsRes as $idxImg => $img):
                        $rutaImg = str_replace('\\', '/', $img['ruta']);
                        $urlImg = BASE_URL . '/' . htmlspecialchars($rutaImg);
                        $altImg = htmlspecialchars($img['alt_text'] ?? 'Imagen de reseña');
                    ?>
                    <div class="img-thumb-wrap" id="img-thumb-<?php echo $img['id']; ?>"
                         data-img-id="<?php echo (int)$img['id']; ?>"
                         data-img-src="<?php echo $urlImg; ?>"
                         data-img-alt="<?php echo $altImg; ?>"
                         data-img-nombre="<?php echo htmlspecialchars(basename($rutaImg)); ?>"
                         style="position: relative; aspect-ratio: 1; border-radius: var(--admin-r-sm); overflow: hidden; border: 1px solid var(--admin-linea); background: var(--admin-papel-alt); transition: border-color .15s, box-shadow .15s;"
                         onmouseover="this.style.borderColor='var(--admin-brand)'; this.style.boxShadow='var(--admin-sombra-suave)';"
                         onmouseout="this.style.borderColor='var(--admin-linea)'; this.style.boxShadow='none';">
                        <button type="button" class="res-lb-thumb"
                                data-resena-id="<?php echo $resena['id']; ?>"
                                data-thumb-idx="<?php echo $idxImg; ?>"
                                aria-label="Ampliar imagen"
                                style="all: unset; cursor: zoom-in; display: block; width: 100%; height: 100%;">
                            <img src="<?php echo $urlImg; ?>"
                                 alt="<?php echo $altImg; ?>"
                                 style="width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none;">
                        </button>
                        <?php if ($resena['estado'] === 'pendiente'): ?>
                        <button type="button"
                                onclick="eliminarImagenResena(<?php echo $img['id']; ?>, <?php echo $resena['id']; ?>, this)"
                                title="Eliminar esta imagen"
                                aria-label="Eliminar imagen"
                                style="position: absolute; top: 4px; right: 4px; background: rgba(220,38,38,.92); color: #fff; border: 0; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: grid; place-items: center; transition: transform .12s, background .15s; box-shadow: 0 2px 6px rgba(0,0,0,.2);"
                                onmouseover="this.style.transform='scale(1.08)'; this.style.background='rgba(220,38,38,1)';"
                                onmouseout="this.style.transform=''; this.style.background='rgba(220,38,38,.92)';">
                            ✕
                        </button>
                        <?php endif; ?>
                        <?php if (!empty($img['estado_llm']) && $img['estado_llm'] === 'pendiente'): ?>
                        <span style="position: absolute; bottom: 4px; left: 4px; background: var(--admin-tone-alerta-fg); color: #fff; font-size: 0.625rem; padding: .1rem .4rem; border-radius: 4px; font-weight: 700; letter-spacing: .02em; pointer-events: none;">pendiente IA</span>
                        <?php elseif (!empty($img['categoria'])): ?>
                        <span style="position: absolute; bottom: 4px; left: 4px; background: rgba(44, 36, 23, 0.85); color: #fff; font-size: 0.625rem; padding: .1rem .4rem; border-radius: 4px; font-weight: 600; pointer-events: none;"><?php echo htmlspecialchars($img['categoria']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div style="display: flex; justify-content: flex-end; gap: .5rem; padding-top: var(--espacio-tres); border-top: 1px solid var(--admin-linea); flex-wrap: wrap; align-items: center;">

                <?php if ($resena['estado'] === 'pendiente'): ?>
                    <label class="spam-toggle"
                           style="display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .7rem; background: var(--admin-tone-error-bg); border-radius: var(--admin-r-sm); font-size: var(--admin-body-sm); color: var(--admin-tone-error-fg); cursor: pointer; font-weight: 600; user-select: none; margin-right: auto;"
                           title="Si la marcás como SPAM se trata como tal — al rechazar va directo a la tab SPAM.">
                        <input type="checkbox" class="field__check field__check--error"
                               data-spam-toggle="<?php echo $resena['id']; ?>"
                               onchange="toggleSpamUI(<?php echo $resena['id']; ?>, this.checked)">
                        Es SPAM
                    </label>
                    <button data-btn-aprobar="<?php echo $resena['id']; ?>"
                            onclick="accionResena(<?php echo $resena['id']; ?>, 'aprobar')" class="boton tres pequeno">
                        <i data-lucide="check" class="icono" style="width: 14px; height: 14px;"></i>
                        Aprobar
                    </button>
                    <button data-btn-rechazar="<?php echo $resena['id']; ?>"
                            onclick="accionResena(<?php echo $resena['id']; ?>, 'rechazar')" class="boton pequeno"
                            style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                        <i data-lucide="x" class="icono" style="width: 14px; height: 14px;"></i>
                        Rechazar
                    </button>
                <?php elseif ($resena['estado'] === 'aprobada'): ?>
                    <button onclick="accionResena(<?php echo $resena['id']; ?>, 'pausar')" class="boton dos pequeno"
                            title="Devuelve la reseña a 'pendiente' y la oculta del público">
                        <i data-lucide="pause" class="icono" style="width: 14px; height: 14px;"></i>
                        Pausar para revisión
                    </button>
                <?php elseif ($resena['estado'] === 'rechazada'): ?>
                    <button onclick="accionResena(<?php echo $resena['id']; ?>, 'pausar')" class="boton dos pequeno"
                            title="Devuelve la reseña a 'pendiente' para reevaluarla">
                        <i data-lucide="rotate-ccw" class="icono" style="width: 14px; height: 14px;"></i>
                        Reevaluar
                    </button>
                    <button onclick="accionResena(<?php echo $resena['id']; ?>, 'eliminar')" class="boton pequeno"
                            style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);"
                            title="Eliminar definitivamente esta reseña y sus imágenes adjuntas">
                        <i data-lucide="trash-2" class="icono" style="width: 14px; height: 14px;"></i>
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
        <a href="?estado=<?php echo $filtro_estado; ?>&pagina=<?php echo $i; ?><?php echo $cremQS; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno"
           style="min-width: 38px; text-align: center; padding: .35rem .7rem; font-variant-numeric: tabular-nums;">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ═══ Lightbox para imágenes de reseñas ═══ -->
<div id="res-lb" style="display:none; position:fixed; inset:0; z-index:200; background:rgba(28,20,12,.86); align-items:center; justify-content:center; padding:var(--espacio-cuatro); flex-direction:column; gap:var(--espacio-tres);">
    <button type="button" id="res-lb-close" aria-label="Cerrar"
            style="position:absolute; top:var(--espacio-cuatro); right:var(--espacio-cuatro); width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
            onmouseover="this.style.background='rgba(255,255,255,.22)'"
            onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <i data-lucide="x" class="icono" style="width:20px; height:20px;"></i>
    </button>
    <div style="position:relative; width:100%; max-width:1100px; flex:1; display:flex; align-items:center; justify-content:center; min-height:0;">
        <button type="button" id="res-lb-prev" aria-label="Anterior"
                style="position:absolute; left:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'"
                onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-left" class="icono" style="width:24px; height:24px;"></i>
        </button>
        <img id="res-lb-img" src="" alt=""
             style="max-width:100%; max-height:75vh; border-radius:var(--admin-r-md); box-shadow:0 8px 40px rgba(0,0,0,.4); object-fit:contain;">
        <button type="button" id="res-lb-next" aria-label="Siguiente"
                style="position:absolute; right:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'"
                onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-right" class="icono" style="width:24px; height:24px;"></i>
        </button>
    </div>
    <div style="display:flex; flex-direction:column; align-items:center; gap:.5rem; text-align:center; color:rgba(255,255,255,.92); font-size:var(--admin-body-sm);">
        <div id="res-lb-nombre" style="font-family:monospace; color:rgba(255,255,255,.7); font-size:var(--admin-body-sm);"></div>
        <div id="res-lb-counter" style="color:rgba(255,255,255,.55); font-size:var(--admin-kicker); font-variant-numeric:tabular-nums; letter-spacing:.04em; text-transform:uppercase;"></div>
        <button type="button" id="res-lb-delete"
                style="display:none; margin-top:.4rem; align-items:center; gap:.4rem; padding:.55rem 1rem; background:rgba(220,38,38,.92); color:#fff; border:0; border-radius:var(--admin-r-sm); cursor:pointer; font-size:var(--admin-body-sm); font-weight:600; transition:background .15s, transform .12s; box-shadow:0 2px 8px rgba(0,0,0,.25);"
                onmouseover="this.style.background='rgba(220,38,38,1)'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.background='rgba(220,38,38,.92)'; this.style.transform='';">
            <i data-lucide="trash-2" class="icono" style="width:16px; height:16px;"></i>
            <span>Eliminar imagen</span>
        </button>
    </div>
</div>

<script>
// ── Lightbox de imágenes de reseñas (delete inline) ──
(function() {
    const lb       = document.getElementById('res-lb');
    const imgEl    = document.getElementById('res-lb-img');
    const nameEl   = document.getElementById('res-lb-nombre');
    const counter  = document.getElementById('res-lb-counter');
    const closeBtn = document.getElementById('res-lb-close');
    const prevBtn  = document.getElementById('res-lb-prev');
    const nextBtn  = document.getElementById('res-lb-next');
    const delBtn   = document.getElementById('res-lb-delete');

    let resenaId = null;
    let estado   = null;
    let imgs     = [];
    let idx      = 0;

    function leerImgsDeResena(rid) {
        const container = document.getElementById('resena-imgs-' + rid);
        if (!container) return [];
        return [...container.querySelectorAll('.img-thumb-wrap')].map(w => ({
            id:     parseInt(w.dataset.imgId, 10),
            src:    w.dataset.imgSrc,
            alt:    w.dataset.imgAlt,
            nombre: w.dataset.imgNombre,
        }));
    }

    function render() {
        if (!imgs.length) { close(); return; }
        if (idx >= imgs.length) idx = imgs.length - 1;
        const it = imgs[idx];
        imgEl.src = it.src;
        imgEl.alt = it.alt;
        nameEl.textContent = it.nombre;
        counter.textContent = (idx + 1) + ' / ' + imgs.length;
        prevBtn.style.display = imgs.length > 1 ? '' : 'none';
        nextBtn.style.display = imgs.length > 1 ? '' : 'none';
        delBtn.style.display = estado === 'pendiente' ? 'inline-flex' : 'none';
    }

    function open(rid, startIdx) {
        const container = document.getElementById('resena-imgs-' + rid);
        if (!container) return;
        resenaId = rid;
        estado   = container.dataset.resenaEstado || '';
        imgs     = leerImgsDeResena(rid);
        idx      = Math.max(0, Math.min(startIdx | 0, imgs.length - 1));
        render();
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
    }

    function close() {
        lb.style.display = 'none';
        document.body.style.overflow = '';
    }

    function prev() { if (imgs.length) { idx = (idx - 1 + imgs.length) % imgs.length; render(); } }
    function next() { if (imgs.length) { idx = (idx + 1) % imgs.length; render(); } }

    function eliminarActual() {
        if (estado !== 'pendiente') return;
        const it = imgs[idx];
        if (!it) return;

        confirmar({
            titulo: 'Eliminar imagen',
            mensaje: 'Se borra el archivo y la fila en BD (irreversible). La reseña se conserva.<br><br>¿Eliminar esta imagen?',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () { proceder(); }
        });

        function proceder() {
        delBtn.disabled = true;
        const labelSpan = delBtn.querySelector('span');
        const labelTxt  = labelSpan ? labelSpan.textContent : '';
        if (labelSpan) labelSpan.textContent = 'Eliminando…';

        const body = new URLSearchParams({ imagen_id: it.id });

        fetch('<?php echo BASE_URL; ?>/admin/imagen-eliminar-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            delBtn.disabled = false;
            if (labelSpan) labelSpan.textContent = labelTxt || 'Eliminar imagen';

            if (!data.ok) {
                toast.error(data.error || data.mensaje || 'No se pudo eliminar');
                return;
            }

            // 1) Quitar thumb del DOM
            const thumb = document.getElementById('img-thumb-' + it.id);
            if (thumb) thumb.remove();

            // 2) Actualizar contador del bloque
            const container = document.getElementById('resena-imgs-' + resenaId);
            if (container) {
                const remaining = container.querySelectorAll('.img-thumb-wrap').length;
                const counterEl = container.querySelector('strong');
                if (counterEl) counterEl.textContent = remaining;
                if (remaining === 0) container.remove();
            }

            // 3) Re-leer array y avanzar / cerrar
            imgs = leerImgsDeResena(resenaId);
            if (!imgs.length) { close(); return; }
            render();
        })
        .catch(() => {
            delBtn.disabled = false;
            if (labelSpan) labelSpan.textContent = labelTxt || 'Eliminar imagen';
            toast.error('Error de conexión');
        });
        } // fin proceder()
    }

    // ── Bindings ──
    document.querySelectorAll('.res-lb-thumb').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            open(btn.dataset.resenaId, parseInt(btn.dataset.thumbIdx, 10) || 0);
        });
    });
    closeBtn.addEventListener('click', close);
    prevBtn.addEventListener('click', prev);
    nextBtn.addEventListener('click', next);
    delBtn.addEventListener('click', eliminarActual);
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    document.addEventListener('keydown', e => {
        if (lb.style.display !== 'flex') return;
        if (e.key === 'Escape')      close();
        if (e.key === 'ArrowLeft')   prev();
        if (e.key === 'ArrowRight')  next();
    });
})();
</script>

<script>
// ── UI: cuando se tilda "Es SPAM", oculta "Aprobar" y cambia "Rechazar" a "Rechazar como SPAM"
function toggleSpamUI(id, esSpam) {
    const btnAprobar  = document.querySelector('[data-btn-aprobar="' + id + '"]');
    const btnRechazar = document.querySelector('[data-btn-rechazar="' + id + '"]');
    if (btnAprobar) btnAprobar.style.display = esSpam ? 'none' : '';
    if (btnRechazar) {
        const textNodes = [...btnRechazar.childNodes].filter(n => n.nodeType === Node.TEXT_NODE);
        if (textNodes.length) textNodes[textNodes.length - 1].textContent = esSpam ? ' Rechazar como SPAM ' : ' Rechazar';
    }
}

function leerEsSpam(id) {
    const cb = document.querySelector('[data-spam-toggle="' + id + '"]');
    return cb ? !!cb.checked : false;
}

function accionResena(id, accion) {
    const imgsContainer = document.getElementById('resena-imgs-' + id);
    const nImgs = imgsContainer ? imgsContainer.querySelectorAll('.img-thumb-wrap').length : 0;
    const esSpam = accion === 'rechazar' ? leerEsSpam(id) : false;
    const imgsTxt = nImgs > 0 ? (nImgs + ' imagen' + (nImgs === 1 ? '' : 'es') + ' adjunta' + (nImgs === 1 ? '' : 's')) : '';

    function ejecutar(eliminarImgs) {
        const body = new URLSearchParams({ id: id, accion: accion });
        if (eliminarImgs) body.append('eliminar_imagenes', '1');
        if (accion === 'eliminar') body.append('confirmar', '1');
        if (esSpam) body.append('es_spam', '1');

        fetch('<?php echo BASE_URL; ?>/admin/resena-accion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                location.reload();
            } else {
                toast.error(data.mensaje || 'Error al procesar');
            }
        })
        .catch(() => toast.error('Error de conexión'));
    }

    if (accion === 'rechazar' && esSpam) {
        confirmar({
            titulo: 'Rechazar y marcar como SPAM',
            mensaje: 'La reseña pasa a la pestaña <strong>SPAM</strong> y deja de mostrarse al público. El texto' +
                     (nImgs > 0 ? ' y las ' + imgsTxt : '') + ' se conserva' + (nImgs > 0 ? 'n' : '') +
                     ' hasta que la borres definitivamente.',
            textoOK: 'Rechazar como SPAM',
            peligroso: true,
            onOK: function () { ejecutar(false); }
        });
    } else if (accion === 'rechazar') {
        confirmar({
            titulo: 'Rechazar reseña',
            mensaje: 'Queda en estado <strong>rechazada</strong>, no se muestra al público. Podés reevaluarla más tarde si te equivocaste.',
            textoOK: 'Rechazar',
            onOK: function () {
                if (nImgs > 0) {
                    confirmar({
                        titulo: 'Imágenes de la reseña',
                        mensaje: 'La reseña tiene ' + imgsTxt + '.<br><br>¿Borrar también las imágenes? ' +
                                 '<strong>Confirmar</strong> elimina archivos + BD (irreversible). ' +
                                 '<strong>Cancelar</strong> las conserva por si reevaluás.',
                        textoOK: 'Borrar imágenes',
                        textoCancelar: 'Conservar',
                        peligroso: true,
                        onOK:     function () { ejecutar(true); },
                        onCancel: function () { ejecutar(false); }
                    });
                } else {
                    ejecutar(false);
                }
            }
        });
    } else if (accion === 'pausar') {
        confirmar({
            titulo: 'Pausar reseña',
            mensaje: 'Vuelve a la cola de pendientes y deja de mostrarse al público hasta que la apruebes de nuevo.',
            textoOK: 'Pausar',
            onOK: function () { ejecutar(false); }
        });
    } else if (accion === 'eliminar') {
        confirmar({
            titulo: 'Eliminar reseña definitivamente',
            mensaje: 'Se borra la reseña completa (texto, autor, fechas)' +
                     (nImgs > 0 ? ' y ' + imgsTxt + ' (archivos + BD)' : '') +
                     '.<br><br>⚠ Acción irreversible. No vas a poder recuperar nada.',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () { ejecutar(false); }
        });
    } else {
        // aprobar u otra acción sin confirmación
        ejecutar(false);
    }
}

function eliminarImagenResena(imagenId, resenaId, btn) {
    confirmar({
        titulo: 'Eliminar imagen',
        mensaje: 'Se borra el archivo y la fila en BD (irreversible). La reseña se conserva.<br><br>¿Eliminar esta imagen?',
        textoOK: 'Eliminar',
        peligroso: true,
        onOK: function () { proceder(); }
    });

    function proceder() {
    btn.disabled = true;
    btn.innerHTML = '…';

    const body = new URLSearchParams({ imagen_id: imagenId });

    fetch('<?php echo BASE_URL; ?>/admin/imagen-eliminar-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Quitar el thumb del DOM con una animación corta
            const thumb = document.getElementById('img-thumb-' + imagenId);
            if (thumb) {
                thumb.style.transition = 'opacity .2s';
                thumb.style.opacity = '0';
                setTimeout(() => {
                    thumb.remove();
                    // Si era la última, actualizar contador o esconder el bloque
                    const container = document.getElementById('resena-imgs-' + resenaId);
                    if (container && container.querySelectorAll('.img-thumb-wrap').length === 0) {
                        container.remove();
                    } else if (container) {
                        const counter = container.querySelector('strong');
                        if (counter) {
                            const remaining = container.querySelectorAll('.img-thumb-wrap').length;
                            counter.textContent = remaining;
                        }
                    }
                }, 200);
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = '✕';
            toast.error(data.error || 'No se pudo eliminar');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '✕';
        toast.error('Error de conexión');
    });
    } // fin proceder()
}

// ── Eliminación masiva de todas las reseñas SPAM ───────────────────────────
function eliminarTodoSpam() {
    const btn = document.getElementById('btn-eliminar-spam');
    if (!btn) return;
    const n = parseInt((btn.textContent.match(/\((\d+)\)/) || [])[1] || '0', 10);
    if (n === 0) return;

    confirmar({
        titulo: 'Eliminar todo el SPAM',
        mensaje: 'Se borran las <strong>' + n + ' reseña' + (n === 1 ? '' : 's') + '</strong> completa' + (n === 1 ? '' : 's') +
                 ' (texto, autor, fechas) y todas las imágenes adjuntas (archivos + BD).' +
                 '<br><br>⚠ Acción irreversible. No vas a poder recuperar nada.',
        textoOK: 'Eliminar todo',
        peligroso: true,
        onOK: function () { proceder(); }
    });

    function proceder() {
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="icono" style="width:16px; height:16px;"></i> Eliminando…';

    fetch('<?php echo BASE_URL; ?>/admin/resena-eliminar-spam-lote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'confirmar=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            toast.ok(data.mensaje);
            setTimeout(function () { location.href = '?estado=rechazada'; }, 900);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="trash-2" class="icono" style="width:16px; height:16px;"></i> Eliminar todo el SPAM (' + n + ')';
            toast.error(data.mensaje || 'Error al procesar');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="trash-2" class="icono" style="width:16px; height:16px;"></i> Eliminar todo el SPAM (' + n + ')';
        toast.error('Error de conexión');
    });
    } // fin proceder()
}
</script>

<?php include 'footer.php'; ?>
