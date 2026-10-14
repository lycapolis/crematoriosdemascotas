<?php
/**
 * ═══════════════════════════════════════════════════════════
 * RESPONDER A UNA RESEÑA — página pública del negocio
 * ═══════════════════════════════════════════════════════════
 *
 * Enlace del email «Tu negocio ha recibido una reseña positiva»:
 *   /responder-resena.php?r=<id de la reseña>&t=<firma HMAC>
 *
 * El negocio no tiene cuenta: el enlace firmado es su credencial.
 * - GET  → muestra la reseña y el formulario (o el estado de su respuesta).
 * - POST → guarda la respuesta como «pendiente». NADA se publica sin moderación.
 *
 * Límite: una respuesta viva (pendiente o aprobada) por reseña. Si se rechaza,
 * se puede volver a enviar pasadas 24 h. No es un chat.
 * Antispam: honeypot + time-trap + máximo de envíos por IP y hora.
 * Las fechas se comparan en SQL (NOW()) para no depender de la zona horaria de PHP.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/notificaciones/despachador.php';

const RESPUESTA_MIN_CARACTERES = 10;
const RESPUESTA_MAX_CARACTERES = 1000;
const RESPUESTA_MAX_POR_IP_HORA = 5;

$resenaId = (int)($_GET['r'] ?? $_POST['r'] ?? 0);
$token    = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$firmaOk  = notifTokenRespuestaValido($resenaId, $token);

$pdo    = $firmaOk ? obtenerConexion() : null;
$resena = null;
$ultima = null;          // última respuesta de esta reseña (cualquier estado)
$error  = '';
$enviada = false;
$notificarRespuestaId = 0;

if ($pdo) {
    $st = $pdo->prepare("SELECT r.id, r.nombre, r.comentario, r.calificacion, r.created_at,
                                c.nombre AS negocio_nombre, c.slug AS negocio_slug
                         FROM resenas r
                         JOIN crematorios c ON c.id = r.crematorio_id
                         WHERE r.id = ? AND r.fuente = 'propio' AND r.estado = 'aprobada'
                         LIMIT 1");
    $st->execute([$resenaId]);
    $resena = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Última respuesta + si ya pasaron 24 h desde que se envió (calculado en SQL). */
