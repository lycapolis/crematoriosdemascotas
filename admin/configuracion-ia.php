<?php
/**
 * Panel Admin — Configuración de IA por sección (solo super_admin).
 *
 * Permite elegir, por cada tarea IA del proyecto (texto o visión), qué
 * proveedor (claude | openrouter) y modelo usar — sin tocar código.
 * Ver tabla ia_config_secciones y el wrapper llamarLLM() en funciones.php.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requiereSuperAdmin();

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

$mensaje = '';
$error   = '';

// Backfill one-off: generar mensaje WhatsApp "auto" para fichas que todavía
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

    $sql = "UPDATE ia_config_secciones
            SET proveedor = :proveedor, modelo = :modelo, max_tokens = :max_tokens, actualizado_por = :admin_id
            WHERE seccion = :seccion";
    $upd = $pdo->prepare($sql);

    $actualizadas = 0;
    foreach ($secciones as $seccion) {
        $seccion   = trim((string) $seccion);
        $proveedor = in_array($proveedores[$seccion] ?? '', ['claude', 'openrouter'], true) ? $proveedores[$seccion] : 'claude';
        $modelo    = trim((string) ($modelos[$seccion] ?? ''));
        $tokens    = max(50, min(8000, (int) ($maxTokens[$seccion] ?? 1500)));

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
    $mensaje = (int) $_GET['saved'] . ' sección(es) actualizadas correctamente.';
}
if (isset($_GET['backfill'])) {
    $mensaje = 'Backfill de mensaje WhatsApp: ' . (int) $_GET['backfill'] . ' de ' . (int) ($_GET['total'] ?? 0) . ' fichas generadas/actualizadas (las que ya tenían una versión manual o IA activa no se tocaron).';
}

$claudeOk      = defined('CLAUDE_API_KEY') && CLAUDE_API_KEY !== '';
$openrouterOk  = defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '';

$config = $pdo->query("SELECT * FROM ia_config_secciones ORDER BY tipo, seccion")->fetchAll(PDO::FETCH_ASSOC);
$porTipo = ['texto' => [], 'vision' => []];
foreach ($config as $row) {
    $porTipo[$row['tipo']][] = $row;
}

// Sugerencias de modelos (no exhaustivo, solo para orientar — el campo es texto libre)
$sugerenciasClaude     = ['claude-haiku-4-5-20251001', 'claude-sonnet-4-5-20250929', 'claude-sonnet-4-6'];
$sugerenciasOpenRouter = ['openai/gpt-4o-mini', 'anthropic/claude-3.5-haiku', 'anthropic/claude-3.5-sonnet', 'google/gemini-flash-1.5', 'meta-llama/llama-3.1-8b-instruct'];

$titulo_pagina = 'Configuración IA — Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <h1 class="admin-page-title">Configuración IA por sección</h1>
        <p class="admin-page-subtitle">
            Elegí qué proveedor y modelo usa cada tarea de IA del panel. Cambios aplican de inmediato, sin tocar código.
        </p>
    </header>

    <!-- Estado de las API keys -->
    <div style="display:flex; gap:var(--espacio-tres); flex-wrap:wrap; margin-bottom:var(--espacio-cuatro);">
        <span class="admin-pill <?php echo $claudeOk ? 'admin-pill--exito' : 'admin-pill--error'; ?>">
            <i data-lucide="<?php echo $claudeOk ? 'check-circle-2' : 'circle-x'; ?>" style="width:12px;height:12px;"></i>
            CLAUDE_API_KEY <?php echo $claudeOk ? 'configurada' : 'NO configurada'; ?>
        </span>
        <span class="admin-pill <?php echo $openrouterOk ? 'admin-pill--exito' : 'admin-pill--error'; ?>">
            <i data-lucide="<?php echo $openrouterOk ? 'check-circle-2' : 'circle-x'; ?>" style="width:12px;height:12px;"></i>
            OPENROUTER_API_KEY <?php echo $openrouterOk ? 'configurada' : 'NO configurada'; ?>
        </span>
    </div>

    <?php if ($mensaje): ?>
    <div class="admin-banner admin-banner--success" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="check-circle-2" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content"><?php echo htmlspecialchars($mensaje); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!$claudeOk || !$openrouterOk): ?>
    <div class="admin-banner admin-banner--warning" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="alert-triangle" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            Si asignás una sección a un proveedor cuya API key no está configurada, esa sección fallará al usarse.
            Las keys se configuran en el <code>.env</code> del servidor (nunca en este panel).
        </div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?php foreach (['texto' => 'Secciones de texto', 'vision' => 'Secciones de visión (análisis de imágenes)'] as $tipo => $tituloGrupo): ?>
        <?php if (empty($porTipo[$tipo])) continue; ?>
        <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
            <h2 class="ficha-card__title">
                <i data-lucide="<?php echo $tipo === 'texto' ? 'file-text' : 'image'; ?>" class="icono"></i>
                <?php echo $tituloGrupo; ?>
            </h2>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Sección</th>
                            <th>Proveedor</th>
                            <th>Modelo</th>
                            <th>Max tokens</th>
                            <th>Última actualización</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porTipo[$tipo] as $row): $sec = $row['seccion']; ?>
                        <tr>
                            <td>
                                <input type="hidden" name="seccion[]" value="<?php echo htmlspecialchars($sec); ?>">
                                <strong><?php echo htmlspecialchars($row['label']); ?></strong>
                                <div class="admin-text-muted" style="font-size:var(--admin-caption); font-family:monospace;"><?php echo htmlspecialchars($sec); ?></div>
                            </td>
                            <td>
                                <select name="proveedor[<?php echo htmlspecialchars($sec); ?>]" class="field__select">
                                    <option value="claude" <?php echo $row['proveedor'] === 'claude' ? 'selected' : ''; ?>>Claude</option>
                                    <option value="openrouter" <?php echo $row['proveedor'] === 'openrouter' ? 'selected' : ''; ?>>OpenRouter</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="modelo[<?php echo htmlspecialchars($sec); ?>]"
                                       class="field__input" style="min-width:220px;"
                                       list="modelos-<?php echo $tipo; ?>"
                                       value="<?php echo htmlspecialchars($row['modelo']); ?>">
                            </td>
                            <td>
                                <input type="number" name="max_tokens[<?php echo htmlspecialchars($sec); ?>]"
                                       class="field__input" style="width:100px;" min="50" max="8000" step="50"
                                       value="<?php echo (int) $row['max_tokens']; ?>">
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
        </section>
        <?php endforeach; ?>

        <datalist id="modelos-texto">
            <?php foreach (array_merge($sugerenciasClaude, $sugerenciasOpenRouter) as $m): ?>
            <option value="<?php echo htmlspecialchars($m); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="modelos-vision">
            <option value="claude-sonnet-4-6">
            <option value="claude-sonnet-4-5-20250929">
            <option value="openai/gpt-4o">
            <option value="anthropic/claude-3.5-sonnet">
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
            Genera el mensaje WhatsApp "auto" para todas las fichas que todavía no tienen ninguna versión guardada
            (ej. después de desplegar esta feature). No pisa fichas donde ya se activó una versión manual o de IA.
        </p>
        <form method="POST" onsubmit="return confirm('¿Generar el mensaje WhatsApp automático para todas las fichas que aún no tienen ninguna versión guardada?');">
            <input type="hidden" name="accion" value="backfill_whatsapp">
            <button type="submit" class="boton dos">
                <i data-lucide="message-circle" class="icono"></i>
                Generar mensajes WhatsApp faltantes
            </button>
        </form>
    </section>

</div>

<?php include 'footer.php'; ?>
