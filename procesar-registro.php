<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PROCESAR REGISTRO DE CREMATORIO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Procesa el formulario de registro de nuevos crematorios.
 * Guarda en BD, envía email y dispara webhooks.
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Fecha: Febrero 2026
 * ═══════════════════════════════════════════════════════════════════════════
 */

// Capturar errores PHP para que no rompan el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iniciar buffer para capturar cualquier output inesperado
ob_start();

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';
require_once 'includes/ImagenHelper.php';

// Limpiar cualquier output del buffer antes de enviar JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIÓN AUXILIAR: Filtrar entrada
// ═══════════════════════════════════════════════════════════════════════════
function filtrar($valor) {
    if (is_array($valor)) {
        return array_map('filtrar', $valor);
    }
    return htmlspecialchars(trim($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// ═══════════════════════════════════════════════════════════════════════════
// ANTI-SPAM (honeypot + time-trap + rate-limit) — mismo patrón que reseñas
// ═══════════════════════════════════════════════════════════════════════════

/** Simula éxito ante el bot (no aprende a evadirnos) y loguea. */
function antiSpamFakeSuccess($motivo) {
    error_log('anti-spam registro bloqueado [' . $motivo . '] ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ' ua=' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120));
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'ok'        => true,
        'mensaje'   => 'Solicitud enviada correctamente',
        'form_id'   => 'form_registro_negocio',
        'form_name' => 'Registro de Crematorio',
    ]);
    exit;
}

/** Rate-limit por IP usando solicitudes_rate_limit. Falla abierta si no hay BD. */
function antiSpamRateLimitRegistro() {
    $MAX_POR_HORA = 3;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') return true;
    try {
        $pdo = obtenerConexion();
        if (!$pdo) return true;
        $ipHash  = hash('sha256', $ip);
        $ventana = date('Y-m-d H:00:00');
        $pdo->prepare("INSERT INTO solicitudes_rate_limit (ip_hash, ventana, intentos)
                       VALUES (:h, :v, 1)
                       ON DUPLICATE KEY UPDATE intentos = intentos + 1")
            ->execute([':h' => $ipHash, ':v' => $ventana]);
        $st = $pdo->prepare("SELECT intentos FROM solicitudes_rate_limit WHERE ip_hash = :h AND ventana = :v");
        $st->execute([':h' => $ipHash, ':v' => $ventana]);
        $intentos = (int) $st->fetchColumn();
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM solicitudes_rate_limit WHERE actualizado_en < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
        }
        return $intentos <= $MAX_POR_HORA;
    } catch (PDOException $e) {
        error_log('rate-limit registro error: ' . $e->getMessage());
        return true; // falla abierta: no bloquear usuarios legítimos
    }
}

// Honeypot: solo los bots completan este campo invisible
if (!empty($_POST['website_url'])) {
    antiSpamFakeSuccess('honeypot');
}

// Time-trap: rechazar envíos demasiado rápidos (bots) o de formularios vencidos
$render_ts = intval($_POST['form_render_ts'] ?? 0);
if ($render_ts > 0) {
    $delta = time() - $render_ts;
    if ($delta < 3) {
        antiSpamFakeSuccess('time-trap: ' . $delta . 's');
    }
    if ($delta > 86400) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['ok' => false, 'mensaje' => 'El formulario expiró. Recargá la página y volvé a intentar.']);
        exit;
    }
}

// Rate-limit por IP
if (!antiSpamRateLimitRegistro()) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensaje' => 'Hemos recibido varias solicitudes desde tu conexión. Esperá un rato antes de enviar otra.']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// RECOGER DATOS DEL FORMULARIO
// ═══════════════════════════════════════════════════════════════════════════

// Contacto comercial (privado)
$contacto_nombre   = filtrar($_POST['contacto_nombre'] ?? '');
$contacto_email    = filtrar($_POST['contacto_email'] ?? '');
$contacto_telefono = filtrar($_POST['contacto_telefono'] ?? '');

// Datos del negocio (público)
$nombre_negocio    = filtrar($_POST['nombre_negocio'] ?? '');
$email_clientes    = filtrar($_POST['email_clientes'] ?? '');
$telefono_clientes = filtrar($_POST['telefono_clientes'] ?? '');

