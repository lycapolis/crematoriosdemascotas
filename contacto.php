<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CONTACTO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 * Form de contacto general con honeypot + time-trap.
 * Email + WhatsApp desde constantes (config.php).
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';

$titulo_pagina = 'Contacto - Crematorios de Mascotas';
$pagina_actual = 'contacto';
include 'includes/header.php';

$emailContacto = defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'contacto@crematoriosdemascotas.com';
$whatsapp      = defined('WHATSAPP_SOPORTE') ? WHATSAPP_SOPORTE : '';
$whatsappFmt   = $whatsapp ? '+' . substr($whatsapp, 0, 1) . ' ' . substr($whatsapp, 1, 3) . ' ' . substr($whatsapp, 4, 3) . ' ' . substr($whatsapp, 7) : '';
?>

<style>
    /* ─── Página Contacto ──────────────────────────────────── */
    /* Aire vertical uniforme en la sección (override del global). */
    body .seccion {
        padding-top: 30px;
        padding-bottom: 30px;
    }
    /* El contenedor dentro de la sección hereda el padding de la sección;
       quitamos su padding-bottom propio para que no se duplique. */
    body .seccion > .contenedor {
        padding-bottom: 0;
    }

    .contacto-grid {
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: var(--espacio-cinco);
        align-items: start;
    }
    @media (max-width: 900px) {
        .contacto-grid { grid-template-columns: 1fr; gap: var(--espacio-cuatro); }
    }

    .contacto-info {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }
    .contacto-info__intro h2 {
        font-size: var(--fs-cuatro);
        color: var(--color-dos);
        margin: 0 0 var(--espacio-dos);
        letter-spacing: -.01em;
    }
    .contacto-info__intro p {
        color: var(--color-seis);
        line-height: 1.6;
        margin: 0;
    }

    .contacto-card {
        display: flex;
        align-items: flex-start;
        gap: var(--espacio-tres);
        padding: var(--espacio-cuatro);
        background: #fff;
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-dos);
        transition: border-color .15s ease, transform .15s ease;
        /* Reset de link: las cards que son <a> heredan el azul + underline del browser */
        text-decoration: none;
        color: inherit;
    }
    .contacto-card:hover {
        border-color: var(--color-uno);
    }
    a.contacto-card,
    a.contacto-card:visited,
    a.contacto-card:hover {
        text-decoration: none;
    }
    .contacto-card__icono {
        width: 44px; height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--color-uno-claro, rgba(184, 112, 79, .12));
        color: var(--color-uno);
        flex-shrink: 0;
    }
    .contacto-card__icono .icono { width: 20px; height: 20px; }
    .contacto-card__cuerpo h3 {
        font-size: 1rem;
        color: var(--color-dos);
        margin: 0 0 4px;
        font-weight: 600;
    }
    .contacto-card__cuerpo p,
    .contacto-card__cuerpo a {
        margin: 0;
        color: var(--color-seis);
        font-size: .95rem;
        line-height: 1.5;
        text-decoration: none;
        word-break: break-word;
    }
    .contacto-card__cuerpo a:hover { color: var(--color-uno); }

    .contacto-form {
        background: #fff;
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-dos);
        padding: var(--espacio-cuatro);
    }
    .contacto-form h2 {
        font-size: var(--fs-cuatro);
        color: var(--color-dos);
        margin: 0 0 var(--espacio-cuatro);
        letter-spacing: -.01em;
    }
</style>

