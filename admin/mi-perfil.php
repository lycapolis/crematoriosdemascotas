<?php
/**
 * Panel Admin — Mi perfil.
 * Accesible para todos los roles autenticados.
 * Permite editar datos personales y ver rol/etiquetas asignadas (read-only).
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

// Cargar la fila completa desde BD (datos frescos, no de sesión)
$stmt = $pdo->prepare("SELECT id, nombre, email, rol, etiquetas, ultimo_login, created_at FROM admins WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminActual['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: logout.php');
    exit;
}

$error = '';
$ok    = '';

// ─── Procesar POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre          = trim($_POST['nombre'] ?? '');
    $email           = trim($_POST['email']  ?? '');
    $passwordActual  = $_POST['password_actual'] ?? '';
    $passwordNuevo   = $_POST['password_nuevo']  ?? '';
    $passwordNuevo2  = $_POST['password_nuevo2'] ?? '';

    if (!$nombre || !$email) $error = 'Nombre y email son obligatorios.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email no válido.';

    // Si quiere cambiar password, verificar
    $cambiarPassword = $passwordNuevo !== '' || $passwordNuevo2 !== '';
    if (!$error && $cambiarPassword) {
        if (strlen($passwordNuevo) < 8) {
            $error = 'El password nuevo requiere al menos 8 caracteres.';
        } elseif ($passwordNuevo !== $passwordNuevo2) {
            $error = 'Los dos passwords nuevos no coinciden.';
        } else {
            // Verificar password actual contra BD
            $stmtPwd = $pdo->prepare("SELECT password_hash FROM admins WHERE id = :id LIMIT 1");
            $stmtPwd->execute([':id' => $admin['id']]);
            $hashActual = $stmtPwd->fetchColumn();
            if (!password_verify($passwordActual, $hashActual)) {
                $error = 'El password actual es incorrecto.';
            }
        }
    }

    // Email único (otros admins)
    if (!$error && $email !== $admin['email']) {
        $stmtChk = $pdo->prepare("SELECT id FROM admins WHERE email = :email AND id != :id LIMIT 1");
        $stmtChk->execute([':email' => $email, ':id' => $admin['id']]);
        if ($stmtChk->fetch()) $error = 'Ya existe otro admin con ese email.';
    }

    if (!$error) {
        $campos = [':id' => $admin['id'], ':nombre' => $nombre, ':email' => $email];
        $setPwd = '';
        if ($cambiarPassword) {
            $campos[':hash'] = password_hash($passwordNuevo, PASSWORD_BCRYPT);
            $setPwd = ', password_hash = :hash';
        }
        $sql = "UPDATE admins SET nombre = :nombre, email = :email {$setPwd} WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($campos);

        // Refrescar sesión
        $_SESSION['admin_nombre'] = $nombre;
        $_SESSION['admin_email']  = $email;

        // Recargar datos para mostrar al volver a render
        $admin['nombre'] = $nombre;
        $admin['email']  = $email;

        $ok = 'Perfil actualizado correctamente.' . ($cambiarPassword ? ' Password cambiado.' : '');
    }
}

$tags = !empty($admin['etiquetas']) ? (json_decode($admin['etiquetas'], true) ?: []) : [];
$rol  = $admin['rol'] ?? 'admin';

// Mapeo rol → variante de pill
$rolPillCls = match ($rol) {
    'super_admin' => 'admin-pill--error',
    'admin'       => 'admin-pill--info',
    'user'        => '',
    default       => '',
};

$titulo_pagina = 'Mi perfil — Panel';

// header.php hace `$admin = obtenerAdminActual()` y pisa la variable.
// Preservo los datos frescos de BD para usarlos en el render.
$adminBd = $admin;
include 'header.php';
$admin = $adminBd;
?>

<div class="admin-page admin-page--narrow">

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap;">
            <h1 class="admin-page-title">Mi perfil</h1>
            <span class="admin-pill <?php echo $rolPillCls; ?>" style="font-family: monospace;">
                <?php echo htmlspecialchars($rol); ?>
            </span>
        </div>
        <p class="admin-page-subtitle">
            Datos personales y permisos asignados
        </p>
    </header>

    <!-- ═══ Banners ═══ -->
    <?php if ($ok): ?>
    <div class="admin-banner admin-banner--ok">
        <i data-lucide="check-circle" class="icono"></i>
        <div class="admin-banner__contenido">
            <span><?php echo htmlspecialchars($ok); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="admin-banner admin-banner--error">
        <i data-lucide="alert-triangle" class="icono"></i>
        <div class="admin-banner__contenido">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ SECCIÓN: Datos personales (form) ═══ -->
    <section class="admin-section">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">
                <i data-lucide="user" class="icono" style="width:18px; height:18px;"></i>
                Datos personales
            </h2>
        </div>

        <form method="POST" class="admin-section__body" style="display:flex; flex-direction:column; gap: var(--espacio-cuatro);">

            <div class="field" style="margin-bottom: 0;">
                <label class="field__label">
                    Nombre
                    <span class="field__req">requerido</span>
                </label>
                <input type="text" name="nombre" class="field__input" required value="<?php echo htmlspecialchars($admin['nombre']); ?>">
            </div>

            <div class="field" style="margin-bottom: 0;">
                <label class="field__label">
                    Email
                    <span class="field__req">requerido</span>
                </label>
                <input type="email" name="email" class="field__input" required value="<?php echo htmlspecialchars($admin['email']); ?>">
            </div>

            <hr class="admin-rule" style="margin: 0;">

            <div>
                <h3 style="font-size: var(--admin-body); color: var(--admin-tinta-fuerte); font-weight: 700; margin: 0 0 .3rem 0;">Cambiar password</h3>
                <p style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin: 0;">Opcional — dejá vacío para no cambiar.</p>
            </div>

            <div class="field" style="margin-bottom: 0;">
                <label class="field__label">Password actual</label>
                <div class="field__password">
                    <input type="password" name="password_actual" class="field__input" autocomplete="current-password">
                    <button type="button" class="field__pwd-toggle" onclick="adminTogglePassword(this)" aria-label="Mostrar password" tabindex="-1">
                        <i data-lucide="eye" class="icono"></i>
                    </button>
                </div>
                <p class="field__hint">Solo si querés cambiar el password.</p>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-tres);">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label">Password nuevo</label>
                    <div class="field__password">
                        <input type="password" name="password_nuevo" class="field__input" autocomplete="new-password" minlength="8">
                        <button type="button" class="field__pwd-toggle" onclick="adminTogglePassword(this)" aria-label="Mostrar password" tabindex="-1">
                            <i data-lucide="eye" class="icono"></i>
                        </button>
                    </div>
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label">Confirmar nuevo</label>
                    <div class="field__password">
                        <input type="password" name="password_nuevo2" class="field__input" autocomplete="new-password" minlength="8">
                        <button type="button" class="field__pwd-toggle" onclick="adminTogglePassword(this)" aria-label="Mostrar password" tabindex="-1">
                            <i data-lucide="eye" class="icono"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap: var(--espacio-dos); justify-content: flex-end; border-top: 1px solid var(--admin-linea); padding-top: var(--espacio-cuatro); margin-top: var(--espacio-dos);">
                <button type="submit" class="boton uno">
                    <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </section>

    <!-- ═══ SECCIÓN: Rol y capacidades (read-only) ═══ -->
    <section class="admin-section">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">
                <i data-lucide="shield" class="icono" style="width:18px; height:18px;"></i>
                Mi rol y capacidades
            </h2>
        </div>

        <div class="admin-section__body">
            <!-- Rol -->
            <div style="display:flex; align-items: center; gap: .8rem; padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--admin-linea); margin-bottom: var(--espacio-tres);">
                <span class="admin-pill <?php echo $rolPillCls; ?>" style="font-family: monospace; padding: .35rem .9rem; font-size: var(--admin-body-sm);">
                    <?php echo htmlspecialchars($rol); ?>
                </span>
                <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave);">
                    <?php echo htmlspecialchars(ROLES_DISPONIBLES[$rol] ?? $rol); ?>
                </span>
            </div>

            <!-- Etiquetas activas -->
            <?php if ($rol === 'super_admin'): ?>
            <p style="font-size: var(--admin-body-sm); color: var(--admin-tinta); margin: 0; line-height: 1.6;">
                Como <code style="background: var(--admin-papel-alt); padding: .1rem .35rem; border-radius: 4px; font-size: .85em;">super_admin</code> tenés acceso completo. Las etiquetas no aplican — todas las capacidades están habilitadas.
            </p>
            <?php elseif (empty($tags)): ?>
            <div class="admin-banner admin-banner--warning" style="margin-bottom: 0;">
                <i data-lucide="info" class="icono"></i>
                <div class="admin-banner__contenido">
                    <strong class="admin-banner__titulo">Sin etiquetas asignadas</strong>
                    <span>Tu acceso es de solo lectura. Pedile a un super admin las capacidades que necesites.</span>
                </div>
            </div>
            <?php else: ?>
            <div style="display:flex; flex-direction: column; gap: .5rem;">
                <?php foreach ($tags as $tag): ?>
                <div style="display:flex; align-items: flex-start; gap: .65rem; padding: .6rem .8rem; background: var(--admin-papel); border-radius: var(--admin-r-sm);">
                    <span class="admin-pill admin-pill--exito" style="font-family: monospace; flex-shrink: 0; margin-top: .1rem;">
                        <?php echo htmlspecialchars($tag); ?>
                    </span>
                    <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta); line-height: 1.5;">
                        <?php echo htmlspecialchars(ETIQUETAS_DISPONIBLES[$tag] ?? '— sin descripción'); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php
            // Capacidades que no tiene (solo no-super)
            if ($rol !== 'super_admin'):
                $faltantes = array_diff(array_keys(ETIQUETAS_DISPONIBLES), $tags);
                if (!empty($faltantes)):
            ?>
            <details style="margin-top: var(--espacio-cuatro);">
                <summary class="admin-link" style="display: inline-flex; align-items: center; gap: .35rem; font-size: var(--admin-body-sm); cursor: pointer;">
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px;"></i>
                    Capacidades disponibles que no tenés (<?php echo count($faltantes); ?>)
                </summary>
                <div style="margin-top: var(--espacio-tres); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--admin-tone-alerta-bg); border-radius: var(--admin-r-sm);">
                    <p style="font-size: var(--admin-body-sm); color: var(--admin-tone-alerta-fg); margin: 0 0 .8rem 0; font-weight: 500;">
                        Si necesitás alguna de estas para tu trabajo, contactá a un super admin para que te la habilite.
                    </p>
                    <ul style="margin: 0; padding-left: 1.2rem; font-size: var(--admin-body-sm); color: var(--admin-tone-alerta-fg); line-height: 1.7;">
                        <?php foreach ($faltantes as $tag): ?>
                        <li>
                            <code style="background: rgba(255,255,255,.5); padding: .05rem .35rem; border-radius: 4px; font-size: .9em; font-weight: 700;"><?php echo htmlspecialchars($tag); ?></code>
                            <span style="margin-left: .25rem;">— <?php echo htmlspecialchars(ETIQUETAS_DISPONIBLES[$tag]); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
            <?php
                endif;
            endif;
            ?>
        </div>
    </section>

    <!-- ═══ SECCIÓN: Info de cuenta ═══ -->
    <section class="admin-section">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">
                <i data-lucide="info" class="icono" style="width:18px; height:18px;"></i>
                Cuenta
            </h2>
        </div>
        <div class="admin-section__body">
            <?php
                $createdAt   = $admin['created_at']   ?? null;
                $ultimoLogin = $admin['ultimo_login'] ?? null;
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--espacio-cuatro);">
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Cuenta creada</div>
                    <div style="color: var(--admin-tinta-fuerte); margin-top: .3rem; font-size: var(--admin-body-sm); font-variant-numeric: tabular-nums;">
                        <?php echo $createdAt ? date('d/m/Y H:i', strtotime($createdAt)) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Último login</div>
                    <div style="color: var(--admin-tinta-fuerte); margin-top: .3rem; font-size: var(--admin-body-sm); font-variant-numeric: tabular-nums;">
                        <?php echo $ultimoLogin ? date('d/m/Y H:i', strtotime($ultimoLogin)) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">ID de admin</div>
                    <div style="color: var(--admin-tinta-fuerte); margin-top: .3rem; font-family: monospace; font-size: var(--admin-body-sm); font-variant-numeric: tabular-nums;">
                        #<?php echo (int)$admin['id']; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<?php include 'footer.php'; ?>