// Ubicación
$pais              = filtrar($_POST['pais'] ?? 'España');
$comunidad         = filtrar($_POST['comunidad'] ?? '');
$provincia         = filtrar($_POST['provincia'] ?? '');
$ciudad            = filtrar($_POST['ciudad'] ?? '');
$direccion         = filtrar($_POST['direccion'] ?? '');
$codigo_postal     = filtrar($_POST['codigo_postal'] ?? '');

// Contenido
$descripcion       = filtrar($_POST['descripcion'] ?? '');
$servicios         = filtrar($_POST['servicios'] ?? '');
$horarios          = filtrar($_POST['horarios'] ?? '');
$precios           = filtrar($_POST['precios'] ?? '');   // opcional
$comentarios_admin = filtrar($_POST['comentarios_admin'] ?? '');

// URLs opcionales
$sitio_web         = filtrar($_POST['sitio_web'] ?? '');
$google_maps_url   = filtrar($_POST['google_maps_url'] ?? '');
$whatsapp          = filtrar($_POST['whatsapp'] ?? '');
$facebook          = filtrar($_POST['facebook'] ?? '');
$instagram         = filtrar($_POST['instagram'] ?? '');

// Metadatos
$page_url          = filtrar($_POST['page_url'] ?? '');
$ip_address        = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent        = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Consentimientos (RGPD) — se validaban solo en JS; ahora también server-side
$consentimiento     = !empty($_POST['consentimiento']) ? 1 : 0;
$consentimiento_com = !empty($_POST['consentimiento_comunicaciones']) ? 1 : 0;
// La fecha la pone la BD (NOW() en el INSERT) para que coincida con created_at.
// Antes era date() de PHP → divergía 1h por TZ PHP≠MySQL (dato legal RGPD).

// ═══════════════════════════════════════════════════════════════════════════
// VALIDACIONES
// ═══════════════════════════════════════════════════════════════════════════

$errores = [];

