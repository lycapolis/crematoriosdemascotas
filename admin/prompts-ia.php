<?php
/**
 * Panel Admin — Prompts de IA (solo super_admin).
 *
 * Lista las tareas de IA repetitivas del sitio y permite editar la parte creativa de su
 * prompt, con historial de versiones y «restaurar original». El formato de respuesta
 * (JSON) y las salvaguardas son el «contrato técnico»: se muestran en solo lectura.
 * Catálogo, textos originales y lógica: includes/ia-prompts.php.
 * Proveedor/modelo de cada tarea: admin/configuracion-ia.php.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/ia-prompts.php';

requerirAutenticacion();
requiereSuperAdmin();

$pdo         = obtenerConexion();
$adminActual = obtenerAdminActual();
$adminId     = (int) $adminActual['id'];
$catalogo    = iaPromptsCatalogo();
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

try {
    $tablasOk = (bool) $pdo->query("SHOW TABLES LIKE 'ia_prompts_versiones'")->fetchColumn()
             && (bool) $pdo->query("SHOW TABLES LIKE 'ia_prompts'")->fetchColumn();
} catch (Throwable $ex) {
    $tablasOk = false;
}

$clave = (string) ($_GET['p'] ?? $_POST['clave'] ?? array_key_first($catalogo));
if (!isset($catalogo[$clave])) $clave = array_key_first($catalogo);
$cat = $catalogo[$clave];

$errores = [];
$textoForm  = null; // si hay error, se conserva lo escrito
$evitarForm = null;

/** Normaliza saltos de línea y espacios finales. */
$limpiar = fn(string $s) => trim(str_replace("\r\n", "\n", $s));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tablasOk) {
        $errores[] = 'Falta correr la migración create_ia_prompts.sql en la base de datos.';
    } elseif (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
        $errores[] = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        $accion = (string) ($_POST['accion'] ?? '');

        if ($accion === 'guardar') {
            $texto  = $limpiar((string) ($_POST['texto'] ?? ''));
            $evitar = $cat['evitar'] !== null ? $limpiar((string) ($_POST['evitar'] ?? '')) : null;
            $errores = iaPromptValidar($clave, $texto);
            if (!$errores) {
                iaPromptGuardar($pdo, $clave, $texto, $evitar, $adminId, 'Guardado');
                header('Location: ' . BASE_URL . '/admin/prompts-ia.php?p=' . urlencode($clave) . '&ok=guardado');
                exit;
            }
            $textoForm = $texto; $evitarForm = $evitar;

        } elseif ($accion === 'restaurar') {
            iaPromptRestaurar($pdo, $clave, $adminId);
            header('Location: ' . BASE_URL . '/admin/prompts-ia.php?p=' . urlencode($clave) . '&ok=restaurado');
            exit;

        } elseif ($accion === 'version') {
            $st = $pdo->prepare('SELECT texto, evitar FROM ia_prompts_versiones WHERE id = :id AND clave = :c');
            $st->execute([':id' => (int) ($_POST['version_id'] ?? 0), ':c' => $clave]);
            if ($v = $st->fetch(PDO::FETCH_ASSOC)) {
                iaPromptGuardar($pdo, $clave, $v['texto'], $v['evitar'], $adminId, 'Recuperada versión #' . (int) $_POST['version_id']);
                header('Location: ' . BASE_URL . '/admin/prompts-ia.php?p=' . urlencode($clave) . '&ok=version');
                exit;
            }
            $errores[] = 'No se encontró esa versión.';
        }
    }
}

$mensajesOk = ['guardado' => 'Prompt guardado. Se aplica desde la próxima vez que se use.', 'restaurado' => 'Vuelto al texto original.', 'version' => 'Versión recuperada y guardada como la vigente.'];
$mensaje = $mensajesOk[$_GET['ok'] ?? ''] ?? '';

$textoActual  = $textoForm  ?? iaPromptTexto($pdo, $clave);
$evitarActual = $evitarForm ?? iaPromptEvitar($pdo, $clave);
$editado      = $tablasOk && iaPromptFila($pdo, $clave) !== null;
$versiones    = $tablasOk ? iaPromptVersiones($pdo, $clave) : [];

