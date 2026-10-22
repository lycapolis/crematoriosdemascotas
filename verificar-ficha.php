<?php
/**
 * ═══════════════════════════════════════════════════════════
 * VERIFICAR LA FICHA — página pública del negocio (campaña de prospección)
 * ═══════════════════════════════════════════════════════════
 *
 * Enlace de los emails de la campaña:
 *   /verificar-ficha.php?f=<id de la ficha>&t=<firma HMAC>
 *
 * El negocio no tiene cuenta: el enlace firmado es su credencial (patrón de recibir-datos.php).
 * - Firma mala o ficha inexistente / no publicada → «Enlace no válido», sin ningún dato.
 * - «Quiero que me llamen»: confirma (o cambia) el teléfono de atención al público y deja una
 *   solicitud en solicitudes_llamada; avisa al equipo (evento llamada.solicitada).
 *   Si cambia el número, pasa a ser el teléfono público de la ficha y el anterior se conserva como historial.
 * - «No me interesa»: marca la baja de la campaña y suprime los emails de la ficha (notificaciones_supresiones).
 *
 * Tramo 2 (pendiente): el formulario completo de actualización de datos irá en la MISMA página,
 * en el bloque `.verificar__formulario` (ver comentario más abajo).
 *
 * Antispam: honeypot + time-trap + máximo de solicitudes por IP y hora. Fechas comparadas en SQL (NOW()).
 * Excepción documentada a «nombre + email + teléfono obligatorios»: aquí solo se confirma el teléfono;
 * el nombre del negocio y su email ya los tenemos en la ficha (el enlace llegó a ese email).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/notificaciones/despachador.php';

const VERIFICAR_MAX_POR_IP_HORA = 5;

$fichaId = (int)($_GET['f'] ?? $_POST['f'] ?? 0);
$token   = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$firmaOk = notifTokenVerificacionValido($fichaId, $token);

$pdo   = null;
$ficha = null;
if ($firmaOk) {
    try {
        $pdo = obtenerConexion();
        $st = $pdo->prepare("SELECT id, nombre, estado, telefono, telefono_clientes, telefonos_json, email, email_clientes, emails_json
                             FROM crematorios WHERE id = ? AND estado IN ('activa','demo') LIMIT 1");
        $st->execute([$fichaId]);
        $ficha = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $ex) {
        error_log('[verificar-ficha] ' . $ex->getMessage());
        $ficha = null;
    }
}

$error     = '';
$resultado = '';          // '' | 'llamada' | 'baja'
$telNuevoPublicado = false;
$telConfirmado = '';
$valores = ['telefono_opcion' => 'correcto', 'telefono_nuevo' => ''];
$llamadaPendiente = null;

/** Todos los emails conocidos de la ficha (para la baja de la campaña). */
function verificarEmailsDeFicha(array $f): array
{
    $out = [$f['email'] ?? '', $f['email_clientes'] ?? ''];
    foreach (json_decode((string)($f['emails_json'] ?? ''), true) ?: [] as $m) $out[] = $m['email'] ?? '';
    $out = array_filter(array_map(fn($x) => mb_strtolower(trim((string)$x)), $out), fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL));
    return array_values(array_unique($out));
}

