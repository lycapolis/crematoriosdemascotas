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
require_once __DIR__ . '/includes/insignias.php';
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
        $st = $pdo->prepare("SELECT id, nombre, slug, estado, telefono, telefono_clientes, telefonos_json, email, email_clientes, emails_json
                             FROM crematorios WHERE id = ? AND estado IN ('activa','demo') LIMIT 1");
        $st->execute([$fichaId]);
        $ficha = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $ex) {
        error_log('[verificar-ficha] ' . $ex->getMessage());
        $ficha = null;
    }
}

// Vista según el botón del email: 'llamada' (por defecto) o 'formulario'. Se conserva en los POST con un campo oculto.
$vista = (string)($_GET['a'] ?? $_POST['a'] ?? '') === 'formulario' ? 'formulario' : 'llamada';
$urlVista = fn(string $v) => 'verificar-ficha.php?f=' . $fichaId . '&t=' . rawurlencode($token) . '&a=' . $v;

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

// Insignias que se muestran como incentivo (datos reales del catálogo, con respaldo por si falta la tabla)
$insignias = [
    'verificado_humano'  => ['nombre' => 'Verificado por humano', 'icono' => 'badge-check', 'color' => '#16A34A',
                             'tooltip' => 'Una persona de nuestro equipo habló por teléfono con este negocio y comprobó que sus datos son correctos.'],
    'datos_actualizados' => ['nombre' => 'Datos actualizados por el negocio', 'icono' => 'file-check', 'color' => '#0EA5E9',
                             'tooltip' => 'El propio negocio ha revisado y confirmado los datos de esta ficha.'],
];
if ($ficha) {
    try {
        $qi = $pdo->query("SELECT slug, nombre, tooltip, icono, color FROM insignias WHERE slug IN ('verificado_humano','datos_actualizados')");
        foreach ($qi->fetchAll(PDO::FETCH_ASSOC) as $r) $insignias[$r['slug']] = array_filter($r, fn($v) => $v !== null && $v !== '') + $insignias[$r['slug']];
    } catch (\Throwable $ex) { /* se usan los textos por defecto */ }
}

