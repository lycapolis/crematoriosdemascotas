<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MAQUETA DE NOTIFICACIONES — herramienta de trabajo
 * ═══════════════════════════════════════════════════════════
 *
 * Muestra cómo se ven TODAS las notificaciones por email y sus variantes, con datos de
 * ejemplo (y, si se quiere, el nombre de un negocio real). El preview cambia al instante
 * al tocar cualquier control. Cada bloque puede enviarse a cualquier email (por defecto,
 * el del admin con sesión) para comprobarlo en una bandeja real.
 *
 * No envía nada a negocios ni toca leads reales. Lógica en includes/notificaciones/maquetas.php,
 * AJAX en admin/notif-preview-ajax.php.
 * ═══════════════════════════════════════════════════════════
 */

require_once '../includes/config.php';
require_once '../includes/conexion_db.php';
require_once '../includes/funciones.php';
require_once 'auth.php';

requierePermiso('solicitudes');

$titulo_pagina = 'Maqueta de notificaciones';
$pdo   = obtenerConexion();
$admin = obtenerAdminActual();
$csrf  = generarTokenCSRF();

$planes = $pdo->query("SELECT id, nombre FROM tiers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$negocios = $pdo->query("SELECT id, nombre, tier, ciudad FROM crematorios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

/** Fila «Enviar esta maqueta a…» común a todos los bloques (por defecto, el email del admin). */
function filaEnvio(string $emailPorDefecto): void
{
    ?>
    <div class="notif-bloque__fila">
      <div class="field" style="margin-bottom:0;">
        <label class="field__label">Enviar esta maqueta a</label>
        <input type="email" data-campo="email" class="field__input" value="<?= htmlspecialchars($emailPorDefecto) ?>" placeholder="tu@email.com">
      </div>
      <div class="field notif-bloque__botones" style="margin-bottom:0;"><label class="field__label">&nbsp;</label><button type="button" class="boton uno" data-enviar><i data-lucide="send" class="icono"></i> Enviar</button></div>
    </div>
    <div class="notif-bloque__msg" aria-live="polite"></div>
    <?php
}

include 'header.php';
?>
<main class="admin-main">
  <style>
    /* Maqueta de notificaciones: formularios a la izquierda, preview fijo a la derecha */
    /* La columna derecha mide exactamente lo que mide el email escalado; la izquierda se lleva todo el resto */
    .notif-test__grid { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: var(--espacio-cuatro); align-items: start; }
    /* Se pega 30 px por debajo del header del admin (su altura real la mide el JS en --notif-top) */
    .notif-test__preview { position: sticky; top: calc(var(--notif-top, 56px) + 30px); min-width: 340px; }
    .notif-card { background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); padding: var(--espacio-cuatro); }
    .notif-card__titulo { margin: 0 0 var(--espacio-tres) 0; font-size: 1.1rem; font-weight: 700; color: var(--admin-tinta-fuerte); }
    .notif-card__nota { margin: 0 0 var(--espacio-tres) 0; font-size: .9rem; color: var(--admin-tinta-tenue); }
    .notif-bloque { margin-bottom: var(--espacio-cuatro); cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .notif-bloque:hover { border-color: var(--admin-linea-fuerte); }
    .notif-bloque.is-activo { border-color: #B8704F; box-shadow: var(--admin-sombra-foco); }
    .notif-bloque__fila { display: flex; gap: var(--espacio-tres); align-items: flex-start; flex-wrap: wrap; }
    .notif-bloque__fila > .field { flex: 1 1 180px; min-width: 0; margin-bottom: var(--espacio-tres); }
    .notif-bloque__fila .field__hint { display: block; margin-top: .35rem; }
    .notif-bloque__botones { flex: 0 0 auto !important; }
    .notif-bloque__botonera { display: flex; gap: .5rem; flex-wrap: nowrap; }
    .notif-bloque__botonera .boton { white-space: nowrap; }
    .notif-bloque__envio { border-top: 1px solid var(--admin-linea); padding-top: var(--espacio-tres); margin-top: .5rem; }
    .notif-bloque__msg { margin-top: .5rem; font-size: .9rem; }
    .notif-bloque__msg.is-ok { color: #15803d; }
    .notif-bloque__msg.is-error { color: #b91c1c; }
    /* width:0 + min-width:100% → ocupan el ancho de la columna pero NO cuentan para calcularlo
       (si no, un texto largo ensancharía la columna del email y encogería la de los formularios) */
    .notif-preview__asunto { width: 0; min-width: 100%; margin: 0 0 .5rem 0; font-size: .85rem; color: var(--admin-tinta-tenue); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* El email se escala para caber entero (sin scrollbars ni horizontal ni vertical) */
    /* Si un email es más alto que la pantalla, se desplaza DENTRO del marco con la rueda (sin barra visible);
       el resto de la página sigue haciendo scroll normal fuera de la columna. */
    .notif-test__marco { overflow-x: hidden; overflow-y: auto; scrollbar-width: none; overscroll-behavior: contain; border-radius: var(--admin-r-md); transition: opacity .15s; cursor: default; }
    .notif-test__marco::-webkit-scrollbar { display: none; }
    .notif-test__cuerpo { position: relative; }
    .notif-test__marco.is-cargando { opacity: .45; }
    .notif-test__iframe { position: absolute; top: 0; left: 0; display: block; border: 0; background: #FFFBF7; transform-origin: 0 0; }
    .notif-bloque__avisos { margin-bottom: var(--espacio-tres); }
    .notif-bloque__avisos .admin-banner { overflow-wrap: anywhere; }
    @media (max-width: 1100px) { .notif-test__grid { grid-template-columns: 1fr; } .notif-test__preview { position: static; } }
  </style>

  <div style="max-width:1600px;margin:0 auto;padding:var(--espacio-cuatro);">
    <div class="notif-test__grid">
    <div class="notif-test__col">

      <h1 style="margin:0 0 .5rem 0;">Maqueta de notificaciones</h1>
      <p class="notif-card__nota" style="margin-bottom:var(--espacio-cuatro);">
      Así se ven los emails y sus variantes, con datos de ejemplo. Toca cualquier control y el preview cambia al instante.
      Esta página nunca envía nada a negocios: solo al email que escribas en cada bloque.
      </p>

      <!-- 1) LEAD AL NEGOCIO -->
      <section class="notif-card notif-bloque" data-bloque="lead" data-tipo="lead_negocio">
        <h2 class="notif-card__titulo">1) Lead nuevo al negocio</h2>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="lead_plan">Plan del negocio</label>
            <select id="lead_plan" data-campo="plan" class="field__select">
              <?php foreach ($planes as $pl): ?>
              <option value="<?= htmlspecialchars($pl['id']) ?>"><?= htmlspecialchars($pl['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field__hint">Cambia la variante del email (Gratis/Básico, Estándar, Premium).</span>
          </div>
          <div class="field">
            <label class="field__label" for="lead_neg">Negocio de ejemplo</label>
            <select id="lead_neg" data-campo="crem_id" data-negocios="todos" class="field__select"></select>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="todos"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
        </div>
        <div class="notif-bloque__envio">
          <?php filaEnvio($admin['email'] ?? ''); ?>
        </div>
      </section>

      <!-- 2) TEASER REGIONAL -->
      <section class="notif-card notif-bloque" data-bloque="teaser" data-tipo="teaser">
        <h2 class="notif-card__titulo">2) Teaser regional (métricas de la provincia)</h2>
        <p class="notif-card__nota">
          Igual para todos los negocios de una provincia. Sin elegir negocio, se ve con cifras de muestra.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="teaser_plan">Plan</label>
            <select id="teaser_plan" data-campo="plan" data-filtra="teaser_neg" class="field__select">
              <option value="">— Sin elegir —</option>
              <?php foreach ($planes as $pl): ?>
              <option value="<?= htmlspecialchars($pl['id']) ?>"><?= htmlspecialchars($pl['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="teaser_neg">Negocio</label>
            <select id="teaser_neg" data-campo="crem_id" data-negocios="plan" class="field__select"></select>
            <span class="field__hint" id="teaser_neg_hint"></span>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="plan" data-plan-select="teaser_plan"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
        </div>
        <div class="notif-bloque__envio">
          <?php filaEnvio($admin['email'] ?? ''); ?>
        </div>
      </section>

      <!-- 3) RESEÑA AL AUTOR -->
      <section class="notif-card notif-bloque" data-bloque="resena" data-tipo="resena_recibida">
        <h2 class="notif-card__titulo">3) Emails al autor de una reseña</h2>
        <p class="notif-card__nota">
          El enlace «Dejar de recibir estas notificaciones» es real para el email al que lo envíes: si lo pulsas y confirmas,
          ese email queda dado de baja (se reactiva al enviar una reseña con él).
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="resena_tipo">Email</label>
            <select id="resena_tipo" data-campo="tipo" class="field__select">
              <option value="resena_recibida">1) Hemos recibido tu reseña</option>
              <option value="resena_aprobada">2) Tu reseña ya es pública</option>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="resena_neg">Negocio de ejemplo</label>
            <select id="resena_neg" data-campo="crem_id" data-negocios="todos" class="field__select"></select>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="todos"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
        </div>
        <div class="notif-bloque__envio">
          <?php filaEnvio($admin['email'] ?? ''); ?>
        </div>
      </section>

      <!-- 4) RESEÑA POSITIVA → NEGOCIO -->
      <section class="notif-card notif-bloque" data-bloque="resena_negocio" data-tipo="resena_negocio">
        <h2 class="notif-card__titulo">4) Reseña positiva: aviso al negocio</h2>
        <p class="notif-card__nota">
          Se envía al aprobar una reseña positiva. Es solo una notificación (no pide nada), con un botón para ver la reseña
          y otro por si el negocio quiere responderla.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="resneg_neg">Negocio de ejemplo</label>
            <select id="resneg_neg" data-campo="crem_id" data-negocios="todos" class="field__select"></select>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="todos"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
        </div>
        <div class="notif-bloque__envio">
          <?php filaEnvio($admin['email'] ?? ''); ?>
        </div>
      </section>

      <!-- 5) SOLICITUD DE ALTA → SOLICITANTE -->
      <section class="notif-card notif-bloque" data-bloque="solicitud" data-tipo="solicitud_recibida">
        <h2 class="notif-card__titulo">5) Solicitud de alta de negocio (al solicitante)</h2>
        <p class="notif-card__nota">
          Los tres momentos del alta: recibida (al enviar el formulario), aprobada y rechazada (al moderar en Solicitudes).
          El motivo del rechazo lo escribe el admin y viaja en el email.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="sol_tipo">Email</label>
            <select id="sol_tipo" data-campo="tipo" class="field__select">
              <option value="solicitud_recibida">1) Hemos recibido tu solicitud</option>
              <option value="solicitud_aprobada">2) Tu negocio ya está en el directorio</option>
              <option value="solicitud_rechazada">3) Rechazo, con motivo</option>
              <option value="solicitud_rechazada_sin_motivo">4) Rechazo, sin motivo</option>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="sol_neg">Negocio de ejemplo</label>
            <select id="sol_neg" data-campo="crem_id" data-negocios="todos" class="field__select"></select>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="todos"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
        </div>
        <div class="notif-bloque__envio">
          <?php filaEnvio($admin['email'] ?? ''); ?>
        </div>
      </section>

      <section class="notif-card" style="font-size:.9rem; color:var(--admin-tinta-suave);">
        <strong>Estado del envío SMTP:</strong>
        <ul style="margin-top:.5rem;">
          <li>SMTP_HOST: <code><?= defined('SMTP_HOST') && SMTP_HOST ? htmlspecialchars(SMTP_HOST) : '<em>vacío → fallback a mail() (en local no envía)</em>' ?></code></li>
          <li>SMTP_USER: <code><?= defined('SMTP_USER') && SMTP_USER ? htmlspecialchars(SMTP_USER) : '<em>vacío</em>' ?></code></li>
          <li>SMTP_FROM: <code><?= defined('SMTP_FROM_EMAIL') ? htmlspecialchars(SMTP_FROM_EMAIL) : '?' ?></code></li>
          <li>PHPMailer instalado: <?= file_exists(__DIR__ . '/../assets/librerias/phpmailer/PHPMailer.php') ? '✓' : '✗' ?></li>
        </ul>
      </section>

    </div><!-- /.notif-test__col -->

    <aside class="notif-test__preview">
      <p class="notif-preview__asunto" id="preview-titulo">Preview</p>
      <div class="notif-test__marco" id="preview-marco">
        <div class="notif-test__cuerpo" id="preview-cuerpo">
          <iframe class="notif-test__iframe" id="preview-iframe" title="Preview del email" scrolling="no"></iframe>
        </div>
      </div>
    </aside>

    </div><!-- /.notif-test__grid -->
  </div>
