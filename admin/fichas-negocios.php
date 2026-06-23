<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LISTADO DE CREMATORIOS - PANEL ADMIN
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/completitud.php';

requerirAutenticacion();

$pdo = obtenerConexion();

// Completitud: definición declarativa única en includes/completitud.php
// (misma lógica que editar-ficha). El ORDEN del listado lo hace el score SQL
// de abajo; acá solo se usa el % para la barra visual por fila. Nota: a nivel
// listado las flags de imagen son "tiene alguna" (img_count/logo_count del
// query) en vez de "procesada" — limitación de data del listado, aceptable
// para el badge; el % autoritativo es el de editar-ficha.

// ─── Parámetros ──────────────────────────────────────────────────────────────

$busqueda  = trim($_GET['q']      ?? '');
$filCiudad = trim($_GET['ciudad'] ?? '');
$filTier   = trim($_GET['tier']   ?? '');
$filEstado = trim($_GET['estado'] ?? '');
$orden     = $_GET['orden']       ?? 'nombre_asc';
$pagina    = max(1, intval($_GET['pagina'] ?? 1));
$porPagina = 25;
$offset    = ($pagina - 1) * $porPagina;

$ordenesValidos = [
    'nombre_asc', 'nombre_desc',
    'id_asc', 'id_desc',
    'tier_asc', 'tier_desc',
    'resenas_desc', 'resenas_asc',
    'completitud_desc', 'completitud_asc',
    'imagenes_desc', 'imagenes_asc',
];
if (!in_array($orden, $ordenesValidos)) $orden = 'nombre_asc';

$estadosValidos = ['activa', 'pausada', 'cerrada', 'archivada'];

// ─── Listas para filtros ──────────────────────────────────────────────────────

$ciudades = $pdo->query("SELECT DISTINCT ciudad FROM crematorios WHERE ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad")->fetchAll(PDO::FETCH_COLUMN);
$tiers    = $pdo->query("SELECT DISTINCT tier    FROM crematorios WHERE tier    IS NOT NULL ORDER BY tier")->fetchAll(PDO::FETCH_COLUMN);

// ─── WHERE ───────────────────────────────────────────────────────────────────

$where  = ['1=1'];
$params = [];

if ($busqueda !== '') {
    $where[]       = '(c.nombre LIKE :q OR c.ciudad LIKE :q2 OR p.nombre LIKE :q3)';
    $params[':q']  = '%' . $busqueda . '%';
    $params[':q2'] = '%' . $busqueda . '%';
    $params[':q3'] = '%' . $busqueda . '%';
}
if ($filCiudad !== '') {
    $where[]          = 'c.ciudad = :ciudad';
    $params[':ciudad'] = $filCiudad;
}
if ($filTier !== '') {
    $where[]        = 'c.tier = :tier';
    $params[':tier'] = $filTier;
}
if ($filEstado !== '' && in_array($filEstado, $estadosValidos, true)) {
    $where[]          = 'c.estado = :estado';
    $params[':estado'] = $filEstado;
}

$whereSQL = implode(' AND ', $where);

// ─── ORDER ───────────────────────────────────────────────────────────────────

$orderSQL = match($orden) {
    'nombre_desc'      => 'c.nombre DESC',
    'id_asc'           => 'c.id ASC',
    'id_desc'          => 'c.id DESC',
    'tier_asc'         => 'c.tier ASC,  c.nombre ASC',
    'tier_desc'        => 'c.tier DESC, c.nombre ASC',
    'resenas_desc'     => 'resenas_pendientes DESC, c.nombre ASC',
    'resenas_asc'      => 'resenas_pendientes ASC,  c.nombre ASC',
    'completitud_desc' => 'completitud_score DESC, c.nombre ASC',
    'completitud_asc'  => 'completitud_score ASC,  c.nombre ASC',
    'imagenes_desc'    => 'img_count DESC, c.nombre ASC',
    'imagenes_asc'     => 'img_count ASC,  c.nombre ASC',
    default            => 'c.nombre ASC',
};

// ─── Score SQL: espeja includes/completitud.php (permite ORDER BY en DB) ─────
// Si cambia definicionCompletitud(), actualizar este CASE en consecuencia.

