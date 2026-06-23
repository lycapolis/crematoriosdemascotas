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
require_once 'includes/ImagenHelper.php';

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
    case 'lead_comercial':
        $payload = procesarLeadComercial();
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
        'form_name' => $payload['form_name'],
        'imagenes_guardadas' => $payload['imagenes_guardadas'] ?? 0,
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
    // ── DEFENSAS ANTI-SPAM ───────────────────────────────────────────────────
    // Honeypot: campo invisible que solo los bots completan
    if (!empty($_POST['website_url'])) {
        antiSpamSilentFakeSuccess('honeypot');
        return null;
    }

    // Time-trap: rechazar si se envía en < 3 segundos del render
    $ts = intval($_POST['form_render_ts'] ?? 0);
    if ($ts > 0) {
        $delta = time() - $ts;
        if ($delta < 3) {
            antiSpamSilentFakeSuccess('time-trap: ' . $delta . 's');
            return null;
        }
        if ($delta > 86400) {
            // > 24h: el form expiró (caso poco probable de pestaña abandonada)
            echo json_encode(['ok' => false, 'mensaje' => 'El formulario expiró. Recargá la página y volvé a intentar.']);
            return null;
        }
    }

    // Rate-limit por IP: máximo 3 reseñas/hora desde la misma IP
    $rl = antiSpamRateLimitResenas();
    if (!$rl['ok']) {
        echo json_encode(['ok' => false, 'mensaje' => $rl['mensaje']]);
        return null;
    }
    // ─────────────────────────────────────────────────────────────────────────

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

        $resena_id = (int) $pdo->lastInsertId();

    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar la reseña']);
        }
        return null;
    }

    // ── Procesar imágenes adjuntas (opcionales, hasta 5) ─────────────────────
    $imagenes_guardadas = 0;
    $imagenes_errores   = [];

    if (extension_loaded('gd') && function_exists('imagewebp')
        && !empty($_FILES['imagenes_resena'])
        && is_array($_FILES['imagenes_resena']['name'])) {

        $crematorio_id_int = (int) $crematorio['id'];
        $cantArchivos      = count($_FILES['imagenes_resena']['name']);
        $maxArchivos       = 5;

        // Calcular orden_negocio inicial para tipo='cliente' del crematorio
        $stmtMaxOrden = $pdo->prepare("
            SELECT COALESCE(MAX(orden_negocio), 0) FROM crematorio_imagenes
            WHERE crematorio_id = :id AND tipo = 'cliente'
        ");
        $stmtMaxOrden->execute([':id' => $crematorio_id_int]);
        $ordenInicio = (int) $stmtMaxOrden->fetchColumn();

        for ($i = 0; $i < min($cantArchivos, $maxArchivos); $i++) {
            $name     = $_FILES['imagenes_resena']['name'][$i]    ?? '';
            $tmp_name = $_FILES['imagenes_resena']['tmp_name'][$i] ?? '';
            $error    = $_FILES['imagenes_resena']['error'][$i]    ?? UPLOAD_ERR_NO_FILE;
            $size     = $_FILES['imagenes_resena']['size'][$i]     ?? 0;
            $type     = $_FILES['imagenes_resena']['type'][$i]     ?? '';

            if ($error !== UPLOAD_ERR_OK || empty($tmp_name)) {
                if ($error !== UPLOAD_ERR_NO_FILE) {
                    $imagenes_errores[] = "Error subida: $name (cod $error)";
                }
                continue;
            }
            if ($size > 5 * 1024 * 1024) {
                $imagenes_errores[] = "Excede 5MB: $name";
                continue;
            }

            $archivo = [
                'name' => $name, 'type' => $type, 'tmp_name' => $tmp_name,
                'error' => $error, 'size' => $size,
            ];
            $indice = $ordenInicio + $imagenes_guardadas + 1;

            try {
                $resultado = ImagenHelper::procesar(
                    $archivo,
                    'cliente',
                    $crematorio_slug,
                    'crematorios',
                    $indice,
                    $crematorio_id_int
                );

                if (empty($resultado['ok'])) {
                    $imagenes_errores[] = "Error procesando $name: " . ($resultado['error'] ?? 'desconocido');
                    continue;
                }

                // Registrar en BD con resena_id vinculada — estado_llm='pendiente' (admin la procesa)
                $id_insertado = ImagenHelper::guardarEnDB(
                    $crematorio_id_int,
                    'cliente',
                    $resultado['ruta'],
                    $resultado['nombre'],
                    null,            // sin categoría → estado_llm='pendiente'
                    null,            // sin alt_text → LLM lo genera
                    $resena_id,      // vinculada a la reseña
                    'resena_cliente' // origen: cliente público vía form de reseñas
                );
                if (!$id_insertado) {
                    $imagenes_errores[] = "Error BD para $name";
                    continue;
                }
                // Actualizar orden_negocio (guardarEnDB no lo setea)
                $pdo->prepare("UPDATE crematorio_imagenes SET orden_negocio = :o WHERE id = :id")
                    ->execute([':o' => $indice, ':id' => $id_insertado]);
                $imagenes_guardadas++;
            } catch (Exception $e) {
                $imagenes_errores[] = "Excepción $name: " . $e->getMessage();
                error_log('procesarResena imagen: ' . $e->getMessage());
            }
        }

        if (!empty($imagenes_errores)) {
            error_log('procesarResena — errores en imágenes: ' . implode(' | ', $imagenes_errores));
        }
    }

    // Retornar payload para email/webhook (mantiene funcionalidad existente)
    return [
        'form_id'   => 'form_resena',
        'form_name' => 'Reseña de Crematorio',
        'timestamp' => date('c'),
        'imagenes_guardadas' => $imagenes_guardadas,
        'data' => [
            'nombre'        => $nombre,
            'email'         => $email,
            'comentario'    => $comentario,
            'calificacion'  => $calificacion,
            'crematorio'    => $crematorio['nombre'],
            'crematorio_id' => $crematorio['id'],
            'resena_id'     => $resena_id,
            'imagenes_adjuntas' => $imagenes_guardadas,
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

    if (!$nombre || !$email || !$telefono) {
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
// PROCESAR LEAD COMERCIAL (B2B "Promocionar mi crematorio")
// Persiste en leads_comerciales + email estructurado (campos discretos).
// ═══════════════════════════════════════════════════════════
function procesarLeadComercial() {
    // ── DEFENSAS ANTI-SPAM ───────────────────────────────────────────────────
    // 1) Honeypot — campo invisible que sólo los bots completan
    if (!empty($_POST['website_url'])) {
        // Respondemos OK falso para no delatar la trampa al bot
        echo json_encode(['ok' => true, 'mensaje' => 'Enviado correctamente', 'form_id' => 'form_promocionar', 'form_name' => 'Promocionar Crematorio']);
        return null;
    }
    // 2) Time-trap — submits demasiado rápidos son bots
    $render_ts = (int)($_POST['form_render_ts'] ?? 0);
    if ($render_ts > 0 && (time() - $render_ts) < 2) {
        echo json_encode(['ok' => false, 'mensaje' => 'Envío demasiado rápido']);
        return null;
    }

    $nombre   = filtrar($_POST['nombre'] ?? '');
    $negocio  = filtrar($_POST['nombre_negocio'] ?? '');
    $email    = filtrar($_POST['email'] ?? '');
    $telefono = filtrar($_POST['telefono'] ?? '');
    $ciudad   = filtrar($_POST['ciudad'] ?? '');
    $mensaje  = filtrar($_POST['mensaje'] ?? '');
    $origen   = in_array(($_POST['origen'] ?? ''), ['popup', 'landing'], true) ? $_POST['origen'] : 'popup';

    if (!$nombre || !$negocio || !$email || !$telefono) {
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        return null;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Email no válido']);
        return null;
    }

    try {
        $pdo = obtenerConexion();
        if (!$pdo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión']);
            return null;
        }
        $stmt = $pdo->prepare("INSERT INTO leads_comerciales
            (nombre, nombre_negocio, email, telefono, ciudad, mensaje, origen, ip_address, page_url)
            VALUES (:n, :ne, :e, :t, :c, :m, :o, :ip, :url)");
        $stmt->execute([
            ':n'   => $nombre,
            ':ne'  => $negocio,
            ':e'   => $email,
            ':t'   => $telefono,
            ':c'   => $ciudad ?: null,
            ':m'   => $mensaje ?: null,
            ':o'   => $origen,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':url' => filtrar($_POST['page_url'] ?? ''),
        ]);
    } catch (PDOException $e) {
        error_log('lead_comercial INSERT: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar. Inténtalo de nuevo.']);
        return null;
    }

    return [
        'form_id'   => 'form_lead_comercial',
        'form_name' => 'Lead comercial (Promocionar crematorio)',
        'timestamp' => date('c'),
        'data' => [
            'nombre'         => $nombre,
            'nombre_negocio' => $negocio,
            'email'          => $email,
            'telefono'       => $telefono,
            'ciudad'         => $ciudad,
            'mensaje'        => $mensaje,
            'origen'         => $origen,
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
 * Anti-spam: simula un envío exitoso al bot sin guardar nada.
 * Le decimos "ok" para que no aprenda a evadirnos. Logueamos para tener visibilidad.
 */
function antiSpamSilentFakeSuccess($motivo) {
    error_log('anti-spam bloqueado [' . $motivo . '] ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' ua=' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120));
    echo json_encode([
        'ok'        => true,
        'mensaje'   => 'Enviado correctamente',
        'form_id'   => 'form_resena',
        'form_name' => 'Reseña de Crematorio',
        'imagenes_guardadas' => 0,
    ]);
}

/**
 * Rate-limit por IP: máximo MAX intentos por ventana horaria.
 * Devuelve ['ok'=>true] si está dentro del límite, o ['ok'=>false, 'mensaje'=>...] si no.
 * Crea/actualiza la fila en resenas_rate_limit (hash de IP + ventana horaria).
 */
function antiSpamRateLimitResenas() {
    $MAX_POR_HORA = 3;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return ['ok' => true]; // sin IP no podemos limitar — falla abierta

    $ipHash  = hash('sha256', $ip);
    $ventana = date('Y-m-d H:00:00');

    try {
        $pdo = obtenerConexion();
        if (!$pdo) return ['ok' => true]; // sin BD no podemos limitar — falla abierta

        // INSERT ... ON DUPLICATE KEY UPDATE — atomic increment
        $stmt = $pdo->prepare("
            INSERT INTO resenas_rate_limit (ip_hash, ventana, intentos)
            VALUES (:hash, :ventana, 1)
            ON DUPLICATE KEY UPDATE intentos = intentos + 1
        ");
        $stmt->execute([':hash' => $ipHash, ':ventana' => $ventana]);

        // Leer cantidad actual
        $stmtR = $pdo->prepare("SELECT intentos FROM resenas_rate_limit WHERE ip_hash = :hash AND ventana = :ventana");
        $stmtR->execute([':hash' => $ipHash, ':ventana' => $ventana]);
        $intentos = (int) $stmtR->fetchColumn();

        if ($intentos > $MAX_POR_HORA) {
            error_log('anti-spam rate-limit superado: ' . $intentos . ' intentos en ' . $ventana . ' desde hash=' . substr($ipHash, 0, 12));
            return [
                'ok' => false,
                'mensaje' => 'Hemos recibido varias reseñas desde tu conexión. Por favor esperá un rato antes de enviar otra.'
            ];
        }

        // Limpieza ocasional de filas viejas (>48h) — 1 de cada 50 requests
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM resenas_rate_limit WHERE actualizado_en < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
        }

        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('rate-limit error BD: ' . $e->getMessage());
        return ['ok' => true]; // falla abierta para no bloquear usuarios legítimos
    }
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
