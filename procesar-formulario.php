<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PROCESADOR DE FORMULARIOS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Recibe datos de formularios vía POST (AJAX).
 * - Envía email con datos legibles + JSON estructurado
 * - Dispara webhooks con payload JSON
 * - Devuelve form_id y form_name para eventos GTM
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';

header('Content-Type: application/json; charset=utf-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

// Obtener tipo de formulario
$tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';

switch ($tipo) {
    case 'registro':
        $payload = procesarRegistro();
        break;
    case 'resena':
        $payload = procesarResena();
        break;
    case 'contacto':
        $payload = procesarContacto();
        break;
    default:
        echo json_encode(['ok' => false, 'mensaje' => 'Tipo de formulario no válido']);
        exit;
}

// Si hay payload, agregar page_url y enviar email + webhooks
if ($payload) {
    // Agregar URL de origen con UTMs
    $payload['page_url'] = filtrar($_POST['page_url'] ?? '');

    $reply_to = $payload['data']['email'] ?? '';
    enviarEmail($payload, $reply_to);
    dispararWebhooks($payload);

    echo json_encode([
        'ok'        => true,
        'mensaje'   => 'Enviado correctamente',
        'form_id'   => $payload['form_id'],
        'form_name' => $payload['form_name']
    ]);
}

// ═══════════════════════════════════════════════════════════
// PROCESAR REGISTRO DE NEGOCIO
// ═══════════════════════════════════════════════════════════
function procesarRegistro() {
    $nombre         = filtrar($_POST['nombre'] ?? '');
    $email          = filtrar($_POST['email'] ?? '');
    $telefono       = filtrar($_POST['telefono'] ?? '');
    $nombre_negocio = filtrar($_POST['nombre_negocio'] ?? '');
    $direccion      = filtrar($_POST['direccion'] ?? '');
    $ciudad         = filtrar($_POST['ciudad'] ?? '');
    $estado         = filtrar($_POST['estado'] ?? '');
    $codigo_postal  = filtrar($_POST['codigo_postal'] ?? '');
    $descripcion    = filtrar($_POST['descripcion'] ?? '');
    $servicios      = filtrar($_POST['servicios'] ?? '');
    $horarios       = filtrar($_POST['horarios'] ?? '');
    $sitio_web      = filtrar($_POST['sitio_web'] ?? '');
    $whatsapp       = filtrar($_POST['whatsapp'] ?? '');
    $facebook       = filtrar($_POST['facebook'] ?? '');
    $instagram      = filtrar($_POST['instagram'] ?? '');

    if (!$nombre || !$email || !$telefono || !$nombre_negocio || !$direccion || !$ciudad || !$estado || !$descripcion) {
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Email no válido']);
        return null;
    }

    return [
        'form_id'   => 'form_registro',
        'form_name' => 'Registro de Crematorio',
        'timestamp' => date('c'),
        'data' => [
            'contacto' => [
                'nombre'   => $nombre,
                'email'    => $email,
                'telefono' => $telefono,
            ],
            'crematorio' => [
                'nombre_negocio' => $nombre_negocio,
                'direccion'      => $direccion,
                'ciudad'         => $ciudad,
                'provincia'      => $estado,
                'codigo_postal'  => $codigo_postal,
                'descripcion'    => $descripcion,
                'servicios'      => $servicios,
                'horarios'       => $horarios,
            ],
            'online' => [
                'sitio_web' => $sitio_web,
                'whatsapp'  => $whatsapp,
                'facebook'  => $facebook,
                'instagram' => $instagram,
            ],
            // Campo plano para acceso rápido en webhooks
            'email' => $email,
        ]
    ];
}

// ═══════════════════════════════════════════════════════════
// PROCESAR RESEÑA - CON GUARDADO EN BD
// ═══════════════════════════════════════════════════════════
function procesarResena() {
    $nombre          = filtrar($_POST['nombre'] ?? '');
    $email           = filtrar($_POST['email'] ?? '');
    $comentario      = filtrar($_POST['comentario'] ?? '');
    $calificacion    = intval($_POST['calificacion'] ?? 0);
    $crematorio_slug = filtrar($_POST['crematorio_slug'] ?? '');
    $crematorio_nombre = filtrar($_POST['crematorio'] ?? '');

    // Validaciones
    if (!$nombre || !$email || !$comentario || $calificacion < 1 || $calificacion > 5) {
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Email no válido']);
        return null;
    }

    if (strlen(strip_tags($comentario)) < 10) {
        echo json_encode(['ok' => false, 'mensaje' => 'El comentario debe tener al menos 10 caracteres']);
        return null;
    }

    // Obtener conexión PDO
    $pdo = obtenerConexion();
    if (!$pdo) {
        echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión']);
        return null;
    }

    // Buscar crematorio por slug
    $stmt = $pdo->prepare("SELECT id, nombre FROM crematorios WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $crematorio_slug]);
    $crematorio = $stmt->fetch();

    if (!$crematorio) {
        echo json_encode(['ok' => false, 'mensaje' => 'Crematorio no encontrado']);
        return null;
    }

    // Insertar reseña en BD con estado 'pendiente'
    try {
        $sql = "INSERT INTO resenas (crematorio_id, nombre, email, comentario, calificacion, ip_address, user_agent, page_url)
                VALUES (:crematorio_id, :nombre, :email, :comentario, :calificacion, :ip, :ua, :url)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':crematorio_id' => $crematorio['id'],
            ':nombre'        => $nombre,
            ':email'         => $email,
            ':comentario'    => $comentario,
            ':calificacion'  => $calificacion,
            ':ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'            => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':url'           => filtrar($_POST['page_url'] ?? '')
        ]);

    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar la reseña']);
        }
        return null;
    }

    // Retornar payload para email/webhook (mantiene funcionalidad existente)
    return [
        'form_id'   => 'form_resena',
        'form_name' => 'Reseña de Crematorio',
        'timestamp' => date('c'),
        'data' => [
            'nombre'        => $nombre,
            'email'         => $email,
            'comentario'    => $comentario,
            'calificacion'  => $calificacion,
            'crematorio'    => $crematorio['nombre'],
            'crematorio_id' => $crematorio['id'],
            'estado'        => 'pendiente'
        ]
    ];
}

