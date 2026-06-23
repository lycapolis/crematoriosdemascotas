<?php
/**
 * AJAX — Procesar texto libre de un crematorio con Claude para sugerir estructura
 *
 * POST:
 *   - crematorio_id (int, requerido)
 *   - seccion (string, requerido): horarios | contenido | cobertura | servicios | seo
 *
 * Respuesta JSON:
 *   {
 *     ok: bool,
 *     seccion: string,
 *     interpretable: bool,
 *     notes: string|null,   // razón si no se pudo interpretar
 *     warnings: string[],   // avisos no fatales
 *     sugerencia: { ... },  // shape depende de la sección
 *     modelo_usado: string,
 *     error: string|null
 *   }
 *
 * Regla crítica (feedback_llm_dummy_detection): si el input es dummy/placeholder/lorem,
 * el LLM marca interpretable:false con motivo, NO inventa datos. La UI muestra el warning.
 */

// Garantizar que NUNCA salga HTML antes del JSON, ni siquiera warnings/fatals
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Trap global: cualquier fatal error → respuesta JSON limpia
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Error PHP fatal: ' . $err['message'] . ' en ' . basename($err['file']) . ':' . $err['line'],
        ]);
    }
});

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/completitud.php';

// Re-asegurar (config.php enciende display_errors en DEBUG_MODE)
ini_set('display_errors', '0');

requerirAutenticacion();

while (ob_get_level() > 0) { ob_end_clean(); }
ob_start();
header('Content-Type: application/json; charset=utf-8');

requierePermiso('ia');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$seccion      = trim($_POST['seccion'] ?? '');

$SECCIONES_VALIDAS = ['horarios', 'contenido', 'cobertura', 'servicios', 'seo', 'descripcion_avanzada', 'precios'];

if (!$crematorioId || !in_array($seccion, $SECCIONES_VALIDAS, true)) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Parámetros inválidos. crematorio_id requerido y seccion debe ser uno de: ' . implode(', ', $SECCIONES_VALIDAS),
    ]);
    exit;
}

$pdo = obtenerConexion();

// ─── Cargar crematorio + provincia ───────────────────────────────────────────

$stmt = $pdo->prepare("
    SELECT c.id, c.nombre, c.ciudad, c.codigo_postal,
           c.descripcion, c.comentarios_admin,
           c.direccion_completa, c.website, c.whatsapp,
           c.telefonos_json, c.emails_json, c.redes_json,
           c.horarios, c.horario_texto,
           c.texto_origen_json,
           c.zona_cobertura, c.ciudades_cobertura,
           c.meta_description_seo,
           c.cremacion_individual, c.cremacion_colectiva,
           c.recogida_domicilio, c.entrega_domicilio,
           c.atencion_24h, c.sala_velatoria, c.souvenires,
           c.urna, c.carta, c.molde,
           p.nombre AS provincia_nombre
    FROM crematorios c
    LEFT JOIN provincias p ON p.id = c.provincia_id
    WHERE c.id = :id
");
$stmt->execute([':id' => $crematorioId]);
$cr = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cr) {
    echo json_encode(['ok' => false, 'error' => 'Crematorio no encontrado']);
    exit;
}

// Si el crematorio vino de una solicitud, recuperar también servicios y horarios raw
$sol = null;
try {
    $stmtSol = $pdo->prepare("
        SELECT servicios, horarios AS horarios_raw
        FROM solicitudes_registro
        WHERE crematorio_id = :id AND estado = 'aprobada'
        ORDER BY moderado_en DESC LIMIT 1
    ");
    $stmtSol->execute([':id' => $crematorioId]);
    $fetched = $stmtSol->fetch(PDO::FETCH_ASSOC);
    if (is_array($fetched)) $sol = $fetched;
} catch (PDOException $e) {
    // La tabla podría no existir en algún entorno; no es crítico para el procesamiento
    $sol = null;
}

// ─── Gate server-side: descripción avanzada exige datos base ≥90% ───────────
// Mismo criterio que el front (includes/completitud.php). No saltable por URL:
// el front deshabilita el botón, pero igual revalidamos acá (costo de API +
// abuso). Mide SOLO el grupo 'input' (pct_ia) — Descripción/Meta SEO no cuentan.
if ($seccion === 'descripcion_avanzada') {
    $comp = completitudDesdeId($pdo, $crematorioId);
    if ($comp === null) {
        echo json_encode(['ok' => false, 'error' => 'Ficha no encontrada']);
        exit;
    }
    if ($comp['pct_ia'] < 90) {
        $faltan = $comp['faltan_ia'] ? ' Faltan: ' . implode(', ', $comp['faltan_ia']) . '.' : '';
        echo json_encode([
            'ok'    => false,
            'error' => 'La ficha necesita los datos base ≥90% completos para generar la descripción avanzada (actual ' . $comp['pct_ia'] . '%).' . $faltan,
        ]);
        exit;
    }
}

// ─── Dispatch por sección ───────────────────────────────────────────────────

$resultado = null;

try {
    switch ($seccion) {
        case 'horarios':   $resultado = procesarHorarios($cr, $sol);   break;
        case 'contenido':  $resultado = procesarContenido($cr, $sol);  break;
        case 'descripcion_avanzada': $resultado = procesarDescripcionAvanzada($cr, $sol); break;
        case 'cobertura':  $resultado = procesarCobertura($cr, $sol);  break;
        case 'servicios':  $resultado = procesarServicios($cr, $sol);  break;
        case 'seo':        $resultado = procesarSeo($cr, $sol);        break;
        case 'precios':    $resultado = procesarPrecios($cr, $sol);    break;
        default:
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Sección "' . $seccion . '" todavía no implementada en este endpoint.']);
            exit;
    }
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'seccion' => $seccion,
        'error' => 'Excepción procesando sección: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
    ]);
    exit;
}

