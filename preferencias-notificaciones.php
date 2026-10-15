<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PREFERENCIAS DE AVISOS POR EMAIL — página pública del negocio
 * ═══════════════════════════════════════════════════════════
 *
 * Enlace «Gestionar mis avisos» de los emails al negocio:
 *   /preferencias-notificaciones.php?e=<email en base64url>&t=<firma HMAC>
 *
 * El negocio no tiene cuenta: el enlace firmado es su credencial. Baja POR EMAIL (no por ficha).
 *
 * - GET  → muestra tres interruptores (reseñas / leads / informes) con el estado actual.
 *          No cambia nada (los antivirus precargan enlaces).
 * - POST (fetch, JSON) → accion = categoria | todo | restaurar. Guarda al instante.
 * - POST de Gmail «Cancelar suscripción» (cuerpo List-Unsubscribe=One-Click) → baja total.
 *
 * Solo afecta a los AVISOS. Las reseñas y la ficha no se tocan.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/notificaciones/despachador.php';

$email  = mb_strtolower(trim(notifB64UrlDecode((string)($_GET['e'] ?? $_POST['e'] ?? ''))));
$token  = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$valido = filter_var($email, FILTER_VALIDATE_EMAIL) && notifTokenPreferenciasValido($email, $token);
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$pdo   = $valido ? obtenerConexion() : null;
$prefs = null;

// ─── Baja de un clic desde Gmail (sin pantalla) ──────────────
if ($valido && $esPost && isset($_POST['List-Unsubscribe'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    if ($pdo) {
        try { notifSuprimir($pdo, $email); echo 'OK'; } catch (\Throwable $e) { http_response_code(500); error_log('[preferencias-notificaciones] ' . $e->getMessage()); }
    } else {
        http_response_code(500);
    }
    exit;
}

// ─── Guardado al instante (fetch) ────────────────────────────
if ($esPost && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if (!$valido || !$pdo) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Enlace no válido.']);
        exit;
    }
    try {
        $antes  = notifPreferencias($pdo, $email);
        $accion = (string)$_POST['accion'];
        if ($accion === 'categoria') {
            notifGuardarCategoria($pdo, $email, (string)($_POST['categoria'] ?? ''), ($_POST['recibir'] ?? '') === '1');
        } elseif ($accion === 'todo') {
            notifSuprimir($pdo, $email);
        } elseif ($accion === 'restaurar') {
            $cats = [];
            foreach (array_keys(notifCategoriasBaja()) as $k) $cats[$k] = ($_POST['c_' . $k] ?? '') === '1';
            notifRestaurarPreferencias($pdo, $email, ($_POST['todo_apagado'] ?? '') === '1', $cats);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Acción no válida.']);
            exit;
        }
        echo json_encode(['ok' => true, 'antes' => $antes, 'ahora' => notifPreferencias($pdo, $email)]);
    } catch (\Throwable $e) {
        error_log('[preferencias-notificaciones] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se ha podido guardar.']);
    }
    exit;
}

if ($valido && $pdo) {
    try { $prefs = notifPreferencias($pdo, $email); } catch (\Throwable $e) { error_log('[preferencias-notificaciones] ' . $e->getMessage()); }
}

$categorias = [
    'resenas'  => ['Reseñas',  'Cuando alguien deja una reseña o se publica tu respuesta.'],
    'leads'    => ['Leads',    'Cuando un cliente te contacta desde tu ficha.'],
    'informes' => ['Informes', 'Resúmenes de actividad de tu ficha.'],
];

header('Referrer-Policy: no-referrer');
$titulo_pagina = 'Tus avisos por email';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';
?>