/** Crea la fila de seguimiento si no existe y aplica el estado indicado. */
function verificarMarcarCampana(PDO $pdo, int $fichaId, string $estado): void
{
    $extra = $estado === 'baja' ? ', baja_en = NOW()' : '';
    $pdo->prepare("INSERT INTO campana_fichas (crematorio_id, estado, respondio_en) VALUES (?, ?, NOW())
                   ON DUPLICATE KEY UPDATE estado = VALUES(estado), respondio_en = COALESCE(respondio_en, NOW())$extra")
        ->execute([$fichaId, $estado]);
}

if ($ficha) {
    $telMostrado = fichaTelefonoMostrado($ficha);

    $q = $pdo->prepare("SELECT id, telefono_confirmado FROM solicitudes_llamada
                        WHERE crematorio_id = ? AND estado IN ('pendiente','reagendar') ORDER BY id DESC LIMIT 1");
    $q->execute([$fichaId]);
    $llamadaPendiente = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = (string)($_POST['accion'] ?? '');
        $esBot  = trim((string)($_POST['website_url'] ?? '')) !== ''
               || (time() - (int)($_POST['form_render_ts'] ?? 0)) < 3;

        if ($esBot) {
            error_log('anti-spam bloqueado [verificar-ficha] ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            $resultado = $accion === 'baja' ? 'baja' : 'llamada';
            $telConfirmado = (string)$telMostrado;
        } elseif ($accion === 'baja') {
            try {
                foreach (verificarEmailsDeFicha($ficha) as $mail) notifSuprimir($pdo, $mail);
                verificarMarcarCampana($pdo, $fichaId, 'baja');
                $resultado = 'baja';
            } catch (\Throwable $ex) {
                error_log('[verificar-ficha baja] ' . $ex->getMessage());
                $error = 'No hemos podido registrar tu respuesta. Inténtalo de nuevo en unos minutos.';
            }
        } elseif ($accion === 'llamada') {
            $opcion = (($_POST['telefono_opcion'] ?? '') === 'otro' || !$telMostrado) ? 'otro' : 'correcto';
            $valores = ['telefono_opcion' => $opcion, 'telefono_nuevo' => trim((string)($_POST['telefono_nuevo'] ?? ''))];

            $telFinal = $telMostrado;
            if ($opcion === 'otro') {
                $telFinal = telefonoEspanaNormalizar($valores['telefono_nuevo']);
                if ($telFinal === null) {
                    $error = $valores['telefono_nuevo'] === ''
                        ? 'Escribe el teléfono al que podemos llamarte (un número de España).'
                        : 'Este número no parece un teléfono de España. Revísalo e inténtalo de nuevo (9 dígitos, por ejemplo 612 345 678).';
                }
            }

            if ($error === '') {
                try {
                    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
                    $q = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_llamada WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                    $q->execute([$ipHash]);
                    if ((int)$q->fetchColumn() >= VERIFICAR_MAX_POR_IP_HORA) {
                        $error = 'Se han enviado varias solicitudes desde tu conexión. Vuelve a intentarlo dentro de un rato.';
                    } elseif ($llamadaPendiente) {
                        // Ya hay una solicitud viva: no se duplica ni se vuelve a cambiar el teléfono
                        $resultado = 'llamada';
                        $telConfirmado = (string)$llamadaPendiente['telefono_confirmado'];
                    } else {
                        $cambiado = false;
                        if ($opcion === 'otro') {
                            $r = telefonoReemplazarPorNegocio($pdo, $fichaId, $telFinal);
                            $cambiado = $r['cambiado'];
                        }
                        $pdo->prepare("INSERT INTO solicitudes_llamada
                                       (crematorio_id, telefono_original, telefono_confirmado, telefono_cambiado, ip_hash)
                                       VALUES (?, ?, ?, ?, ?)")
                            ->execute([$fichaId, $telMostrado, $telFinal, $cambiado ? 1 : 0, $ipHash]);
                        $llamadaId = (int)$pdo->lastInsertId();
                        verificarMarcarCampana($pdo, $fichaId, 'llamada');
                        notificarEvento($pdo, 'llamada.solicitada', ['llamada_id' => $llamadaId]);
                        $resultado = 'llamada';
                        $telConfirmado = $telFinal;
                        $telNuevoPublicado = $cambiado;
                    }
                } catch (\Throwable $ex) {
                    error_log('[verificar-ficha llamada] ' . $ex->getMessage());
                    $error = 'No hemos podido guardar tu solicitud. Inténtalo de nuevo en unos minutos.';
                }
            }
        }
    }
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$titulo_pagina = 'Verifica tu ficha';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<style>
    .verificar { min-height: 60vh; display: flex; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .verificar__inner { width: 100%; max-width: 640px; }
    .verificar__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .verificar__texto { color: var(--color-seis); line-height: 1.65; margin: 0 0 var(--espacio-cuatro); }
    .verificar__tarjeta { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); }
    .verificar__tarjeta-titulo { margin: 0 0 var(--espacio-tres); font-size: var(--fs-tres); color: var(--color-dos); }
    .verificar__telefono { font-size: var(--fs-cuatro); font-weight: var(--peso-negrita); color: var(--color-dos); white-space: nowrap; }
    .verificar__pregunta { margin: 0 0 var(--espacio-tres); color: var(--color-seis); line-height: 1.6; }
    .verificar__opciones { display: grid; gap: var(--espacio-dos); margin: 0 0 var(--espacio-tres); }
    .verificar__otro { margin: 0 0 var(--espacio-tres); }
    .verificar__ayuda { margin: var(--espacio-uno) 0 0; font-size: var(--fs-dos); color: var(--color-seis-claro); }
    .verificar__error { margin: 0 0 var(--espacio-tres); padding: var(--espacio-tres); border-radius: var(--radio-uno); background: #FDECEA; color: #8A1C13; line-height: 1.5; }
    .verificar__ok { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); border-left: 3px solid var(--color-uno); background: var(--color-cinco); border-radius: var(--radio-uno); line-height: 1.6; color: var(--color-dos); }
    .verificar__acciones { display: flex; gap: var(--espacio-tres); flex-wrap: wrap; }
    .verificar__baja { margin-top: var(--espacio-cuatro); padding-top: var(--espacio-cuatro); border-top: 1px solid var(--color-cinco); }
    .verificar [hidden] { display: none !important; }
    .verificar__trampa { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    @media (max-width: 600px) { .verificar__acciones .boton { width: 100%; } }
</style>

<main class="verificar">
    <div class="verificar__inner">
    <?php if (!$ficha): ?>
        <h1 class="verificar__titulo">Enlace no válido</h1>
        <p class="verificar__texto">Este enlace no es válido o está incompleto. Si quieres verificar la ficha de tu negocio, escríbenos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a> y lo gestionamos contigo.</p>

    <?php elseif ($resultado === 'baja'): ?>
        <h1 class="verificar__titulo">De acuerdo, no volveremos a escribirte</h1>
        <p class="verificar__texto">Hemos registrado que no te interesa y no recibirás más mensajes de esta campaña. Gracias por tu tiempo.</p>
        <p class="verificar__texto">Si más adelante cambias de idea, la verificación seguirá siendo gratuita: solo tienes que escribirnos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a>.</p>

    <?php elseif ($resultado === 'llamada'): ?>
        <h1 class="verificar__titulo">¡Listo! Te llamaremos pronto</h1>
        <div class="verificar__ok">
            Te llamaremos al <strong class="verificar__telefono"><?= $e($telConfirmado) ?></strong> lo antes posible, de lunes a viernes en horario comercial. No tienes que hacer nada más.
            <?php if ($telNuevoPublicado): ?>
            <br><br>Además, hemos guardado este número como el teléfono de atención al público de la ficha de <?= $e($ficha['nombre']) ?>, para que las familias te localicen rápido.
            <?php endif; ?>
        </div>
        <p class="verificar__texto">La llamada es gratuita y solo sirve para comprobar que los datos de tu ficha son correctos.</p>

    <?php else:
        $telMostrado = fichaTelefonoMostrado($ficha);
        $abrirOtro   = $valores['telefono_opcion'] === 'otro' || !$telMostrado;
    ?>
        <h1 class="verificar__titulo">Verifica tu ficha de <?= $e($ficha['nombre']) ?></h1>
        <p class="verificar__texto">Tu negocio ya aparece en nuestro directorio. Verificar la ficha es <strong>gratis</strong> y sin ningún compromiso: así las familias saben que tus datos son correctos y pueden contactar contigo rápido.</p>

        <?php if ($error !== ''): ?>
        <div class="verificar__error" role="alert"><?= $e($error) ?></div>
        <?php endif; ?>

        <?php if ($llamadaPendiente): ?>
        <div class="verificar__ok">Ya tenemos tu solicitud. Te llamaremos al <strong class="verificar__telefono"><?= $e($llamadaPendiente['telefono_confirmado']) ?></strong> lo antes posible, de lunes a viernes en horario comercial.</div>
        <?php else: ?>
        <section class="verificar__tarjeta" id="verificar-llamada">
            <h2 class="verificar__tarjeta-titulo">Quiero que me llamen</h2>
            <p class="verificar__texto" style="margin-bottom:var(--espacio-tres);">Te llamaremos lo antes posible, en horario comercial, de lunes a viernes.</p>

            <form method="post" action="verificar-ficha.php" class="verificar__form" novalidate>
                <input type="hidden" name="f" value="<?= (int)$fichaId ?>">
                <input type="hidden" name="t" value="<?= $e($token) ?>">
                <input type="hidden" name="accion" value="llamada">
                <input type="hidden" name="form_render_ts" value="<?= time() ?>">
                <div class="verificar__trampa" aria-hidden="true"><label>No rellenar <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

                <?php if ($telMostrado): ?>
                <p class="verificar__pregunta">Te llamaremos al <strong class="verificar__telefono"><?= $e($telMostrado) ?></strong>. ¿Es correcto?</p>
                <div class="verificar__opciones">
                    <label class="field__opcion"><input type="radio" class="field__radio" name="telefono_opcion" value="correcto" <?= !$abrirOtro ? 'checked' : '' ?>><span>Sí, es correcto</span></label>
                    <label class="field__opcion"><input type="radio" class="field__radio" name="telefono_opcion" value="otro" <?= $abrirOtro ? 'checked' : '' ?>><span>No, usar otro número</span></label>
                </div>
                <?php else: ?>
                <p class="verificar__pregunta">Todavía no tenemos ningún teléfono registrado para tu negocio. Escribe aquí el número al que podemos llamarte (debe ser un teléfono de España). Será también el que se muestre a las familias en tu ficha.</p>
                <input type="hidden" name="telefono_opcion" value="otro">
                <?php endif; ?>

                <div class="field verificar__otro" id="verificar-otro">
                    <label class="field__label" for="verificar-tel-nuevo"><?= $telMostrado ? 'Tu otro número de teléfono' : 'Teléfono de tu negocio' ?></label>
                    <input class="field__input" type="tel" id="verificar-tel-nuevo" name="telefono_nuevo" inputmode="tel" autocomplete="tel" maxlength="20" value="<?= $e($valores['telefono_nuevo']) ?>" placeholder="612 345 678">
                    <p class="verificar__ayuda">Escribe un teléfono de España (9 dígitos, por ejemplo 612 345 678).<?= $telMostrado ? ' Pasará a ser el teléfono de atención al público de tu ficha.' : '' ?></p>
                </div>

                <div class="verificar__acciones">
                    <button type="submit" class="boton uno">Pedir la llamada</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- TRAMO 2: aquí irá el formulario completo para actualizar los datos de la ficha (bloque .verificar__formulario),
             en esta misma página. Quien pide la llamada podrá adelantar datos; quien no, completará el formulario. -->
        <div class="verificar__formulario" hidden></div>

        <form method="post" action="verificar-ficha.php" class="verificar__baja">
            <input type="hidden" name="f" value="<?= (int)$fichaId ?>">
            <input type="hidden" name="t" value="<?= $e($token) ?>">
            <input type="hidden" name="accion" value="baja">
            <input type="hidden" name="form_render_ts" value="<?= time() ?>">
            <p class="verificar__texto" style="margin-bottom:var(--espacio-tres);">¿Prefieres no hacer nada? No pasa nada: no te volveremos a escribir sobre esto.</p>
            <button type="submit" class="boton dos">No me interesa</button>
        </form>

        <script>
        (function () {
            var radios = document.querySelectorAll('input[name="telefono_opcion"][type="radio"]');
            var otro = document.getElementById('verificar-otro');
            if (!radios.length || !otro) return;
            function actualizar() {
                var sel = document.querySelector('input[name="telefono_opcion"]:checked');
                otro.hidden = !(sel && sel.value === 'otro');
            }
            radios.forEach(function (r) { r.addEventListener('change', actualizar); });
            actualizar();
        })();
        </script>
    <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php';
