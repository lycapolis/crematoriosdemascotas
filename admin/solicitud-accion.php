<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ACCIONES DE SOLICITUD DE REGISTRO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Procesa las acciones de aprobar/rechazar solicitudes de registro.
 * Endpoint AJAX.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autenticado']);
    exit;
}
requierePermiso('solicitudes');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$admin = obtenerAdminActual();
$pdo = obtenerConexion();

// Parámetros
$id        = intval($_POST['id'] ?? 0);
$accion    = $_POST['accion'] ?? '';
$motivo    = trim($_POST['motivo'] ?? '');
$confirmar = !empty($_POST['confirmar']);

$accionesValidas = ['aprobar', 'rechazar', 'reevaluar', 'eliminar'];
if (!$id || !in_array($accion, $accionesValidas, true)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

if ($accion === 'eliminar' && !$confirmar) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta confirmación para eliminar']);
    exit;
}

// Obtener solicitud
$sql = "SELECT * FROM solicitudes_registro WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    echo json_encode(['ok' => false, 'mensaje' => 'Solicitud no encontrada']);
    exit;
}

// Validar transiciones permitidas según estado actual
$estado = $solicitud['estado'];
$transicionPermitida = match ($accion) {
    'aprobar'   => $estado === 'pendiente',
    'rechazar'  => $estado === 'pendiente',
    'reevaluar' => $estado === 'rechazada',
    'eliminar'  => $estado === 'rechazada',
    default     => false,
};
if (!$transicionPermitida) {
    echo json_encode([
        'ok' => false,
        'mensaje' => "No se puede '$accion' una solicitud en estado '$estado'"
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIÓN: Crear slug único
// ═══════════════════════════════════════════════════════════════════════════
function crearSlug($texto) {
    // Convertir a minúsculas
    $slug = mb_strtolower($texto, 'UTF-8');

    // Reemplazar caracteres especiales españoles
    $slug = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'ç'],
        ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'c'],
        $slug
    );

    // Remover caracteres no alfanuméricos (excepto espacios)
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

    // Reemplazar espacios y múltiples guiones por un solo guion
    $slug = preg_replace('/[\s-]+/', '-', $slug);

    // Quitar guiones al inicio y final
    $slug = trim($slug, '-');

    return $slug;
}

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIÓN: Generar slug único y SEO-friendly
// ═══════════════════════════════════════════════════════════════════════════
// Lógica (regen 2026-06-23):
//   1. SIEMPRE que haya CLAUDE_API_KEY → LLM (Claude Haiku) analiza el nombre:
//      decide si acortar (o dejarlo como está si ya es óptimo), clasifica si
//      tiene contexto del nicho, sugiere keyword si no lo tiene.
//   2. Si NO hay API key → fallback con detección local de keyword.
//   3. Si el slug contiene palabra ambigua (funeraria/crematorio/tanatorio/etc.)
//      sin "mascotas"/"pets"/"animal" → se inserta "mascotas" para clarificar.
//   4. slug = nombre_corto + keyword (si hace falta) + ciudad.
//   5. Si hay colisión exacta → sufijo numérico (-2, -3...).
// Patrón documentado en BITACORA.md (entrada 2026-06-23).
function generarSlugUnico($pdo, $nombre, $ciudad) {
    $nombreCorto = $nombre;
    $kwSugerida  = null;
    $tieneKw     = false;

    // ─── Paso 1: SIEMPRE pasar por LLM si está disponible ─────────────
    // El LLM decide si conviene acortar (puede devolver el mismo nombre si ya
    // es óptimo) y clasifica el tipo de servicio para sugerir keyword.
    // Proveedor/modelo configurables en admin/configuracion-ia.php (sección 'slug').
    $cfgSlug = function_exists('obtenerConfigIA') ? obtenerConfigIA($pdo, 'slug') : ['proveedor' => 'claude'];
    $slugKeyOk = ($cfgSlug['proveedor'] === 'openrouter')
        ? (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '')
        : (defined('CLAUDE_API_KEY') && CLAUDE_API_KEY !== '');

    if ($slugKeyOk) {
        $prompt = "Analizá este negocio del rubro \"crematorios de mascotas\" y respondé en formato JSON estricto.

Negocio:
- Nombre original: " . $nombre . "
- Ciudad: " . $ciudad . "

Devolvé un JSON con estos 3 campos:

1. \"nombre_corto\": versión acortada del nombre, óptima para URL.
   - Máximo 5 palabras (idealmente 2-3)
   - Mantener nombre propio/marca del negocio
   - Quitar texto descriptivo redundante (ej. 'Tanatorio Crematorio de Mascotas', '24 horas', 'incinerar perros gatos')
   - NO incluir ciudad
   - Sin puntuación, símbolos

2. \"tiene_contexto_nicho\": true si el nombre_corto YA contiene una palabra clave del nicho (mascotas/pets/crematorio/tanatorio/funeraria/incineración/animal/canino). false si NO.

3. \"keyword_sugerida\": qué keyword agregar si tiene_contexto_nicho es false. Opciones: crematorio-mascotas / tanatorio-mascotas / funeraria-mascotas / eutanasia-mascotas / mascotas. Si tiene_contexto_nicho es true: null.

Respondé SOLO el JSON, sin markdown:
{\"nombre_corto\": \"...\", \"tiene_contexto_nicho\": true/false, \"keyword_sugerida\": \"...\" o null}";

        if (function_exists('llamarLLM')) {
            $res = llamarLLM($pdo, 'slug', $prompt);
            if (!empty($res['ok']) && !empty($res['texto'])) {
                $texto = trim($res['texto']);
                $texto = preg_replace('/^```(?:json)?\s*/', '', $texto);
                $texto = preg_replace('/\s*```$/', '', $texto);
                $data  = json_decode($texto, true);
                if (is_array($data)) {
                    $nombreCorto = trim($data['nombre_corto'] ?? $nombre);
                    $tieneKw     = (bool)($data['tiene_contexto_nicho'] ?? false);
                    $kwSugerida  = $data['keyword_sugerida'] ?? null;
                }
            }
        }
    } else {
        // Detección local cuando el nombre ya es corto.
        $slugTmp = slugificar($nombreCorto);
        $palabrasNicho = ['mascotas', 'mascota', 'pets', 'pet', 'animal', 'canino',
                          'crematorio', 'tanatorio', 'funeraria', 'incineradora'];
        $tieneKw = false;
        foreach ($palabrasNicho as $pal) {
            if (str_contains($slugTmp, $pal)) { $tieneKw = true; break; }
        }
        if (!$tieneKw) {
            $kwSugerida = 'crematorio-mascotas'; // default
        }
    }

    // ─── Paso 2: evitar doble ciudad si el nombre la trae ─────────────
    $ciudadSlug = slugificar($ciudad);
    if ($ciudadSlug && str_contains(slugificar($nombreCorto), $ciudadSlug)) {
        $nombreCorto = trim(preg_replace('/\b' . preg_quote($ciudad, '/') . '\b/iu', '', $nombreCorto));
        $nombreCorto = preg_replace('/\s+/', ' ', $nombreCorto);
    }

    // ─── Paso 3: construir slug base ──────────────────────────────────
    $partes = [$nombreCorto];
    if (!$tieneKw && $kwSugerida) {
        $partes[] = $kwSugerida;
    }
    $partes[] = $ciudad;
    $baseSlug = slugificar(implode(' ', $partes));

    // ─── Paso 4: clarificación de palabras ambiguas ───────────────────
    // Si el slug ya tiene funeraria/crematorio/tanatorio/incineradora pero NO
    // tiene "mascotas/pets/animal" en otro lugar, insertamos "mascotas" después
    // para clarificar el nicho (caso: "Funeraria San Antonio Abad" sin contexto
    // podría confundirse con funeraria de personas).
    $ambiguas    = ['funeraria', 'crematorio', 'tanatorio', 'incineradora', 'cementerio'];
    $aclaradoras = ['mascotas', 'mascota', 'pets', 'pet-', '-pet', 'animal', 'canino'];
    $tieneAclaracion = false;
    foreach ($aclaradoras as $a) {
        if (str_contains($baseSlug, $a)) { $tieneAclaracion = true; break; }
    }
    if (!$tieneAclaracion) {
        foreach ($ambiguas as $amb) {
            if (str_contains($baseSlug, $amb . '-')) {
                $baseSlug = preg_replace('/' . $amb . '-/', $amb . '-mascotas-', $baseSlug, 1);
                break;
            }
        }
    }

    // ─── Paso 5: garantizar unicidad ──────────────────────────────────
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crematorios WHERE slug = :slug");
    $stmt->execute([':slug' => $baseSlug]);
    if ($stmt->fetchColumn() == 0) return $baseSlug;

    $i = 2;
    while (true) {
        $slug = $baseSlug . '-' . $i;
        $stmt->execute([':slug' => $slug]);
        if ($stmt->fetchColumn() == 0) return $slug;
        $i++;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIÓN: Buscar o crear provincia
// ═══════════════════════════════════════════════════════════════════════════
function buscarOCrearProvincia($pdo, $nombreProvincia, $nombreComunidad = null) {
    // Normalizar nombre
    $nombreNormalizado = trim($nombreProvincia);

    // Buscar provincia existente por nombre
    $sql = "SELECT id FROM provincias WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nombre' => $nombreNormalizado]);
    $provincia = $stmt->fetch();

    if ($provincia) {
        return $provincia['id'];
    }

    // Si no existe, crear nueva provincia
    // Primero buscar o crear comunidad autónoma
    $comunidadId = null;

    if ($nombreComunidad) {
        $sql = "SELECT id FROM comunidades_autonomas WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nombre' => trim($nombreComunidad)]);
        $comunidad = $stmt->fetch();

        if ($comunidad) {
            $comunidadId = $comunidad['id'];
        } else {
            // Crear comunidad
            $slugComunidad = crearSlug($nombreComunidad);
            $sql = "INSERT INTO comunidades_autonomas (nombre, slug) VALUES (:nombre, :slug)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => trim($nombreComunidad), ':slug' => $slugComunidad]);
            $comunidadId = $pdo->lastInsertId();
        }
    } else {
        // Usar comunidad "Otros" por defecto
        $sql = "SELECT id FROM comunidades_autonomas WHERE slug = 'otros' LIMIT 1";
        $stmt = $pdo->query($sql);
        $comunidad = $stmt->fetch();

        if ($comunidad) {
            $comunidadId = $comunidad['id'];
        } else {
            // Crear comunidad "Otros"
            $sql = "INSERT INTO comunidades_autonomas (nombre, slug) VALUES ('Otros', 'otros')";
            $pdo->exec($sql);
            $comunidadId = $pdo->lastInsertId();
        }
    }

    // Crear provincia
    $slugProvincia = crearSlug($nombreProvincia);
    $sql = "INSERT INTO provincias (comunidad_id, nombre, slug) VALUES (:comunidad_id, :nombre, :slug)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':comunidad_id' => $comunidadId,
        ':nombre' => $nombreNormalizado,
        ':slug' => $slugProvincia
    ]);

    return $pdo->lastInsertId();
}

// ═══════════════════════════════════════════════════════════════════════════
// PROCESAR ACCIÓN
// ═══════════════════════════════════════════════════════════════════════════

try {
    $pdo->beginTransaction();

    if ($accion === 'aprobar') {
        // ═══════════════════════════════════════════════════════════════════
        // APROBAR SOLICITUD
        // ═══════════════════════════════════════════════════════════════════

        // 1. Buscar/crear provincia
        $provinciaId = buscarOCrearProvincia(
            $pdo,
            $solicitud['provincia'],
            $solicitud['comunidad']
        );

        // 2. Generar slug único
        $slug = generarSlugUnico($pdo, $solicitud['nombre_negocio'], $solicitud['ciudad']);

        // 3. Crear crematorio
        // Nota: horarios en crematorios es JSON, pero en solicitud es texto plano
        // Los horarios de texto se guardan en descripcion o se pueden editar después
        $descripcionCompleta = $solicitud['descripcion'];
        if (!empty($solicitud['horarios'])) {
            $descripcionCompleta .= "\n\nHorarios: " . $solicitud['horarios'];
        }
        if (!empty($solicitud['servicios'])) {
            $descripcionCompleta .= "\n\nServicios: " . $solicitud['servicios'];
        }

        // Construir JSONs multi-fuente — JSON = source of truth, flats se sincronizan después
        $fecha    = date('Y-m-d');
        $creadoAt = date('Y-m-d H:i:s');

        // Teléfonos: principal (con fallback a contacto_telefono) + clientes (si difiere)
        $telsArr = [];
        $telPrincipal = $solicitud['telefono_clientes'] ?: $solicitud['contacto_telefono'];
        if (!empty($telPrincipal)) {
            $telsArr[] = ['id' => 'p1', 'origen' => 'manual', 'tipo' => 'principal',
                          'label' => 'Teléfono principal', 'numero' => $telPrincipal, 'visible' => true];
        }
        if (!empty($solicitud['telefono_clientes'])) {
            $telsArr[] = ['id' => 'p2', 'origen' => 'manual', 'tipo' => 'clientes',
                          'label' => 'Atención a clientes', 'numero' => $solicitud['telefono_clientes'], 'visible' => true];
        }
        $telefonosJson = !empty($telsArr) ? json_encode($telsArr, JSON_UNESCAPED_UNICODE) : null;

        // Emails: general (con fallback a contacto_email) + clientes (si difiere)
        $mailsArr = [];
        $emailGeneral = $solicitud['email_clientes'] ?: $solicitud['contacto_email'];
        if (!empty($emailGeneral)) {
            $mailsArr[] = ['id' => 'e1', 'origen' => 'manual', 'tipo' => 'general',
                           'label' => 'General / Contacto', 'email' => $emailGeneral, 'visible' => true];
        }
        if (!empty($solicitud['email_clientes'])) {
            $mailsArr[] = ['id' => 'e2', 'origen' => 'manual', 'tipo' => 'clientes',
                           'label' => 'Atención a clientes', 'email' => $solicitud['email_clientes'], 'visible' => true];
        }
        $emailsJson = !empty($mailsArr) ? json_encode($mailsArr, JSON_UNESCAPED_UNICODE) : null;

        // Redes sociales: facebook + instagram (whatsapp es campo flat propio, no red)
        $redesEntries = [];
        if (!empty($solicitud['facebook'])) {
            $redesEntries[] = ['red' => 'facebook', 'url' => $solicitud['facebook'],
                               'label' => 'Facebook', 'visible' => true];
        }
        if (!empty($solicitud['instagram'])) {
            $redesEntries[] = ['red' => 'instagram', 'url' => $solicitud['instagram'],
                               'label' => 'Instagram', 'visible' => true];
        }
        $redesJson = !empty($redesEntries)
            ? json_encode(['modo' => 'iconos', 'entries' => $redesEntries], JSON_UNESCAPED_UNICODE)
            : null;

        // Descripción: una entrada del cliente, marcada como activa (origen manual_negocio)
        $descsArr = [];
        if (!empty($descripcionCompleta)) {
            $descsArr[] = [
                'id'         => 'd1',
                'origen'     => 'manual_negocio',
                'valor'      => $descripcionCompleta,
                'activo'     => true,
                'creado_at'  => $creadoAt,
                'editado_at' => null,
            ];
        }
        $descripcionesJson = !empty($descsArr) ? json_encode($descsArr, JSON_UNESCAPED_UNICODE) : null;

        // Conservar los textos crudos originales que cargó el cliente al registrarse —
        // sirven como referencia permanente en el panel "Texto original" del admin.
        $textoOrigenJson = json_encode([
            'fuente'            => 'manual_negocio',
            'fecha'             => $fecha,
            'descripcion'       => $solicitud['descripcion']       ?: null,
            'horarios_texto'    => $solicitud['horarios']          ?: null,
            'servicios_texto'   => $solicitud['servicios']         ?: null,
            'precios_texto'     => $solicitud['precios']           ?: null,
            'comentarios_admin' => $solicitud['comentarios_admin'] ?: null,
            'meta_seo_original' => null,
        ], JSON_UNESCAPED_UNICODE);

        // INSERT — NO incluimos las 6 flats sincronizables (telefono, telefono_clientes,
        // email, email_clientes, descripcion, meta_description_seo). Las llena el helper
        // sincronizarCamposFlat() después, leyendo desde los JSON.
        $sql = "INSERT INTO crematorios (
                    provincia_id, nombre, slug,
                    website, whatsapp, google_maps_url,
                    direccion_completa, ciudad, codigo_postal,
                    telefonos_json, emails_json, redes_json,
                    descripciones_json,
                    comentarios_admin,
                    texto_origen_json,
                    verificado, destacado, activo, origen
                ) VALUES (
                    :provincia_id, :nombre, :slug,
                    :website, :whatsapp, :google_maps_url,
                    :direccion, :ciudad, :codigo_postal,
                    :telefonos_json, :emails_json, :redes_json,
                    :descripciones_json,
                    :comentarios_admin,
                    :texto_origen_json,
                    0, 0, 1, 'registro'
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':provincia_id'        => $provinciaId,
            ':nombre'              => $solicitud['nombre_negocio'],
            ':slug'                => $slug,
            ':website'             => $solicitud['sitio_web'],
            ':whatsapp'            => $solicitud['whatsapp'] ?: null,
            ':google_maps_url'     => $solicitud['google_maps_url'],
            ':direccion'           => $solicitud['direccion'],
            ':ciudad'              => $solicitud['ciudad'],
            ':codigo_postal'       => $solicitud['codigo_postal'],
            ':telefonos_json'      => $telefonosJson,
            ':emails_json'         => $emailsJson,
            ':redes_json'          => $redesJson,
            ':descripciones_json'  => $descripcionesJson,
            // No copiar el comentario del cliente a la flat — eso es un MENSAJE del cliente
            // al admin, no una nota interna del admin. Queda preservado en texto_origen_json
            // y se muestra en una tarjeta dedicada del editor. La flat queda libre para que
            // el admin escriba sus propias notas.
            ':comentarios_admin'   => null,
            ':texto_origen_json'   => $textoOrigenJson,
        ]);

        $crematorioId = $pdo->lastInsertId();

        // Sincronizar las 6 flats (telefono, telefono_clientes, email, email_clientes,
        // descripcion, meta_description_seo) desde los JSONs recién insertados.
        sincronizarCamposFlat($pdo, $crematorioId);

        // ─── Geocodificar dirección con Google Geocoding API ──────────────
        // Si funciona → guarda lat/lng (+ google_place_id si no está seteado).
        // Si falla → silenciosamente sigue (la ficha queda sin coords, el admin
        // puede usar el botón manual en editar-ficha-negocio.php).
        if (function_exists('geocodificarDireccion')) {
            $geo = geocodificarDireccion(
                trim(($solicitud['direccion'] ?? '') . ' ' . ($solicitud['codigo_postal'] ?? '')),
                $solicitud['ciudad'] ?? '',
                'ES'
            );
            if (!empty($geo['ok'])) {
                $pdo->prepare(
                    "UPDATE crematorios SET
                        latitud  = :lat,
                        longitud = :lng,
                        google_place_id = COALESCE(NULLIF(google_place_id, ''), :pid)
                     WHERE id = :id"
                )->execute([
                    ':lat' => $geo['lat'],
                    ':lng' => $geo['lng'],
                    ':pid' => $geo['place_id'] ?: null,
                    ':id'  => $crematorioId,
                ]);
            }
        }

        // 4. Actualizar solicitud
        $sql = "UPDATE solicitudes_registro SET
                    estado = 'aprobada',
                    crematorio_id = :crematorio_id,
                    moderado_por = :admin_id,
                    moderado_en = NOW()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':crematorio_id' => $crematorioId,
            ':admin_id'      => $admin['id'],
            ':id'            => $id
        ]);

        // 5. Copiar imágenes de solicitud al crematorio
        // copiarACrematorio usa el índice de la entrada del array para generar nombres
        // únicos (001-, 002-, ...). Por eso hay que pasar TODAS las imágenes de un tipo
        // en una sola llamada — si llamamos una vez por imagen, cada una recibe índice 0
        // y se sobreescriben mutuamente.
        // Pasando $crematorioId, copiarACrematorio también registra cada imagen en
        // crematorio_imagenes con la categoría correcta (logo→procesada, galería→pendiente).
        $sqlImg = "SELECT * FROM solicitud_imagenes WHERE solicitud_id = :id ORDER BY tipo, orden";
        $stmtImg = $pdo->prepare($sqlImg);
        $stmtImg->execute([':id' => $id]);
        $imagenesSolicitud = $stmtImg->fetchAll();

        if (!empty($imagenesSolicitud)) {
            $porTipo = ['logo' => [], 'galeria' => []];
            foreach ($imagenesSolicitud as $img) {
                if (isset($porTipo[$img['tipo']])) {
                    $porTipo[$img['tipo']][] = $img['ruta'];
                }
            }

            $logoRutas = !empty($porTipo['logo'])
                ? ImagenHelper::copiarACrematorio($porTipo['logo'], $slug, 'logo', $crematorioId)
                : [];
            if (!empty($porTipo['galeria'])) {
                ImagenHelper::copiarACrematorio($porTipo['galeria'], $slug, 'galeria', $crematorioId);
            }

            if (!empty($logoRutas)) {
                $sqlUpdLogo = "UPDATE crematorios SET logo = :ruta WHERE id = :id";
                $stmtUpdLogo = $pdo->prepare($sqlUpdLogo);
                $stmtUpdLogo->execute([':ruta' => $logoRutas[0], ':id' => $crematorioId]);
            }
        }

        $pdo->commit();

        // Notificar al admin si quedan imágenes pendientes de análisis LLM
        $totalPendiente = (int) $pdo->query(
            "SELECT COUNT(*) FROM crematorio_imagenes WHERE estado_llm = 'pendiente'"
        )->fetchColumn();
        if ($totalPendiente > 0) {
            ImagenHelper::notificarAdminImagenesPendientes($totalPendiente);
        }

        echo json_encode([
            'ok' => true,
            'mensaje' => 'Solicitud aprobada correctamente',
            'crematorio_id' => $crematorioId,
            'slug' => $slug
        ]);

    } elseif ($accion === 'rechazar') {
        // ═══════════════════════════════════════════════════════════════════
        // RECHAZAR SOLICITUD (pendiente → rechazada)
        // ═══════════════════════════════════════════════════════════════════

        $sql = "UPDATE solicitudes_registro SET
                    estado = 'rechazada',
                    motivo_rechazo = :motivo,
                    moderado_por = :admin_id,
                    moderado_en = NOW()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':motivo'   => $motivo ?: null,
            ':admin_id' => $admin['id'],
            ':id'       => $id
        ]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'mensaje' => 'Solicitud rechazada'
        ]);

    } elseif ($accion === 'reevaluar') {
        // ═══════════════════════════════════════════════════════════════════
        // REEVALUAR SOLICITUD (rechazada → pendiente)
        // ═══════════════════════════════════════════════════════════════════
        // Vuelve a la cola limpia: borra marca de moderación y motivo.
        $sql = "UPDATE solicitudes_registro SET
                    estado = 'pendiente',
                    moderado_por = NULL,
                    moderado_en = NULL,
                    motivo_rechazo = NULL
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'mensaje' => 'Solicitud devuelta a pendiente'
        ]);

    } else {
        // ═══════════════════════════════════════════════════════════════════
        // ELIMINAR SOLICITUD DEFINITIVAMENTE (solo desde rechazada)
        // ═══════════════════════════════════════════════════════════════════
        // Borra fila + cascada de imágenes (archivos físicos + filas).
        // Si por algún motivo tiene crematorio_id (no debería en rechazadas), NO se toca la ficha.

        // 1. Recolectar imágenes para borrado físico
        $stmtImgs = $pdo->prepare("SELECT id, ruta FROM solicitud_imagenes WHERE solicitud_id = :id");
        $stmtImgs->execute([':id' => $id]);
        $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

        $imagenesEliminadas = 0;
        foreach ($imgs as $img) {
            $rutaRel = str_replace('\\', '/', $img['ruta']);
            $rutaAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRel);
            if (is_file($rutaAbs)) {
                @unlink($rutaAbs);
            }
            $imagenesEliminadas++;
        }

        // 2. Borrar filas de solicitud_imagenes (debería caer por FK ON DELETE CASCADE igual,
        //    pero forzamos para garantizar)
        $pdo->prepare("DELETE FROM solicitud_imagenes WHERE solicitud_id = :id")
            ->execute([':id' => $id]);

        // 3. Borrar la solicitud
        $stmtDel = $pdo->prepare("DELETE FROM solicitudes_registro WHERE id = :id");
        $stmtDel->execute([':id' => $id]);

        $pdo->commit();

        $msj = 'Solicitud eliminada definitivamente';
        if ($imagenesEliminadas > 0) {
            $msj .= ' · ' . $imagenesEliminadas . ' imagen' . ($imagenesEliminadas === 1 ? '' : 'es') . ' borrada' . ($imagenesEliminadas === 1 ? '' : 's');
        }
        echo json_encode([
            'ok' => true,
            'mensaje' => $msj,
            'imagenes_eliminadas' => $imagenesEliminadas,
            'eliminada' => true
        ]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo json_encode(['ok' => false, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Error al procesar la solicitud']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
