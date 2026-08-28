<?php
/**
 * Panel Admin — Configuración de formularios / throttling (solo super_admin).
 *
 * Controla las reglas de throttling del widget lead-capture (modal que
 * intercepta clicks en tel/wa/maps/web de las fichas + burbuja flotante).
 * Ver tabla formularios_config, obtenerConfigFormularios() en
 * includes/funciones.php y assets/js/lead-capture.js (window.LC_THROTTLE).
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requiereSuperAdmin();

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activo   = isset($_POST['throttling_activo']) ? 1 : 0;
    $cap      = max(1, min(100,  (int) ($_POST['cap_global_sesion'] ?? 4)));
    $skipMin  = max(0, min(1440, (int) ($_POST['skip_minutos']      ?? 10)));
    $submitH  = max(0, min(720,  (int) ($_POST['submit_horas']      ?? 24)));
    $cookieD  = max(1, min(365,  (int) ($_POST['cookie_dias']       ?? 1)));

    $upd = $pdo->prepare(
        "UPDATE formularios_config
         SET throttling_activo = :activo,
             cap_global_sesion = :cap,
             skip_minutos      = :skip_min,
             submit_horas      = :submit_h,
             cookie_dias       = :cookie_d,
             actualizado_por   = :admin_id
         WHERE clave = 'lead_capture'"
    );
    $upd->execute([
        ':activo'    => $activo,
        ':cap'       => $cap,
        ':skip_min'  => $skipMin,
        ':submit_h'  => $submitH,
        ':cookie_d'  => $cookieD,
        ':admin_id'  => $adminActual['id'],
    ]);

    header('Location: ' . BASE_URL . '/admin/configuracion-formularios.php?saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $mensaje = 'Configuración guardada correctamente. Los cambios aplican en la próxima carga de página del sitio.';
}

$cfg = obtenerConfigFormularios($pdo);

$titulo_pagina = 'Configuración de Formularios — Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <h1 class="admin-page-title">Configuración de formularios</h1>
        <p class="admin-page-subtitle">
            Reglas de throttling del widget lead-capture (modal que intercepta los botones de contacto
            de las fichas y la burbuja de WhatsApp). Cambios aplican de inmediato, sin tocar código.
        </p>
    </header>

    <?php if ($mensaje): ?>
    <div class="admin-banner admin-banner--success" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="check-circle-2" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content"><?php echo htmlspecialchars($mensaje); ?></div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
            <h2 class="ficha-card__title">
                <i data-lucide="gauge" class="icono"></i>
                Throttling del modal lead-capture
            </h2>

            <!-- Toggle principal -->
            <label class="field__opcion" style="margin-bottom:var(--espacio-tres); font-size:1rem;">
                <input type="checkbox" class="field__check" id="throttling_activo" name="throttling_activo" value="1"
                       <?php echo $cfg['throttling_activo'] ? 'checked' : ''; ?>>
                <span><strong>Throttling activado</strong></span>
            </label>
            <p id="throttling-estado" style="font-size:.85rem; color:var(--admin-text-suave); margin:0 0 var(--espacio-cuatro); line-height:1.5;">
            </p>

            <!-- Límites (se atenúan cuando el throttling está off) -->
            <fieldset id="throttling-limites" style="border:0; padding:0; margin:0;">
                <div style="display:grid; gap:var(--espacio-cuatro); grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">

                    <div class="field" style="margin-bottom:0;">
                        <label class="field__label" for="cap_global_sesion">Máx. aperturas por sesión</label>
                        <input type="number" class="field__input" id="cap_global_sesion" name="cap_global_sesion"
                               min="1" max="100" value="<?php echo (int) $cfg['cap_global_sesion']; ?>">
                        <p class="admin-text-muted" style="font-size:.78rem; margin:.35rem 0 0; line-height:1.4;">
                            Superado este número de modals abiertos, el click va directo al destino sin pedir datos.
                            La burbuja flotante no cuenta.
                        </p>
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label class="field__label" for="skip_minutos">Silencio tras cerrar/saltar (minutos)</label>
                        <input type="number" class="field__input" id="skip_minutos" name="skip_minutos"
                               min="0" max="1440" value="<?php echo (int) $cfg['skip_minutos']; ?>">
                        <p class="admin-text-muted" style="font-size:.78rem; margin:.35rem 0 0; line-height:1.4;">
                            Si el usuario cierra el modal o elige "Ir directo", ese canal concreto de esa ficha
                            (tel, WhatsApp, mapa o web) queda en silencio durante este tiempo.
                        </p>
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label class="field__label" for="submit_horas">Silencio tras enviar el form (horas)</label>
                        <input type="number" class="field__input" id="submit_horas" name="submit_horas"
                               min="0" max="720" value="<?php echo (int) $cfg['submit_horas']; ?>">
                        <p class="admin-text-muted" style="font-size:.78rem; margin:.35rem 0 0; line-height:1.4;">
                            Una vez que el usuario envía sus datos, no se le vuelve a pedir en ninguna ficha
                            ni canal durante este tiempo (es la regla más agresiva).
                        </p>
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label class="field__label" for="cookie_dias">Vida de la cookie de estado (días)</label>
                        <input type="number" class="field__input" id="cookie_dias" name="cookie_dias"
                               min="1" max="365" value="<?php echo (int) $cfg['cookie_dias']; ?>">
                        <p class="admin-text-muted" style="font-size:.78rem; margin:.35rem 0 0; line-height:1.4;">
                            Cuánto tiempo persiste la cookie <code>lc_state</code> en el navegador del visitante
                            (se renueva en cada interacción).
                        </p>
                    </div>

                </div>
            </fieldset>
        </section>

        <button type="submit" class="boton tres">
            <i data-lucide="save" class="icono"></i>
            Guardar cambios
        </button>
    </form>

</div>

<script>
// Atenúa los inputs de límites cuando el throttling está desactivado
// (quedan editables igual para preparar la config antes de activarla).
(function () {
    var chk     = document.getElementById('throttling_activo');
    var fieldset = document.getElementById('throttling-limites');
    var estado  = document.getElementById('throttling-estado');
    var TXT_ON  = 'Activado: se aplican las reglas de abajo. Quien ya envió el form o superó los límites va directo al destino sin ver el modal.';
    var TXT_OFF = 'Desactivado: el modal aparece en CADA click, sin límite de aperturas ni silencios. Los valores de abajo se guardan pero no aplican hasta que lo actives.';

    function sync() {
        fieldset.style.opacity = chk.checked ? '1' : '.55';
        estado.textContent = chk.checked ? TXT_ON : TXT_OFF;
    }
    chk.addEventListener('change', sync);
    sync();
})();
</script>

<?php include 'footer.php'; ?>