// ─── Registrar en bitácora si la llamada IA se completó (interpretable o no) ─

if ($resultado['ok']) {
    registrarUsoIA($pdo, $crematorioId, $seccion, $resultado['modelo_usado']);
}

// Limpiar cualquier output que pudiera haberse colado durante el procesamiento
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

// ═══════════════════════════════════════════════════════════════════════════
// SECCIONES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Sección "horarios" — extrae horarios estructurados desde texto libre.
 */
function procesarHorarios(array $cr, ?array $sol): array
{
    // Construir contexto y entrada
    $contexto = $cr['nombre'];
    if ($cr['ciudad'])           $contexto .= ', ' . $cr['ciudad'];
    if ($cr['provincia_nombre']) $contexto .= ' (' . $cr['provincia_nombre'] . ')';

    $textoFuente = [];
    if (!empty($cr['descripcion']))      $textoFuente[] = "--- Descripción de la ficha ---\n" . $cr['descripcion'];
    if (!empty($cr['horario_texto']))    $textoFuente[] = "--- Nota de horarios actual ---\n"  . $cr['horario_texto'];
    if (!empty($cr['comentarios_admin']))$textoFuente[] = "--- Comentarios admin ---\n"        . $cr['comentarios_admin'];
    if (!empty($sol['horarios_raw']))    $textoFuente[] = "--- Horarios cargados por el negocio en el registro ---\n" . $sol['horarios_raw'];

    $textoEntrada = trim(implode("\n\n", $textoFuente));

    if ($textoEntrada === '') {
        return [
            'ok'             => true,
            'seccion'        => 'horarios',
            'interpretable'  => false,
            'notes'          => 'No hay texto fuente del que extraer horarios (descripción, nota, comentarios y registro están vacíos).',
            'warnings'       => [],
            'sugerencia'     => null,
            'modelo_usado'   => 'no_call',
            'error'          => null,
        ];
    }

    $prompt = <<<PROMPT
Eres un asistente que extrae horarios de atención de negocios desde texto libre en español.
Negocio: $contexto (es un crematorio de mascotas en España).

TEXTO FUENTE:
$textoEntrada

TAREA: extraer los horarios de atención estructurados.

REGLA CRÍTICA: si el texto fuente es placeholder / lorem ipsum / dummy / texto de prueba / NO contiene
información real sobre horarios → marca interpretable=false con motivo claro en "notes" y devuelve
sugerencia=null. NO INVENTES horarios. Es preferible no devolver nada antes que devolver datos falsos.

Si el texto sí contiene info sobre horarios pero es ambiguo en algún día (ej: "consultar"), marca ese día
como "no_especificado".

FORMATO DE RESPUESTA — devuelve SOLO JSON válido, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "horarios_canonical": {
    "presencial": {
      "agrupacion": "lv",
      "lunes":     {"estado": "abierto", "turnos": [["09:00","13:00"], ["16:00","20:00"]]},
      "martes":    {"estado": "abierto", "turnos": [["09:00","13:00"], ["16:00","20:00"]]},
      "miercoles": {"estado": "abierto", "turnos": [["09:00","13:00"], ["16:00","20:00"]]},
      "jueves":    {"estado": "abierto", "turnos": [["09:00","13:00"], ["16:00","20:00"]]},
      "viernes":   {"estado": "abierto", "turnos": [["09:00","13:00"]]},
      "sabado":    {"estado": "abierto", "turnos": [["10:00","14:00"]]},
      "domingo":   {"estado": "cerrado", "turnos": []}
    },
    "telefonica": {
      "activa": true,
      "modo": "24h",
      "telefono_sugerido": null,
      "horario": null
    },
    "nota_adicional": "Festivos con cita previa"
  },
  "horario_texto_resumen": "L-V 9:00-13:00 y 16:00-20:00, Sábados 10:00-14:00, Domingos cerrado. Atención telefónica 24h."
}

CONVENCIONES DEL JSON:
- "agrupacion" = "lv" si los 5 días laborables tienen el mismo horario; "individual" si difieren.
- "estado": "abierto" | "cerrado" | "24h" | "no_especificado".
- "turnos": array de [HH:MM, HH:MM]. Vacío si cerrado, 24h o no_especificado.
- "modo" telefónica: "24h" | "mismo_presencial" | "horario_propio". Si "horario_propio", llenar "horario" con la misma estructura que presencial.
- Si no se menciona atención telefónica, poner activa=false y los demás campos null.
- "telefono_sugerido": SOLO si el texto menciona explícitamente un número distinto al principal del negocio.