$scoreSql = "
    (CASE WHEN COALESCE(c.telefono,'')           != '' OR COALESCE(c.telefono_clientes,'') != '' THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.email,'')              != '' OR COALESCE(c.email_clientes,'')    != '' THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.website,'')            != ''                                            THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.direccion_completa,'') != ''                                            THEN 1 ELSE 0 END +
     CASE WHEN c.latitud IS NOT NULL AND c.longitud IS NOT NULL                                   THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.descripcion,'')        != '' AND CHAR_LENGTH(c.descripcion) >= 150      THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.horarios,'')           != ''                                            THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.zona_cobertura,'')     != ''                                            THEN 1 ELSE 0 END +
     CASE WHEN c.cremacion_individual IS NOT NULL OR c.cremacion_colectiva IS NOT NULL
           OR c.recogida_domicilio    IS NOT NULL OR c.entrega_domicilio    IS NOT NULL            THEN 1 ELSE 0 END +
     CASE WHEN COALESCE(c.meta_description_seo,'') != ''                                          THEN 1 ELSE 0 END +
     IF((SELECT COUNT(*) FROM crematorio_imagenes ci  WHERE ci.crematorio_id  = c.id AND ci.estado_llm = 'procesada') > 0, 1, 0) +
     IF((SELECT COUNT(*) FROM crematorio_imagenes ci2 WHERE ci2.crematorio_id = c.id AND ci2.tipo = 'logo' AND ci2.categoria = 'logo') > 0, 1, 0))
";

// ─── Queries ─────────────────────────────────────────────────────────────────

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM crematorios c LEFT JOIN provincias p ON p.id = c.provincia_id WHERE $whereSQL");
$totalStmt->execute($params);
$total        = (int) $totalStmt->fetchColumn();
$totalPaginas = (int) ceil($total / $porPagina);

$sql = "SELECT c.*,
               p.nombre AS provincia_nombre,
               (SELECT COUNT(*) FROM crematorio_imagenes ci
                WHERE ci.crematorio_id = c.id AND ci.estado_llm = 'procesada') AS img_count,
               (SELECT COUNT(*) FROM crematorio_imagenes ci
                WHERE ci.crematorio_id = c.id AND ci.tipo = 'logo' AND ci.categoria = 'logo') AS logo_count,
               (SELECT COUNT(*) FROM resenas r
                WHERE r.crematorio_id = c.id AND r.estado = 'pendiente') AS resenas_pendientes,
               $scoreSql AS completitud_score
        FROM crematorios c
        LEFT JOIN provincias p ON p.id = c.provincia_id
        WHERE $whereSQL
        ORDER BY $orderSQL
        LIMIT :lim OFFSET :off";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,    PDO::PARAM_INT);
$stmt->execute();
$crematorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Helpers UI ──────────────────────────────────────────────────────────────

// Genera link de columna ordenable (toggle asc/desc) — iconos lucide
function thSort(string $label, string $campoDesc, string $campoAsc, string $ordenActual, array $fp): string {
    $isDesc  = $ordenActual === $campoDesc;
    $isAsc   = $ordenActual === $campoAsc;
    $active  = $isDesc || $isAsc;
    $next    = $isDesc ? $campoAsc : $campoDesc;
    $icono   = $isDesc ? 'arrow-down' : ($isAsc ? 'arrow-up' : 'chevrons-up-down');
    $color   = $active ? 'var(--admin-tinta-fuerte)' : 'var(--admin-tinta-tenue)';
    $weight  = $active ? '700' : '600';
    $qs      = http_build_query(array_merge($fp, ['orden' => $next, 'pagina' => 1]));
    return "<a href=\"?$qs\" style=\"color:$color; text-decoration:none; font-weight:$weight; white-space:nowrap; display:inline-flex; align-items:center; gap:.35rem; cursor:pointer;\">$label<i data-lucide=\"$icono\" class=\"icono\" style=\"width:13px; height:13px;\"></i></a>";
}

// Params que persisten en paginación y ordenamiento
$fp = array_filter(['q' => $busqueda, 'ciudad' => $filCiudad, 'tier' => $filTier, 'estado' => $filEstado, 'orden' => $orden]);

$esSuper = esSuperAdmin();

