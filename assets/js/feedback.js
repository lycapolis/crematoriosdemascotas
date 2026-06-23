/* ═══════════════════════════════════════════════════════════
   FEEDBACK — wrapper API sobre Notyf + Micromodal
   ═══════════════════════════════════════════════════════════
   Capa fina para que los call sites no toquen las librerías
   directo. Si algún día cambiamos de librería, se toca solo
   este archivo.

   API pública:
     toast.ok(msg)      → notificación de éxito
     toast.error(msg)   → notificación de error
     toast.info(msg)    → notificación neutra
     confirmar({ titulo, mensaje, textoOK, peligroso, onOK, onCancel })
                        → modal de confirmación (reemplaza confirm())

   Degradación: si Notyf/Micromodal no cargaron, cae a
   alert()/confirm() nativos para no romper nada.
   ═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    // ── Notyf ──────────────────────────────────────────────
    // Colores fijos = paleta cálida del proyecto (Notyf aplica
    // el background inline, así que debe configurarse acá, no
    // solo en CSS). Verde éxito #3F5A2C, rojo #C4695B (--color-siete).
    var notyf = null;
    if (typeof Notyf !== 'undefined') {
        notyf = new Notyf({
            duration: 4000,
            ripple: true,
            // Sin botón de cerrar nativo (se veía como artefacto a la derecha).
            // El cierre se hace clickeando el toast — ver bindCerrar().
            dismissible: false,
            position: { x: 'right', y: 'bottom' },
            types: [
                { type: 'success', background: '#3F5A2C' },
                { type: 'error',   background: '#C4695B', duration: 6000 },
                { type: 'info',    background: '#7A3D1D', icon: false }
            ]
        });
    }

    // Hace que un clic en cualquier parte del toast lo cierre.
    function bindCerrar(notification) {
        if (notification && typeof notification.on === 'function') {
            notification.on('click', function () { notyf.dismiss(notification); });
        }
        return notification;
    }

    window.toast = {
        ok: function (msg) {
            if (notyf) { bindCerrar(notyf.success(String(msg))); }
            else { try { alert(msg); } catch (e) {} }
        },
        error: function (msg) {
            if (notyf) { bindCerrar(notyf.error(String(msg))); }
            else { try { alert(msg); } catch (e) {} }
        },
        info: function (msg) {
            if (notyf) { bindCerrar(notyf.open({ type: 'info', message: String(msg) })); }
            else { try { alert(msg); } catch (e) {} }
        }
    };

    // ── Micromodal: confirmar() ────────────────────────────
    // opciones = {
    //   titulo:      string  (default "Confirmar acción")
    //   mensaje:     string|HTML
    //   textoOK:     string  (default "Confirmar")
    //   textoCancelar: string (default "Cancelar")
    //   peligroso:   bool    (botón rojo + icono alerta)
    //   onOK():      callback al confirmar
    //   onCancel():  callback al cancelar/cerrar
    // }
    window.confirmar = function (opciones) {
        var o = opciones || {};
        var modal = document.getElementById('modal-confirmar');

        // Degradación a confirm() nativo
        if (!modal || typeof MicroModal === 'undefined') {
            var txt = (o.titulo ? o.titulo + '\n\n' : '') +
                      (o.mensaje ? String(o.mensaje).replace(/<[^>]+>/g, '') : '¿Confirmar?');
            if (window.confirm(txt)) { if (o.onOK) o.onOK(); }
            else { if (o.onCancel) o.onCancel(); }
            return;
        }

        var elTitle   = modal.querySelector('#modal-confirmar-title');
        var elContent = modal.querySelector('#modal-confirmar-content');
        var elIcono   = modal.querySelector('#modal-confirmar-icono');
        var okBtn     = modal.querySelector('#modal-confirmar-ok');
        var cancelBtn = modal.querySelector('#modal-confirmar-cancel');

        elTitle.textContent   = o.titulo || 'Confirmar acción';
        elContent.innerHTML   = o.mensaje || '';
        okBtn.textContent     = o.textoOK || 'Confirmar';
        cancelBtn.textContent = o.textoCancelar || 'Cancelar';

        var peligroso = !!o.peligroso;
        okBtn.className = 'boton ' + (peligroso ? 'uno boton--peligro' : 'uno');
        elIcono.className = 'modal__icono' + (peligroso ? ' modal__icono--peligro' : '');
        elIcono.innerHTML = '<i data-lucide="' + (peligroso ? 'alert-triangle' : 'help-circle') +
                            '" class="icono"></i>';
        if (window.lucide) lucide.createIcons();

        // Reemplazar el botón OK para limpiar listeners previos
        var okNuevo = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(okNuevo, okBtn);

        var confirmado = false;
        okNuevo.addEventListener('click', function () {
            confirmado = true;
            MicroModal.close('modal-confirmar');
        });

        MicroModal.show('modal-confirmar', {
            disableScroll: true,
            awaitCloseAnimation: true,
            onClose: function () {
                if (confirmado) { if (o.onOK) o.onOK(); }
                else { if (o.onCancel) o.onCancel(); }
            }
        });
    };

    // ── Helpers para markup (reemplazan onclick="return confirm()") ──
    // Link con confirmación:
    //   <a href="..." onclick="return confirmarLink(this,{mensaje:'…',peligroso:true})">
    window.confirmarLink = function (a, opciones) {
        var o = opciones || {};
        var destino = a.href;
        o.onOK = function () { window.location.href = destino; };
        window.confirmar(o);
        return false; // cancela la navegación default; onOK la dispara
    };

    // Form con confirmación antes de enviar:
    //   <form onsubmit="return confirmarForm(this,{mensaje:'…',peligroso:true})">
    window.confirmarForm = function (form, opciones) {
        var o = opciones || {};
        o.onOK = function () { form.submit(); };
        window.confirmar(o);
        return false; // cancela el submit default; onOK lo dispara
    };
})();