if ($ficha) {
    $telMostrado = fichaTelefonoMostrado($ficha);

    // Datos actuales de la ficha (para precargar el formulario y detectar qué cambia)
    $valoresActuales = actFichaValoresActuales($pdo, $fichaId)['valores'] ?? [];
    $ffDatos = $valoresActuales + ['pais' => 'España'];
    $ffDatos['google_maps_url'] = '';   // llega vacío: el negocio pone el suyo y en la revisión se compara con el del scraping

    // Ciudades que ya existen en las fichas activas, para el selector de cobertura (una sola grafía por ciudad:
    // la más frecuente). Mientras no exista el gestor de ciudades (ficha 12) este es el listado.
    $ffCiudadesListado = [];
    try {
        $porSlug = [];
        foreach ($pdo->query("SELECT ciudad, COUNT(*) AS n FROM crematorios
                              WHERE estado = 'activa' AND ciudad IS NOT NULL AND ciudad != ''
                              GROUP BY ciudad ORDER BY n DESC, ciudad ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = slugificar((string)$r['ciudad']);
            if ($k !== '' && !isset($porSlug[$k])) $porSlug[$k] = trim((string)$r['ciudad']);
        }
        $ffCiudadesListado = array_values($porSlug);
        sort($ffCiudadesListado, SORT_LOCALE_STRING);
    } catch (\Throwable $ex) { /* sin listado: el selector admite escribir ciudades */ }

    $q = $pdo->prepare("SELECT id FROM actualizaciones_ficha WHERE crematorio_id = ? AND estado = 'pendiente' ORDER BY id DESC LIMIT 1");
    $q->execute([$fichaId]);
    $actPendiente = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    $q = $pdo->prepare("SELECT id, telefono_confirmado FROM solicitudes_llamada
                        WHERE crematorio_id = ? AND estado IN ('pendiente','reagendar') ORDER BY id DESC LIMIT 1");
    $q->execute([$fichaId]);
    $llamadaPendiente = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    // Clic en un botón del email de campaña (?c=<paso 1-3>&a=llamada|formulario): solo se cuenta
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array((string)($_GET['c'] ?? ''), ['1', '2', '3'], true)
        && in_array((string)($_GET['a'] ?? ''), ['llamada', 'formulario'], true)) {
        try {
            $pdo->prepare("INSERT INTO campana_clics (crematorio_id, paso, boton) VALUES (?, ?, ?)")
                ->execute([$fichaId, (int)$_GET['c'], (string)$_GET['a']]);
        } catch (\Throwable $ex) { /* migración sin correr: no se cuenta, la página funciona igual */ }
    }

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

                // Cobertura: los selectores múltiples llegan como listas; se guardan como «a, b, c» en los campos de siempre
                $lista = fn(string $k) => implode(', ', array_filter(array_map(
                    fn($x) => trim(mb_scrub((string)$x)), is_array($_POST[$k] ?? null) ? $_POST[$k] : [])));
                $posted['zona_cobertura']     = $lista('cobertura_zonas');
                $posted['ciudades_cobertura'] = $lista('cobertura_ciudades');
                // Servicios y horarios: lo marcado en las etiquetas + lo que el negocio escribe de más.
                // Si el conjunto coincide con el actual (aunque cambie el orden), no cuenta como cambio.
                $lim = fn($k) => array_values(array_filter(array_map(fn($x) => trim(mb_scrub((string)$x)), is_array($_POST[$k] ?? null) ? $_POST[$k] : [])));
                $mismoConjunto = function (array $a, array $b): bool {
                    $a = array_map('mb_strtolower', $a); $b = array_map('mb_strtolower', $b); sort($a); sort($b); return $a === $b;
                };
                if (isset($_POST['servicios_sel'])) {
                    $conocidos = [];
                    foreach (actFichaServiciosCatalogo() as $its) foreach ($its as $it) $conocidos[mb_strtolower($it)] = true;
                    $actualesServ = array_values(array_filter(array_map('trim', explode(',', (string)($valoresActuales['servicios'] ?? '')))));
                    foreach ($actualesServ as $it) $conocidos[mb_strtolower($it)] = true;
                    $habituales = []; $propios = [];
                    foreach (array_merge($lim('servicios_sel'), $lim('servicios_propios')) as $it) {
                        if (isset($conocidos[mb_strtolower($it)])) $habituales[mb_strtolower($it)] = $it; else $propios[mb_strtolower($it)] = $it;
                    }
                    $posted['servicios']         = $mismoConjunto(array_values($habituales), $actualesServ) ? (string)($valoresActuales['servicios'] ?? '') : implode(', ', $habituales);
                    $posted['servicios_propios'] = implode(', ', $propios);
                }
                if (isset($_POST['horarios_sel'])) {
                    $horActual  = array_values(array_filter(array_map('trim', preg_split('/\n+/', (string)($valoresActuales['horarios'] ?? '')) ?: [])));
                    // Los horarios actuales que no son una etiqueta se conservan (la caja de abajo solo añade notas nuevas)
                    $horEtiquetas = array_map('mb_strtolower', actFichaHorariosSugeridos());
                    $horConservados = array_values(array_filter($horActual, fn($l) => !in_array(mb_strtolower($l), $horEtiquetas, true)));
                    $horNuevo   = array_values(array_unique(array_merge($lim('horarios_sel'), $horConservados, $lim('horarios_extra'))));
                    $ffExtraPost = $lim('horarios_extra');
                    $posted['horarios'] = $mismoConjunto($horNuevo, $horActual) ? (string)($valoresActuales['horarios'] ?? '') : implode("\n", $horNuevo);
                }
                // Otra red: nombre + enlace → «nombre | enlace» (así lo guarda el catálogo)
                $posted['red_otra_nombre'] = trim(mb_scrub((string)($_POST['red_otra_nombre'] ?? '')));
                $posted['red_otra_url']    = trim(mb_scrub((string)($_POST['red_otra_url'] ?? '')));
                $posted['red_otra'] = ($posted['red_otra_nombre'] !== '' || $posted['red_otra_url'] !== '')
                    ? $posted['red_otra_nombre'] . ' | ' . $posted['red_otra_url'] : '';
                $ffDatos = $posted;   // si hay errores, el formulario conserva lo escrito
                if (isset($ffExtraPost)) $ffDatos['horarios_extra_post'] = $ffExtraPost;

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
                        : 'Este número no parece un teléfono de España. Revísalo e inténtalo de nuevo (por ejemplo, +34 612 34 56 78).';
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
$tel = fn($t) => $e(telefonoEspanaNormalizar((string)$t) ?? $t);   // teléfono siempre en formato «+34 612 34 56 78»
?>

<style>
    .verificar { min-height: 60vh; display: flex; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .verificar__inner { width: 100%; max-width: 640px; }
    .verificar__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .verificar__negocio { color: var(--color-uno); text-decoration: none; }
    a.verificar__negocio:hover, a.verificar__negocio:focus { color: var(--color-uno); text-decoration: none; cursor: pointer; }
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
    .verificar__invalido { text-align: center; padding: var(--espacio-seis) var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); }
    .verificar__invalido-icono { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--color-ocho); color: var(--color-uno); margin-bottom: var(--espacio-tres); }
    .verificar__invalido .verificar__texto { max-width: 46ch; margin-left: auto; margin-right: auto; }
    .verificar__insignia { display: flex; gap: var(--espacio-tres); align-items: flex-start; margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--color-ocho); border: 1px solid var(--color-cinco); border-radius: var(--radio-dos); }
    .verificar__insignia-icono { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: var(--color-cinco); }
    .verificar__insignia-nombre { display: block; color: var(--color-dos); font-weight: var(--peso-negrita); }
    .verificar__insignia--tarjeta { flex-direction: column; align-items: stretch; gap: var(--espacio-tres); padding: var(--espacio-cuatro); }
    .verificar__insignia-titulo { align-self: center; text-align: center; font-size: var(--fs-dos); font-weight: var(--peso-negrita); color: var(--color-dos); background: #F6E3D8; padding: .25rem .8rem; border-radius: 999px; }
    .verificar__insignia-fila { display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-dos) 0; border-top: 1px solid var(--color-cinco); border-bottom: 1px solid var(--color-cinco); }
    .verificar__insignia-fila .verificar__insignia-nombre { line-height: 1.25; }
    .verificar__insignia-grande { min-width: 52px; height: 52px; }
    .verificar__insignia-grande .icono { width: 28px; height: 28px; }
    .verificar__insignia--tarjeta .verificar__insignia-texto { margin: 0; font-size: var(--fs-dos); line-height: 1.5; text-align: center; }
    .verificar__insignia-cta { margin-top: 0; display: block; text-align: center; white-space: nowrap; font-size: var(--fs-dos); padding: .55rem .6rem; }
    .verificar__insignia-texto { margin: var(--espacio-uno) 0 0; color: var(--color-seis); line-height: 1.5; font-size: var(--fs-dos); }
    .verificar__cta-llamada { display: flex; gap: var(--espacio-tres); align-items: center; justify-content: space-between; flex-wrap: wrap; margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); }
    .verificar__cta-llamada p { margin: 0; flex: 1 1 260px; color: var(--color-dos); line-height: 1.5; }
    /* Dentro del formulario el ritmo lo da el gap del contenedor: sin margen propio (si no, abajo se duplica) */
    .verificar__form-ficha .verificar__cta-llamada, .verificar__form-ficha .verificar__ok { margin: 0; }
    .verificar__alternativa { margin: var(--espacio-cuatro) 0 0; color: var(--color-seis); line-height: 1.6; font-size: var(--fs-dos); }
    .verificar__lateral { display: none; }
    .verificar__nota { display: flex; flex-direction: column; gap: var(--espacio-dos); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--color-dos); color: var(--color-ocho); border-radius: var(--radio-dos); box-shadow: 0 4px 14px rgba(90,62,47,.18); line-height: 1.5; font-size: var(--fs-dos); }
    .verificar__nota .icono { flex: none; color: #F2C9A8; }
    .verificar__nota--destello { animation: verificar-destello 1.1s ease-out; }
    @keyframes verificar-destello {
        0%   { box-shadow: 0 0 0 0 rgba(184,112,79,.8), 0 4px 14px rgba(90,62,47,.18); background: var(--color-uno); }
        60%  { box-shadow: 0 0 0 14px rgba(184,112,79,0), 0 4px 14px rgba(90,62,47,.18); }
        100% { box-shadow: 0 0 0 0 rgba(184,112,79,0), 0 4px 14px rgba(90,62,47,.18); background: var(--color-dos); }
    }
    @media (prefers-reduced-motion: reduce) { .verificar__nota--destello { animation: none; } }
    /* Escritorio: tres columnas (insignia | contenido | nota). En móvil/tablet todo en una columna y la nota se omite */
    .verificar__inner--ancho { max-width: 640px; }
    @media (min-width: 1100px) {
        .verificar__inner--ancho { max-width: 1320px; display: grid; grid-template-columns: 260px minmax(0, 780px) 260px; gap: var(--espacio-cinco); align-items: start; justify-content: center; }
        .verificar__lateral { display: block; position: sticky; top: calc(var(--verificar-header, 0px) + 16px); }
        .verificar__lateral--izq { grid-column: 1; display: flex; flex-direction: column; gap: var(--espacio-tres); }
        .verificar__centro { grid-column: 2; min-width: 0; }
        .verificar__lateral--der { grid-column: 3; }
        .verificar__lateral .verificar__insignia { flex-direction: column; margin: 0; }
        .verificar__insignia--movil { display: none; }
    }
    .verificar__trampa { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    @media (max-width: 600px) { .verificar__acciones .boton { width: 100%; } }
</style>

<?php $principal = $ficha && !in_array($resultado, ['baja', 'llamada', 'actualizacion'], true); ?>
<main class="verificar">
    <div class="verificar__inner<?= $principal ? ' verificar__inner--ancho' : '' ?>">
    <?php if (!$ficha): ?>
        <div class="verificar__invalido">
            <span class="verificar__invalido-icono"><i data-lucide="link-2-off" class="icono" style="width:30px;height:30px;"></i></span>
            <h1 class="verificar__titulo">Este enlace no funciona</h1>
            <p class="verificar__texto">Puede que esté incompleto (algunos programas de correo lo cortan) o que ya no esté activo. Abre de nuevo el email que te enviamos y pulsa el botón desde ahí.</p>
            <p class="verificar__texto">Si no lo encuentras, escríbenos y verificamos la ficha de tu negocio contigo, sin coste.</p>
            <a href="mailto:info@crematoriosdemascotas.com?subject=Quiero%20verificar%20la%20ficha%20de%20mi%20negocio" class="boton uno">Escribirnos</a>
        </div>

    <?php elseif ($resultado === 'baja'): ?>
        <h1 class="verificar__titulo">De acuerdo, no volveremos a escribirte</h1>
        <p class="verificar__texto">Hemos registrado que no te interesa y no recibirás más mensajes de esta campaña. Gracias por tu tiempo.</p>
        <p class="verificar__texto">Si más adelante cambias de idea, la verificación seguirá siendo gratuita: solo tienes que escribirnos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a>.</p>

    <?php elseif ($resultado === 'llamada'): ?>
        <h1 class="verificar__titulo">¡Listo! Te llamaremos pronto</h1>
        <div class="verificar__ok">
            Te llamaremos al <strong class="verificar__telefono"><?= $tel($telConfirmado) ?></strong> lo antes posible, de lunes a viernes en horario comercial. No tienes que hacer nada más.
            <?php if ($telNuevoPublicado): ?>
            <br><br>Además, hemos guardado este número como el teléfono de atención al público de la ficha de <?= $e($ficha['nombre']) ?>, para que las familias te localicen rápido.
            <?php endif; ?>
        </div>
        <p class="verificar__texto">La llamada es gratuita y solo sirve para comprobar que los datos de tu ficha son correctos. Al terminar, tu ficha recibe gratis la insignia «<?= $e($insignias['verificado_humano']['nombre']) ?>». Te hemos enviado un email de confirmación.</p>
        <p class="verificar__texto">¿Quieres ir adelantando datos para agilizar la llamada? Vuelve al email que te enviamos y pulsa «Prefiero completarlo yo».</p>

    <?php elseif ($resultado === 'actualizacion'): ?>
        <h1 class="verificar__titulo">¡Gracias! Hemos recibido tus datos</h1>
        <div class="verificar__ok">
            Hemos recibido la actualización de la ficha de <strong><?= $e($ficha['nombre']) ?></strong>. Nuestro equipo la revisará y, hasta entonces, tu ficha sigue mostrando los datos de siempre: <strong>no se publica nada sin revisar</strong>.
        </div>
        <p class="verificar__texto">Te escribiremos por email en cuanto esté revisada y, si todo es correcto, tu ficha recibirá la insignia «<?= $e($insignias['datos_actualizados']['nombre']) ?>». No tienes que hacer nada más.</p>

    <?php else:
        $telMostrado = fichaTelefonoMostrado($ficha);
        $abrirOtro   = $valores['telefono_opcion'] === 'otro' || !$telMostrado;
        // Insignias que la ficha ya tiene: esa tarjeta no se muestra
        $yaTiene = [];
        try { $yaTiene = array_column(insigniasDeFicha($pdo, $fichaId), 'slug'); } catch (\Throwable $ex) { /* se muestran ambas */ }

        /** Tarjeta lateral de una insignia: réplica grande de la de la ficha pública + texto + botón opcional. */
        $tarjetaInsignia = function (string $slug, string $titulo, string $texto, string $cta = '', string $extra = '') use ($insignias, $e): string {
            $i = $insignias[$slug];
            return '<div class="verificar__insignia verificar__insignia--tarjeta' . $extra . '" id="verificar-insignia-' . $e($slug) . '">'
                 . '<span class="verificar__insignia-titulo">' . $e($titulo) . '</span>'
                 . '<div class="verificar__insignia-fila"><div class="insignias insignias--grande"><span class="insignia verificar__insignia-grande" style="--insignia-color:' . $e($i['color']) . ';"><i data-lucide="' . $e($i['icono']) . '" class="icono"></i></span></div>'
                 . '<span class="verificar__insignia-nombre">' . $e($i['nombre']) . '</span></div>'
                 . '<p class="verificar__insignia-texto">' . $e($texto) . '</p>' . $cta . '</div>';
        };
        $htmlTarjetasIzq = function (bool $movil = false) use ($vista, $yaTiene, $tarjetaInsignia, $urlVista, $e): string {
            $out = '';
            $extra = $movil ? ' verificar__insignia--movil' : '';
            if ($vista === 'llamada') {
                if (!in_array('verificado_humano', $yaTiene, true)) {
                    $out .= $tarjetaInsignia('verificado_humano', 'Esta insignia será de tu ficha', 'Cuando hablemos contigo y comprobemos tus datos, tu ficha la mostrará. Es gratis.', '', $extra);
                }
                return $out;
            }
            if (!in_array('verificado_humano', $yaTiene, true)) {
                $cta = '<a href="' . $e($urlVista('llamada')) . '" class="boton uno verificar__insignia-cta">Quiero que me llamen</a>';
                $out .= $tarjetaInsignia('verificado_humano', 'Tu ficha aún no la tiene', 'Consíguela gratis con una llamada de nuestro equipo.', $cta);
            }
            if (!in_array('datos_actualizados', $yaTiene, true)) {
                $out .= $tarjetaInsignia('datos_actualizados', 'Tu ficha aún no la tiene', 'Consíguela gratis rellenando este formulario.');
            }
            return $out;
        };
    ?>
        <aside class="verificar__lateral verificar__lateral--izq"><?= $htmlTarjetasIzq() ?></aside>
        <div class="verificar__centro">
        <?php if ($vista === 'formulario'): ?>
        <h1 class="verificar__titulo">Completa la ficha de <?php if (!empty($ficha['slug'])): ?><a class="verificar__negocio" href="<?= $e(BASE_URL . '/' . $ficha['slug']) ?>" target="_blank" rel="noopener"><?= $e($ficha['nombre']) ?></a><?php else: ?><span class="verificar__negocio"><?= $e($ficha['nombre']) ?></span><?php endif; ?></h1>
        <p class="verificar__texto">Esto es lo que mostramos de tu negocio. Corrige lo que esté mal y añade lo que falte. <strong>Es gratis y solo lleva unos minutos.</strong> Una ficha completa ayuda a que las familias te encuentren y te contacten antes.<?php if (!empty($ficha['slug'])): ?> <a href="<?= $e(BASE_URL . '/' . $ficha['slug']) ?>" target="_blank" rel="noopener">Ver mi ficha tal como está ahora</a>.<?php endif; ?></p>
        <?php else: ?>
        <h1 class="verificar__titulo">Verifica tu ficha de <span class="verificar__negocio"><?= $e($ficha['nombre']) ?></span></h1>
        <p class="verificar__texto">Tu negocio ya aparece en nuestro directorio. Verificar la ficha es <strong>gratis</strong> y sin ningún compromiso: así las familias saben que tus datos son correctos y pueden contactar contigo rápido.</p>
        <?php endif; ?>

        <?php if ($vista === 'llamada') echo $htmlTarjetasIzq(true); ?>

        <?php if ($error !== ''): ?>
        <div class="verificar__error" role="alert"><?= $e($error) ?></div>
        <?php endif; ?>

        <?php if ($vista === 'llamada'): ?>
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
                <input type="hidden" name="a" value="llamada">
                <input type="hidden" name="form_render_ts" value="<?= time() ?>">
                <div class="verificar__trampa" aria-hidden="true"><label>No rellenar <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

                <?php if ($telMostrado): ?>
                <p class="verificar__pregunta">Te llamaremos al <strong class="verificar__telefono"><?= $tel($telMostrado) ?></strong>. ¿Es correcto?</p>
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
                    <input class="field__input" type="tel" id="verificar-tel-nuevo" name="telefono_nuevo" inputmode="tel" autocomplete="tel" maxlength="20" value="<?= $e($valores['telefono_nuevo']) ?>" placeholder="+34 612 34 56 78">
                    <p class="verificar__ayuda">Escribe un teléfono de España (por ejemplo, +34 612 34 56 78).<?= $telMostrado ? ' Pasará a ser el teléfono de atención al público de tu ficha.' : '' ?></p>
                </div>

                <div class="verificar__acciones">
                    <button type="submit" class="boton uno">Pedir la llamada</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <p class="verificar__alternativa">¿Prefieres completar tú los datos de tu ficha, sin llamada? Vuelve al email que te enviamos y pulsa «Prefiero completarlo yo».</p>
        <?php else: /* vista formulario */ ?>

        <!-- Formulario completo para actualizar los datos de la ficha. Quien pide la llamada puede adelantar datos aquí;
             quien no, simplemente lo completa. Nada se publica sin revisión humana. -->
        <?php
        // «¿Mejor una llamada?»: se pinta justo debajo de «Tus datos de contacto» (hueco $ffTrasContacto del formulario)
        // o, si ya hay una actualización en revisión (no hay formulario), en su sitio habitual.
        $htmlCtaLlamada = function () use ($yaTiene, $urlVista, $e): string {
            if (in_array('verificado_humano', $yaTiene, true)) return '';   // ya verificada por una persona: no se ofrece otra llamada
            ob_start(); ?>
        <div class="verificar__cta-llamada" id="verificar-cta-llamada">
            <p><strong>¿Prefieres que te llamemos?</strong> Rellenaremos esto para ti por teléfono en un par de minutos y obtendrás tu insignia gratis.</p>
            <a href="<?= $e($urlVista('llamada')) ?>" class="boton uno">Quiero que me llamen</a>
        </div>
            <?php return ob_get_clean();
        };
        if ($actPendiente) echo $htmlCtaLlamada();
        ?>

        <section class="verificar__formulario" id="verificar-formulario">
            <?php if ($actPendiente): ?>
            <div class="verificar__ok">Ya tenemos tu actualización y la estamos revisando. Te escribiremos por email en cuanto esté lista. Si quieres añadir algo más, respóndenos a ese email.</div>
            <?php else: ?>

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
                <input type="hidden" name="a" value="formulario">
                <?php
                $ffModo   = 'actualizacion';
                $ffTrasContacto = $htmlCtaLlamada();
                $GEO_ES   = include __DIR__ . '/includes/geo_es.php';
                include __DIR__ . '/includes/form-ficha-campos.php';
                ?>

                <div style="display:flex; flex-direction:column; gap:var(--espacio-dos);">
                    <label class="field__opcion" style="align-items:flex-start; gap:.6rem;">
                        <input type="checkbox" class="field__check" id="consentimiento" name="consentimiento" value="1">
                        <span>Acepto que revisen estos datos y me escriban sobre esta revisión *</span>
                    </label>
                    <label class="field__opcion" style="align-items:flex-start; gap:.6rem;">
                        <input type="checkbox" class="field__check" id="consentimiento_comunicaciones" name="consentimiento_comunicaciones" value="1">
                        <span>Quiero recibir novedades y ofertas para mi negocio (opcional)</span>
                    </label>
                    <p class="verificar__ayuda">Usamos estos datos para revisar y actualizar tu ficha. Más información en nuestra <a href="privacidad.php" target="_blank" rel="noopener">política de privacidad</a>.</p>
                </div>

                <div class="verificar__acciones">
                    <button type="submit" class="boton uno">Enviar para revisión</button>
                </div>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; /* vista */ ?>

        <form method="post" action="verificar-ficha.php" class="verificar__baja">
            <input type="hidden" name="f" value="<?= (int)$fichaId ?>">
            <input type="hidden" name="t" value="<?= $e($token) ?>">
            <input type="hidden" name="accion" value="baja">
            <input type="hidden" name="form_render_ts" value="<?= time() ?>">
            <p class="verificar__texto" style="margin-bottom:var(--espacio-tres);">¿Prefieres no hacer nada? No pasa nada: no te volveremos a escribir sobre esto.</p>
            <button type="submit" class="boton dos">No me interesa</button>
        </form>
        </div><!-- /.verificar__centro -->

        <?php if ($vista === 'formulario' && !$actPendiente): ?>
        <aside class="verificar__lateral verificar__lateral--der">
            <div class="verificar__nota" id="verificar-nota" aria-live="polite">
                <i data-lucide="shield-check" class="icono" style="width:22px;height:22px;"></i>
                <span>Modifica lo que quieras con tranquilidad: <strong>tu ficha no cambia hasta que lo revisemos y lo aprobemos.</strong></span>
            </div>
        </aside>
        <?php endif; ?>

        <script>
        (function () {
            // Altura del header sticky del sitio, para que los laterales queden justo debajo
            var cab0 = document.querySelector('.header');
            if (cab0) document.documentElement.style.setProperty('--verificar-header', cab0.offsetHeight + 'px');
        })();
        (function () {
            // Tarjeta sticky del formulario: destella cada vez que el cursor entra en un campo de escritura
            var nota = document.getElementById('verificar-nota');
            if (nota) {
                var form = document.querySelector('.verificar__form-ficha');
                if (!form) return;
                form.addEventListener('focusin', function (ev) {
                    var t = ev.target;
                    if (!t || !t.matches || !t.matches('input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=hidden]), textarea')) return;
                    nota.classList.remove('verificar__nota--destello');
                    void nota.offsetWidth;   // reinicia la animación
                    nota.classList.add('verificar__nota--destello');
                });
            }
        })();
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
