/**
 * ═══════════════════════════════════════════════════════════
 * LEAD-CAPTURE WIDGET (interno)
 * ═══════════════════════════════════════════════════════════
 *
 * Intercepta clicks en cualquier <a> o <button> con `data-lead-capture`,
 * abre el modal #lc-modal con form prellenado contextualmente, y al submit
 * envía a procesar-lead-b2c.php → guarda lead + dispara webhook → redirige
 * al destino original con UTMs.
 *
 * Atributos esperados en el botón:
 *   data-lead-capture = "tel" | "wa" | "maps" | "web"
 *   data-destino      = URL final donde redirigimos (tel:..., wa.me/..., etc.)
 *   data-crematorio-id, data-crematorio-nombre, data-crematorio-logo (opcional)
 *   data-phone-agent  = teléfono o whatsapp del destino (para metadata)
 *   data-no-skip="1"  = no ofrecer "Ir directo" (la burbuja flotante usa esto)
 *
 * Throttling multi-nivel (cookie `lc_state` con JSON):
 *   - Cap global: máximo 4 modals POR sesión (no cuentan los de la burbuja)
 *   - Silencio post-skip: 10 min POR CANAL ESPECÍFICO de cada ficha
 *     (cada botón tel/wa/maps/web es una intención distinta; cerrar el modal
 *      en "Ver en el mapa" no silencia el resto de los canales)
 *   - Silencio post-submit: 24h POR FICHA (todos los canales) + 24h GLOBAL
 *     (si ya tenemos los datos, no se los pedimos en ningún canal/ficha)
 *   - Burbuja flotante: solo respeta el silencio global post-submit;
 *     no cuenta para el cap, no respeta per-ficha (válvula de escape)
 *
 * Compat: si existe la cookie vieja `lc_session=1`, se respeta como
 * silencio global de 30 min más, hasta que expire por sí sola.
 *
 * Endpoints:
 *   procesar-lead-b2c.php       — submit del form
 *   registrar-clic-outbound.php — tracking ligero de clicks (anonymous)
 *
 * Autor: Facundo M. Campos | Lycapolis LLC
 * ═══════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var modal    = document.getElementById('lc-modal');
    if (!modal) return;

    // El modal viene DESPUÉS del bootstrap de Tom Select en footer.php,
    // así que TS no lo enhance automáticamente. Lo hacemos manualmente aquí.
    if (window.tsEnhanceScope) {
        window.tsEnhanceScope(modal);
    }

    var form      = document.getElementById('lc-form');
    var btnsClose = modal.querySelectorAll('[data-lc-close]');
    var btnSkip   = modal.querySelector('[data-lc-skip]');
    var titulo    = document.getElementById('lc-titulo');
    var sub       = document.getElementById('lc-sub');
    var logoImg   = document.getElementById('lc-logo');
    var logoFb    = document.getElementById('lc-logo-fallback');

    // BASE_URL — se infiere del path actual o desde una var global si existe
    var BASE_URL = (window.LC_BASE_URL || (location.pathname.split('/')[1] ? '/' + location.pathname.split('/')[1] : ''));

    // ═══════════════════════════════════════════════════════════
    // CONFIGURACIÓN DE THROTTLING
    // ═══════════════════════════════════════════════════════════
    var CAP_GLOBAL_SESION  = 4;                  // máx modals/sesión (excluye burbuja)
    var SKIP_MS            = 10 * 60 * 1000;     // 10 min post-skip per-ficha
    var SUBMIT_MS          = 24 * 60 * 60 * 1000; // 24h post-submit per-ficha + global
    var COOKIE_DIAS        = 1;                  // cookie lc_state vive 24h

    // ─── Cookie utils ──────────────────────────────────────────
    function getCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]+)'));
        return m ? decodeURIComponent(m[1]) : null;
    }
    function setCookieMin(name, value, minutes) {
        var d = new Date();
        d.setTime(d.getTime() + minutes * 60 * 1000);
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
    }

    // ─── Estado throttling (cookie lc_state JSON) ──────────────
    function leerEstado() {
        var estado = { global_count: 0, global_submit_until: 0, fichas: {} };
        var s = getCookie('lc_state');
        if (s) {
            try {
                var obj = JSON.parse(s);
                estado.global_count        = obj.global_count        || 0;
                estado.global_submit_until = obj.global_submit_until || 0;
                estado.fichas              = obj.fichas              || {};
            } catch (e) { /* JSON corrupto → estado fresco */ }
        }
        // Compat con cookie vieja lc_session=1 (visitantes ya silenciados antes
        // de este cambio). Si está activa, respetar 30 min más como submit global.
        if (getCookie('lc_session') === '1' && estado.global_submit_until < Date.now()) {
            estado.global_submit_until = Date.now() + 30 * 60 * 1000;
        }
        return estado;
    }

    function guardarEstado(estado) {
        setCookieMin('lc_state', JSON.stringify(estado), COOKIE_DIAS * 24 * 60);
    }

    function getFichaState(estado, fichaId) {
        var id = String(fichaId || 0);
        if (!estado.fichas[id]) estado.fichas[id] = { submit_until: 0, canales: {} };
        if (!estado.fichas[id].canales) estado.fichas[id].canales = {};
        return estado.fichas[id];
    }

    function getCanalState(estado, fichaId, canal) {
        var f = getFichaState(estado, fichaId);
        var key = String(canal || 'unknown');
        if (!f.canales[key]) f.canales[key] = { skip_until: 0 };
        return f.canales[key];
    }

    /**
     * Decide qué hacer cuando se hace click en un trigger.
     * Devuelve 'modal' (abrir modal) o 'directo' (saltar modal, redirigir).
     */
    function decidirAccion(ctx, estado) {
        var ahora = Date.now();

        // 1. Silencio global post-submit → aplica a TODOS (incluida burbuja)
        if (estado.global_submit_until > ahora) return 'directo';

        // 2. Burbuja: solo respetaba el global submit (paso 1) → modal
        if (ctx.esBurbuja) return 'modal';

        // 3. Cap global de sesión (solo cuenta triggers de ficha)
        if (estado.global_count >= CAP_GLOBAL_SESION) return 'directo';

        // 4. Silencios per-ficha (submit) + per-canal (skip)
        if (ctx.crematorioId) {
            var f = getFichaState(estado, ctx.crematorioId);
            // Submit aplica a TODOS los canales de esa ficha
            if (f.submit_until > ahora) return 'directo';
            // Skip solo aplica al CANAL específico clickeado
            var c = getCanalState(estado, ctx.crematorioId, ctx.accion);
            if (c.skip_until > ahora) return 'directo';
        }

        return 'modal';
    }

    // ─── UTMs del URL actual ───────────────────────────────────
    function getUtms() {
        var p = new URLSearchParams(location.search);
        return {
            utm_source:   p.get('utm_source')   || '',
            utm_medium:   p.get('utm_medium')   || '',
            utm_campaign: p.get('utm_campaign') || ''
        };
    }

    // Estado actual del modal (datos del trigger) ──────────────
    var ctxActual = null;

    function abrirModal(ctx) {
        ctxActual = ctx;

        // 2 modos: contextual (ficha) vs genérico (búsqueda / exploración)
        var esGenerico = !ctx.crematorioNombre;
        modal.classList.toggle('lc-modal--generico', esGenerico);

        if (!esGenerico) {
            // ── Modo FICHA: contacto directo al negocio ──
            titulo.textContent = 'Contactar con ' + ctx.crematorioNombre;
            sub.textContent    = 'Completa tus datos para obtener un mejor servicio de este negocio.';
        } else {
            // ── Modo GENÉRICO: búsqueda / asistencia ──
            titulo.textContent = '¿Te ayudamos a elegir?';
            sub.textContent    = 'Cuéntanos qué necesitas y te conectamos con el crematorio ideal de tu zona.';
        }
        if (ctx.crematorioLogo) {
            logoImg.src = ctx.crematorioLogo;
            logoImg.style.display = 'block';
            if (logoFb) logoFb.style.display = 'none';
        } else {
            logoImg.style.display = 'none';
            if (logoFb) logoFb.style.display = '';
        }

        // Toggle del botón "Ir directo sin completar" — oculto cuando viene de la burbuja
        if (btnSkip) {
            btnSkip.style.display = ctx.noSkip ? 'none' : '';
        }

        // Hidden fields contextuales
        document.getElementById('lc-channel').value           = ctx.accion || '';
        document.getElementById('lc-destino').value           = ctx.destino || '';
        document.getElementById('lc-crematorio-id').value     = ctx.crematorioId || '';
        document.getElementById('lc-crematorio-nombre').value = ctx.crematorioNombre || '';
        document.getElementById('lc-phone-agent').value       = ctx.phoneAgent || '';
        document.getElementById('lc-pagina-origen').value     = location.href;
        var u = getUtms();
        document.getElementById('lc-utm-source').value   = u.utm_source;
        document.getElementById('lc-utm-medium').value   = u.utm_medium;
        document.getElementById('lc-utm-campaign').value = u.utm_campaign;
        document.getElementById('lc-render-ts').value    = Math.floor(Date.now() / 1000);

        // Reset honeypot + datos del form (deja default servicio si no estaba)
        form.querySelector('[name="website_url"]').value = '';

        // Abre
        modal.classList.add('lc-modal--abierto');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lc-modal-abierto');

        // Cuenta para el cap solo si NO es burbuja
        if (!ctx.esBurbuja) {
            var estado = leerEstado();
            estado.global_count = (estado.global_count || 0) + 1;
            guardarEstado(estado);
        }

        // Refresh iconos lucide del modal (por si es la primera vez)
        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons({ nodes: modal.querySelectorAll('[data-lucide]') });
        }
    }

    /**
     * Cierra el modal sin submit (X, ESC, overlay).
     * Tratamiento: skip implícito en el CANAL específico clickeado
     * (10 min de silencio solo para ese canal en esa ficha).
     * La burbuja no setea silencio per-ficha (no tiene ficha contextual).
     */
    function cerrarModal(modalAction) {
        modal.classList.remove('lc-modal--abierto');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lc-modal-abierto');
        if (ctxActual && modalAction === 'cancelled') {
            registrarClick(ctxActual, 'cancelled');
            // Cierre sin submit = skip implícito (solo si hay ficha contextual)
            if (ctxActual.crematorioId && !ctxActual.esBurbuja) {
                var estado = leerEstado();
                var c = getCanalState(estado, ctxActual.crematorioId, ctxActual.accion);
                c.skip_until = Date.now() + SKIP_MS;
                guardarEstado(estado);
            }
        }
    }

    // Registra evento de clic (fire-and-forget) ────────────────
    function registrarClick(ctx, modalAction) {
        try {
            var payload = {
                accion:        ctx.accion,
                modal_action:  modalAction,
                crematorio_id: ctx.crematorioId || null,
                destino_url:   ctx.destino || '',
                pagina_origen: location.href
            };
            var url = BASE_URL + '/registrar-clic-outbound.php';
            // sendBeacon si está disponible (más confiable en navigation/unload), si no fetch
            if (navigator.sendBeacon) {
                var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                fetch(url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload),
                    keepalive: true
                });
            }
        } catch (e) { /* nada */ }
    }

    // Detección rápida de mobile (para el fallback de tel: en desktop)
    function esMobile() {
        return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    }

    // Redirige al destino (con UTMs si aplica) ─────────────────
    function redirigirAlDestino(destino, accion) {
        if (!destino) return;
        // Solo agregamos UTMs a URLs HTTP/HTTPS (no a tel:, mailto:, wa.me — wa.me sí es http pero no podemos trackear)
        if (/^https?:\/\//i.test(destino) && accion !== 'wa') {
            try {
                var u = new URL(destino);
                // Política comercial: UTMs siempre con los mismos valores fijos.
                // El canal/contexto se trackea por separado en channel_type + pagina_origen del lead.
                if (!u.searchParams.has('utm_source'))   u.searchParams.set('utm_source',   'crematoriosdemascotas.com');
                if (!u.searchParams.has('utm_medium'))   u.searchParams.set('utm_medium',   'referral');
                if (!u.searchParams.has('utm_campaign')) u.searchParams.set('utm_campaign', 'directorio');
                u.searchParams.set('cmas_origen', location.pathname);
                destino = u.toString();
            } catch (e) { /* URL inválida, redirige tal cual */ }
        }

        if (accion === 'tel') {
            // tel:... solo lo manejan apps OS (dialer mobile, Skype/FaceTime desktop).
            // En desktop sin handler, no pasa nada → además copiamos el número
            // al portapapeles + toast como fallback útil.
            if (!esMobile()) {
                var numero = destino.replace(/^tel:/i, '').trim();
                if (numero && navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(numero).then(function () {
                        if (window.toast) toast.ok('Número copiado: ' + numero);
                    }).catch(function () { /* clipboard puede fallar por permisos */ });
                }
            }
            // Igual intentamos abrir el handler tel: (mobile lo capta, desktop con app también)
            window.location.href = destino;
        } else {
            // wa.me, maps, web → URLs http(s), abren en NUEVA PESTAÑA siempre.
            // Política: cualquier salida del dominio en nueva pestaña → la sesión
            // queda viva en el sitio, más oportunidades de conversión.
            window.open(destino, '_blank', 'noopener');
        }
    }

    // ─── Interceptor global de clicks ─────────────────────────
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest('[data-lead-capture]');
        if (!el) return;

        var accion  = el.getAttribute('data-lead-capture');
        var destino = el.getAttribute('data-destino') || el.getAttribute('href') || '';
        if (!accion || !destino) return;

        ev.preventDefault();

        var noSkip = el.getAttribute('data-no-skip') === '1';
        var ctx = {
            accion:           accion,
            destino:          destino,
            crematorioId:     el.getAttribute('data-crematorio-id') || null,
            crematorioNombre: el.getAttribute('data-crematorio-nombre') || '',
            crematorioLogo:   el.getAttribute('data-crematorio-logo') || '',
            phoneAgent:       el.getAttribute('data-phone-agent') || '',
            subtitulo:        el.getAttribute('data-subtitulo') || '',
            // Si el trigger es la burbuja (intención explícita de contactarnos),
            // no mostramos "Ir directo" — no hay destino externo "que el usuario quería"
            noSkip:           noSkip,
            // La burbuja se identifica por noSkip=1 (la usa para ocultar "Ir directo").
            // Se trata distinto: no cuenta para el cap, no respeta silencio per-ficha;
            // solo respeta el silencio global post-submit.
            esBurbuja:        noSkip
        };

        // Decide si va modal o directo (cap, silencios, burbuja)
        var estado  = leerEstado();
        var decision = decidirAccion(ctx, estado);

        // Registramos el clic inicial
        registrarClick(ctx, 'click');

        if (decision === 'directo') {
            redirigirAlDestino(destino, accion);
            return;
        }

        // Abrimos modal
        abrirModal(ctx);
    });

    // ─── Cerrar modal (overlay click / X / ESC) ───────────────
    btnsClose.forEach(function (b) {
        b.addEventListener('click', function () { cerrarModal('cancelled'); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('lc-modal--abierto')) {
            cerrarModal('cancelled');
        }
    });

    // ─── Botón "Ir directo sin completar" ─────────────────────
    if (btnSkip) {
        btnSkip.addEventListener('click', function () {
            if (!ctxActual) return;
            // Logueamos el skip
            registrarClick(ctxActual, 'skipped');
            // Silencio per-CANAL 10 min (solo si hay ficha contextual)
            if (ctxActual.crematorioId && !ctxActual.esBurbuja) {
                var estado = leerEstado();
                var c = getCanalState(estado, ctxActual.crematorioId, ctxActual.accion);
                c.skip_until = Date.now() + SKIP_MS;
                guardarEstado(estado);
            }
            // Cierra y redirige
            modal.classList.remove('lc-modal--abierto');
            document.body.classList.remove('lc-modal-abierto');
            redirigirAlDestino(ctxActual.destino, ctxActual.accion);
        });
    }

    // ─── Sync select país → hidden phone_code ─────────────────
    var selPais = form.querySelector('[name="country_code"]');
    var inpPhoneCode = document.getElementById('lc-phone-code');
    if (selPais && inpPhoneCode) {
        selPais.addEventListener('change', function () {
            var opt = selPais.options[selPais.selectedIndex];
            inpPhoneCode.value = opt ? (opt.getAttribute('data-code') || '') : '';
        });
    }

    // ─── Submit del form ──────────────────────────────────────
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        // Validación nativa mínima
        var nombre  = form.elements['nombre'].value.trim();
        var email   = form.elements['email'].value.trim();
        var ciudad  = form.elements['ciudad'].value.trim();
        var wa      = form.elements['whatsapp_number'].value.trim();
        var serv    = form.elements['servicio'].value;
        var tamano  = form.elements['mascota_tamano'].value;

        if (!serv) {
            if (window.toast) toast.error('Elige Perro, Gato u Otro');
            return;
        }
        if (!tamano) {
            if (window.toast) toast.error('Indica el tamaño de la mascota');
            return;
        }
        if (!nombre || !email || !ciudad || !wa) {
            if (window.toast) toast.error('Completa todos los campos obligatorios');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (window.toast) toast.error('Email no válido');
            return;
        }

        // Deshabilitar submit mientras procesa
        var btn = form.querySelector('.lc-btn-primary');
        var btnTextoOrig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Enviando…';

        var fd = new FormData(form);

        fetch(BASE_URL + '/procesar-lead-b2c.php', {
            method: 'POST',
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.ok) {
                // Silencio post-submit: 24h global + 24h per-ficha (si aplica)
                var estado = leerEstado();
                var ahora  = Date.now();
                estado.global_submit_until = ahora + SUBMIT_MS;
                if (ctxActual && ctxActual.crematorioId && !ctxActual.esBurbuja) {
                    var f = getFichaState(estado, ctxActual.crematorioId);
                    f.submit_until = ahora + SUBMIT_MS;
                }
                guardarEstado(estado);

                modal.classList.remove('lc-modal--abierto');
                document.body.classList.remove('lc-modal-abierto');
                if (window.toast) toast.ok('¡Gracias! Te conectamos…');
                redirigirAlDestino(data.destino || ctxActual.destino, ctxActual ? ctxActual.accion : '');
            } else {
                if (window.toast) toast.error((data && data.mensaje) || 'No pudimos enviar. Reintenta.');
                btn.disabled = false;
                btn.innerHTML = btnTextoOrig;
            }
        })
        .catch(function () {
            if (window.toast) toast.error('Error de red. Reintenta.');
            btn.disabled = false;
            btn.innerHTML = btnTextoOrig;
        });
    });

})();
