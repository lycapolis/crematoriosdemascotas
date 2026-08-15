<?php
/**
 * ═══════════════════════════════════════════════════════════
 * EDITAR CREMATORIO - PANEL ADMIN
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/completitud.php';

requerirAutenticacion();

$pdo = obtenerConexion();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: fichas-negocios.php'); exit; }

// ─── Cargar crematorio ───────────────────────────────────────────────────────

$stmt = $pdo->prepare("SELECT c.*, p.nombre AS provincia_nombre FROM crematorios c LEFT JOIN provincias p ON p.id = c.provincia_id WHERE c.id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cr) { header('Location: fichas-negocios.php'); exit; }

// ─── Imágenes ────────────────────────────────────────────────────────────────

$imgStmt = $pdo->prepare("SELECT * FROM crematorio_imagenes WHERE crematorio_id = :id ORDER BY tipo, orden_negocio ASC, id ASC");
$imgStmt->execute([':id' => $id]);
$imagenes       = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
$imagenesLocales = array_values(array_filter($imagenes, fn($i) => !str_starts_with($i['ruta'], 'http') && $i['tipo'] !== 'cliente'));
$imagenesCliente = array_values(array_filter($imagenes, fn($i) => !str_starts_with($i['ruta'], 'http') && $i['tipo'] === 'cliente'));
$imagenesURL     = array_values(array_filter($imagenes, fn($i) =>  str_starts_with($i['ruta'], 'http')));
$_imgFlags   = flagsImagenesFicha($imagenes); // lógica única (compartida con el gate server-side)
$tieneImg     = $_imgFlags['img'];
$tieneLogo    = $_imgFlags['logo'];
$tienePortada = $_imgFlags['portada'];

// ─── Reseñas de clientes vinculadas a esta ficha ─────────────────────────────
$stmtResenas = $pdo->prepare("
    SELECT id, nombre, email, comentario, calificacion, estado, fuente,
           created_at, moderado_en, motivo_rechazo, es_spam
    FROM resenas
    WHERE crematorio_id = :id
    ORDER BY FIELD(estado, 'pendiente', 'aprobada', 'rechazada'), created_at DESC
");
$stmtResenas->execute([':id' => $id]);
$fichaResenas = $stmtResenas->fetchAll(PDO::FETCH_ASSOC);

// Agrupar imágenes de cliente por reseña (solo las que tienen resena_id)
$fichaImagenesPorResena = [];
foreach ($imagenesCliente as $imgCli) {
    if (empty($imgCli['resena_id'])) continue;
    $rid = (int) $imgCli['resena_id'];
    if (!isset($fichaImagenesPorResena[$rid])) $fichaImagenesPorResena[$rid] = [];
    $fichaImagenesPorResena[$rid][] = $imgCli;
}

// Stats reseñas (para el header de la sección)
$fichaResenasStats = ['total' => count($fichaResenas), 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0, 'spam' => 0];
foreach ($fichaResenas as $r) {
    $key = $r['estado'] . 's'; // pendiente→pendientes, aprobada→aprobadas, rechazada→rechazadas
    if (isset($fichaResenasStats[$key])) $fichaResenasStats[$key]++;
    if (!empty($r['es_spam'])) $fichaResenasStats['spam']++;
}

// ─── Completitud ─────────────────────────────────────────────────────────────
// Definición declarativa única en includes/completitud.php (front + gate IA
// server-side miden idéntico). $pct = general (panel); $pctIa = solo datos
// base (grupo 'input') → habilita la Descripción avanzada con IA (≥90%).

$checks       = evaluarCompletitud($cr, $tieneImg, $tieneLogo);
$_comp        = resumenCompletitud($checks);
$completados  = $_comp['completados'];
$total_checks = $_comp['total'];
$pct          = $_comp['pct'];
$pctIa        = $_comp['pct_ia'];
$faltanIa     = $_comp['faltan_ia'];
$checkLabels  = $_comp['labels'];

// ─── Guardar ─────────────────────────────────────────────────────────────────

$mensaje = isset($_GET['saved']) ? 'Guardado correctamente' : '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $boolVal = function(string $key): ?int {
        $v = $_POST[$key] ?? '';
        return $v === '' ? null : (int) $v;
    };

    // Las 6 flats sincronizables (telefono, telefono_clientes, email, email_clientes,
    // descripcion, meta_description_seo) NO se escriben en este UPDATE.
    // Las deriva sincronizarCamposFlat() del JSON tras el UPDATE, dentro de la misma
    // transacción. JSON = source of truth, flat = cache.
    $datos = [
        ':id'                   => $id,
        ':nombre'               => trim($_POST['nombre'] ?? $cr['nombre']),
        ':tier'                 => trim($_POST['tier'] ?? $cr['tier']),
        // estado = source of truth; activo es cache (1 solo si estado='activa')
        ':estado'               => in_array(($_POST['estado'] ?? 'activa'), ['activa','pausada','cerrada','archivada'], true)
                                       ? $_POST['estado'] : 'activa',
        ':activo'               => (($_POST['estado'] ?? 'activa') === 'activa') ? 1 : 0,
        ':verificado'           => isset($_POST['verificado']) ? 1 : 0,
        ':destacado'            => isset($_POST['destacado'])  ? 1 : 0,
        ':website'              => trim($_POST['website'] ?? ''),
        ':whatsapp'             => trim($_POST['whatsapp'] ?? ''),
        ':telefonos_json'       => trim($_POST['telefonos_json'] ?? '') ?: null,
        ':emails_json'          => trim($_POST['emails_json']    ?? '') ?: null,
        ':redes_json'           => trim($_POST['redes_json']     ?? '') ?: null,
        ':descripciones_json'   => trim($_POST['descripciones_json'] ?? '') ?: null,
        ':metas_json'           => trim($_POST['metas_json']          ?? '') ?: null,
        ':precios_json'         => trim($_POST['precios_json']        ?? '') ?: null,
        ':mensajes_whatsapp_json' => trim($_POST['mensajes_whatsapp_json'] ?? '') ?: null,
        ':booking_link'         => trim($_POST['booking_link'] ?? ''),
        ':google_maps_url'      => trim($_POST['google_maps_url'] ?? ''),
        ':google_place_id'      => trim($_POST['google_place_id'] ?? '') ?: null,
        ':direccion_completa'   => trim($_POST['direccion_completa'] ?? ''),
        ':ciudad'               => trim($_POST['ciudad'] ?? ''),
        ':codigo_postal'        => trim($_POST['codigo_postal'] ?? ''),
        ':latitud'              => trim($_POST['latitud'] ?? '') ?: null,
        ':longitud'             => trim($_POST['longitud'] ?? '') ?: null,
        ':horarios'             => trim($_POST['horarios'] ?? '') ?: null,
        ':horario_texto'        => trim($_POST['horario_texto'] ?? '') ?: null,
        ':zona_cobertura'       => trim($_POST['zona_cobertura'] ?? '') ?: null,
        ':ciudades_cobertura'   => trim($_POST['ciudades_cobertura'] ?? '') ?: null,
        ':comentarios_admin'    => trim($_POST['comentarios_admin'] ?? '') ?: null,
        ':cremacion_individual' => $boolVal('cremacion_individual'),
        ':cremacion_colectiva'  => $boolVal('cremacion_colectiva'),
        ':recogida_domicilio'   => $boolVal('recogida_domicilio'),
        ':entrega_domicilio'    => $boolVal('entrega_domicilio'),
        ':atencion_24h'         => $boolVal('atencion_24h'),
        ':sala_velatoria'       => $boolVal('sala_velatoria'),
        ':souvenires'           => $boolVal('souvenires'),
        ':urna'                 => $boolVal('urna'),
        ':carta'                => $boolVal('carta'),
        ':molde'                => $boolVal('molde'),
        ':recibe_notif_leads'   => isset($_POST['recibe_notif_leads']) ? 1 : 0,
        ':email_notif_leads'    => trim($_POST['email_notif_leads'] ?? '') ?: null,
    ];

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE crematorios SET
                    nombre               = :nombre,
                    tier                 = :tier,
                    estado               = :estado,
                    activo               = :activo,
                    verificado           = :verificado,
                    destacado            = :destacado,
                    website              = :website,
                    whatsapp             = :whatsapp,
                    telefonos_json       = :telefonos_json,
                    emails_json          = :emails_json,
                    redes_json           = :redes_json,
                    descripciones_json   = :descripciones_json,
                    metas_json           = :metas_json,
                    precios_json         = :precios_json,
                    mensajes_whatsapp_json = :mensajes_whatsapp_json,
                    booking_link         = :booking_link,
                    google_maps_url      = :google_maps_url,
                    google_place_id      = :google_place_id,
                    direccion_completa   = :direccion_completa,
                    ciudad               = :ciudad,
                    codigo_postal        = :codigo_postal,
                    latitud              = :latitud,
                    longitud             = :longitud,
                    horarios             = :horarios,
                    horario_texto        = :horario_texto,
                    zona_cobertura       = :zona_cobertura,
                    ciudades_cobertura   = :ciudades_cobertura,
                    comentarios_admin    = :comentarios_admin,
                    cremacion_individual = :cremacion_individual,
                    cremacion_colectiva  = :cremacion_colectiva,
                    recogida_domicilio   = :recogida_domicilio,
                    entrega_domicilio    = :entrega_domicilio,
                    atencion_24h         = :atencion_24h,
                    sala_velatoria       = :sala_velatoria,
                    souvenires           = :souvenires,
                    urna                 = :urna,
                    carta                = :carta,
                    molde                = :molde,
                    recibe_notif_leads   = :recibe_notif_leads,
                    email_notif_leads    = :email_notif_leads
                WHERE id = :id";

        $upd = $pdo->prepare($sql);
        $upd->execute($datos);

        // Sincronizar las 6 flats desde el JSON recién guardado.
        sincronizarCamposFlat($pdo, $id);

        // Si la versión activa del mensaje WhatsApp es "auto", regenerarla con
        // los datos recién guardados (teléfono, precio, rating, etc. pueden
        // haber cambiado en este mismo submit). No toca versiones manuales/IA.
        regenerarMensajeWhatsappAutoSiCorresponde($pdo, $id);

        $pdo->commit();

        // POST → Redirect → GET para evitar reenvío al refrescar
        header('Location: ' . BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $id . '&saved=1');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Error al guardar: ' . (defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'error de BD');
    }
}

// ─── Migración automática flat → JSON (primera vez que se abre el form) ──────

// Contactos: asegurar campo origen en entradas existentes
$ensureOrigen = function(array $arr, string $default = 'manual'): array {
    foreach ($arr as &$item) {
        if (!isset($item['origen'])) $item['origen'] = $default;
    }
    return $arr;
};

$telefonosJson = $cr['telefonos_json'] ?? '';
if (empty($telefonosJson)) {
    $tMig = [];
    if (!empty($cr['telefono']))          $tMig[] = ['id'=>'p1','origen'=>'manual','tipo'=>'principal','label'=>'Teléfono principal','numero'=>$cr['telefono'],'visible'=>true];
    if (!empty($cr['telefono_clientes'])) $tMig[] = ['id'=>'p2','origen'=>'manual','tipo'=>'clientes','label'=>'Atención a clientes','numero'=>$cr['telefono_clientes'],'visible'=>true];
    $telefonosJson = json_encode($tMig, JSON_UNESCAPED_UNICODE);
} else {
    $tArr = json_decode($telefonosJson, true) ?: [];
    $telefonosJson = json_encode($ensureOrigen($tArr), JSON_UNESCAPED_UNICODE);
}

$emailsJson = $cr['emails_json'] ?? '';
if (empty($emailsJson)) {
    $eMig = [];
    if (!empty($cr['email']))            $eMig[] = ['id'=>'e1','origen'=>'manual','tipo'=>'general','label'=>'General / Contacto','email'=>$cr['email'],'visible'=>true];
    if (!empty($cr['email_clientes']))   $eMig[] = ['id'=>'e2','origen'=>'manual','tipo'=>'clientes','label'=>'Atención a clientes','email'=>$cr['email_clientes'],'visible'=>true];
    $emailsJson = json_encode($eMig, JSON_UNESCAPED_UNICODE);
} else {
    $eArr = json_decode($emailsJson, true) ?: [];
    $emailsJson = json_encode($ensureOrigen($eArr), JSON_UNESCAPED_UNICODE);
}

$redesJson = !empty($cr['redes_json']) ? $cr['redes_json'] : json_encode(['modo'=>'iconos','entries'=>[]], JSON_UNESCAPED_UNICODE);

// Precios: lista de ítems. Sin migración flat (campo nuevo).
$preciosJson = !empty($cr['precios_json']) ? $cr['precios_json'] : '[]';

// Fuentes de texto: migración flat → JSON con versiones candidatas
$descJson = $cr['descripciones_json'] ?? '';
if (empty($descJson)) {
    $dMig = [];
    if (!empty($cr['descripcion'])) {
        $dMig[] = ['id'=>'d1','origen'=>'manual','valor'=>$cr['descripcion'],'activo'=>true,'fecha'=>date('Y-m-d')];
    }
    $descJson = json_encode($dMig, JSON_UNESCAPED_UNICODE);
}

$metaJson = $cr['metas_json'] ?? '';
if (empty($metaJson)) {
    $mMig = [];
    if (!empty($cr['meta_description_seo'])) {
        $mMig[] = ['id'=>'m1','origen'=>'manual','valor'=>$cr['meta_description_seo'],'activo'=>true,'fecha'=>date('Y-m-d')];
    }
    $metaJson = json_encode($mMig, JSON_UNESCAPED_UNICODE);
}

// Mensaje WhatsApp: si todavía no hay ninguna versión, generar la "auto"
// (plantilla determinística, sin IA) a partir de los datos ya cargados de la
// ficha. Se mantiene al día en cada guardado — ver
// regenerarMensajeWhatsappAutoSiCorresponde() en funciones.php.
$whatsappJson = $cr['mensajes_whatsapp_json'] ?? '';
if (empty($whatsappJson)) {
    $wMig = [];
    $wMsgInicial = generarMensajeWhatsappAuto($cr);
    if ($wMsgInicial !== '') {
        $wMig[] = ['id' => 'w1', 'origen' => 'auto', 'valor' => $wMsgInicial, 'activo' => true, 'fecha' => date('Y-m-d')];
    }
    $whatsappJson = json_encode($wMig, JSON_UNESCAPED_UNICODE);
}

// Bitácora IA (cuándo fue procesada cada sección con IA y con qué modelo)
$iaLog = obtenerLogIA($pdo, $id);

// Texto original (textos crudos que dieron origen a la ficha — referencia inmutable)
$textoOrigen = [];
if (!empty($cr['texto_origen_json'])) {
    $decoded = json_decode($cr['texto_origen_json'], true);
    if (is_array($decoded)) $textoOrigen = $decoded;
}

// Estado del mensaje del cliente (leído / respondido / solucionado)
$msjEstado = ['leido' => false, 'respondido' => false, 'solucionado' => false];
if (!empty($cr['mensaje_cliente_estado_json'])) {
    $decoded = json_decode($cr['mensaje_cliente_estado_json'], true);
    if (is_array($decoded)) {
        foreach (['leido', 'respondido', 'solucionado'] as $k) {
            if (isset($decoded[$k])) $msjEstado[$k] = (bool) $decoded[$k];
        }
    }
}

function etiquetaFuenteOrigen(string $fuente): array {
    return match (true) {
        str_starts_with($fuente, 'seed_')  => ['icono' => '📦', 'lbl' => 'Importado del semillado inicial', 'color' => '#1d4ed8', 'bg' => '#dbeafe'],
        $fuente === 'manual_negocio'       => ['icono' => '🏢', 'lbl' => 'Cargado por el negocio al registrarse', 'color' => '#15803d', 'bg' => '#dcfce7'],
        $fuente === 'manual_admin'         => ['icono' => '✍️', 'lbl' => 'Cargado manualmente por un admin', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
        default                            => ['icono' => '📋', 'lbl' => 'Fuente: ' . $fuente, 'color' => '#6b7280', 'bg' => '#f3f4f6'],
    };
}

/**
 * Renderiza un mini-panel "Texto original" inline en una sección del form.
 * Solo se renderiza si el campo correspondiente tiene contenido. Colapsado por default.
 */
function renderTextoOrigenBloque(string $key, array $textoOrigen, string $titulo): void {
    $val = $textoOrigen[$key] ?? null;
    if (empty($val)) return;

    $fuente = $textoOrigen['fuente'] ?? 'desconocida';
    $fecha  = $textoOrigen['fecha']  ?? '—';
    $lblFte = etiquetaFuenteOrigen($fuente);
    $txtId  = 'txt-origen-' . $key;
    ?>
    <details class="txt-origen-mini">
        <summary>
            <span class="txt-origen-mini-titulo"><i data-lucide="file-text" style="width:14px;height:14px;"></i> Texto original — <?php echo htmlspecialchars($titulo); ?></span>
            <span class="txt-origen-fuente" style="background:<?php echo $lblFte['bg']; ?>; color:<?php echo $lblFte['color']; ?>;">
                <?php echo $lblFte['icono']; ?> <?php echo htmlspecialchars($lblFte['lbl']); ?>
                <span style="opacity:.65;">· <?php echo htmlspecialchars($fecha); ?></span>
            </span>
            <span class="txt-origen-mini-toggle">
                <span class="lbl-expandir">Expandir</span><span class="lbl-colapsar">Colapsar</span>
                <i data-lucide="chevron-down" style="width:13px;height:13px;"></i>
            </span>
        </summary>
        <div class="txt-origen-mini-body">
            <button type="button" class="txt-origen-copiar" onclick="copiarTextoOrigen('<?php echo $txtId; ?>', this)"><i data-lucide="copy" style="width:13px;height:13px;"></i> Copiar</button>
            <pre id="<?php echo $txtId; ?>" class="txt-origen-contenido"><?php echo htmlspecialchars($val); ?></pre>
        </div>
    </details>
    <?php
}

// Helper para renderizar el bloque del Asistente IA en cada sección
function renderBotonIA(string $seccion, array $iaLog, string $label = 'Interpretar con IA', ?string $bloqueoMsg = null): void {
    $entry  = $iaLog[$seccion] ?? null;
    $fecha  = $entry['fecha']  ?? null;
    $modelo = $entry['modelo'] ?? null;
    $bloq   = ($bloqueoMsg !== null && $bloqueoMsg !== '');
    ?>
    <div class="ia-bloque">
        <button type="button" class="btn-ia"
                <?php echo $bloq ? 'disabled title="' . htmlspecialchars($bloqueoMsg) . '"' : 'onclick="iaProcesarSeccion(\'' . htmlspecialchars($seccion) . '\', this)"'; ?>>
            <i data-lucide="sparkles" class="icono" style="width:14px;height:14px;"></i> <?php echo htmlspecialchars($label); ?>
        </button>
        <span class="ia-ultima <?php echo ($bloq || !$fecha) ? 'nunca' : ''; ?>" id="ia-ultima-<?php echo htmlspecialchars($seccion); ?>">
            <?php if ($bloq): ?>
                <?php echo htmlspecialchars($bloqueoMsg); ?>
            <?php elseif ($fecha): ?>
                Última: <?php echo htmlspecialchars($fecha); ?> · <?php echo htmlspecialchars($modelo ?? ''); ?>
            <?php else: ?>
                Nunca procesada con IA
            <?php endif; ?>
        </span>
    </div>
    <div class="ia-feedback" id="ia-feedback-<?php echo htmlspecialchars($seccion); ?>"></div>
    <?php
}

$titulo_pagina = 'Editar: ' . htmlspecialchars($cr['nombre']) . ' - Admin';
include 'header.php';

// Helper para select de booleanos
function selectBool(string $name, $valor): string {
    $opts = ['' => 'Sin definir', '1' => 'Sí', '0' => 'No'];
    $html = "<select name=\"$name\" class=\"field__select field__select--enhanced\" data-ts-search=\"off\" data-placeholder=\"Sin definir\">";
    foreach ($opts as $v => $label) {
        // PHP auto-castea las keys '1' y '0' del array a int — comparar siempre como string
        $vStr = (string) $v;
        $sel = ($valor === null && $vStr === '')
            || ($valor !== null && (string)$valor === $vStr)
            ? ' selected' : '';
        $html .= "<option value=\"$vStr\"$sel>$label</option>";
    }
    $html .= '</select>';
    return $html;
}
?>

<style>
/* Header sticky de la ficha en edición — JUSTO debajo del admin-header (que es sticky top:0 z-index:100) */
.ficha-sticky {
    position: sticky;
    top: 56px; /* fallback; el JS ajusta a la altura real del admin-header */
    z-index: 99;
    background: var(--admin-superficie);
    border-bottom: 1px solid var(--admin-linea);
    box-shadow: var(--admin-sombra-suave);
}
.ficha-sticky__inner {
    max-width: 1200px; /* = .admin-page → bordes alineados con la caja de 1152 */
    margin: 0 auto;
    padding: .6rem var(--espacio-cuatro);
    display: flex;
    align-items: center;
    gap: var(--espacio-tres);
}
/* .ficha-sticky__back ahora usa .boton dos pequeno (sistema unificado) — solo
   ajustamos que sea cuadrado para el icono solo */
.ficha-sticky .ficha-sticky__back {
    flex-shrink: 0;
    padding-left: .55rem;
    padding-right: .55rem;
}
.ficha-sticky__info {
    flex: 1;
    min-width: 0;
    line-height: 1.25;
}
.ficha-sticky__nombre {
    font-size: var(--admin-h4);
    font-weight: 700;
    color: var(--admin-tinta-fuerte);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ficha-sticky__meta {
    font-size: var(--admin-body-sm);
    color: var(--admin-tinta-suave);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Tag input */
.tag-box {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    padding: .4rem .5rem;
    border: 1px solid var(--admin-linea-fuerte);
    border-radius: var(--admin-r-sm);
    background: var(--admin-superficie);
    cursor: text;
    min-height: 2.4rem;
    align-items: center;
    transition: border-color 140ms ease-out, box-shadow 140ms ease-out;
}
.tag-box:focus-within { border-color: var(--admin-brand); box-shadow: var(--admin-sombra-foco); outline: none; }
.tag-pill {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: var(--admin-brand-soft);
    color: var(--admin-brand-hover);
    border-radius: var(--admin-r-pill);
    padding: .2rem .6rem;
    font-size: var(--admin-caption);
    font-weight: 600;
    white-space: nowrap;
}
.tag-pill button {
    background: none;
    border: none;
    color: var(--admin-brand-hover);
    cursor: pointer;
    font-size: .75rem;
    padding: 0;
    line-height: 1;
    opacity: .7;
}
.tag-pill button:hover { opacity: 1; }
.tag-box input[type=text] {
    border: none;
    outline: none;
    background: transparent;
    font-size: var(--admin-body-sm);
    min-width: 120px;
    flex: 1;
    padding: .1rem .2rem;
    font-family: var(--fuente-texto);
    color: var(--admin-tinta-fuerte);
}
/* Tag input */
.completitud-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .6rem;
    border-radius: var(--admin-r-pill);
    font-size: var(--admin-caption);
    font-weight: 600;
}
.completitud-badge.ok    { background: var(--admin-tone-exito-bg); color: var(--admin-tone-exito-fg); }
.completitud-badge.falta { background: var(--admin-tone-error-bg); color: var(--admin-tone-error-fg); }
/* Custom autocomplete dropdown */
.tag-input-wrap { position: relative; }
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--admin-superficie);
    border: 1px solid var(--admin-linea-fuerte);
    border-top: none;
    border-radius: 0 0 var(--admin-r-md) var(--admin-r-md);
    max-height: 30vh;
    overflow-y: auto;
    z-index: 200;
    box-shadow: var(--admin-sombra-alta);
}
.autocomplete-dropdown .ac-item {
    padding: .45rem .75rem;
    font-size: var(--admin-body-sm);
    cursor: pointer;
    color: var(--admin-tinta);
}
.autocomplete-dropdown .ac-item:hover,
.autocomplete-dropdown .ac-item.active {
    background: var(--admin-brand);
    color: #fff;
}
/* Asistente IA — botón y feedback por sección */
/* Asistente IA — acento púrpura deliberado (identidad de la feature IA,
   se mantiene distinto de la paleta cálida a propósito). Tokens
   estructurales (radio, fuente) sí migrados a --admin-*. */
