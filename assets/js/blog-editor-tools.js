/* ═══════════════════════════════════════════════════════════
   BLOG — bloques personalizados de Editor.js
   ═══════════════════════════════════════════════════════════
   - BedModulo : módulo especial de la biblioteca (CTA, banner, destacado, lead)
   - BedAviso  : caja "Consejo" / "Importante"
   - BedFaq    : preguntas frecuentes (genera schema FAQPage en el público)
   - BedImagen : ImageTool + campo de texto alternativo (alt) obligatorio
   Depende de: window.BLOG_EDITOR.modulos (lista de la biblioteca).
   ═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var ICONOS = {
        modulo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>',
        aviso:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
        faq:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>'
    };
    var TIPOS = {
        ficha_destacada: 'Negocio destacado',
        cta: 'Llamada a la acción',
        banner: 'Banner con imagen',
        lead: 'Captura de leads (WhatsApp)'
    };

    /** Evita que Editor.js interprete Enter/Backspace dentro de campos propios. */
    function aislarTeclas(el) {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab') e.stopPropagation();
        });
        el.addEventListener('paste', function (e) {
            // Pegar como texto plano dentro de los campos de bloques propios
            e.stopPropagation();
            e.preventDefault();
            var txt = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, txt);
        });
    }

    function editable(tag, cls, html, placeholder) {
        var el = document.createElement(tag);
        el.className = cls;
        el.contentEditable = 'true';
        el.innerHTML = html || '';
        if (placeholder) el.setAttribute('data-placeholder', placeholder);
        aislarTeclas(el);
        return el;
    }

    // ─── Módulo especial ─────────────────────────────────────
    function BedModulo(opts) {
        this.data = opts.data || {};
        this.api = opts.api;
    }
    BedModulo.toolbox = { title: 'Módulo especial', icon: ICONOS.modulo };
    BedModulo.isReadOnlySupported = true;
    BedModulo.prototype.render = function () {
        var modulos = (window.BLOG_EDITOR && window.BLOG_EDITOR.modulos) || [];
        var wrap = document.createElement('div');
        wrap.className = 'bed-modulo';
        wrap.contentEditable = 'false';

        var ico = document.createElement('div');
        ico.className = 'bed-modulo__icono';
        ico.innerHTML = ICONOS.modulo;

        var info = document.createElement('div');
        var kicker = document.createElement('div');
        kicker.className = 'bed-modulo__kicker';
        var nombre = document.createElement('div');
        nombre.className = 'bed-modulo__nombre';
        var sel = document.createElement('select');
        sel.innerHTML = '<option value="">— Elegí un módulo —</option>' + modulos.map(function (m) {
            return '<option value="' + m.id + '">' + m.nombre.replace(/</g, '&lt;') + ' (' + (TIPOS[m.tipo] || m.tipo) + ')</option>';
        }).join('');
        sel.value = this.data.modulo_id ? String(this.data.modulo_id) : '';
        var nota = document.createElement('div');
        nota.className = 'bed-modulo__nota';
        nota.textContent = 'Se muestra ya diseñado en esta posición del artículo. Se edita desde "Módulos especiales".';

        var self = this;
        function pintar() {
            var m = modulos.find(function (x) { return String(x.id) === sel.value; });
            kicker.textContent = m ? (TIPOS[m.tipo] || 'Módulo') : 'Módulo especial';
            nombre.textContent = m ? m.nombre : 'Sin módulo elegido';
            self.data.modulo_id = m ? m.id : null;
        }
        sel.addEventListener('change', function () { pintar(); document.dispatchEvent(new CustomEvent('blog:cambio')); });
        pintar();

        info.appendChild(kicker);
        info.appendChild(nombre);
        info.appendChild(sel);
        info.appendChild(nota);
        wrap.appendChild(ico);
        wrap.appendChild(info);
        return wrap;
    };
    BedModulo.prototype.save = function () { return { modulo_id: this.data.modulo_id ? Number(this.data.modulo_id) : null }; };
    BedModulo.prototype.validate = function (d) { return !!d.modulo_id; };

    // ─── Aviso (Consejo / Importante) ────────────────────────
    function BedAviso(opts) {
        this.data = Object.assign({ tipo: 'consejo', titulo: '', text: '' }, opts.data || {});
    }
    BedAviso.toolbox = { title: 'Consejo / Importante', icon: ICONOS.aviso };
    BedAviso.enableLineBreaks = true;
    BedAviso.sanitize = { titulo: false, text: { b: true, i: true, a: { href: true }, br: true, mark: true } };
    BedAviso.prototype.render = function () {
        var self = this;
        var wrap = document.createElement('div');
        wrap.className = 'bed-aviso bed-aviso--' + this.data.tipo;
        var cab = document.createElement('div');
        cab.className = 'bed-aviso__cabecera';
        var sel = document.createElement('select');
        sel.innerHTML = '<option value="consejo">💡 Consejo</option><option value="importante">⚠️ Importante</option>';
        sel.value = this.data.tipo;
        sel.addEventListener('change', function () {
            self.data.tipo = sel.value;
            wrap.className = 'bed-aviso bed-aviso--' + sel.value;
        });
        this._titulo = editable('div', 'bed-aviso__titulo', this.data.titulo, 'Título (opcional)');
        this._texto  = editable('div', 'bed-aviso__texto', this.data.text, 'Escribe el consejo o la advertencia…');
        cab.appendChild(sel);
        cab.appendChild(this._titulo);
        wrap.appendChild(cab);
        wrap.appendChild(this._texto);
        return wrap;
    };
    BedAviso.prototype.save = function () {
        return { tipo: this.data.tipo, titulo: this._titulo.textContent.trim(), text: this._texto.innerHTML.trim() };
    };

    // ─── Preguntas frecuentes ────────────────────────────────
    function BedFaq(opts) {
        var d = opts.data || {};
        this.data = { titulo: d.titulo || 'Preguntas frecuentes', items: (d.items && d.items.length) ? d.items : [{ pregunta: '', respuesta: '' }] };
    }
    BedFaq.toolbox = { title: 'Preguntas frecuentes', icon: ICONOS.faq };
    BedFaq.enableLineBreaks = true;
    BedFaq.prototype.render = function () {
        var self = this;
        var wrap = document.createElement('div');
        wrap.className = 'bed-faq';
        this._titulo = editable('div', 'bed-faq__titulo', this.data.titulo, 'Preguntas frecuentes');
        wrap.appendChild(this._titulo);
        this._lista = document.createElement('div');
        wrap.appendChild(this._lista);
        this.data.items.forEach(function (it) { self._agregarItem(it); });
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bed-faq__agregar';
        btn.textContent = '+ Añadir pregunta';
        btn.addEventListener('click', function () { var el = self._agregarItem({ pregunta: '', respuesta: '' }); el.querySelector('.bed-faq__pregunta').focus(); });
        wrap.appendChild(btn);
        return wrap;
    };
    BedFaq.prototype._agregarItem = function (it) {
        var self = this;
        var el = document.createElement('div');
        el.className = 'bed-faq__item';
        el.appendChild(editable('div', 'bed-faq__pregunta', it.pregunta, 'Escribe la pregunta…'));
        el.appendChild(editable('div', 'bed-faq__respuesta', it.respuesta, 'Escribe la respuesta…'));
        var quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'bed-faq__quitar';
        quitar.title = 'Quitar pregunta';
        quitar.innerHTML = '&times;';
        quitar.addEventListener('click', function () { if (self._lista.children.length > 1) el.remove(); });
        el.appendChild(quitar);
        this._lista.appendChild(el);
        return el;
    };
    BedFaq.prototype.save = function () {
        return {
            titulo: this._titulo.textContent.trim(),
            items: Array.prototype.map.call(this._lista.children, function (el) {
                return {
                    pregunta: el.querySelector('.bed-faq__pregunta').textContent.trim(),
                    respuesta: el.querySelector('.bed-faq__respuesta').innerHTML.trim()
                };
            })
        };
    };

    // ─── Imagen con texto alternativo ────────────────────────
    function crearBedImagen() {
        if (typeof window.ImageTool === 'undefined') return null;
        class BedImagen extends window.ImageTool {
            constructor(opts) {
                super(opts);
                this._alt = (opts.data && opts.data.alt) || '';
                // Procedencia de fotos de banco / IA (crédito y etiqueta en el pie público)
                this._extra = {};
                this._rutaOriginal = (opts.data && opts.data.file) ? opts.data.file.ruta : undefined;
                var self = this;
                ['origen', 'credito', 'etiqueta_ia'].forEach(function (k) {
                    if (opts.data && opts.data[k] !== undefined && opts.data[k] !== null) self._extra[k] = opts.data[k];
                });
            }
            render() {
                var wrap = super.render();
                var inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'bed-alt' + (this._alt ? '' : ' bed-alt--vacio');
                inp.placeholder = 'Texto alternativo: describe la imagen (lo lee Google y los lectores de pantalla)';
                inp.value = this._alt;
                var self = this;
                inp.addEventListener('input', function () {
                    self._alt = inp.value;
                    inp.classList.toggle('bed-alt--vacio', !inp.value.trim());
                    document.dispatchEvent(new CustomEvent('blog:cambio'));
                });
                ['keydown', 'paste', 'cut'].forEach(function (ev) { inp.addEventListener(ev, function (e) { e.stopPropagation(); }); });
                wrap.appendChild(inp);
                return wrap;
            }
            save(el) {
                var d = super.save(el);
                d.alt = (this._alt || '').trim();
                // Si se reemplaza la imagen desde el propio bloque, deja de ser la foto acreditada
                var ext = this._extra || {};
                if (ext.origen && d.file && this._rutaOriginal !== undefined && d.file.ruta !== this._rutaOriginal) ext = {};
                Object.keys(ext).forEach(function (k) { d[k] = ext[k]; });
                return d;
            }
        }
        return BedImagen;
    }

    window.BlogEditorTools = {
        BedModulo: BedModulo,
        BedAviso: BedAviso,
        BedFaq: BedFaq,
        crearBedImagen: crearBedImagen,
        TIPOS_MODULO: TIPOS
    };
})();