// Contacto comercial
if (empty($contacto_nombre)) {
    $errores[] = 'El nombre de contacto es requerido';
}
if (empty($contacto_email) || !filter_var($contacto_email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'Email de contacto válido es requerido';
}
if (empty($contacto_telefono)) {
    $errores[] = 'El teléfono de contacto es requerido';
}

// Datos del negocio
if (empty($nombre_negocio)) {
    $errores[] = 'El nombre del crematorio es requerido';
}
if (empty($email_clientes) || !filter_var($email_clientes, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'Email para clientes válido es requerido';
}
if (empty($telefono_clientes)) {
    $errores[] = 'El teléfono para clientes es requerido';
}

// Ubicación
if (empty($pais)) {
    $errores[] = 'El país es requerido';
}
if (empty($provincia)) {
    $errores[] = 'La provincia es requerida';
}
if (empty($ciudad)) {
    $errores[] = 'La ciudad es requerida';
}
if (empty($direccion)) {
    $errores[] = 'La dirección es requerida';
}
if (empty($codigo_postal)) {
    $errores[] = 'El código postal es requerido';
}

// Contenido
if (empty($descripcion)) {
    $errores[] = 'La descripción es requerida';
} elseif (mb_strlen($descripcion) < 150) {
    $errores[] = 'La descripción debe tener al menos 150 caracteres';
}
if (empty($servicios)) {
    $errores[] = 'Los servicios son requeridos';
}
if (empty($horarios)) {
    $errores[] = 'Los horarios son requeridos';
}

// Consentimientos obligatorios (RGPD) — no confiar solo en el JS
if (!$consentimiento || !$consentimiento_com) {
    $errores[] = 'Debes aceptar ambos consentimientos para enviar la solicitud';
}

// Si hay errores, retornar
if (!empty($errores)) {
    echo json_encode([
        'ok' => false,
        'mensaje' => implode('<br>', $errores)
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// GUARDAR EN BASE DE DATOS
// ═══════════════════════════════════════════════════════════════════════════

try {
    $pdo = obtenerConexion();

    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $sql = "INSERT INTO solicitudes_registro (
        contacto_nombre, contacto_email, contacto_telefono,
        nombre_negocio, email_clientes, telefono_clientes,
        pais, comunidad, provincia, ciudad, direccion, codigo_postal,
        descripcion, servicios, horarios, precios, comentarios_admin,
        consentimiento, consentimiento_comunicaciones, consentimiento_fecha,
        sitio_web, google_maps_url, whatsapp, facebook, instagram,
        ip_address, user_agent, page_url
    ) VALUES (
        :contacto_nombre, :contacto_email, :contacto_telefono,
        :nombre_negocio, :email_clientes, :telefono_clientes,
        :pais, :comunidad, :provincia, :ciudad, :direccion, :codigo_postal,
        :descripcion, :servicios, :horarios, :precios, :comentarios_admin,
        :consentimiento, :consentimiento_comunicaciones, NOW(),
        :sitio_web, :google_maps_url, :whatsapp, :facebook, :instagram,
        :ip_address, :user_agent, :page_url
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':contacto_nombre'   => $contacto_nombre,
        ':contacto_email'    => $contacto_email,
        ':contacto_telefono' => $contacto_telefono,
        ':nombre_negocio'    => $nombre_negocio,
        ':email_clientes'    => $email_clientes ?: null,
        ':telefono_clientes' => $telefono_clientes ?: null,
        ':pais'              => $pais,
        ':comunidad'         => $comunidad ?: null,
        ':provincia'         => $provincia,
        ':ciudad'            => $ciudad,
        ':direccion'         => $direccion,
        ':codigo_postal'     => $codigo_postal ?: null,
        ':descripcion'       => $descripcion,
        ':servicios'         => $servicios ?: null,
        ':horarios'          => $horarios ?: null,
        ':precios'           => $precios ?: null,
        ':comentarios_admin' => $comentarios_admin ?: null,
        ':consentimiento'                => $consentimiento,
        ':consentimiento_comunicaciones' => $consentimiento_com,
        ':sitio_web'         => $sitio_web ?: null,
        ':google_maps_url'   => $google_maps_url ?: null,
        ':whatsapp'          => $whatsapp ?: null,
        ':facebook'          => $facebook ?: null,
        ':instagram'         => $instagram ?: null,
        ':ip_address'        => $ip_address,
        ':user_agent'        => $user_agent,
        ':page_url'          => $page_url
    ]);

    $solicitud_id = $pdo->lastInsertId();

    // ═══════════════════════════════════════════════════════════════════════════
    // PROCESAR IMÁGENES
    // ═══════════════════════════════════════════════════════════════════════════

    // Verificar que GD está disponible
    $gdDisponible = extension_loaded('gd') && function_exists('imagecreatefromjpeg');

    // Log para debug
    error_log('=== PROCESANDO IMÁGENES ===');
    error_log('GD disponible: ' . ($gdDisponible ? 'SI' : 'NO'));
    error_log('$_FILES keys: ' . implode(', ', array_keys($_FILES)));
    error_log('Logo isset: ' . (isset($_FILES['logo']) ? 'SI' : 'NO'));
    if (isset($_FILES['logo'])) {
        error_log('Logo tmp_name: ' . ($_FILES['logo']['tmp_name'] ?? 'vacío'));
        error_log('Logo error: ' . ($_FILES['logo']['error'] ?? 'N/A'));
        error_log('Logo size: ' . ($_FILES['logo']['size'] ?? 0));
    }

    // Generar slug temporal para nombres de archivo
    $slugTemp = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $nombre_negocio)));
    $slugTemp = substr($slugTemp, 0, 30) . '-' . $solicitud_id;
    error_log('Slug temporal: ' . $slugTemp);

    // Procesar logo
    if ($gdDisponible && isset($_FILES['logo']) && !empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        error_log('Intentando procesar logo...');
        try {
            $resultadoLogo = ImagenHelper::procesar($_FILES['logo'], 'logo', $slugTemp, 'solicitudes');
            error_log('Resultado logo: ' . json_encode($resultadoLogo));

            if ($resultadoLogo['ok']) {
                $sqlImg = "INSERT INTO solicitud_imagenes (solicitud_id, tipo, nombre_original, nombre_archivo, ruta, extension, tamano, orden)
                           VALUES (:solicitud_id, 'logo', :nombre_original, :nombre_archivo, :ruta, 'webp', :tamano, 0)";
                $stmtImg = $pdo->prepare($sqlImg);
                $stmtImg->execute([
                    ':solicitud_id'    => $solicitud_id,
                    ':nombre_original' => $resultadoLogo['nombre_original'],
                    ':nombre_archivo'  => $resultadoLogo['nombre'],
                    ':ruta'            => $resultadoLogo['ruta'],
                    ':tamano'          => $resultadoLogo['tamano']
                ]);
                error_log('Logo guardado en BD');
            } else {
                error_log('Logo NO OK: ' . ($resultadoLogo['error'] ?? 'sin error'));
            }
        } catch (Exception $e) {
            error_log('EXCEPCIÓN procesando logo: ' . $e->getMessage());
        }
    } else {
        error_log('Logo no procesado - condiciones: GD=' . ($gdDisponible ? '1' : '0') .
                  ', isset=' . (isset($_FILES['logo']) ? '1' : '0') .
                  ', tmp=' . (!empty($_FILES['logo']['tmp_name']) ? '1' : '0') .
                  ', error=' . ($_FILES['logo']['error'] ?? 'N/A'));
    }

    // Procesar galería
    if ($gdDisponible && isset($_FILES['galeria']) && !empty($_FILES['galeria']['tmp_name'][0])) {
        error_log('Intentando procesar galería...');
        try {
            $resultadoGaleria = ImagenHelper::procesarGaleria($_FILES['galeria'], $slugTemp, 'solicitudes', 10);
            error_log('Resultado galería: ' . json_encode($resultadoGaleria));

            if (!empty($resultadoGaleria['imagenes'])) {
                $sqlImg = "INSERT INTO solicitud_imagenes (solicitud_id, tipo, nombre_original, nombre_archivo, ruta, extension, tamano, orden)
                           VALUES (:solicitud_id, 'galeria', :nombre_original, :nombre_archivo, :ruta, 'webp', :tamano, :orden)";
                $stmtImg = $pdo->prepare($sqlImg);

                foreach ($resultadoGaleria['imagenes'] as $orden => $img) {
                    $stmtImg->execute([
                        ':solicitud_id'    => $solicitud_id,
                        ':nombre_original' => $img['nombre_original'],
                        ':nombre_archivo'  => $img['nombre'],
                        ':ruta'            => $img['ruta'],
                        ':tamano'          => $img['tamano'],
                        ':orden'           => $orden + 1
                    ]);
                }
                error_log('Galería guardada en BD');
            }
        } catch (Exception $e) {
            error_log('EXCEPCIÓN procesando galería: ' . $e->getMessage());
        }
    } else {
        error_log('Galería no procesada');
    }

    error_log('=== FIN PROCESAMIENTO IMÁGENES ===');

} catch (PDOException $e) {
    if (ob_get_length()) ob_clean();
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo json_encode(['ok' => false, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar la solicitud. Inténtalo de nuevo.']);
    }
    exit;
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// PREPARAR PAYLOAD PARA EMAIL Y WEBHOOKS
// ═══════════════════════════════════════════════════════════════════════════

$payload = [
    'form_id'   => 'form_registro_negocio',
    'form_name' => 'Registro de Crematorio',
    'timestamp' => date('c'),
    'solicitud_id' => $solicitud_id,
    'data' => [
        'contacto' => [
            'nombre'   => $contacto_nombre,
            'email'    => $contacto_email,
            'telefono' => $contacto_telefono
        ],
        'negocio' => [
            'nombre'           => $nombre_negocio,
            'email_clientes'   => $email_clientes,
            'telefono_clientes'=> $telefono_clientes
        ],
        'ubicacion' => [
            'pais'          => $pais,
            'comunidad'     => $comunidad,
            'provincia'     => $provincia,
            'ciudad'        => $ciudad,
            'direccion'     => $direccion,
            'codigo_postal' => $codigo_postal
        ],
        'contenido' => [
            'descripcion'       => $descripcion,
            'servicios'         => $servicios,
            'horarios'          => $horarios,
            'precios'           => $precios,
            'comentarios_admin' => $comentarios_admin
        ],
        'urls' => [
            'sitio_web'       => $sitio_web,
            'google_maps_url' => $google_maps_url,
            'whatsapp'        => $whatsapp,
            'facebook'        => $facebook,
            'instagram'       => $instagram
        ],
        'email' => $contacto_email
    ],
    'page_url' => $page_url
];

// ═══════════════════════════════════════════════════════════════════════════
// ENVIAR EMAIL
// ═══════════════════════════════════════════════════════════════════════════

function enviarEmailRegistro($payload, $reply_to) {
    $to = EMAIL_CONTACTO;
    $subject = '🏢 Nueva Solicitud de Registro: ' . $payload['data']['negocio']['nombre'];

    $body = "═══════════════════════════════════════════════════════════\n";
    $body .= "NUEVA SOLICITUD DE REGISTRO DE CREMATORIO\n";
    $body .= "═══════════════════════════════════════════════════════════\n\n";

    $body .= "📋 ID SOLICITUD: #{$payload['solicitud_id']}\n";
    $body .= "📅 Fecha: " . date('d/m/Y H:i:s') . "\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "👤 CONTACTO COMERCIAL (PRIVADO)\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Nombre: {$payload['data']['contacto']['nombre']}\n";
    $body .= "Email: {$payload['data']['contacto']['email']}\n";
    $body .= "Teléfono: {$payload['data']['contacto']['telefono']}\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "🏢 DATOS DEL NEGOCIO\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Nombre: {$payload['data']['negocio']['nombre']}\n";
    $body .= "Email clientes: {$payload['data']['negocio']['email_clientes']}\n";
    $body .= "Teléfono clientes: {$payload['data']['negocio']['telefono_clientes']}\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "📍 UBICACIÓN\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "País: {$payload['data']['ubicacion']['pais']}\n";
    $body .= "Comunidad: {$payload['data']['ubicacion']['comunidad']}\n";
    $body .= "Provincia: {$payload['data']['ubicacion']['provincia']}\n";
    $body .= "Ciudad: {$payload['data']['ubicacion']['ciudad']}\n";
    $body .= "Dirección: {$payload['data']['ubicacion']['direccion']}\n";
    $body .= "C.P.: {$payload['data']['ubicacion']['codigo_postal']}\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "📝 CONTENIDO\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Descripción:\n{$payload['data']['contenido']['descripcion']}\n\n";
    $body .= "Servicios:\n{$payload['data']['contenido']['servicios']}\n\n";
    $body .= "Horarios:\n{$payload['data']['contenido']['horarios']}\n\n";
    if (!empty($payload['data']['contenido']['precios'])) {
        $body .= "Precios:\n{$payload['data']['contenido']['precios']}\n\n";
    }

    if (!empty($payload['data']['contenido']['comentarios_admin'])) {
        $body .= "Comentarios adicionales:\n{$payload['data']['contenido']['comentarios_admin']}\n\n";
    }

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "🌐 PRESENCIA EN LÍNEA\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Sitio web: {$payload['data']['urls']['sitio_web']}\n";
    $body .= "Google Maps: {$payload['data']['urls']['google_maps_url']}\n";
    $body .= "WhatsApp: {$payload['data']['urls']['whatsapp']}\n";
    $body .= "Facebook: {$payload['data']['urls']['facebook']}\n";
    $body .= "Instagram: {$payload['data']['urls']['instagram']}\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "🔗 ACCIONES\n";
    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Ver en admin: " . BASE_URL . "/admin/solicitud-ver.php?id={$payload['solicitud_id']}\n\n";

    $body .= "───────────────────────────────────────────────────────────\n";
    $body .= "Enviado desde: {$payload['page_url']}\n";

    $headers = "From: " . SITIO_NOMBRE . " <noreply@crematoriosdemascotas.com>\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $body, $headers);
}

enviarEmailRegistro($payload, $contacto_email);

// ═══════════════════════════════════════════════════════════════════════════
// DISPARAR WEBHOOKS
// ═══════════════════════════════════════════════════════════════════════════

function dispararWebhooks($payload) {
    $webhook_urls = json_decode(WEBHOOK_URLS, true);

    if (empty($webhook_urls) || !is_array($webhook_urls)) {
        return;
    }

    $json_payload = json_encode($payload);

    foreach ($webhook_urls as $url) {
        if (empty($url)) continue;

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $json_payload,
                'timeout' => 5
            ]
        ]);

        @file_get_contents($url, false, $context);
    }
}

dispararWebhooks($payload);

// ═══════════════════════════════════════════════════════════════════════════
// RESPUESTA EXITOSA
// ═══════════════════════════════════════════════════════════════════════════

// Limpiar cualquier output del buffer antes de enviar JSON
if (ob_get_length()) {
    ob_clean();
}

echo json_encode([
    'ok'        => true,
    'mensaje'   => 'Solicitud enviada correctamente',
    'form_id'   => $payload['form_id'],
    'form_name' => $payload['form_name']
]);