Recuerda: SOLO JSON, sin envoltorio ni explicación. Si no se puede interpretar, devolvé igual JSON con interpretable=false.
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 1500);

    if (!$resp['ok']) {
        return [
            'ok'            => false,
            'seccion'       => 'horarios',
            'interpretable' => false,
            'notes'         => null,
            'warnings'      => [],
            'sugerencia'    => null,
            'modelo_usado'  => $resp['modelo'],
            'error'         => 'Llamada a Claude falló: ' . $resp['error'],
        ];
    }

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) {
        return [
            'ok'            => false,
            'seccion'       => 'horarios',
            'interpretable' => false,
            'notes'         => null,
            'warnings'      => [],
            'sugerencia'    => null,
            'modelo_usado'  => $resp['modelo'],
            'error'         => 'La respuesta de Claude no contenía JSON válido. Texto recibido: ' . mb_substr($resp['texto'], 0, 200),
        ];
    }

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable && isset($parsed['horarios_canonical'])) {
        // Convertir formato canónico a formato del editor visual
        $sugerencia = [
            'horarios_editor'      => canonicalAEditor($parsed['horarios_canonical']),
            'horarios_canonical'   => $parsed['horarios_canonical'],
            'horario_texto_resumen'=> trim((string)($parsed['horario_texto_resumen'] ?? '')),
        ];
    }

    return [
        'ok'            => true,
        'seccion'       => 'horarios',
        'interpretable' => $interpretable,
        'notes'         => $notes,
        'warnings'      => $warnings,
        'sugerencia'    => $sugerencia,
        'modelo_usado'  => $resp['modelo'],
        'error'         => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// CONVERSIÓN canónico → formato del editor visual
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Convierte el formato canónico del LLM al formato que el editor JS de horarios
 * espera (mismo shape que se guarda en crematorios.horarios JSON).
 *
 * Canónico (LLM):                          Editor (DB):
 *   estado=abierto + turnos=[[a,b],[c,d]]  → "a-b y c-d"
 *   estado=cerrado                         → null
 *   estado=24h                             → "24h"
 *   estado=no_especificado                 → ""
 */
function canonicalAEditor(array $canon): array
{
    $estadoAStr = function(array $dia): ?string {
        $estado = $dia['estado'] ?? 'no_especificado';
        if ($estado === 'cerrado')         return null;
        if ($estado === '24h')             return '24h';
        if ($estado === 'no_especificado') return '';
        // abierto
        $turnos = array_values(array_filter(
            (array)($dia['turnos'] ?? []),
            fn($t) => is_array($t) && count($t) >= 2
        ));
        if (empty($turnos)) return '';
        return implode(' y ', array_map(fn($t) => $t[0] . '-' . $t[1], $turnos));
    };

    $out = [];

    // Presencial
    $pres = $canon['presencial'] ?? [];
    $agrup = $pres['agrupacion'] ?? 'lv';
    $presOut = [];

    if ($agrup === 'individual') {
        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes'] as $d) {
            if (isset($pres[$d])) $presOut[$d] = $estadoAStr($pres[$d]);
        }
    } else {
        // Tomar el lunes como referencia para "lv"
        if (isset($pres['lunes'])) $presOut['lv'] = $estadoAStr($pres['lunes']);
    }
    if (isset($pres['sabado']))  $presOut['s'] = $estadoAStr($pres['sabado']);
    if (isset($pres['domingo'])) $presOut['d'] = $estadoAStr($pres['domingo']);

    $out['presencial'] = $presOut;

    // Telefónica
    $tel = $canon['telefonica'] ?? [];
    if (!empty($tel['activa'])) {
        $telOut = [
            'activa'   => true,
            'modo'     => $tel['modo'] ?? '24h',
            'telefono' => '',  // el editor lo selecciona por dropdown; sugerido va en metadata
        ];
        if (($tel['modo'] ?? '') === 'horario_propio' && isset($tel['horario'])) {
            // Convertir el horario telefónico también
            $telPres = $tel['horario'];
            $telPresOut = [];
            $telAgrup = $telPres['agrupacion'] ?? 'lv';
            if ($telAgrup === 'individual') {
                foreach (['lunes','martes','miercoles','jueves','viernes'] as $d) {
                    if (isset($telPres[$d])) $telPresOut[$d] = $estadoAStr($telPres[$d]);
                }
            } else if (isset($telPres['lunes'])) {
                $telPresOut['lv'] = $estadoAStr($telPres['lunes']);
            }
            if (isset($telPres['sabado']))  $telPresOut['s'] = $estadoAStr($telPres['sabado']);
            if (isset($telPres['domingo'])) $telPresOut['d'] = $estadoAStr($telPres['domingo']);
            $telOut['modo']    = 'horario';  // el editor JS espera 'horario' no 'horario_propio'
            $telOut['horario'] = $telPresOut;
        } elseif (($tel['modo'] ?? '') === 'mismo_presencial') {
            $telOut['modo'] = 'presencial';  // alias del editor
        }
        $out['telefonica'] = $telOut;
    }

    if (!empty($canon['nota_adicional'])) {
        $out['nota'] = (string) $canon['nota_adicional'];
    }

    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════
// Helpers de armado de contexto y validación de fuente común a todas las secciones
// ═══════════════════════════════════════════════════════════════════════════

function construirContexto(array $cr): string
{
    $ctx = $cr['nombre'];
    if ($cr['ciudad'])           $ctx .= ', ' . $cr['ciudad'];
    if ($cr['provincia_nombre']) $ctx .= ' (' . $cr['provincia_nombre'] . ')';
    return $ctx;
}

function construirTextoFuente(array $cr, ?array $sol): string
{
    $partes = [];
    if (!empty($cr['descripcion']))       $partes[] = "--- Descripción de la ficha ---\n"   . $cr['descripcion'];
    if (!empty($cr['horario_texto']))     $partes[] = "--- Nota de horarios ---\n"          . $cr['horario_texto'];
    if (!empty($cr['comentarios_admin'])) $partes[] = "--- Comentarios internos admin ---\n". $cr['comentarios_admin'];
    if (!empty($sol['servicios']))        $partes[] = "--- Servicios cargados al registrar ---\n" . $sol['servicios'];
    if (!empty($sol['horarios_raw']))     $partes[] = "--- Horarios texto del registro ---\n"     . $sol['horarios_raw'];
    return trim(implode("\n\n", $partes));
}

function resultadoSinFuente(string $seccion): array
{
    return [
        'ok'             => true,
        'seccion'        => $seccion,
        'interpretable'  => false,
        'notes'          => 'No hay texto fuente del que extraer información (descripción, notas y registro están vacíos).',
        'warnings'       => [],
        'sugerencia'     => null,
        'modelo_usado'   => 'no_call',
        'error'          => null,
    ];
}

function resultadoErrorApi(string $seccion, array $resp): array
{
    return [
        'ok'            => false,
        'seccion'       => $seccion,
        'interpretable' => false,
        'notes'         => null,
        'warnings'      => [],
        'sugerencia'    => null,
        'modelo_usado'  => $resp['modelo'],
        'error'         => 'Llamada a Claude falló: ' . $resp['error'],
    ];
}

function resultadoJsonInvalido(string $seccion, array $resp): array
{
    return [
        'ok'            => false,
        'seccion'       => $seccion,
        'interpretable' => false,
        'notes'         => null,
        'warnings'      => [],
        'sugerencia'    => null,
        'modelo_usado'  => $resp['modelo'],
        'error'         => 'La respuesta de Claude no contenía JSON válido. Texto recibido: ' . mb_substr($resp['texto'], 0, 200),
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "contenido" — sugiere descripción mejorada (entrada en JSON multi-fuente)
// ═══════════════════════════════════════════════════════════════════════════

function procesarContenido(array $cr, ?array $sol): array
{
    $contexto = construirContexto($cr);
    $textoEntrada = construirTextoFuente($cr, $sol);
    if ($textoEntrada === '') return resultadoSinFuente('contenido');

    $prompt = <<<PROMPT
Eres un redactor SEO especializado en el sector de crematorios de mascotas en España.
Negocio: $contexto.

TEXTO FUENTE:
$textoEntrada

TAREA: redactar una descripción mejorada del negocio para su ficha pública. Tono empático, respetuoso,
honesto. SEO localizable: nombre del negocio, ciudad/provincia, servicios concretos. NO uses promesas
exageradas. Mínimo 200 palabras, máximo 500. Castellano (español).

REGLA CRÍTICA: si el texto fuente es placeholder / lorem ipsum / dummy / NO contiene información real
sobre el negocio → interpretable=false con motivo, sin "descripcion_sugerida". NO INVENTES datos
inexistentes (servicios, ubicaciones, valores) — solo reformulá lo que YA está en la fuente.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "descripcion_sugerida": "Texto completo de la descripción mejorada, sin saltos de línea extraños...",
  "razonamiento": "Breve nota (1-2 frases) sobre qué cambios principales hiciste"
}

Si interpretable=false, "descripcion_sugerida" debe ser null.
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 2000);
    if (!$resp['ok']) return resultadoErrorApi('contenido', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('contenido', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable && !empty($parsed['descripcion_sugerida'])) {
        $sugerencia = [
            'descripcion_sugerida' => trim((string) $parsed['descripcion_sugerida']),
            'razonamiento'         => trim((string) ($parsed['razonamiento'] ?? '')),
            'modelo'               => $resp['modelo'],
        ];
    }

    return [
        'ok' => true, 'seccion' => 'contenido', 'interpretable' => $interpretable,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "descripcion_avanzada" — descripción larga enriquecida (SEO + GEO).
// Gated en el front por completitud ≥90%. Vuelca TODA la data estructurada de
// la ficha en prosa optimizada para Google/Bing y para que LLMs (ChatGPT,
// Gemini) la citen. Devuelve el mismo shape que "contenido" → el front reusa
// iaAplicarContenido (entra como versión nueva de descripción, a revisar).
// ═══════════════════════════════════════════════════════════════════════════

function procesarDescripcionAvanzada(array $cr, ?array $sol): array
{
    // Modelo: Sonnet para contenido SEO público (calidad/estructura).
    // Si la API key no lo soporta, llamarClaudeApi devuelve error legible y
    // basta cambiar este string por 'claude-haiku-4-5-20251001'.
    $MODELO = 'claude-sonnet-4-5-20250929';

    $contexto = construirContexto($cr);

    // ── Servicios (booleans → prosa) ──
    $servDefs = [
        'cremacion_individual' => 'cremación individual',
        'cremacion_colectiva'  => 'cremación colectiva',
        'recogida_domicilio'   => 'recogida a domicilio',
        'entrega_domicilio'    => 'entrega de cenizas a domicilio',
        'atencion_24h'         => 'atención 24 horas',
        'sala_velatoria'       => 'sala velatoria / velatorio',
        'souvenires'           => 'recuerdos y souvenirs',
        'urna'                 => 'urna incluida',
        'carta'                => 'carta o nota de condolencia',
        'molde'                => 'molde de huella',
    ];
    $ofrece = []; $noOfrece = [];
    foreach ($servDefs as $k => $lbl) {
        $v = $cr[$k] ?? null;
        if ($v === null || $v === '') continue;
        if ((int) $v === 1) $ofrece[] = $lbl; else $noOfrece[] = $lbl;
    }

    // ── Contacto visible (JSON multi-fuente) ──
    $decodeArr = function ($json) {
        if (empty($json)) return [];
        $d = json_decode($json, true);
        return is_array($d) ? $d : [];
    };
    $tels = array_filter($decodeArr($cr['telefonos_json'] ?? ''), fn($t) => ($t['visible'] ?? true) !== false);
    $mails = array_filter($decodeArr($cr['emails_json'] ?? ''),   fn($e) => ($e['visible'] ?? true) !== false);
    $redes = array_filter($decodeArr($cr['redes_json'] ?? ''),    fn($r) => ($r['visible'] ?? true) !== false && !empty($r['url']));
    $canales = [];
    if (!empty($cr['whatsapp'])) $canales[] = 'WhatsApp ' . $cr['whatsapp'];
    foreach ($tels  as $t) if (!empty($t['numero'])) $canales[] = 'teléfono ' . $t['numero'];
    foreach ($mails as $e) if (!empty($e['email']))  $canales[] = 'email ' . $e['email'];
    if (!empty($cr['website'])) $canales[] = 'web ' . $cr['website'];
    foreach ($redes as $r) $canales[] = ($r['red'] ?? 'red') . ' (' . $r['url'] . ')';

    // ── Bloque de datos estructurados ──
    $D = [];
    $D[] = 'Nombre: ' . $cr['nombre'];
    $loc = trim(($cr['direccion_completa'] ?? '') . ' ' . ($cr['ciudad'] ?? '') . ' ' . ($cr['codigo_postal'] ?? ''));
    if ($loc !== '')                    $D[] = 'Ubicación: ' . $loc . ($cr['provincia_nombre'] ? ' (' . $cr['provincia_nombre'] . ')' : '');
    if ($ofrece)                        $D[] = 'Servicios que ofrece: ' . implode(', ', $ofrece) . '.';
    if ($noOfrece)                      $D[] = 'Servicios que NO ofrece: ' . implode(', ', $noOfrece) . '.';
    if (!empty($cr['horario_texto']))   $D[] = 'Horarios: ' . $cr['horario_texto'];
    if (!empty($cr['zona_cobertura']))  $D[] = 'Zona de cobertura: ' . $cr['zona_cobertura'];
    if (!empty($cr['ciudades_cobertura'])) $D[] = 'Ciudades cubiertas: ' . $cr['ciudades_cobertura'];
    if ($canales)                       $D[] = 'Canales de contacto: ' . implode('; ', $canales) . '.';
    if (!empty($cr['descripcion']))     $D[] = 'Descripción existente (referencia, podés mejorarla): ' . $cr['descripcion'];
    if (!empty($cr['comentarios_admin'])) $D[] = 'Notas internas (NO citar literal, solo contexto): ' . $cr['comentarios_admin'];
    $datos = implode("\n", $D);

    $prompt = <<<PROMPT
Eres un redactor experto en SEO y en GEO (Generative Engine Optimization: optimización para que
asistentes de IA como ChatGPT, Gemini o Perplexity encuentren, entiendan y CITEN esta ficha cuando
un usuario investiga servicios de cremación de mascotas).

Negocio: $contexto (crematorio / servicio funerario de mascotas en España).

DATOS ESTRUCTURADOS DE LA FICHA (única fuente de verdad — NO inventes nada que no esté acá):
$datos

TAREA: redactá una DESCRIPCIÓN PÚBLICA AVANZADA, larga y bien estructurada, que integre TODA la
información de arriba en prosa natural. Es deseable que sea exhaustiva y algo redundante con los
campos de la ficha: el objetivo es máxima cobertura semántica para buscadores y para LLMs.

REQUISITOS:
- Castellano (España). Tono empático, cálido y respetuoso (es un servicio para mascotas fallecidas),
  pero informativo y concreto. Sin promesas exageradas ni clichés vacíos.
- 350 a 650 palabras.
- Estructura escaneable: 3 a 5 párrafos, cada uno con un subtítulo corto en su propia línea
  (ej. "Servicios", "Zona de cobertura", "Horarios y contacto", "Por qué elegir <negocio>").
  Marcá CADA subtítulo envolviéndolo en doble asterisco para que salga en negrita en la ficha,
  exactamente así: **Servicios** (en su propia línea, seguido de un salto de línea y su párrafo).
  NO uses ningún otro markdown (ni #, ni viñetas, ni listas, ni *cursiva*): SOLO **subtítulos**.
- Mencioná de forma natural: nombre del negocio, ciudad y provincia, servicios concretos que ofrece,
  zona/ciudades de cobertura, horarios y formas de contacto. Repetí el nombre + la localidad un par
  de veces de forma natural (señal SEO local).
- Pensado para GEO: frases claras y autocontenidas, datos verificables, que un asistente de IA pueda
  extraer y atribuir a este negocio.
- NO inventes servicios, precios, certificaciones, años de experiencia ni datos que no estén en los
  DATOS ESTRUCTURADOS. Si algo no está, simplemente no lo menciones.

REGLA: si los datos estructurados son claramente placeholder/dummy/incoherentes (ej. "prueba 999",
lorem ipsum) → interpretable=false con motivo y descripcion_sugerida=null. NO maquilles basura.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "descripcion_sugerida": "Descripción avanzada completa. Usá \\n para separar párrafos/subtítulos.",
  "razonamiento": "1-2 frases: qué datos integraste y enfoque SEO/GEO aplicado"
}

Si interpretable=false, "descripcion_sugerida" debe ser null.
PROMPT;

    $resp = llamarClaudeApi($prompt, $MODELO, 2500);
    if (!$resp['ok']) return resultadoErrorApi('descripcion_avanzada', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('descripcion_avanzada', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes'] ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable && !empty($parsed['descripcion_sugerida'])) {
        $sugerencia = [
            'descripcion_sugerida' => trim((string) $parsed['descripcion_sugerida']),
            'razonamiento'         => trim((string) ($parsed['razonamiento'] ?? '')),
            'modelo'               => $resp['modelo'],
        ];
    }

    return [
        'ok' => true, 'seccion' => 'descripcion_avanzada', 'interpretable' => $interpretable,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "cobertura" — sugiere zona y ciudades de cobertura
// ═══════════════════════════════════════════════════════════════════════════

function procesarCobertura(array $cr, ?array $sol): array
{
    $contexto = construirContexto($cr);
    $textoEntrada = construirTextoFuente($cr, $sol);
    if ($textoEntrada === '') return resultadoSinFuente('cobertura');

    $zonaActual    = trim((string) ($cr['zona_cobertura']     ?? ''));
    $ciudadesActual= trim((string) ($cr['ciudades_cobertura'] ?? ''));

    $prompt = <<<PROMPT
Eres un asistente que extrae zonas geográficas de cobertura de un negocio en España desde texto libre.
Negocio: $contexto (crematorio de mascotas).

TEXTO FUENTE:
$textoEntrada

ZONA ACTUAL (referencia): "$zonaActual"
CIUDADES ACTUALES (referencia): "$ciudadesActual"

TAREA: detectar provincias / comunidades autónomas y ciudades / barrios que el negocio cubre.

INSTRUCCIONES:
- "zona_cobertura" → array de provincias o comunidades autónomas en España, con su nombre oficial en
  castellano (ej: "Madrid", "Cataluña", "Comunidad Valenciana", "Andalucía"). Sin duplicar.
- "ciudades_cobertura" → array de ciudades, barrios o distritos mencionados. Mantené capitalización
  natural ("Pozuelo de Alarcón", "Chamartín"). Sin duplicar.
- Si la zona/ciudad ya aparece en la referencia, igual la incluís en el array — el array es la lista
  COMPLETA que querés que quede, no el delta.

REGLA CRÍTICA: si el texto NO menciona ninguna zona geográfica → interpretable=false con motivo.
NO INVENTES provincias/ciudades. Si el texto solo menciona "Madrid" sin más, devolvé solo Madrid.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "zona_cobertura":     ["Madrid", "Cataluña", "Comunidad Valenciana"],
  "ciudades_cobertura": ["Madrid", "Pozuelo de Alarcón", "Majadahonda", "Barcelona", "Valencia"]
}

Si interpretable=false, devolvé los arrays vacíos.
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 800);
    if (!$resp['ok']) return resultadoErrorApi('cobertura', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('cobertura', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable) {
        $zonas    = is_array($parsed['zona_cobertura']     ?? null) ? $parsed['zona_cobertura']     : [];
        $ciudades = is_array($parsed['ciudades_cobertura'] ?? null) ? $parsed['ciudades_cobertura'] : [];
        // Sanitizar — strings, sin vacíos, sin duplicados
        $zonas    = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)$s), $zonas))));
        $ciudades = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)$s), $ciudades))));
        if (!empty($zonas) || !empty($ciudades)) {
            $sugerencia = [
                'zona_cobertura'     => $zonas,
                'ciudades_cobertura' => $ciudades,
            ];
        }
    }

    return [
        'ok' => true, 'seccion' => 'cobertura', 'interpretable' => $interpretable && $sugerencia !== null,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "servicios" — sugiere los 10 booleanos de servicios
// ═══════════════════════════════════════════════════════════════════════════

function procesarServicios(array $cr, ?array $sol): array
{
    $contexto = construirContexto($cr);
    $textoEntrada = construirTextoFuente($cr, $sol);
    if ($textoEntrada === '') return resultadoSinFuente('servicios');

    $prompt = <<<PROMPT
Eres un asistente que clasifica qué servicios ofrece un crematorio de mascotas en España desde texto libre.
Negocio: $contexto.

TEXTO FUENTE:
$textoEntrada

TAREA: determinar para cada uno de los 10 servicios si el negocio LO OFRECE, NO LO OFRECE, o el texto
NO LO MENCIONA (no inventar). Devolvé true / false / null respectivamente.

SERVICIOS:
- "cremacion_individual": cremación uno-a-uno, las cenizas se devuelven a la familia.
- "cremacion_colectiva":  cremación grupal, sin devolución de cenizas (o cenizas mezcladas).
- "recogida_domicilio":   el negocio retira la mascota del domicilio del cliente.
- "entrega_domicilio":    el negocio lleva las cenizas al domicilio del cliente.
- "atencion_24h":         atención disponible 24 horas (todos los días del año).
- "sala_velatoria":       tienen una sala física para que la familia se despida.
- "souvenires":           ofrecen recuerdos / souvenirs / placas / objetos conmemorativos.
- "urna":                 ofrecen urna (incluida o adicional) para las cenizas.
- "carta":                ofrecen carta de condolencias / despedida.
- "molde":                ofrecen molde de huella de la mascota (recuerdo físico).

REGLAS CRÍTICAS — leer con atención:

1. Solo respondé **true** si el texto MENCIONA EXPLÍCITAMENTE que el negocio ofrece ese servicio
   (palabras concretas, no inferencias).

2. Solo respondé **false** si el texto MENCIONA EXPLÍCITAMENTE que el negocio NO ofrece ese servicio
   (ej: "no tenemos sala velatoria", "no hacemos recogida"). NO inferir false desde la ausencia
   del servicio en el texto.

3. Si el texto NO MENCIONA el servicio (ni positiva ni negativamente) → respondé **null**. Esto es
   lo más común y es lo correcto: null = "no sabemos, queda Sin Definir para que el admin verifique
   manualmente". Mejor null que false-positive en cualquier dirección.

4. **NO RAZONAR POR EXCLUSIÓN MUTUA.** Cada servicio se evalúa INDEPENDIENTEMENTE. Que el texto
   mencione un servicio NO implica que NO ofrezca otros. Ejemplos:
   - Texto: "Ofrecemos cremación individual" → cremacion_individual=true, cremacion_colectiva=null
     (NO false, aunque suene "exclusivo"; el negocio podría ofrecer colectiva sin mencionarlo).
   - Texto: "Recogemos en domicilio" → recogida_domicilio=true, entrega_domicilio=null
     (NO inferir false sobre entrega — quizás también la ofrecen, no se dice).
   - Texto: "Atención telefónica 24h" → atencion_24h=true (la atención telefónica 24h cuenta como
     atención 24h del negocio).

5. Si el texto entero es dummy / placeholder / sin información real → interpretable=false con
   motivo. Los 10 campos deben ser null en ese caso.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "servicios": {
    "cremacion_individual": true,
    "cremacion_colectiva":  true,
    "recogida_domicilio":   null,
    "entrega_domicilio":    null,
    "atencion_24h":         true,
    "sala_velatoria":       null,
    "souvenires":           null,
    "urna":                 null,
    "carta":                null,
    "molde":                null
  }
}
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 700);
    if (!$resp['ok']) return resultadoErrorApi('servicios', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('servicios', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $SERVICIOS_KEYS = [
        'cremacion_individual', 'cremacion_colectiva', 'recogida_domicilio',
        'entrega_domicilio', 'atencion_24h', 'sala_velatoria',
        'souvenires', 'urna', 'carta', 'molde',
    ];

    $sugerencia = null;
    if ($interpretable && is_array($parsed['servicios'] ?? null)) {
        $servicios = [];
        $algunoSet = false;
        foreach ($SERVICIOS_KEYS as $k) {
            $v = $parsed['servicios'][$k] ?? null;
            // Normalizar: true/false/null. Cualquier otro valor → null.
            if ($v === true || $v === false) {
                $servicios[$k] = $v;
                $algunoSet = true;
            } else {
                $servicios[$k] = null;
            }
        }
        if ($algunoSet) $sugerencia = ['servicios' => $servicios];
    }

    return [
        'ok' => true, 'seccion' => 'servicios', 'interpretable' => $interpretable && $sugerencia !== null,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "seo" — sugiere meta description (entrada en JSON multi-fuente)
// ═══════════════════════════════════════════════════════════════════════════

function procesarSeo(array $cr, ?array $sol): array
{
    $contexto = construirContexto($cr);
    $textoEntrada = construirTextoFuente($cr, $sol);
    if ($textoEntrada === '') return resultadoSinFuente('seo');

    $prompt = <<<PROMPT
Eres un experto SEO que escribe meta descriptions para fichas de directorio en España.
Negocio: $contexto (crematorio de mascotas).

TEXTO FUENTE:
$textoEntrada

TAREA: redactar UNA meta description optimizada para SERP de Google. Castellano.

REGLAS:
- Longitud: 140 a 220 caracteres (la BD acepta hasta 220, idealmente 150-170).
- Incluir nombre del negocio + ciudad/provincia + servicio principal + diferenciador.
- Tono empático pero claro, sin clickbait.
- Sin punto final si el texto rompe el límite. Sin emojis. Sin signos de exclamación.
- Sin frases vacías tipo "Tu mejor opción". Concretitud > marketing.

REGLA CRÍTICA: si el texto fuente es placeholder / dummy → interpretable=false con motivo.
NO INVENTES servicios o ubicaciones.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "meta_description_sugerida": "Texto de 140-220 caracteres, sin saltos de línea",
  "longitud": 165
}

"longitud" es el conteo de caracteres de la meta. Debe coincidir con la longitud real del texto.
Si interpretable=false, "meta_description_sugerida" y "longitud" deben ser null.
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 600);
    if (!$resp['ok']) return resultadoErrorApi('seo', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('seo', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable && !empty($parsed['meta_description_sugerida'])) {
        $meta = trim((string) $parsed['meta_description_sugerida']);
        // Recorte defensivo si la IA se pasó de 220
        if (mb_strlen($meta) > 220) {
            $meta = mb_substr($meta, 0, 220);
            $warnings[] = 'La meta description superaba 220 caracteres y fue recortada.';
        }
        $sugerencia = [
            'meta_description_sugerida' => $meta,
            'longitud'                  => mb_strlen($meta),
            'modelo'                    => $resp['modelo'],
        ];
    }

    return [
        'ok' => true, 'seccion' => 'seo', 'interpretable' => $interpretable && $sugerencia !== null,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// SECCIÓN "precios" — estructura texto libre de precios en ítems tipados.
// Fuente principal: crematorios.precios_texto (texto crudo de importación o
// carga admin). Como respaldo mira descripción/comentarios por si hay precios.
// La sugerencia entra al editor de precios del admin (NO auto-publica).
// ═══════════════════════════════════════════════════════════════════════════

function procesarPrecios(array $cr, ?array $sol): array
{
    $contexto = construirContexto($cr);

    // Fuente: el texto original de precios (texto_origen_json.precios_texto),
    // que viene del registro del negocio o de la importación masiva.
    // Como respaldo mira descripción/comentarios por si mencionan precios.
    $origen = [];
    if (!empty($cr['texto_origen_json'])) {
        $decoded = json_decode($cr['texto_origen_json'], true);
        if (is_array($decoded)) $origen = $decoded;
    }
    $partes = [];
    if (!empty($origen['precios_texto']))  $partes[] = "--- Precios cargados por el negocio ---\n" . $origen['precios_texto'];
    if (!empty($cr['descripcion']))        $partes[] = "--- Descripción de la ficha (puede mencionar precios) ---\n" . $cr['descripcion'];
    if (!empty($cr['comentarios_admin']))  $partes[] = "--- Comentarios internos admin ---\n" . $cr['comentarios_admin'];
    $textoEntrada = trim(implode("\n\n", $partes));

    if ($textoEntrada === '') {
        return [
            'ok' => true, 'seccion' => 'precios', 'interpretable' => false,
            'notes' => 'No hay texto original de precios. Si el negocio no compartió precios, cargalos a mano con "+ Agregar precio".',
            'warnings' => [], 'sugerencia' => null, 'modelo_usado' => 'no_call', 'error' => null,
        ];
    }

    $simbolo = defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : '€';

    $prompt = <<<PROMPT
Eres un asistente que estructura listas de precios de un crematorio de mascotas en España desde texto libre.
Negocio: $contexto. Moneda: $simbolo.

TEXTO FUENTE:
$textoEntrada

TAREA: extraer una lista de ítems de precio estructurados. Cada ítem es un servicio/producto con su precio.

CADA ÍTEM tiene un "tipo" según cómo se exprese el precio:
- "fijo"   → precio único y cerrado. Llenar "min" con el monto. "max" = null.
- "desde"  → precio mínimo orientativo ("desde X", "a partir de X"). Llenar "min". "max" = null.
- "rango"  → precio entre dos valores ("de X a Y", "X-Y"). Llenar "min" y "max".
- "custom" → sin monto numérico (ej. "consultar", "según peso"). "min" y "max" = null;
             la info va en "nombre"/"descripcion".

REGLA CRÍTICA: si el texto es placeholder / lorem ipsum / dummy / no contiene precios reales →
interpretable=false con motivo en "notes" y "precios"=[]. NO INVENTES precios ni montos.
Solo extraé lo que el texto dice explícitamente. Si un monto es ambiguo, usá "custom".

- "nombre": el servicio o producto (ej. "Cremación individual perro pequeño"). Obligatorio.
- "descripcion": detalle si lo hay (ej. "hasta 10 kg, incluye urna básica"). Opcional, "" si no hay.
- "min"/"max": números sin símbolo de moneda ni separadores de miles. null si no aplica.

FORMATO DE RESPUESTA — SOLO JSON, sin texto adicional ni code-fence:

{
  "interpretable": true,
  "notes": null,
  "warnings": [],
  "precios": [
    {"tipo": "desde",  "nombre": "Cremación individual perro pequeño", "descripcion": "hasta 10 kg", "min": 120, "max": null},
    {"tipo": "rango",  "nombre": "Cremación individual perro grande",  "descripcion": "", "min": 180, "max": 260},
    {"tipo": "fijo",   "nombre": "Cremación colectiva", "descripcion": "sin devolución de cenizas", "min": 60, "max": null},
    {"tipo": "custom", "nombre": "Recogida a domicilio", "descripcion": "consultar según zona", "min": null, "max": null}
  ]
}

Si interpretable=false, "precios" debe ser [].
PROMPT;

    $resp = llamarClaudeApi($prompt, 'claude-haiku-4-5-20251001', 1500);
    if (!$resp['ok']) return resultadoErrorApi('precios', $resp);

    $parsed = extraerJsonDeRespuesta($resp['texto']);
    if ($parsed === null) return resultadoJsonInvalido('precios', $resp);

    $interpretable = !empty($parsed['interpretable']);
    $notes         = $parsed['notes']    ?? null;
    $warnings      = is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [];

    $sugerencia = null;
    if ($interpretable && is_array($parsed['precios'] ?? null)) {
        $tiposValidos = ['fijo', 'desde', 'rango', 'custom'];
        $items = [];
        foreach ($parsed['precios'] as $p) {
            if (!is_array($p)) continue;
            $nombre = trim((string)($p['nombre'] ?? ''));
            if ($nombre === '') continue; // sin nombre no es ítem válido
            $tipo = in_array(($p['tipo'] ?? ''), $tiposValidos, true) ? $p['tipo'] : 'custom';
            $min  = (isset($p['min']) && is_numeric($p['min'])) ? (float)$p['min'] : null;
            $max  = (isset($p['max']) && is_numeric($p['max'])) ? (float)$p['max'] : null;
            $items[] = [
                'tipo'        => $tipo,
                'nombre'      => $nombre,
                'descripcion' => trim((string)($p['descripcion'] ?? '')),
                'min'         => $min,
                'max'         => $max,
            ];
        }
        if (!empty($items)) $sugerencia = ['precios' => $items];
    }

    return [
        'ok' => true, 'seccion' => 'precios', 'interpretable' => $interpretable && $sugerencia !== null,
        'notes' => $notes, 'warnings' => $warnings, 'sugerencia' => $sugerencia,
        'modelo_usado' => $resp['modelo'], 'error' => null,
    ];
}