</main>

<script>
(function () {
  var CSRF     = <?= json_encode($csrf) ?>;
  var NEGOCIOS = <?= json_encode($negocios, JSON_UNESCAPED_UNICODE) ?>;
  var URL_AJAX = 'notif-preview-ajax.php';
  var activo   = null;      // bloque cuyo email se ve a la derecha
  var secuencia = 0;        // descarta respuestas viejas si el usuario cambia rápido
  var temporizador = null;

  function llamar(datos) {
    datos.csrf_token = CSRF;
    return fetch(URL_AJAX, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(datos)
    }).then(function (r) { return r.json(); });
  }

  function estado(bloque) {
    var e = { tipo: bloque.getAttribute('data-tipo'), plan: '', crem_id: 0,
              email: (bloque.querySelector('[data-campo="email"]') || {}).value || '' };
    bloque.querySelectorAll('[data-campo]').forEach(function (c) {
      var k = c.getAttribute('data-campo');
      if (k === 'tipo') e.tipo = c.value; else if (k === 'crem_id') e.crem_id = parseInt(c.value || '0', 10); else if (k === 'plan') e.plan = c.value;
    });
    return e;
  }

  // Los avisos van DENTRO del bloque activo (columna izquierda), no bajo el email:
  // así no le quitan ancho ni alto a la columna del preview.
  function pintarAvisos(avisos) {
    document.querySelectorAll('.notif-bloque__avisos').forEach(function (c) { c.innerHTML = ''; });
    if (!activo) return;
    var cont = activo.querySelector('.notif-bloque__avisos');
    if (!cont) {
      cont = document.createElement('div');
      cont.className = 'notif-bloque__avisos';
      activo.querySelector('.notif-bloque__envio').parentNode.insertBefore(cont, activo.querySelector('.notif-bloque__envio'));
    }
    (avisos || []).forEach(function (a) {
      var d = document.createElement('div');
      d.className = 'admin-banner';
      d.style.marginBottom = '.5rem';
      d.style.fontSize = '.85rem';
      d.textContent = 'ℹ ' + a;
      cont.appendChild(d);
    });
  }

  // Escala el email para que quepa en el ancho de la columna; sin scrollbars visibles (si es más alto que la pantalla, se desplaza con la rueda dentro del marco).
  var ANCHO_EMAIL = 600;   // ancho real de la tarjeta del email (sin el margen crema que la rodea)
  var ALTO_REFERENCIA = 840; // altura aprox. del email más alto (lead al negocio): fija el tamaño común de la tarjeta
  function ajustarPreview() {
    var iframe = document.getElementById('preview-iframe');
    var marco  = document.getElementById('preview-marco');
    var doc = iframe.contentDocument;
    if (!doc || !doc.documentElement || !doc.body) return;
    doc.documentElement.style.overflow = 'hidden';
    // Quita el margen crema exterior del email: aquí solo interesa la tarjeta
    var exterior = doc.body.querySelector('table');
    if (exterior) exterior.style.padding = '0';
    iframe.style.width = ANCHO_EMAIL + 'px';
    iframe.style.height = '10px';
    var alto = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight);
    iframe.style.height = alto + 'px';
    // En pantallas anchas la columna mide lo que mida el email (tope: 40 % de la ventana);
    // en una sola columna (<1100 px) se ajusta al ancho disponible.
    var apilado = window.matchMedia('(max-width: 1100px)').matches;
    var anchoDisponible = apilado ? marco.parentElement.clientWidth : Math.min(ANCHO_EMAIL, window.innerWidth * 0.4);
    var cabecera = document.querySelector('.admin-header');
    var altoCabecera = cabecera ? cabecera.offsetHeight : 56;
    document.documentElement.style.setProperty('--notif-top', altoCabecera + 'px');
    // cabecera del admin + 30 px de aire + línea del asunto + margen inferior
    var altoDisponible = window.innerHeight - altoCabecera - 30 - 60;
    // Escala ÚNICA para todos los emails (calculada con la altura del de referencia): la tarjeta
    // mide siempre lo mismo. Un email más alto que la pantalla NO se reduce: se desplaza dentro del marco.
    var escala = Math.max(0.4, Math.min(1, anchoDisponible / ANCHO_EMAIL, altoDisponible / ALTO_REFERENCIA));
    var anchoEscalado = Math.floor(ANCHO_EMAIL * escala);
    var altoEscalado  = Math.floor(alto * escala);
    document.querySelector('.notif-test__preview').style.width = apilado ? '' : anchoEscalado + 'px';
    iframe.style.transform = 'scale(' + escala + ')';
    var cuerpo = document.getElementById('preview-cuerpo');
    cuerpo.style.width  = anchoEscalado + 'px';
    cuerpo.style.height = altoEscalado + 'px';
    marco.style.width     = anchoEscalado + 'px';
    marco.style.maxHeight = altoDisponible + 'px';
    marco.scrollTop = 0;
  }
  document.getElementById('preview-iframe').addEventListener('load', ajustarPreview);
  window.addEventListener('resize', ajustarPreview);

  function previsualizar(bloque) {
    activo = bloque;
    document.querySelectorAll('.notif-bloque').forEach(function (b) { b.classList.toggle('is-activo', b === bloque); });
    var miSecuencia = ++secuencia;
    var iframe = document.getElementById('preview-iframe');
    var marco  = document.getElementById('preview-marco');
    marco.classList.add('is-cargando');
    var e = estado(bloque); e.accion = 'preview';
    llamar(e).then(function (r) {
      if (miSecuencia !== secuencia) return;
      marco.classList.remove('is-cargando');
      if (!r.ok) { pintarAvisos([r.error || 'No se pudo generar el preview.']); return; }
      document.getElementById('preview-titulo').textContent = 'Preview · ' + r.asunto;
      pintarAvisos(r.avisos);
      iframe.srcdoc = r.html;
    }).catch(function () {
      if (miSecuencia !== secuencia) return;
      marco.classList.remove('is-cargando');
      pintarAvisos(['Error de red al generar el preview.']);
    });
  }

  function programar(bloque) {
    clearTimeout(temporizador);
    temporizador = setTimeout(function () { previsualizar(bloque); }, 150);
  }

  // ── Selects de negocios ───────────────────────────────────
  function llenarNegocios(select, plan) {
    var previo = select.value;
    select.innerHTML = '';
    var vacio = document.createElement('option');
    vacio.value = '0';
    vacio.textContent = select.getAttribute('data-negocios') === 'plan'
      ? '— Ninguno (cifras de muestra) —' : '— Crematorio Ejemplo (ficticio) —';
    select.appendChild(vacio);
    var n = 0;
    NEGOCIOS.forEach(function (x) {
      if (plan && x.tier !== plan) return;
      var o = document.createElement('option');
      o.value = x.id; o.textContent = x.nombre + (x.ciudad ? ' — ' + x.ciudad : '');
      select.appendChild(o); n++;
    });
    select.value = previo && select.querySelector('option[value="' + previo + '"]') ? previo : '0';
    return n;
  }

  document.querySelectorAll('select[data-negocios]').forEach(function (s) {
    var modo = s.getAttribute('data-negocios');
    var n = llenarNegocios(s, '');
    if (modo === 'plan') document.getElementById('teaser_neg_hint').textContent = n + ' negocios en total.';
  });

  // El teaser filtra sus negocios según el plan elegido
  var planTeaser = document.getElementById('teaser_plan');
  planTeaser.addEventListener('change', function () {
    var s = document.getElementById(planTeaser.getAttribute('data-filtra'));
    s.value = '0';
    var n = llenarNegocios(s, planTeaser.value);
    document.getElementById('teaser_neg_hint').textContent = planTeaser.value
      ? (n ? n + ' negocios en este plan.' : 'Ningún negocio está en este plan todavía: se verá con cifras de muestra.')
      : n + ' negocios en total.';
  });

  // ── Eventos por bloque ────────────────────────────────────
  document.querySelectorAll('.notif-bloque').forEach(function (bloque) {
    bloque.addEventListener('click', function () { if (activo !== bloque) previsualizar(bloque); });
    bloque.querySelectorAll('select[data-campo]').forEach(function (c) {
      c.addEventListener('change', function () { programar(bloque); });
    });
    var email = bloque.querySelector('[data-campo="email"]');
    if (email) email.addEventListener('change', function () { if (activo === bloque) programar(bloque); });

    var azar = bloque.querySelector('[data-azar]');
    if (azar) azar.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var planSel = azar.getAttribute('data-plan-select');
      var plan = planSel ? document.getElementById(planSel).value : '';
      llamar({ accion: 'azar', plan: plan }).then(function (r) {
        var s = bloque.querySelector('select[data-negocios]');
        if (!r.ok) { mostrarMensaje(bloque, r.error, false); return; }
        if (!s.querySelector('option[value="' + r.negocio.id + '"]')) llenarNegocios(s, '');
        s.value = r.negocio.id;
        previsualizar(bloque);
      });
    });

    var ver = bloque.querySelector('[data-ver]');
    if (ver) ver.addEventListener('click', function (ev) { ev.stopPropagation(); previsualizar(bloque); });

    var enviar = bloque.querySelector('[data-enviar]');
    if (enviar) enviar.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var e = estado(bloque); e.accion = 'enviar';
      enviar.disabled = true;
      llamar(e).then(function (r) {
        enviar.disabled = false;
        mostrarMensaje(bloque, r.ok ? r.mensaje : r.error, !!r.ok);
      }).catch(function () { enviar.disabled = false; mostrarMensaje(bloque, 'Error de red.', false); });
    });
  });

  function mostrarMensaje(bloque, texto, ok) {
    var m = bloque.querySelector('.notif-bloque__msg');
    m.textContent = (ok ? '✓ ' : '✗ ') + texto;
    m.className = 'notif-bloque__msg ' + (ok ? 'is-ok' : 'is-error');
  }

  // Siempre hay un ejemplo a la vista al abrir la página
  previsualizar(document.querySelector('.notif-bloque'));
})();
</script>
<?php include 'footer.php'; ?>
