<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LOGIN - ACCESO ADMIN - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 *
 * Página de acceso para administradores
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
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

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/componentes.css">

    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Link volver -->
    <a href="<?php echo $base_url; ?>/" style="position: absolute; top: var(--espacio-cinco); left: var(--espacio-cinco); display: flex; align-items: center; gap: var(--espacio-dos); color: var(--color-seis); text-decoration: none; font-size: var(--fs-uno);">
        <i data-lucide="arrow-left" class="icono" style="width: 16px; height: 16px;"></i>
        Volver al inicio
    </a>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENEDOR LOGIN
         ═══════════════════════════════════════════════════════════ -->
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--espacio-cuatro) var(--espacio-cinco); background: var(--color-cuatro);">
        <div class="tarjeta simple" style="width: 100%; max-width: 450px; padding: var(--espacio-seis); background: var(--color-ocho); border: 1px solid var(--color-cinco);">

            <!-- Header Logo-->
            <div style="text-align: center; margin-bottom: var(--espacio-seis);">
                <a href="<?php echo $base_url; ?>/" class="header__logo" style="justify-content: center; margin-bottom: var(--espacio-cuatro);">
                    <i data-lucide="paw-print" class="icono"></i>
                    Crematorios de Mascotas
                </a>
                <h1 class="estilo-h4">Acceso Administrativo</h1>
                <p style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Ingresa tus credenciales para acceder al panel de administración</p>
            </div>

            <!-- Alerta -->
            <div class="alerta error" id="alerta-error" style="display: none; margin-bottom: var(--espacio-cuatro);">
                <strong>Error:</strong> <span id="mensaje-error"></span>
            </div>

            <!-- Formulario -->
            <form onsubmit="iniciarSesion(event)" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                <!-- Email -->
                <div class="formulario-grupo" style="margin-bottom: 0;">
                    <label for="email" class="formulario-etiqueta">Email *</label>
                    <input
                        type="email"
                        id="email"
                        class="campo"
                        placeholder="tu@email.com"
                        required
                    >
                </div>

                <!-- Contraseña -->
                <div class="formulario-grupo" style="margin-bottom: 0;">
                    <label for="password" class="formulario-etiqueta">Contraseña *</label>
                    <input
                        type="password"
                        id="password"
                        class="campo"
                        placeholder="********"
                        required
                    >
                </div>

                <!-- Recordar / Olvidé contraseña -->
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label class="casilla-verificacion" style="padding: 0;">
                        <input type="checkbox" id="recordar">
                        <span class="casilla-verificacion__texto">Recordarme</span>
                    </label>
                    <a href="#" style="font-size: var(--fs-uno); color: var(--color-uno); text-decoration: none;">¿Olvidaste tu contraseña?</a>
                </div>

                <!-- Botón submit -->
                <button type="submit" class="boton uno grande" style="width: 100%;">
                    <i data-lucide="log-in" class="icono"></i>
                    Iniciar sesión
                </button>

            </form>

            <!-- Footer -->
            <div style="text-align: center; margin-top: var(--espacio-seis); padding-top: var(--espacio-cinco); border-top: 1px solid var(--color-cinco);">
                <p style="font-size: var(--fs-uno); color: var(--color-seis-claro); margin-bottom: var(--espacio-dos);">¿No tienes un crematorio registrado?</p>
                <a href="<?php echo $base_url; ?>/registrar-negocio.php" style="color: var(--color-uno); text-decoration: none; font-weight: var(--peso-negrita);">Registra tu negocio aquí</a>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo $base_url; ?>/assets/js/lucide.min.js"></script>
    <script>
        lucide.createIcons();

        function iniciarSesion(event) {
            event.preventDefault();

            // Obtener valores
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const recordar = document.getElementById('recordar').checked;

            // Validación básica
            if (!email || !password) {
                mostrarError('Por favor completa todos los campos');
                return;
            }

            console.log('Iniciando sesión...', { email, recordar });

            // En producción: fetch('/api/login', { method: 'POST', body: JSON.stringify({ email, password }) })

            // Simulación éxito
            setTimeout(() => {
                window.location.href = 'admin/dashboard.php';
            }, 500);
        }

        function mostrarError(mensaje) {
            const alerta = document.getElementById('alerta-error');
            const mensajeSpan = document.getElementById('mensaje-error');

            mensajeSpan.textContent = mensaje;
            alerta.style.display = 'flex';

            setTimeout(() => {
                alerta.style.display = 'none';
            }, 5000);
        }
    </script>

</body>
</html>
