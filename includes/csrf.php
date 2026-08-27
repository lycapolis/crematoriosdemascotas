<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CSRF — Protección contra falsificación de peticiones
 * ═══════════════════════════════════════════════════════════
 *
 * Helper genérico y reusable. Hoy se usa en admin/login.php,
 * pensado para sumarse después a otros formularios POST del panel
 * (admin-editar.php, resena-accion.php, etc.) sin duplicar lógica.
 *
 * Uso:
 *   require_once 'csrf.php';
 *   $token = generarTokenCSRF();          // en el <form>, campo hidden
 *   validarTokenCSRF($_POST['csrf_token'] ?? '');  // al procesar el POST
 */

/**
 * Genera (o reusa, si ya existe en la sesión activa) un token CSRF.
 * Requiere que la sesión ya esté iniciada (session_start() previo).
 *
 * @return string Token en hexadecimal, listo para imprimir en un input hidden.
 */
function generarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF recibido contra el guardado en sesión.
 * Comparación timing-safe con hash_equals().
 *
 * @param string $tokenRecibido Valor recibido en $_POST['csrf_token'].
 * @return bool
 */
function validarTokenCSRF(string $tokenRecibido): bool
{
    if (empty($_SESSION['csrf_token']) || $tokenRecibido === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $tokenRecibido);
}
