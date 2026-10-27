<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CIUDADES — catálogo de ciudades del portal
 * Revisar las ciudades que proponen los negocios («pendientes»), corregir nombres y
 * coordenadas, fusionar variantes y aprobar. Una ciudad pendiente NO genera página
 * pública hasta que se aprueba o se fusiona con otra.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('crematorios');

$pdo = obtenerConexion();

$ESTADOS = ['aprobada' => 'Aprobada', 'dudosa' => 'Dudosa (se muestra, revisar)', 'pendiente' => 'Pendiente'];

// ¿Está la migración?
$migracionFalta = false;
try {
    $pdo->query("SELECT lat, estado_revision FROM ciudades LIMIT 1");
} catch (PDOException $e) {
    $migracionFalta = true;
}

// Pestaña: ciudades (por defecto), provincias o regiones. País → región → provincia → ciudad.
$tab = in_array($_GET['tab'] ?? ($_POST['tab'] ?? ''), ['provincias', 'regiones'], true) ? ($_GET['tab'] ?? $_POST['tab']) : 'ciudades';
$paisFalta = false;
try { $pdo->query("SELECT pais_codigo FROM comunidades_autonomas LIMIT 1"); } catch (PDOException $e) { $paisFalta = true; }

// Añade slugs al alias (JSON) sin repetir ni incluir el slug propio.
function ciudadAgregarAlias(?string $json, array $nuevos, string $slugPropio): string
{
    $lista = json_decode((string) $json, true);
    $lista = is_array($lista) ? $lista : [];
    foreach ($nuevos as $n) {
        $n = slugificar((string) $n);
        if ($n !== '' && $n !== $slugPropio && !in_array($n, $lista, true)) $lista[] = $n;
    }
    $lista = array_values(array_filter($lista, fn($s) => $s !== $slugPropio));
    return json_encode($lista, JSON_UNESCAPED_UNICODE);
}

// ─── POST de Provincias / Regiones (PRG) ─────────────────────────────────────
// El slug de una provincia o región forma parte de sus URLs públicas: una vez creado no se edita aquí.
if (!$migracionFalta && !$paisFalta && $tab !== 'ciudades' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = fn(string $k, string $m) => 'ciudades.php?tab=' . $tab . '&' . $k . '=' . urlencode($m);
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) { header('Location: ' . $url('error', 'Sesión caducada, vuelve a intentarlo')); exit; }

    $accion = $_POST['accion'] ?? '';
    $nombre = trim(preg_replace('/\s+/u', ' ', $_POST['nombre'] ?? ''));
    $id     = (int) ($_POST['id'] ?? 0);
    $pais   = strtoupper(preg_replace('/[^A-Za-z]/', '', $_POST['pais_codigo'] ?? 'ES')) ?: 'ES';
    try {
        if ($nombre === '') throw new RuntimeException('El nombre no puede quedar vacío');
        switch ($accion) {
            case 'prov_crear':
                $reg = (int) ($_POST['comunidad_id'] ?? 0);
                $slug = slugificar($nombre);
                if (!$reg || $slug === '') throw new RuntimeException('Elige la región de la provincia');
                $pdo->prepare("INSERT INTO provincias (comunidad_id, nombre, slug) VALUES (?, ?, ?)")->execute([$reg, $nombre, $slug]);
                header('Location: ' . $url('ok', 'Provincia «' . $nombre . '» creada')); exit;
            case 'prov_guardar':
                $pdo->prepare("UPDATE provincias SET nombre = ?, comunidad_id = ?, activo = ? WHERE id = ?")
                    ->execute([$nombre, (int) ($_POST['comunidad_id'] ?? 0), empty($_POST['activo']) ? 0 : 1, $id]);
                header('Location: ' . $url('ok', 'Provincia guardada')); exit;
            case 'reg_crear':
                $slug = slugificar($nombre);
                if ($slug === '') throw new RuntimeException('Nombre no válido');
                $pdo->prepare("INSERT INTO comunidades_autonomas (nombre, slug, pais_codigo) VALUES (?, ?, ?)")->execute([$nombre, $slug, $pais]);
                header('Location: ' . $url('ok', 'Región «' . $nombre . '» creada')); exit;
            case 'reg_guardar':
                $pdo->prepare("UPDATE comunidades_autonomas SET nombre = ?, pais_codigo = ?, activo = ? WHERE id = ?")
                    ->execute([$nombre, $pais, empty($_POST['activo']) ? 0 : 1, $id]);
                header('Location: ' . $url('ok', 'Región guardada')); exit;
        }
        header('Location: ' . $url('error', 'Acción desconocida')); exit;
    } catch (Throwable $e) {
        error_log('[zonas] ' . $e->getMessage());
        $msg = $e instanceof RuntimeException ? $e->getMessage()
             : (($e instanceof PDOException && str_contains($e->getMessage(), 'Duplicate')) ? 'Ya existe una con ese nombre (mismo slug)' : 'No se pudo guardar');
        header('Location: ' . $url('error', $msg)); exit;
    }
}

