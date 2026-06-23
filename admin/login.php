<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LOGIN ADMIN - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';

// Si ya está autenticado, redirigir al dashboard
if (estaAutenticado()) {
    header('Location: ' . BASE_URL . '/admin/resenas.php');
    exit;
}

$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Por favor completa todos los campos';
    } else {
        $resultado = intentarLogin($email, $password);

        if ($resultado['ok']) {
            header('Location: ' . BASE_URL . '/admin/resenas.php');
            exit;
        } else {
            $error = $resultado['mensaje'];
        }
    }
}

$titulo_pagina = 'Acceso Admin - Crematorios de Mascotas';
$base_url = BASE_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo_pagina); ?></title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $base_url; ?>/assets/img/favicon/favicon-96x96.png">

    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/componentes.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <a href="<?php echo $base_url; ?>/" style="position: absolute; top: var(--espacio-cinco); left: var(--espacio-cinco); display: flex; align-items: center; gap: var(--espacio-dos); color: var(--color-seis); text-decoration: none; font-size: var(--fs-uno);">
        <i data-lucide="arrow-left" class="icono" style="width: 16px; height: 16px;"></i>
        Volver al inicio
    </a>

    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--espacio-cuatro) var(--espacio-cinco); background: var(--color-cuatro);">
        <div class="tarjeta simple" style="width: 100%; max-width: 450px; padding: var(--espacio-seis); background: var(--color-ocho); border: 1px solid var(--color-cinco);">

            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <a href="<?php echo $base_url; ?>/" class="header__logo" style="justify-content: center; margin-bottom: var(--espacio-cuatro);">
                    <i data-lucide="paw-print" class="icono"></i>
                    Crematorios de Mascotas
                </a>
                <h1 class="estilo-h4">Acceso Administrativo</h1>
                <p style="font-size: var(--fs-uno); color: var(--color-seis); opacity: 0.7;">Ingresa tus credenciales para acceder al panel</p>
            </div>

            <?php if ($error): ?>
            <div class="alerta error" style="margin-bottom: var(--espacio-cuatro);">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">
                <div class="formulario-grupo" style="margin-bottom: 0;">
                    <label for="email" class="formulario-etiqueta">Email *</label>
                    <input type="email" id="email" name="email" class="campo" placeholder="tu@email.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="formulario-grupo" style="margin-bottom: 0;">
                    <label for="password" class="formulario-etiqueta">Contraseña *</label>
                    <input type="password" id="password" name="password" class="campo" placeholder="********" required>
                </div>

                <button type="submit" class="boton uno grande" style="width: 100%;">
                    <i data-lucide="log-in" class="icono"></i>
                    Iniciar sesión
                </button>
            </form>
        </div>
    </div>

    <script src="<?php echo $base_url; ?>/assets/js/lucide.min.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
