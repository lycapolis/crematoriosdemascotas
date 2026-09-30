/* ═══════════════════════════════════════════════════════════
   BLOG — modal de imágenes desde banco de fotos o IA
   (admin/blog-editar.php → admin/blog-imagenes-ajax.php)
   ═══════════════════════════════════════════════════════════
   BlogImagenes.abrir({
       destino:   'portada' | 'bloque',
       tab:       'banco' | 'ia',
       contexto:  function () { return { titulo, extracto, keyword, seccion, uso } },
       altsUsados:function () { return ['…'] },
       onElegir:  function (res) { … }   // { file, alt, titulo, caption, origen, credito, etiqueta_ia, avisos }
   })
   El servidor descarga/genera la imagen, pide alt + nombre de archivo a la IA
   (visión) y la pasa por la optimización existente (WebP 1600 + 800).
   ═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var opts = null;
    var ocupado = false;
    var pagina = 1;
    var tokenIa = '';
    var propuesto = false;

    function C() { return window.BLOG_EDITOR || {}; }
    function aviso(tipo, msg) {
        if (window.toast && window.toast[tipo]) window.toast[tipo](msg);
        else alert(msg);
    }
    function iconos() { if (window.lucide) window.lucide.createIcons(); }
    function esc(s) { return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function estado(msg) { $('bimg-estado').textContent = msg || ''; }
    function bloquear(si, msg) {
        ocupado = si;
        $('bimg-modal').classList.toggle('bimg-modal--ocupado', si);
        estado(si ? msg : '');
    }

    function post(accion, datos) {
        var body = Object.assign({ accion: accion }, datos || {});
        return fetch(C().urls.imagenes, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C().csrf },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) throw new Error(d.mensaje || 'Error inesperado');
            return d;
        });
    }

    function contexto() { return (opts && opts.contexto) ? opts.contexto() : {}; }

    // ─── Pestañas ─────────────────────────────────────────
    function tab(nombre) {
        document.querySelectorAll('.bimg-modal__tab').forEach(function (b) {
            var activa = b.dataset.tab === nombre;
            b.classList.toggle('bimg-modal__tab--activa', activa);
            b.setAttribute('aria-selected', activa ? 'true' : 'false');
        });
        document.querySelectorAll('.bimg-modal__panel').forEach(function (p) { p.hidden = p.dataset.panel !== nombre; });
        $('bimg-etiqueta-wrap').hidden = nombre !== 'ia';
        if (!propuesto && (nombre === 'ia' ? !$('bimg-prompt').value.trim() : !$('bimg-q').value.trim())) proponer(nombre === 'banco');
    }

    // ─── Propuesta de búsqueda/escena a partir del artículo ─
    function proponer(buscarDespues) {
        var ctx = contexto();
        if (!ctx.titulo && !ctx.keyword) { estado('Escribe el título del artículo para recibir sugerencias.'); return; }
        propuesto = true;
        bloquear(true, 'Leyendo el artículo para sugerir imágenes…');
        post('proponer', { contexto: ctx }).then(function (d) {
            if (d.consulta && !$('bimg-q').value.trim()) $('bimg-q').value = d.consulta;
            if (d.prompt) $('bimg-prompt').value = d.prompt;
            if (d.aviso) aviso('error', d.aviso);
        }).catch(function (e) { aviso('error', e.message); })
          .finally(function () {
              bloquear(false);
              if (buscarDespues && $('bimg-q').value.trim()) buscar(false);
          });
    }

    // ─── Banco de fotos ───────────────────────────────────
    function buscar(mas) {
        var q = $('bimg-q').value.trim();
        if (!q || ocupado) return;
        var cfg = C().imagenes || {};
        if (!cfg.pexels && !cfg.pixabay) {
            $('bimg-grid').innerHTML = '<p class="bimg-modal__vacio">Faltan las claves PEXELS_API_KEY / PIXABAY_API_KEY en el .env del servidor.</p>';
            return;
        }
        pagina = mas ? pagina + 1 : 1;
        bloquear(true, 'Buscando fotos…');
        post('buscar', { q: q, fuente: $('bimg-fuente').value, orientacion: $('bimg-orientacion').value, pagina: pagina })
            .then(function (d) {
                var grid = $('bimg-grid');
                if (!mas) grid.innerHTML = '';
                if (!d.items.length && !mas) grid.innerHTML = '<p class="bimg-modal__vacio">Sin resultados. Prueba con otras palabras (mejor en inglés).</p>';
                d.items.forEach(function (f) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'bimg-foto';
                    b.title = f.descripcion || '';
                    b.innerHTML = '<img src="' + esc(f.miniatura) + '" alt="" loading="lazy">'
                        + '<span class="bimg-foto__fuente bimg-foto__fuente--' + f.fuente + '">' + (f.fuente === 'pexels' ? 'Pexels' : 'Pixabay') + '</span>'
                        + '<span class="bimg-foto__autor">' + esc(f.autor) + ' · ' + f.ancho + '×' + f.alto + '</span>'
                        + '<span class="bimg-foto__usar"><i data-lucide="check" class="icono"></i> Usar esta</span>';
                    b.addEventListener('click', function () { importar({ fuente: f.fuente, foto: f }, b); });
                    grid.appendChild(b);
                });
                $('bimg-mas-wrap').hidden = !d.items.length || grid.querySelectorAll('.bimg-foto').length >= d.total;
                (d.avisos || []).forEach(function (m) { aviso('error', m); });
                iconos();
            })
            .catch(function (e) { aviso('error', e.message); })
            .finally(function () { bloquear(false); });
    }

    // ─── Generar con IA ───────────────────────────────────
    function generar() {
        if (ocupado) return;
        var prompt = $('bimg-prompt').value.trim();
        if (prompt.length < 10) { aviso('error', 'Describe la escena que quieres generar.'); $('bimg-prompt').focus(); return; }
        bloquear(true, 'Generando la imagen… puede tardar hasta un minuto.');
        post('generar', { prompt: prompt, estilo: $('bimg-estilo').value, aspecto: $('bimg-aspecto').value })
            .then(function (d) {
                tokenIa = d.token;
                $('bimg-preview-img').src = d.preview;
                $('bimg-preview').hidden = false;
            })
            .catch(function (e) { aviso('error', e.message); })
            .finally(function () { bloquear(false); });
    }

    // ─── Importar (IA de alt/nombre + optimización + registro) ─
    function importar(datos, tarjeta) {
        if (ocupado) return;
        if (tarjeta) tarjeta.classList.add('bimg-foto--cargando');
        bloquear(true, 'Optimizando la imagen y generando el texto alternativo…');
        post('importar', Object.assign({
            contexto: contexto(),
            alts_usados: opts.altsUsados ? opts.altsUsados() : [],
            con_logo: $('bimg-logo').checked,
            etiqueta_ia: $('bimg-etiqueta').checked,
            articulo_id: C().id || 0
        }, datos))
            .then(function (d) {
                (d.avisos || []).forEach(function (m) { aviso('error', m); });
                var cb = opts.onElegir;
                bloquear(false);
                cerrar();
                if (cb) cb(d);
            })
            .catch(function (e) { aviso('error', e.message); bloquear(false); })
            .finally(function () { if (tarjeta) tarjeta.classList.remove('bimg-foto--cargando'); });
    }

    // ─── Abrir / cerrar ───────────────────────────────────
    function abrir(o) {
        opts = o || {};
        var esPortada = opts.destino === 'portada';
        $('bimg-destino').textContent = esPortada ? 'Portada del artículo' : 'Imagen dentro del texto';
        $('bimg-aspecto').value = esPortada ? '16:9' : '4:3';
        $('bimg-orientacion').value = 'horizontal';
        $('bimg-preview').hidden = true;
        tokenIa = '';
        propuesto = false;
        $('bimg-prompt').value = '';

        var cfg = C().imagenes || {};
        $('bimg-logo').checked = !!cfg.logo; // marcado por defecto (si existe el archivo del logo)
        $('bimg-logo').disabled = !cfg.logo;
        $('bimg-logo-wrap').title = cfg.logo ? 'Logo pequeño y semitransparente en la esquina inferior derecha' : 'Falta el archivo assets/img/marca/logo-marca-agua.png';

        $('bimg-modal').hidden = false;
        document.body.style.overflow = 'hidden';
        tab(opts.tab === 'ia' ? 'ia' : 'banco');
        iconos();
    }
    function cerrar() {
        if (ocupado) return;
        $('bimg-modal').hidden = true;
        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!$('bimg-modal')) return;
        document.querySelectorAll('#bimg-modal [data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrar); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('bimg-modal').hidden) cerrar(); });
        document.querySelectorAll('.bimg-modal__tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
        $('bimg-form-buscar').addEventListener('submit', function (e) { e.preventDefault(); buscar(false); });
        $('bimg-mas').addEventListener('click', function () { buscar(true); });
        $('bimg-proponer').addEventListener('click', function () { proponer(false); });
        $('bimg-generar').addEventListener('click', generar);
        $('bimg-otra').addEventListener('click', generar);
        $('bimg-usar-ia').addEventListener('click', function () { if (tokenIa) importar({ fuente: 'ia', token: tokenIa }); });
        // Que Editor.js no capture las teclas escritas en el modal
        ['keydown', 'paste', 'cut'].forEach(function (ev) {
            $('bimg-modal').addEventListener(ev, function (e) { if (e.key !== 'Escape') e.stopPropagation(); });
        });
    });

    window.BlogImagenes = { abrir: abrir, cerrar: cerrar };
})();