// ═══════════════════════════════════════════════════════════
// PROCESAR CONTACTO
// ═══════════════════════════════════════════════════════════
function procesarContacto() {
    $nombre   = filtrar($_POST['nombre'] ?? '');
    $email    = filtrar($_POST['email'] ?? '');
    $telefono = filtrar($_POST['telefono'] ?? '');
    $asunto   = filtrar($_POST['asunto'] ?? '');
    $mensaje  = filtrar($_POST['mensaje'] ?? '');

    if (!$nombre || !$email || !$mensaje) {
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Email no válido']);
        return null;
    }

    return [
        'form_id'   => 'form_contacto',
        'form_name' => 'Contacto General',
        'timestamp' => date('c'),
        'data' => [
            'nombre'   => $nombre,
            'email'    => $email,
            'telefono' => $telefono,
            'asunto'   => $asunto,
            'mensaje'  => $mensaje,
        ]
    ];
}

// ═══════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES
// ═══════════════════════════════════════════════════════════

function filtrar($valor) {
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

/**
 * Enviar email con datos legibles + bloque JSON estructurado
 */
function enviarEmail($payload, $reply_to) {
    $destino = EMAIL_CONTACTO;
    $asunto  = '[' . $payload['form_name'] . '] ' . $payload['form_id'] . ' - ' . date('d/m/Y H:i');

    // Cuerpo legible
    $cuerpo  = strtoupper($payload['form_name']) . "\n";
    $cuerpo .= str_repeat('=', 50) . "\n";
    $cuerpo .= "ID: " . $payload['form_id'] . "\n";
    $cuerpo .= "Fecha: " . $payload['timestamp'] . "\n";
    if (!empty($payload['page_url'])) {
        $cuerpo .= "URL origen: " . $payload['page_url'] . "\n";
    }
    $cuerpo .= "\n";

    $cuerpo .= formatearDatosEmail($payload['data']) . "\n";

    // Bloque JSON para copiar/pegar
    $cuerpo .= str_repeat('-', 50) . "\n";
    $cuerpo .= "DATOS ESTRUCTURADOS (JSON):\n\n";
    $cuerpo .= json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    $headers  = "From: " . SITIO_NOMBRE . " <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($destino, $asunto, $cuerpo, $headers);
}

/**
 * Formatear datos del payload como texto legible para el email
 */
function formatearDatosEmail($data, $nivel = 0) {
    $texto = '';
    $indent = str_repeat('  ', $nivel);

    foreach ($data as $clave => $valor) {
        if (is_array($valor)) {
            $texto .= $indent . strtoupper(str_replace('_', ' ', $clave)) . ":\n";
            $texto .= formatearDatosEmail($valor, $nivel + 1);
        } else {
            $label = ucfirst(str_replace('_', ' ', $clave));
            $texto .= $indent . "{$label}: {$valor}\n";
        }
    }

    return $texto;
}

/**
 * Disparar webhooks con el payload JSON
 */
function dispararWebhooks($payload) {
    $urls = json_decode(WEBHOOK_URLS, true);

    if (empty($urls) || !is_array($urls)) {
        return;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    foreach ($urls as $url) {
        $contexto = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ]);

        @file_get_contents($url, false, $contexto);
    }
}
