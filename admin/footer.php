    <script src="<?php echo $base_url; ?>/assets/js/lucide.min.js"></script>
    <script>lucide.createIcons();</script>

    <!-- Tom Select — enhance todos los <select.field__select--enhanced>
         data-ts-search="off"  → desactiva el input de búsqueda (listas fijas)
         data-ts-clear="on"    → habilita el botón X de clear (por default está off, "Todos/Todas" ya hace de clear)
         data-ts-autosubmit="1" → submit del form al cambiar (alternativa al onchange="this.form.submit()") -->
    <script src="<?php echo $base_url; ?>/assets/librerias/tom-select/tom-select.complete.min.js"></script>
    <script>
    (function() {
        if (typeof TomSelect === 'undefined') return;

        // Inicializa UN select. data-ts-portal="off" → dropdown dentro del wrapper
        // (para editores dinámicos que se re-renderizan: evita paneles huérfanos
        // en <body> al reemplazar innerHTML). Default: portal en body (escapa
        // overflow:hidden de cards).
        function tsInitOne(sel) {
            if (!sel || sel.tomselect) return;
            var opts = {
                create: false,
                allowEmptyOption: true,
                plugins: sel.dataset.tsClear === 'on' ? ['clear_button'] : [],
                placeholder: sel.dataset.placeholder || sel.querySelector('option[value=""]')?.textContent || '',
                onChange: function() {
                    if (sel.dataset.tsAutosubmit === '1') {
                        var f = sel.closest('form');
                        if (f) f.submit();
                    }
                }
            };
            if (sel.dataset.tsPortal !== 'off') opts.dropdownParent = 'body';
            if (sel.dataset.tsSearch === 'off') opts.controlInput = null;
            new TomSelect(sel, opts);
        }

        // Enhance todos los selects sin inicializar dentro de un scope (default: documento)
        window.tsEnhanceScope = function(scope) {
            (scope || document).querySelectorAll('select.field__select--enhanced').forEach(tsInitOne);
        };
        // Destruye instancias dentro de un scope ANTES de reemplazar su innerHTML
        // (los editores dinámicos re-renderizan; sin esto quedan listeners/paneles colgados).
        window.tsDestroyScope = function(scope) {
            if (!scope) return;
            scope.querySelectorAll('select').forEach(function(sel) {
                if (sel.tomselect) { try { sel.tomselect.destroy(); } catch (e) {} }
            });
        };

        window.tsEnhanceScope(document);
    })();
    </script>
    <!-- ═══ FASE 6 — Feedback: Notyf (toasts) + Micromodal (confirmaciones) ═══ -->
    <script src="<?php echo $base_url; ?>/assets/librerias/notyf/notyf.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/librerias/micromodal/micromodal.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/js/feedback.js"></script>

    <!-- Template único del modal de confirmación (Micromodal lo reusa) -->
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

    <!-- Puente flash server→toast: convierte ?ok/?error/?img_ok/... en toast.
         Si la página renderiza su propio banner PHP de flash (marcado con
         [data-flash-php], ej: editar-ficha-negocio sin refactorizar aún),
         solo limpia la URL y no duplica. -->
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

        // Siempre limpiar los params de la URL (haya o no toast)
        var changed = false;
        okParams.concat(errParams).forEach(function(p) {
            if (url.searchParams.has(p)) { url.searchParams.delete(p); changed = true; }
        });
        if (changed) window.history.replaceState({}, '', url.toString());
    })();
    </script>
    <script>
    // Toggle de visibilidad de un input password adyacente
    function adminTogglePassword(btn) {
        var input = btn.parentElement.querySelector('input[type="password"], input[data-pwd]');
        if (!input) return;
        var icon = btn.querySelector('[data-lucide]');
        if (input.type === 'password') {
            input.type = 'text';
            input.setAttribute('data-pwd', '1');
            if (icon) icon.setAttribute('data-lucide', 'eye-off');
            btn.setAttribute('aria-label', 'Ocultar password');
        } else {
            input.type = 'password';
            input.setAttribute('data-pwd', '1');
            if (icon) icon.setAttribute('data-lucide', 'eye');
            btn.setAttribute('aria-label', 'Mostrar password');
        }
        if (window.lucide) lucide.createIcons();
    }
    </script>
</body>
</html>
