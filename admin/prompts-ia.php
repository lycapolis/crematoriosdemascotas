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
$probable     = iaPromptProbable($pdo, $clave);

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
    .prompt-ia__panel { margin-top: var(--espacio-cuatro); padding: var(--espacio-tres); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); background: var(--admin-superficie); }
    .prompt-ia__panel[hidden] { display: none; }
    .prompt-ia__panel-titulo { margin: 0 0 var(--espacio-dos); font-size: 1rem; }
    .prompt-ia__diff { font-family: ui-monospace, Consolas, monospace; font-size: .8rem; line-height: 1.5; border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); max-height: 380px; overflow: auto; }
    .prompt-ia__diff-linea { padding: .1rem .6rem; white-space: pre-wrap; word-break: break-word; }
    .prompt-ia__diff-linea--add, .prompt-ia__diff-leyenda--add { background: #dcfce7; color: #14532d; }
    .prompt-ia__diff-linea--del, .prompt-ia__diff-leyenda--del { background: #fee2e2; color: #7f1d1d; text-decoration: line-through; }
    .prompt-ia__diff-leyenda { padding: 0 .35rem; border-radius: 3px; }
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
                    <?php if ($probable): ?>
                    <button type="button" class="boton dos" id="prompt-ia-probar">
                        <i data-lucide="flask-conical" class="icono"></i> Probar
                    </button>
                    <?php endif; ?>
                    <button type="button" class="boton dos" id="prompt-ia-mejorar">
                        <i data-lucide="sparkles" class="icono"></i> Mejorar con IA
                    </button>
                </div>
                <p class="field__hint" style="margin-top:.5rem;">
                    «Probar» y «Mejorar con IA» usan lo que hay escrito ahora, aunque no lo hayas guardado, y gastan tokens reales. Nada se guarda hasta que pulses Guardar.
                </p>
            </form>

            <!-- ── Panel «Mejorar con IA» ── -->
            <div class="prompt-ia__panel" id="prompt-ia-panel-mejorar" hidden>
                <h3 class="prompt-ia__panel-titulo">Mejorar con IA</h3>
                <div class="field">
                    <label class="field__label" for="prompt-ia-instruccion">¿Qué quieres cambiar? <span style="font-weight:400;">(opcional)</span></label>
                    <input type="text" class="field__input" id="prompt-ia-instruccion" maxlength="500" placeholder="Ej.: que proponga ideas menos tristes y más variadas">
                </div>
                <div class="prompt-ia__acciones" style="margin-top:var(--espacio-dos);">
                    <button type="button" class="boton uno" id="prompt-ia-mejorar-go">
                        <i data-lucide="wand-sparkles" class="icono"></i> <span>Pedir propuesta</span>
                    </button>
                </div>
                <div id="prompt-ia-mejorar-res" hidden>
                    <p class="field__hint" id="prompt-ia-mejorar-resumen" style="margin-top:var(--espacio-tres);"></p>
                    <div class="prompt-ia__diff" id="prompt-ia-diff" aria-label="Diferencias entre tu texto y la propuesta"></div>
                    <p class="field__hint"><span class="prompt-ia__diff-leyenda prompt-ia__diff-leyenda--del">Rojo</span> = se quita · <span class="prompt-ia__diff-leyenda prompt-ia__diff-leyenda--add">Verde</span> = se añade.</p>
                    <div class="prompt-ia__acciones">
                        <button type="button" class="boton uno" id="prompt-ia-usar">Usar en el editor</button>
                        <button type="button" class="boton dos" id="prompt-ia-otra">Pedir otra</button>
                        <button type="button" class="boton dos" id="prompt-ia-descartar">Descartar</button>
                    </div>
                </div>
            </div>

            <!-- ── Panel «Probar» ── -->
            <?php if ($probable): ?>
            <div class="prompt-ia__panel" id="prompt-ia-panel-probar" hidden>
                <h3 class="prompt-ia__panel-titulo">Resultado de la prueba</h3>
                <p class="field__hint" id="prompt-ia-probar-meta"></p>
                <span class="field__label">Respuesta de la IA</span>
                <pre class="prompt-ia__contrato" id="prompt-ia-probar-resp"></pre>
                <span class="field__label" style="margin-top:var(--espacio-tres); display:block;">Interpretada por el código</span>
                <pre class="prompt-ia__contrato" id="prompt-ia-probar-json"></pre>
                <details style="margin-top:var(--espacio-tres);">
                    <summary style="cursor:pointer; font-weight:700;">Prompt exacto enviado</summary>
                    <pre class="prompt-ia__contrato" id="prompt-ia-probar-prompt" style="margin-top:.6rem;"></pre>
                </details>
            </div>
            <?php endif; ?>

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

// ── Probar y Mejorar con IA ──
(function () {
    var CSRF = <?php echo json_encode(generarTokenCSRF()); ?>;
    var CLAVE = <?php echo json_encode($clave); ?>;
    var AJAX = 'prompts-ia-ajax.php';
    var $ = function (id) { return document.getElementById(id); };
    var texto = $('prompt-ia-texto'), evitar = $('prompt-ia-evitar');
    var propuesta = '';

    function llamar(accion, extra, boton) {
        var etiqueta = boton.innerHTML;
        boton.disabled = true;
        boton.textContent = 'Trabajando…';
        var body = Object.assign({ accion: accion, clave: CLAVE, texto: texto.value, evitar: evitar ? evitar.value : '' }, extra || {});
        return fetch(AJAX, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); })
          .catch(function () { return { ok: false, error: 'No se pudo conectar con el servidor.' }; })
          .then(function (d) {
              boton.disabled = false;
              boton.innerHTML = etiqueta;
              if (window.lucide) lucide.createIcons();
              return d;
          });
    }

    // Diff línea a línea (LCS)
    function diffLineas(a, b) {
        var A = a.split('\n'), B = b.split('\n'), n = A.length, m = B.length, i, j;
        var L = [];
        for (i = 0; i <= n; i++) { L.push(new Array(m + 1).fill(0)); }
        for (i = n - 1; i >= 0; i--) for (j = m - 1; j >= 0; j--)
            L[i][j] = A[i] === B[j] ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
        var out = []; i = 0; j = 0;
        while (i < n && j < m) {
            if (A[i] === B[j]) { out.push(['=', A[i]]); i++; j++; }
            else if (L[i + 1][j] >= L[i][j + 1]) { out.push(['-', A[i++]]); }
            else { out.push(['+', B[j++]]); }
        }
        while (i < n) out.push(['-', A[i++]]);
        while (j < m) out.push(['+', B[j++]]);
        return out;
    }

    function pintarDiff(antes, despues) {
        var cont = $('prompt-ia-diff');
        cont.textContent = '';
        diffLineas(antes, despues).forEach(function (p) {
            var d = document.createElement('div');
            d.className = 'prompt-ia__diff-linea' + (p[0] === '+' ? ' prompt-ia__diff-linea--add' : p[0] === '-' ? ' prompt-ia__diff-linea--del' : '');
            d.textContent = (p[0] === '=' ? '  ' : p[0] + ' ') + p[1];
            cont.appendChild(d);
        });
    }

    var btnMejorar = $('prompt-ia-mejorar');
    var btnGo = $('prompt-ia-mejorar-go');
    btnMejorar.addEventListener('click', function () {
        var p = $('prompt-ia-panel-mejorar');
        p.hidden = !p.hidden;
        if (!p.hidden) $('prompt-ia-instruccion').focus();
    });

    function pedir() {
        if (texto.readOnly) return;
        var res = $('prompt-ia-mejorar-res');
        res.hidden = true;
        llamar('mejorar', { instruccion: $('prompt-ia-instruccion').value }, btnGo).then(function (d) {
            if (!d.ok) { alert(d.error || 'No se pudo mejorar el prompt.'); return; }
            propuesta = d.texto;
            var resumen = d.resumen || '';
            if (d.perdidas && d.perdidas.length) resumen += ' Atención: la propuesta quitó ' + d.perdidas.join(', ') + ' (opcional/es).';
            $('prompt-ia-mejorar-resumen').textContent = (resumen + ' [modelo: ' + d.modelo + ']').trim();
            pintarDiff(texto.value.trim().replace(/\r\n/g, '\n'), propuesta);
            res.hidden = false;
        });
    }
    btnGo.addEventListener('click', pedir);
    $('prompt-ia-otra').addEventListener('click', pedir);
    $('prompt-ia-descartar').addEventListener('click', function () {
        propuesta = '';
        $('prompt-ia-mejorar-res').hidden = true;
        $('prompt-ia-panel-mejorar').hidden = true;
    });
    $('prompt-ia-usar').addEventListener('click', function () {
        if (!propuesta) return;
        texto.value = propuesta;
        texto.focus();
        $('prompt-ia-mejorar-res').hidden = true;
        $('prompt-ia-panel-mejorar').hidden = true;
        alert('Propuesta pasada al editor. Revísala y pulsa Guardar para aplicarla.');
    });

    var btnProbar = $('prompt-ia-probar');
    if (btnProbar) btnProbar.addEventListener('click', function () {
        llamar('probar', {}, btnProbar).then(function (d) {
            if (!d.ok) { alert(d.error || 'No se pudo probar el prompt.'); return; }
            $('prompt-ia-probar-meta').textContent = 'Datos de ejemplo del catálogo · modelo ' + d.modelo + ' · ' + d.segundos + ' s';
            $('prompt-ia-probar-resp').textContent = d.respuesta;
            $('prompt-ia-probar-json').textContent = d.json || 'El código NO pudo leer esta respuesta como JSON: con este prompt la tarea real fallaría.';
            $('prompt-ia-probar-prompt').textContent = d.prompt;
            $('prompt-ia-panel-probar').hidden = false;
            $('prompt-ia-panel-probar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
})();
</script>

<?php include 'footer.php'; ?>
