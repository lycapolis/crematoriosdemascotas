<?php
/**
 * Panel Admin — Configuración de IA por sección (solo super_admin).
 *
 * Permite elegir, por cada tarea IA del proyecto (texto, visión o generación
 * de imágenes), qué proveedor (claude | openrouter) y modelo usar — sin tocar
 * código. Ver tabla ia_config_secciones y los wrappers llamarLLM() /
 * llamarLLMImagen() en funciones.php.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requiereSuperAdmin();

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

$mensaje = '';
$error   = '';

// Generación masiva one-off: mensaje WhatsApp "auto" para fichas que todavía
// no tienen ninguna versión (ej. tras desplegar la feature en producción).
// Botón en la sección "Herramientas" más abajo. No pisa versiones manuales/IA.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'backfill_whatsapp') {
    $ids = $pdo->query("SELECT id FROM crematorios")->fetchAll(PDO::FETCH_COLUMN);
    $generados = 0;
    foreach ($ids as $idCr) {
        if (regenerarMensajeWhatsappAutoSiCorresponde($pdo, (int) $idCr)) $generados++;
    }
    header('Location: ' . BASE_URL . '/admin/configuracion-ia.php?backfill=' . $generados . '&total=' . count($ids));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secciones = $_POST['seccion'] ?? [];
    $proveedores = $_POST['proveedor'] ?? [];
    $modelos = $_POST['modelo'] ?? [];
    $maxTokens = $_POST['max_tokens'] ?? [];

    // Las secciones de generación de imágenes solo pueden usar OpenRouter (Claude no genera imágenes)
    $tiposSeccion = $pdo->query("SELECT seccion, tipo FROM ia_config_secciones")->fetchAll(PDO::FETCH_KEY_PAIR);

    $sql = "UPDATE ia_config_secciones
            SET proveedor = :proveedor, modelo = :modelo, max_tokens = :max_tokens, actualizado_por = :admin_id
            WHERE seccion = :seccion";
    $upd = $pdo->prepare($sql);

    $actualizadas = 0;
    foreach ($secciones as $seccion) {
        $seccion   = trim((string) $seccion);
        $proveedor = in_array($proveedores[$seccion] ?? '', ['claude', 'openrouter'], true) ? $proveedores[$seccion] : 'claude';
        if (($tiposSeccion[$seccion] ?? '') === 'imagen') $proveedor = 'openrouter';
        $modelo    = trim((string) ($modelos[$seccion] ?? ''));
        $tokens    = (($tiposSeccion[$seccion] ?? '') === 'imagen') ? 0 : max(50, min(8000, (int) ($maxTokens[$seccion] ?? 1500)));

        if ($seccion === '' || $modelo === '') continue;

        $upd->execute([
            ':proveedor'  => $proveedor,
            ':modelo'     => $modelo,
            ':max_tokens' => $tokens,
            ':admin_id'   => $adminActual['id'],
            ':seccion'    => $seccion,
        ]);
        $actualizadas++;
    }

    header('Location: ' . BASE_URL . '/admin/configuracion-ia.php?saved=' . $actualizadas);
    exit;
}

if (isset($_GET['saved'])) {
    $n = (int) $_GET['saved'];
    $mensaje = $n === 1 ? 'Se actualizó 1 sección.' : "Se actualizaron $n secciones.";
}
if (isset($_GET['backfill'])) {
    $mensaje = 'Mensajes de WhatsApp: se generaron ' . (int) $_GET['backfill'] . ' de ' . (int) ($_GET['total'] ?? 0) . ' fichas (las que ya tenían una versión manual o de IA activa no se tocaron).';
}

$claves = [
    'Proveedores de IA' => [
        'CLAUDE_API_KEY'     => ['ok' => defined('CLAUDE_API_KEY') && CLAUDE_API_KEY !== '',         'uso' => 'Claude (Anthropic)'],
        'OPENROUTER_API_KEY' => ['ok' => defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '', 'uso' => 'OpenRouter: texto, visión y generación de imágenes'],
    ],
    'Bancos de fotos del blog' => [
        'PEXELS_API_KEY'  => ['ok' => defined('PEXELS_API_KEY') && PEXELS_API_KEY !== '',   'uso' => 'Pexels'],
        'PIXABAY_API_KEY' => ['ok' => defined('PIXABAY_API_KEY') && PIXABAY_API_KEY !== '', 'uso' => 'Pixabay'],
    ],
];
$faltaAlguna = false;
foreach ($claves as $grupo) foreach ($grupo as $c) if (!$c['ok']) $faltaAlguna = true;

$config = $pdo->query("SELECT * FROM ia_config_secciones ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);
$porTipo = ['texto' => [], 'vision' => [], 'imagen' => []];
foreach ($config as $row) {
    if (isset($porTipo[$row['tipo']])) $porTipo[$row['tipo']][] = $row;
}

// Sugerencias de modelos (no exhaustivo, solo para orientar — el campo es texto libre)
$sugerenciasClaude     = ['claude-haiku-4-5-20251001', 'claude-sonnet-4-5-20250929', 'claude-sonnet-4-6'];
$sugerenciasOpenRouter = ['openai/gpt-4o-mini', 'anthropic/claude-3.5-haiku', 'anthropic/claude-3.5-sonnet', 'google/gemini-flash-1.5', 'meta-llama/llama-3.1-8b-instruct'];

/** Tabla editable de secciones de un tipo (texto | vision | imagen). */
$tablaSecciones = function (string $tipo, array $filas): void {
    $esImagen = $tipo === 'imagen';
    ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tarea</th>
                    <th>Proveedor</th>
                    <th>Modelo</th>
                    <th><?php echo $esImagen ? '' : 'Máx. tokens'; ?></th>
                    <th>Última actualización</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $row): $sec = htmlspecialchars($row['seccion']); ?>
                <tr>
                    <td>
                        <input type="hidden" name="seccion[]" value="<?php echo $sec; ?>">
                        <strong><?php echo htmlspecialchars($row['label']); ?></strong>
                        <div class="admin-text-muted" style="font-size:var(--admin-caption); font-family:monospace;"><?php echo $sec; ?></div>
                    </td>
                    <td>
                        <?php if ($esImagen): ?>
                        <input type="hidden" name="proveedor[<?php echo $sec; ?>]" value="openrouter">
                        <span class="admin-text-muted">OpenRouter</span>
                        <?php else: ?>
                        <select name="proveedor[<?php echo $sec; ?>]" class="field__select">
                            <option value="claude" <?php echo $row['proveedor'] === 'claude' ? 'selected' : ''; ?>>Claude</option>
                            <option value="openrouter" <?php echo $row['proveedor'] === 'openrouter' ? 'selected' : ''; ?>>OpenRouter</option>
                        </select>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="text" name="modelo[<?php echo $sec; ?>]"
                               class="field__input" style="min-width:220px;"
                               list="modelos-<?php echo $tipo; ?>"
                               value="<?php echo htmlspecialchars($row['modelo']); ?>">
                    </td>
                    <td>
                        <?php if (!$esImagen): ?>
                        <input type="number" name="max_tokens[<?php echo $sec; ?>]"
                               class="field__input" style="width:100px;" min="50" max="8000" step="50"
                               value="<?php echo (int) $row['max_tokens']; ?>">
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-text-muted" style="font-size:var(--admin-body-sm);">
                            <?php echo $row['actualizado_en'] ? date('d/m/Y H:i', strtotime($row['actualizado_en'])) : '—'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$titulo_pagina = 'Configuración IA — Admin';
