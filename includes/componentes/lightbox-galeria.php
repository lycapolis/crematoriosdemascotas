<?php
/**
 * Lightbox de galería compartido (con borrado inline opcional).
 *
 * Patrón espejado del `res-lb` de resenas.php (probado y aprobado), extraído
 * a un partial único para que admin lo reuse sin duplicar código.
 *
 * ── Cómo se usa ─────────────────────────────────────────────────────────────
 * En cada miniatura clickeable, pon un disparador con estos data-attributes:
 *
 *   <button type="button"
 *           data-lbg-src="URL_imagen"
 *           data-lbg-group="clave-de-galeria"   (agrupa: prev/next navegan dentro del grupo)
 *           data-lbg-alt="texto alternativo"     (opcional → caption)
 *           data-lbg-nombre="archivo.jpg"        (opcional → línea monoespaciada)
 *           data-lbg-id="123"                    (opcional → habilita borrado AJAX)
 *           data-lbg-del="1"                     (opcional → muestra botón Eliminar)
 *           data-lbg-card="img-card-123">        (opcional → id del nodo a quitar al borrar;
 *                                                 si falta, usa el closest [data-lbg-card])
 *     <img ...>
 *   </button>
 *
 * El borrado llama a /admin/imagen-eliminar-ajax.php (imagen_id) y, si sale OK,
 * quita la card del DOM, re-lee el grupo y avanza/cierra. Además dispara un
 * CustomEvent `lbg:deleted` (detail.id) en document para que la página sincronice
 * otras vistas de la misma imagen sin recargar.
 *
 * Incluí este partial UNA vez por página, antes del footer.
 * Requiere feedback.js (confirmar / toast), ya cargado en el footer del admin.
 */
if (!defined('BASE_URL')) { return; }
?>
<div id="lbg-overlay" style="display:none; position:fixed; inset:0; z-index:9000; background:rgba(28,20,12,.86); align-items:center; justify-content:center; padding:var(--espacio-cuatro); flex-direction:column; gap:var(--espacio-tres);">
    <button type="button" id="lbg-close" aria-label="Cerrar (Esc)"
            style="position:absolute; top:var(--espacio-cuatro); right:var(--espacio-cuatro); width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
            onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <i data-lucide="x" class="icono" style="width:20px; height:20px;"></i>
    </button>
    <div style="position:relative; width:100%; max-width:1100px; flex:1; display:flex; align-items:center; justify-content:center; min-height:0;">
        <button type="button" id="lbg-prev" aria-label="Anterior (←)"
                style="position:absolute; left:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-left" class="icono" style="width:24px; height:24px;"></i>
        </button>
        <img id="lbg-img" src="" alt=""
             style="max-width:100%; max-height:78vh; border-radius:var(--admin-r-md); box-shadow:0 8px 40px rgba(0,0,0,.4); object-fit:contain;">
        <button type="button" id="lbg-next" aria-label="Siguiente (→)"
                style="position:absolute; right:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-right" class="icono" style="width:24px; height:24px;"></i>
        </button>
    </div>
    <div style="display:flex; flex-direction:column; align-items:center; gap:.4rem; text-align:center;">
        <p id="lbg-caption" style="color:rgba(255,255,255,.92); margin:0; font-size:var(--admin-body-sm); max-width:80vw; line-height:1.4;"></p>
        <p id="lbg-nombre" style="color:rgba(255,255,255,.6); margin:0; font-family:monospace; font-size:var(--admin-body-sm);"></p>
        <p id="lbg-counter" style="color:rgba(255,255,255,.55); margin:0; font-size:var(--admin-kicker); font-variant-numeric:tabular-nums; letter-spacing:.04em; text-transform:uppercase;"></p>
        <button type="button" id="lbg-delete"
                style="display:none; margin-top:.4rem; align-items:center; gap:.4rem; padding:.55rem 1rem; background:rgba(122,45,29,.92); color:#fff; border:0; border-radius:var(--admin-r-sm); cursor:pointer; font-size:var(--admin-body-sm); font-weight:600; transition:background .15s, transform .12s; box-shadow:0 2px 8px rgba(0,0,0,.25);"
                onmouseover="this.style.background='rgba(122,45,29,1)'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.background='rgba(122,45,29,.92)'; this.style.transform='';">
            <i data-lucide="trash-2" class="icono" style="width:16px; height:16px;"></i>
            <span>Eliminar imagen</span>
        </button>
    </div>
</div>

