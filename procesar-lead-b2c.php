<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PROCESAR LEAD B2C (widget lead-capture interno)
 * ═══════════════════════════════════════════════════════════
 *
 * Recibe el form del modal lead-capture cuando un usuario está por hacer
 * clic en tel:/wa.me/maps/web de un negocio (o genérico fuera de ficha).
 *
 * Flujo:
 * 1. Valida datos.
 * 2. Inserta en `leads_b2c`.
 * 3. Inserta evento en `outbound_clicks` con modal_action='sent' y lead_b2c_id.
 * 4. Devuelve { ok:true, destino: 'URL FINAL' } así el JS redirige al usuario.
 *
 * Después de responder (webhook con la MISMA estructura del widget Lycapolis previo +
 * campos nuevos contextuales, backward compat): evento lead_b2c.recibido (webhooks + email al equipo) y, si hay
 * ficha, entrega del lead al negocio según su plan (ver includes/notificaciones.php).
 *
 * Autor: Facundo M. Campos | Lycapolis LLC
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';
require_once 'includes/notificaciones.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// ─── 1. Anti-spam: honeypot + time-trap ─────────────────────────────
if (!empty($_POST['website_url'])) {
    // Honeypot completado → bot. Respondemos OK falso (no le decimos que fue rechazado).
    echo json_encode(['ok' => true, 'destino' => $_POST['accion_destino'] ?? '/']);
    exit;
}
$renderTs = (int)($_POST['form_render_ts'] ?? 0);
if ($renderTs && (time() - $renderTs) < 2) {
    echo json_encode(['ok' => false, 'mensaje' => 'Envío demasiado rápido']);
    exit;
}

// ─── 2. Validación de campos ────────────────────────────────────────
$nombre  = trim($_POST['nombre'] ?? '');
$email   = trim($_POST['email'] ?? '');
$ciudad  = trim($_POST['ciudad'] ?? '');
$mascota  = trim($_POST['mascota'] ?? '');
$tamano  = trim($_POST['mascota_tamano'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');
$countryCode    = trim($_POST['country_code'] ?? '');
$phoneCode      = trim($_POST['phone_code'] ?? '');
$whatsappNumber = trim($_POST['whatsapp_number'] ?? '');

$channelType    = trim($_POST['channel_type'] ?? '');        // tel | wa | maps | web
$accionDestino  = trim($_POST['accion_destino'] ?? '');      // URL final
$crematorioId   = (int)($_POST['crematorio_id'] ?? 0) ?: null;
$crematorioName = trim($_POST['crematorio_nombre'] ?? '');
$phoneAgent     = trim($_POST['phone_agent'] ?? '');
$paginaOrigen   = trim($_POST['pagina_origen'] ?? '');

if ($nombre === '' || $email === '' || $whatsappNumber === '' || $ciudad === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Completa nombre, email, WhatsApp y ciudad.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Email no válido.']);
    exit;
}
if (!in_array($channelType, ['tel', 'wa', 'maps', 'web', ''], true)) {
    $channelType = ''; // ignorar valor inesperado
}

$ip        = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$referrer  = $_SERVER['HTTP_REFERER'] ?? null;

// UTMs (si vienen del query string original guardado por JS en el form)
$utmSource   = trim($_POST['utm_source'] ?? '');
$utmMedium   = trim($_POST['utm_medium'] ?? '');
$utmCampaign = trim($_POST['utm_campaign'] ?? '');

// ─── 3. Insertar lead en leads_b2c ──────────────────────────────────
$pdo = obtenerConexion();
if (!$pdo) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión BD.']);
    exit;
}

$sql = "INSERT INTO leads_b2c
    (channel_type, accion_destino, crematorio_id, crematorio_nombre, phone_agent, pagina_origen,
     servicio, mascota_tamano, nombre, email, country_code, phone_code, whatsapp_number, ciudad_lead, mensaje,
     ip, user_agent, utm_source, utm_medium, utm_campaign, referrer)
    VALUES
    (:channel_type, :accion_destino, :crematorio_id, :crematorio_nombre, :phone_agent, :pagina_origen,
     :mascota, :mascota_tamano, :nombre, :email, :country_code, :phone_code, :whatsapp_number, :ciudad_lead, :mensaje,
     :ip, :user_agent, :utm_source, :utm_medium, :utm_campaign, :referrer)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':channel_type'      => $channelType ?: null,
    ':accion_destino'    => $accionDestino ?: null,
    ':crematorio_id'     => $crematorioId,
    ':crematorio_nombre' => $crematorioName ?: null,
    ':phone_agent'       => $phoneAgent ?: null,
    ':pagina_origen'     => $paginaOrigen ?: null,
    ':mascota'           => $mascota ?: null,
    ':mascota_tamano'    => $tamano ?: null,
    ':nombre'            => $nombre,
    ':email'             => $email,
    ':country_code'      => $countryCode ?: null,
    ':phone_code'        => $phoneCode ?: null,
    ':whatsapp_number'   => $whatsappNumber,
    ':ciudad_lead'       => $ciudad ?: null,
    ':mensaje'           => $mensaje ?: null,
    ':ip'                => $ip,
    ':user_agent'        => $userAgent,
    ':utm_source'        => $utmSource ?: null,
    ':utm_medium'        => $utmMedium ?: null,
    ':utm_campaign'      => $utmCampaign ?: null,
    ':referrer'          => $referrer,
]);
$leadId = (int)$pdo->lastInsertId();