// ─── POST (PRG) ──────────────────────────────────────────────────────────────
if (!$migracionFalta && $tab === 'ciudades' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $volver = in_array($_POST['volver'] ?? '', ['todas', 'pendiente', 'dudosa'], true) ? $_POST['volver'] : 'pendiente';
    $q      = trim($_POST['q'] ?? '');
    $url    = fn(string $k, string $m) => 'ciudades.php?ver=' . $volver . ($q !== '' ? '&q=' . urlencode($q) : '') . '&' . $k . '=' . urlencode($m);

    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
        header('Location: ' . $url('error', 'Sesión caducada, vuelve a intentarlo')); exit;
    }

    $id     = (int) ($_POST['ciudad_id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $st = $pdo->prepare("SELECT * FROM ciudades WHERE id = ?");
    $st->execute([$id]);
    $ciu = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ciu) { header('Location: ' . $url('error', 'Ciudad no encontrada')); exit; }

    try {
        switch ($accion) {
            case 'guardar':
                $nombre = trim(preg_replace('/\s+/u', ' ', $_POST['nombre'] ?? ''));
                $slug   = slugificar(trim($_POST['slug'] ?? '') ?: $nombre);
                if ($nombre === '' || $slug === '') throw new RuntimeException('El nombre no puede quedar vacío');
                $lat = trim($_POST['lat'] ?? '') === '' ? null : (float) str_replace(',', '.', $_POST['lat']);
                $lng = trim($_POST['lng'] ?? '') === '' ? null : (float) str_replace(',', '.', $_POST['lng']);
                $estado = array_key_exists($_POST['estado_revision'] ?? '', $ESTADOS) ? $_POST['estado_revision'] : $ciu['estado_revision'];

                $alias = preg_split('/[,;\n]+/', (string) ($_POST['alias'] ?? ''));
                $alias[] = $ciu['slug'];            // el slug anterior sigue funcionando (301)
                $aliasJson = ciudadAgregarAlias(null, $alias, $slug);

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE ciudades SET nombre = ?, slug = ?, slug_alias_json = ?, lat = ?, lng = ?, estado_revision = ?, nota = ? WHERE id = ?")
                    ->execute([$nombre, $slug, $aliasJson === '[]' ? null : $aliasJson, $lat, $lng, $estado, trim($_POST['nota'] ?? '') ?: null, $id]);
                if ($estado !== 'pendiente') {
                    $pdo->prepare("UPDATE crematorios SET ciudad = ? WHERE ciudad_id = ?")->execute([$nombre, $id]);
                }
                $pdo->commit();
                header('Location: ' . $url('ok', 'Ciudad guardada')); exit;

            case 'aprobar':
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE ciudades SET estado_revision = 'aprobada', nota = NULL WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE crematorios SET ciudad = ? WHERE ciudad_id = ?")->execute([$ciu['nombre'], $id]);
                $pdo->commit();
                header('Location: ' . $url('ok', '«' . $ciu['nombre'] . '» aprobada')); exit;

            case 'fusionar':
                $destId = (int) ($_POST['destino_id'] ?? 0);
                $st = $pdo->prepare("SELECT * FROM ciudades WHERE id = ? AND provincia_id = ? AND id <> ?");
                $st->execute([$destId, $ciu['provincia_id'], $id]);
                $dest = $st->fetch(PDO::FETCH_ASSOC);
                if (!$dest) throw new RuntimeException('Elige una ciudad de la misma provincia');

                $viejos = array_merge([$ciu['slug'], $ciu['nombre']], json_decode((string) $ciu['slug_alias_json'], true) ?: []);
                $aliasJson = ciudadAgregarAlias($dest['slug_alias_json'], $viejos, $dest['slug']);

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE crematorios SET ciudad_id = ?, ciudad = ? WHERE ciudad_id = ?")->execute([$destId, $dest['nombre'], $id]);
                $pdo->prepare("UPDATE ciudades SET slug_alias_json = ? WHERE id = ?")->execute([$aliasJson, $destId]);
                $pdo->prepare("DELETE FROM ciudades WHERE id = ?")->execute([$id]);
                $pdo->commit();
                header('Location: ' . $url('ok', '«' . $ciu['nombre'] . '» fusionada con «' . $dest['nombre'] . '»')); exit;

            case 'geocodificar':
                $p = $pdo->prepare("SELECT nombre FROM provincias WHERE id = ?");
                $p->execute([$ciu['provincia_id']]);
                $geo = geocodificarDireccion($ciu['nombre'] . ', ' . $p->fetchColumn(), '', 'ES');
                if (empty($geo['ok'])) throw new RuntimeException('Google no encontró la ciudad: ' . ($geo['error'] ?? ''));
                $pdo->prepare("UPDATE ciudades SET lat = ?, lng = ? WHERE id = ?")->execute([$geo['lat'], $geo['lng'], $id]);
                header('Location: ' . $url('ok', 'Coordenadas actualizadas')); exit;

            case 'eliminar':
                $n = $pdo->prepare("SELECT COUNT(*) FROM crematorios WHERE ciudad_id = ?");
                $n->execute([$id]);
                if ((int) $n->fetchColumn() > 0) throw new RuntimeException('Tiene fichas: fusiónala con otra ciudad en vez de eliminarla');
                $pdo->prepare("DELETE FROM ciudades WHERE id = ?")->execute([$id]);
                header('Location: ' . $url('ok', '«' . $ciu['nombre'] . '» eliminada')); exit;
        }
        header('Location: ' . $url('error', 'Acción desconocida')); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e instanceof RuntimeException ? $e->getMessage()
             : (($e instanceof PDOException && str_contains($e->getMessage(), 'Duplicate')) ? 'Ya existe otra ciudad con ese nombre en la provincia: fusiónalas' : 'No se pudo guardar');
        error_log('[ciudades] ' . $e->getMessage());
        header('Location: ' . $url('error', $msg)); exit;
    }
}

