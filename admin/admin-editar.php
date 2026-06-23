<?php
/**
 * Panel Admin — Crear/Editar admin (rol + etiquetas + activo + password).
 * Accesible para super_admin o admins con etiqueta 'gestionar_admins'.
 *
 * Reglas de seguridad:
 *   - Solo super_admin puede crear/editar otro super_admin.
 *   - Un admin no super no puede cambiar su propio rol/etiquetas.
 *   - No se permite que el único super_admin activo se desactive ni se degrade.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

if (!esSuperAdmin()) {
    requierePermiso('gestionar_admins');
}

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$esNuevo = $id === 0;
$error   = '';

// ─── Cargar admin a editar ─────────────────────────────────────────────────
$admin = null;
if (!$esNuevo) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        header('Location: admins.php?error=' . urlencode('Admin no encontrado'));
        exit;
    }
}

// ─── Procesar POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $rol      = $_POST['rol']           ?? 'admin';
    $activo   = !empty($_POST['activo']);
    $tags     = $_POST['etiquetas']     ?? [];
    if (!is_array($tags)) $tags = [];

    // Sanitizar tags contra el catálogo
    $tagsValidos = array_keys(ETIQUETAS_DISPONIBLES);
    $tags = array_values(array_intersect($tags, $tagsValidos));

    // Validar rol
    if (!array_key_exists($rol, ROLES_DISPONIBLES)) $rol = 'admin';

    // Reglas de seguridad
    if (!esSuperAdmin() && $rol === 'super_admin') {
        $error = 'Solo un super_admin puede crear o asignar el rol super_admin.';
    }
    if (!$esNuevo && !esSuperAdmin() && ($admin['rol'] ?? '') === 'super_admin') {
        $error = 'Solo un super_admin puede editar a otro super_admin.';
    }
    if (!$esNuevo && (int)$admin['id'] === (int)$adminActual['id'] && !esSuperAdmin() && $rol !== $admin['rol']) {
        $error = 'No podés cambiar tu propio rol.';
    }
    if (!$esNuevo && (int)$admin['id'] === (int)$adminActual['id'] && esSuperAdmin() && $rol !== 'super_admin') {
        // Verificar que no es el único super_admin
        $otros = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE rol = 'super_admin' AND activo = 1 AND id != " . (int)$admin['id'])->fetchColumn();
        if ($otros === 0) $error = 'No podés degradarte: sos el único super_admin activo.';
    }
    if (!$esNuevo && (int)$admin['id'] === (int)$adminActual['id'] && !$activo && esSuperAdmin()) {
        $otros = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE rol = 'super_admin' AND activo = 1 AND id != " . (int)$admin['id'])->fetchColumn();
        if ($otros === 0) $error = 'No podés desactivarte: sos el único super_admin activo.';
    }

    if (!$nombre || !$email) $error = $error ?: 'Nombre y email son obligatorios.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = $error ?: 'Email no válido.';
    if ($esNuevo && strlen($password) < 8) $error = $error ?: 'Password requiere al menos 8 caracteres.';
    if (!$esNuevo && $password !== '' && strlen($password) < 8) $error = $error ?: 'Password nuevo requiere al menos 8 caracteres.';

    // Verificar email único (excluyendo el actual)
    if (!$error) {
        $sqlChk = $esNuevo
            ? "SELECT id FROM admins WHERE email = :email LIMIT 1"
            : "SELECT id FROM admins WHERE email = :email AND id != :id LIMIT 1";
        $stmtChk = $pdo->prepare($sqlChk);
        $stmtChk->execute($esNuevo ? [':email' => $email] : [':email' => $email, ':id' => $id]);
        if ($stmtChk->fetch()) $error = 'Ya existe un admin con ese email.';
    }

    if (!$error) {
        // super_admin con etiquetas → NULL (ignora)
        $tagsJson = ($rol === 'super_admin') ? null : json_encode($tags, JSON_UNESCAPED_UNICODE);

        if ($esNuevo) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO admins (nombre, email, password_hash, rol, etiquetas, activo)
                    VALUES (:nombre, :email, :hash, :rol, :tags, :activo)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':email'  => $email,
                ':hash'   => $hash,
                ':rol'    => $rol,
                ':tags'   => $tagsJson,
                ':activo' => $activo ? 1 : 0,
            ]);
            $nuevoId = $pdo->lastInsertId();
            header('Location: admins.php?ok=' . urlencode('Admin "' . $nombre . '" creado'));
            exit;
        } else {
            $campos = [
                ':id'     => $id,
                ':nombre' => $nombre,
                ':email'  => $email,
                ':rol'    => $rol,
                ':tags'   => $tagsJson,
                ':activo' => $activo ? 1 : 0,
            ];
            $setPwd = '';
            if ($password !== '') {
                $campos[':hash'] = password_hash($password, PASSWORD_BCRYPT);
                $setPwd = ', password_hash = :hash';
            }
            $sql = "UPDATE admins SET nombre = :nombre, email = :email, rol = :rol,
                    etiquetas = :tags, activo = :activo {$setPwd} WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($campos);

            // Si el admin editado es el actual, refrescar sesión
            if ((int)$id === (int)$adminActual['id']) {
                $_SESSION['admin_nombre']    = $nombre;
                $_SESSION['admin_email']     = $email;
                $_SESSION['admin_rol']       = $rol;
                $_SESSION['admin_etiquetas'] = $tagsJson;
            }

            header('Location: admins.php?ok=' . urlencode('Admin "' . $nombre . '" actualizado'));
            exit;
        }
    }

    // Si hubo error, repoblar para mostrar valores ingresados
    $admin = [
        'id'       => $id,
        'nombre'   => $nombre,
        'email'    => $email,
        'rol'      => $rol,
        'etiquetas'=> json_encode($tags),
        'activo'   => $activo ? 1 : 0,
    ];
}

// Valores iniciales
$nombreVal = $admin['nombre']     ?? '';
$emailVal  = $admin['email']      ?? '';
$rolVal    = $admin['rol']        ?? 'admin';
$activoVal = isset($admin['activo']) ? (int)$admin['activo'] : 1;
$tagsVal   = !empty($admin['etiquetas']) ? (json_decode($admin['etiquetas'], true) ?: []) : ETIQUETAS_DEFAULT_POR_ROL[$rolVal] ?? [];
$esYo      = !$esNuevo && (int)$admin['id'] === (int)$adminActual['id'];

// Bloquear edición de super_admin para no-super
$bloqueado = !esSuperAdmin() && $rolVal === 'super_admin' && !$esNuevo;

$titulo_pagina = ($esNuevo ? 'Nuevo admin' : 'Editar admin') . ' — Panel';
include 'header.php';
?>

<div class="admin-page admin-page--narrow">

    <!-- Volver -->
    <a href="admins.php" class="admin-link" style="display:inline-flex; align-items:center; gap:.35rem; margin-bottom: var(--espacio-tres); font-size: var(--admin-body-sm);">
        <i data-lucide="arrow-left" class="icono" style="width:14px; height:14px;"></i>
        Volver a admins
    </a>

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap;">
            <h1 class="admin-page-title"><?php echo $esNuevo ? 'Nuevo admin' : 'Editar admin'; ?></h1>
            <?php if ($esYo): ?>
            <span class="admin-pill admin-pill--info">vos</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- ═══ Banners ═══ -->
    <?php if ($error): ?>
    <div class="admin-banner admin-banner--error">
        <i data-lucide="alert-triangle" class="icono"></i>
        <div class="admin-banner__contenido">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($bloqueado): ?>
    <div class="admin-banner admin-banner--warning">
        <i data-lucide="lock" class="icono"></i>
        <div class="admin-banner__contenido">
            <strong class="admin-banner__titulo">Modo solo lectura</strong>
            <span>Solo un super_admin puede editar a otro super_admin. Podés ver los datos pero no modificarlos.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Form ═══ -->
    <form method="POST" class="admin-section__body" style="display:flex; flex-direction:column; gap: var(--espacio-cuatro);">

        <div class="field" style="margin-bottom: 0;">
            <label class="field__label">
                Nombre
                <span class="field__req">requerido</span>
            </label>
            <input type="text" name="nombre" class="field__input" required value="<?php echo htmlspecialchars($nombreVal); ?>" <?php echo $bloqueado ? 'disabled' : ''; ?>>
        </div>

        <div class="field" style="margin-bottom: 0;">
            <label class="field__label">
                Email
                <span class="field__req">requerido</span>
            </label>
            <input type="email" name="email" class="field__input" required value="<?php echo htmlspecialchars($emailVal); ?>" <?php echo $bloqueado ? 'disabled' : ''; ?>>
        </div>

        <div class="field" style="margin-bottom: 0;">
            <label class="field__label">
                Password
                <span class="field__req"><?php echo $esNuevo ? 'requerido' : 'opcional'; ?></span>
            </label>
            <div class="field__password">
                <input type="password" name="password" class="field__input" autocomplete="new-password" minlength="8" <?php echo $esNuevo ? 'required' : ''; ?> <?php echo $bloqueado ? 'disabled' : ''; ?>>
                <button type="button" class="field__pwd-toggle" onclick="adminTogglePassword(this)" aria-label="Mostrar password" tabindex="-1">
                    <i data-lucide="eye" class="icono"></i>
                </button>
            </div>
            <p class="field__hint">
                <?php echo $esNuevo ? 'Mínimo 8 caracteres.' : 'Dejá vacío para mantener el password actual. Mínimo 8 caracteres si lo cambiás.'; ?>
            </p>
        </div>

        <div class="field" style="margin-bottom: 0;">
            <label class="field__label">
                Rol
                <span class="field__req">requerido</span>
            </label>
            <select name="rol" id="rolSelect" class="field__select" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                <?php foreach (ROLES_DISPONIBLES as $key => $lbl):
                    if ($key === 'super_admin' && !esSuperAdmin()) continue; ?>
                <option value="<?php echo $key; ?>" <?php echo $rolVal === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lbl); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="bloqueEtiquetas" style="<?php echo $rolVal === 'super_admin' ? 'display:none;' : ''; ?>">
            <label class="field__label" style="margin-bottom: .6rem; display: flex; justify-content: space-between;">
                <span>Etiquetas de capacidad</span>
                <span class="field__req">no aplican a super_admin</span>
            </label>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:.6rem;">
                <?php foreach (ETIQUETAS_DISPONIBLES as $key => $desc):
                    $checked = in_array($key, $tagsVal, true);
                ?>
                <label class="admin-tag-option<?php echo $checked ? ' admin-tag-option--checked' : ''; ?>"<?php echo $bloqueado ? ' style="opacity:.55; cursor:not-allowed;"' : ''; ?>>
                    <input type="checkbox" class="field__check" name="etiquetas[]" value="<?php echo $key; ?>"
                           <?php echo $checked ? 'checked' : ''; ?>
                           <?php echo $bloqueado ? 'disabled' : ''; ?>
                           onchange="this.closest('.admin-tag-option').classList.toggle('admin-tag-option--checked', this.checked)">
                    <div class="admin-tag-option__body">
                        <div class="admin-tag-option__name"><?php echo htmlspecialchars($key); ?></div>
                        <div class="admin-tag-option__desc"><?php echo htmlspecialchars($desc); ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cuenta activa toggle (switch más prominente que un checkbox suelto) -->
        <label class="field__opcion" style="gap: .75rem; padding: .85rem 1rem; background: var(--admin-papel); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-sm);<?php echo $bloqueado ? ' opacity: .55; cursor: not-allowed;' : ''; ?>">
            <input type="checkbox" class="field__switch" name="activo" value="1" <?php echo $activoVal ? 'checked' : ''; ?> <?php echo $bloqueado ? 'disabled' : ''; ?>>
            <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-fuerte); font-weight: 600;">Cuenta activa</span>
            <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave);">— si está desactivada no puede loguearse</span>
        </label>

        <?php if (!$bloqueado): ?>
        <div style="display:flex; gap: var(--espacio-dos); justify-content:flex-end; border-top:1px solid var(--admin-linea); padding-top: var(--espacio-cuatro); margin-top: var(--espacio-dos);">
            <a href="admins.php" class="boton dos">Cancelar</a>
            <button type="submit" class="boton uno">
                <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                <?php echo $esNuevo ? 'Crear admin' : 'Guardar cambios'; ?>
            </button>
        </div>
        <?php endif; ?>
    </form>

</div>

<script>
// Ocultar etiquetas si rol es super_admin
document.getElementById('rolSelect')?.addEventListener('change', function(e) {
    const bloque = document.getElementById('bloqueEtiquetas');
    if (!bloque) return;
    bloque.style.display = e.target.value === 'super_admin' ? 'none' : '';
});
</script>

<?php include 'footer.php'; ?>