// ─── 4. Insertar evento en outbound_clicks (modal_action=sent) ──────
$pdo->prepare("INSERT INTO outbound_clicks
    (crematorio_id, accion, destino_url, pagina_origen, modal_action, ip, user_agent, referrer, lead_b2c_id)
    VALUES (?, ?, ?, ?, 'sent', ?, ?, ?, ?)")
    ->execute([$crematorioId, $channelType ?: 'unknown', $accionDestino, $paginaOrigen, $ip, $userAgent, $referrer, $leadId]);

// ─── 5. Mapear channel_type al `channelType` legacy del widget Lycapolis ─
// tel→phone, wa→whatsapp, maps→maps, web→web
$channelTypeLegacy = [
    'tel'  => 'phone',
    'wa'   => 'whatsapp',
    'maps' => 'maps',
    'web'  => 'web',
][$channelType] ?? $channelType;

// ─── 6. Construir payload del webhook (compat con widget Lycapolis) ──
$utmParams = http_build_query(array_filter([
    'utm_source'   => $utmSource,
    'utm_medium'   => $utmMedium,
    'utm_campaign' => $utmCampaign,
]));

$payload = [
    'id_formulario'         => 'cmas-lead-capture-interno',
    'formulario'            => 'Formulario de Contacto - Ficha de Crematorio',
    'telefono'              => '+' . $phoneCode . $whatsappNumber,
    'idAgent'               => $crematorioId ? 'crematorio-' . $crematorioId : 'generico',
    'channelType'           => $channelTypeLegacy,
    'phoneAgent'            => $phoneAgent,
    'idStat'                => (string)$leadId,
    'referrer'              => $referrer ?? '',
    'referrerArrival'       => $paginaOrigen,
    'sentDateUTC'           => gmdate('c'),
    'utmParams'             => $utmParams,
    'utmParams2'            => '',
    'priceCurrency'         => 'EUR',
    'idService'             => $crematorioId ? (string)$crematorioId : 'NA',
    'Servicio'              => '',
    'Mascota'               => $mascota,
    'Tamaño de la Mascota'  => $tamano,
    'Nombre'                => $nombre,
    'Email'                 => $email,
    'countryCode'           => $countryCode,
    'phoneCode'             => $phoneCode,
    'WhatsApp Number'       => $whatsappNumber,
    'Ciudad'                => $ciudad,
    'Mensaje'               => $mensaje,
    'ip'                    => $ip,
    // ── Campos nuevos contextuales (backward compat: extras al final) ──
    'crematorio_id'         => $crematorioId,
    'crematorio_nombre'     => $crematorioName,
    'pagina_origen'         => $paginaOrigen,
    'destino_url'           => $accionDestino,
    'lead_source'           => 'cmas_interno_' . ($channelType ?: 'desconocido'),
];

// ─── 7-9. Webhooks, aviso al equipo y entrega al negocio ────────────
// Ya NO van aquí: se lanzan DESPUÉS de responder (al final del archivo) para que el modal
// redirija al instante. El JSON de Make ($payload, arriba) no cambia.

// ─── 10. Si el canal era WhatsApp, reconstruir wa.me con mensaje rico ──
// El mensaje original era genérico ("Hola, me gustaría obtener información").
// Ahora que el usuario llenó el form, le precargamos un mensaje completo
// para que solo tenga que tocar "Enviar" en WhatsApp.
//
// Tres plantillas:
//  A) ficha + número del negocio  → mensaje "al negocio" (vio su ficha, pide info)
//  B) sin crematorio_id           → mensaje "a soporte" (busca orientación)
//  C) ficha + número de soporte   → mensaje "ayúdenme a contactarlos"
$destinoFinal = $accionDestino ?: '/';

if ($channelType === 'wa') {
    // Número de destino: priorizar phoneAgent (data-phone-agent), si no, parsear
    // el wa.me original. Limpiamos cualquier carácter no numérico.
    $numWa = preg_replace('/[^0-9]/', '', $phoneAgent);
    if ($numWa === '' && preg_match('#wa\.me/(\+?\d+)#i', $accionDestino, $m)) {
        $numWa = preg_replace('/[^0-9]/', '', $m[1]);
    }

    if ($numWa !== '') {
        $datos = [
            'nombre'            => $nombre,
            'whatsapp_cliente'  => $whatsappNumber,
            'email'             => $email,
            'ciudad'            => $ciudad,
            'mascota'           => $mascota,
            'tamano'            => $tamano,
            'mensaje'           => $mensaje,
            'crematorio_nombre' => $crematorioName,
            'pagina_origen'     => $paginaOrigen,
        ];
        // Plantilla según el DESTINO real del número, no por tier hardcodeado:
        // si el wa.me final apunta a soporte B2C (por regla de tier o por ficha
        // sin WhatsApp propio), el framing es "ayúdenme a contactarlos".
        $waSoporte = resolverWaSoportePais('ES');
        $esSoporte = ($waSoporte !== '' && $numWa === $waSoporte);
        $texto = ($crematorioId && $esSoporte)
            ? armarMensajeWaAyuda($datos)
            : (($crematorioId && $crematorioName !== '')
                ? armarMensajeWaNegocio($datos)
                : armarMensajeWaSoporte($datos));
        $destinoFinal = 'https://wa.me/' . $numWa . '?text=' . urlencode($texto);
    }
}

echo json_encode([
    'ok'      => true,
    'destino' => $destinoFinal,
    'lead_id' => $leadId,
], JSON_UNESCAPED_UNICODE);

// ─── 11. Avisos tras responder (capa de notificaciones por evento) ──
// a) evento lead_b2c.recibido → webhooks (mismo JSON de siempre en lista + variables extra)
//    y email diseñado al equipo; b) entrega del lead al negocio (email + evento lead.entregado).
// Un fallo aquí se registra pero nunca afecta al usuario, que ya tiene su respuesta.
try {
    require_once __DIR__ . '/includes/notificaciones/despachador.php';
    notifCerrarRespuesta();

    $resNotif = notificarEvento($pdo, 'lead_b2c.recibido', ['lead_id' => $leadId, 'payload' => $payload]);

    // Estado del webhook en la propia fila del lead (lo que ve admin/leads-b2c.php)
    $wh = $resNotif['webhook'] ?? null;
    if ($wh && in_array($wh['estado'], ['enviada', 'fallida'], true)) {
        $pdo->prepare("UPDATE leads_b2c SET webhook_enviado = ?, webhook_error = ? WHERE id = ?")
            ->execute([$wh['estado'] === 'enviada' ? 1 : 0, $wh['estado'] === 'fallida' ? ($wh['error'] ?? 'fallido') : null, $leadId]);
    }

    // Entrega al negocio (solo si el lead viene de una ficha; el resto de reglas están dentro)
    if ($crematorioId) {
        notificarNegocioLead($pdo, $leadId);
    }
} catch (\Throwable $e) {
    error_log('[procesar-lead-b2c] avisos tras responder: ' . $e->getMessage());
}


// ═══════════════════════════════════════════════════════════════════
// Helpers para armar el mensaje rico de WhatsApp
// ═══════════════════════════════════════════════════════════════════

/**
 * Plantilla A — Mensaje del cliente AL NEGOCIO
 * Usado cuando el lead viene de una ficha específica (hay crematorio_id).
 * Tono: cliente potencial dirigiéndose al negocio. Castellano neutral.
 */
function armarMensajeWaNegocio(array $d): string {
    $crematorio = $d['crematorio_nombre'] ?? '';
    $nombre     = $d['nombre'] ?? '';
    $mascota    = trim($d['mascota'] ?? '');
    $tamano     = trim($d['tamano'] ?? '');
    $mensaje    = trim($d['mensaje'] ?? '');
    $wa         = trim($d['whatsapp_cliente'] ?? '');
    $email      = trim($d['email'] ?? '');
    $ciudad     = trim($d['ciudad'] ?? '');

    $partes = [];
    $partes[] = "Hola, vi su ficha de {$crematorio} en Crematoriosdemascotas.com.";
    $partes[] = '';

    $linea = "Soy {$nombre}.";
    if ($mascota !== '') {
        $linea .= " Tengo un(a) {$mascota}";
        $linea .= ($tamano !== '') ? " de tamaño {$tamano}." : '.';
    } else {
        $linea .= ($tamano !== '')
            ? " Tengo una mascota de tamaño {$tamano} y necesito información."
            : ' Necesito información sobre sus servicios.';
    }
    $partes[] = $linea;

    if ($mensaje !== '') {
        $partes[] = '';
        $partes[] = $mensaje;
    }

    $partes[] = '';
    $partes[] = '📞 Para contactarme:';
    if ($wa !== '')     $partes[] = "WhatsApp: {$wa}";
    if ($email !== '')  $partes[] = "Email: {$email}";
    if ($ciudad !== '') $partes[] = "Ciudad: {$ciudad}";

    $partes[] = '';
    $partes[] = '— Lead vía Crematoriosdemascotas.com';

    return implode("\n", $partes);
}

/**
 * Plantilla C — Mensaje del cliente solicitando AYUDA para contactar un negocio.
 * Se usa cuando el número destino es soporte B2C (regla de tier o ficha sin
 * WhatsApp propio). Framing "pídanos que los ayudemos a contactarlos".
 */
function armarMensajeWaAyuda(array $d): string {
    $crematorio = $d['crematorio_nombre'] ?? '';
    $nombre     = $d['nombre'] ?? '';
    $mascota    = trim($d['mascota'] ?? '');
    $tamano     = trim($d['tamano'] ?? '');
    $mensaje    = trim($d['mensaje'] ?? '');
    $wa         = trim($d['whatsapp_cliente'] ?? '');
    $email      = trim($d['email'] ?? '');
    $ciudad     = trim($d['ciudad'] ?? '');

    $partes = [];
    $partes[] = "Hola, vi la ficha de {$crematorio} en Crematoriosdemascotas.com y me gustaría que me ayuden a contactarlos.";
    $partes[] = '';

    $linea = "Soy {$nombre}.";
    if ($mascota !== '') {
        $linea .= " Tengo un(a) {$mascota}";
        $linea .= ($tamano !== '') ? " de tamaño {$tamano}." : '.';
    } else {
        $linea .= ($tamano !== '')
            ? " Tengo una mascota de tamaño {$tamano} y necesito información."
            : ' Necesito información sobre sus servicios.';
    }
    $partes[] = $linea;

    if ($mensaje !== '') {
        $partes[] = '';
        $partes[] = $mensaje;
    }

    $partes[] = '';
    $partes[] = '📞 Para contactarme:';
    if ($wa !== '')    $partes[] = "WhatsApp: {$wa}";
    if ($email !== '') $partes[] = "Email: {$email}";
    if ($ciudad !== '') $partes[] = "Ciudad: {$ciudad}";

    return implode("\n", $partes);
}

/**
 * Plantilla B — Mensaje del cliente A SOPORTE
 * Usado cuando el lead NO viene de una ficha específica (sin crematorio_id).
 * Tono: cliente buscando orientación al directorio. Castellano neutral.
 */
function armarMensajeWaSoporte(array $d): string {
    $nombre   = $d['nombre'] ?? '';
    $mascota  = trim($d['mascota'] ?? '');
    $tamano   = trim($d['tamano'] ?? '');
    $mensaje  = trim($d['mensaje'] ?? '');
    $wa       = trim($d['whatsapp_cliente'] ?? '');
    $email    = trim($d['email'] ?? '');
    $ciudad   = trim($d['ciudad'] ?? '');

    $partes = [];
    $partes[] = "Hola, soy {$nombre}.";
    $partes[] = '';

    $linea = 'Estoy buscando un crematorio para mi mascota';
    $linea .= ($ciudad !== '') ? " en {$ciudad}." : '.';
    $partes[] = $linea;

    if ($mascota !== '' || $tamano !== '') {
        $linea = '';
        if ($mascota !== '') {
            $linea = "Tengo un(a) {$mascota}";
            $linea .= ($tamano !== '') ? " de tamaño {$tamano}." : '.';
        } elseif ($tamano !== '') {
            $linea = "Mi mascota es de tamaño {$tamano}.";
        }
        $partes[] = $linea;
    }

    if ($mensaje !== '') {
        $partes[] = '';
        $partes[] = $mensaje;
    }

    $partes[] = '';
    $partes[] = '📞 Para contactarme:';
    if ($wa !== '')    $partes[] = "WhatsApp: {$wa}";
    if ($email !== '') $partes[] = "Email: {$email}";

    return implode("\n", $partes);
}
