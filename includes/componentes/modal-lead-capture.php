<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MODAL LEAD-CAPTURE (widget interno)
 * ═══════════════════════════════════════════════════════════
 *
 * Modal interceptor: se abre cuando el usuario clickea un botón con
 * data-lead-capture (tel/wa/maps/web). Captura datos del lead antes de
 * redirigir al destino externo.
 *
 * Branding: en contexto de ficha muestra logo + nombre del negocio.
 * Genérico: muestra ícono propio del sitio.
 *
 * Activado por: assets/js/lead-capture.js
 * Endpoint: procesar-lead-b2c.php
 * ═══════════════════════════════════════════════════════════
 */
?>
<div class="lc-modal" id="lc-modal" aria-hidden="true">
    <div class="lc-modal__overlay" data-lc-close></div>
    <div class="lc-modal__card" role="dialog" aria-labelledby="lc-titulo" aria-modal="true">

        <!-- Header con branding (logo + título + cerrar) -->
        <header class="lc-modal__header">
            <!-- Brand row del sitio: paw + nombre. Visible en modo genérico, centrado. -->
            <div class="lc-modal__brand">
                <i data-lucide="paw-print" class="icono lc-modal__brand-icon"></i>
                <span class="lc-modal__brand-name"><?php echo defined('SITIO_NOMBRE') ? htmlspecialchars(SITIO_NOMBRE) : 'Crematorios de Mascotas'; ?></span>
            </div>

            <!-- Logo del negocio: visible en modo ficha (a la izquierda del título) -->
            <div class="lc-modal__logo-wrap">
                <img id="lc-logo" src="" alt="" style="display:none;">
                <span class="lc-modal__logo-fallback" id="lc-logo-fallback">
                    <i data-lucide="paw-print" class="icono"></i>
                </span>
            </div>

            <div class="lc-modal__heading">
                <h2 class="lc-modal__titulo" id="lc-titulo">¿Cómo podemos ayudarte?</h2>
            </div>

            <button class="lc-modal__close" type="button" data-lc-close aria-label="Cerrar">
                <i data-lucide="x" class="icono"></i>
            </button>
        </header>

        <!-- Form -->
        <form id="lc-form" class="lc-form" novalidate>
            <!-- Honeypot anti-bot -->
            <input type="text" name="website_url" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;">
            <input type="hidden" name="form_render_ts" id="lc-render-ts">

            <!-- Contexto (lo llena el JS al abrir) -->
            <input type="hidden" name="channel_type"      id="lc-channel">
            <input type="hidden" name="accion_destino"    id="lc-destino">
            <input type="hidden" name="crematorio_id"     id="lc-crematorio-id">
            <input type="hidden" name="crematorio_nombre" id="lc-crematorio-nombre">
            <input type="hidden" name="phone_agent"       id="lc-phone-agent">
            <input type="hidden" name="pagina_origen"     id="lc-pagina-origen">
            <input type="hidden" name="utm_source"        id="lc-utm-source">
            <input type="hidden" name="utm_medium"        id="lc-utm-medium">
            <input type="hidden" name="utm_campaign"      id="lc-utm-campaign">

            <!-- Subtítulo motivacional (contextual) -->
            <p class="lc-form__intro" id="lc-sub">Completa tus datos para obtener un mejor servicio de este negocio.</p>

            <!-- Servicio (Perro/Gato/Otro) -->
            <div class="lc-field">
                <div class="lc-radios">
                    <label class="lc-radio">
                        <input type="radio" name="servicio" value="Perro" required>
                        <span>Perro</span>
                    </label>
                    <label class="lc-radio">
                        <input type="radio" name="servicio" value="Gato">
                        <span>Gato</span>
                    </label>
                    <label class="lc-radio">
                        <input type="radio" name="servicio" value="Otro">
                        <span>Otro</span>
                    </label>
                </div>
            </div>

            <!-- Tamaño (Tom Select; placeholder no aparece en el listado) -->
            <div class="lc-field">
                <select name="mascota_tamano" class="field__select field__select--enhanced lc-select" data-ts-search="off" data-ts-hide-empty="1" data-placeholder="Tamaño de la mascota*" required>
                    <option value="">Tamaño de la mascota*</option>
                    <option value="Hasta 5 kg">Hasta 5 kg</option>
                    <option value="5 - 15 kg">5 - 15 kg</option>
                    <option value="15 - 25 kg">15 - 25 kg</option>
                    <option value="Más de 25 kg">Más de 25 kg</option>
                    <option value="No sé">No sé</option>
                </select>
            </div>

            <!-- Nombre -->
            <input type="text" name="nombre" placeholder="Nombre*" required class="lc-input">

            <!-- Email -->
            <input type="email" name="email" placeholder="Email*" required class="lc-input">

            <!-- WhatsApp con código de país (split país | número) — país con Tom Select -->
            <div class="lc-tel-wrap">
                <select name="country_code" class="field__select field__select--enhanced lc-select-pais" required>
                    <option value="ES" data-code="34" selected>ES +34</option>
                    <option value="PT" data-code="351">PT +351</option>
                    <option value="AR" data-code="54">AR +54</option>
                    <option value="MX" data-code="52">MX +52</option>
                    <option value="CO" data-code="57">CO +57</option>
                    <option value="CL" data-code="56">CL +56</option>
                    <option value="PE" data-code="51">PE +51</option>
                    <option value="EC" data-code="593">EC +593</option>
                    <option value="UY" data-code="598">UY +598</option>
                    <option value="VE" data-code="58">VE +58</option>
                    <option value="BR" data-code="55">BR +55</option>
                    <option value="US" data-code="1">US +1</option>
                </select>
                <input type="hidden" name="phone_code" id="lc-phone-code" value="34">
                <input type="tel" name="whatsapp_number" placeholder="Teléfono*" required class="lc-input lc-input--tel">
            </div>

            <!-- Ciudad -->
            <input type="text" name="ciudad" placeholder="Ciudad*" required class="lc-input">

            <!-- Mensaje -->
            <textarea name="mensaje" placeholder="Mensaje (opcional)" rows="3" class="lc-input"></textarea>

            <!-- Acciones -->
            <div class="lc-acciones">
                <button type="submit" class="lc-btn-primary">
                    <i data-lucide="send" class="icono"></i>
                    Enviar y continuar
                </button>
                <button type="button" class="lc-btn-skip" data-lc-skip>
                    Ir directo sin completar
                    <i data-lucide="arrow-right" class="icono"></i>
                </button>
            </div>
        </form>
    </div>
</div>
