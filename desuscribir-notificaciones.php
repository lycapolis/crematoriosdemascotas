<?php
/**
 * ═══════════════════════════════════════════════════════════
 * DEJAR DE RECIBIR NOTIFICACIONES — página pública
 * ═══════════════════════════════════════════════════════════
 *
 * Enlace de los emails transaccionales al autor de una reseña:
 *   /desuscribir-notificaciones.php?e=<email en base64url>&t=<firma HMAC>
 *
 * - GET  → muestra la confirmación (no actúa: los antivirus/clientes de correo
 *          precargan enlaces y darían de baja a todo el mundo).
 * - POST → registra la baja en notificaciones_supresiones.
 *
 * Solo afecta a las NOTIFICACIONES sobre lo que el usuario compartió. La reseña
 * publicada no se toca. Si el mismo email vuelve a enviar una reseña, se reactivan
 * únicamente las notificaciones.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/notificaciones/despachador.php';

$email  = mb_strtolower(trim(notifB64UrlDecode((string)($_GET['e'] ?? $_POST['e'] ?? ''))));
$token  = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$valido = filter_var($email, FILTER_VALIDATE_EMAIL) && notifTokenValido($email, $token);

$hecho = false;
if ($valido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = obtenerConexion();
    if ($pdo) {
        try {
            notifSuprimir($pdo, $email);
            $hecho = true;
        } catch (\Throwable $e) {
            error_log('[desuscribir-notificaciones] ' . $e->getMessage());
        }
    }
}

header('Referrer-Policy: no-referrer');
$titulo_pagina = 'Notificaciones por email';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';
?>

<style>
    .notif-baja { min-height: 60vh; display: flex; align-items: center; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .notif-baja__inner { max-width: 520px; text-align: center; }
    .notif-baja__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .notif-baja__texto { color: var(--color-seis); line-height: 1.65; margin: 0 0 var(--espacio-cuatro); }
    .notif-baja__acciones { display: flex; gap: var(--espacio-tres); justify-content: center; flex-wrap: wrap; }
    @media (max-width: 600px) { .notif-baja__acciones .boton { width: 100%; } }
</style>

<main class="notif-baja">
    <div class="notif-baja__inner">
    <?php if (!$valido): ?>
        <h1 class="notif-baja__titulo">Enlace no válido</h1>
        <p class="notif-baja__texto">Este enlace no es válido o está incompleto. Si quieres dejar de recibir notificaciones, escríbenos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a> y lo gestionamos.</p>

    <?php elseif ($hecho): ?>
        <h1 class="notif-baja__titulo">Hecho, no te enviaremos más avisos</h1>
        <p class="notif-baja__texto">Ya no recibirás notificaciones por email sobre reseñas. Las reseñas ya publicadas siguen visibles. Si en el futuro vuelves a enviarnos una reseña con este mismo email, volveremos a avisarte de ella.</p>

    <?php else: ?>
        <h1 class="notif-baja__titulo">¿Dejar de recibir notificaciones?</h1>
        <p class="notif-baja__texto">Confirma que no quieres recibir avisos por email sobre reseñas. No enviamos publicidad a esta dirección.</p>
        <form method="post" class="notif-baja__acciones">
            <input type="hidden" name="e" value="<?= htmlspecialchars((string)($_GET['e'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="boton uno">Sí, dejar de recibir avisos</button>
            <a href="<?= BASE_URL ?>/" class="boton dos">Cancelar</a>
        </form>
    <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
