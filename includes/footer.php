
    <!-- Footer -->
    <footer class="footer">
        <div class="footer__contenedor">

            <!-- Grid principal: 4 columnas -->
            <div class="footer__grid">

                <!-- Sección 1: Sobre nosotros -->
                <div class="footer__seccion">
                    <h3 class="footer__titulo" style="display:flex; align-items:center; gap:.5rem;">
                        <i data-lucide="paw-print" class="icono" style="width:22px; height:22px; color:var(--color-uno);"></i>
                        Crematorios de Mascotas
                    </h3>
                    <p class="footer__texto">
                        El directorio más completo de servicios de cremación para mascotas en España.
                        Encuentra el crematorio perfecto para despedir a tu compañero con dignidad.
                    </p>
                </div>

                <!-- Sección 2: Enlaces rápidos -->
                <div class="footer__seccion">
                    <h3 class="footer__titulo">Enlaces Rápidos</h3>
                    <ul class="footer__lista">
                        <li>
                            <a href="<?php echo $base_url; ?>/" class="footer__enlace">
                                <i data-lucide="home" class="icono"></i>
                                Inicio
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_url; ?>/directorio.php" class="footer__enlace">
                                <i data-lucide="map" class="icono"></i>
                                Directorio
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_url; ?>/nosotros.php" class="footer__enlace">
                                <i data-lucide="info" class="icono"></i>
                                Sobre Nosotros
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Sección 3: Contacto -->
                <div class="footer__seccion">
                    <h3 class="footer__titulo">Contacto</h3>
                    <ul class="footer__lista">
                        <li>
                            <a href="mailto:info@crematoriosdemascotas.com" class="footer__enlace">
                                <i data-lucide="mail" class="icono"></i>
                                info@crematoriosdemascotas.com
                            </a>
                        </li>
                        <li>
                            <a href="tel:+34631256751" class="footer__enlace">
                                <i data-lucide="phone" class="icono"></i>
                                +34 631 256 751
                            </a>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- CTA Para Negocios (tarjeta horizontal) -->
            <div class="footer__cta-negocios">
                <div class="footer__cta-negocios__texto">
                    <p class="footer__cta-negocios__titulo">¿Tienes un crematorio de mascotas?</p>
                    <p class="footer__cta-negocios__descripcion">Únete a nuestro directorio y conecta con más familias</p>
                </div>
                <div class="footer__cta-negocios__acciones">
                    <a href="<?php echo $base_url; ?>/registrar-negocio.php" class="boton uno">
                        <i data-lucide="plus-circle" class="icono"></i>
                        Registrar mi Crematorio
                    </a>
                    <button type="button" class="boton dos" onclick="MicroModal.show('modal-promocionar', { disableScroll: true, awaitCloseAnimation: true })">
                        <i data-lucide="megaphone" class="icono"></i>
                        Promocionar mi Crematorio
                    </button>
                </div>
            </div>

            <!-- Copyright -->
            <div class="footer__copyright">
                <p>&copy; <?php echo date('Y'); ?> Crematorios de Mascotas. Todos los derechos reservados.</p>
                <p>
                    <a href="<?php echo $base_url; ?>/privacidad.php" class="footer__enlace-legal">Política de Privacidad</a> |
                    <a href="<?php echo $base_url; ?>/terminos.php" class="footer__enlace-legal">Términos de Uso</a> |
                    <a href="<?php echo $base_url; ?>/cookies.php" class="footer__enlace-legal">Política de Cookies</a>
                </p>
                <p>
                    Sitio Web Diseñado por <a href="<?php echo htmlspecialchars(urlConUtm('https://lycapolis.com/')); ?>" target="_blank" rel="noopener" class="footer__enlace-legal">Lycapolis LLC</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="<?php echo $base_url; ?>/assets/js/lucide.min.js"></script>
    <script>
        // Inicializar iconos Lucide
        lucide.createIcons();

        // Toggle menú móvil — también cambia el ícono del botón entre ☰ y ✕.
        // El header sigue visible por encima del menú overlay (z-index 1600 vs
        // 1500), así que el mismo botón sirve para abrir y cerrar.
        // OJO: lucide convierte el <i data-lucide> en <svg> al cargar la página.
        // Cambiar el atributo data-lucide del SVG no actualiza nada; hay que
        // reemplazar el innerHTML del botón con un <i> nuevo y volver a llamar
        // a lucide.createIcons() para que lo procese.
        function toggleMenu() {
            const menu = document.getElementById('menu-movil');
            const btn  = document.getElementById('btn-menu-movil');
            const abierto = menu.classList.toggle('activo');
            if (btn) {
                btn.innerHTML = '<i data-lucide="' + (abierto ? 'x' : 'menu') + '" class="icono"></i>';
                btn.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
                if (window.lucide) lucide.createIcons();
            }
        }
    </script>

    <!-- Tom Select — enhance todos los <select.field__select--enhanced>
         data-ts-search="off"  → desactiva el input de búsqueda (listas fijas)
         data-ts-clear="on"    → habilita el botón X de clear (por default está off)
         data-ts-autosubmit="1" → submit del form al cambiar -->
    <script src="<?php echo $base_url; ?>/assets/librerias/tom-select/tom-select.complete.min.js"></script>
    <script>
    (function() {
        if (typeof TomSelect === 'undefined') return;
        function tsInitOne(sel) {
            if (!sel || sel.tomselect) return;
            // data-ts-hide-empty="1" → la opción con value="" se usa SOLO como placeholder
            // (no aparece en el listado del dropdown). Para selects required donde
            // la opción vacía no es una elección válida del usuario.
            var hideEmpty = sel.dataset.tsHideEmpty === '1';
            var opts = {
                create: false,
                allowEmptyOption: true,  // siempre true para que el placeholder funcione
                plugins: sel.dataset.tsClear === 'on' ? ['clear_button'] : [],
                placeholder: sel.dataset.placeholder || sel.querySelector('option[value=""]')?.textContent || ''
            };
            if (sel.dataset.tsPortal !== 'off') opts.dropdownParent = 'body';
            if (sel.dataset.tsSearch === 'off') opts.controlInput = null;
            // Si data-ts-hide-empty="1": oculta la opción vacía del listado
            // pero mantiene la lógica del placeholder intacta.
            if (hideEmpty) {
                opts.render = {
                    option: function(data, escape) {
                        if (data.value === '') return '<div style="display:none;"></div>';
                        return '<div>' + escape(data.text) + '</div>';
                    }
                };
            }
            // Guardamos el value antes de inicializar TS — base para detectar
            // cambios REALES del usuario (no normalizaciones internas de TS).
            var valorInicial = sel.value;

            new TomSelect(sel, opts);

            // Autosubmit: listener nativo agregado DESPUÉS de inicializar TS,
            // dentro de un setTimeout(0) para asegurar que cualquier `change`
            // event que TS dispare durante init ya pasó.
            //
            // Crítico: si el listener corre durante init con value="", el form
            // se submite con todos los campos vacíos → URL queda con
            // ?orden=&geo=&ciudad=&valoracion_minima= incluso en páginas legales.
            if (sel.dataset.tsAutosubmit === '1') {
                setTimeout(function() {
                    sel.addEventListener('change', function() {
                        // Doble safety: ignorar si el valor no cambió respecto al inicial
                        if (sel.value === valorInicial) return;
                        valorInicial = sel.value;
                        var f = sel.form || sel.closest('form');
                        if (f) f.submit();
                    });
                }, 0);
            }
        }
        window.tsEnhanceScope = function(scope) {
            (scope || document).querySelectorAll('select.field__select--enhanced').forEach(tsInitOne);
        };
        window.tsDestroyScope = function(scope) {
            if (!scope) return;
            scope.querySelectorAll('select').forEach(function(sel) {
                if (sel.tomselect) { try { sel.tomselect.destroy(); } catch (e) {} }
            });
        };
        window.tsEnhanceScope(document);
    })();
    </script>

    <!-- Cascade Geo (CCAA/Provincia) → Ciudad. Filtra opciones del dropdown
         de ciudad según la geo elegida. Útil cuando NO hay autosubmit
         (la página no recarga; necesitamos filtrar dinámicamente en cliente). -->
    <script>
    (function() {
        function wireCascade(geoId, ciudadId) {
            var geoSel    = document.getElementById(geoId);
            var ciudadSel = document.getElementById(ciudadId);
            if (!geoSel || !ciudadSel) return;

            // Snapshot de opciones originales (cuando se carga la página)
            var todas = Array.from(ciudadSel.querySelectorAll('option')).map(function(o) {
                return {
                    value: o.value,
                    text:  o.textContent.trim(),
                    ccaa:  parseInt(o.dataset.comunidadId || 0, 10),
                    prov:  parseInt(o.dataset.provinciaId || 0, 10)
                };
            });

            function parseGeo() {
                var v = geoSel.value || '';
                if (v.indexOf('ccaa:') === 0) return { ccaa: parseInt(v.slice(5), 10), prov: 0 };
                if (v.indexOf('prov:') === 0) return { ccaa: 0, prov: parseInt(v.slice(5), 10) };
                return { ccaa: 0, prov: 0 };
            }

            function aplicar() {
                var f = parseGeo();
                var ts = ciudadSel.tomselect;
                if (!ts) return;
                var prev = ts.getValue();
                ts.clear(true);
                ts.clearOptions();
                todas.forEach(function(opt) {
                    if (opt.value === '') { ts.addOption({ value: '', text: opt.text }); return; }
                    if (f.prov && opt.prov !== f.prov) return;
                    if (f.ccaa && opt.ccaa !== f.ccaa) return;
                    ts.addOption({ value: opt.value, text: opt.text });
                });
                ts.refreshOptions(false);
                if (prev && ts.options[prev]) ts.setValue(prev, true);
            }

            if (geoSel.tomselect) {
                geoSel.tomselect.on('change', aplicar);
            } else {
                geoSel.addEventListener('change', aplicar);
            }
        }

        // Pares conocidos: home (h-geo/h-ciudad) y directorio (geo/ciudad)
        wireCascade('h-geo',  'h-ciudad');
        wireCascade('geo',    'ciudad');
    })();
    </script>

    <!-- ═══ Feedback: Notyf (toasts) + Micromodal (confirmaciones) ═══ -->
    <script src="<?php echo $base_url; ?>/assets/librerias/notyf/notyf.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/librerias/micromodal/micromodal.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/feedback.js'); ?>"></script>

    <!-- ═══ Lead-capture widget (interno) ═══
         Modal interceptor para clicks salientes (tel/wa/maps/web).
         Reemplaza el widget externo de Lycapolis (ya removido del header). -->
    <?php include ROOT_PATH . '/includes/componentes/modal-lead-capture.php'; ?>

    <?php
    // ─── Burbuja flotante (WhatsApp) que acompaña el scroll ───
    // Contexto: si estamos en una ficha, usa el WA del negocio. Si no, WHATSAPP_SOPORTE.
    // Si no hay ningún número aplicable, no renderizamos la burbuja.
    $_lcBubbleNum    = '';
    $_lcBubbleId     = '';
    $_lcBubbleName   = '';
    $_lcBubbleLogo   = '';
    if (!empty($whatsapp ?? null)) {
        $_lcBubbleNum  = preg_replace('/[^0-9]/', '', $whatsapp);
        $_lcBubbleId   = (int)($crematorio['id'] ?? 0);
        $_lcBubbleName = $crematorio_nombre ?? '';
        $_lcBubbleLogo = $logo_url ?? '';
    } elseif (defined('WHATSAPP_SOPORTE') && WHATSAPP_SOPORTE !== '') {
        $_lcBubbleNum = preg_replace('/[^0-9]/', '', WHATSAPP_SOPORTE);
    }
    if ($_lcBubbleNum):
        // Mensaje inicial del wa.me — SOLO se usa si el usuario evita el modal
        // (skip) y va directo. Si llena el form, procesar-lead-b2c.php lo
        // reemplaza por uno rico. Le damos contexto si hay ficha (no datos
        // personales que aún no tenemos).
        if ($_lcBubbleName !== '') {
            $_lcBubbleTexto = 'Hola, vi su ficha de ' . $_lcBubbleName
                            . ' en Crematoriosdemascotas.com y me gustaría obtener información.';
        } else {
            $_lcBubbleTexto = 'Hola, me gustaría obtener información sobre servicios de cremación para mascotas.';
        }
        $_lcBubbleUrl = 'https://wa.me/' . $_lcBubbleNum . '?text=' . urlencode($_lcBubbleTexto);
    ?>
    <a href="<?php echo $_lcBubbleUrl; ?>"
       class="lc-bubble"
       target="_blank"
       rel="noopener"
       aria-label="Escribir por WhatsApp"
       data-lead-capture="wa"
       data-destino="<?php echo $_lcBubbleUrl; ?>"
       data-no-skip="1"
       data-phone-agent="<?php echo $_lcBubbleNum; ?>"
       <?php if ($_lcBubbleId): ?>
       data-crematorio-id="<?php echo $_lcBubbleId; ?>"
       data-crematorio-nombre="<?php echo htmlspecialchars($_lcBubbleName, ENT_QUOTES); ?>"
       data-crematorio-logo="<?php echo htmlspecialchars($_lcBubbleLogo, ENT_QUOTES); ?>"
       <?php endif; ?>>
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/>
        </svg>
    </a>
    <?php endif; ?>

    <script>window.LC_BASE_URL = <?php echo json_encode($base_url ?? '/crematoriosdemascotas'); ?>;</script>
    <script src="<?php echo assetUrl('assets/js/lead-capture.js'); ?>" defer></script>

    <div class="modal micromodal-slide" id="modal-confirmar" aria-hidden="true">
        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
            <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-confirmar-title">
                <header class="modal__header">
                    <span class="modal__icono" id="modal-confirmar-icono"></span>
                    <h2 class="modal__title" id="modal-confirmar-title"></h2>
                </header>
                <div class="modal__content" id="modal-confirmar-content"></div>
                <footer class="modal__footer">
                    <button type="button" class="boton dos" id="modal-confirmar-cancel" data-micromodal-close>Cancelar</button>
                    <button type="button" class="boton uno" id="modal-confirmar-ok">Confirmar</button>
                </footer>
            </div>
        </div>
    </div>

    <!-- Modal: Promocionar mi crematorio (lead comercial B2B) -->
    <style>
        /* Compacto: que entre sin scroll en viewports normales */
        #modal-promocionar .modal__container { padding: var(--espacio-tres) var(--espacio-cuatro); }
        #modal-promocionar .modal__header    { margin-bottom: 0.4rem; }
        #modal-promocionar .modal__content   { margin-bottom: var(--espacio-tres); }
        #modal-promocionar .field            { margin-bottom: 0.7rem; gap: 0.3rem; }
        #modal-promocionar .field__textarea  { min-height: 58px; }
    </style>
    <div class="modal micromodal-slide" id="modal-promocionar" aria-hidden="true">
        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
            <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-promocionar-title" style="max-width:520px; max-height:90vh; overflow-y:auto;">
                <form id="form-promocionar" onsubmit="enviarPromocion(event)">
                    <!-- Anti-spam: honeypot (invisible, sólo bots completan) + time-trap -->
                    <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; width:0; overflow:hidden;">
                        <label for="promo-website-url">No completar este campo</label>
                        <input type="text" id="promo-website-url" name="website_url" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <input type="hidden" id="promo-render-ts" name="form_render_ts" value="<?php echo time(); ?>">

                    <header class="modal__header">
                        <span class="modal__icono"><i data-lucide="megaphone"></i></span>
                        <h2 class="modal__title" id="modal-promocionar-title">Promociona tu crematorio</h2>
                    </header>
                    <div class="modal__content">
                        <p style="margin:0 0 var(--espacio-tres); color:var(--admin-tinta-suave);">
                            Destaca tu ficha y llega a más familias. Déjanos tus datos y te contamos las opciones.
                        </p>

                        <div class="field">
                            <label class="field__label" for="promo-nombre">Nombre y apellido <span class="field__req">obligatorio</span></label>
                            <input type="text" id="promo-nombre" class="field__input" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="promo-negocio">Nombre del crematorio <span class="field__req">obligatorio</span></label>
                            <input type="text" id="promo-negocio" class="field__input" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="promo-email">Email <span class="field__req">obligatorio</span></label>
                            <input type="email" id="promo-email" class="field__input" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="promo-telefono">Teléfono <span class="field__req">obligatorio</span></label>
                            <input type="tel" id="promo-telefono" class="field__input" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="promo-ciudad">Ciudad</label>
                            <input type="text" id="promo-ciudad" class="field__input" placeholder="Opcional">
                        </div>
                        <div class="field">
                            <label class="field__label" for="promo-mensaje">Mensaje</label>
                            <textarea id="promo-mensaje" class="field__textarea" rows="3" placeholder="Cuéntanos qué te interesa: destacar tu ficha, más visibilidad, etc. (opcional)"></textarea>
                        </div>
                    </div>
                    <footer class="modal__footer">
                        <button type="button" class="boton dos pequeno" data-micromodal-close>Cancelar</button>
                        <button type="submit" class="boton uno pequeno">
                            <i data-lucide="send" class="icono"></i>
                            Enviar consulta
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
    <script>
    function enviarPromocion(e) {
        e.preventDefault();
        var g = function (id) { return (document.getElementById(id).value || '').trim(); };
        var nombre   = g('promo-nombre');
        var negocio  = g('promo-negocio');
        var email    = g('promo-email');
        var telefono = g('promo-telefono');
        var ciudad   = g('promo-ciudad');
        var mensaje  = g('promo-mensaje');

        var faltan = [];
        if (!nombre)   faltan.push('Nombre y apellido');
        if (!negocio)  faltan.push('Nombre del crematorio');
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) faltan.push('Email válido');
        if (!telefono) faltan.push('Teléfono');
        if (faltan.length) {
            var msg = 'Faltan datos:<br>• ' + faltan.join('<br>• ');
            if (window.toast) toast.error(msg); else alert(faltan.join('\n'));
            return;
        }

        // tipo=lead_comercial → se persiste en leads_comerciales + email estructurado.
        var fd = new FormData();
        fd.append('tipo', 'lead_comercial');
        fd.append('nombre', nombre);
        fd.append('nombre_negocio', negocio);
        fd.append('email', email);
        fd.append('telefono', telefono);
        fd.append('ciudad', ciudad);
        fd.append('mensaje', mensaje);
        fd.append('origen', 'popup');
        fd.append('page_url', window.location.href);
        // Anti-spam — honeypot + time-trap (mismo patrón que contacto / reseña / lead-capture)
        fd.append('website_url', g('promo-website-url'));
        fd.append('form_render_ts', document.getElementById('promo-render-ts').value || '');

        var btn = e.target.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        fetch('<?php echo $base_url; ?>/procesar-formulario.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    if (window.MicroModal) MicroModal.close('modal-promocionar');
                    if (window.toast) toast.ok('¡Gracias! Recibimos tu consulta. Te abrimos WhatsApp con tu mensaje precargado para acelerar el contacto.');
                    document.getElementById('form-promocionar').reset();
                    // ─── Redirigir a WhatsApp con mensaje rico al soporte comercial ──
                    // Plantilla C: cliente B2B (dueño de negocio) presentándose para
                    // promocionar su crematorio. Castellano neutral.
                    var partes = [];
                    partes.push('Hola, soy ' + nombre + ' de ' + negocio + '.');
                    partes.push('');
                    partes.push('Estoy interesado en promocionar mi crematorio en Crematoriosdemascotas.com.');
                    if (mensaje) {
                        partes.push('');
                        partes.push(mensaje);
                    }
                    partes.push('');
                    partes.push('📞 Mis datos:');
                    if (telefono) partes.push('Teléfono: ' + telefono);
                    if (email)    partes.push('Email: ' + email);
                    if (ciudad)   partes.push('Ciudad: ' + ciudad);
                    partes.push('');
                    partes.push('— Lead comercial vía Crematoriosdemascotas.com');
                    var texto = partes.join('\n');
                    var waSoporte = '<?php echo defined("WHATSAPP_SOPORTE") ? preg_replace("/[^0-9]/", "", WHATSAPP_SOPORTE) : ""; ?>';
                    if (waSoporte) {
                        var waUrl = 'https://wa.me/' + waSoporte + '?text=' + encodeURIComponent(texto);
                        // Pequeño delay para que el usuario vea el toast antes de cambiar de pestaña
                        setTimeout(function () { window.open(waUrl, '_blank', 'noopener'); }, 800);
                    }
                } else {
                    if (window.toast) toast.error(d.mensaje || 'No se pudo enviar. Intentalo de nuevo.');
                }
            })
            .catch(function () {
                if (window.toast) toast.error('Error de conexión. Probá de nuevo.');
            })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="send" class="icono"></i> Enviar consulta'; if (window.lucide) lucide.createIcons(); }
            });
    }
    // El modal se inyecta después del lucide.createIcons() inicial → sus
    // iconos (megaphone, send) quedan sin convertir. Los renderizamos acá.
    if (window.lucide) lucide.createIcons();
    </script>

    <!-- Puente flash server→toast (público) -->
    <script>
    (function() {
        var url = new URL(window.location);
        var okParams  = ['ok', 'img_ok'];
        var errParams = ['error', 'img_error', 'form_error'];
        var yaRenderizadoEnPHP = !!document.querySelector('[data-flash-php]');
        function leer(p) {
            if (!url.searchParams.has(p)) return null;
            var v = url.searchParams.get(p);
            try { v = decodeURIComponent(v); } catch (e) {}
            return v;
        }
        if (!yaRenderizadoEnPHP && window.toast) {
            okParams.forEach(function(p) { var m = leer(p); if (m) window.toast.ok(m); });
            errParams.forEach(function(p) { var m = leer(p); if (m) window.toast.error(m); });
        }
        var changed = false;
        okParams.concat(errParams).forEach(function(p) {
            if (url.searchParams.has(p)) { url.searchParams.delete(p); changed = true; }
        });
        if (changed) window.history.replaceState({}, '', url.toString());
    })();
    </script>
</body>
</html>