// Vista previa: el texto vigente con datos de ejemplo + contrato
$vistaPrevia = iaPromptSustituir($textoActual, $cat['variables'], $cat['ejemplo'] + ['evitar' => iaPromptEvitarFormateado($evitarActual)]);
$vistaPrevia = rtrim($vistaPrevia) . ($cat['contrato'] !== '' ? ($cat['union'] ?? "\n\n") . $cat['contrato'] : '');

// Agrupar la lista por «grupo»
$grupos = [];
foreach ($catalogo as $k => $c) $grupos[$c['grupo']][$k] = $c;

$titulo_pagina = 'Prompts de IA — Admin';
include 'header.php';
?>

<style>
    .prompt-ia { display: grid; grid-template-columns: 300px 1fr; gap: var(--espacio-cuatro); align-items: start; }
    @media (max-width: 900px) { .prompt-ia { grid-template-columns: 1fr; } }
    .prompt-ia__grupo { font-size: var(--admin-caption); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--admin-text-suave); margin: var(--espacio-tres) 0 .4rem; }
    .prompt-ia__grupo:first-child { margin-top: 0; }
    .prompt-ia__item { display: block; padding: .6rem .75rem; border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); text-decoration: none; color: inherit; margin-bottom: .4rem; background: var(--admin-superficie); }
    .prompt-ia__item:hover { border-color: var(--color-uno); }
    .prompt-ia__item--activo { border-color: var(--color-uno); box-shadow: 0 0 0 1px var(--color-uno); }
    .prompt-ia__item-nombre { font-weight: 700; font-size: var(--admin-body-sm); display: block; }
    .prompt-ia__item-estado { font-size: var(--admin-caption); color: var(--admin-text-suave); }
    .prompt-ia__guia { display: grid; gap: var(--espacio-dos); margin: 0 0 var(--espacio-tres); }
    .prompt-ia__guia dt { font-size: var(--admin-caption); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--admin-text-suave); }
    .prompt-ia__guia dd { margin: .15rem 0 0; line-height: 1.5; }
    .prompt-ia__texto { min-height: 360px; font-family: ui-monospace, Consolas, monospace; font-size: .85rem; line-height: 1.5; }
    .prompt-ia__vars { display: flex; flex-wrap: wrap; gap: .4rem; margin: .4rem 0 var(--espacio-tres); }
    .prompt-ia__var { font-family: ui-monospace, Consolas, monospace; font-size: .8rem; padding: .2rem .5rem; border: 1px solid var(--admin-linea); border-radius: 999px; background: var(--admin-superficie); cursor: pointer; }
    .prompt-ia__var:hover { border-color: var(--color-uno); }
    .prompt-ia__var--obligatoria { font-weight: 700; }
    .prompt-ia__contrato { white-space: pre-wrap; font-family: ui-monospace, Consolas, monospace; font-size: .8rem; line-height: 1.5; background: var(--admin-fondo, #f4f4f5); border: 1px dashed var(--admin-linea); border-radius: var(--admin-r-md); padding: var(--espacio-tres); margin: 0; }
    .prompt-ia__acciones { display: flex; flex-wrap: wrap; gap: var(--espacio-dos); margin-top: var(--espacio-tres); }
    .prompt-ia__versiones { width: 100%; border-collapse: collapse; font-size: var(--admin-body-sm); }
    .prompt-ia__versiones td { padding: .35rem .5rem; border-top: 1px solid var(--admin-linea); }
</style>

<div class="admin-page">

    <p style="margin-bottom:var(--espacio-dos);">
        <a href="configuracion.php" class="admin-text-muted">← Configuración</a>
    </p>

    <header class="admin-page-header">
        <h1 class="admin-page-title">Prompts de IA</h1>
        <p class="admin-page-subtitle">
            Las instrucciones que sigue la IA en las tareas repetitivas del sitio. Edita la parte creativa;
            el formato de respuesta y las salvaguardas son fijos para no romper nada.
            El modelo de cada tarea se cambia en <a href="configuracion-ia.php">Configuración de IA</a>.
        </p>
    </header>

    <?php if (!$tablasOk): ?>
    <div class="admin-banner admin-banner--warning" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="alert-triangle" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            Falta crear las tablas de esta sección: ejecuta <code>admin/migrations/create_ia_prompts.sql</code> en phpMyAdmin.
            Mientras tanto la IA usa los textos originales y no se puede guardar.
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
    <div class="admin-banner admin-banner--success" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="check-circle-2" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content"><?php echo $e($mensaje); ?></div>
    </div>
    <?php endif; ?>

    <?php if ($errores): ?>
    <div class="admin-banner admin-banner--error" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="circle-x" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            <?php foreach ($errores as $m): ?><div><?php echo $e($m); ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="prompt-ia">

        <!-- ── Lista de prompts ── -->
        <nav class="prompt-ia__lista" aria-label="Prompts de IA">
            <?php foreach ($grupos as $nombreGrupo => $items): ?>
            <div class="prompt-ia__grupo"><?php echo $e($nombreGrupo); ?></div>
            <?php foreach ($items as $k => $c):
                $esEditado = $tablasOk && iaPromptFila($pdo, $k) !== null; ?>
            <a class="prompt-ia__item <?php echo $k === $clave ? 'prompt-ia__item--activo' : ''; ?>"
               href="prompts-ia.php?p=<?php echo urlencode($k); ?>">
                <span class="prompt-ia__item-nombre"><?php echo $e($c['nombre']); ?></span>
                <span class="prompt-ia__item-estado"><?php echo $esEditado ? '✎ Editado' : 'Original'; ?></span>
            </a>
            <?php endforeach; endforeach; ?>

            <div class="prompt-ia__grupo">Fuera del sitio</div>
            <div class="prompt-ia__item" style="cursor:default;">
                <span class="prompt-ia__item-nombre">Asistente de visitantes</span>
                <span class="prompt-ia__item-estado">Vive en n8n: su prompt se edita allí, en el nodo del agente de IA. Aquí solo se documentará.</span>
            </div>
        </nav>

        <!-- ── Editor ── -->
        <section class="ficha-card prompt-ia__editor">
            <h2 class="ficha-card__title">
                <i data-lucide="message-square-text" class="icono"></i>
                <?php echo $e($cat['nombre']); ?>
                <span class="admin-pill <?php echo $editado ? 'admin-pill--exito' : ''; ?>" style="margin-left:.5rem;"><?php echo $editado ? 'Editado' : 'Original'; ?></span>
            </h2>

            <dl class="prompt-ia__guia">
                <div>
                    <dt>Qué hace</dt>
                    <dd><?php echo $e($cat['que_hace']); ?></dd>
                </div>
                <div>
                    <dt>Dónde se ve el resultado</dt>
                    <dd><?php echo $e($cat['donde_se_ve']); ?></dd>
                </div>
                <div>
                    <dt>Tarea y modelo</dt>
                    <dd><code><?php echo $e($cat['tarea']); ?></code> — <a href="configuracion-ia.php">cambiar proveedor o modelo</a></dd>
                </div>
            </dl>

            <form method="POST" action="prompts-ia.php?p=<?php echo urlencode($clave); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $e(generarTokenCSRF()); ?>">
                <input type="hidden" name="clave" value="<?php echo $e($clave); ?>">

                <div class="field">
                    <label class="field__label" for="prompt-ia-texto">Prompt (parte editable)</label>
                    <textarea class="field__textarea prompt-ia__texto" id="prompt-ia-texto" name="texto" <?php echo $tablasOk ? '' : 'readonly'; ?>><?php echo $e($textoActual); ?></textarea>
                </div>

                <?php if ($cat['variables']): ?>
                <p class="field__hint" style="margin:.6rem 0 0;">Variables disponibles: haz clic para insertarla donde está el cursor. Las <strong>en negrita</strong> son obligatorias.</p>
                <div class="prompt-ia__vars">
                    <?php foreach ($cat['variables'] as $nombre => $def): ?>
                    <button type="button" class="prompt-ia__var <?php echo !empty($def['obligatoria']) ? 'prompt-ia__var--obligatoria' : ''; ?>"
                            data-var="{<?php echo $e($nombre); ?>}" title="<?php echo $e($def['desc']); ?>">{<?php echo $e($nombre); ?>}</button>
                    <?php endforeach; ?>
                </div>
                <ul class="field__hint" style="margin:0 0 var(--espacio-tres); padding-left:1.1rem;">
                    <?php foreach ($cat['variables'] as $nombre => $def): ?>
                    <li><code>{<?php echo $e($nombre); ?>}</code> — <?php echo $e($def['desc']); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($cat['evitar'] !== null): ?>
                <div class="field">
                    <label class="field__label" for="prompt-ia-evitar">Temas a evitar <span style="font-weight:400;">(uno por línea; entra en el prompt como <code>{evitar}</code>)</span></label>
                    <textarea class="field__textarea" id="prompt-ia-evitar" name="evitar" rows="6" <?php echo $tablasOk ? '' : 'readonly'; ?>><?php echo $e($evitarActual); ?></textarea>
                    <p class="field__hint">Motivos que la IA tiende a repetir. Añade aquí lo que veas demasiado en las imágenes del blog.</p>
                </div>
                <?php endif; ?>

                <div class="field">
                    <span class="field__label">Contrato técnico (fijo, no editable)</span>
                    <pre class="prompt-ia__contrato"><?php echo $e($cat['contrato']); ?></pre>
                    <p class="field__hint">
                        El código lee la respuesta de la IA con este formato, por eso no se puede cambiar desde aquí. Se añade siempre al final del prompt.
                        <?php if (!empty($cat['contrato_nota'])) echo $e($cat['contrato_nota']); ?>
                    </p>
                </div>

                <div class="prompt-ia__acciones">
                    <button type="submit" name="accion" value="guardar" class="boton uno" <?php echo $tablasOk ? '' : 'disabled'; ?>>
                        <i data-lucide="save" class="icono"></i> Guardar
                    </button>
                    <?php if ($editado): ?>
                    <button type="submit" name="accion" value="restaurar" class="boton dos"
                            onclick="return confirm('¿Volver al texto original? Tu versión actual queda en el historial.');">
                        <i data-lucide="rotate-ccw" class="icono"></i> Restaurar original
                    </button>
                    <?php endif; ?>
                </div>
            </form>

            <details style="margin-top:var(--espacio-cuatro);">
                <summary style="cursor:pointer; font-weight:700;">Así lo recibe la IA (con datos de ejemplo)</summary>
                <pre class="prompt-ia__contrato" style="margin-top:.6rem;"><?php echo $e($vistaPrevia); ?></pre>
                <p class="field__hint">Vista previa del texto guardado; los datos reales se completan al usarlo.</p>
            </details>

            <details style="margin-top:var(--espacio-tres);">
                <summary style="cursor:pointer; font-weight:700;">Historial de versiones (<?php echo count($versiones); ?>)</summary>
                <?php if ($versiones): ?>
                <table class="prompt-ia__versiones" style="margin-top:.6rem;">
                    <?php foreach ($versiones as $v): ?>
                    <tr>
                        <td>#<?php echo (int) $v['id']; ?></td>
                        <td><?php echo $e(date('d/m/Y H:i', strtotime($v['creado_en']))); ?></td>
                        <td><?php echo $e($v['nota']); ?> · <?php echo $e($v['autor'] ?: '—'); ?></td>
                        <td style="text-align:right;">
                            <form method="POST" action="prompts-ia.php?p=<?php echo urlencode($clave); ?>" style="display:inline;"
                                  onsubmit="return confirm('¿Recuperar esta versión como la vigente?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $e(generarTokenCSRF()); ?>">
                                <input type="hidden" name="clave" value="<?php echo $e($clave); ?>">
                                <input type="hidden" name="version_id" value="<?php echo (int) $v['id']; ?>">
                                <button type="submit" name="accion" value="version" class="boton dos pequeno">Recuperar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?>
                <p class="field__hint" style="margin-top:.6rem;">Todavía no hay versiones: este prompt usa el texto original.</p>
                <?php endif; ?>
            </details>
        </section>
    </div>
</div>

<script>
// Insertar {variable} en el cursor del prompt
document.querySelectorAll('.prompt-ia__var').forEach(function (b) {
    b.addEventListener('click', function () {
        var t = document.getElementById('prompt-ia-texto');
        if (!t || t.readOnly) return;
        var ini = t.selectionStart, fin = t.selectionEnd;
        t.value = t.value.slice(0, ini) + b.dataset.var + t.value.slice(fin);
        t.focus();
        t.selectionStart = t.selectionEnd = ini + b.dataset.var.length;
    });
});
</script>

<?php include 'footer.php'; ?>
