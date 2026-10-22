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
 * - «Prefiero completarlo yo» (tramo 2): formulario completo de actualización de datos en la MISMA página
 *   (bloque `.verificar__formulario`, campos compartidos con registrar-negocio.php). Precargado con los datos
 *   actuales; el envío queda como «pendiente de revisión» (actualizaciones_ficha) y NO cambia la ficha pública.
 *   El equipo lo revisa campo a campo en admin/actualizaciones-pendientes.php. Quien pidió la llamada puede
 *   adelantar datos aquí; actualiza campana_fichas.estado (llamada / formulario). Aviso al equipo + email al negocio.
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
require_once __DIR__ . '/includes/actualizacion-ficha.php';
require_once __DIR__ . '/includes/ImagenHelper.php';

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
$resultado = '';          // '' | 'llamada' | 'baja' | 'actualizacion'
$erroresForm = [];        // errores del formulario de actualización (se muestran dentro de su bloque)
$actPendiente = null;     // actualización ya enviada y aún sin revisar
$ffDatos = [];            // datos que pinta el formulario (actuales, o lo enviado si hubo errores)
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

    // Datos actuales de la ficha (para precargar el formulario y detectar qué cambia)
    $valoresActuales = actFichaValoresActuales($pdo, $fichaId)['valores'] ?? [];
    $ffDatos = $valoresActuales + ['pais' => 'España'];
    $ffDatos['nombre_negocio'] = (string)$ficha['nombre'];

    $q = $pdo->prepare("SELECT id FROM actualizaciones_ficha WHERE crematorio_id = ? AND estado = 'pendiente' ORDER BY id DESC LIMIT 1");
    $q->execute([$fichaId]);
    $actPendiente = $q->fetch(PDO::FETCH_ASSOC) ?: null;

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
            $resultado = $accion === 'baja' ? 'baja' : ($accion === 'actualizar' ? 'actualizacion' : 'llamada');
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
        } elseif ($accion === 'actualizar') {
            if ($actPendiente) {
                $resultado = 'actualizacion';
            } else {
                $posted = [];
                foreach (array_merge(array_keys(actFichaCatalogo()), ['contacto_nombre', 'contacto_email', 'contacto_telefono', 'comunidad', 'pais', 'comentarios_admin']) as $k) {
                    $posted[$k] = trim(mb_scrub((string)($_POST[$k] ?? '')));   // mb_scrub: descarta bytes que no sean UTF-8 válido
                }
                $posted['nombre_negocio'] = (string)$ficha['nombre'];
                $ffDatos = $posted;   // si hay errores, el formulario conserva lo escrito

                $cNombre = mb_substr($posted['contacto_nombre'], 0, 150);
                if ($cNombre === '') $erroresForm[] = 'Escribe tu nombre.';
                if (!filter_var($posted['contacto_email'], FILTER_VALIDATE_EMAIL)) $erroresForm[] = 'Escribe un email de contacto válido.';
                if (strlen(preg_replace('/\D/', '', $posted['contacto_telefono'])) < 9) $erroresForm[] = 'Escribe un teléfono de contacto válido.';
                if (empty($_POST['consentimiento'])) $erroresForm[] = 'Para enviar tus datos tienes que aceptar que revisemos la información.';

                $cambios = actFichaDiff($valoresActuales, $posted);
                $erroresForm = array_merge($erroresForm, actFichaValidarCambios($cambios));

                $hayLogo    = !empty($_FILES['logo']['tmp_name']) && ($_FILES['logo']['error'] ?? 4) === UPLOAD_ERR_OK;
                $hayGaleria = !empty($_FILES['galeria']['tmp_name'][0]);
                if (!$erroresForm && !$cambios && !$hayLogo && !$hayGaleria) {
                    $erroresForm[] = 'No has cambiado ningún dato. Modifica lo que quieras actualizar o sube alguna imagen y vuelve a enviarlo.';
                }

                if (!$erroresForm) {
                    try {
                        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
                        $q = $pdo->prepare("SELECT COUNT(*) FROM actualizaciones_ficha WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                        $q->execute([$ipHash]);
                        if ((int)$q->fetchColumn() >= VERIFICAR_MAX_POR_IP_HORA) {
                            $erroresForm[] = 'Se han enviado varias solicitudes desde tu conexión. Vuelve a intentarlo dentro de un rato.';
                        } else {
                            $pdo->beginTransaction();
                            $pdo->prepare("INSERT INTO actualizaciones_ficha
                                (crematorio_id, contacto_nombre, contacto_email, contacto_telefono, comentarios, acepta_comunicaciones, datos_enviados_json, desde_llamada, ip_hash)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$fichaId, $cNombre, mb_strtolower($posted['contacto_email']), mb_substr($posted['contacto_telefono'], 0, 50),
                                           $posted['comentarios_admin'] !== '' ? $posted['comentarios_admin'] : null,
                                           !empty($_POST['consentimiento_comunicaciones']) ? 1 : 0,
                                           json_encode($posted, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), $llamadaPendiente ? 1 : 0, $ipHash]);
                            $actId = (int)$pdo->lastInsertId();

                            $ic = $pdo->prepare("INSERT INTO actualizacion_ficha_campos (actualizacion_id, campo, valor_actual, valor_propuesto) VALUES (?, ?, ?, ?)");
                            foreach ($cambios as $campo => $c) $ic->execute([$actId, $campo, $c['actual'] !== '' ? $c['actual'] : null, $c['propuesto']]);

                            // Imágenes propuestas (quedan aparte hasta que el admin las apruebe)
                            $ii = $pdo->prepare("INSERT INTO actualizacion_ficha_imagenes (actualizacion_id, tipo, nombre_original, nombre_archivo, ruta, tamano, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $gd = extension_loaded('gd') && function_exists('imagecreatefromjpeg');
                            if ($gd && $hayLogo) {
                                $r = ImagenHelper::procesar($_FILES['logo'], 'logo', 'act' . $actId, 'solicitudes');
                                if (!empty($r['ok'])) $ii->execute([$actId, 'logo', $r['nombre_original'], $r['nombre'], $r['ruta'], $r['tamano'], 0]);
                                else error_log('[verificar-ficha] logo no procesado: ' . ($r['error'] ?? ''));
                            }
                            if ($gd && $hayGaleria) {
                                $r = ImagenHelper::procesarGaleria($_FILES['galeria'], 'act' . $actId, 'solicitudes', 10);
                                foreach ($r['imagenes'] ?? [] as $n => $img) $ii->execute([$actId, 'galeria', $img['nombre_original'], $img['nombre'], $img['ruta'], $img['tamano'], $n + 1]);
                            }
                            $pdo->commit();

                            verificarMarcarCampana($pdo, $fichaId, $llamadaPendiente ? 'llamada' : 'formulario');
                            notificarEvento($pdo, 'ficha.actualizacion_recibida', ['actualizacion_id' => $actId]);
                            $resultado = 'actualizacion';
                        }
                    } catch (\Throwable $ex) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('[verificar-ficha actualizar] ' . $ex->getMessage());
                        $erroresForm[] = 'No hemos podido guardar tus datos. Inténtalo de nuevo en unos minutos.';
                    }
                }
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

    <?php elseif ($resultado === 'actualizacion'): ?>
        <h1 class="verificar__titulo">¡Gracias! Hemos recibido tus datos</h1>
        <div class="verificar__ok">
            Hemos recibido la actualización de la ficha de <strong><?= $e($ficha['nombre']) ?></strong>. Nuestro equipo la revisará y, hasta entonces, tu ficha sigue mostrando los datos de siempre: <strong>no se publica nada sin revisar</strong>.
        </div>
        <p class="verificar__texto">Te escribiremos por email en cuanto esté revisada. No tienes que hacer nada más.</p>

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

        <!-- Formulario completo para actualizar los datos de la ficha. Quien pide la llamada puede adelantar datos aquí;
             quien no, simplemente lo completa. Nada se publica sin revisión humana. -->
        <section class="verificar__formulario" id="verificar-formulario">
            <h2 class="verificar__tarjeta-titulo">Prefiero completarlo yo</h2>
            <?php if ($actPendiente): ?>
            <div class="verificar__ok">Ya tenemos tu actualización y la estamos revisando. Te escribiremos por email en cuanto esté lista. Si quieres añadir algo más, respóndenos a ese email.</div>
            <?php else: ?>
            <p class="verificar__texto">Revisa los datos de tu ficha, corrige o completa lo que quieras y envíalos. Nuestro equipo los revisará antes de publicarlos: <strong>tu ficha no cambia hasta entonces</strong>.</p>

            <?php if ($erroresForm): ?>
            <div class="verificar__error" role="alert">
                <strong>Revisa lo siguiente:</strong>
                <ul style="margin:var(--espacio-uno) 0 0; padding-left:1.2rem;">
                    <?php foreach ($erroresForm as $msg): ?><li><?= $e($msg) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" action="verificar-ficha.php#verificar-formulario" enctype="multipart/form-data" novalidate class="verificar__form-ficha" style="display:flex; flex-direction:column; gap:var(--espacio-cuatro);">
                <input type="hidden" name="f" value="<?= (int)$fichaId ?>">
                <input type="hidden" name="t" value="<?= $e($token) ?>">
                <input type="hidden" name="accion" value="actualizar">
                <?php
                $ffModo   = 'actualizacion';
                $GEO_ES   = include __DIR__ . '/includes/geo_es.php';
                include __DIR__ . '/includes/form-ficha-campos.php';
                ?>

                <div style="display:flex; flex-direction:column; gap:var(--espacio-dos);">
                    <label class="field__opcion" style="align-items:flex-start; gap:.6rem;">
                        <input type="checkbox" class="field__check" id="consentimiento" name="consentimiento" value="1">
                        <span>Acepto que revisen la información que envío para actualizar mi ficha y que me escriban sobre esta revisión. *</span>
                    </label>
                    <label class="field__opcion" style="align-items:flex-start; gap:.6rem;">
                        <input type="checkbox" class="field__check" id="consentimiento_comunicaciones" name="consentimiento_comunicaciones" value="1">
                        <span>Quiero recibir también ofertas comerciales y novedades de servicios para mi negocio (opcional).</span>
                    </label>
                    <p class="verificar__ayuda">Usamos estos datos para revisar y actualizar tu ficha. Más información en nuestra <a href="privacidad.php" target="_blank" rel="noopener">política de privacidad</a>.</p>
                </div>

                <div class="verificar__acciones">
                    <button type="submit" class="boton uno">Enviar los datos para revisión</button>
                </div>
            </form>
            <?php endif; ?>
        </section>

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