<style>
    .notif-pref { min-height: 60vh; display: flex; align-items: flex-start; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .notif-pref__inner { width: 100%; max-width: 560px; }
    .notif-pref__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .notif-pref__texto { color: var(--color-seis); line-height: 1.65; margin: 0 0 var(--espacio-cuatro); }
    .notif-pref__lista { list-style: none; margin: 0 0 var(--espacio-cuatro); padding: 0; border: 1px solid rgba(0,0,0,.1); border-radius: 12px; overflow: hidden; background: #fff; }
    .notif-pref__item + .notif-pref__item { border-top: 1px solid rgba(0,0,0,.08); }
    .notif-pref__fila { display: flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) var(--espacio-cuatro); cursor: pointer; }
    .notif-pref__info { flex: 1; min-width: 0; }
    .notif-pref__nombre { display: block; font-weight: 600; color: var(--color-dos); }
    .notif-pref__desc { display: block; font-size: .9em; color: var(--color-seis); line-height: 1.5; margin-top: 2px; }
    .notif-pref__interruptor { position: absolute; opacity: 0; width: 0; height: 0; }
    .notif-pref__pista { position: relative; flex: none; width: 46px; height: 26px; border-radius: 13px; background: #c9c3bd; transition: background .15s; }
    .notif-pref__pista::after { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .15s; }
    .notif-pref__interruptor:checked + .notif-pref__pista { background: #2E7D32; }
    .notif-pref__interruptor:checked + .notif-pref__pista::after { transform: translateX(20px); }
    .notif-pref__interruptor:focus-visible + .notif-pref__pista { outline: 2px solid var(--color-uno, #B8704F); outline-offset: 2px; }
    .notif-pref__interruptor:disabled + .notif-pref__pista { opacity: .6; }
    .notif-pref__estado { min-height: 1.6em; margin: 0 0 var(--espacio-tres); color: var(--color-seis); font-size: .95em; }
    .notif-pref__deshacer { background: none; border: 0; padding: 0; color: var(--color-uno, #B8704F); text-decoration: underline; cursor: pointer; font: inherit; }
    .notif-pref__todo { background: none; border: 0; padding: 0; color: var(--color-seis); text-decoration: underline; cursor: pointer; font: inherit; }
    @media (max-width: 600px) { .notif-pref__fila { padding: var(--espacio-tres); } }
</style>

<main class="notif-pref">
    <div class="notif-pref__inner">
    <?php if (!$valido || $prefs === null): ?>
        <h1 class="notif-pref__titulo">Enlace no válido</h1>
        <p class="notif-pref__texto">Este enlace no es válido o está incompleto. Si quieres cambiar tus avisos por email, escríbenos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a> y lo gestionamos.</p>

    <?php else: ?>
        <h1 class="notif-pref__titulo">Tus avisos por email</h1>
        <p class="notif-pref__texto">Elige qué avisos quieres recibir en <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>. Los cambios se guardan al instante.</p>

        <ul class="notif-pref__lista">
        <?php foreach ($categorias as $clave => [$nombre, $desc]): ?>
            <li class="notif-pref__item">
                <label class="notif-pref__fila">
                    <span class="notif-pref__info">
                        <span class="notif-pref__nombre"><?= htmlspecialchars($nombre) ?></span>
                        <span class="notif-pref__desc"><?= htmlspecialchars($desc) ?></span>
                    </span>
                    <input type="checkbox" class="notif-pref__interruptor" data-categoria="<?= $clave ?>" <?= $prefs[$clave] ? 'checked' : '' ?>>
                    <span class="notif-pref__pista" aria-hidden="true"></span>
                </label>
            </li>
        <?php endforeach; ?>
        </ul>

        <p class="notif-pref__estado" id="notif-pref-estado" role="status" aria-live="polite"></p>

        <p class="notif-pref__texto"><button type="button" class="notif-pref__todo" id="notif-pref-todo">Dejar de recibir todo</button></p>
    <?php endif; ?>
    </div>
</main>

<?php if ($valido && $prefs !== null): ?>
<script>
(function () {
    var e = <?= json_encode((string)($_GET['e'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var t = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var interruptores = document.querySelectorAll('.notif-pref__interruptor');
    var estado = document.getElementById('notif-pref-estado');
    var botonTodo = document.getElementById('notif-pref-todo');
    var cola = Promise.resolve();

    function enviar(datos) {
        var f = new FormData();
        f.append('e', e); f.append('t', t);
        Object.keys(datos).forEach(function (k) { f.append(k, datos[k]); });
        return fetch(location.pathname, { method: 'POST', body: f, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }
    function pintar(p) {
        interruptores.forEach(function (i) { i.checked = !!p[i.dataset.categoria]; });
    }
    function mensaje(texto, anterior) {
        estado.textContent = texto + ' ';
        if (anterior) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'notif-pref__deshacer'; b.textContent = 'Deshacer';
            b.addEventListener('click', function () { deshacer(anterior); });
            estado.appendChild(b);
        }
    }
    function error() { mensaje('No se ha podido guardar. Inténtalo de nuevo.', null); }

    function aplicar(datos, texto) {
        // En cola: un cambio no empieza hasta que acaba el anterior
        cola = cola.then(function () { return enviar(datos); }).then(function (r) {
            if (!r.ok) { error(); return; }
            pintar(r.ahora);
            mensaje(texto, r.antes);
        }).catch(error);
    }
    function deshacer(a) {
        var d = { accion: 'restaurar', todo_apagado: a.todo_apagado ? '1' : '0' };
        ['resenas', 'leads', 'informes'].forEach(function (k) { d['c_' + k] = a[k] ? '1' : '0'; });
        cola = cola.then(function () { return enviar(d); }).then(function (r) {
            if (!r.ok) { error(); return; }
            pintar(r.ahora);
            mensaje('Cambio deshecho.', null);
        }).catch(error);
    }

    interruptores.forEach(function (i) {
        i.addEventListener('change', function () {
            aplicar({ accion: 'categoria', categoria: i.dataset.categoria, recibir: i.checked ? '1' : '0' }, 'Guardado.');
        });
    });
    botonTodo.addEventListener('click', function () {
        aplicar({ accion: 'todo' }, 'Ya no te enviaremos ningún aviso. Puedes volver a activarlos aquí cuando quieras.');
    });
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
