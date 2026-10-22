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
require_once '../includes/notificaciones/maquetas.php';

requierePermiso('solicitudes');

$titulo_pagina = 'Maqueta de notificaciones';
$pdo   = obtenerConexion();
$admin = obtenerAdminActual();
$csrf  = generarTokenCSRF();

$planes = $pdo->query("SELECT id, nombre FROM tiers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$negocios = $pdo->query("SELECT id, nombre, tier, ciudad FROM crematorios WHERE activo = 1 OR estado = 'demo' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

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

/** Abre un bloque colapsable. Se cierra con cerrarBloque(). */
function abrirBloque(string $id, string $tipo, string $titulo, string $resumen = ''): void
{
    ?>
      <section class="notif-card notif-bloque is-colapsado" data-bloque="<?= htmlspecialchars($id) ?>" data-tipo="<?= htmlspecialchars($tipo) ?>">
        <button type="button" class="notif-bloque__cabecera" aria-expanded="false">
          <i data-lucide="chevron-right" class="icono notif-bloque__flecha"></i>
          <span class="notif-bloque__titulo"><?= htmlspecialchars($titulo) ?></span>
          <?php if ($resumen !== ''): ?><span class="notif-bloque__resumen"><?= htmlspecialchars($resumen) ?></span><?php endif; ?>
        </button>
        <div class="notif-bloque__cuerpo">
    <?php
}

function cerrarBloque(): void
{
    ?>
        </div>
      </section>
    <?php
}

/** Selector de negocio de ejemplo + botones Al azar / Ver preview. */
function filaNegocio(string $selectId): void
{
    ?>
          <div class="field">
            <label class="field__label" for="<?= $selectId ?>">Negocio de ejemplo</label>
            <select id="<?= $selectId ?>" data-campo="crem_id" data-negocios="todos" class="field__select"></select>
          </div>
          <div class="field notif-bloque__botones"><label class="field__label">&nbsp;</label><div class="notif-bloque__botonera"><button type="button" class="boton dos" data-azar="todos"><i data-lucide="shuffle" class="icono"></i> Al azar</button><button type="button" class="boton dos" data-ver><i data-lucide="eye" class="icono"></i> Ver preview</button></div></div>
    <?php
}

function grupo(string $titulo, string $nota = ''): void
{
    ?>
    <div class="notif-grupo">
      <h2 class="notif-grupo__titulo"><?= htmlspecialchars($titulo) ?></h2>
      <?php if ($nota !== ''): ?><p class="notif-grupo__nota"><?= htmlspecialchars($nota) ?></p><?php endif; ?>
    </div>
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
    /* Bloques colapsables y grupos */
    .notif-grupo { margin: var(--espacio-cuatro) 0 var(--espacio-dos) 0; }
    .notif-grupo__titulo { margin: 0; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--admin-tinta-tenue); }
    .notif-grupo__nota { margin: .25rem 0 0 0; font-size: .85rem; color: var(--admin-tinta-tenue); }
    .notif-bloque { padding: 0; }
    .notif-bloque__cabecera { display: flex; align-items: center; gap: .6rem; width: 100%; padding: var(--espacio-tres) var(--espacio-cuatro); background: none; border: 0; text-align: left; cursor: pointer; font: inherit; color: inherit; }
    .notif-bloque__flecha { width: 18px; height: 18px; flex: 0 0 auto; transition: transform .15s; color: var(--admin-tinta-tenue); }
    .notif-bloque:not(.is-colapsado) .notif-bloque__flecha { transform: rotate(90deg); }
    .notif-bloque__titulo { font-size: 1.05rem; font-weight: 700; color: var(--admin-tinta-fuerte); }
    .notif-bloque__resumen { margin-left: auto; font-size: .8rem; color: var(--admin-tinta-tenue); text-align: right; }
    .notif-bloque__cuerpo { padding: 0 var(--espacio-cuatro) var(--espacio-cuatro) var(--espacio-cuatro); }
    .notif-bloque.is-colapsado .notif-bloque__cuerpo { display: none; }
    .notif-herramientas { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; margin-bottom: var(--espacio-dos); }
    .notif-formato { display: inline-flex; border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); overflow: hidden; margin: 0 0 .5rem 0; }
    .notif-formato button { padding: .35rem .8rem; background: var(--admin-superficie); border: 0; font: inherit; font-size: .82rem; cursor: pointer; color: var(--admin-tinta-tenue); }
    .notif-formato button + button { border-left: 1px solid var(--admin-linea); }
    .notif-formato button.is-activo { background: #B8704F; color: #fff; }
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

      <div class="notif-herramientas">
        <button type="button" class="boton dos" id="notif-expandir"><i data-lucide="chevrons-down" class="icono"></i> Expandir todo</button>
        <button type="button" class="boton dos" id="notif-colapsar"><i data-lucide="chevrons-up" class="icono"></i> Colapsar todo</button>
      </div>

      <?php grupo('Al negocio', 'Emails que reciben los negocios de los directorios.'); ?>

      <?php abrirBloque('lead', 'lead_negocio', 'Lead nuevo al negocio', 'según el plan'); ?>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="lead_plan">Plan del negocio</label>
            <select id="lead_plan" data-campo="plan" class="field__select">
              <?php foreach ($planes as $pl): ?>
              <option value="<?= htmlspecialchars($pl['id']) ?>"><?= htmlspecialchars($pl['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field__hint">Cambia la variante del email (Gratis con cupo, o datos completos en los planes de pago).</span>
          </div>
          <div class="field">
            <label class="field__label" for="lead_entregas">Entregas usadas (simulación)</label>
            <select id="lead_entregas" data-campo="entregas" class="field__select">
              <?php for ($i = 0; $i <= 5; $i++): ?>
              <option value="<?= $i ?>"><?= $i ?> de 5<?= $i === 5 ? ' — agotado' : '' ?></option>
              <?php endfor; ?>
            </select>
            <span class="field__hint">Solo afecta a planes con cupo (Gratis). No consume cupo real.</span>
          </div>
          <?php filaNegocio('lead_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('teaser', 'teaser', 'Teaser regional (métricas de la provincia)', 'igual para toda la provincia'); ?>
        <p class="notif-card__nota">Igual para todos los negocios de una provincia. Sin elegir negocio, se ve con cifras de muestra.</p>
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
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('resena_negocio', 'resena_negocio', 'Reseña positiva: aviso al negocio', '1 email'); ?>
        <p class="notif-card__nota">
          Se envía al aprobar una reseña positiva. Es solo una notificación (no pide nada), con un botón para ver la reseña
          y otro por si el negocio quiere responderla (abre responder-resena.php: elige un negocio con reseñas propias aprobadas para probarlo).
        </p>
        <div class="notif-bloque__fila"><?php filaNegocio('resneg_neg'); ?></div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('respuesta_aprobada', 'respuesta_aprobada', 'Respuesta del negocio publicada: aviso al negocio', '1 email'); ?>
        <p class="notif-card__nota">
          Se envía cuando apruebas la respuesta que el negocio escribió a una reseña. Confirma que ya es pública.
        </p>
        <div class="notif-bloque__fila"><?php filaNegocio('respapr_neg'); ?></div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php grupo('Al usuario', 'Emails transaccionales a quien deja una reseña o solicita el alta.'); ?>

      <?php abrirBloque('resena', 'resena_recibida', 'Emails al autor de una reseña', '2 emails'); ?>
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
          <?php filaNegocio('resena_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('lead_confirmacion', 'lead_confirmacion_ficha', 'Confirmación al visitante que deja sus datos', '2 variantes'); ?>
        <p class="notif-card__nota">
          Se envía al visitante al completar el formulario de lead, como máximo un email por ficha y día para la misma dirección.
          Sin botones ni teléfono: el portal es intermediario.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="leadconf_tipo">Email</label>
            <select id="leadconf_tipo" data-campo="tipo" class="field__select">
              <option value="lead_confirmacion_ficha">1) Desde una ficha (nombra el negocio)</option>
              <option value="lead_confirmacion_burbuja">2) Desde la burbuja (sin ficha)</option>
            </select>
          </div>
          <?php filaNegocio('leadconf_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('solicitud', 'solicitud_recibida', 'Solicitud de alta de negocio (al solicitante)', '4 variantes'); ?>
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
          <?php filaNegocio('sol_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php abrirBloque('actualizacion', 'actualizacion_recibida', 'Actualización de ficha por el negocio (al negocio)', '4 variantes'); ?>
        <p class="notif-card__nota">
          Los dos momentos del formulario de verificar-ficha.php: recibida (al enviar los datos) y revisada (al terminar la revisión en Actualizaciones),
          con tres resultados posibles: todo aprobado, parte aprobada y nada aprobado.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="act_tipo">Email</label>
            <select id="act_tipo" data-campo="tipo" class="field__select">
              <option value="actualizacion_recibida">1) Hemos recibido tus datos</option>
              <option value="actualizacion_revisada">2) Tu ficha está revisada (todo aprobado)</option>
              <option value="actualizacion_revisada_parcial">3) Revisada, con algo sin aprobar</option>
              <option value="actualizacion_revisada_nada">4) Revisada, nada aprobado</option>
            </select>
          </div>
          <?php filaNegocio('act_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php grupo('Al equipo', 'Avisos internos a ' . (defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@') . ' por cada conversión del sitio.'); ?>

      <?php abrirBloque('equipo', 'equipo_resena', 'Avisos al equipo por conversión', '9 avisos'); ?>
        <p class="notif-card__nota">
          Uno por cada conversión: reseña, contacto, «Promociona tu crematorio», alta de negocio y lead (de una ficha o de la burbuja).
          Llevan el email de quien convierte en Reply-To, así que se le responde directamente desde la bandeja.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="equipo_tipo">Conversión</label>
            <select id="equipo_tipo" data-campo="tipo" class="field__select">
              <option value="equipo_resena">Nueva reseña</option>
              <option value="equipo_respuesta">Respuesta de un negocio a una reseña</option>
              <option value="equipo_llamada">Un negocio pide que le llamemos (verificación)</option>
              <option value="equipo_actualizacion">Un negocio envía datos para actualizar su ficha</option>
              <option value="equipo_contacto">Mensaje de contacto</option>
              <option value="equipo_promociona">Lead comercial «Promociona tu crematorio»</option>
              <option value="equipo_registro">Solicitud de alta de negocio</option>
              <option value="equipo_lead_ficha">Lead desde la ficha de un negocio</option>
              <option value="equipo_lead_burbuja">Lead desde la burbuja (sin ficha)</option>
            </select>
          </div>
          <?php filaNegocio('equipo_neg'); ?>
        </div>
        <div class="notif-bloque__envio"><?php filaEnvio($admin['email'] ?? ''); ?></div>
      <?php cerrarBloque(); ?>

      <?php grupo('Webhooks', 'Lo que reciben Make, n8n u otros destinos. No es un email: el preview es el JSON.'); ?>

      <?php abrirBloque('webhook', 'webhook:resena.recibida', 'Payload de los webhooks', count(notifMaquetaTiposWebhook()) . ' eventos'); ?>
        <p class="notif-card__nota">
          El JSON que recibe cada destino. Los eventos «heredados» mantienen el formato que Make ya conoce y suman las variables extra
          activas; los demás usan el sobre normalizado. Destinos, interruptores y variables se configuran en
          <a href="webhooks.php">Webhooks</a>.
        </p>
        <div class="notif-bloque__fila">
          <div class="field">
            <label class="field__label" for="webhook_tipo">Evento</label>
            <select id="webhook_tipo" data-campo="tipo" class="field__select">
              <?php foreach (notifMaquetaTiposWebhook() as $k => $etq): ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars(preg_replace('/^Webhook: /', '', $etq)) ?> — <?= htmlspecialchars(substr($k, 8)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php filaNegocio('webhook_neg'); ?>
        </div>
        <div class="notif-bloque__envio">
          <div class="notif-bloque__msg"></div>
          <a href="webhooks.php" class="boton dos"><i data-lucide="webhook" class="icono"></i> Configurar webhooks y enviar una prueba</a>
        </div>
      <?php cerrarBloque(); ?>

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
      <div class="notif-formato" role="group" aria-label="Formato del email">
        <button type="button" class="is-activo" data-formato="html">Diseñado</button>
        <button type="button" data-formato="texto">Texto plano</button>
      </div>
      <p class="notif-preview__asunto" id="preview-titulo">Preview</p>
      <button type="button" class="boton tres notif-preview__copiar" id="preview-copiar" hidden><i data-lucide="copy" class="icono"></i> <span class="notif-preview__copiar-texto">Copiar JSON</span></button>
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
  var formato = 'html';     // 'html' (diseñado) | 'texto' (texto plano): afecta al preview y al envío

  function llamar(datos) {
    datos.csrf_token = CSRF;
    return fetch(URL_AJAX, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(datos)
    }).then(function (r) { return r.json(); });
  }

  function estado(bloque) {
    var e = { tipo: bloque.getAttribute('data-tipo'), plan: '', crem_id: 0, entregas: 0, formato: formato,
              email: (bloque.querySelector('[data-campo="email"]') || {}).value || '' };
    bloque.querySelectorAll('[data-campo]').forEach(function (c) {
      var k = c.getAttribute('data-campo');
      if (k === 'tipo') e.tipo = c.value; else if (k === 'crem_id') e.crem_id = parseInt(c.value || '0', 10); else if (k === 'plan') e.plan = c.value; else if (k === 'entregas') e.entregas = parseInt(c.value || '0', 10);
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

  // Botón «Copiar JSON» (solo visible cuando el preview es un webhook)
  var jsonActual = null;
  document.getElementById('preview-copiar').addEventListener('click', function () {
    var etq = this.querySelector('.notif-preview__copiar-texto');
    var listo = function (ok) {
      etq.textContent = ok ? 'Copiado ✓' : 'No se pudo copiar';
      setTimeout(function () { etq.textContent = 'Copiar JSON'; }, 2000);
    };
    if (jsonActual === null) return;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(jsonActual).then(function () { listo(true); }, function () { listo(false); });
    } else {
      var ta = document.createElement('textarea'); ta.value = jsonActual; document.body.appendChild(ta); ta.select();
      var ok = false; try { ok = document.execCommand('copy'); } catch (x) {}
      document.body.removeChild(ta); listo(ok);
    }
  });

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
      jsonActual = (typeof r.json === 'string') ? r.json : null;
      document.getElementById('preview-copiar').hidden = (jsonActual === null);
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

  // ── Bloques colapsables (el estado se recuerda en este navegador) ─────
  var CLAVE_ABIERTOS = 'notifMaquetaAbiertos';
  function leerAbiertos() { try { return JSON.parse(localStorage.getItem(CLAVE_ABIERTOS) || 'null'); } catch (e) { return null; } }
  function guardarAbiertos() {
    try {
      var ids = [];
      document.querySelectorAll('.notif-bloque:not(.is-colapsado)').forEach(function (b) { ids.push(b.getAttribute('data-bloque')); });
      localStorage.setItem(CLAVE_ABIERTOS, JSON.stringify(ids));
    } catch (e) { /* sin almacenamiento: no pasa nada */ }
  }
  function fijarColapsado(bloque, colapsado) {
    bloque.classList.toggle('is-colapsado', colapsado);
    bloque.querySelector('.notif-bloque__cabecera').setAttribute('aria-expanded', colapsado ? 'false' : 'true');
  }
  document.querySelectorAll('.notif-bloque__cabecera').forEach(function (cab) {
    cab.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var bloque = cab.closest('.notif-bloque');
      var abrir = bloque.classList.contains('is-colapsado');
      fijarColapsado(bloque, !abrir);
      guardarAbiertos();
      if (abrir) previsualizar(bloque);
    });
  });
  document.getElementById('notif-expandir').addEventListener('click', function () {
    document.querySelectorAll('.notif-bloque').forEach(function (b) { fijarColapsado(b, false); });
    guardarAbiertos();
  });
  document.getElementById('notif-colapsar').addEventListener('click', function () {
    document.querySelectorAll('.notif-bloque').forEach(function (b) { fijarColapsado(b, true); });
    guardarAbiertos();
  });

  // ── Formato: diseñado / texto plano ───────────────────────
  document.querySelectorAll('[data-formato]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      formato = btn.getAttribute('data-formato');
      document.querySelectorAll('[data-formato]').forEach(function (b) { b.classList.toggle('is-activo', b === btn); });
      if (activo) previsualizar(activo);
    });
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

  // Al abrir la página: se despliegan los bloques recordados (o el primero) y se ve un ejemplo
  var recordados = leerAbiertos();
  var bloques = document.querySelectorAll('.notif-bloque');
  bloques.forEach(function (b) {
    if (recordados && recordados.indexOf(b.getAttribute('data-bloque')) !== -1) fijarColapsado(b, false);
  });
  var primero = document.querySelector('.notif-bloque:not(.is-colapsado)');
  if (!primero) { primero = bloques[0]; fijarColapsado(primero, false); }
  previsualizar(primero);
})();
</script>
<?php include 'footer.php'; ?>