// ─── Listado ─────────────────────────────────────────────────────────────────
$ver = in_array($_GET['ver'] ?? '', ['todas', 'pendiente', 'dudosa'], true) ? $_GET['ver'] : 'pendiente';
$q   = trim($_GET['q'] ?? '');

$ciudades = []; $cont = ['todas' => 0, 'pendiente' => 0, 'dudosa' => 0]; $hermanas = [];
if (!$migracionFalta) {
    $where = []; $params = [];
    if ($ver !== 'todas') { $where[] = 'ci.estado_revision = :e'; $params[':e'] = $ver; }
    if ($q !== '')        { $where[] = '(ci.nombre LIKE :q1 OR p.nombre LIKE :q2)'; $params[':q1'] = $params[':q2'] = '%' . $q . '%'; }
    $st = $pdo->prepare("SELECT ci.*, p.nombre AS provincia_nombre,
                                (SELECT COUNT(*) FROM crematorios c WHERE c.ciudad_id = ci.id AND c.estado = 'activa') AS fichas
                           FROM ciudades ci JOIN provincias p ON p.id = ci.provincia_id"
                        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
                          ORDER BY p.nombre, ci.nombre LIMIT 300");
    $st->execute($params);
    $ciudades = $st->fetchAll(PDO::FETCH_ASSOC);

    $c = $pdo->query("SELECT COUNT(*) AS todas, SUM(estado_revision = 'pendiente') AS pendiente, SUM(estado_revision = 'dudosa') AS dudosa FROM ciudades")->fetch(PDO::FETCH_ASSOC);
    $cont = array_map('intval', $c);

    foreach ($pdo->query("SELECT id, provincia_id, nombre FROM ciudades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $hermanas[$h['provincia_id']][] = $h;
    }
}

$titulo_pagina = 'Ciudades - Admin';
include 'header.php';
$csrf = generarTokenCSRF();
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Ciudades</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Dónde está cada negocio, en cuatro niveles: <strong>país → región → provincia → ciudad</strong>. Cuando un negocio escribe una ciudad que no conocemos, aparece en Ciudades como <strong>pendiente</strong>: apruébala si es correcta, o fusiónala con la que corresponda. Cada ficha cubre como mínimo 20&nbsp;km a la redonda de su dirección.
            </p>
        </div>
    </header>

    <?php if ($migracionFalta): ?>
    <div class="panel" style="border-left:3px solid var(--color-siete);">Falta ejecutar la migración <code>admin/migrations/ciudades_modelo_1.sql</code> y el script <code>seed_ciudades.php</code>.</div>
    <?php else: ?>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-uno);"><?php echo htmlspecialchars($_GET['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Nivel de zona">
        <?php foreach (['ciudades' => 'Ciudades', 'provincias' => 'Provincias', 'regiones' => 'Regiones'] as $k => $lbl): ?>
        <a href="?tab=<?php echo $k; ?>" class="admin-tab<?php echo $tab === $k ? ' admin-tab--activo' : ''; ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($paisFalta && $tab !== 'ciudades'): ?>
    <div class="panel" style="border-left:3px solid var(--color-siete);">Falta ejecutar la migración <code>admin/migrations/zonas_pais_1.sql</code>.</div>
    <?php elseif ($tab === 'provincias'):
        $regiones = $pdo->query("SELECT id, nombre, pais_codigo FROM comunidades_autonomas ORDER BY pais_codigo, nombre")->fetchAll(PDO::FETCH_ASSOC);
        $provs = $pdo->query("SELECT p.*, ca.nombre AS region,
                                     (SELECT COUNT(*) FROM ciudades ci WHERE ci.provincia_id = p.id) AS ciudades,
                                     (SELECT COUNT(*) FROM crematorios c WHERE c.provincia_id = p.id AND c.estado = 'activa') AS fichas
                                FROM provincias p LEFT JOIN comunidades_autonomas ca ON ca.id = p.comunidad_id
                               ORDER BY ca.nombre, p.nombre")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <p class="admin-page-subtitle" style="margin:var(--espacio-tres) 0;">Cada ficha cuelga de una ciudad, y cada ciudad de una provincia. Si una provincia comparte nombre con su región (por ejemplo Madrid), en la web solo se muestra uno de los dos niveles.</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Provincia</th><th>Región</th><th class="admin-table__num">Ciudades</th><th class="admin-table__num">Fichas</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($provs as $pv): ?>
                <tr>
                    <td><div class="admin-table__principal"><?php echo htmlspecialchars($pv['nombre']); ?></div><div class="admin-table__secundario"><?php echo htmlspecialchars($pv['slug']); ?></div></td>
                    <td><?php echo htmlspecialchars($pv['region'] ?? '—'); ?></td>
                    <td class="admin-table__num"><?php echo (int) $pv['ciudades']; ?></td>
                    <td class="admin-table__num"><?php echo (int) $pv['fichas']; ?></td>
                    <td><span class="admin-pill <?php echo $pv['activo'] ? 'admin-pill--exito' : 'admin-pill--alerta'; ?>"><?php echo $pv['activo'] ? 'Activa' : 'Oculta'; ?></span></td>
                    <td class="admin-table__acciones">
                        <details class="ciudades-editar">
                            <summary class="boton dos pequeno"><i data-lucide="pencil" class="icono"></i> Editar</summary>
                            <form method="POST" style="text-align:left; margin-top:var(--espacio-tres); display:grid; gap:var(--espacio-dos); min-width:280px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="tab" value="provincias"><input type="hidden" name="id" value="<?php echo (int) $pv['id']; ?>">
                                <div class="field" style="margin:0;"><label class="field__label">Nombre</label><input type="text" name="nombre" class="field__input" value="<?php echo htmlspecialchars($pv['nombre']); ?>" required></div>
                                <div class="field" style="margin:0;"><label class="field__label">Región</label>
                                    <select name="comunidad_id" class="field__select">
                                        <?php foreach ($regiones as $rg): ?><option value="<?php echo (int) $rg['id']; ?>"<?php echo (int) $rg['id'] === (int) $pv['comunidad_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($rg['nombre'] . ' (' . $rg['pais_codigo'] . ')'); ?></option><?php endforeach; ?>
                                    </select></div>
                                <label style="display:flex; gap:.5rem; align-items:center;"><input type="checkbox" name="activo" value="1"<?php echo $pv['activo'] ? ' checked' : ''; ?>> Activa (visible en la web)</label>
                                <small class="field__hint">El slug (<?php echo htmlspecialchars($pv['slug']); ?>) forma parte de la URL y no se cambia aquí.</small>
                                <button type="submit" name="accion" value="prov_guardar" class="boton uno pequeno"><i data-lucide="save" class="icono"></i> Guardar</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="POST" class="panel" style="margin-top:var(--espacio-cuatro); display:flex; gap:var(--espacio-tres); flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="tab" value="provincias">
        <div class="field" style="margin:0; flex:1; min-width:200px;"><label class="field__label">Nueva provincia</label><input type="text" name="nombre" class="field__input" placeholder="Nombre" required></div>
        <div class="field" style="margin:0; flex:1; min-width:200px;"><label class="field__label">Región</label>
            <select name="comunidad_id" class="field__select" required>
                <option value="">Elige la región…</option>
                <?php foreach ($regiones as $rg): ?><option value="<?php echo (int) $rg['id']; ?>"><?php echo htmlspecialchars($rg['nombre'] . ' (' . $rg['pais_codigo'] . ')'); ?></option><?php endforeach; ?>
            </select></div>
        <button type="submit" name="accion" value="prov_crear" class="boton uno pequeno"><i data-lucide="plus" class="icono"></i> Añadir provincia</button>
    </form>
    <?php elseif ($tab === 'regiones'):
        $regs = $pdo->query("SELECT ca.*,
                                    (SELECT COUNT(*) FROM provincias p WHERE p.comunidad_id = ca.id) AS provincias,
                                    (SELECT COUNT(*) FROM crematorios c JOIN provincias p ON p.id = c.provincia_id WHERE p.comunidad_id = ca.id AND c.estado = 'activa') AS fichas
                               FROM comunidades_autonomas ca ORDER BY ca.pais_codigo, ca.nombre")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <p class="admin-page-subtitle" style="margin:var(--espacio-tres) 0;">Región = el nivel que agrupa provincias (en España, las comunidades autónomas; en otros países, estados, regiones, etc.). El país va con su código de dos letras (ES, PT, US…).</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Región</th><th>País</th><th class="admin-table__num">Provincias</th><th class="admin-table__num">Fichas</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($regs as $rg): ?>
                <tr>
                    <td><div class="admin-table__principal"><?php echo htmlspecialchars($rg['nombre']); ?></div><div class="admin-table__secundario"><?php echo htmlspecialchars($rg['slug']); ?></div></td>
                    <td><?php echo htmlspecialchars($rg['pais_codigo']); ?></td>
                    <td class="admin-table__num"><?php echo (int) $rg['provincias']; ?></td>
                    <td class="admin-table__num"><?php echo (int) $rg['fichas']; ?></td>
                    <td><span class="admin-pill <?php echo $rg['activo'] ? 'admin-pill--exito' : 'admin-pill--alerta'; ?>"><?php echo $rg['activo'] ? 'Activa' : 'Oculta'; ?></span></td>
                    <td class="admin-table__acciones">
                        <details class="ciudades-editar">
                            <summary class="boton dos pequeno"><i data-lucide="pencil" class="icono"></i> Editar</summary>
                            <form method="POST" style="text-align:left; margin-top:var(--espacio-tres); display:grid; gap:var(--espacio-dos); min-width:260px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="tab" value="regiones"><input type="hidden" name="id" value="<?php echo (int) $rg['id']; ?>">
                                <div class="field" style="margin:0;"><label class="field__label">Nombre</label><input type="text" name="nombre" class="field__input" value="<?php echo htmlspecialchars($rg['nombre']); ?>" required></div>
                                <div class="field" style="margin:0;"><label class="field__label">País (código de 2 letras)</label><input type="text" name="pais_codigo" class="field__input" maxlength="2" value="<?php echo htmlspecialchars($rg['pais_codigo']); ?>" required></div>
                                <label style="display:flex; gap:.5rem; align-items:center;"><input type="checkbox" name="activo" value="1"<?php echo $rg['activo'] ? ' checked' : ''; ?>> Activa (visible en la web)</label>
                                <small class="field__hint">El slug (<?php echo htmlspecialchars($rg['slug']); ?>) forma parte de la URL y no se cambia aquí.</small>
                                <button type="submit" name="accion" value="reg_guardar" class="boton uno pequeno"><i data-lucide="save" class="icono"></i> Guardar</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="POST" class="panel" style="margin-top:var(--espacio-cuatro); display:flex; gap:var(--espacio-tres); flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="tab" value="regiones">
        <div class="field" style="margin:0; flex:1; min-width:200px;"><label class="field__label">Nueva región</label><input type="text" name="nombre" class="field__input" placeholder="Nombre" required></div>
        <div class="field" style="margin:0; width:120px;"><label class="field__label">País</label><input type="text" name="pais_codigo" class="field__input" maxlength="2" value="ES" required></div>
        <button type="submit" name="accion" value="reg_crear" class="boton uno pequeno"><i data-lucide="plus" class="icono"></i> Añadir región</button>
    </form>
    <?php else: ?>

    <nav class="admin-tabs">
        <?php foreach (['pendiente' => 'Pendientes', 'dudosa' => 'Dudosas', 'todas' => 'Todas'] as $k => $lbl): ?>
        <a href="?ver=<?php echo $k; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>" class="admin-tab<?php echo $ver === $k ? ' admin-tab--activo' : ''; ?>">
            <?php echo $lbl; ?> <span class="admin-tab__count"><?php echo (int) ($cont[$k] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <form method="GET" class="ciudades-buscador" style="margin:var(--espacio-tres) 0; display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
        <input type="hidden" name="ver" value="<?php echo htmlspecialchars($ver); ?>">
        <input type="search" name="q" class="field__input" style="max-width:320px;" placeholder="Buscar ciudad o provincia…" value="<?php echo htmlspecialchars($q); ?>">
        <button type="submit" class="boton dos pequeno"><i data-lucide="search" class="icono"></i> Buscar</button>
    </form>

    <?php if (empty($ciudades)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="map-pin" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay ciudades en este filtro.</p>
        </div>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table ciudades-tabla">
            <thead>
                <tr><th>Ciudad</th><th>Provincia</th><th class="admin-table__num">Fichas</th><th>Coordenadas</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($ciudades as $c):
                $aliasTxt = implode(', ', json_decode((string) $c['slug_alias_json'], true) ?: []);
                $pill = ['aprobada' => 'admin-pill--exito', 'dudosa' => 'admin-pill--alerta', 'pendiente' => 'admin-pill--error'][$c['estado_revision']]; ?>
                <tr class="ciudades-tabla__fila">
                    <td>
                        <div class="admin-table__principal"><?php echo htmlspecialchars($c['nombre']); ?></div>
                        <div class="admin-table__secundario"><?php echo htmlspecialchars($c['slug']); ?><?php echo $c['nota'] ? ' · ' . htmlspecialchars($c['nota']) : ''; ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($c['provincia_nombre']); ?></td>
                    <td class="admin-table__num"><?php echo (int) $c['fichas']; ?></td>
                    <td><?php echo $c['lat'] !== null ? number_format((float) $c['lat'], 4) . ', ' . number_format((float) $c['lng'], 4) : '<span class="admin-pill admin-pill--alerta">Sin coordenadas</span>'; ?></td>
                    <td><span class="admin-pill <?php echo $pill; ?>"><?php echo $c['estado_revision'] === 'dudosa' ? 'Dudosa' : ucfirst($c['estado_revision']); ?></span></td>
                    <td class="admin-table__acciones">
                        <details class="ciudades-editar">
                            <summary class="boton dos pequeno"><i data-lucide="pencil" class="icono"></i> Editar</summary>
                            <div class="ciudades-editar__cuerpo" style="text-align:left; margin-top:var(--espacio-tres); display:grid; gap:var(--espacio-tres); min-width:320px;">
                                <form method="POST" style="display:grid; gap:var(--espacio-dos);">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="ciudad_id" value="<?php echo (int) $c['id']; ?>">
                                    <input type="hidden" name="volver" value="<?php echo htmlspecialchars($ver); ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                                    <div class="field" style="margin:0;"><label class="field__label">Nombre</label>
                                        <input type="text" name="nombre" class="field__input" value="<?php echo htmlspecialchars($c['nombre']); ?>" required></div>
                                    <div class="field" style="margin:0;"><label class="field__label">Slug (URL, sin acentos)</label>
                                        <input type="text" name="slug" class="field__input" value="<?php echo htmlspecialchars($c['slug']); ?>">
                                        <small class="field__hint">Si lo cambias, el anterior sigue funcionando y redirige al nuevo.</small></div>
                                    <div class="field" style="margin:0;"><label class="field__label">Otras escrituras (separadas por coma)</label>
                                        <input type="text" name="alias" class="field__input" value="<?php echo htmlspecialchars($aliasTxt); ?>" placeholder="elche, elx"></div>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--espacio-dos);">
                                        <div class="field" style="margin:0;"><label class="field__label">Latitud</label>
                                            <input type="text" name="lat" class="field__input" value="<?php echo htmlspecialchars((string) ($c['lat'] ?? '')); ?>"></div>
                                        <div class="field" style="margin:0;"><label class="field__label">Longitud</label>
                                            <input type="text" name="lng" class="field__input" value="<?php echo htmlspecialchars((string) ($c['lng'] ?? '')); ?>"></div>
                                    </div>
                                    <div class="field" style="margin:0;"><label class="field__label">Estado</label>
                                        <select name="estado_revision" class="field__select">
                                            <?php foreach ($ESTADOS as $k => $lbl): ?>
                                            <option value="<?php echo $k; ?>"<?php echo $c['estado_revision'] === $k ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div class="field" style="margin:0;"><label class="field__label">Nota interna</label>
                                        <input type="text" name="nota" class="field__input" value="<?php echo htmlspecialchars($c['nota'] ?? ''); ?>"></div>
                                    <div style="display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
                                        <button type="submit" name="accion" value="guardar" class="boton uno pequeno"><i data-lucide="save" class="icono"></i> Guardar</button>
                                        <?php if ($c['estado_revision'] !== 'aprobada'): ?>
                                        <button type="submit" name="accion" value="aprobar" class="boton uno pequeno"><i data-lucide="check" class="icono"></i> Aprobar</button>
                                        <?php endif; ?>
                                        <button type="submit" name="accion" value="geocodificar" class="boton dos pequeno"><i data-lucide="map-pin" class="icono"></i> Obtener coordenadas</button>
                                    </div>
                                </form>

                                <form method="POST" style="display:flex; gap:var(--espacio-dos); flex-wrap:wrap; align-items:flex-end; border-top:1px solid var(--admin-linea); padding-top:var(--espacio-tres);">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="ciudad_id" value="<?php echo (int) $c['id']; ?>">
                                    <input type="hidden" name="volver" value="<?php echo htmlspecialchars($ver); ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                                    <div class="field" style="margin:0; flex:1; min-width:180px;"><label class="field__label">Fusionar con (misma provincia)</label>
                                        <select name="destino_id" class="field__select" required>
                                            <option value="">Elige la ciudad correcta…</option>
                                            <?php foreach ($hermanas[$c['provincia_id']] ?? [] as $h): if ((int) $h['id'] === (int) $c['id']) continue; ?>
                                            <option value="<?php echo (int) $h['id']; ?>"><?php echo htmlspecialchars($h['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <button type="submit" name="accion" value="fusionar" class="boton dos pequeno" onclick="return confirm('Las fichas pasan a la ciudad elegida y esta se elimina. ¿Continuar?');"><i data-lucide="git-merge" class="icono"></i> Fusionar</button>
                                    <?php if ((int) $c['fichas'] === 0): ?>
                                    <button type="submit" name="accion" value="eliminar" class="boton dos pequeno" onclick="return confirm('¿Eliminar esta ciudad?');"><i data-lucide="trash-2" class="icono"></i> Eliminar</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; /* pestaña ciudades */ ?>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