.ia-bloque {
    display: flex;
    align-items: center;
    gap: var(--espacio-dos);
    flex-wrap: wrap;
    margin-bottom: var(--espacio-dos);
    padding: .4rem .65rem;
    background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
    border-radius: var(--admin-r-sm);
    font-size: var(--admin-body-sm);
}
.ia-bloque button.btn-ia {
    background: #7c3aed; color: #fff; border: none;
    padding: .4rem .85rem; border-radius: var(--admin-r-sm);
    font-weight: 600; font-size: var(--admin-body-sm); cursor: pointer;
    display: inline-flex; align-items: center; gap: .35rem;
}
.ia-bloque button.btn-ia:hover { background: #6d28d9; }
.ia-bloque button.btn-ia:disabled { background: #c4b5fd; cursor: wait; }
.ia-bloque .ia-ultima { color: #6b21a8; opacity: .8; font-size: var(--admin-body-sm); }
.ia-bloque .ia-ultima.nunca { color: #6b21a8; opacity: .85; font-style: italic; }
/* Mini-panel "Texto original" — distribuido por sección */
.txt-origen-mini {
    margin-bottom: var(--espacio-dos);
    border: 1px solid var(--admin-linea);
    border-radius: var(--admin-r-sm);
    background: var(--admin-papel-alt);
    font-size: var(--admin-body-sm);
}
.txt-origen-mini summary {
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .4rem .6rem;
    flex-wrap: wrap;
}
.txt-origen-mini summary::-webkit-details-marker { display: none; }
.txt-origen-mini-titulo {
    font-weight: 600;
    color: var(--admin-tinta-suave);
}
.txt-origen-fuente {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .12rem .55rem;
    border-radius: var(--admin-r-pill);
    font-size: var(--admin-caption);
    font-weight: 600;
}
.txt-origen-mini-toggle {
    margin-left: auto;
    color: var(--admin-tinta-tenue);
    font-size: var(--admin-caption);
    display: inline-flex;
    align-items: center;
    gap: .3rem;
}
.txt-origen-mini-toggle svg { transition: transform 180ms ease-out; }
.txt-origen-mini[open] .txt-origen-mini-toggle svg { transform: rotate(180deg); }
.txt-origen-mini-toggle .lbl-colapsar { display: none; }
.txt-origen-mini[open] .txt-origen-mini-toggle .lbl-expandir { display: none; }
.txt-origen-mini[open] .txt-origen-mini-toggle .lbl-colapsar { display: inline; }
.txt-origen-mini-body {
    padding: .5rem .6rem .65rem;
    border-top: 1px solid var(--admin-linea);
    position: relative;
}
.txt-origen-copiar {
    position: absolute;
    top: .55rem;
    right: .6rem;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--admin-papel-alt);
    border: 0;
    border-radius: var(--admin-r-sm);
    padding: .25rem .6rem;
    font-size: var(--admin-caption);
    font-weight: 600;
    cursor: pointer;
    color: var(--admin-tinta-suave);
    z-index: 1;
    transition: background 140ms ease-out, color 140ms ease-out;
}
.txt-origen-copiar:hover { background: var(--admin-brand-soft); color: var(--admin-brand-hover); }
.txt-origen-copiar.copiado { background: var(--admin-tone-exito-bg); color: var(--admin-tone-exito-fg); }
.txt-origen-contenido {
    margin: 0;
    padding: .55rem .7rem;
    padding-right: 5rem; /* hueco para el botón Copiar */
    font-family: var(--fuente-texto);
    font-size: var(--admin-body-sm);
    line-height: 1.5;
    color: var(--admin-tinta);
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 220px;
    overflow-y: auto;
    background: var(--admin-superficie);
    border-radius: var(--admin-r-sm);
    border: 1px solid var(--admin-linea);
}
.ia-feedback {
    margin-top: var(--espacio-dos);
    padding: .55rem .8rem;
    border-radius: var(--admin-r-sm);
    font-size: var(--admin-body-sm);
    line-height: 1.45;
    display: none;
}
.ia-feedback.ok    { background: var(--admin-tone-exito-bg);  border:1px solid var(--admin-tone-exito-bord);  color: var(--admin-tone-exito-fg); }
.ia-feedback.warn  { background: var(--admin-tone-alerta-bg); border:1px solid var(--admin-tone-alerta-bord); color: var(--admin-tone-alerta-fg); }
.ia-feedback.err   { background: var(--admin-tone-error-bg);  border:1px solid var(--admin-tone-error-bord);  color: var(--admin-tone-error-fg); }
.ia-feedback ul    { margin: .25rem 0 0 1.1rem; padding: 0; }
.sugerido-ia {
    background: #fef9c3 !important;
    border-color: #facc15 !important;
    box-shadow: 0 0 0 2px rgba(250, 204, 21, .3);
    transition: background .25s, box-shadow .25s;
}
@keyframes ia-spin { to { transform: rotate(360deg); } }
.ia-spinner {
    width: 12px; height: 12px;
    border: 2px solid rgba(255,255,255,.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: ia-spin .8s linear infinite;
    display: inline-block;
}
/* Card de sección de la ficha (reemplaza .tarjeta simple) — chrome de esta página */
.ficha-card {
    background: var(--admin-superficie);
    border: 1px solid var(--admin-linea);
    border-radius: var(--admin-r-md);
    box-shadow: var(--admin-sombra-suave);
    padding: var(--espacio-cuatro);
}
.ficha-card__title {
    font-family: var(--fuente-texto);
    font-size: var(--admin-h4);
    font-weight: 700;
    color: var(--admin-tinta-fuerte);
    margin: 0 0 var(--espacio-cuatro);
    display: flex;
    align-items: center;
    gap: var(--espacio-dos);
    flex-wrap: wrap;
}
.ficha-card__title .icono { width: 18px; height: 18px; color: var(--admin-brand); }
</style>

<!-- Header sticky de la ficha en edición — debajo del admin-header -->
<div class="ficha-sticky">
    <div class="ficha-sticky__inner">
        <a href="fichas-negocios.php" class="boton dos pequeno ficha-sticky__back" title="Volver al listado" aria-label="Volver al listado">
            <i data-lucide="arrow-left" class="icono" style="width:16px;height:16px;"></i>
        </a>
        <div class="ficha-sticky__info">
            <div style="display:flex; align-items:center; gap:.6rem; min-width:0;">
                <div class="ficha-sticky__nombre" style="min-width:0;"><?php echo htmlspecialchars($cr['nombre']); ?></div>
                <!-- Estado persistente "cambios sin guardar" — JS lo muestra/oculta.
                     Va justo a la derecha del nombre (barra sticky, siempre visible). -->
                <span id="dirty-bar" style="display:none; flex-shrink:0; font-size:var(--admin-caption); font-weight:600;
                             color:var(--admin-tone-alerta-fg); background:var(--admin-tone-alerta-bg);
                             padding:.22rem .7rem; border-radius:var(--admin-r-pill); white-space:nowrap;">
                    ● Cambios sin guardar
                </span>
            </div>
            <div class="ficha-sticky__meta">
                <?php echo htmlspecialchars($cr['ciudad'] ?? ''); ?><?php if ($cr['provincia_nombre']): ?> · <?php echo htmlspecialchars($cr['provincia_nombre']); ?><?php endif; ?>
                · Plan <?php echo htmlspecialchars($cr['tier']); ?>
                · ID <?php echo $id; ?>
            </div>
        </div>
        <a href="<?php echo BASE_URL . '/' . $cr['slug']; ?>" target="_blank" class="boton dos pequeno" style="flex-shrink:0;">
            <i data-lucide="external-link" class="icono" style="width:14px;height:14px;"></i>
            Ver ficha
        </a>
    </div>
</div>

<?php
    $barColor = $pct >= 80
        ? 'var(--admin-tone-exito-fg)'
        : ($pct >= 50 ? 'var(--admin-tone-alerta-fg)' : 'var(--admin-tone-error-fg)');
    ?>
<!-- ROLLBACK a "todo 760": en la línea de abajo cambiar  class="admin-page"
     por  class="admin-page admin-page--narrow" ; borrar el <div class="ficha-layout">
     y su </div> de cierre; borrar el <div class="ficha-layout__main"> / </div>;
     mover el <aside class="ficha-layout__aside"> (panel Completitud + acciones)
     a ANTES del <form>; y quitar la clase  ficha-layout__gallery  de las 3
     secciones de galerías. Toda la lógica PHP queda igual. -->
<div class="admin-page">
<div class="ficha-layout">
    <div class="ficha-layout__main">

    <!-- Formulario -->
    <form id="main-form" method="POST" style="display:flex; flex-direction:column; gap:var(--espacio-cuatro);">

        <!-- ── Datos básicos ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="building-2" class="icono"></i>
                Datos básicos
            </h2>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:var(--espacio-tres);">
                <div class="field" style="grid-column:1/-1; margin-bottom:0;">
                    <label class="field__label" for="nombre">Nombre del negocio <span class="field__req">requerido</span></label>
                    <input type="text" id="nombre" name="nombre" class="field__input" required
                           value="<?php echo htmlspecialchars($cr['nombre']); ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="tier">Plan</label>
                    <select id="tier" name="tier" class="field__select field__select--enhanced" data-ts-search="off">
                        <?php foreach (['01' => 'P1 — Básico', '02' => 'P2 — Con imágenes', '03' => 'P3', '04' => 'P4', '05' => 'P5'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $cr['tier'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="estado">Estado de la ficha</label>
                    <select id="estado" name="estado" class="field__select field__select--enhanced" data-ts-search="off">
                        <?php
                        $estadoActual = $cr['estado'] ?? 'activa';
                        foreach ([
                            'activa'    => 'Activa — visible al público',
                            'pausada'   => 'Pausada — oculta temporalmente',
                            'cerrada'   => 'Cerrada — visible con aviso',
                            'archivada' => 'Archivada — oculta (recuperable)',
                        ] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $estadoActual === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; gap:.6rem; justify-content:flex-end;">
                    <label class="field__opcion">
                        <input type="checkbox" class="field__switch" name="verificado" value="1" <?php echo $cr['verificado'] ? 'checked' : ''; ?>>
                        Verificado
                    </label>
                    <label class="field__opcion">
                        <input type="checkbox" class="field__switch" name="destacado" value="1" <?php echo $cr['destacado'] ? 'checked' : ''; ?>>
                        Destacado
                    </label>
                </div>
            </div>
        </section>

        <!-- ── Reputación online ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="star" class="icono"></i>
                Reputación online
            </h2>
            <?php
            $rat      = $cr['rating'];
            $tieneRat = !($rat === null || $rat === '' || (float)$rat <= 0);
            $ratF     = $tieneRat ? (float)$rat : 0;
            $revT     = (int) ($cr['reviews_total'] ?? 0);
            $revLink  = trim((string)($cr['reviews_link'] ?? '')) ?: trim((string)($cr['location_reviews_link'] ?? ''));
            $lleno    = (int) round($ratF);
            ?>
            <div style="display:flex; align-items:center; gap:var(--espacio-cuatro); flex-wrap:wrap;">
                <div style="text-align:center; min-width:120px;">
                    <div style="font-size:var(--admin-h2); font-weight:700; line-height:1.1; color:var(--admin-tinta-fuerte);">
                        <?php echo $tieneRat ? number_format($ratF, 1) : '—'; ?>
                    </div>
                    <div style="margin-top:.35rem; display:flex; gap:.12rem; justify-content:center;">
                        <?php for ($i = 1; $i <= 5; $i++): $on = $tieneRat && $i <= $lleno; ?>
                        <i data-lucide="star" style="width:17px;height:17px; color:<?php echo $on ? '#f59e0b' : 'var(--admin-tinta-tenue)'; ?>; fill:<?php echo $on ? '#f59e0b' : 'none'; ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <div style="margin-top:.4rem; font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);">
                        <?php echo $revT; ?> reseña<?php echo $revT === 1 ? '' : 's'; ?> en Google
                    </div>
                    <?php if ($revLink): ?>
                    <a href="<?php echo htmlspecialchars($revLink); ?>" target="_blank" rel="noopener"
                       style="font-size:var(--admin-caption); color:var(--admin-brand); text-decoration:underline; display:inline-flex; align-items:center; gap:.25rem; margin-top:.3rem;">
                        Ver en Google <i data-lucide="external-link" style="width:11px;height:11px;"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($revT > 0): ?>
                <div style="flex:1; min-width:220px; display:flex; flex-direction:column; gap:.3rem; padding-top:.2rem;">
                    <?php for ($s = 5; $s >= 1; $s--): $cnt = (int) ($cr['reviews_' . $s] ?? 0); $pr = $revT > 0 ? round($cnt / $revT * 100) : 0; ?>
                    <div style="display:flex; align-items:center; gap:.5rem; font-size:var(--admin-caption); color:var(--admin-tinta-suave);">
                        <span style="width:38px; text-align:right; font-variant-numeric:tabular-nums;"><?php echo $s; ?> <i data-lucide="star" style="width:10px;height:10px;color:#f59e0b;fill:#f59e0b;vertical-align:-1px;"></i></span>
                        <span style="flex:1; height:7px; background:var(--admin-papel-alt); border-radius:var(--admin-r-pill); overflow:hidden;">
                            <span style="display:block; height:100%; width:<?php echo $pr; ?>%; background:#f59e0b;"></span>
                        </span>
                        <span style="width:40px; text-align:right; font-variant-numeric:tabular-nums;"><?php echo $cnt; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php elseif (!$tieneRat): ?>
                <div style="flex:1; min-width:200px; align-self:center; font-size:var(--admin-body-sm); color:var(--admin-tinta-tenue); font-style:italic;">
                    Sin valoración ni reseñas en Google todavía.
                </div>
                <?php endif; ?>
            </div>

            <!-- Google Business Profile + Lead B2B -->
            <?php
            $gv         = $cr['google_verificado'] ?? null;
            $gvEstado   = ($gv === null || $gv === '') ? 'sin_datos' : ((int)$gv === 1 ? 'verificado' : 'no_verificado');
            $verificado = ($gvEstado === 'verificado');

            // Lead B2B (Lycapolis SEO local) = perfil VERIFICADO (dueño contactable
            // que ya invirtió) PERO descuidado. Umbrales ajustables.
            $UMBRAL_RESENAS = 100;
            $UMBRAL_RATING  = 3.5;
            $rev    = $revT;
            $ratRaw = $cr['rating'];
            $sinRating  = !$tieneRat;
            $sinWeb     = (trim((string)($cr['website'] ?? '')) === '');
            $sinDescG   = (trim((string)($cr['descripcion_google'] ?? '')) === '');
            $señales = [];
            if ($rev < $UMBRAL_RESENAS)              $señales[] = 'pocas reseñas (' . $rev . ' &lt; ' . $UMBRAL_RESENAS . ')';
            if ($sinRating)                          $señales[] = 'sin rating en Google';
            elseif ((float)$ratRaw < $UMBRAL_RATING) $señales[] = 'rating bajo (' . htmlspecialchars((string)$ratRaw) . ' &lt; ' . $UMBRAL_RATING . ')';
            if ($sinWeb)                             $señales[] = 'sin sitio web';
            if ($sinDescG)                           $señales[] = 'perfil pobre (sin descripción de Google)';
            $esLeadB2B = $verificado && !empty($señales);
            ?>
            <div style="margin-top:var(--espacio-tres); padding-top:var(--espacio-tres); border-top:1px solid var(--admin-linea);">
                <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                    <span style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Google Business Profile:</span>
                    <?php if ($gvEstado === 'verificado'): ?>
                    <span class="admin-pill admin-pill--exito"><i data-lucide="badge-check" style="width:12px;height:12px;"></i> Verificado en Google</span>
                    <?php elseif ($gvEstado === 'no_verificado'): ?>
                    <span class="admin-pill"><i data-lucide="circle-slash" style="width:12px;height:12px;"></i> Sin verificar</span>
                    <?php else: ?>
                    <span class="admin-pill"><i data-lucide="help-circle" style="width:12px;height:12px;"></i> Sin datos de Google</span>
                    <?php endif; ?>
                    <?php if ($esLeadB2B): ?>
                    <span class="admin-pill admin-pill--alerta"><i data-lucide="target" style="width:12px;height:12px;"></i> Lead B2B</span>
                    <?php endif; ?>
                </div>
                <?php if ($esLeadB2B): ?>
                <div class="admin-banner admin-banner--warning" style="margin-top:var(--espacio-dos); margin-bottom:0;">
                    <i data-lucide="briefcase" class="icono admin-banner__icon"></i>
                    <div class="admin-banner__content">
                        <strong>Oportunidad B2B (Lycapolis):</strong> perfil de Google <strong>verificado pero descuidado</strong> → candidato para SEO local / gestión de perfil.
                        <span style="display:block; margin-top:.25rem; font-size:var(--admin-caption); color:var(--admin-tinta-suave);">Señales: <?php echo implode(' · ', $señales); ?>.</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Enlaces directos a Google (solo si hay google_place_id) -->
            <?php
            $pid = trim((string)($cr['google_place_id'] ?? ''));
            if ($pid !== ''):
                $enlacesGoogle = [
                    ['titulo' => 'Ficha en Google Maps', 'icono' => 'map-pin',       'url' => 'https://www.google.com/maps/place/?q=place_id:' . $pid],
                    ['titulo' => 'Ver reseñas',          'icono' => 'message-square','url' => 'https://search.google.com/local/reviews?placeid=' . $pid],
                    ['titulo' => 'Escribir reseña',      'icono' => 'edit-3',        'url' => 'https://search.google.com/local/writereview?placeid=' . $pid],
                    ['titulo' => 'Cómo llegar',          'icono' => 'navigation',    'url' => 'https://www.google.com/maps/dir/?api=1&destination=&destination_place_id=' . $pid],
                ];
            ?>
            <div style="margin-top:var(--espacio-tres); padding-top:var(--espacio-tres); border-top:1px solid var(--admin-linea);">
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600; margin-bottom:var(--espacio-dos);">
                    Enlaces directos a Google
                </div>
                <div style="display:flex; flex-direction:column; gap:.5rem;">
                    <?php foreach ($enlacesGoogle as $e): ?>
                    <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:nowrap;">
                        <div style="width:180px; flex-shrink:0; font-size:var(--admin-body-sm); color:var(--admin-tinta-fuerte); display:flex; align-items:center; gap:.4rem;">
                            <i data-lucide="<?php echo $e['icono']; ?>" style="width:14px;height:14px;color:var(--admin-tinta-suave);"></i>
                            <?php echo $e['titulo']; ?>
                        </div>
                        <input type="text" readonly value="<?php echo htmlspecialchars($e['url']); ?>"
                               style="flex:1; min-width:0; background:var(--admin-papel-alt); border:1px solid var(--admin-linea); border-radius:var(--admin-r-sm); padding:.35rem .6rem; font-family:monospace; font-size:var(--admin-caption); color:var(--admin-tinta-suave);"
                               onclick="this.select()" title="Click para seleccionar y copiar (Ctrl+C)">
                        <a href="<?php echo htmlspecialchars($e['url']); ?>" target="_blank" rel="noopener"
                           class="boton dos pequeno" title="Abrir en nueva pestaña" style="flex-shrink:0;">
                            <i data-lucide="external-link" style="width:14px;height:14px;"></i> Abrir
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- ── Contacto ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="phone" class="icono"></i>
                Contacto
                <?php if (!$checks['telefono'] || !$checks['email']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Incompleto</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completo</span>
                <?php endif; ?>
            </h2>

            <!-- Inputs JSON (gestionados por JS) -->
            <input type="hidden" id="telefonos-json" name="telefonos_json" value="<?php echo htmlspecialchars($telefonosJson); ?>">
            <input type="hidden" id="emails-json"    name="emails_json"    value="<?php echo htmlspecialchars($emailsJson); ?>">
            <input type="hidden" id="redes-json"     name="redes_json"     value="<?php echo htmlspecialchars($redesJson); ?>">
            <!-- Legacy flat — auto-populate por JS antes del submit -->
            <input type="hidden" id="tel-legacy"     name="telefono"           value="<?php echo htmlspecialchars($cr['telefono'] ?? ''); ?>">
            <input type="hidden" id="tel-cli-legacy" name="telefono_clientes"  value="<?php echo htmlspecialchars($cr['telefono_clientes'] ?? ''); ?>">
            <input type="hidden" id="email-legacy"   name="email"              value="<?php echo htmlspecialchars($cr['email'] ?? ''); ?>">
            <input type="hidden" id="email-cli-legacy" name="email_clientes"   value="<?php echo htmlspecialchars($cr['email_clientes'] ?? ''); ?>">

            <!-- Campos fijos (no en el editor dinámico) -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); margin-bottom:var(--espacio-cuatro);">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="whatsapp">WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" class="field__input" placeholder="+34 600 000 000"
                           value="<?php echo htmlspecialchars($cr['whatsapp'] ?? ''); ?>">
                    <span class="field__hint">Puede coincidir con cualquier teléfono</span>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="website">Sitio web <?php echo !$checks['website'] ? '<span style="color:var(--admin-tone-error-fg)">○</span>' : '<span style="color:var(--admin-tone-exito-fg)">✓</span>'; ?></label>
                    <input type="url" id="website" name="website" class="field__input"
                           value="<?php echo htmlspecialchars($cr['website'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="booking_link">Link de reserva</label>
                    <input type="url" id="booking_link" name="booking_link" class="field__input"
                           value="<?php echo htmlspecialchars($cr['booking_link'] ?? ''); ?>">
                </div>
            </div>

            <!-- Editores JS -->
            <div id="contacto-tel-editor"   style="margin-bottom:var(--espacio-cuatro);"></div>
            <div id="contacto-email-editor" style="margin-bottom:var(--espacio-cuatro);"></div>
            <div id="contacto-redes-editor"></div>

            <!-- ── Notificaciones de leads al negocio ── -->
            <div style="margin-top:var(--espacio-cinco); padding:var(--espacio-cuatro); background:var(--admin-bg-suave); border:1px solid var(--admin-border); border-radius:var(--admin-radio-md);">
                <div style="display:flex; align-items:center; gap:var(--espacio-dos); margin-bottom:var(--espacio-tres);">
                    <i data-lucide="bell" class="icono" style="color:var(--admin-tone-marca-fg);"></i>
                    <strong style="font-size:.95rem;">Notificaciones de leads por email</strong>
                </div>
                <p style="margin:0 0 var(--espacio-tres) 0; font-size:.85rem; color:var(--admin-text-suave); line-height:1.5;">
                    Cuando un visitante completa el formulario de contacto en esta ficha, el negocio recibirá un email con los datos del lead. Disponible solo para planes <strong>02</strong> y <strong>03</strong>.
                </p>

                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer; margin-bottom:var(--espacio-tres);">
                    <input type="checkbox" name="recibe_notif_leads" value="1"
                           <?php echo (int)($cr['recibe_notif_leads'] ?? 1) === 1 ? 'checked' : ''; ?>>
                    <span style="font-size:.9rem;">Recibir notificaciones de leads</span>
                </label>

                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="email_notif_leads">Email alternativo para notificaciones</label>
                    <input type="email" id="email_notif_leads" name="email_notif_leads" class="field__input"
                           placeholder="comercial@..." value="<?php echo htmlspecialchars($cr['email_notif_leads'] ?? ''); ?>">
                    <span class="field__hint">Si se deja vacío, se usa el primer email general del negocio.</span>
                </div>
            </div>
        </section>

        <!-- ── Ubicación ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="map-pin" class="icono"></i>
                Ubicación
                <?php if (!$checks['direccion'] || !$checks['coordenadas']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Incompleto</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completo</span>
                <?php endif; ?>
            </h2>
            <div class="field" style="margin-bottom:var(--espacio-tres);">
                <label class="field__label" for="direccion_completa">Dirección completa <?php echo !$checks['direccion'] ? '<span style="color:var(--admin-tone-error-fg)">○</span>' : '<span style="color:var(--admin-tone-exito-fg)">✓</span>'; ?></label>
                <input type="text" id="direccion_completa" name="direccion_completa" class="field__input"
                       value="<?php echo htmlspecialchars($cr['direccion_completa'] ?? ''); ?>">
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:var(--espacio-tres);">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="ciudad">Ciudad</label>
                    <input type="text" id="ciudad" name="ciudad" class="field__input"
                           value="<?php echo htmlspecialchars($cr['ciudad'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="codigo_postal">Código postal</label>
                    <input type="text" id="codigo_postal" name="codigo_postal" class="field__input"
                           value="<?php echo htmlspecialchars($cr['codigo_postal'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="latitud">Latitud <?php echo !$checks['coordenadas'] ? '<span style="color:var(--admin-tone-error-fg)">○</span>' : '<span style="color:var(--admin-tone-exito-fg)">✓</span>'; ?></label>
                    <input type="text" id="latitud" name="latitud" class="field__input"
                           value="<?php echo htmlspecialchars($cr['latitud'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="longitud">Longitud</label>
                    <input type="text" id="longitud" name="longitud" class="field__input"
                           value="<?php echo htmlspecialchars($cr['longitud'] ?? ''); ?>">
                </div>
                <div class="field" style="grid-column:1/-1; margin-bottom:0;">
                    <button type="button" id="btn-geocodificar" class="boton dos pequeno"
                            onclick="geocodificarFicha(<?php echo (int)$id; ?>)"
                            style="display:inline-flex; align-items:center; gap:.4rem;">
                        <i data-lucide="map-pin" class="icono" style="width:14px;height:14px;"></i>
                        Geocodificar dirección con Google
                    </button>
                    <span id="geocod-resultado" style="margin-left:.6rem; font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);"></span>
                    <div style="font-size:var(--admin-caption); color:var(--admin-tinta-tenue); margin-top:.3rem;">
                        Toma la dirección + código postal + ciudad y obtiene lat/lng automáticamente.
                        Útil si la ficha llegó sin coordenadas o si cambiaste la dirección.
                    </div>
                </div>
                <script>
                function geocodificarFicha(id) {
                    var btn = document.getElementById('btn-geocodificar');
                    var msg = document.getElementById('geocod-resultado');
                    btn.disabled = true;
                    msg.textContent = 'Buscando…';
                    msg.style.color = 'var(--admin-tinta-suave)';
                    var fd = new FormData();
                    fd.append('id', id);
                    fetch('geocodificar-ajax.php', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            if (data.ok) {
                                document.getElementById('latitud').value  = data.lat;
                                document.getElementById('longitud').value = data.lng;
                                msg.textContent = '✓ Coordenadas actualizadas (' + data.formatted + ')';
                                msg.style.color = 'var(--admin-tone-exito-fg)';
                                if (data.place_id) {
                                    var pidInput = document.getElementById('google_place_id');
                                    if (pidInput && !pidInput.value.trim()) pidInput.value = data.place_id;
                                }
                            } else {
                                msg.textContent = '✗ ' + (data.error || 'Sin resultados');
                                msg.style.color = 'var(--admin-tone-error-fg)';
                            }
                        })
                        .catch(function(e) {
                            btn.disabled = false;
                            msg.textContent = '✗ Error de red: ' + e.message;
                            msg.style.color = 'var(--admin-tone-error-fg)';
                        });
                }
                </script>
                <div class="field" style="grid-column:1/-1; margin-bottom:0;">
                    <label class="field__label" for="google_maps_url">URL Google Maps</label>
                    <input type="url" id="google_maps_url" name="google_maps_url" class="field__input"
                           value="<?php echo htmlspecialchars($cr['google_maps_url'] ?? ''); ?>">
                </div>
                <?php
                    $gpid    = trim((string)($cr['google_place_id'] ?? ''));
                    $pidImp  = trim((string)($cr['place_id'] ?? ''));
                    $gpidVal = $gpid !== '' ? $gpid : $pidImp;        // autocopia desde el import si está vacío
                    $gpidSugerido = ($gpid === '' && $pidImp !== ''); // se está proponiendo el del CSV
                ?>
                <div class="field" style="grid-column:1/-1; margin-bottom:0;">
                    <label class="field__label" for="google_place_id">
                        Google Place ID
                        <span style="font-weight:400; color:var(--admin-tinta-tenue);">(habilita el mapa real de Google en Plan 03+)</span>
                    </label>
                    <input type="text" id="google_place_id" name="google_place_id" class="field__input"
                           placeholder="ChIJ..." value="<?php echo htmlspecialchars($gpidVal); ?>">
                    <span class="field__hint" style="display:block; margin-top:.25rem; font-size:var(--admin-caption); color:var(--admin-tinta-tenue);">
                        <?php if ($gpidSugerido): ?>
                        <i data-lucide="info" style="width:12px;height:12px;vertical-align:-2px;"></i>
                        Sugerido desde el importador (<code><?php echo htmlspecialchars($pidImp); ?></code>). Revisalo y guardá para confirmarlo.
                        <?php else: ?>
                        El ID del lugar en Google Maps. Lo usa la ficha pública para el mapa embebido.
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- ── Descripción ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="file-text" class="icono"></i>
                Descripción
                <?php if (!$checks['descripcion']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Mínimo 150 caracteres</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completa</span>
                <?php endif; ?>
            </h2>

            <!-- Asistente IA -->
            <?php renderBotonIA('contenido', $iaLog, 'Sugerir descripción mejorada'); ?>

            <!-- Descripción avanzada SEO + IA-search — gated por completitud de
                 DATOS BASE ≥90% ($pctIa: excluye Descripción y Meta SEO, que son
                 el output de la IA; medir sobre ellos sería circular). -->
            <?php
            $gateAvanzada = $pctIa >= 90
                ? null
                : 'Disponible cuando los datos base estén ≥90% completos (actual ' . $pctIa . '%' .
                  ($faltanIa ? ' · faltan: ' . implode(', ', $faltanIa) : '') . ')';
            renderBotonIA('descripcion_avanzada', $iaLog, 'Generar descripción avanzada (SEO + IA-search)', $gateAvanzada);
            ?>

            <!-- Gestionado por JS — value sincronizado desde versión activa -->
            <input type="hidden" id="descripcion"       name="descripcion"        value="<?php echo htmlspecialchars($cr['descripcion'] ?? ''); ?>">
            <input type="hidden" id="descripciones-json" name="descripciones_json" value="<?php echo htmlspecialchars($descJson); ?>">
            <div id="desc-fuentes-editor"></div>
        </section>

        <!-- ── Horarios ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="clock" class="icono"></i>
                Horarios
                <?php if (!$checks['horarios']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Sin definir</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completo</span>
                <?php endif; ?>
            </h2>

            <!-- Asistente IA -->
            <?php renderBotonIA('horarios', $iaLog); ?>

            <!-- Texto original (referencia inmutable) -->
            <?php renderTextoOrigenBloque('horarios_texto', $textoOrigen, 'Horarios'); ?>

            <!-- Inputs ocultos que se envían con el form -->
            <input type="hidden" id="horarios-json"   name="horarios"       value="<?php echo htmlspecialchars($cr['horarios'] ?? ''); ?>">
            <input type="hidden" id="horario-txt-hid" name="horario_texto"  value="<?php echo htmlspecialchars($cr['horario_texto'] ?? ''); ?>">

            <!-- Editor visual — renderizado por JS -->
            <div id="horario-editor" style="border:1px solid var(--admin-linea-fuerte); border-radius:var(--admin-r-sm); overflow:hidden; min-height:60px;"></div>
            <div id="tel-section" style="margin-top:var(--espacio-dos);"></div>
            <div id="nota-horario"></div>
        </section>

        <!-- ── Zona de cobertura ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="map-pin" class="icono"></i>
                Zona de cobertura
                <?php if (!$checks['zona']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Sin definir</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completo</span>
                <?php endif; ?>
            </h2>

            <!-- Asistente IA para zona + ciudades de cobertura -->
            <?php renderBotonIA('cobertura', $iaLog, 'Detectar zonas y ciudades con IA'); ?>

            <!-- Lista de provincias/CCAA para autocomplete custom (ver JS) -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:var(--espacio-tres);">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">
                        Zona de cobertura (provincias / CCAA)
                        <?php echo !$checks['zona'] ? '<span style="color:var(--admin-tone-error-fg)">○</span>' : '<span style="color:var(--admin-tone-exito-fg)">✓</span>'; ?>
                    </label>
                    <input type="hidden" id="zona_cobertura" name="zona_cobertura"
                           value="<?php echo htmlspecialchars($cr['zona_cobertura'] ?? ''); ?>">
                    <div class="tag-input-wrap" data-target="zona_cobertura" data-autocomplete="zonas"></div>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">Ciudades de cobertura</label>
                    <input type="hidden" id="ciudades_cobertura" name="ciudades_cobertura"
                           value="<?php echo htmlspecialchars($cr['ciudades_cobertura'] ?? ''); ?>">
                    <div class="tag-input-wrap" data-target="ciudades_cobertura"></div>
                </div>
            </div>
        </section>

        <!-- ── Servicios ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="list-checks" class="icono"></i>
                Servicios
                <?php if (!$checks['servicios']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Sin definir</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Definidos</span>
                <?php endif; ?>
            </h2>

            <!-- Asistente IA para servicios booleanos -->
            <?php renderBotonIA('servicios', $iaLog, 'Detectar servicios con IA'); ?>

            <!-- Texto original (referencia inmutable) -->
            <?php renderTextoOrigenBloque('servicios_texto', $textoOrigen, 'Servicios'); ?>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:var(--espacio-tres);">
                <?php
                $servicios = [
                    'cremacion_individual' => 'Cremación individual',
                    'cremacion_colectiva'  => 'Cremación colectiva',
                    'recogida_domicilio'   => 'Recogida a domicilio',
                    'entrega_domicilio'    => 'Entrega a domicilio',
                    'atencion_24h'         => 'Atención 24h',
                    'sala_velatoria'       => 'Sala velatoria',
                    'souvenires'           => 'Souvenirs / recuerdos',
                    'urna'                 => 'Urna incluida',
                    'carta'                => 'Carta de condolencias',
                    'molde'                => 'Molde de huella',
                ];
                foreach ($servicios as $campo => $label):
                ?>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="<?php echo $campo; ?>"><?php echo $label; ?></label>
                    <?php echo selectBool($campo, $cr[$campo]); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── Precios ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="tag" class="icono"></i>
                Precios
                <span id="precios-badge" class="completitud-badge" style="margin-left:auto;"></span>
            </h2>
            <p style="font-size:.85rem; color:var(--admin-text-suave); margin:0 0 var(--espacio-tres); line-height:1.5;">
                Opcional. Lo que el negocio comparta: un precio fijo, un "desde", un rango o una nota.
                Si no hay precios, la sección no aparece en la ficha pública.
            </p>

            <!-- Texto original de precios (inmutable) — viene del registro del
                 negocio o de la importación. Solo se muestra si existe. -->
            <?php renderTextoOrigenBloque('precios_texto', $textoOrigen, 'Precios'); ?>

            <!-- Asistente IA: estructura el texto original en ítems tipados.
                 Si no hay texto original, el admin carga a mano con "+ Agregar precio". -->
            <?php renderBotonIA('precios', $iaLog, 'Estructurar precios con IA'); ?>

            <input type="hidden" id="precios-json" name="precios_json"
                   value="<?php echo htmlspecialchars($preciosJson); ?>">
            <div id="precios-editor" style="display:flex; flex-direction:column; gap:var(--espacio-tres);"></div>
            <button type="button" id="precios-add" class="boton dos pequeno" style="margin-top:var(--espacio-tres);">
                <i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i>
                Agregar precio
            </button>
        </section>

        <!-- ── Mensaje WhatsApp (asistente IA) ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="message-circle" class="icono"></i>
                Mensaje WhatsApp
            </h2>
            <p style="font-size:.85rem; color:var(--admin-text-suave); margin:0 0 var(--espacio-tres); line-height:1.5;">
                Mensaje corto y pre-formateado que el asistente de WhatsApp (N8N) envía cuando recomienda este negocio.
                La versión <strong>"Plantilla automática"</strong> se genera sola con los datos de la ficha (sin IA) y se
                actualiza cada vez que guardás cambios. Podés generar una variante con IA (redacción distinta, mismos
                datos) o escribir una a mano.
            </p>

            <?php renderBotonIA('mensaje_whatsapp', $iaLog, 'Generar variante con IA'); ?>

            <input type="hidden" id="mensajes-whatsapp-json" name="mensajes_whatsapp_json" value="<?php echo htmlspecialchars($whatsappJson); ?>">
            <input type="hidden" id="mensaje-whatsapp-flat" name="mensaje_whatsapp" value="<?php echo htmlspecialchars($cr['mensaje_whatsapp'] ?? ''); ?>">
            <div id="whatsapp-fuentes-editor"></div>
        </section>

        <!-- ── SEO ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="search" class="icono"></i>
                SEO
                <?php if (!$checks['meta_seo']): ?>
                <span class="completitud-badge falta" style="margin-left:auto;">○ Sin meta</span>
                <?php else: ?>
                <span class="completitud-badge ok" style="margin-left:auto;">✓ Completo</span>
                <?php endif; ?>
            </h2>
            <div class="field" style="margin-bottom:0;">
                <label class="field__label">
                    Meta descripción
                    <?php echo !$checks['meta_seo'] ? '<span style="color:var(--admin-tone-error-fg)">○</span>' : '<span style="color:var(--admin-tone-exito-fg)">✓</span>'; ?>
                    <span style="color:var(--admin-tinta-tenue); font-weight:400;">(máx 220 caracteres)</span>
                </label>

                <!-- Asistente IA -->
                <?php renderBotonIA('seo', $iaLog, 'Sugerir meta description'); ?>

                <!-- Gestionado por JS — value sincronizado desde versión activa -->
                <input type="hidden" id="meta_description_seo" name="meta_description_seo" value="<?php echo htmlspecialchars($cr['meta_description_seo'] ?? ''); ?>">
                <input type="hidden" id="metas-json"           name="metas_json"           value="<?php echo htmlspecialchars($metaJson); ?>">
                <div id="meta-fuentes-editor"></div>
            </div>
        </section>

        <?php
        // Comentario adicional del cliente — solo aparece si fuente='manual_negocio'
        // y tiene un mensaje no vacío. NO es editable.
        $msjCliente = ($textoOrigen['fuente'] ?? '') === 'manual_negocio'
            ? trim((string)($textoOrigen['comentarios_admin'] ?? ''))
            : '';
        if ($msjCliente !== ''):
            $msjFecha = $textoOrigen['fecha'] ?? '';
        ?>
        <!-- ── Comentario adicional del cliente ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="message-square-quote" class="icono"></i>
                Comentario adicional del cliente
                <span class="txt-origen-fuente" style="background:var(--admin-tone-exito-bg); color:var(--admin-tone-exito-fg);">
                    👤 Al registrarse
                    <?php if ($msjFecha): ?><span style="opacity:.7;">· <?php echo htmlspecialchars($msjFecha); ?></span><?php endif; ?>
                </span>
            </h2>

            <blockquote style="margin:0 0 var(--espacio-tres) 0; padding:var(--espacio-tres) var(--espacio-cuatro); background:var(--admin-papel-alt); border:1px solid var(--admin-linea); border-radius:var(--admin-r-sm); font-size:var(--admin-body-sm); color:var(--admin-tinta); line-height:1.55; white-space:pre-wrap; word-wrap:break-word;"><?php echo htmlspecialchars($msjCliente); ?></blockquote>

            <p style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin:0 0 var(--espacio-tres) 0;">
                Comentario adicional del cliente cuando registró su negocio.
            </p>

            <!-- Checkboxes de seguimiento -->
            <div style="display:flex; gap:var(--espacio-cuatro); flex-wrap:wrap; align-items:center;">
                <?php foreach ([
                    'leido'       => ['lbl' => 'Leído'],
                    'respondido'  => ['lbl' => 'Respondido'],
                    'solucionado' => ['lbl' => 'Solucionado'],
                ] as $flag => $info): ?>
                <label class="msj-flag field__opcion" data-flag="<?php echo $flag; ?>">
                    <input type="checkbox" class="field__check" <?php echo $msjEstado[$flag] ? 'checked' : ''; ?>
                           onchange="msjClienteToggle('<?php echo $flag; ?>', this)">
                    <span><?php echo htmlspecialchars($info['lbl']); ?></span>
                </label>
                <?php endforeach; ?>
                <span id="msj-cliente-status" style="margin-left:auto; font-size:var(--admin-body-sm); color:var(--admin-tinta-tenue);"></span>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── Admin ── -->
        <section class="ficha-card">
            <h2 class="ficha-card__title">
                <i data-lucide="message-circle" class="icono"></i>
                Notas internas del admin
            </h2>
            <div class="field" style="margin-bottom:0;">
                <label class="field__label" for="comentarios_admin">Notas privadas del equipo (no públicas)</label>
                <textarea id="comentarios_admin" name="comentarios_admin" class="field__textarea" rows="3"
                          placeholder="Anotá observaciones, pendientes o recordatorios sobre esta ficha. No se muestra al público."><?php echo htmlspecialchars($cr['comentarios_admin'] ?? ''); ?></textarea>
            </div>
        </section>

    </form>

    </div><!-- /.ficha-layout__main -->

    <aside class="ficha-layout__aside">

        <!-- Panel de completitud -->
        <section class="admin-section" style="background:var(--admin-superficie); border:1px solid var(--admin-linea); border-radius:var(--admin-r-md); box-shadow:var(--admin-sombra-suave); padding:var(--espacio-cuatro); margin-bottom:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--espacio-tres); gap:var(--espacio-dos); flex-wrap:wrap;">
                <h2 class="admin-section__title" style="margin:0;">
                    <i data-lucide="check-square" class="icono" style="width:18px; height:18px;"></i>
                    Completitud del perfil
                </h2>
                <div style="display:flex; align-items:center; gap:var(--espacio-dos);">
                    <span style="font-size:var(--admin-h5); font-weight:700; color:<?php echo $barColor; ?>; font-variant-numeric:tabular-nums;"><?php echo $pct; ?>%</span>
                    <span style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-variant-numeric:tabular-nums;"><?php echo $completados; ?>/<?php echo $total_checks; ?></span>
                </div>
            </div>
            <div style="width:100%; background:var(--admin-papel-alt); border:1px solid var(--admin-linea); border-radius:var(--admin-r-pill); height:8px; overflow:hidden; margin-bottom:var(--espacio-tres);">
                <div style="width:<?php echo $pct; ?>%; height:100%; background:<?php echo $barColor; ?>; border-radius:var(--admin-r-pill); transition:width .25s;"></div>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:var(--espacio-dos);">
                <?php foreach ($checks as $key => $ok): ?>
                <span class="completitud-badge <?php echo $ok ? 'ok' : 'falta'; ?>">
                    <i data-lucide="<?php echo $ok ? 'check-circle-2' : 'circle'; ?>" style="width:13px;height:13px;vertical-align:-2px;"></i>
                    <?php echo $checkLabels[$key]; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php if (!$tienePortada && !$tieneImg): ?>
            <div style="margin-top:var(--espacio-tres); font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);">
                Las imágenes se gestionan desde <a href="imagenes-cola.php" style="color:var(--admin-brand); font-weight:500;">la cola LLM</a>.
            </div>
            <?php endif; ?>
        </section>

        <!-- Acciones (sticky, siempre a la vista) -->
        <div class="ficha-card" style="padding:var(--espacio-cuatro);">
            <div style="display:flex; flex-direction:column; gap:var(--espacio-dos);">
                <button type="submit" form="main-form" class="boton tres" style="width:100%; justify-content:center;">
                    <i data-lucide="save" class="icono"></i>
                    Guardar cambios
                </button>
                <a href="fichas-negocios.php" class="boton dos" style="width:100%; justify-content:center;">Cancelar</a>
            </div>
            <p style="margin:var(--espacio-dos) 0 0; font-size:var(--admin-caption); color:var(--admin-tinta-suave); text-align:center;">
                El estado “cambios sin guardar” se ve en la barra superior.
            </p>
        </div>

    </aside>

    <!-- Galerías: van DENTRO del grid en la columna 1, debajo del form.
         El aside (col 2) abarca ambas filas con grid-row: 1 / span 2 y se
         mantiene sticky mientras se scrollea por las galerías. -->
    <div class="ficha-galerias" style="display:flex; flex-direction:column; gap:var(--espacio-cuatro);">

    <!-- ═══════════════════════════════════════════════════════
         SECCIÓN IMÁGENES (formulario separado, multipart)
         ═══════════════════════════════════════════════════════ -->

    <?php
    // Mensajes de imagen (vienen por GET tras redirect) — se muestran en barra sticky
    $imgOk    = htmlspecialchars($_GET['img_ok']    ?? '');
    $imgError = htmlspecialchars($_GET['img_error'] ?? '');
    ?>

    <section id="seccion-imagenes" class="ficha-card ficha-layout__gallery" style="margin:0;">
        <h2 class="ficha-card__title">
            <i data-lucide="image" class="icono"></i>
            Imágenes
            <?php if (!$tieneImg && !$tieneLogo): ?>
            <span class="completitud-badge falta" style="margin-left:auto;">○ Sin imágenes locales</span>
            <?php else: ?>
            <span class="completitud-badge ok" style="margin-left:auto;">✓ <?php echo count($imagenes); ?> imagen(es)</span>
            <?php endif; ?>
        </h2>

        <?php
        // ── Alertas de gestión de imágenes ──────────────────────────────────────
        $nPendientes        = (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id=$id AND (categoria IS NULL OR categoria='') AND tipo NOT IN ('logo','portada','cliente') AND ruta NOT LIKE 'http%'")->fetchColumn();
        $nLotes             = max(1, (int) ceil($nPendientes / 10));
        $nPendientesCliente = (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id=$id AND (categoria IS NULL OR categoria='') AND tipo='cliente' AND ruta NOT LIKE 'http%'")->fetchColumn();
        $nLotesCliente      = max(1, (int) ceil($nPendientesCliente / 10));
        // Errores LLM: solo imágenes locales ya categorizadas (URL e imágenes sin cat. no aplican)
        $nErrores    = (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id=$id AND estado_llm='error' AND categoria IS NOT NULL AND categoria != '' AND ruta NOT LIKE 'http%'")->fetchColumn();

        // Alt texts duplicados dentro de este crematorio
        $dupAlt = $pdo->query("
            SELECT alt_text, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM crematorio_imagenes
            WHERE crematorio_id=$id AND alt_text IS NOT NULL AND alt_text != ''
            GROUP BY alt_text HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Imágenes locales con categoría pero sin alt text
        $nSinAlt = (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id=$id AND (alt_text IS NULL OR alt_text='') AND categoria IS NOT NULL AND categoria!='' AND ruta NOT LIKE 'http%'")->fetchColumn();
        $nDupAlt = count($dupAlt);
        $nAltProblemas = $nSinAlt + $nDupAlt;
        $apiKeyOk = defined('CLAUDE_API_KEY') && !empty(CLAUDE_API_KEY);
        ?>

        <?php if ($nPendientes > 0): ?>
        <div id="llm-alert-box" class="admin-banner admin-banner--warning" style="margin-bottom:var(--espacio-tres); flex-direction:column; align-items:stretch; gap:var(--espacio-dos);">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--espacio-dos);">
                <div>
                    <strong style="font-size:var(--admin-body);">
                        <?php echo $nPendientes; ?> imagen<?php echo $nPendientes > 1 ? 'es' : ''; ?> sin categorizar
                    </strong>
                    <?php if ($nLotes > 1): ?>
                    <span style="font-size:var(--admin-body-sm); margin-left:.4rem; opacity:.8;">— <?php echo $nLotes; ?> lotes de 10</span>
                    <?php endif; ?>
                </div>
                <?php if ($apiKeyOk): ?>
                <button id="btn-procesar-llm" onclick="procesarLote(<?php echo $id; ?>, 1)" class="boton uno pequeno"
                        style="display:inline-flex; align-items:center; gap:.4rem;">
                    <i data-lucide="sparkles" class="icono" style="width:15px;height:15px;"></i>
                    Procesar próximo lote<?php echo $nPendientes <= 10 ? ' (' . $nPendientes . ' imágenes)' : ''; ?>
                </button>
                <?php endif; ?>
            </div>
            <ul style="margin:0; padding-left:1.1rem; font-size:var(--admin-body-sm); display:flex; flex-direction:column; gap:.2rem;">
                <li><strong>Con IA:</strong> procesá de a <?php echo $nLotes > 1 ? '10' : $nPendientes; ?> imagen<?php echo ($nLotes > 1 || $nPendientes > 1) ? 'es' : ''; ?> por lote con el botón.</li>
                <li><strong>Manualmente:</strong> editá tipo y categoría directamente en cada imagen resaltada abajo.</li>
                <?php if (!$apiKeyOk): ?>
                <li><strong>Sin API key:</strong> configurá <code style="background:var(--admin-papel-alt); padding:.1rem .3rem; border-radius:3px;">CLAUDE_API_KEY</code> en config.php para usar la IA.</li>
                <?php endif; ?>
                <li style="opacity:.75;">Los errores de IA se corrigen manualmente — no se reintenta automáticamente.</li>
            </ul>
        </div>
        <div id="llm-resultado" style="margin-bottom:var(--espacio-tres);"></div>
        <?php endif; ?>

        <?php if ($nErrores > 0): ?>
        <div class="admin-banner admin-banner--error" style="margin-bottom:var(--espacio-tres);">
            <i data-lucide="alert-octagon" class="icono admin-banner__icon"></i>
            <div class="admin-banner__content">
                <strong><?php echo $nErrores; ?> imagen<?php echo $nErrores > 1 ? 'es' : ''; ?> con error en el procesado LLM</strong>
                — <a href="<?php echo BASE_URL; ?>/admin/imagenes-cola.php?estado=error&q=<?php echo urlencode($cr['nombre']); ?>" style="color:var(--admin-tone-error-fg); font-weight:600; text-decoration:underline;">Ver en cola</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($nAltProblemas > 0): ?>
        <div id="alt-alert-box" class="admin-banner admin-banner--info" style="margin-bottom:var(--espacio-tres); flex-direction:column; align-items:stretch; gap:var(--espacio-dos);">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--espacio-dos);">
                <strong style="font-size:var(--admin-body);">
                    <?php
                    $partes = [];
                    if ($nSinAlt > 0) $partes[] = "$nSinAlt sin alt text";
                    if ($nDupAlt > 0) $partes[] = "$nDupAlt duplicado" . ($nDupAlt > 1 ? 's' : '');
                    echo implode(' · ', $partes);
                    ?>
                </strong>
                <?php if ($apiKeyOk): ?>
                <button id="btn-generar-alt" onclick="generarAltTexts(<?php echo $id; ?>)" class="boton uno pequeno"
                        style="display:inline-flex; align-items:center; gap:.4rem;">
                    <i data-lucide="type" class="icono" style="width:15px;height:15px;"></i>
                    Generar alt texts con IA
                </button>
                <?php endif; ?>
            </div>
            <?php if ($nSinAlt > 0): ?>
            <p style="margin:0; font-size:var(--admin-body-sm);">
                • <strong><?php echo $nSinAlt; ?> imagen<?php echo $nSinAlt > 1 ? 'es' : ''; ?></strong> con categoría asignada pero sin alt text.
            </p>
            <?php endif; ?>
            <?php if (!empty($dupAlt)): ?>
            <p style="margin:0; font-size:var(--admin-body-sm);"><strong>Alt texts duplicados:</strong></p>
            <ul style="margin:0; padding-left:1.2rem; font-size:var(--admin-body-sm);">
                <?php foreach ($dupAlt as $dup): ?>
                <li>"<?php echo htmlspecialchars(mb_substr($dup['alt_text'], 0, 80)); ?>…" <span style="opacity:.7;">(<?php echo $dup['cnt']; ?>x · IDs: <?php echo htmlspecialchars($dup['ids']); ?>)</span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (!$apiKeyOk): ?>
            <p style="margin:0; font-size:var(--admin-body-sm); opacity:.85;">Para generarlos con IA, ejecutá <code style="background:var(--admin-papel-alt); padding:.1rem .3rem; border-radius:3px;">php scripts/generar-alt-texts.php</code> en localhost.</p>
            <?php endif; ?>
        </div>
        <div id="alt-resultado" style="display:none; margin-bottom:var(--espacio-tres);"></div>
        <?php endif; ?>

        <!-- Galería de imágenes existentes -->
        <?php
        $categoriasOpciones = [
            ''                       => '— Sin categoría (pendiente LLM)',
            'logo'                   => 'Logo',
            'exterior'               => 'Exterior',
            'interior_sala'          => 'Interior — Sala',
            'interior_recepcion'     => 'Interior — Recepción',
            'interior_amenities'     => 'Interior — Amenities',
            'produccion_tecnologia'  => 'Producción / Tecnología',
            'recuerdos_souvenires'   => 'Recuerdos / Souvenirs',
            'equipo_personas'        => 'Equipo / Personas',
            'fotos_clientes'         => 'Fotos de clientes',
            'otro'                   => 'Otro',
        ];
        ?>
        <?php if (!empty($imagenesLocales)):
            // Orden: logos → portada → sin categoría → galería categorizada (por id dentro de cada grupo)
            usort($imagenesLocales, function($a, $b) {
                $peso = function($img) {
                    if ($img['tipo'] === 'logo')    return 0;
                    if ($img['tipo'] === 'portada') return 1;
                    if (empty($img['categoria']))   return 2;
                    return 3;
                };
                $pa = $peso($a); $pb = $peso($b);
                if ($pa !== $pb) return $pa - $pb;
                return $a['id'] - $b['id'];
            });
        ?>
        <!-- Barra selección múltiple -->
        <div id="barra-seleccion" style="display:flex; align-items:center; gap:var(--espacio-tres); margin-bottom:var(--espacio-tres); flex-wrap:wrap;">
            <button type="button" id="btn-modo-seleccion"
                    onclick="toggleModoSeleccion()"
                    class="boton dos pequeno" style="display:inline-flex; align-items:center; gap:.4rem;">
                <i data-lucide="check-square" class="icono" style="width:14px;height:14px;"></i>
                Selección múltiple
            </button>
            <div id="acciones-seleccion" style="display:none; align-items:center; gap:var(--espacio-dos); flex-wrap:wrap;">
                <span id="contador-seleccion" style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-variant-numeric:tabular-nums;">0 seleccionadas</span>
                <button type="button" onclick="accionSeleccion('desactivar')"
                        class="admin-pill admin-pill--alerta" style="border:0; cursor:pointer; font-weight:600;">
                    <i data-lucide="eye-off" style="width:12px;height:12px;"></i> Desactivar
                </button>
                <button type="button" onclick="accionSeleccion('reactivar')"
                        class="admin-pill admin-pill--exito" style="border:0; cursor:pointer; font-weight:600;">
                    <i data-lucide="eye" style="width:12px;height:12px;"></i> Reactivar
                </button>
                <button type="button" onclick="accionSeleccion('eliminar')"
                        class="admin-pill admin-pill--error" style="border:0; cursor:pointer; font-weight:600;">
                    <i data-lucide="trash-2" style="width:12px;height:12px;"></i> Eliminar
                </button>
                <button type="button" onclick="toggleModoSeleccion()"
                        style="font-size:var(--admin-body-sm); background:none; border:none; color:var(--admin-tinta-suave); cursor:pointer; text-decoration:underline; padding:0;">
                    Cancelar
                </button>
            </div>
        </div>

        <div id="grid-imagenes" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:var(--espacio-tres); margin-bottom:var(--espacio-cinco);">
            <?php
            $tiposLabels    = ['logo' => 'Logo', 'portada' => 'Portada', 'galeria' => 'Galería'];
            $logoPrincipalId = (int)($cr['logo_principal_id'] ?? 0);
            // Si no hay pin manual, el logo auto-activo es el de mayor created_at (más reciente)
            $logoAutoActivoId = 0;
            if (!$logoPrincipalId) {
                $logosSort = array_filter($imagenesLocales, fn($i) => $i['tipo'] === 'logo');
                if ($logosSort) {
                    usort($logosSort, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
                    $logoAutoActivoId = (int)array_values($logosSort)[0]['id'];
                }
            }
            $portadaPrincipalId = (int)($cr['portada_principal_id'] ?? 0);
            // Si no hay pin manual, la portada auto-activa es la de mayor created_at entre tipo='portada'
            $portadaAutoActivaId = 0;
            if (!$portadaPrincipalId) {
                // 1. Preferencia: imagen con tipo='portada'
                $portadasTipo = array_filter($imagenesLocales, fn($i) => $i['tipo'] === 'portada');
                if ($portadasTipo) {
                    usort($portadasTipo, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
                    $portadaAutoActivaId = (int)array_values($portadasTipo)[0]['id'];
                } else {
                    // 2. Fallback: primera imagen de galería por created_at DESC (igual que funciones.php)
                    $galeriasLocales = array_values(array_filter($imagenesLocales, fn($i) => $i['tipo'] === 'galeria'));
                    if ($galeriasLocales) {
                        usort($galeriasLocales, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
                        $portadaAutoActivaId = (int)$galeriasLocales[0]['id'];
                    }
                }
            }
            // Orden de tarjetas: logo(s) → portada activa → resto.
            // Tiebreak interno: orden_negocio ASC, luego id ASC (orden original).
            $portadaActivaId = $portadaPrincipalId ?: $portadaAutoActivaId;
            $logoActivoId    = $logoPrincipalId ?: $logoAutoActivoId;
            usort($imagenesLocales, function ($a, $b) use ($portadaActivaId, $logoActivoId) {
                $pa = $a['tipo'] === 'logo' ? 0 : ((int)$a['id'] === $portadaActivaId ? 1 : 2);
                $pb = $b['tipo'] === 'logo' ? 0 : ((int)$b['id'] === $portadaActivaId ? 1 : 2);
                if ($pa !== $pb) return $pa <=> $pb;
                // Entre logos: el activo (el que se ve en la ficha pública) primero
                if ($pa === 0) {
                    $aAct = ((int)$a['id'] === $logoActivoId) ? 0 : 1;
                    $bAct = ((int)$b['id'] === $logoActivoId) ? 0 : 1;
                    if ($aAct !== $bAct) return $aAct <=> $bAct;
                }
                $oa = (int)($a['orden_negocio'] ?? 0);
                $ob = (int)($b['orden_negocio'] ?? 0);
                if ($oa !== $ob) return $oa <=> $ob;
                return (int)$a['id'] <=> (int)$b['id'];
            });
            foreach ($imagenesLocales as $idx => $img):
                $imgUrl    = BASE_URL . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/');
                $esLocal   = true;
                $tipoLabel = $tiposLabels[$img['tipo'] ?? 'galeria'] ?? ucfirst($img['tipo'] ?? '');
                $catLabel  = $categoriasOpciones[$img['categoria'] ?? ''] ?? '— Sin categoría';
                $cardId    = 'img-card-' . $img['id'];
                $imgInfo   = '';
                if ($esLocal) {
                    $filePath = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/');
                    if (file_exists($filePath)) {
                        $size = getimagesize($filePath);
                        $kb   = round(filesize($filePath) / 1024);
                        if ($size) $imgInfo = $size[0] . ' × ' . $size[1] . ' px · ' . $kb . ' KB';
                    }
                }
                $esPendiente      = (empty($img['categoria']) || $img['estado_llm'] === 'pendiente')
                                  && $img['tipo'] !== 'logo' && $img['tipo'] !== 'portada';
                $esLogoPrincipal  = ($img['tipo'] === 'logo' && $logoPrincipalId > 0 && (int)$img['id'] === $logoPrincipalId);
                $esLogoAutoActivo = ($img['tipo'] === 'logo' && !$logoPrincipalId && (int)$img['id'] === $logoAutoActivoId);
                $esLogoActivo     = $esLogoPrincipal || $esLogoAutoActivo;
                $esPortadaPrincipal  = ($portadaPrincipalId > 0 && (int)$img['id'] === $portadaPrincipalId);
                $esPortadaAutoActiva = (!$portadaPrincipalId && (int)$img['id'] === $portadaAutoActivaId);
                $esPortadaActiva     = $esPortadaPrincipal || $esPortadaAutoActiva;
                $esListaParaPortada  = !empty($img['categoria']) && !empty($img['alt_text'])
                                    && $img['estado_llm'] === 'procesada'
                                    && $img['tipo'] !== 'logo' && $img['tipo'] !== 'cliente';
                $esVisible           = (int)($img['visible'] ?? 1) === 1;
                $esSeleccionable     = $img['tipo'] === 'galeria' && !$esPortadaActiva;
                if ($img['tipo'] === 'logo')        $ring = $esLogoActivo ? 'var(--admin-brand)' : 'var(--admin-brand-soft-hover)';
                elseif ($esPortadaActiva)           $ring = 'var(--admin-tone-exito-fg)';
                elseif ($img['tipo'] === 'portada') $ring = 'var(--admin-tone-exito-bord)';
                elseif (!$esVisible)                $ring = 'var(--admin-tinta-tenue)';
                elseif ($esPendiente)               $ring = 'var(--admin-tone-alerta-fg)';
                else                                $ring = '';

                $cfg = [
                    'modo'                  => 'ficha',
                    'crematorio_id'         => $id,
                    'categoriasOpciones'    => $categoriasOpciones,
                    'redir'                 => '',
                    'img_url'               => $imgUrl,
                    'card_id'               => $cardId,
                    'es_local'              => $esLocal,
                    'es_pendiente'          => $esPendiente,
                    'es_visible'            => $esVisible,
                    'es_seleccionable'      => $esSeleccionable,
                    'es_logo_activo'        => $esLogoActivo,
                    'es_logo_principal'     => $esLogoPrincipal,
                    'es_portada_activa'     => $esPortadaActiva,
                    'es_portada_principal'  => $esPortadaPrincipal,
                    'es_lista_para_portada' => $esListaParaPortada,
                    'ring'                  => $ring,
                    'tipo_label'            => $tipoLabel,
                    'cat_label'             => $catLabel,
                    'img_info'              => $imgInfo,
                ];
                include __DIR__ . '/../includes/componentes/img-card-admin.php';
            ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Formulario de subida -->
        <div style="border-top:1px solid var(--admin-linea); padding-top:var(--espacio-cuatro);">
            <h3 style="font-size:var(--admin-h5); font-weight:700; color:var(--admin-tinta-fuerte); margin-bottom:var(--espacio-tres);">Subir nueva imagen</h3>
            <form method="POST" action="subir-imagen.php" enctype="multipart/form-data"
                  style="display:flex; flex-direction:column; gap:var(--espacio-tres);">
                <input type="hidden" name="crematorio_id" value="<?php echo $id; ?>">
                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($cr['slug']); ?>">

                <!-- Fila 1: selector de archivos -->
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">Archivos <span style="font-weight:400; color:var(--admin-tinta-tenue);">(JPG, PNG, WebP — máx 5MB c/u — máx 20 por subida)</span></label>
                    <div class="field__file">
                        <input type="file" id="img-archivo" name="imagenes[]" class="field__file-input"
                               accept="image/jpeg,image/png,image/webp,image/gif"
                               multiple required
                               onchange="actualizarArchivos()">
                        <button type="button" onclick="document.getElementById('img-archivo').click()"
                                class="boton uno field__file-btn">
                            <i data-lucide="folder-open" class="icono"></i>
                            Elegir archivos
                        </button>
                        <div id="archivos-display" class="field__file-display">
                            Ningún archivo seleccionado
                        </div>
                        <button type="button" id="archivos-limpiar" onclick="limpiarArchivos()"
                                class="field__file-clear" title="Limpiar selección" style="display:none;">
                            <i data-lucide="x" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                    <div id="archivos-lista" class="field__file-list" style="display:none;"></div>
                </div>

                <!-- Fila 2: tipo + categoría + botón -->
                <div style="display:flex; gap:var(--espacio-tres); align-items:flex-end; flex-wrap:wrap;">
                    <div class="field" style="flex:0 0 170px; margin-bottom:0;">
                        <label class="field__label" for="img-tipo">Tipo</label>
                        <select id="img-tipo" name="tipo" class="field__select field__select--enhanced" data-ts-search="off" onchange="actualizarCategoria(this)">
                            <option value="foto">Foto de galería</option>
                            <option value="logo">Logo</option>
                            <option value="portada">Portada</option>
                        </select>
                    </div>
                    <div class="field" id="wrap-categoria" style="flex:1; min-width:200px; margin-bottom:0;">
                        <label class="field__label" for="img-categoria">Categoría <span style="font-weight:400; color:var(--admin-tinta-tenue);">(opcional — vacío va a cola LLM)</span></label>
                        <select id="img-categoria" name="categoria" class="field__select field__select--enhanced" data-ts-search="off" onchange="sincCategoria(this)">
                            <option value="">Dejar para LLM</option>
                            <option value="exterior">Exterior</option>
                            <option value="interior_sala">Interior — Sala</option>
                            <option value="interior_recepcion">Interior — Recepción</option>
                            <option value="interior_amenities">Interior — Amenities</option>
                            <option value="produccion_tecnologia">Producción / Tecnología</option>
                            <option value="recuerdos_souvenires">Recuerdos / Souvenirs</option>
                            <option value="equipo_personas">Equipo / Personas</option>
                            <option value="fotos_clientes">Fotos de clientes</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <button type="submit" class="boton uno" style="flex-shrink:0;"
                            onclick="var n=document.getElementById('img-archivo').files.length; if(n>20){toast.error('Seleccionaste '+n+' archivos. PHP solo puede procesar 20 por subida — dividí en grupos de 20.'); return false;}">
                        <i data-lucide="upload" class="icono"></i>
                        Subir imágenes
                    </button>
                </div>
            </form>
        <!-- Imágenes vía URL -->
        <div style="border-top:1px solid var(--admin-linea); padding-top:var(--espacio-cuatro); margin-top:var(--espacio-cuatro);">
            <h3 style="font-size:var(--admin-h5); font-weight:700; color:var(--admin-tinta-fuerte); margin-bottom:.25rem;">Imágenes vía URL</h3>
            <p style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-bottom:var(--espacio-tres);">
                Imágenes externas (Google Business, web propia, etc.) disponibles para fichas Plan 01.
            </p>

            <!-- Formulario agregar URL -->
            <form method="POST" action="agregar-imagen-url.php"
                  style="display:flex; flex-wrap:wrap; gap:var(--espacio-dos) var(--espacio-tres); align-items:flex-end; margin-bottom:var(--espacio-cuatro); padding:var(--espacio-tres); background:var(--admin-papel-alt); border:1px solid var(--admin-linea); border-radius:var(--admin-r-md);">
                <input type="hidden" name="crematorio_id" value="<?php echo $id; ?>">
                <div class="field" style="flex:1; min-width:260px; margin-bottom:0;">
                    <label class="field__label">URL de imagen</label>
                    <input type="url" name="url" required placeholder="https://..." class="field__input">
                </div>
                <div class="field" style="min-width:130px; margin-bottom:0;">
                    <label class="field__label">Tipo</label>
                    <select name="tipo" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="galeria">Galería</option>
                        <option value="logo">Logo</option>
                        <option value="portada">Portada</option>
                    </select>
                </div>
                <div class="field" style="min-width:170px; margin-bottom:0;">
                    <label class="field__label">Categoría</label>
                    <select name="categoria" class="field__select field__select--enhanced" data-ts-search="off">
                        <?php foreach ($categoriasOpciones as $val => $label): ?>
                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="boton uno pequeno" style="align-self:flex-end;">
                    <i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i>
                    Agregar
                </button>
            </form>

            <?php if (!empty($imagenesURL)): ?>
            <div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:var(--espacio-tres);">
            <?php
            foreach ($imagenesURL as $img):
                $imgId2 = (int) $img['id'];
                $cfg = [
                    'modo'               => 'ficha',
                    'variante'           => 'url',
                    'crematorio_id'      => $id,
                    'categoriasOpciones' => $categoriasOpciones,
                    'redir'              => '',
                    'img_url'            => $img['ruta'],
                    'card_id'            => 'img-card-' . $imgId2,
                    'es_local'           => true,
                    'es_pendiente'       => (empty($img['categoria']) || ($img['estado_llm'] ?? '') === 'pendiente'),
                    'es_visible'         => true,
                    'es_seleccionable'   => false,
                    'ring'               => '',
                    'cat_label'          => $categoriasOpciones[$img['categoria'] ?? ''] ?? '— Sin categoría',
                    'img_info'           => '',
                ];
                include __DIR__ . '/../includes/componentes/img-card-admin.php';
            ?>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <!-- ═══ FIN IMÁGENES (NEGOCIO) ═══════════════════════════════════════════ -->

    <!-- ═══ IMÁGENES DE CLIENTES ════════════════════════════════════════════ -->
    <section id="seccion-imagenes-clientes" class="ficha-card ficha-layout__gallery" style="margin:0;">
        <h2 class="ficha-card__title" style="margin-bottom:var(--espacio-uno);">
            <i data-lucide="users" class="icono"></i>
            Imágenes de clientes
            <span class="admin-pill admin-pill--info" style="margin-left:auto;">
                <?php echo count($imagenesCliente); ?> imagen<?php echo count($imagenesCliente) !== 1 ? 'es' : ''; ?>
            </span>
        </h2>
        <p style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-bottom:var(--espacio-tres);">
            Imágenes aportadas por clientes vía reseñas, importadas de Google Business o TrustIndex. Solo editables por el administrador del directorio.
        </p>

            <?php if ($nPendientesCliente > 0): ?>
            <div id="llm-cliente-alert" class="admin-banner admin-banner--info" style="margin-bottom:var(--espacio-tres); flex-direction:column; align-items:stretch; gap:var(--espacio-dos);">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--espacio-dos);">
                    <div>
                        <strong style="font-size:var(--admin-body);">
                            <?php echo $nPendientesCliente; ?> imagen<?php echo $nPendientesCliente > 1 ? 'es' : ''; ?> de reseñas de clientes pendientes de procesar
                        </strong>
                        <?php if ($nLotesCliente > 1): ?>
                        <span style="font-size:var(--admin-body-sm); margin-left:.4rem; opacity:.8;">— <?php echo $nLotesCliente; ?> lotes de 10</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($apiKeyOk): ?>
                    <button id="btn-procesar-cliente" onclick="procesarLoteCliente(<?php echo $id; ?>, 1)" class="boton uno pequeno"
                            style="display:inline-flex; align-items:center; gap:.4rem;">
                        <i data-lucide="sparkles" class="icono" style="width:15px;height:15px;"></i>
                        Procesar próximo lote<?php echo $nPendientesCliente <= 10 ? ' (' . $nPendientesCliente . ' imágenes)' : ''; ?>
                    </button>
                    <?php endif; ?>
                </div>
                <ul style="margin:0; padding-left:1.1rem; font-size:var(--admin-body-sm); display:flex; flex-direction:column; gap:.2rem;">
                    <li>Mismo flujo que galería: la IA asigna categoría y alt text.</li>
                    <li style="opacity:.75;">Los errores se corrigen manualmente — el tipo <strong>cliente</strong> no cambia automáticamente.</li>
                </ul>
            </div>
            <div id="llm-cliente-resultado" style="margin-bottom:var(--espacio-tres);"></div>
            <?php endif; ?>

            <?php if (empty($imagenesCliente)): ?>
            <div class="admin-empty" style="padding:var(--espacio-cuatro);">
                <div class="admin-empty__icon"><i data-lucide="users" style="width:26px;height:26px;"></i></div>
                <div class="admin-empty__texto">Sin imágenes de clientes todavía. Se agregarán automáticamente al procesar reseñas con adjuntos.</div>
            </div>
            <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:var(--espacio-tres);">
            <?php
            foreach ($imagenesCliente as $img):
                $esPendienteC = empty($img['categoria']) || $img['estado_llm'] === 'pendiente';
                $imgUrlC      = BASE_URL . '/' . ltrim($img['ruta'], '/');
                $catLabelC    = $categoriasOpciones[$img['categoria'] ?? ''] ?? '— Sin categoría';
                $imgIdC       = (int) $img['id'];
                $imgInfoC     = '';
                $fpC = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/');
                if (is_file($fpC)) {
                    $szC = @getimagesize($fpC);
                    $kbC = round(filesize($fpC) / 1024);
                    if ($szC) $imgInfoC = $szC[0] . ' × ' . $szC[1] . ' px · ' . $kbC . ' KB';
                }
                $cfg = [
                    'modo'               => 'ficha',
                    'variante'           => 'cliente',
                    'crematorio_id'      => $id,
                    'categoriasOpciones' => $categoriasOpciones,
                    'redir'              => '',
                    'img_url'            => $imgUrlC,
                    'card_id'            => 'img-card-' . $imgIdC,
                    'es_local'           => true,
                    'es_pendiente'       => $esPendienteC,
                    'es_visible'         => true,
                    'es_seleccionable'   => false,
                    'ring'               => $esPendienteC ? 'var(--admin-tone-alerta-fg)' : '',
                    'cat_label'          => $catLabelC,
                    'img_info'           => $imgInfoC,
                ];
                include __DIR__ . '/../includes/componentes/img-card-admin.php';
            ?>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <!-- ═══ FIN IMÁGENES DE CLIENTES ══════════════════════════════════════ -->
    </section>

    <!-- ═══ RESEÑAS DE CLIENTES ═════════════════════════════════════════════ -->
    <section id="seccion-resenas-clientes" class="ficha-card ficha-layout__gallery" style="margin:0;">
        <h2 class="ficha-card__title" style="margin-bottom:var(--espacio-uno);">
            <i data-lucide="message-square" class="icono"></i>
            Reseñas de clientes
            <?php if ($fichaResenasStats['total'] > 0): ?>
            <span style="margin-left:auto; display:inline-flex; align-items:center; gap:.35rem; flex-wrap:wrap;">
                <span class="admin-pill"><?php echo $fichaResenasStats['total']; ?> total</span>
                <?php if ($fichaResenasStats['pendientes'] > 0): ?>
                <span class="admin-pill admin-pill--alerta"><?php echo $fichaResenasStats['pendientes']; ?> pendiente<?php echo $fichaResenasStats['pendientes'] === 1 ? '' : 's'; ?></span>
                <?php endif; ?>
                <?php if ($fichaResenasStats['aprobadas'] > 0): ?>
                <span class="admin-pill admin-pill--exito"><?php echo $fichaResenasStats['aprobadas']; ?> aprobada<?php echo $fichaResenasStats['aprobadas'] === 1 ? '' : 's'; ?></span>
                <?php endif; ?>
                <?php if ($fichaResenasStats['rechazadas'] > 0): ?>
                <span class="admin-pill"><?php echo $fichaResenasStats['rechazadas']; ?> rechazada<?php echo $fichaResenasStats['rechazadas'] === 1 ? '' : 's'; ?></span>
                <?php endif; ?>
                <?php if ($fichaResenasStats['spam'] > 0): ?>
                <span class="admin-pill admin-pill--error">
                    <i data-lucide="alert-triangle" class="icono" style="width:11px; height:11px;"></i>
                    <?php echo $fichaResenasStats['spam']; ?> SPAM
                </span>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </h2>
        <p style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-bottom:var(--espacio-tres);">
            Reseñas vinculadas a esta ficha. Se pueden moderar y eliminar imágenes adjuntas inline (las pendientes muestran botones de aprobar / rechazar).
        </p>

        <?php if (empty($fichaResenas)): ?>
        <div class="admin-empty" style="padding:var(--espacio-cuatro);">
            <div class="admin-empty__icon"><i data-lucide="message-square" style="width:26px;height:26px;"></i></div>
            <div class="admin-empty__texto">Sin reseñas para esta ficha todavía.</div>
        </div>
        <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:var(--espacio-tres);">
        <?php
        // Estado → variante de admin-pill
        $estadoPill = [
            'pendiente'  => ['cls' => 'admin-pill--alerta', 'label' => 'Pendiente'],
            'aprobada'   => ['cls' => 'admin-pill--exito',  'label' => 'Aprobada'],
            'rechazada'  => ['cls' => 'admin-pill--error',  'label' => 'Rechazada'],
        ];
        // Fuente → icono lucide + label
        $fuenteIconos = [
            'google'      => ['icono' => 'search',      'lbl' => 'Google'],
            'trustindex'  => ['icono' => 'star',        'lbl' => 'Trustindex'],
            'propio'      => ['icono' => 'badge-check',  'lbl' => 'Verificada'],
        ];

        // Pendientes siempre visibles; resueltas (aprobadas/rechazadas) colapsadas
        // in-page; si superan el tope visible, el resto se gestiona en resenas.php.
        // $fichaResenas viene ordenado: pendientes → aprobadas → rechazadas.
        $nResTotal     = (int) $fichaResenasStats['aprobadas'] + (int) $fichaResenasStats['rechazadas'];
        $LIM_RES       = 6;
        $idxRes        = 0;
        $nResOcultas   = max(0, $nResTotal - $LIM_RES);
        $colResAbierto = false;
        $lblVerRes     = 'Ver ' . $nResTotal . ' reseña' . ($nResTotal === 1 ? '' : 's') . ' resuelta' . ($nResTotal === 1 ? '' : 's');

        foreach ($fichaResenas as $resena):
            $rId      = (int) $resena['id'];
            $estInfo  = $estadoPill[$resena['estado']] ?? ['cls'=>'', 'label'=>ucfirst($resena['estado'])];
            $fteInfo  = $fuenteIconos[$resena['fuente']] ?? ['icono'=>'circle', 'lbl'=>$resena['fuente']];
            $resImgs  = $fichaImagenesPorResena[$rId] ?? [];
            $fecha    = date('d/m/Y H:i', strtotime($resena['created_at']));
            $esSpamR  = !empty($resena['es_spam']);
            $esPendR  = $resena['estado'] === 'pendiente';
        ?>
        <?php if (!$esPendR && !$colResAbierto): ?>
        </div><!-- /pendientes -->
        <div class="resenas-resueltas-wrap" style="margin-top:var(--espacio-uno);">
            <button type="button" id="btn-toggle-resueltas" onclick="toggleResenasResueltas()" class="boton dos pequeno" aria-expanded="false">
                <i data-lucide="chevron-down" class="icono" id="ic-toggle-resueltas" style="width:14px;height:14px;"></i>
                <span id="lbl-toggle-resueltas" data-lbl-ver="<?php echo htmlspecialchars($lblVerRes); ?>"><?php echo htmlspecialchars($lblVerRes); ?></span>
            </button>
            <div id="resenas-resueltas" style="display:none; flex-direction:column; gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
        <?php $colResAbierto = true; endif; ?>
        <?php
            if (!$esPendR) {
                $idxRes++;
                if ($idxRes > $LIM_RES) { continue; }
            }
        ?>
        <article id="ficha-resena-<?php echo $rId; ?>" style="border:1px solid <?php echo $esSpamR ? 'var(--admin-tone-error-bord)' : 'var(--admin-linea)'; ?>; border-radius:var(--admin-r-md); padding:var(--espacio-tres) var(--espacio-cuatro); background:var(--admin-superficie); box-shadow:var(--admin-sombra-suave); <?php echo $esSpamR ? 'background:linear-gradient(180deg, var(--admin-tone-error-bg) 0%, var(--admin-superficie) 30%);' : ''; ?>">

            <!-- Header de reseña: autor, estado, spam, estrellas, fecha, fuente -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:var(--espacio-tres); flex-wrap:wrap; margin-bottom:var(--espacio-tres);">
                <div style="min-width:0; flex:1;">
                    <div style="display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; margin-bottom:.2rem;">
                        <span style="font-weight:700; color:var(--admin-tinta-fuerte); font-size:var(--admin-h6); line-height:1.3;">
                            <?php echo htmlspecialchars($resena['nombre']); ?>
                        </span>
                        <span class="admin-pill <?php echo $estInfo['cls']; ?>"><?php echo $estInfo['label']; ?></span>
                        <?php if ($esSpamR): ?>
                        <span class="admin-pill admin-pill--stamp admin-pill--error">
                            <i data-lucide="alert-triangle" class="icono" style="width:11px; height:11px;"></i>
                            SPAM
                        </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);">
                        <a href="mailto:<?php echo htmlspecialchars($resena['email']); ?>" class="admin-link"><?php echo htmlspecialchars($resena['email']); ?></a>
                        <span class="admin-dash"></span>
                        <span style="display:inline-flex; align-items:center; gap:.3rem;" title="<?php echo htmlspecialchars($fteInfo['lbl']); ?>">
                            <i data-lucide="<?php echo $fteInfo['icono']; ?>" class="icono" style="width:13px; height:13px;"></i>
                            <?php echo htmlspecialchars($fteInfo['lbl']); ?>
                        </span>
                    </div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <div style="margin-bottom:.25rem; line-height:1;">
                        <?php echo generarEstrellas($resena['calificacion'], 16); ?>
                    </div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-tenue); font-variant-numeric:tabular-nums;">
                        <?php echo htmlspecialchars($fecha); ?>
                    </div>
                </div>
            </div>

            <!-- Comentario (read-only — texto original del cliente) -->
            <blockquote style="margin:0 0 var(--espacio-tres) 0; padding:var(--espacio-tres); background:var(--admin-papel-alt); border-radius:var(--admin-r-sm); font-size:var(--admin-body-sm); color:var(--admin-tinta); line-height:1.6; white-space:pre-wrap; word-wrap:break-word;">
                <?php echo htmlspecialchars($resena['comentario']); ?>
            </blockquote>

            <?php if ($resena['estado'] === 'rechazada' && !empty($resena['motivo_rechazo'])): ?>
            <div style="padding:var(--espacio-dos) var(--espacio-tres); background:var(--admin-tone-error-bg); border-radius:var(--admin-r-sm); margin-bottom:var(--espacio-tres); font-size:var(--admin-body-sm); color:var(--admin-tone-error-fg);">
                <strong>Motivo de rechazo:</strong> <?php echo htmlspecialchars($resena['motivo_rechazo']); ?>
            </div>
            <?php endif; ?>

            <!-- Imágenes adjuntas -->
            <?php if (!empty($resImgs)): ?>
            <div id="ficha-resena-imgs-<?php echo $rId; ?>" style="margin-bottom:var(--espacio-tres);">
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-bottom:.5rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap;">
                    <i data-lucide="images" class="icono" style="width:14px; height:14px;"></i>
                    <span><strong style="color:var(--admin-tinta); font-variant-numeric:tabular-nums;"><?php echo count($resImgs); ?></strong> imagen<?php echo count($resImgs) === 1 ? '' : 'es'; ?> adjunta<?php echo count($resImgs) === 1 ? '' : 's'; ?></span>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(110px, 1fr)); gap:.5rem;">
                <?php foreach ($resImgs as $resImg):
                    $resImgUrl = BASE_URL . '/' . ltrim(str_replace('\\','/',$resImg['ruta']), '/');
                    $resImgId  = (int) $resImg['id'];
                    $resImgDel = $resena['estado'] === 'pendiente' ? '1' : '0';
                ?>
                <div class="ficha-resena-thumb-wrap" id="ficha-resena-thumb-<?php echo $resImgId; ?>" style="position:relative; aspect-ratio:1; border-radius:var(--admin-r-sm); overflow:hidden; border:1px solid var(--admin-linea); background:var(--admin-papel-alt);">
                    <button type="button"
                            data-lbg-src="<?php echo htmlspecialchars($resImgUrl); ?>"
                            data-lbg-group="fres-<?php echo $rId; ?>"
                            data-lbg-alt="<?php echo htmlspecialchars($resImg['alt_text'] ?? ''); ?>"
                            data-lbg-nombre="<?php echo htmlspecialchars(basename($resImg['ruta'])); ?>"
                            data-lbg-id="<?php echo $resImgId; ?>"
                            data-lbg-del="<?php echo $resImgDel; ?>"
                            data-lbg-card="ficha-resena-thumb-<?php echo $resImgId; ?>"
                            aria-label="Ampliar imagen"
                            style="all:unset; cursor:zoom-in; display:block; width:100%; height:100%;">
                        <img src="<?php echo htmlspecialchars($resImgUrl); ?>"
                             alt="<?php echo htmlspecialchars($resImg['alt_text'] ?? ''); ?>"
                             loading="lazy"
                             style="width:100%; height:100%; object-fit:cover; display:block; pointer-events:none;"
                             onerror="this.style.display='none'">
                    </button>
                    <button type="button"
                            onclick="fichaResenaEliminarImg(<?php echo $resImgId; ?>, <?php echo $rId; ?>, this)"
                            title="Eliminar esta imagen"
                            aria-label="Eliminar imagen"
                            style="position:absolute; top:4px; right:4px; background:rgba(122,45,29,.92); color:#fff; border:0; border-radius:50%; width:24px; height:24px; cursor:pointer; display:grid; place-items:center; box-shadow:0 2px 6px rgba(0,0,0,.2);">
                        <i data-lucide="x" class="icono" style="width:13px; height:13px;"></i>
                    </button>
                    <?php if (!empty($resImg['estado_llm']) && $resImg['estado_llm'] === 'pendiente'): ?>
                    <span style="position:absolute; bottom:4px; left:4px; background:var(--admin-tone-alerta-fg); color:#fff; font-size:.625rem; padding:.1rem .4rem; border-radius:4px; font-weight:700; letter-spacing:.02em; pointer-events:none;">pendiente IA</span>
                    <?php elseif (!empty($resImg['categoria'])): ?>
                    <span style="position:absolute; bottom:4px; left:4px; background:rgba(44,36,23,.85); color:#fff; font-size:.625rem; padding:.1rem .4rem; border-radius:4px; font-weight:600; pointer-events:none;"><?php echo htmlspecialchars($resImg['categoria']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Acciones inline -->
            <div style="display:flex; gap:.5rem; justify-content:flex-end; flex-wrap:wrap; padding-top:var(--espacio-tres); border-top:1px solid var(--admin-linea); align-items:center;">

                <?php if ($resena['moderado_en'] && $resena['estado'] !== 'pendiente'): ?>
                <span style="font-size:var(--admin-body-sm); color:var(--admin-tinta-tenue); align-self:center; margin-right:auto;">
                    Moderada el <?php echo date('d/m/Y H:i', strtotime($resena['moderado_en'])); ?>
                </span>
                <?php endif; ?>

                <?php if ($resena['estado'] === 'pendiente'): ?>
                    <label class="ficha-spam-toggle" style="display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; background:var(--admin-tone-error-bg); border-radius:var(--admin-r-sm); font-size:var(--admin-body-sm); color:var(--admin-tone-error-fg); cursor:pointer; font-weight:600; user-select:none; margin-right:auto;"
                           title="Si la marcás como SPAM se trata como tal — al rechazar va directo a la tab SPAM.">
                        <input type="checkbox" class="field__check field__check--error" data-ficha-spam-toggle="<?php echo $rId; ?>"
                               onchange="fichaToggleSpamUI(<?php echo $rId; ?>, this.checked)">
                        Es SPAM
                    </label>
                    <button type="button" data-ficha-btn-aprobar="<?php echo $rId; ?>"
                            onclick="fichaResenaAccion(<?php echo $rId; ?>, 'aprobar')"
                            class="boton tres pequeno">
                        <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                        Aprobar
                    </button>
                    <button type="button" data-ficha-btn-rechazar="<?php echo $rId; ?>"
                            onclick="fichaResenaAccion(<?php echo $rId; ?>, 'rechazar')"
                            class="boton pequeno" style="background:var(--color-siete); color:var(--color-ocho); border-color:var(--color-siete);">
                        <i data-lucide="x" class="icono" style="width:14px; height:14px;"></i>
                        Rechazar
                    </button>
                <?php elseif ($resena['estado'] === 'aprobada'): ?>
                    <button type="button" onclick="fichaResenaAccion(<?php echo $rId; ?>, 'pausar')"
                            class="boton dos pequeno" title="Devuelve la reseña a 'pendiente' y la oculta del público hasta volver a aprobarla">
                        <i data-lucide="pause" class="icono" style="width:14px; height:14px;"></i>
                        Pausar para revisión
                    </button>
                <?php elseif ($resena['estado'] === 'rechazada'): ?>
                    <button type="button" onclick="fichaResenaAccion(<?php echo $rId; ?>, 'pausar')"
                            class="boton dos pequeno" title="Devuelve la reseña a 'pendiente' para revaluarla">
                        <i data-lucide="rotate-ccw" class="icono" style="width:14px; height:14px;"></i>
                        Reevaluar
                    </button>
                    <button type="button" onclick="fichaResenaAccion(<?php echo $rId; ?>, 'eliminar')"
                            class="boton pequeno"
                            style="background:var(--color-siete); color:var(--color-ocho); border-color:var(--color-siete);"
                            title="Eliminar definitivamente esta reseña y sus imágenes adjuntas">
                        <i data-lucide="trash-2" class="icono" style="width:14px; height:14px;"></i>
                        Eliminar definitivamente
                    </button>
                <?php endif; ?>

                <a href="resenas.php?estado=<?php echo $resena['estado']; ?>#resena-<?php echo $rId; ?>"
                   class="boton dos pequeno" target="_blank"
                   title="Abrir en el panel general de reseñas">
                    <i data-lucide="external-link" class="icono" style="width:14px; height:14px;"></i>
                    Ver en moderación
                </a>
            </div>

        </article>
        <?php endforeach; ?>
        <?php if ($colResAbierto): ?>
            <?php if ($nResOcultas > 0): ?>
            <div style="margin-top:var(--espacio-tres); text-align:center;">
                <a href="resenas.php?estado=todas&crematorio_id=<?php echo $id; ?>" target="_blank" class="boton dos pequeno"
                   title="Abrir el panel de moderación filtrado por esta ficha">
                    <i data-lucide="external-link" class="icono" style="width:14px;height:14px;"></i>
                    Hay <?php echo $nResOcultas; ?> resuelta<?php echo $nResOcultas === 1 ? '' : 's'; ?> más — ver todas en moderación
                </a>
            </div>
            <?php endif; ?>
            </div><!-- /#resenas-resueltas -->
        </div><!-- /.resenas-resueltas-wrap -->
        <?php else: ?>
        </div><!-- /pendientes -->
        <?php endif; ?>
        <?php endif; // empty($fichaResenas) ?>
    </section>
    <!-- ═══ FIN RESEÑAS DE CLIENTES ════════════════════════════════════════ -->
</div>

</div><!-- /.ficha-galerias -->
</div><!-- /.ficha-layout — grid (form/galerías en col 1; aside sticky en col 2) -->
</div><!-- /.admin-page -->

<!-- Scripts y modales: fuera del grid/marco (no son contenido visual de la página). -->

<script>
// Toggle del bloque colapsable de reseñas resueltas (aprobadas/rechazadas).
function toggleResenasResueltas() {
    var box = document.getElementById('resenas-resueltas');
    var btn = document.getElementById('btn-toggle-resueltas');
    var ic  = document.getElementById('ic-toggle-resueltas');
    var lbl = document.getElementById('lbl-toggle-resueltas');
    if (!box) return;
    var abierto = box.style.display !== 'none';
    box.style.display = abierto ? 'none' : 'flex';
    if (btn) btn.setAttribute('aria-expanded', String(!abierto));
    if (ic)  ic.setAttribute('data-lucide', abierto ? 'chevron-down' : 'chevron-up');
    if (lbl) lbl.textContent = abierto ? (lbl.dataset.lblVer || 'Ver resueltas') : 'Ocultar reseñas resueltas';
    if (window.lucide) lucide.createIcons();
}
</script>

<script>
// ─── Editor de fuentes de texto (descripción y meta) ──────────────────────
(function() {
    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function uid() { return '_' + Math.random().toString(36).slice(2,9); }
    function today() { return new Date().toISOString().slice(0,10); }
    function nowTimestamp() {
        const d = new Date(), pad = n => String(n).padStart(2,'0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    /**
     * Devuelve metadatos visuales para cada origen:
     * { icono, label, color (texto), bg (fondo), readonly (no se puede editar ni borrar) }
     */
    function origenMeta(origen) {
        const o = origen || 'admin_manual';
        if (o === 'admin_manual')      return { icono:'✍️', label:'Creado manualmente por Admin',         color:'#374151', bg:'#f3f4f6', readonly:false };
        if (o === 'auto')              return { icono:'⚙️', label:'Plantilla automática (sin IA)',        color:'#0e7490', bg:'#cffafe', readonly:false };
        if (o === 'manual_negocio')    return { icono:'🏢', label:'Creado por el negocio al registrarse', color:'#15803d', bg:'#dcfce7', readonly:true  };
        if (o.startsWith('seed_'))     return { icono:'📦', label:'Importado del semillado' + (o === 'seed_legacy' ? '' : ' · ' + o.replace('seed_','')), color:'#1d4ed8', bg:'#dbeafe', readonly:true };
        if (o.startsWith('llm_'))      return { icono:'🤖', label:'Procesado con IA ' + capitalize(o.replace('llm_','')), color:'#7c3aed', bg:'#ede9fe', readonly:false };
        if (o.startsWith('csv_'))      return { icono:'📥', label:'Importado vía CSV · ' + o.replace('csv_',''), color:'#0891b2', bg:'#cffafe', readonly:false };
        if (o === 'manual')            return { icono:'✍️', label:'Manual (legacy)',                       color:'#6b7280', bg:'#f3f4f6', readonly:false };
        return { icono:'📋', label:o, color:'#6b7280', bg:'#f3f4f6', readonly:false };
    }
    function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
    function fmtFecha(ts) {
        if (!ts) return '';
        // Aceptar 'YYYY-MM-DD HH:MM:SS' o 'YYYY-MM-DD'. Mostrar 'YYYY-MM-DD HH:MM' si hay hora, sino solo fecha.
        return ts.length > 10 ? ts.slice(0, 16) : ts;
    }

    function initFuentesEditor(opts) {
        let data = [];
        const abiertas = new Set();  // ids de versiones inactivas expandidas (estado UI)
        let sugeridaId = null;        // última versión agregada por IA (badge + scroll)

        function cargar() {
            try { data = JSON.parse(document.getElementById(opts.jsonInputId).value || '[]') || []; }
            catch(e) { data = []; }
            const activos = data.filter(d => d.activo);
            if (activos.length > 1) activos.slice(1).forEach(d => { d.activo = false; });
            if (data.length > 0 && !data.some(d => d.activo)) data[0].activo = true;
        }

        function syncHiddens() {
            document.getElementById(opts.jsonInputId).value = JSON.stringify(data);
            const activo = data.find(d => d.activo);
            const flatEl = document.getElementById(opts.flatInputId);
            if (flatEl) flatEl.value = activo ? (activo.valor || '') : '';
        }

        function setActivo(id) {
            data.forEach(d => { d.activo = d.id === id; });
            syncHiddens();
            render();
        }

        function delEntry(id) {
            const idx = data.findIndex(d => d.id === id);
            if (idx === -1) return;
            // Bloqueo: no se pueden borrar textos originales (seed_*, manual_negocio)
            const meta = origenMeta(data[idx].origen);
            if (meta.readonly) {
                toast.error('Esta entrada es un texto original (' + meta.label + ') y no puede ser borrada. Solo podés desactivarla.');
                return;
            }
            const wasActivo = data[idx].activo;
            data.splice(idx, 1);
            if (wasActivo && data.length > 0) data[0].activo = true;
            syncHiddens();
            render();
        }

        function addManual() {
            const newId = uid();
            data.push({
                id: newId,
                origen: 'admin_manual',
                valor: '',
                activo: false,
                creado_at: nowTimestamp(),
                editado_at: null,
            });
            syncHiddens();
            render();
            setTimeout(() => { const el = document.getElementById('fv-ta-' + newId); if (el) el.focus(); }, 50);
        }

        function updateValor(id, v) {
            const d = data.find(d => d.id === id);
            if (!d) return;
            // Bloqueo: no se puede editar el valor de textos originales
            const meta = origenMeta(d.origen);
            if (meta.readonly) return;
            // Solo marcar editado_at si el valor cambió
            if (d.valor !== v) {
                d.valor = v;
                d.editado_at = nowTimestamp();
                syncHiddens();
            }
            const ctr = document.getElementById('fv-ctr-' + id);
            if (!ctr) return;
            const n = v.length;
            const words = v.trim() ? v.trim().split(/\s+/).length : 0;
            if (opts.tipo === 'desc') {
                ctr.textContent = n + ' caracteres · ' + words + ' palabras';
                ctr.style.color = n >= 150 ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tone-error-fg)';
            } else {
                ctr.textContent = n + ' / ' + (opts.maxLen || 220);
                ctr.style.color = n > (opts.maxLen || 220) ? 'var(--admin-tone-error-fg)' : (n > 0 ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tinta-suave)');
            }
        }

        function ctrHtml(d) {
            const n = (d.valor||'').length;
            const words = d.valor && d.valor.trim() ? d.valor.trim().split(/\s+/).length : 0;
            let txt, clr;
            if (opts.tipo === 'desc') {
                txt = n + ' caracteres · ' + words + ' palabras';
                clr = n >= 150 ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tone-error-fg)';
            } else {
                txt = n + ' / ' + (opts.maxLen || 220);
                clr = n > (opts.maxLen || 220) ? 'var(--admin-tone-error-fg)' : (n > 0 ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tinta-suave)');
            }
            return { txt, clr };
        }

        function render() {
            const c = document.getElementById(opts.editorDivId);
            if (!c) return;

            if (data.length === 0) {
                c.innerHTML = `<div style="border:1px dashed var(--admin-linea-fuerte);border-radius:var(--admin-r-md);padding:var(--espacio-cuatro);text-align:center;color:var(--admin-tinta-suave);font-size:var(--admin-body-sm);">Sin versiones guardadas.<br>
                    <button type="button" onclick="fv_addManual_${opts.tipo}()" class="boton dos pequeno" style="margin-top:.6rem;"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir versión manual</button>
                </div>`;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }

            let html = '';
            data.forEach((d) => {
                const meta     = origenMeta(d.origen);
                const isActivo = !!d.activo;
                const esSug    = (d.id === sugeridaId) && !isActivo;
                const estaAbierta = abiertas.has(d.id);
                const isReadonly = meta.readonly;
                const preview  = (d.valor || '').slice(0, 120) + ((d.valor||'').length > 120 ? '…' : '');
                const { txt: ctrTxt, clr: ctrClr } = ctrHtml(d);

                // Línea de timestamps
                const tsPartes = [];
                if (d.creado_at) tsPartes.push('creado ' + escHtml(fmtFecha(d.creado_at)));
                if (d.editado_at) tsPartes.push('editado ' + escHtml(fmtFecha(d.editado_at)));
                const tsLinea = tsPartes.length
                    ? `<span style="font-size:var(--admin-caption);color:var(--admin-tinta-tenue);">${tsPartes.join(' · ')}</span>`
                    : '';

                // Modelo en itálica (solo si existe)
                const modeloLinea = d.modelo
                    ? `<span style="font-size:var(--admin-caption);color:var(--admin-tinta-tenue);font-style:italic;margin-left:.4rem;">${escHtml(d.modelo)}</span>`
                    : '';

                // Botón borrar — solo si NO es readonly
                const btnBorrar = !isReadonly
                    ? `<button type="button" onclick="fv_del_${opts.tipo}('${d.id}')" style="background:none;border:none;color:var(--admin-tone-error-fg);cursor:pointer;padding:.15rem;line-height:1;flex-shrink:0;display:grid;place-items:center;" title="Eliminar"><i data-lucide="x" style="width:14px;height:14px;"></i></button>`
                    : `<span title="Texto original — no se puede borrar, solo desactivar" style="color:var(--admin-tinta-tenue);flex-shrink:0;display:grid;place-items:center;"><i data-lucide="lock" style="width:13px;height:13px;"></i></span>`;

                html += `<div id="fv-entry-${d.id}" style="border:1px solid ${isActivo?'var(--admin-brand)':(esSug?'var(--admin-tone-alerta-fg)':'var(--admin-linea)')};border-radius:var(--admin-r-md);margin-bottom:var(--espacio-dos);overflow:hidden;${esSug?'box-shadow:0 0 0 2px var(--admin-tone-alerta-bg);':''}">
                    <div style="padding:var(--espacio-dos) var(--espacio-tres);background:${isActivo?'var(--admin-brand-soft)':'var(--admin-superficie)'};">
                        <div style="display:flex;align-items:center;gap:var(--espacio-dos);flex-wrap:wrap;">
                            <label class="field__opcion" style="font-size:var(--admin-body-sm);">
                                <input type="radio" class="field__radio" name="fv-radio-${opts.tipo}" ${isActivo?'checked':''} onchange="fv_setActivo_${opts.tipo}('${d.id}')">
                                <span>${isActivo ? '<strong style="color:var(--admin-brand-hover);">Activa</strong>' : 'Usar esta'}</span>
                            </label>
                            <span style="font-size:var(--admin-caption);font-weight:600;background:${meta.bg};color:${meta.color};padding:.15rem .55rem;border-radius:var(--admin-r-pill);flex-shrink:0;display:inline-flex;align-items:center;gap:.25rem;">${meta.icono} ${escHtml(meta.label)}</span>
                            ${modeloLinea}
                            <span style="margin-left:auto;font-size:var(--admin-caption);color:var(--admin-tinta-tenue);">${(d.valor||'').length} car.</span>
                            ${btnBorrar}
                        </div>
                        ${tsLinea ? `<div style="margin-top:.2rem;padding-left:1.4rem;">${tsLinea}</div>` : ''}
                    </div>
                    ${isActivo && !isReadonly ? `
                    <div style="padding:0 var(--espacio-tres) var(--espacio-tres);">
                        <textarea id="fv-ta-${d.id}" class="field__textarea" rows="${opts.tipo==='desc'?6:3}" ${opts.maxLen?`maxlength="${opts.maxLen}"`:''} style="margin-top:var(--espacio-dos);"
                                  oninput="fv_upd_${opts.tipo}('${d.id}',this.value)">${escHtml(d.valor||'')}</textarea>
                        <div id="fv-ctr-${d.id}" style="font-size:var(--admin-caption);margin-top:.25rem;color:${ctrClr};">${ctrTxt}</div>
                    </div>` : isActivo ? `
                    <div style="padding:var(--espacio-dos) var(--espacio-tres) var(--espacio-tres);">
                        <div style="font-size:var(--admin-body-sm);color:var(--admin-tinta);line-height:1.55;white-space:pre-wrap;">${escHtml(d.valor || '') || '<em style="opacity:.5">Sin contenido</em>'}</div>
                        <div style="font-size:var(--admin-caption);margin-top:.25rem;color:${ctrClr};">${ctrTxt}</div>
                    </div>` : `
                    <div>
                        ${esSug ? `<div style="display:flex;align-items:center;gap:.4rem;padding:var(--espacio-dos) var(--espacio-tres);background:var(--admin-tone-alerta-bg);color:var(--admin-tone-alerta-fg);font-size:var(--admin-caption);font-weight:600;line-height:1.4;"><i data-lucide="sparkles" style="width:13px;height:13px;flex-shrink:0;"></i><span>Sugerencia IA — guardada como borrador (no se pierde si recargás). Para publicarla: marcá "Usar esta" y "Guardar cambios".</span></div>` : ''}
                        <button type="button" onclick="fv_toggle_${opts.tipo}('${d.id}')" style="all:unset;cursor:pointer;box-sizing:border-box;display:flex;align-items:center;gap:.4rem;width:100%;padding:var(--espacio-dos) var(--espacio-tres);font-size:var(--admin-body-sm);color:var(--admin-tinta-suave);">
                            <i data-lucide="${estaAbierta?'chevron-down':'chevron-right'}" style="width:14px;height:14px;flex-shrink:0;"></i>
                            ${estaAbierta ? '<span style="font-weight:600;">Ocultar</span>' : `<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(preview) || '<em>Sin contenido</em>'}</span>`}
                        </button>
                        ${estaAbierta ? `<div style="padding:0 var(--espacio-tres) var(--espacio-tres);font-size:var(--admin-body-sm);color:var(--admin-tinta);line-height:1.55;white-space:pre-wrap;word-wrap:break-word;">${escHtml(d.valor||'') || '<em style="opacity:.5">Sin contenido</em>'}</div>` : ''}
                    </div>`}
                </div>`;
            });

            html += `<button type="button" onclick="fv_addManual_${opts.tipo}()" class="boton dos pequeno" style="width:100%;justify-content:center;margin-top:var(--espacio-uno);">
                <i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir versión manual
            </button>`;

            c.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // Agregar una entrada externa (ej: sugerencia del asistente IA).
        // entry: { origen, valor, modelo?, activo?, creado_at?, id? }. Si no hay id, se genera.
        // Si activo:true, se desactivan los demás. Si no, queda como nueva entrada inactiva.
        function addEntry(entry) {
            if (!entry || typeof entry.valor !== 'string') return null;
            const nuevo = {
                id:         entry.id        || uid(),
                origen:     entry.origen    || 'admin_manual',
                valor:      entry.valor,
                activo:     !!entry.activo,
                creado_at:  entry.creado_at || nowTimestamp(),
                editado_at: entry.editado_at || null,
            };
            if (entry.modelo) nuevo.modelo = entry.modelo;
            data.push(nuevo);
            if (nuevo.activo) {
                data.forEach(d => { if (d !== nuevo) d.activo = false; });
            }
            // Garantizar al menos una activa
            if (!data.some(d => d.activo) && data.length > 0) data[0].activo = true;
            // Entrada nueva → expandida para leerla/editarla al toque.
            abiertas.add(nuevo.id);
            const esIA = String(nuevo.origen || '').indexOf('llm') === 0;
            if (esIA) sugeridaId = nuevo.id;
            syncHiddens();
            render();
            if (esIA) setTimeout(function() {
                const el = document.getElementById('fv-entry-' + nuevo.id);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 60);
            return nuevo.id;
        }

        window['fv_setActivo_' + opts.tipo] = setActivo;
        window['fv_del_' + opts.tipo]       = delEntry;
        window['fv_addManual_' + opts.tipo] = addManual;
        window['fv_upd_' + opts.tipo]       = updateValor;
        window['fv_addEntry_' + opts.tipo]  = addEntry;
        window['fv_toggle_'   + opts.tipo]  = function(id) {
            if (abiertas.has(id)) abiertas.delete(id); else abiertas.add(id);
            render();
        };

        cargar();
        syncHiddens();
        render();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initFuentesEditor({ jsonInputId:'descripciones-json', flatInputId:'descripcion',       editorDivId:'desc-fuentes-editor', tipo:'desc' });
        initFuentesEditor({ jsonInputId:'metas-json',         flatInputId:'meta_description_seo', editorDivId:'meta-fuentes-editor', tipo:'meta', maxLen:220 });
        initFuentesEditor({ jsonInputId:'mensajes-whatsapp-json', flatInputId:'mensaje-whatsapp-flat', editorDivId:'whatsapp-fuentes-editor', tipo:'whatsapp', maxLen:500 });
    });
})();

// ─── Tag inputs (zona_cobertura / ciudades_cobertura) ──────────────────────
(function() {
    const ZONAS_LISTA = [
        'Andalucía','Aragón','Asturias','Islas Baleares','Canarias','Cantabria',
        'Castilla-La Mancha','Castilla y León','Cataluña','Extremadura','Galicia',
        'La Rioja','Madrid','Murcia','Navarra','País Vasco','Comunidad Valenciana',
        'Ceuta','Melilla',
        'Álava','Albacete','Alicante','Almería','Ávila','Badajoz','Barcelona','Burgos',
        'Cáceres','Cádiz','Castellón','Ciudad Real','Córdoba','La Coruña','Cuenca',
        'Gerona','Granada','Guadalajara','Guipúzcoa','Huelva','Huesca','Jaén',
        'Las Palmas','León','Lérida','Lugo','Málaga','Orense','Palencia','Pontevedra',
        'Salamanca','Santa Cruz de Tenerife','Segovia','Sevilla','Soria','Tarragona',
        'Teruel','Toledo','Valencia','Valladolid','Vizcaya','Zamora','Zaragoza'
    ];

    function escapeHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function initTagInput(wrap) {
        const targetId = wrap.dataset.target;
        const autocomp = wrap.dataset.autocomplete || null;
        const hidden   = document.getElementById(targetId);
        if (!hidden) return;

        let tags = hidden.value
            ? hidden.value.split(',').map(t => t.trim()).filter(Boolean)
            : [];
        let dropdown = null;
        let activeIdx = -1;

        function getList() {
            return autocomp === 'zonas' ? ZONAS_LISTA : [];
        }

        function showDropdown(inp, query) {
            const list = getList();
            if (!list.length) return;
            const q = query.toLowerCase().trim();
            if (!q) { hideDropdown(); return; }
            const filtered = list.filter(item =>
                item.toLowerCase().includes(q) && !tags.includes(item)
            );
            if (!filtered.length) { hideDropdown(); return; }

            if (!dropdown) {
                dropdown = document.createElement('div');
                dropdown.className = 'autocomplete-dropdown';
                wrap.appendChild(dropdown);
            }
            activeIdx = -1;
            dropdown.innerHTML = '';
            filtered.slice(0, 25).forEach(item => {
                const div = document.createElement('div');
                div.className = 'ac-item';
                div.textContent = item;
                div.onmousedown = (e) => {
                    e.preventDefault();
                    addTag(item);
                    inp.value = '';
                    hideDropdown();
                    setTimeout(() => wrap.querySelector('input')?.focus(), 0);
                };
                dropdown.appendChild(div);
            });
            dropdown.style.display = 'block';
        }

        function hideDropdown() {
            if (dropdown) dropdown.style.display = 'none';
            activeIdx = -1;
        }

        function navigateDropdown(dir) {
            if (!dropdown || dropdown.style.display === 'none') return false;
            const items = dropdown.querySelectorAll('.ac-item');
            if (!items.length) return false;
            items[activeIdx]?.classList.remove('active');
            activeIdx = Math.max(0, Math.min(items.length - 1, activeIdx + dir));
            items[activeIdx].classList.add('active');
            items[activeIdx].scrollIntoView({block:'nearest'});
            return true;
        }

        function render() {
            wrap.innerHTML = '';
            dropdown = null;

            const box = document.createElement('div');
            box.className = 'tag-box';
            box.onclick = () => inp.focus();

            tags.forEach((tag, i) => {
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.innerHTML = `${escapeHtml(tag)}<button type="button" title="Quitar">×</button>`;
                pill.querySelector('button').onclick = (e) => {
                    e.stopPropagation();
                    tags.splice(i, 1);
                    syncAndRender();
                };
                box.appendChild(pill);
            });

            const inp = document.createElement('input');
            inp.type = 'text';
            inp.placeholder = tags.length ? '' : 'Escribí y presioná Enter o coma';

            inp.addEventListener('input', () => showDropdown(inp, inp.value));

            inp.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') { e.preventDefault(); navigateDropdown(1); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); navigateDropdown(-1); return; }
                if (e.key === 'Escape')    { hideDropdown(); return; }
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const active = dropdown?.querySelector('.ac-item.active');
                    if (active) {
                        addTag(active.textContent);
                        inp.value = '';
                        hideDropdown();
                    } else if (inp.value.trim()) {
                        addTag(inp.value);
                        inp.value = '';
                        hideDropdown();
                    }
                } else if (e.key === 'Backspace' && !inp.value && tags.length) {
                    tags.pop();
                    syncAndRender();
                }
            });

            inp.addEventListener('blur', () => setTimeout(hideDropdown, 150));

            box.appendChild(inp);
            wrap.appendChild(box);
        }

        function addTag(raw) {
            raw.split(',').map(t => t.trim()).filter(Boolean).forEach(t => {
                if (!tags.includes(t)) tags.push(t);
            });
            syncAndRender();
        }

        function syncAndRender() {
            hidden.value = tags.join(', ');
            render();
            wrap.querySelector('input')?.focus();
        }

        render();
    }

    function init() {
        document.querySelectorAll('.tag-input-wrap').forEach(initTagInput);
    }

    // Expuesto para el Asistente IA: re-poblar un tag input desde un array.
    // Reemplaza completamente los tags actuales y re-renderiza el componente.
    window.iaSetTags = function(targetId, tagsArray) {
        const wrap = document.querySelector(`.tag-input-wrap[data-target="${targetId}"]`);
        const hidden = document.getElementById(targetId);
        if (!wrap || !hidden) return false;
        hidden.value = (Array.isArray(tagsArray) ? tagsArray : []).join(', ');
        wrap.innerHTML = '';
        initTagInput(wrap);
        return true;
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();
})();

// ─── Editor de contactos ──────────────────────────────────────────────────
(function() {
    const TEL_TIPOS = [
        {v:'principal',   lbl:'Teléfono principal',      privado:false},
        {v:'clientes',    lbl:'Atención a clientes',     privado:false},
        {v:'proveedores', lbl:'Proveedores',              privado:true },
        {v:'dueno',       lbl:'Dueño / Propietario',     privado:true },
        {v:'gerente',     lbl:'Gerente / Dirección',     privado:true },
        {v:'admin',       lbl:'Administración',           privado:true },
        {v:'custom',      lbl:'Otro (personalizado)',     privado:false},
    ];
    const EMAIL_TIPOS = [
        {v:'general',     lbl:'General / Contacto',      privado:false},
        {v:'clientes',    lbl:'Atención a clientes',     privado:false},
        {v:'facturacion', lbl:'Facturación',              privado:true },
        {v:'proveedores', lbl:'Proveedores',              privado:true },
        {v:'gerencia',    lbl:'Gerencia',                 privado:true },
        {v:'custom',      lbl:'Otro (personalizado)',     privado:false},
    ];
    const REDES_PRESET = [
        {v:'facebook',  lbl:'Facebook',        ico:'facebook',  color:'#1877f2', base:'https://facebook.com/',        ej:'https://facebook.com/tunegocio'},
        {v:'instagram', lbl:'Instagram',       ico:'instagram', color:'#e4405f', base:'https://instagram.com/',       ej:'https://instagram.com/tunegocio'},
        {v:'x',         lbl:'X (Twitter)',     ico:'twitter',   color:'#000000', base:'https://x.com/',               ej:'https://x.com/tunegocio'},
        {v:'tiktok',    lbl:'TikTok',          ico:'music-2',   color:'#010101', base:'https://tiktok.com/@',         ej:'https://tiktok.com/@tunegocio'},
        {v:'youtube',   lbl:'YouTube',         ico:'youtube',   color:'#ff0000', base:'https://youtube.com/@',        ej:'https://youtube.com/@tunegocio'},
        {v:'linkedin',  lbl:'LinkedIn',        ico:'linkedin',  color:'#0a66c2', base:'https://linkedin.com/company/', ej:'https://linkedin.com/company/tunegocio'},
        {v:'google',    lbl:'Google Business', ico:'map-pin',   color:'#4285f4', base:'',                             ej:'https://maps.google.com/?cid=...'},
        {v:'pinterest', lbl:'Pinterest',       ico:'image',     color:'#e60023', base:'https://pinterest.com/',       ej:'https://pinterest.com/tunegocio'},
        {v:'vimeo',     lbl:'Vimeo',           ico:'video',     color:'#1ab7ea', base:'https://vimeo.com/',           ej:'https://vimeo.com/tunegocio'},
    ];
    const REDES_MODOS = [
        {v:'iconos', lbl:'Solo iconos',     ej:'★ ★ ★'},
        {v:'handle', lbl:'Iconos + handle', ej:'★ @perfil  ★ /nombre'},
        {v:'lista',  lbl:'Lista completa',  ej:'Facebook · facebook.com/nombre'},
    ];

    function uid() { return '_' + Math.random().toString(36).slice(2,9); }
    function telInfo(v)  { return TEL_TIPOS.find(t=>t.v===v)   || TEL_TIPOS[TEL_TIPOS.length-1]; }
    function mailInfo(v) { return EMAIL_TIPOS.find(t=>t.v===v) || EMAIL_TIPOS[EMAIL_TIPOS.length-1]; }

    // ── Preview de redes sociales (datos reales, refleja la ficha) ──────────────
    function _redLimpiarUrl(url) {
        return String(url || '').trim()
            .replace(/^https?:\/\//i, '')
            .replace(/^www\./i, '')
            .replace(/\/+$/, '');
    }
    function _redHandle(url, redKey) {
        const clean = _redLimpiarUrl(url);
        if (!clean) return '';
        const slash = clean.indexOf('/');
        if (slash === -1) return clean;                 // solo dominio
        const path = clean.slice(slash + 1).split('/').filter(Boolean)[0] || '';
        if (!path) return clean;
        const arroba = ['instagram','x','tiktok','youtube','pinterest','vimeo'];
        return (arroba.includes(redKey) ? '@' : '/') + path;
    }
    function buildPreviewRedes(items, modo) {
        const vis = (items || []).filter(r => r.visible && (r.url || '').trim());
        if (!vis.length) {
            return `<span style="font-size:var(--admin-body-sm);color:var(--admin-tinta-tenue);font-style:italic;">Agregá una red social (con su URL) para ver el preview</span>`;
        }
        const meta = r => REDES_PRESET.find(p => p.v === r.red) || {ico:'link', color:'var(--admin-tinta-suave)', lbl:(r.label||'Red')};
        if (modo === 'lista') {
            return `<div style="display:flex;flex-direction:column;gap:.45rem;padding:.2rem 0;">` + vis.map(r => {
                const m = meta(r);
                return `<span style="display:flex;align-items:center;gap:.5rem;font-size:var(--admin-body-sm);color:var(--admin-tinta);">
                    <i data-lucide="${m.ico}" style="width:16px;height:16px;color:${m.color};flex-shrink:0;"></i>
                    <strong style="color:var(--admin-tinta-fuerte);">${r.label || m.lbl}</strong>
                    <span style="color:var(--admin-tinta-suave);">· ${_redLimpiarUrl(r.url)}</span>
                </span>`;
            }).join('') + `</div>`;
        }
        if (modo === 'handle') {
            return `<div style="display:flex;gap:1.1rem;flex-wrap:wrap;padding:.3rem 0;">` + vis.map(r => {
                const m = meta(r);
                return `<span style="display:inline-flex;align-items:center;gap:.35rem;font-size:var(--admin-body-sm);color:var(--admin-tinta);">
                    <i data-lucide="${m.ico}" style="width:18px;height:18px;color:${m.color};"></i>${_redHandle(r.url, r.red)}
                </span>`;
            }).join('') + `</div>`;
        }
        // iconos
        return `<div style="display:flex;gap:.7rem;flex-wrap:wrap;padding:.3rem 0;">` + vis.map(r => {
            const m = meta(r);
            return `<i data-lucide="${m.ico}" title="${r.label || m.lbl}" style="width:22px;height:22px;color:${m.color};"></i>`;
        }).join('') + `</div>`;
    }

    // Estado global expuesto para que el editor de horarios pueda leerlo
    window.cState = { telefonos:[], emails:[], redes:[], redesModo:'iconos' };

    function cargar() {
        try { window.cState.telefonos = JSON.parse(document.getElementById('telefonos-json').value||'[]')||[]; } catch(e){window.cState.telefonos=[];}
        try { window.cState.emails    = JSON.parse(document.getElementById('emails-json').value||'[]')||[];    } catch(e){window.cState.emails=[];}
        try {
            const rd = JSON.parse(document.getElementById('redes-json').value||'{}');
            if (Array.isArray(rd)) { window.cState.redes=rd; window.cState.redesModo='iconos'; }
            else { window.cState.redes=rd.entries||[]; window.cState.redesModo=rd.modo||'iconos'; }
        } catch(e){window.cState.redes=[];}
    }

    function sync() {
        document.getElementById('telefonos-json').value = JSON.stringify(window.cState.telefonos);
        document.getElementById('emails-json').value    = JSON.stringify(window.cState.emails);
        document.getElementById('redes-json').value     = JSON.stringify({modo:window.cState.redesModo, entries:window.cState.redes});
        const s=(id,v)=>{const el=document.getElementById(id);if(el)el.value=v||'';};
        s('tel-legacy',      (window.cState.telefonos.find(t=>t.tipo==='principal') || {}).numero);
        s('tel-cli-legacy',  (window.cState.telefonos.find(t=>t.tipo==='clientes')  || {}).numero);
        s('email-legacy',    (window.cState.emails.find(e=>e.tipo==='general')      || {}).email);
        s('email-cli-legacy',(window.cState.emails.find(e=>e.tipo==='clientes')     || {}).email);
        // Notificar al editor de horarios para que actualice el dropdown de teléfonos
        if (typeof window.hRenderTel === 'function') window.hRenderTel();
    }

    // ── Sección header helper ──────────────────────────────────────────────────
    function secHeader(ico, txt) {
        return `<div style="font-size:var(--admin-body-sm);font-weight:700;color:var(--admin-tinta-fuerte);margin-bottom:var(--espacio-dos);display:flex;align-items:center;gap:var(--espacio-dos);">
            <i data-lucide="${ico}" style="width:15px;height:15px;flex-shrink:0;color:var(--admin-brand);"></i>${txt}
        </div>`;
    }

    // ── Render teléfonos ───────────────────────────────────────────────────────
    function renderTels() {
        const c=document.getElementById('contacto-tel-editor'); if(!c) return;
        const items=window.cState.telefonos;
        let html=secHeader('phone','Teléfonos');
        items.forEach((tel,i)=>{
            const info=telInfo(tel.tipo);
            const priv=info.privado?`<div style="font-size:var(--admin-body-sm);color:var(--admin-tone-alerta-fg);background:var(--admin-tone-alerta-bg);border-radius:var(--admin-r-sm);padding:.3rem .7rem;margin-bottom:var(--espacio-dos);">⚠ Este número no es público ni visible en la ficha</div>`:'';
            html+=`<div style="padding:var(--espacio-tres) 0;${i<items.length-1?'border-bottom:1px solid var(--admin-linea);':''}${!tel.visible?'opacity:.55;':''}">
                ${priv}
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:var(--espacio-dos);align-items:center;">
                    <select class="field__select field__select--enhanced" data-ts-search="off" data-ts-portal="off" onchange="cTelTipo('${tel.id}',this.value)">
                        ${TEL_TIPOS.map(t=>`<option value="${t.v}"${tel.tipo===t.v?' selected':''}>${t.lbl}</option>`).join('')}
                    </select>
                    <input type="tel" class="field__input" placeholder="+34 600 000 000" value="${(tel.numero||'').replace(/"/g,'&quot;')}"
                           oninput="cTelNum('${tel.id}',this.value)">
                    <div style="display:flex;gap:.15rem;align-items:center;">
                        <button type="button" onclick="cTelUp(${i})" ${i===0?'disabled':''} title="Subir" aria-label="Subir" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===0?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-up" style="width:15px;height:15px;"></i></button>
                        <button type="button" onclick="cTelDn(${i})" ${i===items.length-1?'disabled':''} title="Bajar" aria-label="Bajar" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===items.length-1?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-down" style="width:15px;height:15px;"></i></button>
                        <button type="button" onclick="cTelDel('${tel.id}')" title="Eliminar" aria-label="Eliminar" style="background:none;border:none;cursor:pointer;color:var(--admin-tone-error-fg);display:grid;place-items:center;padding:.25rem;"><i data-lucide="x" style="width:15px;height:15px;"></i></button>
                    </div>
                </div>
                ${tel.tipo==='custom'?`<input type="text" class="field__input" placeholder="Nombre personalizado" value="${(tel.label||'').replace(/"/g,'&quot;')}" oninput="cTelLbl('${tel.id}',this.value)" style="margin-top:var(--espacio-dos);width:100%;">`:'' }
                <label class="field__opcion" style="margin-top:var(--espacio-dos);">
                    <input type="checkbox" class="field__check" ${tel.visible?'checked':''} onchange="cTelVis('${tel.id}',this.checked)">
                    <span>Visible en ficha${info.privado?' <span style="color:var(--admin-tone-alerta-fg);">(privado por defecto)</span>':''}</span>
                </label>
            </div>`;
        });
        html+=`<button type="button" onclick="cTelAdd()" class="boton dos pequeno" style="width:100%;justify-content:center;"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir teléfono</button>`;
        if(window.tsDestroyScope) tsDestroyScope(c);
        c.innerHTML=html;
        if(typeof lucide!=='undefined') lucide.createIcons();
        if(window.tsEnhanceScope) tsEnhanceScope(c);
    }

    // ── Render emails ──────────────────────────────────────────────────────────
    function renderEmails() {
        const c=document.getElementById('contacto-email-editor'); if(!c) return;
        const items=window.cState.emails;
        let html=secHeader('mail','Emails');
        items.forEach((mail,i)=>{
            const info=mailInfo(mail.tipo);
            const priv=info.privado?`<div style="font-size:var(--admin-body-sm);color:var(--admin-tone-alerta-fg);background:var(--admin-tone-alerta-bg);border-radius:var(--admin-r-sm);padding:.3rem .7rem;margin-bottom:var(--espacio-dos);">⚠ Este email no es público ni visible en la ficha</div>`:'';
            html+=`<div style="padding:var(--espacio-tres) 0;${i<items.length-1?'border-bottom:1px solid var(--admin-linea);':''}${!mail.visible?'opacity:.55;':''}">
                ${priv}
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:var(--espacio-dos);align-items:center;">
                    <select class="field__select field__select--enhanced" data-ts-search="off" data-ts-portal="off" onchange="cMailTipo('${mail.id}',this.value)">
                        ${EMAIL_TIPOS.map(t=>`<option value="${t.v}"${mail.tipo===t.v?' selected':''}>${t.lbl}</option>`).join('')}
                    </select>
                    <input type="email" class="field__input" placeholder="info@empresa.com" value="${(mail.email||'').replace(/"/g,'&quot;')}"
                           oninput="cMailDir('${mail.id}',this.value)">
                    <div style="display:flex;gap:.15rem;align-items:center;">
                        <button type="button" onclick="cMailUp(${i})" ${i===0?'disabled':''} title="Subir" aria-label="Subir" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===0?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-up" style="width:15px;height:15px;"></i></button>
                        <button type="button" onclick="cMailDn(${i})" ${i===items.length-1?'disabled':''} title="Bajar" aria-label="Bajar" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===items.length-1?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-down" style="width:15px;height:15px;"></i></button>
                        <button type="button" onclick="cMailDel('${mail.id}')" title="Eliminar" aria-label="Eliminar" style="background:none;border:none;cursor:pointer;color:var(--admin-tone-error-fg);display:grid;place-items:center;padding:.25rem;"><i data-lucide="x" style="width:15px;height:15px;"></i></button>
                    </div>
                </div>
                ${mail.tipo==='custom'?`<input type="text" class="field__input" placeholder="Nombre personalizado" value="${(mail.label||'').replace(/"/g,'&quot;')}" oninput="cMailLbl('${mail.id}',this.value)" style="margin-top:var(--espacio-dos);width:100%;">`:'' }
                <label class="field__opcion" style="margin-top:var(--espacio-dos);">
                    <input type="checkbox" class="field__check" ${mail.visible?'checked':''} onchange="cMailVis('${mail.id}',this.checked)">
                    <span>Visible en ficha${info.privado?' <span style="color:var(--admin-tone-alerta-fg);">(privado por defecto)</span>':''}</span>
                </label>
            </div>`;
        });
        html+=`<button type="button" onclick="cMailAdd()" class="boton dos pequeno" style="width:100%;justify-content:center;"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir email</button>`;
        if(window.tsDestroyScope) tsDestroyScope(c);
        c.innerHTML=html;
        if(typeof lucide!=='undefined') lucide.createIcons();
        if(window.tsEnhanceScope) tsEnhanceScope(c);
    }

    // ── Render redes sociales ──────────────────────────────────────────────────
    function renderRedes() {
        const c=document.getElementById('contacto-redes-editor'); if(!c) return;
        const items=window.cState.redes;
        let html=secHeader('share-2','Redes sociales');
        html+=`<div style="margin-bottom:var(--espacio-tres);">
            <div style="font-size:var(--admin-body-sm);color:var(--admin-tinta-suave);margin-bottom:var(--espacio-dos);">Modo de visualización en la ficha:</div>
            <div style="display:flex;gap:var(--espacio-cuatro);flex-wrap:wrap;margin-bottom:var(--espacio-dos);">
                ${REDES_MODOS.map(m=>`<label class="field__opcion">
                    <input type="radio" class="field__radio" name="redes-modo" value="${m.v}" ${window.cState.redesModo===m.v?'checked':''} onchange="cRedesModo(this.value)"><span>${m.lbl}</span>
                </label>`).join('')}
            </div>
            <div style="background:var(--admin-papel-alt);border-radius:var(--admin-r-sm);padding:var(--espacio-dos) var(--espacio-tres);">
                <span style="font-size:var(--admin-body-sm);color:var(--admin-tinta-tenue);display:block;margin-bottom:.35rem;">Preview (así se verá en la ficha):</span>
                ${buildPreviewRedes(items, window.cState.redesModo)}
            </div>
        </div>`;
        items.forEach((red,i)=>{
            const pr=REDES_PRESET.find(r=>r.v===red.red);
            html+=`<div style="padding:var(--espacio-dos) 0;${i<items.length-1?'border-bottom:1px solid var(--admin-linea);':''}display:grid;grid-template-columns:20px 120px 1fr auto;gap:var(--espacio-dos);align-items:center;${!red.visible?'opacity:.55;':''}">
                <i data-lucide="${pr?.ico||'link'}" style="width:16px;height:16px;color:${pr?.color||'var(--admin-tinta-suave)'};flex-shrink:0;"></i>
                <span style="font-size:var(--admin-body-sm);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--admin-tinta-fuerte);">${red.label||pr?.lbl||'Red social'}</span>
                <input type="url" class="field__input" placeholder="${(pr?.ej||'https://...').replace(/"/g,'&quot;')}" value="${(red.url||'').replace(/"/g,'&quot;')}"
                       oninput="cRedUrl('${red.id}',this.value)">
                <div style="display:flex;gap:.15rem;align-items:center;">
                    <label title="Visible en ficha" class="field__opcion" style="gap:0;">
                        <input type="checkbox" class="field__check" ${red.visible?'checked':''} onchange="cRedVis('${red.id}',this.checked)">
                    </label>
                    <button type="button" onclick="cRedUp(${i})" ${i===0?'disabled':''} title="Subir" aria-label="Subir" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===0?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-up" style="width:15px;height:15px;"></i></button>
                    <button type="button" onclick="cRedDn(${i})" ${i===items.length-1?'disabled':''} title="Bajar" aria-label="Bajar" style="background:none;border:none;cursor:pointer;color:var(--admin-tinta-suave);opacity:${i===items.length-1?.3:1};display:grid;place-items:center;padding:.25rem;"><i data-lucide="chevron-down" style="width:15px;height:15px;"></i></button>
                    <button type="button" onclick="cRedDel('${red.id}')" title="Eliminar" aria-label="Eliminar" style="background:none;border:none;cursor:pointer;color:var(--admin-tone-error-fg);display:grid;place-items:center;padding:.25rem;"><i data-lucide="x" style="width:15px;height:15px;"></i></button>
                </div>
            </div>`;
        });
        const yaPresentes=new Set(items.filter(r=>r.red!=='custom').map(r=>r.red));
        const disp=REDES_PRESET.filter(r=>!yaPresentes.has(r.v));
        html+=`<div style="display:flex;gap:var(--espacio-dos);flex-wrap:wrap;align-items:center;">
            ${disp.length?`<div style="flex:1;min-width:180px;"><select id="redes-add-sel" class="field__select field__select--enhanced" data-ts-search="off" data-ts-portal="off">${disp.map(r=>`<option value="${r.v}">${r.lbl}</option>`).join('')}</select></div>
            <button type="button" onclick="cRedAdd(document.getElementById('redes-add-sel').value)" class="boton dos pequeno" style="white-space:nowrap;"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Añadir</button>`:''}
            <button type="button" onclick="cRedCustom()" class="boton dos pequeno" style="white-space:nowrap;"><i data-lucide="plus" class="icono" style="width:14px;height:14px;"></i> Red personalizada</button>
        </div>`;
        if(window.tsDestroyScope) tsDestroyScope(c);
        c.innerHTML=html;
        if(typeof lucide!=='undefined') lucide.createIcons();
        if(window.tsEnhanceScope) tsEnhanceScope(c);
    }

    function renderAll() { renderTels(); renderEmails(); renderRedes(); sync(); }

    // ── Acciones — teléfonos ───────────────────────────────────────────────────
    window.cTelAdd  = ()      => { window.cState.telefonos.push({id:uid(),tipo:'principal',label:'Teléfono principal',numero:'',visible:true}); renderTels(); sync(); };
    window.cTelDel  = id      => { window.cState.telefonos=window.cState.telefonos.filter(t=>t.id!==id); renderTels(); sync(); };
    window.cTelNum  = (id,v)  => { const t=window.cState.telefonos.find(t=>t.id===id); if(t){t.numero=v; sync();} };
    window.cTelLbl  = (id,v)  => { const t=window.cState.telefonos.find(t=>t.id===id); if(t){t.label=v; sync();} };
    window.cTelVis  = (id,v)  => { const t=window.cState.telefonos.find(t=>t.id===id); if(t){t.visible=v; renderTels(); sync();} };
    window.cTelTipo = (id,v)  => {
        const t=window.cState.telefonos.find(t=>t.id===id); if(!t) return;
        const info=telInfo(v); t.tipo=v; t.label=v==='custom'?(t.label||''):info.lbl;
        if(info.privado) t.visible=false;
        renderTels(); sync();
    };
    window.cTelUp   = i => { const a=window.cState.telefonos; if(i>0){[a[i-1],a[i]]=[a[i],a[i-1]]; renderTels(); sync();} };
    window.cTelDn   = i => { const a=window.cState.telefonos; if(i<a.length-1){[a[i],a[i+1]]=[a[i+1],a[i]]; renderTels(); sync();} };

    // ── Acciones — emails ──────────────────────────────────────────────────────
    window.cMailAdd  = ()      => { window.cState.emails.push({id:uid(),tipo:'general',label:'General / Contacto',email:'',visible:true}); renderEmails(); sync(); };
    window.cMailDel  = id      => { window.cState.emails=window.cState.emails.filter(e=>e.id!==id); renderEmails(); sync(); };
    window.cMailDir  = (id,v)  => { const m=window.cState.emails.find(e=>e.id===id); if(m){m.email=v; sync();} };
    window.cMailLbl  = (id,v)  => { const m=window.cState.emails.find(e=>e.id===id); if(m){m.label=v; sync();} };
    window.cMailVis  = (id,v)  => { const m=window.cState.emails.find(e=>e.id===id); if(m){m.visible=v; renderEmails(); sync();} };
    window.cMailTipo = (id,v)  => {
        const m=window.cState.emails.find(e=>e.id===id); if(!m) return;
        const info=mailInfo(v); m.tipo=v; m.label=v==='custom'?(m.label||''):info.lbl;
        if(info.privado) m.visible=false;
        renderEmails(); sync();
    };
    window.cMailUp   = i => { const a=window.cState.emails; if(i>0){[a[i-1],a[i]]=[a[i],a[i-1]]; renderEmails(); sync();} };
    window.cMailDn   = i => { const a=window.cState.emails; if(i<a.length-1){[a[i],a[i+1]]=[a[i+1],a[i]]; renderEmails(); sync();} };

    // ── Acciones — redes ───────────────────────────────────────────────────────
    window.cRedAdd    = v  => { const pr=REDES_PRESET.find(r=>r.v===v); window.cState.redes.push({id:uid(),red:v,label:pr?.lbl||v,url:pr?.base||'',visible:true}); renderRedes(); sync(); };
    window.cRedCustom = () => { const lbl=prompt('Nombre de la red social:'); if(!lbl) return; window.cState.redes.push({id:uid(),red:'custom',label:lbl,url:'',visible:true}); renderRedes(); sync(); };
    window.cRedDel    = id => { window.cState.redes=window.cState.redes.filter(r=>r.id!==id); renderRedes(); sync(); };
    window.cRedUrl    = (id,v) => { const r=window.cState.redes.find(r=>r.id===id); if(r){r.url=v; sync();} };
    window.cRedVis    = (id,v) => { const r=window.cState.redes.find(r=>r.id===id); if(r){r.visible=v; renderRedes(); sync();} };
    window.cRedUp     = i => { const a=window.cState.redes; if(i>0){[a[i-1],a[i]]=[a[i],a[i-1]]; renderRedes(); sync();} };
    window.cRedDn     = i => { const a=window.cState.redes; if(i<a.length-1){[a[i],a[i+1]]=[a[i+1],a[i]]; renderRedes(); sync();} };
    window.cRedesModo = v  => { window.cState.redesModo=v; renderRedes(); sync(); };

    function init() { cargar(); renderAll(); }
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init) : init();
})();

// ─── Editor de horarios ────────────────────────────────────────────────────
(function() {
    const DIAS_LV     = ['lunes','martes','miercoles','jueves','viernes'];
    const LABELS      = {lv:'Lunes – Viernes', lunes:'Lunes', martes:'Martes',
                         miercoles:'Miércoles', jueves:'Jueves', viernes:'Viernes',
                         s:'Sábado', d:'Domingo'};
    const PREFIJOS    = {lv:'L-V', lunes:'L', martes:'M', miercoles:'X',
                         jueves:'J', viernes:'V', s:'S', d:'D'};
    const MODOS       = ['no_especificado','cerrado','24h','horario'];
    const MODO_LABELS = {no_especificado:'No especificado', cerrado:'Cerrado', '24h':'24h', horario:'Horario'};

    // ── Dropdowns de hora (grilla 30 min) + formato de visualización ──────────
    // El value SIEMPRE es "HH:MM" 24h (no cambia el JSON/horario_texto ni la
    // ficha pública). El toggle solo cambia las ETIQUETAS del admin.
    const HFMT_KEY = 'admin_horario_fmt12';
    let h12 = (function(){ try { return localStorage.getItem(HFMT_KEY) === '1'; } catch(e){ return false; } })();
    function horaLabel(hhmm) {
        if (!h12) return hhmm;
        const p = hhmm.split(':'); const H = +p[0], M = +p[1];
        const ap = H < 12 ? 'AM' : 'PM'; let h = H % 12; if (h === 0) h = 12;
        return h + ':' + String(M).padStart(2,'0') + ' ' + ap;
    }
    function horaOpciones(sel) {
        const grid = [];
        for (let H = 0; H < 24; H++) for (let M = 0; M < 60; M += 30)
            grid.push(String(H).padStart(2,'0') + ':' + String(M).padStart(2,'0'));
        // Preservar una hora guardada fuera de la grilla (ej. 09:15) sin alterarla
        if (sel && grid.indexOf(sel) === -1) grid.push(sel);
        grid.sort();
        return grid.map(v => `<option value="${v}"${v === sel ? ' selected' : ''}>${horaLabel(v)}</option>`).join('');
    }
    window.hFmtToggle = function() {
        h12 = !h12;
        try { localStorage.setItem(HFMT_KEY, h12 ? '1' : '0'); } catch(e){}
        render();
    };

    function mkG() {
        const g = {};
        [...DIAS_LV, 'lv', 's', 'd'].forEach(k => { g[k] = {modo:'no_especificado', turnos:[['09:00','18:00']]}; });
        return g;
    }

    const state = {
        lvMode: 'grouped', g: mkG(),
        tel: { activa:false, modo:'24h', telefono:'', horarioData:{ lvMode:'grouped', g:mkG() } },
        nota: ''
    };

    // ── Parse ───────────────────────────────────────────────────────────────
    function parseVal(val) {
        if (val === null || val === undefined) return {modo:'cerrado',   turnos:[['09:00','18:00']]};
        if (val === '')                        return {modo:'no_especificado', turnos:[['09:00','18:00']]};
        if (val === '24h')                     return {modo:'24h',       turnos:[['09:00','18:00']]};
        const turnos = String(val).split(' y ').map(t => {
            const p = t.trim().split('-');
            return [p[0]||'09:00', p[1]||'18:00'];
        }).filter(([a,c]) => a && c);
        return {modo:'horario', turnos: turnos.length ? turnos : [['09:00','18:00']]};
    }

    function loadInto(target, src) {
        const hasInd = DIAS_LV.some(d => d in src);
        if (hasInd) {
            target.lvMode = 'individual';
            DIAS_LV.forEach(d => { target.g[d] = parseVal(src[d]); });
            target.g.lv = JSON.parse(JSON.stringify(target.g.lunes));
        } else {
            target.lvMode = 'grouped';
            target.g.lv = parseVal('lv' in src ? src.lv : undefined);
            DIAS_LV.forEach(d => { target.g[d] = JSON.parse(JSON.stringify(target.g.lv)); });
        }
        target.g.s = parseVal('s' in src ? src.s : undefined);
        target.g.d = parseVal('d' in src ? src.d : undefined);
    }

    function cargarJSON(raw) {
        try {
            const data = JSON.parse(raw || '{}');
            loadInto(state, ('presencial' in data) ? data.presencial : data);
            if (data.telefonica) {
                state.tel.activa   = !!data.telefonica.activa;
                state.tel.modo     = data.telefonica.modo || '24h';
                state.tel.telefono = data.telefonica.telefono || '';
                if (data.telefonica.horario) loadInto(state.tel.horarioData, data.telefonica.horario);
            }
            if (data.nota) state.nota = data.nota;
        } catch(e) { /* keep defaults */ }
    }

    // ── Generar salidas ─────────────────────────────────────────────────────
    function fmtTurnos(turnos) { return turnos.map(([a,c]) => a+'-'+c).join(' y '); }

    function gStr(g, k) {
        const {modo, turnos} = g[k];
        if (modo === 'cerrado')         return null;
        if (modo === 'no_especificado') return '';
        if (modo === '24h')             return '24h';
        return fmtTurnos(turnos);
    }

    function buildObj(target) {
        const out = {};
        if (target.lvMode === 'grouped') {
            out.lv = gStr(target.g, 'lv');
        } else {
            DIAS_LV.forEach(d => { out[d] = gStr(target.g, d); });
        }
        out.s = gStr(target.g, 's');
        out.d = gStr(target.g, 'd');
        return out;
    }

    function strLbl(v) { return v === null ? 'Cerrado' : v === '' ? '—' : v; }

    function toJSON() {
        const out = { presencial: buildObj(state) };
        if (state.tel.activa) {
            out.telefonica = { activa:true, modo:state.tel.modo, telefono:state.tel.telefono||'' };
            if (state.tel.modo === 'horario') out.telefonica.horario = buildObj(state.tel.horarioData);
        }
        if (state.nota.trim()) out.nota = state.nota.trim();
        return out;
    }

    function toTexto() {
        const p = [];
        if (state.lvMode === 'grouped') {
            p.push('L-V: ' + strLbl(gStr(state.g,'lv')));
        } else {
            DIAS_LV.forEach(d => p.push(PREFIJOS[d]+': '+strLbl(gStr(state.g,d))));
        }
        p.push('S: '+strLbl(gStr(state.g,'s')));
        p.push('D: '+strLbl(gStr(state.g,'d')));
        if (state.tel.activa) {
            const td = state.tel.horarioData;
            if (state.tel.modo === '24h')             p.push('Tel: 24h');
            else if (state.tel.modo === 'presencial')  p.push('Tel: mismo horario presencial');
            else if (state.tel.modo === 'horario') {
                if (td.lvMode === 'grouped') p.push('Tel L-V: '+strLbl(gStr(td.g,'lv')));
                else DIAS_LV.forEach(d => p.push('Tel '+PREFIJOS[d]+': '+strLbl(gStr(td.g,d))));
                p.push('Tel S: '+strLbl(gStr(td.g,'s')));
                p.push('Tel D: '+strLbl(gStr(td.g,'d')));
            }
            if (state.tel.telefono) p.push(state.tel.telefono);
        }
        if (state.nota.trim()) p.push('Nota: '+state.nota.trim());
        return p.join(' | ');
    }

    function syncHiddens() {
        document.getElementById('horarios-json').value   = JSON.stringify(toJSON());
        document.getElementById('horario-txt-hid').value = toTexto();
        const pre = document.querySelector('#json-horario-preview textarea');
        if (pre) pre.value = JSON.stringify(toJSON(), null, 2);
    }

    // ── HTML genérico (reutilizado por presencial y telefónica) ─────────────
    // prefix: string usado para radio names y nombres de handlers ('P' o 'T')
    // cls:    clase CSS de las filas (para querySelector)
    function turnoHTMLGen(prefix, cls, k, ap, ci, isFirst) {
        const addBtn = isFirst
            ? `<button type="button" onclick="h${prefix}AddTurno('${k}')" class="boton dos pequeno" style="white-space:nowrap;"><i data-lucide="plus" class="icono" style="width:13px;height:13px;"></i> Turno tarde</button>`
            : `<button type="button" onclick="h${prefix}QuitarTurno(this,'${k}')" title="Quitar turno" style="background:none;border:none;color:var(--admin-tone-error-fg);cursor:pointer;display:grid;place-items:center;padding:.25rem;"><i data-lucide="x" style="width:15px;height:15px;"></i></button>`;
        return `<div class="t-fila" style="display:flex;align-items:center;gap:var(--espacio-dos);flex-wrap:wrap;margin-bottom:var(--espacio-uno);">
            <select class="t-ap field__select" style="width:auto;min-width:96px;" onchange="h${prefix}Leer('${k}')">${horaOpciones(ap)}</select>
            <span style="color:var(--admin-tinta-tenue);">–</span>
            <select class="t-ci field__select" style="width:auto;min-width:96px;" onchange="h${prefix}Leer('${k}')">${horaOpciones(ci)}</select>
            ${addBtn}
        </div>`;
    }

    function filaHTMLGen(prefix, cls, g, k, extraBtnHtml) {
        const {modo, turnos} = g[k];
        const radios = MODOS.map(m =>
            `<label class="field__opcion" style="font-size:var(--admin-body-sm);">
                <input type="radio" class="field__radio" name="${prefix}-modo-${k}" value="${m}" ${modo===m?'checked':''} onchange="h${prefix}Modo('${k}',this.value)">
                <span>${MODO_LABELS[m]}</span>
            </label>`
        ).join('');
        const turnosHtml = turnos.map(([a,c],i) => turnoHTMLGen(prefix,cls,k,a,c,i===0)).join('');
        return `<div class="${cls}" data-k="${k}" style="display:grid;grid-template-columns:140px 1fr;align-items:start;border-bottom:1px solid var(--admin-linea);padding:var(--espacio-tres);">
            <span style="font-size:var(--admin-body-sm);font-weight:600;color:var(--admin-tinta-fuerte);padding-top:.35rem;">${LABELS[k]}</span>
            <div>
                <div style="display:flex;gap:var(--espacio-dos);margin-bottom:var(--espacio-dos);flex-wrap:wrap;align-items:center;">
                    ${radios}${extraBtnHtml||''}
                </div>
                <div class="t-wrap" style="display:${modo==='horario'?'block':'none'};">${turnosHtml}</div>
            </div>
        </div>`;
    }

    // Shorthands
    const CLS_P = 'h-fila-p', CLS_T = 'h-fila-t';
    function filaHTML(k, extra)    { return filaHTMLGen('P', CLS_P, state.g, k, extra); }
    function telFilaHTML(k, extra) { return filaHTMLGen('T', CLS_T, state.tel.horarioData.g, k, extra); }

    // ── Render sub-editor de horario telefónico ─────────────────────────────
    function renderTelSubEditor() {
        const sub = document.getElementById('tel-horario-sub');
        if (!sub) return;
        const td = state.tel.horarioData;
        let html = '';
        if (td.lvMode === 'grouped') {
            const btn = `<button type="button" onclick="hTDesglosar()" style="font-size:var(--admin-caption);background:none;border:none;color:var(--admin-tinta-tenue);cursor:pointer;text-decoration:underline;padding:0;margin-left:var(--espacio-dos);display:inline-flex;align-items:center;gap:.25rem;">Día por día <i data-lucide="chevron-down" style="width:13px;height:13px;"></i></button>`;
            html += telFilaHTML('lv', btn);
        } else {
            html += `<div style="display:grid;grid-template-columns:140px 1fr;align-items:center;border-bottom:1px solid var(--admin-linea);padding:var(--espacio-dos) var(--espacio-tres);background:var(--admin-papel-alt);">
                <span style="font-size:var(--admin-body-sm);color:var(--admin-tinta-fuerte);font-weight:600;">Días laborables</span>
                <button type="button" onclick="hTAgrupar()" style="font-size:var(--admin-body-sm);background:none;border:none;color:var(--admin-brand);cursor:pointer;text-decoration:underline;text-align:left;padding:0;display:inline-flex;align-items:center;gap:.25rem;"><i data-lucide="chevron-up" style="width:13px;height:13px;"></i> Agrupar Lun–Vie</button>
            </div>`;
            DIAS_LV.forEach(d => { html += telFilaHTML(d, ''); });
        }
        html += telFilaHTML('s', '');
        html += telFilaHTML('d', '');
        sub.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        const filas = sub.querySelectorAll('.'+CLS_T);
        if (filas.length) filas[filas.length-1].style.borderBottom = 'none';
    }

    function buildTelSelect(currentVal) {
        const opts = (window.cState?.telefonos || []).filter(t => t.visible);
        if (!opts.length) return `<span style="font-size:var(--admin-body-sm);color:var(--admin-tinta-suave);font-style:italic;">Sin teléfonos públicos — añade uno en la sección Contacto</span>`;
        const options = `<option value="">— Seleccionar —</option>` +
            opts.map(t => `<option value="${t.numero.replace(/"/g,'&quot;')}" ${currentVal===t.numero?'selected':''}>${t.label}: ${t.numero}</option>`).join('');
        // Tom Select SIN data-ts-portal="off" → dropdownParent:'body' (escapa el
        // overflow:hidden de la tarjeta; el panel no se recorta). renderTel hace
        // tsDestroyScope(sec) antes de cada innerHTML → sin paneles huérfanos.
        return `<select class="field__select field__select--enhanced" data-ts-search="off" onchange="hTelTelefono(this.value)">${options}</select>`;
    }

    // Expuesto para que el editor de contactos pueda forzar un re-render al cambiar teléfonos
    window.hRenderTel = function() {
        if (state.tel.activa) renderTel();
    };

    function renderTel() {
        const sec = document.getElementById('tel-section');
        if (!sec) return;
        const { activa, modo, telefono } = state.tel;
        const telModos = [
            { v:'24h',        lbl:'24 horas' },
            { v:'presencial', lbl:'Mismo horario presencial' },
            { v:'horario',    lbl:'Horario personalizado' },
        ];
        if (window.tsDestroyScope) tsDestroyScope(sec);
        sec.innerHTML = `
        <div style="border:1px solid var(--admin-linea);border-radius:var(--admin-r-md);overflow:hidden;">
            <div style="padding:var(--espacio-tres);${activa?'border-bottom:1px solid var(--admin-linea);':''}display:flex;align-items:center;gap:var(--espacio-dos);background:var(--admin-papel-alt);">
                <label class="field__opcion" style="font-weight:600;">
                    <input type="checkbox" class="field__switch" ${activa?'checked':''} onchange="hTelActiva(this.checked)">
                    <span>Atención telefónica disponible</span>
                </label>
            </div>
            ${activa ? `
            <div style="padding:var(--espacio-tres);display:flex;flex-direction:column;gap:var(--espacio-tres);">
                <div style="display:flex;gap:var(--espacio-cuatro);flex-wrap:wrap;align-items:center;">
                    ${telModos.map(({v,lbl}) =>
                        `<label class="field__opcion" style="font-size:var(--admin-body-sm);">
                            <input type="radio" class="field__radio" name="tel-modo" value="${v}" ${modo===v?'checked':''} onchange="hTelModo(this.value)">
                            <span>${lbl}</span>
                        </label>`
                    ).join('')}
                </div>
                ${modo === 'horario' ? `<div id="tel-horario-sub" style="border:1px solid var(--admin-linea);border-radius:var(--admin-r-sm);overflow:hidden;"></div>` : ''}
                <div style="display:flex;align-items:center;gap:var(--espacio-dos);">
                    <label style="font-size:var(--admin-body-sm);font-weight:600;color:var(--admin-tinta-fuerte);white-space:nowrap;">Teléfono:</label>
                    ${buildTelSelect(telefono)}
                </div>
            </div>
            ` : ''}
        </div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        if (window.tsEnhanceScope) tsEnhanceScope(sec);
        if (activa && modo === 'horario') renderTelSubEditor();
    }

    function renderNota() {
        const sec = document.getElementById('nota-horario');
        if (!sec) return;
        sec.innerHTML = `
        <div style="margin-top:var(--espacio-dos);">
            <label style="display:block;font-size:var(--admin-body-sm);font-weight:600;color:var(--admin-tinta-fuerte);margin-bottom:.3rem;">Nota adicional (opcional)</label>
            <textarea class="field__textarea" rows="2" placeholder="Ej: Cerrado en agosto. Festivos con cita previa."
                      oninput="hNota(this.value)">${state.nota.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
        </div>`;
    }

    // ── Render completo ─────────────────────────────────────────────────────
    function render() {
        const c = document.getElementById('horario-editor');
        let html = '';
        html += `<div style="display:flex;justify-content:flex-end;align-items:center;gap:.5rem;padding:var(--espacio-dos) var(--espacio-tres);border-bottom:1px solid var(--admin-linea);background:var(--admin-papel-alt);">
            <span style="font-size:var(--admin-caption);color:var(--admin-tinta-tenue);">Formato de hora</span>
            <button type="button" onclick="hFmtToggle()" class="boton dos pequeno" title="Cambiar entre 24h y AM/PM (solo visual, no afecta la ficha pública)">
                <i data-lucide="clock" class="icono" style="width:13px;height:13px;"></i> ${h12 ? 'AM/PM' : '24h'}
            </button>
        </div>`;
        if (state.lvMode === 'grouped') {
            const btn = `<button type="button" onclick="hDesglosar()" style="font-size:var(--admin-caption);background:none;border:none;color:var(--admin-tinta-tenue);cursor:pointer;text-decoration:underline;padding:0;margin-left:var(--espacio-dos);display:inline-flex;align-items:center;gap:.25rem;">Día por día <i data-lucide="chevron-down" style="width:13px;height:13px;"></i></button>`;
            html += filaHTML('lv', btn);
        } else {
            html += `<div style="display:grid;grid-template-columns:140px 1fr;align-items:center;border-bottom:1px solid var(--admin-linea);padding:var(--espacio-dos) var(--espacio-tres);background:var(--admin-papel-alt);">
                <span style="font-size:var(--admin-body-sm);color:var(--admin-tinta-fuerte);font-weight:600;">Días laborables</span>
                <button type="button" onclick="hAgrupar()" style="font-size:var(--admin-body-sm);background:none;border:none;color:var(--admin-brand);cursor:pointer;text-decoration:underline;text-align:left;padding:0;display:inline-flex;align-items:center;gap:.25rem;"><i data-lucide="chevron-up" style="width:13px;height:13px;"></i> Agrupar Lun–Vie</button>
            </div>`;
            DIAS_LV.forEach(d => { html += filaHTML(d, ''); });
        }
        html += filaHTML('s', '');
        html += filaHTML('d', '');
        c.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        const filas = c.querySelectorAll('.'+CLS_P);
        if (filas.length) filas[filas.length-1].style.borderBottom = 'none';
        renderTel();
        renderNota();
        syncHiddens();
    }

    // ── Lector DOM genérico ─────────────────────────────────────────────────
    function leerFila(prefix, cls, g, k) {
        const fila = document.querySelector(`.${cls}[data-k="${k}"]`);
        if (!fila) return;
        const radio  = fila.querySelector(`input[name="${prefix}-modo-${k}"]:checked`);
        const modo   = radio ? radio.value : g[k].modo;
        const turnos = [];
        fila.querySelectorAll('.t-fila').forEach(tf => {
            const a = tf.querySelector('.t-ap')?.value;
            const c = tf.querySelector('.t-ci')?.value;
            if (a && c) turnos.push([a,c]);
        });
        g[k] = {modo, turnos: turnos.length ? turnos : [['09:00','18:00']]};
    }

    // ── Acciones presencial ─────────────────────────────────────────────────
    window.hPModo = function(k, modo) {
        leerFila('P', CLS_P, state.g, k);
        state.g[k].modo = modo;
        const fila = document.querySelector(`.${CLS_P}[data-k="${k}"]`);
        if (fila) fila.querySelector('.t-wrap').style.display = modo === 'horario' ? 'block' : 'none';
        syncHiddens();
    };
    window.hPLeer        = function(k) { leerFila('P',CLS_P,state.g,k); syncHiddens(); };
    window.hPAddTurno    = function(k) { leerFila('P',CLS_P,state.g,k); state.g[k].turnos.push(['16:00','20:00']); render(); };
    window.hPQuitarTurno = function(btn,k) { leerFila('P',CLS_P,state.g,k); if(state.g[k].turnos.length>1) state.g[k].turnos.pop(); render(); };
    window.hDesglosar    = function() {
        leerFila('P',CLS_P,state.g,'lv');
        DIAS_LV.forEach(d => { state.g[d] = JSON.parse(JSON.stringify(state.g.lv)); });
        state.lvMode = 'individual'; render();
    };
    window.hAgrupar      = function() {
        DIAS_LV.forEach(d => leerFila('P',CLS_P,state.g,d));
        state.g.lv = JSON.parse(JSON.stringify(state.g.lunes));
        state.lvMode = 'grouped'; render();
    };

    // ── Acciones horario telefónico ─────────────────────────────────────────
    const tg = () => state.tel.horarioData;
    window.hTModo = function(k, modo) {
        leerFila('T',CLS_T,tg().g,k);
        tg().g[k].modo = modo;
        const fila = document.querySelector(`.${CLS_T}[data-k="${k}"]`);
        if (fila) fila.querySelector('.t-wrap').style.display = modo === 'horario' ? 'block' : 'none';
        syncHiddens();
    };
    window.hTLeer        = function(k) { leerFila('T',CLS_T,tg().g,k); syncHiddens(); };
    window.hTAddTurno    = function(k) { leerFila('T',CLS_T,tg().g,k); tg().g[k].turnos.push(['16:00','20:00']); renderTelSubEditor(); syncHiddens(); };
    window.hTQuitarTurno = function(btn,k) { leerFila('T',CLS_T,tg().g,k); if(tg().g[k].turnos.length>1) tg().g[k].turnos.pop(); renderTelSubEditor(); syncHiddens(); };
    window.hTDesglosar   = function() {
        leerFila('T',CLS_T,tg().g,'lv');
        DIAS_LV.forEach(d => { tg().g[d] = JSON.parse(JSON.stringify(tg().g.lv)); });
        tg().lvMode = 'individual'; renderTelSubEditor(); syncHiddens();
    };
    window.hTAgrupar     = function() {
        DIAS_LV.forEach(d => leerFila('T',CLS_T,tg().g,d));
        tg().g.lv = JSON.parse(JSON.stringify(tg().g.lunes));
        tg().lvMode = 'grouped'; renderTelSubEditor(); syncHiddens();
    };

    // ── Acciones tel top-level ──────────────────────────────────────────────
    window.hTelActiva    = function(v) { state.tel.activa = v; renderTel(); syncHiddens(); };
    window.hTelModo      = function(v) { state.tel.modo = v; renderTel(); syncHiddens(); };
    window.hTelTelefono  = function(v) { state.tel.telefono = v; syncHiddens(); };
    window.hNota         = function(v) { state.nota = v; syncHiddens(); };

    // ── Init ────────────────────────────────────────────────────────────────
    function init() {
        cargarJSON(document.getElementById('horarios-json')?.value || '');
        render();
    }

    // Expuesto para el Asistente IA: aplicar un JSON sugerido por el LLM
    // y resetear el editor visual al nuevo estado.
    window.hCargarDesdeJson = function(jsonStrOrObj) {
        const raw = typeof jsonStrOrObj === 'string'
            ? jsonStrOrObj
            : JSON.stringify(jsonStrOrObj);
        // Reset al estado base antes de cargar (evita merge raro entre estado anterior y nuevo)
        state.lvMode = 'grouped';
        state.g = mkG();
        state.tel = { activa:false, modo:'24h', telefono:'', horarioData:{ lvMode:'grouped', g:mkG() } };
        state.nota = '';
        cargarJSON(raw);
        render();
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();
})();


// ─── Edición inline de categoría de imagen
function abrirEdicion(id) {
    document.getElementById('cat-label-'    + id).style.display   = 'none';
    document.getElementById('cat-acciones-' + id).style.display   = 'none';
    const form = document.getElementById('cat-form-' + id);
    form.style.display = 'flex';
}
function cancelarEdicion(id) {
    document.getElementById('cat-form-'     + id).style.display   = 'none';
    document.getElementById('cat-label-'    + id).style.display   = '';
    document.getElementById('cat-acciones-' + id).style.display   = 'flex';
}
function abrirAlt(id) {
    document.getElementById('alt-label-' + id).style.display = 'none';
    document.getElementById('btn-alt-'   + id).style.display = 'none';
    document.getElementById('alt-form-'  + id).style.display = 'flex';
}
function cancelarAlt(id) {
    document.getElementById('alt-form-'  + id).style.display = 'none';
    document.getElementById('alt-label-' + id).style.display = '';
    document.getElementById('btn-alt-'   + id).style.display = '';
}

function actualizarArchivos() {
    var MAX_UPLOAD = 20;
    var input   = document.getElementById('img-archivo');
    var display = document.getElementById('archivos-display');
    var limpiar = document.getElementById('archivos-limpiar');
    var lista   = document.getElementById('archivos-lista');
    if (!input.files.length) { limpiarArchivos(); return; }
    var n = input.files.length;
    var excede = n > MAX_UPLOAD;
    display.textContent   = (excede ? '⚠ ' : '') + (n === 1 ? '1 archivo seleccionado' : n + ' archivos seleccionados') + (excede ? ' — máximo ' + MAX_UPLOAD + ' por subida' : '');
    display.classList.add('tiene-archivos');
    display.style.color   = excede ? 'var(--admin-tone-error-fg)' : '';
    limpiar.style.display = 'flex';
    lista.innerHTML = '';
    lista.style.display = 'flex';
    Array.from(input.files).forEach(function(f, i) {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:.4rem;';
        var nombre = document.createElement('span');
        nombre.style.cssText = 'color:var(--admin-tinta); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:340px;';
        nombre.textContent = f.name;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.title = 'Quitar este archivo';
        btn.dataset.idx = i;
        btn.style.cssText = 'flex-shrink:0; background:none; border:none; cursor:pointer; padding:0 .1rem; color:var(--admin-tinta-tenue); line-height:1; display:grid; place-items:center;';
        btn.innerHTML = '<i data-lucide="x" style="width:13px;height:13px;"></i>';
        btn.onmouseenter = function() { this.style.color = 'var(--color-siete)'; };
        btn.onmouseleave = function() { this.style.color = 'var(--admin-tinta-tenue)'; };
        btn.onclick = function() { eliminarArchivo(parseInt(this.dataset.idx)); };
        var size = document.createElement('span');
        size.style.cssText = 'color:var(--admin-tinta-tenue); white-space:nowrap; margin-left:.25rem;';
        size.textContent = '— ' + (f.size / 1024).toFixed(0) + ' KB';
        row.appendChild(nombre);
        row.appendChild(btn);
        row.appendChild(size);
        lista.appendChild(row);
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function eliminarArchivo(idx) {
    var input = document.getElementById('img-archivo');
    var dt = new DataTransfer();
    Array.from(input.files).forEach(function(f, i) { if (i !== idx) dt.items.add(f); });
    input.files = dt.files;
    actualizarArchivos();
}
function limpiarArchivos() {
    var input   = document.getElementById('img-archivo');
    var display = document.getElementById('archivos-display');
    var limpiar = document.getElementById('archivos-limpiar');
    var lista   = document.getElementById('archivos-lista');
    input.value             = '';
    display.textContent     = 'Ningún archivo seleccionado';
    display.classList.remove('tiene-archivos');
    display.style.color     = '';
    limpiar.style.display   = 'none';
    lista.style.display     = 'none';
    lista.innerHTML         = '';
}

// Acoplamiento logo en formulario de subida
function actualizarCategoria(sel) {
    const wrap = document.getElementById('wrap-categoria');
    const cat  = document.getElementById('img-categoria');
    if (!wrap || !cat) return;
    if (sel.value === 'logo') {
        cat.value              = 'logo';
        wrap.style.opacity     = '0.4';
        wrap.style.pointerEvents = 'none';
    } else {
        wrap.style.opacity     = '1';
        wrap.style.pointerEvents = '';
        if (cat.value === 'logo') cat.value = '';
    }
}
function sincCategoria(sel) {
    const tipo = document.getElementById('img-tipo');
    if (!tipo) return;
    if (sel.value === 'logo') {
        tipo.value = 'logo';
        actualizarCategoria(tipo); // bloquear el desplegable de categoría
    }
}

// Acoplamiento logo en formularios de edición inline por imagen
function sincLogoTipo(tipoSel) {
    const form = tipoSel.closest('form');
    if (!form) return;
    const catSel = form.querySelector('select[name="categoria"]');
    if (!catSel) return;
    if (tipoSel.value === 'logo') {
        catSel.value = 'logo';
        catSel.disabled = true;
    } else {
        catSel.disabled = false;
        if (catSel.value === 'logo') catSel.value = '';
    }
}
function sincLogoCategoria(catSel) {
    const form = catSel.closest('form');
    if (!form) return;
    const tipoSel = form.querySelector('select[name="tipo"]');
    if (!tipoSel) return;
    if (catSel.value === 'logo') {
        tipoSel.value = 'logo';
    } else if (tipoSel.value === 'logo') {
        tipoSel.value = 'galeria';
    }
}
// Conectar listeners a todos los formularios de edición inline al cargar
document.querySelectorAll('[id^="cat-form-"]').forEach(function(form) {
    const tipoSel = form.querySelector('select[name="tipo"]');
    const catSel  = form.querySelector('select[name="categoria"]');
    if (tipoSel) tipoSel.addEventListener('change', function() { sincLogoTipo(this); });
    if (catSel)  catSel.addEventListener('change',  function() { sincLogoCategoria(this); });
    // Estado inicial: si ya es logo, bloquear categoría
    if (tipoSel && tipoSel.value === 'logo' && catSel) catSel.disabled = true;
});

// Lightbox: unificado en el partial compartido includes/componentes/lightbox-galeria.php
// (data-lbg-*, IIFE aislado, borrado inline). Acá solo el guard de modo selección:
// en modo selección, clic en miniatura selectable togglea su checkbox en vez de
// abrir el lightbox. Capture-phase + stopPropagation para ganarle al listener
// delegado del partial (que escucha en bubble sobre document).
document.addEventListener('click', function(e) {
    if (!modoSeleccion) return;
    var trg = e.target.closest ? e.target.closest('[data-lbg-src]') : null;
    if (!trg) return;
    var card = trg.closest('[data-seleccionable]');
    if (card && card.dataset.seleccionable === '1') {
        var chk = card.querySelector('.img-check');
        if (chk) { chk.checked = !chk.checked; actualizarContador(); }
    }
    e.stopPropagation();
    e.preventDefault();
}, true);
</script>

<!-- Flash transitorio (guardado/error/imagen) → toast Notyf.
     La barra de acciones flotante se ELIMINÓ (quedaba vacía y sin cierre →
     franja blanca fija). Guardar/Cancelar viven en el
     <aside class="ficha-layout__aside"> (sticky). -->
<?php
$flashText = $error ?: $imgError ?: $mensaje ?: $imgOk ?: '';
$flashOk   = !$error && !$imgError;
?>

    <!-- Flash transitorio (guardado/error/imagen) → toast Notyf.
         El marcador data-flash-php hace que el puente del footer NO duplique el
         toast (sí limpia los ?img_ok/?img_error de la URL). -->
    <?php if ($flashText): ?>
    <span data-flash-php hidden></span>
    <script>
    (function () {
        function fire() {
            if (!window.toast) { setTimeout(fire, 80); return; }
            toast.<?php echo $flashOk ? 'ok' : 'error'; ?>(<?php echo json_encode(html_entity_decode((string) $flashText, ENT_QUOTES, 'UTF-8')); ?>);
        }
        fire();
    })();
    </script>
    <?php endif; ?>

    <!-- Botones Guardar/Cancelar viven en el <aside class="ficha-layout__aside"> (sticky).
         El grid/marco ya se cerró tras la sección de Reseñas de clientes. -->

<script>
// Scroll + dirty + flash — después del DOM completo
(function() {
    const KEY = 'scrollY_edit_<?php echo $id; ?>';
    const saved = sessionStorage.getItem(KEY);
    if (saved !== null) { window.scrollTo(0, parseInt(saved)); sessionStorage.removeItem(KEY); }
    document.querySelectorAll('form').forEach(function(f) {
        f.addEventListener('submit', function() { sessionStorage.setItem(KEY, window.scrollY); });
    });

    const mainForm = document.getElementById('main-form');
    const dirtyBar = document.getElementById('dirty-bar');
    if (!mainForm || !dirtyBar) return;

    // Snapshot del form completo (incluye los inputs ocultos que los editores
    // dinámicos —teléfonos/emails/redes/descripción/horarios/zona— y el
    // "aplicar IA" rellenan por JS sin disparar eventos input/change).
    function serialize() {
        try { return new URLSearchParams(new FormData(mainForm)).toString(); }
        catch (e) { return ''; }
    }
    // "Armado" recién en la PRIMERA interacción real del usuario con el form.
    // Antes de eso, todo el churn de inicialización (editores que renderizan y
    // sincronizan inputs ocultos, Tom Select, auto-guardado IA, etc.) NO cuenta
    // como "cambio" → cero falsos positivos al cargar. El baseline se toma en
    // ese primer gesto, ANTES de que la acción modifique nada (pointerdown/
    // keydown disparan antes del click/input), así la edición sí se detecta.
    let baseline = '';
    let armado   = false;
    let isDirty  = false;

    function recompute() {
        if (!armado) return;
        const d = serialize() !== baseline;
        if (d === isDirty) return;
        isDirty = d;
        dirtyBar.style.display = d ? '' : 'none';
    }
    function armar() {
        if (armado) return;
        baseline = serialize();   // estado pre-edición (post init), justo antes del gesto
        armado = true;
    }
    // Primer gesto genuino del usuario dentro del form → arma la guarda.
    mainForm.addEventListener('pointerdown', armar, true);
    mainForm.addEventListener('keydown',     armar, true);

    // Una vez armado: feedback al tipear/cambiar + catch-all para cambios
    // programáticos de los editores (alta/baja/reordenar, aplicar IA…).
    mainForm.addEventListener('input',  recompute);
    mainForm.addEventListener('change', recompute);
    setInterval(recompute, 900);

    mainForm.addEventListener('submit', function() {
        isDirty = false;
        dirtyBar.style.display = 'none';
        baseline = serialize();   // post-guardado: limpio
        armado = true;
    });
    window.addEventListener('beforeunload', function(e) {
        if (armado && isDirty) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>

<script>
function procesarLote(crematorioId, loteNum) {
    var btn = document.getElementById('btn-procesar-llm');
    var res = document.getElementById('llm-resultado');
    if (!btn || !res) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="icono" style="width:14px;height:14px;animation:spin 1s linear infinite;vertical-align:middle;"></i> Procesando…';
    if (window.lucide) lucide.createIcons();

    var fd = new FormData();
    fd.append('crematorio_id', crematorioId);
    fd.append('limite', '10');

    fetch('procesar-llm-ajax.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var data;
            try { data = JSON.parse(text); }
            catch(e) {
                agregarResultadoLote(res, loteNum, false, 0, 1, [], 'Respuesta inválida del servidor: ' + text.substring(0, 200));
                btn.disabled = false;
                btn.innerHTML = '↺ Reintentar próximo lote';
                return;
            }

            agregarResultadoLote(res, loteNum, data.ok, data.procesadas || 0, data.errores || 0, data.detalles || [], data.error || data.mensaje || '');

            if (!data.ok) {
                btn.disabled = false;
                btn.innerHTML = '↺ Reintentar próximo lote';
                return;
            }

            // Recargar siempre tras un lote exitoso para reflejar el estado real
            btn.disabled = true;
            var p = document.createElement('p');
            p.style.cssText = 'margin:.5rem 0 0; font-size:var(--admin-body-sm); color:var(--admin-tone-exito-fg); font-weight:600;';
            p.textContent = data.total === 0 ? '✓ Todos los lotes procesados. Recargando…' : 'Lote ' + loteNum + ' completado. Recargando…';
            res.appendChild(p);
            setTimeout(function() { location.reload(); }, 2000);
        })
        .catch(function(e) {
            agregarResultadoLote(res, loteNum, false, 0, 1, [], 'Error de red: ' + e.message);
            btn.disabled = false;
            btn.innerHTML = '↺ Reintentar próximo lote';
        });
}

function seleccionarLogo(crematorioId, imagenId) {
    var fd = new FormData();
    fd.append('crematorio_id', crematorioId);
    fd.append('imagen_id', imagenId);
    fetch('logo-seleccionar.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) location.reload();
            else toast.error('Error: ' + (data.error || 'desconocido'));
        })
        .catch(function(e) { toast.error('Error de red: ' + e.message); });
}

function seleccionarPortada(crematorioId, imagenId) {
    var fd = new FormData();
    fd.append('crematorio_id', crematorioId);
    fd.append('imagen_id', imagenId);
    fetch('portada-seleccionar.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) location.reload();
            else toast.error('Error: ' + (data.error || 'desconocido'));
        })
        .catch(function(e) { toast.error('Error de red: ' + e.message); });
}

// ─── Selección múltiple ───────────────────────────────────────────────────────
var modoSeleccion = false;

function toggleModoSeleccion() {
    modoSeleccion = !modoSeleccion;
    var checkboxes = document.querySelectorAll('.checkbox-seleccion');
    var acciones   = document.getElementById('acciones-seleccion');
    var btnModo    = document.getElementById('btn-modo-seleccion');
    checkboxes.forEach(function(el) { el.style.display = modoSeleccion ? 'block' : 'none'; });
    acciones.style.display = modoSeleccion ? 'flex' : 'none';
    btnModo.style.display  = modoSeleccion ? 'none' : '';
    // En modo selección: mostrar tarjetas desactivadas al 100% de opacidad
    document.querySelectorAll('#grid-imagenes [data-seleccionable="1"]').forEach(function(card) {
        card.style.opacity = modoSeleccion ? (card.dataset.desactivada === '1' ? '0.6' : '1') : (card.dataset.desactivada === '1' ? '0.45' : '');
    });
    if (!modoSeleccion) {
        document.querySelectorAll('.img-check').forEach(function(c) { c.checked = false; });
        actualizarContador();
    }
}

function actualizarContador() {
    var n = document.querySelectorAll('.img-check:checked').length;
    document.getElementById('contador-seleccion').textContent = n + ' seleccionada' + (n !== 1 ? 's' : '');
}

function idsSeleccionados() {
    return Array.from(document.querySelectorAll('.img-check:checked')).map(function(c) { return parseInt(c.dataset.id); });
}

function cambiarVisibilidad(ids, visible) {
    confirmar({
        titulo: (visible ? 'Reactivar' : 'Desactivar') + ' imágenes',
        mensaje: '¿' + (visible ? 'Reactivar' : 'Desactivar') + ' ' + ids.length + ' imagen' + (ids.length !== 1 ? 'es' : '') + '?',
        textoOK: visible ? 'Reactivar' : 'Desactivar',
        peligroso: !visible,
        onOK: function () {
            var fd = new FormData();
            fd.append('crematorio_id', <?php echo $id; ?>);
            fd.append('visible', visible);
            ids.forEach(function(id) { fd.append('ids[]', id); });
            fetch('imagen-visibilidad.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) { if (data.ok) location.reload(); else toast.error('Error: ' + (data.error || 'desconocido')); })
                .catch(function(e) { toast.error('Error de red: ' + e.message); });
        }
    });
}

function accionSeleccion(tipo) {
    var ids = idsSeleccionados();
    if (!ids.length) { toast.info('Seleccioná al menos una imagen.'); return; }
    if (tipo === 'desactivar') {
        cambiarVisibilidad(ids, 0);
    } else if (tipo === 'reactivar') {
        cambiarVisibilidad(ids, 1);
    } else if (tipo === 'eliminar') {
        confirmar({
            titulo: 'Eliminar imágenes',
            mensaje: '¿Eliminar ' + ids.length + ' imagen' + (ids.length !== 1 ? 'es' : '') + '? Esta acción no se puede deshacer.',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () {
                var fd = new FormData();
                fd.append('crematorio_id', <?php echo $id; ?>);
                ids.forEach(function(id) { fd.append('ids[]', id); });
                fetch('imagen-eliminar-lote.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) { if (data.ok) location.reload(); else toast.error('Error: ' + (data.error || 'desconocido')); })
                    .catch(function(e) { toast.error('Error de red: ' + e.message); });
            }
        });
    }
}

function procesarLoteCliente(crematorioId, loteNum) {
    var btn = document.getElementById('btn-procesar-cliente');
    var res = document.getElementById('llm-cliente-resultado');
    if (!btn || !res) return;

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="icono" style="width:14px;height:14px;animation:spin 1s linear infinite;vertical-align:middle;"></i> Procesando…';
    if (window.lucide) lucide.createIcons();

    var fd = new FormData();
    fd.append('crematorio_id', crematorioId);
    fd.append('limite', '10');
    fd.append('solo_tipo', 'cliente');

    fetch('procesar-llm-ajax.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var data;
            try { data = JSON.parse(text); }
            catch(e) {
                agregarResultadoLote(res, loteNum, false, 0, 1, [], 'Respuesta inválida: ' + text.substring(0, 200));
                btn.disabled = false;
                btn.innerHTML = '↺ Reintentar próximo lote';
                return;
            }
            agregarResultadoLote(res, loteNum, data.ok, data.procesadas || 0, data.errores || 0, data.detalles || [], data.error || data.mensaje || '');
            if (!data.ok) { btn.disabled = false; btn.innerHTML = '↺ Reintentar próximo lote'; return; }
            btn.disabled = true;
            var p = document.createElement('p');
            p.style.cssText = 'margin:.5rem 0 0; font-size:var(--admin-body-sm); color:var(--admin-tone-info-fg); font-weight:600;';
            p.textContent = data.total === 0 ? '✓ Todos los lotes de clientes procesados. Recargando…' : 'Lote ' + loteNum + ' completado. Recargando…';
            res.appendChild(p);
            setTimeout(function() { location.reload(); }, 2000);
        })
        .catch(function(e) {
            agregarResultadoLote(res, loteNum, false, 0, 1, [], 'Error de red: ' + e.message);
            btn.disabled = false;
            btn.innerHTML = '↺ Reintentar próximo lote';
        });
}

function agregarResultadoLote(res, loteNum, ok, procesadas, errores, detalles, msg) {
    var soloError = !ok || (procesadas === 0 && errores > 0);
    var color = soloError ? 'var(--admin-tone-error-bg)' : (errores > 0 ? 'var(--admin-tone-alerta-bg)' : 'var(--admin-tone-exito-bg)');
    var borde = soloError ? 'var(--admin-tone-error-bord)' : (errores > 0 ? 'var(--admin-tone-alerta-bord)' : 'var(--admin-tone-exito-bord)');
    var html  = '<div style="background:' + color + '; border:1px solid ' + borde + '; border-radius:var(--admin-r-sm); padding:var(--espacio-dos) var(--espacio-tres); margin-bottom:.3rem; font-size:var(--admin-body-sm);">';
    html += '<strong style="color:var(--admin-tinta-suave);">Lote ' + loteNum + '</strong> — ';

    if (!ok) {
        html += '<span style="color:var(--admin-tone-error-fg);">Error: ' + (msg || 'desconocido') + '</span>';
    } else if (msg && procesadas === 0 && errores === 0) {
        html += '<span style="color:var(--admin-tone-alerta-fg);">' + msg + '</span>';
    } else {
        html += '<span style="color:var(--admin-tone-exito-fg);">✓ ' + procesadas + ' procesada(s)</span>';
        if (errores > 0) html += ' · <span style="color:var(--admin-tone-error-fg);">✗ ' + errores + ' con error — editá manualmente</span>';
        if (detalles.length) {
            html += '<ul style="margin:.3rem 0 0; padding-left:1.2rem;">';
            detalles.forEach(function(d) {
                var clr = d.estado === 'ok' ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tone-error-fg)';
                html += '<li style="color:' + clr + '; margin-bottom:.1rem;">';
                html += (d.estado === 'ok' ? '✓ ' : '✗ ') + (d.nombre || 'imagen');
                if (d.categoria) html += ' <span style="opacity:.6; color:var(--admin-tinta-tenue);">→ ' + d.categoria + '</span>';
                if (d.msg)       html += ' <span style="opacity:.6; color:var(--admin-tinta-tenue);">(' + d.msg + ')</span>';
                html += '</li>';
            });
            html += '</ul>';
        }
    }
    html += '</div>';
    res.insertAdjacentHTML('beforeend', html);
}
</script>
<script>
function generarAltTexts(crematorioId) {
    var btn = document.getElementById('btn-generar-alt');
    var res = document.getElementById('alt-resultado');
    if (!btn || !res) return;

    btn.disabled = true;
    btn.textContent = 'Generando…';
    res.style.display = 'none';

    var fd = new FormData();
    fd.append('crematorio_id', crematorioId);
    fd.append('modo', 'crematorio');

    fetch('generar-alt-ajax.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var data;
            try { data = JSON.parse(text); }
            catch(e) {
                mostrarResultadoAlt(res, false, 0, 1, [], 'Respuesta inválida del servidor: ' + text.substring(0, 200));
                btn.disabled = false; btn.textContent = '↺ Reintentar'; return;
            }

            mostrarResultadoAlt(res, data.ok, data.actualizadas || 0, data.errores || 0, data.detalles || [], data.error || data.mensaje || '', data.sin_categoria || 0);

            if (data.ok && data.actualizadas > 0 && data.errores === 0) {
                res.querySelector('div').innerHTML += '<p style="margin:.5rem 0 0; font-size:var(--admin-body-sm); color:var(--admin-tone-exito-fg);">Recargando…</p>';
                setTimeout(function() { location.reload(); }, 1800);
            } else {
                btn.disabled = false;
                btn.textContent = data.actualizadas > 0 ? '↺ Reintentar errores' : '↺ Reintentar';
            }
        })
        .catch(function(e) {
            mostrarResultadoAlt(res, false, 0, 1, [], 'Error de red: ' + e.message);
            btn.disabled = false; btn.textContent = '↺ Reintentar';
        });
}

function mostrarResultadoAlt(res, ok, actualizadas, errores, detalles, msg, sinCategoria) {
    sinCategoria = sinCategoria || 0;
    var soloError = !ok || (actualizadas === 0 && errores > 0);
    var color = soloError ? 'var(--admin-tone-error-bg)' : (errores > 0 ? 'var(--admin-tone-alerta-bg)' : 'var(--admin-tone-exito-bg)');
    var borde = soloError ? 'var(--admin-tone-error-bord)' : (errores > 0 ? 'var(--admin-tone-alerta-bord)' : 'var(--admin-tone-exito-bord)');
    var html  = '<div style="background:' + color + '; border:1px solid ' + borde + '; border-radius:var(--admin-r-sm); padding:var(--espacio-tres) var(--espacio-cuatro);">';

    if (!ok || (actualizadas === 0 && errores === 0 && msg)) {
        html += '<strong>' + (msg || 'Sin cambios') + '</strong>';
    } else {
        html += '<strong style="color:var(--admin-tone-exito-fg);">✓ ' + actualizadas + ' alt text(s) generado(s)';
        if (errores > 0) html += ' · <span style="color:var(--admin-tone-error-fg);">✗ ' + errores + ' error(es)</span>';
        html += '</strong>';
        if (detalles.length) {
            html += '<ul style="margin:.4rem 0 0; padding-left:1.2rem; font-size:var(--admin-body-sm);">';
            detalles.forEach(function(d) {
                var clr = d.estado === 'ok' ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tone-error-fg)';
                var icn = d.estado === 'ok' ? '✓' : '✗';
                html += '<li style="color:' + clr + '; margin-bottom:.2rem;">' + icn + ' ';
                if (d.alt_text) html += '"' + d.alt_text + '"';
                else html += d.nombre;
                if (d.tipo === 'reemplazado') html += ' <span style="opacity:.5; font-size:.7rem;">(duplicado reemplazado)</span>';
                if (d.msg) html += ' <span style="opacity:.6;">(' + d.msg + ')</span>';
                html += '</li>';
            });
            html += '</ul>';
        }
    }
    if (sinCategoria > 0) {
        html += '<p style="margin:.5rem 0 0; font-size:var(--admin-body-sm); color:var(--admin-tone-alerta-fg);">⚠ ' + sinCategoria + ' imagen(es) omitida(s) por no tener categoría — usá el botón amarillo primero.</p>';
    }
    html += '</div>';
    res.innerHTML = html;
    res.style.display = 'block';
}
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<!-- ═══════════════════════════════════════════════════════════
     ASISTENTE IA — botones de interpretación por sección
     ═══════════════════════════════════════════════════════════ -->
<script>
window.CREMATORIO_ID = <?php echo (int) $id; ?>;
window.BASE_URL_IA   = <?php echo json_encode(BASE_URL); ?>;

// ── Ajustar el top del header sticky de la ficha según altura real del admin-header ─
(function() {
    function ajustar() {
        const adminH = document.querySelector('.admin-header');
        const fichaS = document.querySelector('.ficha-sticky');
        if (!adminH || !fichaS) return;
        const h = adminH.offsetHeight;
        fichaS.style.top = h + 'px';
        // +24 = padding-top de .admin-page (var(--espacio-cuatro)): el aside
        // queda EXACTO en su posición natural → se pega sin "pre-roll" (no se
        // mueve unos px antes de fijarse).
        const tope = h + fichaS.offsetHeight + 24;
        // Scroll padding para anchor jumps / scroll-into-view
        document.documentElement.style.scrollPaddingTop = tope + 'px';
        // Tope real del sidebar sticky (debajo de admin-header + barra .ficha-sticky)
        document.documentElement.style.setProperty('--ficha-aside-top', tope + 'px');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ajustar);
    } else {
        ajustar();
    }
    window.addEventListener('resize', ajustar);
})();

// ── Preservar posición del scroll a través del submit del form ─────────────
// Antes de enviar el form, guardamos scrollY. Tras el redirect+GET la recargamos
// y restauramos. Esto evita el "jump al top" después de Guardar.
(function() {
    const STORAGE_KEY = 'editar_crematorio_scroll_' + window.CREMATORIO_ID;

    document.addEventListener('DOMContentLoaded', function() {
        const formPrincipal = document.getElementById('main-form');
        if (formPrincipal) {
            formPrincipal.addEventListener('submit', function() {
                try { sessionStorage.setItem(STORAGE_KEY, String(window.scrollY)); } catch (e) {}
            });
        }

        // Si venimos de un guardado exitoso, restaurar posición
        const params = new URLSearchParams(window.location.search);
        if (params.get('saved') === '1') {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved !== null) {
                // Esperar un tick para que el layout esté listo
                requestAnimationFrame(() => {
                    window.scrollTo({ top: parseInt(saved, 10) || 0, behavior: 'instant' });
                    sessionStorage.removeItem(STORAGE_KEY);
                });
            }
        }
    });
})();

/**
 * Lanza la interpretación IA de una sección.
 * Si la sección ya fue procesada (botón muestra fecha), pide confirmación antes
 * de gastar otra llamada API.
 *
 * @param {string} seccion  horarios | contenido | cobertura | servicios | seo
 * @param {HTMLButtonElement} btn  El botón que disparó la acción
 */
function iaProcesarSeccion(seccion, btn) {
    const ultimaEl = document.getElementById('ia-ultima-' + seccion);
    const fbEl     = document.getElementById('ia-feedback-' + seccion);
    const yaProcesada = ultimaEl && !ultimaEl.classList.contains('nunca');

    // Confirmación obligatoria si ya fue procesada (gasta API y machaca trabajo previo)
    if (yaProcesada) {
        const ultimaTxt = ultimaEl.textContent.trim();
        confirmar({
            titulo: 'Reprocesar sección con IA',
            mensaje: 'Esta sección ya fue procesada:<br><strong>' + escHtmlIA(ultimaTxt) + '</strong>' +
                     '<br><br>Reprocesar gasta otra llamada a la API y puede pisar el trabajo previo. ¿Reprocesar igual?',
            textoOK: 'Reprocesar',
            peligroso: true,
            onOK: function () { iaEjecutarSeccion(seccion, btn, fbEl); }
        });
        return;
    }
    iaEjecutarSeccion(seccion, btn, fbEl);
}

async function iaEjecutarSeccion(seccion, btn, fbEl) {
    // UI: spinner + bloquear botón
    const labelOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="ia-spinner"></span> Procesando...';
    fbEl.style.display = 'none';
    fbEl.className = 'ia-feedback';

    try {
        const fd = new FormData();
        fd.append('crematorio_id', window.CREMATORIO_ID);
        fd.append('seccion', seccion);

        const r = await fetch(window.BASE_URL_IA + '/admin/procesar-llm-texto-ajax.php', {
            method: 'POST',
            body: fd
        });
        const data = await r.json();

        if (!data.ok) {
            iaMostrarFeedback(fbEl, 'err', '❌ Error al llamar a la IA',
                [data.error || 'Error desconocido']);
            return;
        }

        if (!data.interpretable) {
            iaMostrarFeedback(fbEl, 'warn', '⚠ La IA no pudo interpretar esta sección',
                [data.notes || 'Sin detalle. Revisá el texto fuente y volvé a intentar.'],
                'Los campos no fueron modificados.');
            // Aún así actualizamos la fecha de "última procesada" porque la API fue llamada
            iaActualizarUltima(seccion, data.modelo_usado);
            return;
        }

        // Aplicar la sugerencia según sección
        const aplicado = iaAplicarSugerencia(seccion, data.sugerencia);

        iaMostrarFeedback(fbEl, 'ok',
            '✓ Sugerencia aplicada — revisá y guardá si te parece bien',
            data.warnings || [],
            aplicado ? '' : 'No se modificó ningún campo (aplicador no implementado para esta sección).');

        iaActualizarUltima(seccion, data.modelo_usado);

    } catch (e) {
        iaMostrarFeedback(fbEl, 'err', '❌ Error de conexión', [e.message || String(e)]);
    } finally {
        btn.disabled = false;
        btn.innerHTML = labelOriginal;
    }
}

function iaMostrarFeedback(el, tipo, titulo, items, footer) {
    el.className = 'ia-feedback ' + tipo;
    let html = '<strong>' + titulo + '</strong>';
    if (Array.isArray(items) && items.length) {
        html += '<ul>' + items.map(i => '<li>' + escHtmlIA(i) + '</li>').join('') + '</ul>';
    }
    if (footer) html += '<div style="margin-top:.3rem;opacity:.85;">' + escHtmlIA(footer) + '</div>';
    el.innerHTML = html;
    el.style.display = 'block';
}

function iaActualizarUltima(seccion, modelo) {
    const el = document.getElementById('ia-ultima-' + seccion);
    if (!el) return;
    const ahora = new Date();
    const pad = n => String(n).padStart(2, '0');
    const fecha = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())} ${pad(ahora.getHours())}:${pad(ahora.getMinutes())}:${pad(ahora.getSeconds())}`;
    el.classList.remove('nunca');
    el.textContent = `Última: ${fecha} · ${modelo}`;
}

function escHtmlIA(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/**
 * Aplica una sugerencia al UI según la sección. Devuelve true si aplicó algo.
 */
function iaAplicarSugerencia(seccion, sug) {
    if (!sug) return false;

    switch (seccion) {
        case 'horarios':  return iaAplicarHorarios(sug);
        case 'contenido': return iaAplicarContenido(sug);
        case 'descripcion_avanzada': return iaAplicarContenido(sug);
        case 'cobertura': return iaAplicarCobertura(sug);
        case 'servicios': return iaAplicarServicios(sug);
        case 'seo':       return iaAplicarSeo(sug);
        case 'precios':   return iaAplicarPrecios(sug);
        case 'mensaje_whatsapp': return iaAplicarMensajeWhatsapp(sug);
        default:          return false;
    }
}

function iaAplicarPrecios(sug) {
    if (!sug || !Array.isArray(sug.precios) || !sug.precios.length) return false;
    if (typeof window.preciosCargarItems !== 'function') return false;
    var ok = window.preciosCargarItems(sug.precios);
    if (ok) {
        var editor = document.getElementById('precios-editor');
        if (editor) {
            editor.classList.add('sugerido-ia');
            setTimeout(function() { editor.classList.remove('sugerido-ia'); }, 4000);
        }
    }
    return ok;
}

function iaAplicarHorarios(sug) {
    if (!sug.horarios_editor) return false;
    if (typeof window.hCargarDesdeJson === 'function') {
        window.hCargarDesdeJson(sug.horarios_editor);
        const editor = document.getElementById('horario-editor');
        if (editor) {
            editor.classList.add('sugerido-ia');
            setTimeout(() => editor.classList.remove('sugerido-ia'), 4000);
        }
    }
    return true;
}

// Auto-guarda la versión IA recién generada (como INACTIVA, no publica) para
// no perder el trabajo/tokens por refresh u olvido. Fire-and-forget + toast.
function iaAutoguardarVersion(campo, id, modelo, valor) {
    try {
        const body = new URLSearchParams({
            crematorio_id: window.CREMATORIO_ID,
            campo: campo,
            entrada: JSON.stringify({ id: id, origen: 'llm_claude', modelo: modelo || '', valor: valor })
        });
        fetch(window.BASE_URL_IA + '/admin/guardar-version-ia-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(d => {
            if (d && d.ok) { if (window.toast) toast.ok('Versión IA guardada automáticamente'); }
            else if (window.toast) toast.error('No se pudo auto-guardar la versión IA — Guardá cambios manualmente para no perderla');
        })
        .catch(() => { if (window.toast) toast.error('No se pudo auto-guardar la versión IA — Guardá cambios manualmente'); });
    } catch (e) { /* noop */ }
}

function iaAplicarContenido(sug) {
    if (!sug.descripcion_sugerida) return false;
    if (typeof window.fv_addEntry_desc !== 'function') return false;

    // Agregar como entrada NUEVA, inactiva — el admin decide si la activa
    const newId = window.fv_addEntry_desc({
        origen: 'llm_claude',
        modelo: sug.modelo || null,
        valor:  sug.descripcion_sugerida,
        activo: false,
    });
    if (!newId) return false;
    // Auto-guardado inmediato (inactiva — no publica). El editor ya la marca,
    // expande y scrollea (ver addEntry / render del fuentes editor).
    iaAutoguardarVersion('descripciones_json', newId, sug.modelo, sug.descripcion_sugerida);
    return true;
}

function iaAplicarSeo(sug) {
    if (!sug.meta_description_sugerida) return false;
    if (typeof window.fv_addEntry_meta !== 'function') return false;

    const newId = window.fv_addEntry_meta({
        origen: 'llm_claude',
        modelo: sug.modelo || null,
        valor:  sug.meta_description_sugerida,
        activo: false,
    });
    if (!newId) return false;
    // Idem contenido: auto-guardado inmediato (inactiva, no publica).
    iaAutoguardarVersion('metas_json', newId, sug.modelo, sug.meta_description_sugerida);
    return true;
}

function iaAplicarMensajeWhatsapp(sug) {
    if (!sug.mensaje_sugerido) return false;
    if (typeof window.fv_addEntry_whatsapp !== 'function') return false;

    // Origen dinámico según proveedor real (llm_claude / llm_openrouter) —
    // reusa el badge morado "Procesado con IA X" ya existente en origenMeta().
    const origen = 'llm_' + (sug.proveedor || 'ia');

    const newId = window.fv_addEntry_whatsapp({
        origen: origen,
        modelo: sug.modelo || null,
        valor:  sug.mensaje_sugerido,
        activo: false,
    });
    if (!newId) return false;
    iaAutoguardarVersion('mensajes_whatsapp_json', newId, sug.modelo, sug.mensaje_sugerido);
    return true;
}

function iaAplicarCobertura(sug) {
    let aplicado = false;
    if (Array.isArray(sug.zona_cobertura) && typeof window.iaSetTags === 'function') {
        if (window.iaSetTags('zona_cobertura', sug.zona_cobertura)) aplicado = true;
    }
    if (Array.isArray(sug.ciudades_cobertura) && typeof window.iaSetTags === 'function') {
        if (window.iaSetTags('ciudades_cobertura', sug.ciudades_cobertura)) aplicado = true;
    }
    if (aplicado) {
        // Highlight visual sobre los dos tag inputs
        document.querySelectorAll('.tag-input-wrap[data-target="zona_cobertura"], .tag-input-wrap[data-target="ciudades_cobertura"]')
            .forEach(el => {
                el.classList.add('sugerido-ia');
                setTimeout(() => el.classList.remove('sugerido-ia'), 4000);
            });
    }
    return aplicado;
}

function iaAplicarServicios(sug) {
    if (!sug.servicios || typeof sug.servicios !== 'object') return false;
    let aplicado = false;
    Object.entries(sug.servicios).forEach(([campo, valor]) => {
        if (valor === null) return; // no mencionado → no tocar
        const sel = document.querySelector(`select[name="${campo}"]`);
        if (!sel) return;
        sel.value = (valor === true) ? '1' : '0';
        sel.classList.add('sugerido-ia');
        setTimeout(() => sel.classList.remove('sugerido-ia'), 4000);
        aplicado = true;
    });
    return aplicado;
}

// ── Toggle "No mostrar en galería principal" para imágenes de cliente ──────
// Selector de visibilidad pública de una foto de cliente (4 niveles).
async function cambiarVisibilidadCliente(imagenId, sel) {
    const status = document.getElementById('vis-status-' + imagenId);
    sel.disabled = true;
    if (status) { status.textContent = 'Guardando…'; status.style.color = 'var(--admin-tinta-tenue)'; }
    try {
        const fd = new FormData();
        fd.append('imagen_id', imagenId);
        fd.append('visibilidad', sel.value);
        const r = await fetch(window.BASE_URL_IA + '/admin/imagen-ocultar-galeria-ajax.php', {
            method: 'POST', body: fd
        });
        const data = await r.json();
        if (!data.ok) {
            if (status) { status.textContent = ''; }
            if (window.toast) toast.error(data.error || 'No se pudo guardar');
            return;
        }
        if (status) {
            status.textContent = '✓ Guardado';
            status.style.color = 'var(--admin-tone-exito-fg)';
            setTimeout(() => { status.textContent = ''; }, 2200);
        }
    } catch (e) {
        if (status) { status.textContent = ''; }
        if (window.toast) toast.error('Error de conexión');
    } finally {
        sel.disabled = false;
    }
}

async function toggleOcultarGaleria(imagenId, input) {
    const status = document.getElementById('ocg-status-' + imagenId);
    const checked = input.checked;
    input.disabled = true;
    if (status) { status.textContent = 'Guardando…'; status.style.color = '#6b7280'; }

    try {
        const fd = new FormData();
        fd.append('imagen_id', imagenId);
        fd.append('valor', checked ? '1' : '0');

        const r = await fetch(window.BASE_URL_IA + '/admin/imagen-ocultar-galeria-ajax.php', {
            method: 'POST', body: fd
        });
        const data = await r.json();

        if (!data.ok) {
            input.checked = !checked;
            if (status) { status.textContent = '⚠ ' + (data.error || 'No se pudo'); status.style.color = '#991b1b'; }
            return;
        }

        if (status) {
            status.textContent = checked ? '✓ Excluida' : '✓ En galería';
            status.style.color = '#166534';
            setTimeout(() => { status.textContent = ''; }, 2500);
        }
    } catch (e) {
        input.checked = !checked;
        if (status) { status.textContent = '⚠ Conexión'; status.style.color = '#991b1b'; }
    } finally {
        input.disabled = false;
    }
}

// ── Toggle checkboxes del comentario del cliente (leído/respondido/solucionado) ─
async function msjClienteToggle(flag, input) {
    const status = document.getElementById('msj-cliente-status');
    const checked = input.checked;
    input.disabled = true;
    if (status) status.textContent = 'Guardando…';

    try {
        const fd = new FormData();
        fd.append('crematorio_id', window.CREMATORIO_ID);
        fd.append('flag', flag);
        fd.append('valor', checked ? '1' : '0');

        const r = await fetch(window.BASE_URL_IA + '/admin/mensaje-cliente-ajax.php', {
            method: 'POST', body: fd
        });
        const data = await r.json();

        if (!data.ok) {
            input.checked = !checked; // revertir
            if (status) {
                status.textContent = '⚠ ' + (data.error || 'No se pudo guardar');
                status.style.color = '#991b1b';
            }
            return;
        }

        if (status) {
            const ahora = new Date();
            const pad = n => String(n).padStart(2,'0');
            status.textContent = '✓ Guardado · ' + pad(ahora.getHours()) + ':' + pad(ahora.getMinutes()) + ':' + pad(ahora.getSeconds());
            status.style.color = '#166534';
        }
    } catch (e) {
        input.checked = !checked;
        if (status) {
            status.textContent = '⚠ Error de conexión';
            status.style.color = '#991b1b';
        }
    } finally {
        input.disabled = false;
    }
}

// ── Reseñas de clientes: eliminar imagen adjunta ────────────────────────────
function fichaResenaEliminarImg(imagenId, resenaId, btn) {
    confirmar({
        titulo: 'Eliminar imagen',
        mensaje: 'Se borra el archivo y la fila en BD (irreversible). La reseña se conserva.<br><br>¿Eliminar esta imagen?',
        textoOK: 'Eliminar',
        peligroso: true,
        onOK: function () { proceder(); }
    });

    function restaurarBtn() {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="x" class="icono" style="width:13px; height:13px;"></i>';
        if (window.lucide) lucide.createIcons();
    }

    function proceder() {
    btn.disabled = true;
    btn.innerHTML = '…';

    const body = new URLSearchParams({ imagen_id: imagenId });

    fetch(window.BASE_URL_IA + '/admin/imagen-eliminar-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // 1. Quitar el thumb de la sección "Reseñas"
            const thumb = document.getElementById('ficha-resena-thumb-' + imagenId);
            if (thumb) {
                thumb.style.transition = 'opacity .2s';
                thumb.style.opacity = '0';
                setTimeout(() => {
                    thumb.remove();
                    const container = document.getElementById('ficha-resena-imgs-' + resenaId);
                    if (container && container.querySelectorAll('.ficha-resena-thumb-wrap').length === 0) {
                        container.remove();
                    }
                }, 200);
            }

            // 2. Quitar la card en la sección "Imágenes de clientes" si existe
            fichaSyncDomImagenClienteEliminada(imagenId);
            toast.ok('Imagen eliminada');
        } else {
            restaurarBtn();
            toast.error(data.error || 'No se pudo eliminar');
        }
    })
    .catch(() => {
        restaurarBtn();
        toast.error('Error de conexión');
    });
    }
}

// ── Sincronizar DOM cuando una imagen de cliente se elimina ────────────────
// Se llama desde el handler de borrado de imagen en reseñas y al eliminar reseñas
// en cascada, para que la sección "Imágenes de clientes" refleje el cambio sin reload.
function fichaSyncDomImagenClienteEliminada(imagenId) {
    const card = document.getElementById('img-card-' + imagenId);
    if (!card) return;
    card.style.transition = 'opacity .2s';
    card.style.opacity = '0';
    setTimeout(() => {
        card.remove();
        // Actualizar contador en el header de la sección
        const header = document.querySelector('#seccion-imagenes-clientes h2 span, #seccion-imagenes-clientes h2 small');
        if (header) {
            const m = header.textContent.match(/(\d+)/);
            if (m) {
                const n = Math.max(0, parseInt(m[1], 10) - 1);
                header.textContent = header.textContent.replace(/\d+/, n);
            }
        }
    }, 200);
}

// ── UI: tildar "Es SPAM" oculta "Aprobar" y reformatea el botón Rechazar ───
function fichaToggleSpamUI(id, esSpam) {
    const btnA = document.querySelector('[data-ficha-btn-aprobar="' + id + '"]');
    const btnR = document.querySelector('[data-ficha-btn-rechazar="' + id + '"]');
    if (btnA) btnA.style.display = esSpam ? 'none' : '';
    if (btnR) {
        const textNodes = [...btnR.childNodes].filter(n => n.nodeType === Node.TEXT_NODE);
        if (textNodes.length) textNodes[textNodes.length - 1].textContent = esSpam ? ' Rechazar como SPAM ' : ' Rechazar';
    }
}
function fichaLeerEsSpam(id) {
    const cb = document.querySelector('[data-ficha-spam-toggle="' + id + '"]');
    return cb ? !!cb.checked : false;
}

// ── Reseñas de clientes: aprobar / rechazar / pausar / eliminar inline ──────
function fichaResenaAccion(id, accion) {
    const imgsContainer = document.getElementById('ficha-resena-imgs-' + id);
    const nImgs = imgsContainer ? imgsContainer.querySelectorAll('.ficha-resena-thumb-wrap').length : 0;
    const esSpam = accion === 'rechazar' ? fichaLeerEsSpam(id) : false;
    const imgsTxt = nImgs > 0 ? (nImgs + ' imagen' + (nImgs === 1 ? '' : 'es') + ' adjunta' + (nImgs === 1 ? '' : 's')) : '';

    function ejecutar(eliminarImgs) {
        const body = new URLSearchParams({ id: id, accion: accion });
        if (eliminarImgs) body.append('eliminar_imagenes', '1');
        if (accion === 'eliminar') body.append('confirmar', '1');
        if (esSpam) body.append('es_spam', '1');

        fetch(window.BASE_URL_IA + '/admin/resena-accion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Sincronizar DOM: si se eliminaron imágenes (eliminar o rechazar+cascada),
                // borrar también las cards de "Imágenes de clientes" en simultáneo
                if (data.imagenes_eliminadas > 0 && imgsContainer) {
                    imgsContainer.querySelectorAll('.ficha-resena-thumb-wrap').forEach(wrap => {
                        const m = wrap.id.match(/^ficha-resena-thumb-(\d+)$/);
                        if (m) fichaSyncDomImagenClienteEliminada(parseInt(m[1], 10));
                    });
                }
                location.reload();
            } else {
                toast.error(data.mensaje || data.error || 'Error al procesar');
            }
        })
        .catch(() => toast.error('Error de conexión'));
    }

    if (accion === 'rechazar' && esSpam) {
        confirmar({
            titulo: 'Rechazar y marcar como SPAM',
            mensaje: 'La reseña pasa a la pestaña <strong>SPAM</strong> y deja de mostrarse al público. El texto' +
                     (nImgs > 0 ? ' y las ' + imgsTxt : '') + ' se conserva' + (nImgs > 0 ? 'n' : '') +
                     ' hasta que la borres definitivamente.',
            textoOK: 'Rechazar como SPAM',
            peligroso: true,
            onOK: function () { ejecutar(false); }
        });
    } else if (accion === 'rechazar') {
        confirmar({
            titulo: 'Rechazar reseña',
            mensaje: 'Queda en estado <strong>rechazada</strong>, no se muestra al público. Podés reevaluarla más tarde si te equivocaste.',
            textoOK: 'Rechazar',
            onOK: function () {
                if (nImgs > 0) {
                    confirmar({
                        titulo: 'Imágenes de la reseña',
                        mensaje: 'La reseña tiene ' + imgsTxt + '.<br><br>¿Borrar también las imágenes? ' +
                                 '<strong>Confirmar</strong> elimina archivos + BD (irreversible). ' +
                                 '<strong>Cancelar</strong> las conserva por si reevaluás.',
                        textoOK: 'Borrar imágenes',
                        textoCancelar: 'Conservar',
                        peligroso: true,
                        onOK:     function () { ejecutar(true); },
                        onCancel: function () { ejecutar(false); }
                    });
                } else {
                    ejecutar(false);
                }
            }
        });
    } else if (accion === 'pausar') {
        confirmar({
            titulo: 'Pausar reseña',
            mensaje: 'Vuelve a la cola de pendientes y deja de mostrarse al público hasta que la apruebes de nuevo.',
            textoOK: 'Pausar',
            onOK: function () { ejecutar(false); }
        });
    } else if (accion === 'eliminar') {
        confirmar({
            titulo: 'Eliminar reseña definitivamente',
            mensaje: 'Se borra la reseña completa (texto, autor, fechas)' +
                     (nImgs > 0 ? ' y ' + imgsTxt + ' (archivos + BD)' : '') +
                     '.<br><br>⚠ Acción irreversible. No vas a poder recuperar nada.',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () { ejecutar(false); }
        });
    } else {
        // aprobar u otra acción sin confirmación
        ejecutar(false);
    }
}

// ── Copiar texto del panel "Texto original" al portapapeles ─────────────────
async function copiarTextoOrigen(elId, btn) {
    const el = document.getElementById(elId);
    if (!el) return;
    const texto = el.textContent || '';
    try {
        await navigator.clipboard.writeText(texto);
        const original = btn.innerHTML;
        btn.classList.add('copiado');
        btn.innerHTML = '<i data-lucide="check" style="width:13px;height:13px;"></i> Copiado';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => { btn.innerHTML = original; btn.classList.remove('copiado'); }, 1500);
    } catch (e) {
        // Fallback para navegadores viejos / contexto sin clipboard API
        const ta = document.createElement('textarea');
        ta.value = texto;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); btn.innerHTML = '✓ Copiado'; }
        catch (e2) { btn.innerHTML = '✗ Error al copiar'; }
        document.body.removeChild(ta);
        setTimeout(() => { btn.innerHTML = '📋 Copiar'; btn.classList.remove('copiado'); }, 1500);
    }
}
</script>

<?php include __DIR__ . '/../includes/componentes/lightbox-galeria.php'; ?>
<script>
// Cuando el lightbox compartido borra una imagen de reseña, sincronizar el resto
// de la página (card en "Imágenes de clientes" + contenedor vacío de la reseña).
document.addEventListener('lbg:deleted', function(e) {
    var id = e.detail && e.detail.id;
    if (!id) return;
    if (typeof fichaSyncDomImagenClienteEliminada === 'function') {
        fichaSyncDomImagenClienteEliminada(id);
    }
    document.querySelectorAll('[id^="ficha-resena-imgs-"]').forEach(function(c) {
        if (c.querySelectorAll('.ficha-resena-thumb-wrap').length === 0) c.remove();
    });
});
</script>

<script>
/* ─── Editor de Precios ───────────────────────────────────────
   Filas dinámicas → serializa a #precios-json antes del submit. */
(function() {
    var input  = document.getElementById('precios-json');
    var editor = document.getElementById('precios-editor');
    var addBtn = document.getElementById('precios-add');
    var badge  = document.getElementById('precios-badge');
    if (!input || !editor || !addBtn) return;

    var MONEDA = '<?php echo defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : '€'; ?>';
    var TIPOS  = {
        fijo:   'Precio fijo',
        desde:  'Desde',
        rango:  'Rango (mín – máx)',
        custom: 'Nota (sin monto)'
    };

    var items = [];
    try { items = JSON.parse(input.value || '[]'); } catch (e) { items = []; }
    if (!Array.isArray(items)) items = [];

    function uid() { return 'p' + Date.now() + Math.floor(Math.random() * 1000); }

    function serializar() {
        input.value = JSON.stringify(items);
        actualizarBadge();
    }

    function actualizarBadge() {
        if (!badge) return;
        if (items.length === 0) {
            badge.textContent = '○ Sin precios';
            badge.className = 'completitud-badge falta';
        } else {
            badge.textContent = '✓ ' + items.length + ' precio' + (items.length > 1 ? 's' : '');
            badge.className = 'completitud-badge ok';
        }
    }

    function render() {
        editor.innerHTML = '';
        if (items.length === 0) {
            var vacio = document.createElement('p');
            vacio.style.cssText = 'color:var(--admin-text-suave); font-size:.85rem; font-style:italic; margin:0;';
            vacio.textContent = 'Todavía no hay precios cargados.';
            editor.appendChild(vacio);
        }
        items.forEach(function(it, idx) { editor.appendChild(filaPrecio(it, idx)); });
        if (window.lucide) lucide.createIcons();
    }

    function filaPrecio(it, idx) {
        var card = document.createElement('div');
        card.style.cssText = 'border:1px solid var(--admin-linea); border-radius:var(--admin-r-sm); padding:var(--espacio-tres); background:var(--admin-papel-alt);';

        // Fila 1: tipo + destacado + eliminar
        var fila1 = document.createElement('div');
        fila1.style.cssText = 'display:flex; gap:var(--espacio-tres); align-items:center; margin-bottom:var(--espacio-tres);';

        var selTipo = document.createElement('select');
        selTipo.className = 'field__select';
        selTipo.style.cssText = 'flex:0 0 200px;';
        Object.keys(TIPOS).forEach(function(k) {
            var o = document.createElement('option');
            o.value = k; o.textContent = TIPOS[k];
            if (it.tipo === k) o.selected = true;
            selTipo.appendChild(o);
        });
        selTipo.addEventListener('change', function() {
            it.tipo = selTipo.value; serializar(); render();
        });

        var lblDest = document.createElement('label');
        lblDest.style.cssText = 'display:flex; align-items:center; gap:.4rem; font-size:.85rem; cursor:pointer; white-space:nowrap;';
        var chkDest = document.createElement('input');
        chkDest.type = 'checkbox';
        chkDest.checked = !!it.destacado;
        chkDest.addEventListener('change', function() { it.destacado = chkDest.checked; serializar(); });
        lblDest.appendChild(chkDest);
        lblDest.appendChild(document.createTextNode('Destacar'));

        // Reordenar: subir / bajar. Mueve el ítem en el array y re-renderiza.
        var mkOrden = function(icono, titulo, deshabilitado, onClick) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'boton dos pequeno';
            b.title = titulo;
            b.disabled = deshabilitado;
            b.style.cssText = 'padding:.3rem .4rem;' + (deshabilitado ? 'opacity:.35;cursor:default;' : '');
            b.innerHTML = '<i data-lucide="' + icono + '" class="icono" style="width:13px;height:13px;"></i>';
            if (!deshabilitado) b.addEventListener('click', onClick);
            return b;
        };
        var btnSubir = mkOrden('chevron-up', 'Subir', idx === 0, function() {
            var tmp = items[idx - 1]; items[idx - 1] = items[idx]; items[idx] = tmp;
            serializar(); render();
        });
        // El grupo subir/bajar/borrar va junto a la derecha.
        btnSubir.style.marginLeft = 'auto';
        var btnBajar = mkOrden('chevron-down', 'Bajar', idx === items.length - 1, function() {
            var tmp = items[idx + 1]; items[idx + 1] = items[idx]; items[idx] = tmp;
            serializar(); render();
        });

        var btnDel = document.createElement('button');
        btnDel.type = 'button';
        btnDel.className = 'boton dos pequeno';
        btnDel.style.cssText = 'color:var(--admin-tone-error-fg); border-color:var(--admin-tone-error-fg); padding:.3rem .5rem;';
        btnDel.innerHTML = '<i data-lucide="trash-2" class="icono" style="width:13px;height:13px;"></i>';
        btnDel.addEventListener('click', function() {
            items.splice(idx, 1); serializar(); render();
        });

        fila1.appendChild(selTipo);
        fila1.appendChild(lblDest);
        fila1.appendChild(btnSubir);
        fila1.appendChild(btnBajar);
        fila1.appendChild(btnDel);

        // Fila 2: nombre
        var inNombre = mkInput('text', it.nombre || '', 'Nombre del servicio (ej. Cremación individual perro pequeño)');
        inNombre.addEventListener('input', function() { it.nombre = inNombre.value; serializar(); });

        // Fila 3: descripción
        var inDesc = mkInput('text', it.descripcion || '', 'Detalle (ej. hasta 10 kg, incluye urna básica) — opcional');
        inDesc.addEventListener('input', function() { it.descripcion = inDesc.value; serializar(); });

        // Fila 4: montos (según tipo)
        var filaMontos = document.createElement('div');
        filaMontos.style.cssText = 'display:flex; gap:var(--espacio-tres); margin-top:var(--espacio-dos);';
        if (it.tipo !== 'custom') {
            var inMin = mkInput('number', it.min != null ? it.min : '',
                it.tipo === 'rango' ? 'Mínimo (' + MONEDA + ')' : 'Monto (' + MONEDA + ')');
            inMin.addEventListener('input', function() {
                it.min = inMin.value === '' ? null : parseFloat(inMin.value); serializar();
            });
            filaMontos.appendChild(inMin);
            if (it.tipo === 'rango') {
                var inMax = mkInput('number', it.max != null ? it.max : '', 'Máximo (' + MONEDA + ')');
                inMax.addEventListener('input', function() {
                    it.max = inMax.value === '' ? null : parseFloat(inMax.value); serializar();
                });
                filaMontos.appendChild(inMax);
            }
        }

        card.appendChild(fila1);
        card.appendChild(inNombre);
        card.appendChild(inDesc);
        if (filaMontos.children.length) card.appendChild(filaMontos);
        return card;
    }

    function mkInput(tipo, valor, placeholder) {
        var i = document.createElement('input');
        i.type = tipo;
        i.className = 'field__input';
        i.value = valor;
        i.placeholder = placeholder;
        i.style.cssText = 'margin-top:var(--espacio-dos); flex:1;';
        if (tipo === 'number') { i.min = '0'; i.step = '1'; }
        return i;
    }

    addBtn.addEventListener('click', function() {
        items.push({ id: uid(), tipo: 'desde', nombre: '', descripcion: '', min: null, max: null, destacado: false });
        serializar(); render();
    });

    // Expuesto para el asistente IA: reemplaza la lista con los ítems sugeridos.
    // El admin revisa y recién con "Guardar cambios" se persiste.
    window.preciosCargarItems = function(nuevos) {
        if (!Array.isArray(nuevos)) return false;
        var tiposOk = ['fijo', 'desde', 'rango', 'custom'];
        items = nuevos.map(function(n) {
            return {
                id: uid(),
                tipo: tiposOk.indexOf(n.tipo) >= 0 ? n.tipo : 'custom',
                nombre: String(n.nombre || ''),
                descripcion: String(n.descripcion || ''),
                min: (n.min != null && n.min !== '') ? parseFloat(n.min) : null,
                max: (n.max != null && n.max !== '') ? parseFloat(n.max) : null,
                destacado: !!n.destacado
            };
        });
        serializar();
        render();
        return true;
    };

    render();
    actualizarBadge();
})();
</script>

<?php include 'footer.php'; ?>