<script>
// ── Lightbox de galería compartido (IIFE aislado: resiliente a errores JS de la página) ──
(function() {
    var overlay = document.getElementById('lbg-overlay');
    if (!overlay) return;

    var imgEl   = document.getElementById('lbg-img');
    var capEl   = document.getElementById('lbg-caption');
    var nameEl  = document.getElementById('lbg-nombre');
    var cntEl   = document.getElementById('lbg-counter');
    var closeB  = document.getElementById('lbg-close');
    var prevB   = document.getElementById('lbg-prev');
    var nextB   = document.getElementById('lbg-next');
    var delB    = document.getElementById('lbg-delete');
    var AJAX    = '<?php echo BASE_URL; ?>/admin/imagen-eliminar-ajax.php';

    var items = [];
    var idx   = 0;

    function leer(el) {
        return {
            el:     el,
            id:     el.dataset.lbgId ? parseInt(el.dataset.lbgId, 10) : null,
            src:    el.dataset.lbgSrc,
            alt:    el.dataset.lbgAlt || '',
            nombre: el.dataset.lbgNombre || '',
            del:    el.dataset.lbgDel === '1',
            cardId: el.dataset.lbgCard || ''
        };
    }

    function collect(group) {
        var sel = '[data-lbg-src][data-lbg-group="' + (window.CSS && CSS.escape ? CSS.escape(group) : group) + '"]';
        return Array.prototype.map.call(document.querySelectorAll(sel), leer);
    }

    function render() {
        if (!items.length) { close(); return; }
        if (idx >= items.length) idx = items.length - 1;
        if (idx < 0) idx = 0;
        var it = items[idx];
        imgEl.src = it.src;
        imgEl.alt = it.alt;
        capEl.textContent  = it.alt;
        capEl.style.display = it.alt ? '' : 'none';
        nameEl.textContent = it.nombre;
        nameEl.style.display = it.nombre ? '' : 'none';
        cntEl.textContent  = items.length > 1 ? (idx + 1) + ' / ' + items.length : '';
        // Carrusel circular: con 2+ imágenes ambas flechas siempre disponibles (envuelven).
        prevB.style.visibility = items.length > 1 ? 'visible' : 'hidden';
        nextB.style.visibility = items.length > 1 ? 'visible' : 'hidden';
        delB.style.display = (it.del && it.id) ? 'inline-flex' : 'none';
    }

    function open(group, startIdx) {
        items = collect(group);
        if (!items.length) return;
        idx = Math.max(0, Math.min(startIdx | 0, items.length - 1));
        render();
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (window.lucide) lucide.createIcons();
    }

    function close() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    function prev() { if (items.length) { idx = (idx - 1 + items.length) % items.length; render(); } }
    function next() { if (items.length) { idx = (idx + 1) % items.length; render(); } }

    function eliminarActual() {
        var it = items[idx];
        if (!it || !it.id || !it.del) return;

        confirmar({
            titulo: 'Eliminar imagen',
            mensaje: 'Se borra el archivo y la fila en BD (irreversible).<br><br>¿Eliminar esta imagen?',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () { proceder(); }
        });

        function proceder() {
            delB.disabled = true;
            var span = delB.querySelector('span');
            var prevTxt = span ? span.textContent : '';
            if (span) span.textContent = 'Eliminando…';

            var body = new URLSearchParams({ imagen_id: it.id });
            fetch(AJAX, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                delB.disabled = false;
                if (span) span.textContent = prevTxt || 'Eliminar imagen';
                if (!data.ok) {
                    toast.error(data.error || data.mensaje || 'No se pudo eliminar');
                    return;
                }
                // Quitar la card del DOM (id explícito o closest [data-lbg-card])
                var card = it.cardId ? document.getElementById(it.cardId)
                                     : (it.el.closest ? it.el.closest('[data-lbg-card]') : null);
                if (card) {
                    card.style.transition = 'opacity .2s';
                    card.style.opacity = '0';
                    setTimeout(function() { card.remove(); }, 200);
                }
                // Avisar a la página para que sincronice otras vistas de la misma imagen
                document.dispatchEvent(new CustomEvent('lbg:deleted', { detail: { id: it.id } }));
                toast.ok('Imagen eliminada');
                // Re-leer el grupo y avanzar / cerrar
                var grp = it.el.dataset.lbgGroup;
                setTimeout(function() {
                    items = collect(grp);
                    if (!items.length) { close(); return; }
                    render();
                }, 210);
            })
            .catch(function() {
                delB.disabled = false;
                if (span) span.textContent = prevTxt || 'Eliminar imagen';
                toast.error('Error de conexión');
            });
        }
    }

    // Delegación: funciona aunque los thumbs se agreguen/re-rendericen después.
    document.addEventListener('click', function(e) {
        var trg = e.target.closest ? e.target.closest('[data-lbg-src]') : null;
        if (!trg) return;
        e.preventDefault();
        var grp = trg.dataset.lbgGroup || '_';
        var lista = collect(grp);
        var pos = lista.findIndex(function(x) { return x.el === trg; });
        open(grp, pos < 0 ? 0 : pos);
    });

    closeB.addEventListener('click', close);
    prevB.addEventListener('click', prev);
    nextB.addEventListener('click', next);
    delB.addEventListener('click', eliminarActual);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function(e) {
        if (overlay.style.display !== 'flex') return;
        if (e.key === 'Escape')     close();
        if (e.key === 'ArrowLeft')  prev();
        if (e.key === 'ArrowRight') next();
    });
})();
</script>