function respuestaUltima(PDO $pdo, int $resenaId): ?array
{
    $st = $pdo->prepare("SELECT id, texto, estado, created_at,
                                (created_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS puede_reintentar,
                                DATE_ADD(created_at, INTERVAL 1 DAY) AS reintento_desde
                         FROM resenas_respuestas WHERE resena_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$resenaId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($resena) {
    $ultima = respuestaUltima($pdo, $resenaId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $texto = trim(strip_tags((string)($_POST['respuesta'] ?? '')));
        $texto = preg_replace("/\r\n?/", "\n", $texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
        $largo = mb_strlen($texto);
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

        // Honeypot y time-trap: a los bots se les dice «ok» sin guardar nada
        $esBot = trim((string)($_POST['website_url'] ?? '')) !== ''
              || (time() - (int)($_POST['form_render_ts'] ?? 0)) < 3;

        $vivaOReciente = $ultima && ($ultima['estado'] !== 'rechazada' || !(int)$ultima['puede_reintentar']);

        if ($esBot) {
            error_log('anti-spam bloqueado [responder-resena] ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            $enviada = true;
        } elseif ($vivaOReciente) {
            $error = 'Esta reseña ya tiene una respuesta registrada.';
        } elseif ($largo < RESPUESTA_MIN_CARACTERES) {
            $error = 'Escribe al menos ' . RESPUESTA_MIN_CARACTERES . ' caracteres.';
        } elseif ($largo > RESPUESTA_MAX_CARACTERES) {
            $error = 'La respuesta es demasiado larga (máximo ' . RESPUESTA_MAX_CARACTERES . ' caracteres).';
        } else {
            try {
                $q = $pdo->prepare("SELECT COUNT(*) FROM resenas_respuestas WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                $q->execute([$ipHash]);
                if ((int)$q->fetchColumn() >= RESPUESTA_MAX_POR_IP_HORA) {
                    $error = 'Se han enviado varias respuestas desde tu conexión. Vuelve a intentarlo dentro de un rato.';
                } else {
                    // Inserción atómica: solo si no hay una respuesta viva ni un rechazo de hace menos de 24 h
                    $ins = $pdo->prepare("INSERT INTO resenas_respuestas (resena_id, texto, estado, ip_hash)
                                          SELECT ?, ?, 'pendiente', ? FROM DUAL
                                          WHERE NOT EXISTS (
                                              SELECT 1 FROM resenas_respuestas
                                              WHERE resena_id = ?
                                                AND (estado IN ('pendiente','aprobada')
                                                     OR created_at > DATE_SUB(NOW(), INTERVAL 1 DAY))
                                          )");
                    $ins->execute([$resenaId, $texto, $ipHash, $resenaId]);
                    if ($ins->rowCount() === 1) {
                        $enviada = true;
                        $notificarRespuestaId = (int)$pdo->lastInsertId();
                    } else {
                        $error = 'Esta reseña ya tiene una respuesta registrada.';
                    }
                }
            } catch (\Throwable $e) {
                error_log('[responder-resena] ' . $e->getMessage());
                $error = 'No hemos podido guardar tu respuesta. Inténtalo de nuevo en unos minutos.';
            }
        }
        $ultima = respuestaUltima($pdo, $resenaId);
    }
}

$puedeResponder = $resena && !$enviada && (!$ultima || ($ultima['estado'] === 'rechazada' && (int)$ultima['puede_reintentar']));

header('Referrer-Policy: no-referrer');
$titulo_pagina = 'Responder a una reseña';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<style>
    .resp-resena { min-height: 60vh; display: flex; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .resp-resena__inner { width: 100%; max-width: 640px; }
    .resp-resena__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .resp-resena__texto { color: var(--color-seis); line-height: 1.65; margin: 0 0 var(--espacio-cuatro); }
    .resp-resena__cita { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); }
    .resp-resena__cita-autor { font-weight: var(--peso-negrita); color: var(--color-dos); margin-bottom: var(--espacio-uno); }
    .resp-resena__cita-texto { color: var(--color-seis); line-height: 1.6; margin: var(--espacio-dos) 0 0; white-space: pre-line; }
    .resp-resena__tuya { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); border-left: 3px solid var(--color-uno); background: var(--color-cinco); border-radius: var(--radio-uno); white-space: pre-line; color: var(--color-seis); line-height: 1.6; }
    .resp-resena__contador { font-size: var(--fs-dos); color: var(--color-seis-claro); text-align: right; margin-top: var(--espacio-uno); }
    .resp-resena__error { color: #b42318; margin: 0 0 var(--espacio-tres); }
    .resp-resena__acciones { display: flex; gap: var(--espacio-tres); flex-wrap: wrap; margin-top: var(--espacio-cuatro); }
    @media (max-width: 600px) { .resp-resena__acciones .boton { width: 100%; } }
</style>

<main class="resp-resena">
    <div class="resp-resena__inner">
    <?php if (!$resena): ?>
        <h1 class="resp-resena__titulo">Enlace no válido</h1>
        <p class="resp-resena__texto">Este enlace no es válido, está incompleto o la reseña ya no está publicada. Si quieres responder a una reseña, escríbenos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a> y lo gestionamos.</p>

    <?php else: ?>
        <h1 class="resp-resena__titulo">Responder a la reseña de <?= $e($resena['nombre']) ?></h1>

        <blockquote class="resp-resena__cita">
            <div class="resp-resena__cita-autor"><?= $e($resena['nombre']) ?> · <?= $e($resena['negocio_nombre']) ?></div>
            <div class="tarjeta__valoracion"><?= generarEstrellas($resena['calificacion']) ?></div>
            <p class="resp-resena__cita-texto"><?= $e($resena['comentario']) ?></p>
        </blockquote>

        <?php if ($enviada): ?>
            <h2 class="resp-resena__titulo">Respuesta recibida</h2>
            <p class="resp-resena__texto">Gracias. Revisaremos tu respuesta y, si todo está en orden, la publicaremos bajo la reseña. Te avisaremos por email en cuanto esté pública.</p>

        <?php elseif ($ultima && $ultima['estado'] === 'pendiente'): ?>
            <h2 class="resp-resena__titulo">Tu respuesta está en revisión</h2>
            <p class="resp-resena__texto">Ya hemos recibido tu respuesta y la estamos revisando. Te avisaremos por email cuando se publique.</p>
            <p class="resp-resena__tuya"><?= $e($ultima['texto']) ?></p>

        <?php elseif ($ultima && $ultima['estado'] === 'aprobada'): ?>
            <h2 class="resp-resena__titulo">Tu respuesta ya es pública</h2>
            <p class="resp-resena__texto">Tu respuesta está publicada bajo la reseña, en la ficha de <?= $e($resena['negocio_nombre']) ?>.</p>
            <p class="resp-resena__tuya"><?= $e($ultima['texto']) ?></p>
            <div class="resp-resena__acciones">
                <a class="boton uno" href="<?= $e(notifUrlPublica($resena['negocio_slug'])) ?>#tras-galeria">Ver mi ficha</a>
            </div>

        <?php elseif ($ultima && !$puedeResponder): ?>
            <h2 class="resp-resena__titulo">Tu última respuesta no se pudo publicar</h2>
            <p class="resp-resena__texto">No hemos podido publicar tu respuesta anterior. Podrás enviar otra a partir del <?= $e(date('d/m/Y H:i', strtotime((string)$ultima['reintento_desde']))) ?>.</p>

        <?php else: ?>
            <?php if ($ultima && $ultima['estado'] === 'rechazada'): ?>
            <p class="resp-resena__texto">Tu respuesta anterior no se pudo publicar. Puedes escribir una nueva.</p>
            <?php else: ?>
            <p class="resp-resena__texto">Escribe el mensaje que te gustaría mostrar bajo la reseña. Lo revisaremos antes de publicarlo y te avisaremos por email. Solo se admite una respuesta por reseña.</p>
            <?php endif; ?>

            <?php if ($error !== ''): ?><p class="resp-resena__error" role="alert"><?= $e($error) ?></p><?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="r" value="<?= (int)$resenaId ?>">
                <input type="hidden" name="t" value="<?= $e($token) ?>">
                <input type="hidden" name="form_render_ts" value="<?= time() ?>">
                <input type="text" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true"
                       style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;">

                <div class="field">
                    <label class="field__label" for="respuesta">Tu respuesta *</label>
                    <textarea id="respuesta" name="respuesta" class="field__textarea" rows="7" required
                              minlength="<?= RESPUESTA_MIN_CARACTERES ?>" maxlength="<?= RESPUESTA_MAX_CARACTERES ?>"
                              placeholder="Escribe aquí tu respuesta"><?= $e($_POST['respuesta'] ?? '') ?></textarea>
                    <div class="resp-resena__contador"><span id="respuesta-contador">0</span> / <?= RESPUESTA_MAX_CARACTERES ?></div>
                </div>

                <div class="resp-resena__acciones">
                    <button type="submit" class="boton uno">Enviar para revisión</button>
                    <a href="<?= $e(notifUrlPublica($resena['negocio_slug'])) ?>#tras-galeria" class="boton dos">Cancelar</a>
                </div>
            </form>
            <script>
            (function () {
                var t = document.getElementById('respuesta'), c = document.getElementById('respuesta-contador');
                function act() { c.textContent = t.value.length; }
                t.addEventListener('input', act); act();
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</main>

<?php
include 'includes/footer.php';

// Aviso interno al equipo (tras renderizar la página, para no hacer esperar al negocio)
if ($notificarRespuestaId > 0 && $pdo) {
    try {
        notifCerrarRespuesta();
        notificarEvento($pdo, 'respuesta.recibida', ['respuesta_id' => $notificarRespuestaId]);
    } catch (\Throwable $ex) {
        error_log('[responder-resena] notificación: ' . $ex->getMessage());
    }
}