$titulo_pagina = 'Fichas de negocios - Admin';
include 'header.php';
?>

<div class="admin-page">

    <!-- Page header -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Fichas de negocios</h1>
        <p class="admin-page-subtitle">
            <?php
            $totalFmt = number_format($total, 0, ',', '.');
            $sub = $totalFmt . ' registro' . ($total === 1 ? '' : 's');
            $filtrosActivos = array_filter([$busqueda, $filCiudad, $filTier, $filEstado]);
            if ($filtrosActivos) $sub .= ' — filtros activos';
            echo htmlspecialchars($sub);
            ?>
        </p>
    </header>

    <!-- Filtros -->
    <form method="GET" class="admin-filtros">
        <input type="hidden" name="orden" value="<?php echo htmlspecialchars($orden); ?>">

        <div class="admin-filtros__campos">
            <div class="field">
                <label class="field__label">Buscar</label>
                <div class="field__group">
                    <span class="field__prefix"><i data-lucide="search" class="icono"></i></span>
                    <input type="text" name="q" class="field__input"
                           placeholder="Nombre, ciudad, provincia…"
                           value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
                    <button type="button" class="field__clear" aria-label="Limpiar búsqueda"
                            onclick="var i=this.closest('.field__group').querySelector('input'); i.value=''; if(i.form){i.form.submit();}else{i.focus();}">
                        <i data-lucide="x" class="icono"></i>
                    </button>
                </div>
            </div>

            <div class="field">
                <label class="field__label">Ciudad</label>
                <select name="ciudad" class="field__select field__select--enhanced"
                        data-placeholder="Todas las ciudades">
                    <option value="">Todas</option>
                    <?php foreach ($ciudades as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filCiudad === $c ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Plan</label>
                <select name="tier" class="field__select field__select--enhanced"
                        data-placeholder="Todos los planes"
                        data-ts-search="off">
                    <option value="">Todos</option>
                    <?php foreach ($tiers as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filTier === $t ? 'selected' : ''; ?>>
                        P<?php echo ltrim($t, '0'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Estado</label>
                <select name="estado" class="field__select field__select--enhanced"
                        data-placeholder="Todos los estados"
                        data-ts-search="off">
                    <option value="">Todos</option>
                    <?php foreach (['activa'=>'Activa','pausada'=>'Pausada','cerrada'=>'Cerrada','archivada'=>'Archivada'] as $val=>$lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filEstado === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="admin-filtros__acciones">
            <button type="submit" class="boton uno pequeno" style="display:inline-flex; align-items:center; gap:.35rem;">
                <i data-lucide="search" class="icono" style="width:14px; height:14px;"></i>
                Filtrar
            </button>
            <?php if ($busqueda || $filCiudad || $filTier || $filEstado): ?>
            <a href="fichas-negocios.php" class="boton dos pequeno" style="display:inline-flex; align-items:center; gap:.35rem;">
                <i data-lucide="x" class="icono" style="width:14px; height:14px;"></i>
                Limpiar
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabla -->
    <?php if (empty($crematorios)): ?>
    <div class="admin-empty">
        <div class="admin-empty__icon">
            <i data-lucide="<?php echo ($busqueda || $filCiudad || $filTier || $filEstado) ? 'search-x' : 'building-2'; ?>" style="width: 28px; height: 28px;"></i>
        </div>
        <div class="admin-empty__titulo">
            <?php echo ($busqueda || $filCiudad || $filTier || $filEstado) ? 'Sin resultados con estos filtros' : 'No hay fichas cargadas'; ?>
        </div>
        <div class="admin-empty__texto">
            <?php if ($busqueda || $filCiudad || $filTier || $filEstado): ?>
                Probá ajustar o limpiar los filtros para ver más fichas.
            <?php else: ?>
                Cuando se agreguen negocios al sistema aparecerán acá.
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap" style="overflow-x: auto;">
    <table class="admin-table">
        <thead>
        <tr>
            <th style="text-align: center; width: 64px;">
                <?php echo thSort('ID', 'id_desc', 'id_asc', $orden, $fp); ?>
            </th>
            <th><?php echo thSort('Nombre', 'nombre_desc', 'nombre_asc', $orden, $fp); ?></th>
            <th><?php echo thSort('Plan', 'tier_desc', 'tier_asc', $orden, $fp); ?></th>
            <th style="min-width: 160px;">Estado</th>
            <th style="text-align: center;"><?php echo thSort('Reseñas', 'resenas_desc', 'resenas_asc', $orden, $fp); ?></th>
            <th class="admin-table__acciones"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($crematorios as $c):
            $comp     = resumenCompletitud(evaluarCompletitud($c, $c['img_count'] > 0, $c['logo_count'] > 0));
            $pct      = $comp['pct'];
            $barColor = $pct >= 80
                ? 'var(--admin-tone-exito-fg)'
                : ($pct >= 50 ? 'var(--admin-tone-alerta-fg)' : 'var(--admin-tone-error-fg)');
            $resenas  = (int) $c['resenas_pendientes'];
            $estado   = $c['estado'] ?? 'activa';
            $inactiva = $estado !== 'activa';
        ?>
        <tr data-ficha-id="<?php echo (int)$c['id']; ?>"
            style="<?php echo $inactiva ? 'opacity:.6;' : ''; ?>">
            <td style="text-align:center; color: var(--admin-tinta-tenue); font-variant-numeric: tabular-nums; font-size: var(--admin-body-sm);">
                <?php echo (int)$c['id']; ?>
            </td>

            <td style="min-width: 240px;">
                <div class="admin-table__principal"><?php echo htmlspecialchars($c['nombre']); ?></div>
                <?php
                $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
                if ($ubic !== ''): ?>
                <div class="admin-table__secundario"><?php echo htmlspecialchars($ubic); ?></div>
                <?php endif; ?>
                <div style="display:flex; align-items:center; gap:.5rem; margin-top:.4rem;">
                    <div style="flex:1; background: var(--admin-papel-alt); border-radius: var(--admin-r-pill); height: 6px; overflow: hidden; max-width: 140px; border: 1px solid var(--admin-linea);">
                        <div style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $barColor; ?>; border-radius: var(--admin-r-pill); transition: width .25s;"></div>
                    </div>
                    <span style="font-size: var(--admin-body-sm); font-weight: 700; color: <?php echo $barColor; ?>; font-variant-numeric: tabular-nums;">
                        <?php echo $pct; ?>%
                    </span>
                </div>
            </td>

            <td>
                <span class="admin-pill" style="font-variant-numeric: tabular-nums;">
                    P<?php echo ltrim($c['tier'] ?? '?', '0'); ?>
                </span>
            </td>

            <td>
                <select class="field__select field__select--enhanced js-estado-ficha"
                        data-ficha-id="<?php echo (int)$c['id']; ?>"
                        data-ts-search="off">
                    <?php foreach (['activa'=>'Activa','pausada'=>'Pausada','cerrada'=>'Cerrada','archivada'=>'Archivada'] as $val=>$lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo $estado === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td style="text-align: center;">
                <?php if ($resenas > 0): ?>
                <a href="resenas.php?crematorio_id=<?php echo $c['id']; ?>"
                   class="admin-pill admin-pill--alerta"
                   style="text-decoration: none; display: inline-flex; align-items: center; gap: .3rem;">
                    <i data-lucide="message-square" style="width: 11px; height: 11px;"></i>
                    <?php echo $resenas; ?> pendiente<?php echo $resenas > 1 ? 's' : ''; ?>
                </a>
                <?php else: ?>
                <span style="color: var(--admin-tinta-tenue);">—</span>
                <?php endif; ?>
            </td>

            <td class="admin-table__acciones">
                <div style="display:inline-flex; align-items:center; gap:.3rem;">
                    <a href="editar-ficha-negocio.php?id=<?php echo $c['id']; ?>"
                       class="boton dos pequeno"
                       style="display: inline-flex; align-items: center; gap: .3rem;">
                        <i data-lucide="pencil" class="icono" style="width: 13px; height: 13px;"></i>
                        Editar
                    </a>
                    <?php if ($esSuper): ?>
                    <button type="button"
                            class="boton dos pequeno js-eliminar-ficha"
                            data-ficha-id="<?php echo (int)$c['id']; ?>"
                            data-ficha-nombre="<?php echo htmlspecialchars($c['nombre'], ENT_QUOTES); ?>"
                            title="Eliminar ficha"
                            style="display:inline-flex; align-items:center; padding:.3rem .45rem; color:var(--admin-tone-error-fg); border-color:var(--admin-tone-error-fg);">
                        <i data-lucide="trash-2" class="icono" style="width: 13px; height: 13px;"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </td>

        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
    <div style="display: flex; justify-content: center; gap: .4rem; margin-top: var(--espacio-cuatro); flex-wrap: wrap;">
        <?php
        $fpPag = array_filter(['q' => $busqueda, 'ciudad' => $filCiudad, 'tier' => $filTier, 'estado' => $filEstado, 'orden' => $orden]);
        for ($i = 1; $i <= $totalPaginas; $i++):
            $qs = http_build_query(array_merge($fpPag, ['pagina' => $i]));
        ?>
        <a href="?<?php echo $qs; ?>"
           class="boton <?php echo $i === $pagina ? 'uno' : 'dos'; ?> pequeno"
           style="min-width: 36px; text-align: center; font-variant-numeric: tabular-nums;">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
(function() {
    // ─── Cambiar estado de una ficha (AJAX) ──────────────────
    document.querySelectorAll('.js-estado-ficha').forEach(function(sel) {
        var estadoPrevio = sel.value;
        sel.addEventListener('change', function() {
            var id     = sel.dataset.fichaId;
            var nuevo  = sel.value;
            // Guard: ignorar el change que Tom Select dispara al inicializar
            if (nuevo === estadoPrevio) return;
            var fd     = new FormData();
            fd.append('accion', 'cambiar_estado');
            fd.append('id', id);
            fd.append('estado', nuevo);

            sel.disabled = true;
            fetch('ficha-accion.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        estadoPrevio = nuevo;
                        var tr = sel.closest('tr');
                        if (tr) tr.style.opacity = (nuevo === 'activa') ? '' : '.6';
                        if (window.toast) toast.ok('Estado actualizado a "' + nuevo + '".');
                    } else {
                        sel.value = estadoPrevio;
                        if (window.toast) toast.error(d.mensaje || 'No se pudo actualizar.');
                    }
                })
                .catch(function() {
                    sel.value = estadoPrevio;
                    if (window.toast) toast.error('Error de conexión.');
                })
                .finally(function() { sel.disabled = false; });
        });
    });

    // ─── Eliminar ficha (AJAX, super_admin) ──────────────────
    document.querySelectorAll('.js-eliminar-ficha').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id     = btn.dataset.fichaId;
            var nombre = btn.dataset.fichaNombre;

            var hacerlo = function() {
                var fd = new FormData();
                fd.append('accion', 'eliminar');
                fd.append('id', id);
                btn.disabled = true;
                fetch('ficha-accion.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.ok) {
                            var tr = btn.closest('tr');
                            if (tr) {
                                tr.style.transition = 'opacity .3s';
                                tr.style.opacity = '0';
                                setTimeout(function() { tr.remove(); }, 300);
                            }
                            if (window.toast) toast.ok(d.mensaje || 'Ficha eliminada.');
                        } else {
                            btn.disabled = false;
                            if (window.toast) toast.error(d.mensaje || 'No se pudo eliminar.');
                        }
                    })
                    .catch(function() {
                        btn.disabled = false;
                        if (window.toast) toast.error('Error de conexión.');
                    });
            };

            // Confirmación — usa window.confirmar() si existe, si no confirm() nativo
            var msgHtml = 'Vas a eliminar la ficha <strong>' + nombre + '</strong>.<br><br>' +
                          'Esto borra la ficha, sus imágenes y reseñas de forma permanente. ' +
                          'Los leads y clics se conservan como histórico.<br><br>' +
                          '<strong>No se puede deshacer.</strong>';
            if (window.confirmar) {
                window.confirmar({
                    titulo: 'Eliminar ficha',
                    mensaje: msgHtml,
                    textoOK: 'Eliminar',
                    peligroso: true,
                    onOK: hacerlo
                });
            } else if (confirm('Eliminar la ficha "' + nombre + '"? No se puede deshacer.')) {
                hacerlo();
            }
        });
    });
})();
</script>

<?php include 'footer.php'; ?>