include 'header.php';
?>

<div class="admin-page">

    <p style="margin-bottom:var(--espacio-dos);">
        <a href="configuracion.php" class="admin-text-muted">← Configuración</a>
    </p>

    <header class="admin-page-header">
        <h1 class="admin-page-title">Configuración de IA por tarea</h1>
        <p class="admin-page-subtitle">
            Elegí qué proveedor y qué modelo usa cada tarea de IA del panel. Los cambios se aplican al instante, sin tocar código.
        </p>
    </header>

    <?php if ($mensaje): ?>
    <div class="admin-banner admin-banner--success" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="check-circle-2" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content"><?php echo htmlspecialchars($mensaje); ?></div>
    </div>
    <?php endif; ?>

    <!-- ── Claves de API (solo estado; se configuran en el .env) ── -->
    <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="ficha-card__title">
            <i data-lucide="key-round" class="icono"></i>
            Claves de API
        </h2>
        <?php foreach ($claves as $grupo => $lista): ?>
        <div style="margin-bottom:var(--espacio-tres);">
            <div class="admin-text-muted" style="font-size:var(--admin-caption); font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.4rem;"><?php echo $grupo; ?></div>
            <div style="display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
                <?php foreach ($lista as $nombre => $c): ?>
                <span class="admin-pill <?php echo $c['ok'] ? 'admin-pill--exito' : 'admin-pill--error'; ?>" title="<?php echo htmlspecialchars($c['uso']); ?>">
                    <i data-lucide="<?php echo $c['ok'] ? 'check-circle-2' : 'circle-x'; ?>" style="width:12px;height:12px;"></i>
                    <?php echo $nombre; ?> <?php echo $c['ok'] ? 'configurada' : 'sin configurar'; ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($faltaAlguna): ?>
        <div class="admin-banner admin-banner--warning" style="margin:0;">
            <i data-lucide="alert-triangle" class="icono admin-banner__icon"></i>
            <div class="admin-banner__content">
                Si una tarea usa un proveedor cuya clave no está configurada, esa tarea fallará al usarse.
                Las claves se cargan en el archivo <code>.env</code> del servidor, nunca en este panel.
            </div>
        </div>
        <?php endif; ?>
    </section>

    <form method="POST">

        <!-- ── IA de texto ── -->
        <?php if ($porTipo['texto']): ?>
        <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
            <h2 class="ficha-card__title">
                <i data-lucide="file-text" class="icono"></i>
                IA de texto
            </h2>
            <p class="admin-text-muted" style="margin:0 0 var(--espacio-tres); font-size:var(--admin-body-sm);">
                Tareas que leen y escriben texto: descripciones, horarios, precios, SEO, mensajes, etc.
            </p>
            <?php $tablaSecciones('texto', $porTipo['texto']); ?>
        </section>
        <?php endif; ?>

        <!-- ── IA de imágenes ── -->
        <?php if ($porTipo['vision'] || $porTipo['imagen']): ?>
        <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
            <h2 class="ficha-card__title">
                <i data-lucide="image" class="icono"></i>
                IA de imágenes
            </h2>

            <?php if ($porTipo['vision']): ?>
            <h3 style="display:flex; align-items:center; gap:.4rem; font-size:1rem; margin:0 0 .3rem;">
                <i data-lucide="scan-eye" class="icono" style="width:16px;height:16px;"></i> Análisis de imágenes (visión)
            </h3>
            <p class="admin-text-muted" style="margin:0 0 var(--espacio-tres); font-size:var(--admin-body-sm);">
                La IA mira una imagen existente para clasificarla o escribir su texto alternativo. Usa modelos con visión.
            </p>
            <?php $tablaSecciones('vision', $porTipo['vision']); ?>
            <?php endif; ?>

            <?php if ($porTipo['imagen']): ?>
            <h3 style="display:flex; align-items:center; gap:.4rem; font-size:1rem; margin:var(--espacio-cuatro) 0 .3rem;">
                <i data-lucide="wand-sparkles" class="icono" style="width:16px;height:16px;"></i> Generación de imágenes
            </h3>
            <p class="admin-text-muted" style="margin:0 0 var(--espacio-tres); font-size:var(--admin-body-sm);">
                Crea imágenes nuevas a partir de una descripción. Siempre vía OpenRouter (Claude no genera imágenes).
                Modelos disponibles: <a href="https://openrouter.ai/models?output_modalities=image" target="_blank" rel="noopener">openrouter.ai/models</a>.
            </p>
            <?php $tablaSecciones('imagen', $porTipo['imagen']); ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <datalist id="modelos-texto">
            <?php foreach (array_merge($sugerenciasClaude, $sugerenciasOpenRouter) as $m): ?>
            <option value="<?php echo htmlspecialchars($m); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="modelos-vision">
            <option value="claude-sonnet-4-6">
            <option value="claude-sonnet-4-5-20250929">
            <option value="claude-haiku-4-5-20251001">
            <option value="openai/gpt-4o">
            <option value="anthropic/claude-3.5-sonnet">
        </datalist>
        <datalist id="modelos-imagen">
            <option value="google/gemini-2.5-flash-image">
            <option value="black-forest-labs/flux.2-pro">
            <option value="openai/gpt-image-1">
            <option value="bytedance-seed/seedream-4.5">
        </datalist>

        <button type="submit" class="boton tres">
            <i data-lucide="save" class="icono"></i>
            Guardar cambios
        </button>
    </form>

    <!-- ── Herramientas ── -->
    <section class="ficha-card" style="margin-top:var(--espacio-cuatro);">
        <h2 class="ficha-card__title">
            <i data-lucide="wrench" class="icono"></i>
            Herramientas
        </h2>
        <p style="font-size:.85rem; color:var(--admin-text-suave); margin:0 0 var(--espacio-tres); line-height:1.5;">
            Genera el mensaje de WhatsApp automático para todas las fichas que todavía no tienen ninguna versión guardada
            (por ejemplo, después de desplegar esta función). No toca las fichas que ya tienen activa una versión manual o de IA.
        </p>
        <form method="POST" onsubmit="return confirm('¿Generar el mensaje de WhatsApp automático para todas las fichas que aún no tienen ninguna versión guardada?');">
            <input type="hidden" name="accion" value="backfill_whatsapp">
            <button type="submit" class="boton dos">
                <i data-lucide="message-circle" class="icono"></i>
                Generar mensajes de WhatsApp faltantes
            </button>
        </form>
    </section>

</div>

<?php include 'footer.php'; ?>