<section class="seccion">
    <div class="contenedor">
        <div class="contacto-grid">

            <!-- COLUMNA 1: Información de contacto -->
            <div class="contacto-info">
                <div class="contacto-info__intro">
                    <h2>Información de contacto</h2>
                    <p>Puedes comunicarte con nosotros a través de los siguientes medios o completando el formulario.</p>
                </div>

                <a class="contacto-card" href="mailto:<?= htmlspecialchars($emailContacto) ?>">
                    <span class="contacto-card__icono"><i data-lucide="mail" class="icono"></i></span>
                    <div class="contacto-card__cuerpo">
                        <h3>Email</h3>
                        <p><?= htmlspecialchars($emailContacto) ?></p>
                    </div>
                </a>

                <?php if ($whatsapp): ?>
                <a class="contacto-card" href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener">
                    <span class="contacto-card__icono"><i data-lucide="message-circle" class="icono"></i></span>
                    <div class="contacto-card__cuerpo">
                        <h3>WhatsApp</h3>
                        <p>Respuesta directa al equipo</p>
                    </div>
                </a>
                <?php endif; ?>

                <div class="contacto-card" style="cursor:default;">
                    <span class="contacto-card__icono"><i data-lucide="clock" class="icono"></i></span>
                    <div class="contacto-card__cuerpo">
                        <h3>Horario de atención</h3>
                        <p>Lunes a Viernes · 9:00 – 18:00<br>Sábados · 9:00 – 14:00</p>
                    </div>
                </div>
            </div>

            <!-- COLUMNA 2: Formulario -->
            <div class="contacto-form">
                <h2>Envíanos un mensaje</h2>

                <form id="formulario-contacto" novalidate>

                    <!-- Honeypot anti-bot -->
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off"
                           style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;">
                    <input type="hidden" name="form_render_ts" value="<?= time() ?>">

                    <div class="field">
                        <label class="field__label" for="ct_nombre">Nombre *</label>
                        <input type="text" id="ct_nombre" name="nombre" class="field__input" required placeholder="Tu nombre completo">
                    </div>

                    <div class="field">
                        <label class="field__label" for="ct_email">Email *</label>
                        <input type="email" id="ct_email" name="email" class="field__input" required placeholder="tu@email.com">
                    </div>

                    <div class="field">
                        <label class="field__label" for="ct_telefono">Teléfono *</label>
                        <input type="tel" id="ct_telefono" name="telefono" class="field__input" required placeholder="+34 600 000 000">
                    </div>

                    <div class="field">
                        <label class="field__label" for="ct_asunto">Asunto</label>
                        <select id="ct_asunto" name="asunto" class="field__select field__select--enhanced" data-ts-search="off">
                            <option value="consulta">Consulta general</option>
                            <option value="sugerencia">Sugerencia</option>
                            <option value="problema">Reportar problema</option>
                            <option value="negocio">Información para negocios</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="ct_mensaje">Mensaje *</label>
                        <textarea id="ct_mensaje" name="mensaje" class="field__textarea" required placeholder="Escribe tu mensaje aquí..." rows="6"></textarea>
                    </div>

                    <button type="submit" class="boton uno grande" style="width:100%;">
                        <i data-lucide="send" class="icono"></i>
                        Enviar mensaje
                    </button>
                </form>
            </div>

        </div>
    </div>
</section>

<script>
(function() {
    const form = document.getElementById('formulario-contacto');
    if (!form) return;

    form.addEventListener('submit', function(ev) {
        ev.preventDefault();

        const nombre  = form.querySelector('[name="nombre"]').value.trim();
        const email   = form.querySelector('[name="email"]').value.trim();
        const mensaje = form.querySelector('[name="mensaje"]').value.trim();

        if (!nombre || !email || !mensaje) {
            if (window.toast) window.toast.error('Completa nombre, email y mensaje.');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (window.toast) window.toast.error('Email no válido.');
            return;
        }

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        const txtOriginal = btn.innerHTML;
        btn.innerHTML = 'Enviando…';

        const fd = new FormData(form);
        fd.append('tipo', 'contacto');
        fd.append('page_url', window.location.href);

        fetch('<?= BASE_URL ?>/procesar-formulario.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    if (window.dataLayer) {
                        window.dataLayer.push({
                            event: 'form_submit_success',
                            form_id: data.form_id,
                            form_name: data.form_name
                        });
                    }
                    if (window.toast) window.toast.ok('¡Mensaje enviado! Te respondemos lo antes posible.');
                    form.reset();
                } else {
                    if (window.toast) window.toast.error(data.mensaje || 'Error al enviar. Inténtalo otra vez.');
                }
            })
            .catch(() => {
                if (window.toast) window.toast.error('Error de conexión. Inténtalo otra vez.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = txtOriginal;
            });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
