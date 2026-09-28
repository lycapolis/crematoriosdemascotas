/* ═══════════════════════════════════════════════════════════
   BLOG — editor de artículo (admin/blog-editar.php)
   ═══════════════════════════════════════════════════════════
   - Editor.js con bloques propios (blog-editor-tools.js)
   - Paleta de bloques/módulos: arrastrar al lienzo o a la Estructura, o "+"
   - Estructura: lista de bloques reordenable (drag, subir/bajar, borrar)
   - Pegado limpio desde Google Docs / Word (negritas, cursivas, imágenes)
   - Portada con drag & drop + alt obligatorio para publicar
   - Guardado AJAX (Ctrl+S), vista previa, copia local de seguridad
   - Análisis SEO en vivo + vista previa de Google
   ═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var C = window.BLOG_EDITOR;
    var T = window.BlogEditorTools;
    var $ = function (id) { return document.getElementById(id); };
    var editor = null;
    var sucio = false;
    var editorListo = false; // Editor.js dispara onChange al cargar: se ignora hasta estar listo
    var guardando = false;
    var portada = { ruta: C.portada.ruta || '', url: C.portada.url || '' };
    var tsTax = {};
    var tsCtx = {};
    var CLAVE_LOCAL = 'blog-borrador-' + (C.id || 'nuevo');

    function aviso(tipo, msg) {
        if (window.toast && window.toast[tipo]) window.toast[tipo](msg);
        else alert(msg);
    }
    function debounce(fn, ms) {
        var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); };
    }
    function slugificar(txt) {
        return (txt || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase().replace(/ñ/g, 'n').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180);
    }
    function textoPlano(el) { return el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : ''; }
    function iconos() { if (window.lucide) window.lucide.createIcons(); }
    function autoAltura(ta) { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }

    // ═══════════════════════════════════════════════════════
    // EDITOR.JS
    // ═══════════════════════════════════════════════════════
    var i18n = {
        messages: {
            ui: {
                blockTunes: { toggler: { 'Click to tune': 'Opciones del bloque', 'or drag to move': 'o arrastra para mover' } },
                inlineToolbar: { converter: { 'Convert to': 'Convertir en' } },
                toolbar: { toolbox: { Add: 'Añadir bloque' } },
                popover: { Filter: 'Buscar', 'Nothing found': 'Sin resultados', 'Convert to': 'Convertir en' }
            },
            toolNames: {
                Text: 'Párrafo', Heading: 'Subtítulo', 'Unordered List': 'Lista con viñetas', 'Ordered List': 'Lista numerada',
                Checklist: 'Lista de verificación', List: 'Lista', Quote: 'Cita', Delimiter: 'Separador', Table: 'Tabla',
                Image: 'Imagen', Link: 'Enlace', Bold: 'Negrita', Italic: 'Cursiva', Marker: 'Resaltar'
            },
            tools: {
                link: { 'Add a link': 'Pega o escribe un enlace' },
                header: { 'Heading 2': 'Subtítulo H2', 'Heading 3': 'Subtítulo H3', 'Heading 4': 'Subtítulo H4' },
                list: { Unordered: 'Viñetas', Ordered: 'Numerada', Checklist: 'Verificación', 'Start with': 'Empezar en', 'Counter type': 'Tipo de numeración' },
                image: { Caption: 'Pie de foto', 'Select an Image': 'Elegir imagen', 'With border': 'Con borde', 'Stretch image': 'Más ancha', 'With background': 'Con fondo' },
                quote: { 'Align Left': 'Alinear a la izquierda', 'Align Center': 'Centrar' },
                table: { 'With headings': 'Con encabezados', 'Without headings': 'Sin encabezados', 'Add column to left': 'Añadir columna a la izquierda', 'Add column to right': 'Añadir columna a la derecha', 'Delete column': 'Eliminar columna', 'Add row above': 'Añadir fila arriba', 'Add row below': 'Añadir fila abajo', 'Delete row': 'Eliminar fila' },
                stub: { 'The block can not be displayed correctly.': 'Este bloque no se puede mostrar.' }
            },
            blockTunes: {
                delete: { Delete: 'Eliminar', 'Click to delete': 'Clic para confirmar' },
                moveUp: { 'Move up': 'Subir' },
                moveDown: { 'Move down': 'Bajar' }
            }
        }
    };

    function crearEditor() {
        var Imagen = T.crearBedImagen() || window.ImageTool;
        var endpoint = C.urls.subir + '?articulo_id=' + (C.id || 0);
        editor = new window.EditorJS({
            holder: 'editorjs',
            data: (C.contenido && C.contenido.blocks) ? C.contenido : { blocks: [] },
            placeholder: 'Pega aquí el texto del artículo (desde Google Docs o Word) o empieza a escribir…',
            inlineToolbar: ['bold', 'italic', 'link', 'marker'],
            i18n: i18n,
            tools: {
                header: { class: window.Header, inlineToolbar: ['italic', 'link', 'marker'], shortcut: 'CMD+SHIFT+H',
                          config: { levels: [2, 3, 4], defaultLevel: 2, placeholder: 'Escribe el subtítulo' } },
                list: { class: window.EditorjsList, inlineToolbar: true, config: { defaultStyle: 'unordered' } },
                image: { class: Imagen, config: {
                    endpoints: { byFile: endpoint, byUrl: endpoint },
                    field: 'image',
                    types: 'image/jpeg,image/png,image/webp,image/gif',
                    additionalRequestHeaders: { 'X-CSRF-Token': C.csrf },
                    captionPlaceholder: 'Pie de foto (opcional)',
                    buttonContent: 'Elegir imagen o arrastrarla aquí'
                } },
                quote: { class: window.Quote, inlineToolbar: true, config: { quotePlaceholder: 'Texto de la cita', captionPlaceholder: 'Autor (opcional)' } },
                delimiter: window.Delimiter,
                table: { class: window.Table, inlineToolbar: true, config: { rows: 3, cols: 3, withHeadings: true } },
                embed: { class: window.Embed, config: { services: { youtube: true, vimeo: true } } },
                marker: { class: window.Marker },
                aviso: { class: T.BedAviso },
                faq: { class: T.BedFaq },
                modulo: { class: T.BedModulo }
            },
            onReady: function () {
                if (window.Undo) { try { new window.Undo({ editor: editor }); } catch (e) {} }
                refrescarEstructura();
                analizarSeo();
                revisarCopiaLocal();
                setTimeout(function () { editorListo = true; }, 800);
            },
            onChange: function () {
                if (!editorListo) { refrescoDiferido(); return; }
                marcarSucio(); refrescoDiferido();
            }
        });
    }

    // ─── Pegado limpio (Google Docs / Word) ──────────────────
    function desenvolver(el) {
        var p = el.parentNode; if (!p) return;
        while (el.firstChild) p.insertBefore(el.firstChild, el);
        p.removeChild(el);
    }
    function limpiarHtmlPegado(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll('style, meta, link, script, title, o\\:p').forEach(function (n) { n.remove(); });
        doc.querySelectorAll('b[id^="docs-internal-guid"]').forEach(desenvolver);
        // Negritas/cursivas de Google Docs vienen como <span style="font-weight:700">
        doc.querySelectorAll('span').forEach(function (s) {
            var st = s.getAttribute('style') || '';
            var negrita = /font-weight:\s*(bold|[6-9]00)/i.test(st);
            var cursiva = /font-style:\s*italic/i.test(st);
            var frag = doc.createDocumentFragment();
            while (s.firstChild) frag.appendChild(s.firstChild);
            var nodo = frag;
            if (cursiva) { var i = doc.createElement('i'); i.appendChild(nodo); nodo = i; }
            if (negrita) { var b = doc.createElement('b'); b.appendChild(nodo); nodo = b; }
            s.parentNode.replaceChild(nodo, s);
        });
        doc.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(function (h) { h.querySelectorAll('b,strong').forEach(desenvolver); });
        doc.querySelectorAll('li > p').forEach(desenvolver);
        // Las imágenes dentro de párrafos pasan a ser bloques propios
        doc.querySelectorAll('p img, h1 img, h2 img, h3 img, li img').forEach(function (img) {
            var bloque = img.closest('p, h1, h2, h3, li');
            if (bloque && bloque.parentNode) bloque.parentNode.insertBefore(img, bloque.nextSibling);
        });
        doc.querySelectorAll('p').forEach(function (p) { if (!p.textContent.trim() && !p.querySelector('img')) p.remove(); });
        return doc.body.innerHTML;
    }
    function engancharPegado() {
        var holder = $('editorjs');
        holder.addEventListener('paste', function (e) {
            if (e.__blogLimpio) return;
            if (e.target.closest && e.target.closest('.bed-aviso, .bed-faq, .bed-alt, .bed-modulo')) return;
            var dt = e.clipboardData;
            if (!dt || Array.prototype.indexOf.call(dt.types || [], 'application/x-editor-js') !== -1) return;
            var html = dt.getData('text/html');
            if (!html) return;
            var limpio = limpiarHtmlPegado(html);
            try {
                var ndt = new DataTransfer();
                ndt.setData('text/html', limpio);
                ndt.setData('text/plain', dt.getData('text/plain'));
                var ev = new ClipboardEvent('paste', { clipboardData: ndt, bubbles: true, cancelable: true });
                if (!ev.clipboardData || ev.clipboardData.getData('text/html') !== limpio) return; // navegador sin soporte: pegado normal
                e.preventDefault();
                e.stopImmediatePropagation();
                ev.__blogLimpio = true;
                e.target.dispatchEvent(ev);
            } catch (err) { /* pegado normal */ }
        }, true);
    }

    // ═══════════════════════════════════════════════════════
    // ESTRUCTURA + PALETA
    // ═══════════════════════════════════════════════════════
    var ICONO_TIPO = { paragraph: 'pilcrow', header: 'heading', image: 'image', list: 'list', quote: 'quote', table: 'table',
                       delimiter: 'minus', embed: 'video', aviso: 'lightbulb', faq: 'circle-help', modulo: 'layout-template' };

    function etiquetaBloque(bloque) {
        var h = bloque.holder;
        switch (bloque.name) {
            case 'paragraph': return textoPlano(h) || '(párrafo vacío)';
            case 'header': return textoPlano(h) || '(subtítulo vacío)';
            case 'image':
                var cap = textoPlano(h.querySelector('.image-tool__caption'));
                var alt = (h.querySelector('.bed-alt') || {}).value || '';
                return 'Imagen' + (alt || cap ? ' · ' + (alt || cap) : '');
            case 'list': return 'Lista · ' + textoPlano(h.querySelector('li, .cdx-list__item'));
            case 'quote': return 'Cita · ' + textoPlano(h.querySelector('.cdx-quote__text'));
            case 'table': return 'Tabla';
            case 'delimiter': return 'Separador';
            case 'embed': return 'Vídeo';
            case 'aviso': return (h.querySelector('select') || {}).value === 'importante' ? 'Importante · ' + textoPlano(h.querySelector('.bed-aviso__texto')) : 'Consejo · ' + textoPlano(h.querySelector('.bed-aviso__texto'));
            case 'faq': return 'Preguntas frecuentes (' + h.querySelectorAll('.bed-faq__item').length + ')';
            case 'modulo': return 'Módulo · ' + textoPlano(h.querySelector('.bed-modulo__nombre'));
        }
        return bloque.name;
    }

    function refrescarEstructura() {
        if (!editor || !editor.blocks) return;
        var ul = $('bed-estructura');
        var n = editor.blocks.getBlocksCount();
        var html = '';
        for (var i = 0; i < n; i++) {
            var b = editor.blocks.getBlockByIndex(i);
            if (!b) continue;
            var nivel = b.name === 'header' && b.holder.querySelector('h3, h4') ? ' bestructura__item--h3' : '';
            var texto = etiquetaBloque(b).slice(0, 90).replace(/&/g, '&amp;').replace(/</g, '&lt;');
            html += '<li class="bestructura__item bestructura__item--' + b.name + nivel + '" data-index="' + i + '" draggable="true">'
                + '<span class="bestructura__asa" title="Arrastrar"><i data-lucide="grip-vertical" class="icono"></i></span>'
                + '<span class="bestructura__icono"><i data-lucide="' + (ICONO_TIPO[b.name] || 'square') + '" class="icono"></i></span>'
                + '<span class="bestructura__texto" title="' + texto + '">' + texto + '</span>'
                + '<span class="bestructura__acciones">'
                + '<button type="button" data-acc="subir" title="Subir"><i data-lucide="chevron-up" class="icono"></i></button>'
                + '<button type="button" data-acc="bajar" title="Bajar"><i data-lucide="chevron-down" class="icono"></i></button>'
                + '<button type="button" data-acc="borrar" class="peligro" title="Eliminar bloque"><i data-lucide="x" class="icono"></i></button>'
                + '</span></li>';
        }
        ul.innerHTML = html || '<li class="bestructura__vacio">El artículo está vacío. Pega el texto en el centro.</li>';
        $('bed-num-bloques').textContent = n;
        iconos();
    }
    var refrescoDiferido = debounce(function () { refrescarEstructura(); analizarSeo(); guardarCopiaLocal(); }, 350);

    function resaltarBloque(i) {
        var b = editor.blocks.getBlockByIndex(i);
        if (!b) return;
        b.holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
        b.holder.classList.remove('ce-block--resaltado');
        void b.holder.offsetWidth;
        b.holder.classList.add('ce-block--resaltado');
    }

    function moverBloque(desde, hacia) {
        var n = editor.blocks.getBlocksCount();
        if (hacia < 0 || hacia >= n || desde === hacia) return;
        editor.blocks.move(hacia, desde);
        marcarSucio();
        setTimeout(function () { refrescarEstructura(); resaltarBloque(hacia); }, 30);
    }

    function datosIniciales(tipo, moduloId) {
        switch (tipo) {
            case 'modulo': return { modulo_id: moduloId };
            case 'header': return { text: '', level: 2 };
            case 'aviso': return { tipo: 'consejo', titulo: '', text: '' };
            default: return undefined;
        }
    }

    function insertarBloque(tipo, moduloId, indice) {
        var n = editor.blocks.getBlocksCount();
        if (indice === undefined || indice === null) {
            var actual = editor.blocks.getCurrentBlockIndex();
            indice = actual >= 0 ? actual + 1 : n;
        }
        indice = Math.max(0, Math.min(indice, n));
        editor.blocks.insert(tipo, datosIniciales(tipo, moduloId), undefined, indice, true);
        marcarSucio();
        setTimeout(function () {
            refrescarEstructura();
            resaltarBloque(indice);
            if (tipo !== 'modulo' && tipo !== 'image') { try { editor.caret.setToBlock(indice, 'start'); } catch (e) {} }
        }, 60);
    }

    function engancharEstructura() {
        var ul = $('bed-estructura');
        var desde = null;

        ul.addEventListener('click', function (e) {
            var li = e.target.closest('.bestructura__item'); if (!li) return;
            var i = Number(li.dataset.index);
            var btn = e.target.closest('button[data-acc]');
            if (!btn) { resaltarBloque(i); return; }
            if (btn.dataset.acc === 'subir') moverBloque(i, i - 1);
            if (btn.dataset.acc === 'bajar') moverBloque(i, i + 1);
            if (btn.dataset.acc === 'borrar') {
                editor.blocks.delete(i);
                marcarSucio();
                setTimeout(refrescarEstructura, 30);
            }
        });
        ul.addEventListener('dragstart', function (e) {
            var li = e.target.closest('.bestructura__item'); if (!li) return;
            desde = Number(li.dataset.index);
            li.classList.add('bestructura__item--arrastrando');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/x-bed-index', String(desde));
        });
        ul.addEventListener('dragend', function () {
            desde = null;
            ul.querySelectorAll('.bestructura__item').forEach(function (li) { li.classList.remove('bestructura__item--arrastrando', 'bestructura__item--sobre-arriba', 'bestructura__item--sobre-abajo'); });
        });
        ul.addEventListener('dragover', function (e) {
            var li = e.target.closest('.bestructura__item');
            var esPaleta = Array.prototype.indexOf.call(e.dataTransfer.types, 'text/x-bed-paleta') !== -1;
            if (!li || (desde === null && !esPaleta)) return;
            e.preventDefault();
            var r = li.getBoundingClientRect();
            var abajo = e.clientY > r.top + r.height / 2;
            ul.querySelectorAll('.bestructura__item').forEach(function (x) { x.classList.remove('bestructura__item--sobre-arriba', 'bestructura__item--sobre-abajo'); });
            li.classList.add(abajo ? 'bestructura__item--sobre-abajo' : 'bestructura__item--sobre-arriba');
        });
        ul.addEventListener('drop', function (e) {
            var li = e.target.closest('.bestructura__item'); if (!li) return;
            e.preventDefault();
            var t = Number(li.dataset.index);
            var r = li.getBoundingClientRect();
            var destino = t + (e.clientY > r.top + r.height / 2 ? 1 : 0);
            var paleta = e.dataTransfer.getData('text/x-bed-paleta');
            if (paleta) {
                var p = JSON.parse(paleta);
                insertarBloque(p.tipo, p.modulo, destino);
                return;
            }
            if (desde === null) return;
            if (desde < destino) destino -= 1;
            moverBloque(desde, destino);
        });
    }

    function engancharPaleta() {
        var lienzo = $('bed-lienzo');
        var linea = null;
        function datosItem(item) { return { tipo: item.dataset.bloque, modulo: item.dataset.moduloId ? Number(item.dataset.moduloId) : null }; }

        document.querySelectorAll('.bpaleta__item').forEach(function (item) {
            item.addEventListener('dragstart', function (e) {
                item.classList.add('bpaleta__item--arrastrando');
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/x-bed-paleta', JSON.stringify(datosItem(item)));
            });
            item.addEventListener('dragend', function () { item.classList.remove('bpaleta__item--arrastrando'); quitarLinea(); });
            item.querySelector('.bpaleta__mas').addEventListener('click', function () {
                var d = datosItem(item); insertarBloque(d.tipo, d.modulo);
            });
            item.addEventListener('dblclick', function () { var d = datosItem(item); insertarBloque(d.tipo, d.modulo); });
        });

        function indiceEnLienzo(y) {
            var n = editor.blocks.getBlocksCount();
            for (var i = 0; i < n; i++) {
                var r = editor.blocks.getBlockByIndex(i).holder.getBoundingClientRect();
                if (y < r.top + r.height / 2) return { indice: i, y: r.top };
            }
            var ult = n ? editor.blocks.getBlockByIndex(n - 1).holder.getBoundingClientRect() : lienzo.getBoundingClientRect();
            return { indice: n, y: n ? ult.bottom : ult.top + 30 };
        }
        function quitarLinea() { if (linea) { linea.remove(); linea = null; } lienzo.classList.remove('blog-editor__lienzo--sobre'); }

        lienzo.addEventListener('dragover', function (e) {
            if (Array.prototype.indexOf.call(e.dataTransfer.types, 'text/x-bed-paleta') === -1) return;
            e.preventDefault();
            e.stopPropagation();
            lienzo.classList.add('blog-editor__lienzo--sobre');
            var pos = indiceEnLienzo(e.clientY);
            if (!linea) { linea = document.createElement('div'); linea.className = 'blog-editor__drop-linea'; lienzo.appendChild(linea); }
            linea.style.top = (pos.y - lienzo.getBoundingClientRect().top - 2) + 'px';
        }, true);
        lienzo.addEventListener('dragleave', function (e) { if (!lienzo.contains(e.relatedTarget)) quitarLinea(); });
        lienzo.addEventListener('drop', function (e) {
            var paleta = e.dataTransfer.getData('text/x-bed-paleta');
            if (!paleta) return;
            e.preventDefault();
            e.stopPropagation();
            var pos = indiceEnLienzo(e.clientY);
            quitarLinea();
            var p = JSON.parse(paleta);
            insertarBloque(p.tipo, p.modulo, pos.indice);
        }, true);
    }

    // ═══════════════════════════════════════════════════════
    // PORTADA
    // ═══════════════════════════════════════════════════════
    function pintarPortada() {
        var hay = !!portada.url;
        $('bed-portada-img').hidden = !hay;
        if (hay) { $('bed-portada-img').src = portada.url; $('bed-portada-img').alt = $('bed-portada-alt').value; }
        $('bed-portada-vacia').hidden = hay;
        $('bed-portada-acciones').hidden = !hay;
        $('bed-portada-alt-wrap').hidden = !hay;
    }
    function subirPortada(archivo) {
        if (!archivo) return;
        if (!/^image\/(jpeg|png|webp|gif)$/.test(archivo.type)) { aviso('error', 'Formato no válido. Usa JPG, PNG o WebP.'); return; }
        if (archivo.size > 5 * 1024 * 1024) { aviso('error', 'La imagen supera los 5 MB.'); return; }
        var zona = $('bed-portada');
        var carg = document.createElement('div');
        carg.className = 'blog-editor__portada-cargando';
        carg.textContent = 'Subiendo imagen…';
        zona.appendChild(carg);
        var fd = new FormData();
        fd.append('image', archivo);
        fd.append('nombre', $('bed-slug').value || slugificar($('bed-titulo').value) || 'portada');
        fd.append('articulo_id', C.id || 0);
        fetch(C.urls.subir, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': C.csrf } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.mensaje || 'No se pudo subir la imagen');
                portada = { ruta: d.file.ruta, url: d.file.url };
                pintarPortada();
                marcarSucio();
                analizarSeo();
                if (!$('bed-portada-alt').value.trim()) $('bed-portada-alt').focus();
                aviso('ok', 'Portada subida. Escribe su texto alternativo.');
            })
            .catch(function (err) { aviso('error', err.message); })
            .finally(function () { carg.remove(); });
    }
    function engancharPortada() {
        var zona = $('bed-portada');
        var input = $('bed-portada-file');
        zona.addEventListener('click', function (e) {
            if (e.target.closest('#bed-portada-quitar')) return;
            input.click();
        });
        zona.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        input.addEventListener('change', function () { subirPortada(input.files[0]); input.value = ''; });
        ['dragenter', 'dragover'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) {
                if (Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') === -1) return;
                e.preventDefault(); zona.classList.add('blog-editor__portada--sobre');
            });
        });
        zona.addEventListener('dragleave', function () { zona.classList.remove('blog-editor__portada--sobre'); });
        zona.addEventListener('drop', function (e) {
            if (!e.dataTransfer.files.length) return;
            e.preventDefault(); zona.classList.remove('blog-editor__portada--sobre');
            subirPortada(e.dataTransfer.files[0]);
        });
        $('bed-portada-quitar').addEventListener('click', function (e) {
            e.stopPropagation();
            portada = { ruta: '', url: '' };
            pintarPortada(); marcarSucio(); analizarSeo();
        });
        $('bed-portada-alt').addEventListener('input', function () { marcarSucio(); analizarSeo(); });
        pintarPortada();
    }

    // ═══════════════════════════════════════════════════════
    // CATEGORÍAS, TAXONOMÍAS, CONTEXTO
    // ═══════════════════════════════════════════════════════
    function actualizarPrincipal() {
        var sel = $('bed-cat-principal'); if (!sel) return;
        var actual = sel.value || sel.dataset.actual;
        var marcadas = Array.prototype.filter.call(document.querySelectorAll('#bed-categorias input'), function (i) { return i.checked; });
        sel.innerHTML = marcadas.length
            ? marcadas.map(function (i) { return '<option value="' + i.value + '">' + i.dataset.nombre.replace(/</g, '&lt;') + '</option>'; }).join('')
            : '<option value="">— Marca al menos una categoría —</option>';
        if (marcadas.some(function (i) { return i.value === String(actual); })) sel.value = String(actual);
        sel.dataset.actual = sel.value;
    }
    function engancharCategorias() {
        var cont = $('bed-categorias'); if (!cont) return;
        cont.addEventListener('change', function () { actualizarPrincipal(); marcarSucio(); });
        $('bed-cat-principal').addEventListener('change', function () { this.dataset.actual = this.value; marcarSucio(); });
        actualizarPrincipal();

        function crear() {
            var inp = $('bed-cat-nueva');
            var nombre = inp.value.trim(); if (!nombre) return;
            fetch(C.urls.taxo, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ accion: 'crear_termino', taxonomia_id: C.taxCategoria, nombre: nombre })
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d.ok) throw new Error(d.mensaje || 'No se pudo crear');
                var lab = document.createElement('label');
                lab.className = 'field__opcion';
                lab.innerHTML = '<input type="checkbox" class="field__check" checked value="' + d.id + '"><span></span>';
                lab.querySelector('input').dataset.nombre = d.nombre;
                lab.querySelector('span').textContent = d.nombre;
                cont.appendChild(lab);
                inp.value = '';
                actualizarPrincipal(); marcarSucio();
                aviso('ok', 'Categoría creada');
            }).catch(function (e) { aviso('error', e.message); });
        }
        $('bed-cat-crear').addEventListener('click', crear);
        $('bed-cat-nueva').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); crear(); } });
    }

    function engancharSelects() {
        if (typeof window.TomSelect === 'undefined') return;
        document.querySelectorAll('select.bed-tax').forEach(function (sel) {
            tsTax[sel.dataset.tax] = new window.TomSelect(sel, {
                plugins: ['remove_button'], persist: false, maxOptions: 300,
                create: function (input) { return { value: 'nuevo:' + input, text: input }; },
                render: { option_create: function (d, esc) { return '<div class="create">Crear <strong>' + esc(d.input) + '</strong></div>'; },
                          no_results: function () { return '<div class="no-results">Sin resultados</div>'; } },
                onChange: marcarSucio
            });
        });
        document.querySelectorAll('select.bed-ctx').forEach(function (sel) {
            tsCtx[sel.dataset.ctx] = new window.TomSelect(sel, {
                plugins: ['remove_button'], maxOptions: 200,
                render: { no_results: function () { return '<div class="no-results">Sin resultados</div>'; } },
                onChange: marcarSucio
            });
        });
    }

    // ═══════════════════════════════════════════════════════
    // SEO
    // ═══════════════════════════════════════════════════════
    function normalizar(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

    function analizarSeo() {
        var kw = normalizar($('bed-keyword').value.trim());
        var titulo = $('bed-titulo').value.trim();
        var metaT = $('bed-meta-title').value.trim() || titulo;
        var metaD = $('bed-meta-desc').value.trim() || $('bed-extracto').value.trim();
        var slug = $('bed-slug').value.trim() || slugificar(titulo);

        // Contadores
        var ct = $('bed-mt-cont'), cd = $('bed-md-cont');
        ct.textContent = metaT.length + ' / 60';
        ct.className = 'bseo-contador ' + (metaT.length >= 30 && metaT.length <= 60 ? 'bseo-contador--ok' : 'bseo-contador--mal');
        cd.textContent = metaD.length + ' / 160';
        cd.className = 'bseo-contador ' + (metaD.length >= 120 && metaD.length <= 160 ? 'bseo-contador--ok' : 'bseo-contador--mal');

        // Vista de Google
        $('bed-g-url').textContent = (C.dominio || 'crematoriosdemascotas.com') + ' › blog › ' + (slug || '…');
        $('bed-g-titulo').textContent = metaT || 'Título del artículo';
        $('bed-g-desc').textContent = metaD || 'Escribe una entradilla o una descripción para Google.';

        // Contenido del editor (DOM)
        var raiz = document.querySelector('#editorjs .codex-editor__redactor');
        var textos = raiz ? raiz.querySelectorAll('.ce-paragraph, .ce-header, .cdx-list__item, li, .cdx-quote__text, .bed-aviso__texto, .bed-faq__pregunta, .bed-faq__respuesta, .tc-cell') : [];
        var todo = Array.prototype.map.call(textos, textoPlano).join(' ');
        var palabras = todo.split(/\s+/).filter(Boolean).length;
        var primerP = raiz ? Array.prototype.find.call(raiz.querySelectorAll('.ce-paragraph'), function (p) { return textoPlano(p).length > 20; }) : null;
        var subtitulos = raiz ? raiz.querySelectorAll('h2.ce-header, h3.ce-header') : [];
        var h2 = raiz ? raiz.querySelectorAll('h2.ce-header').length : 0;
        var enlaces = raiz ? raiz.querySelectorAll('a[href]') : [];
        var internos = Array.prototype.filter.call(enlaces, function (a) {
            var h = a.getAttribute('href') || '';
            return h.charAt(0) === '/' || h.indexOf(C.host) !== -1 || (C.dominio && h.indexOf(C.dominio) !== -1);
        }).length;
        var imgs = raiz ? raiz.querySelectorAll('.bed-alt') : [];
        var sinAlt = Array.prototype.filter.call(imgs, function (i) { return !i.value.trim(); }).length;
        var contieneKw = function (t) { return kw && normalizar(t).indexOf(kw) !== -1; };
        var kwEnSub = Array.prototype.some.call(subtitulos, function (h) { return contieneKw(textoPlano(h)); });

        var checks = [
            [!!kw, 'Define una palabra clave principal', 'Palabra clave definida'],
            [contieneKw(metaT), 'La palabra clave no está en el título', 'Palabra clave en el título'],
            [contieneKw(metaD), 'La palabra clave no está en la descripción', 'Palabra clave en la descripción'],
            [kw && slug.indexOf(slugificar(kw)) !== -1, 'La palabra clave no está en la URL', 'Palabra clave en la URL'],
            [primerP && contieneKw(textoPlano(primerP)), 'Menciona la palabra clave en el primer párrafo', 'Palabra clave en el primer párrafo'],
            [kwEnSub, 'Usa la palabra clave en algún subtítulo', 'Palabra clave en un subtítulo'],
            [metaT.length >= 30 && metaT.length <= 60, 'Título para Google: ideal entre 30 y 60 caracteres (' + metaT.length + ')', 'Título con buena longitud'],
            [metaD.length >= 120 && metaD.length <= 160, 'Descripción: ideal entre 120 y 160 caracteres (' + metaD.length + ')', 'Descripción con buena longitud'],
            [palabras >= 600 ? true : (palabras >= 300 ? 'medio' : false), 'Texto corto: ' + palabras + ' palabras (recomendado 600 o más)', palabras + ' palabras'],
            [h2 >= 2, 'Divide el texto con al menos 2 subtítulos H2', 'Estructura con subtítulos'],
            [!!portada.url && !!$('bed-portada-alt').value.trim(), portada.url ? 'Falta el texto alternativo de la portada' : 'Añade una imagen de portada', 'Portada con texto alternativo'],
            [sinAlt === 0, sinAlt + ' imagen(es) sin texto alternativo', 'Todas las imágenes tienen alt'],
            [internos >= 1, 'Añade al menos un enlace interno (al directorio, una ficha o otro artículo)', 'Tiene enlaces internos']
        ];
        var puntos = 0;
        var html = checks.map(function (c) {
            var estado = c[0] === 'medio' ? 'medio' : (c[0] ? 'ok' : 'mal');
            puntos += estado === 'ok' ? 1 : (estado === 'medio' ? 0.5 : 0);
            var ico = estado === 'ok' ? 'check-circle-2' : (estado === 'medio' ? 'alert-circle' : 'x-circle');
            return '<li class="' + estado + '"><i data-lucide="' + ico + '" class="icono"></i><span>' + (estado === 'ok' ? c[2] : c[1]) + '</span></li>';
        }).join('');
        $('bed-seo-checks').innerHTML = html;
        var pct = Math.round(puntos / checks.length * 100);
        var color = pct >= 80 ? 'var(--admin-tone-exito-fg)' : (pct >= 50 ? '#d6a021' : 'var(--admin-tone-error-fg)');
        $('bed-seo-barra').style.width = pct + '%';
        $('bed-seo-barra').style.background = color;
        $('bed-seo-texto').textContent = 'SEO ' + pct + '%';
        $('bed-seo-badge').textContent = pct + '%';
        iconos();
    }

    // ═══════════════════════════════════════════════════════
    // ESTADO / GUARDADO
    // ═══════════════════════════════════════════════════════
    function estadoElegido() { var r = document.querySelector('input[name="bed-estado"]:checked'); return r ? r.value : 'borrador'; }

    function actualizarBoton() {
        var e = estadoElegido();
        var txt = e === 'borrador' ? 'Guardar borrador' : (e === 'programado' ? 'Programar' : (C.estadoGuardado === 'publicado' ? 'Actualizar' : 'Publicar'));
        $('bed-guardar').querySelector('span').textContent = txt;
        $('bed-fecha-wrap').hidden = e === 'borrador';
        $('bed-fecha-hint').textContent = e === 'programado'
            ? 'El artículo se publicará solo en esta fecha y hora.'
            : 'Si la dejas vacía se usa la fecha y hora actuales.';
    }

    function marcarSucio() {
        sucio = true;
        var el = $('bed-guardado');
        el.className = 'blog-editor__estado-guardado blog-editor__estado-guardado--sucio';
        el.innerHTML = '<span class="punto"></span> Cambios sin guardar';
    }
    function marcarLimpio(msg) {
        sucio = false;
        var el = $('bed-guardado');
        el.className = 'blog-editor__estado-guardado';
        el.textContent = msg || ('Guardado a las ' + new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }));
    }

    function recolectar() {
        return editor.save().then(function (contenido) {
            var terminos = {};
            if (C.taxCategoria) {
                terminos[C.taxCategoria] = Array.prototype.filter.call(document.querySelectorAll('#bed-categorias input'), function (i) { return i.checked; })
                    .map(function (i) { return Number(i.value); });
            }
            Object.keys(tsTax).forEach(function (tax) {
                var v = tsTax[tax].getValue();
                terminos[tax] = (Array.isArray(v) ? v : (v ? [v] : [])).map(function (x) { return /^\d+$/.test(x) ? Number(x) : x; });
            });
            var contexto = {};
            Object.keys(tsCtx).forEach(function (k) { contexto[k] = tsCtx[k].getValue(); });
            contexto.servicio = Array.prototype.filter.call(document.querySelectorAll('#bed-ctx-servicios input'), function (i) { return i.checked; }).map(function (i) { return i.value; });

            return {
                accion: 'guardar',
                csrf_token: C.csrf,
                id: C.id,
                titulo: $('bed-titulo').value.trim(),
                slug: $('bed-slug').value.trim(),
                extracto: $('bed-extracto').value.trim(),
                contenido: contenido,
                portada_ruta: portada.ruta,
                portada_alt: $('bed-portada-alt').value.trim(),
                autor_id: $('bed-autor').value,
                revisor_id: $('bed-revisor').value,
                categoria_principal_id: $('bed-cat-principal') ? $('bed-cat-principal').value : '',
                terminos: terminos,
                contexto: contexto,
                estado: estadoElegido(),
                publicado_at: $('bed-fecha').value,
                destacado: $('bed-destacado').checked ? 1 : 0,
                mostrar_indice: $('bed-indice').checked ? 1 : 0,
                meta_title: $('bed-meta-title').value.trim(),
                meta_description: $('bed-meta-desc').value.trim(),
                keyword_principal: $('bed-keyword').value.trim(),
                canonical_url: $('bed-canonical').value.trim(),
                noindex: $('bed-noindex').checked ? 1 : 0
            };
        });
    }

    function guardar() {
        if (guardando) return;
        var titulo = $('bed-titulo').value.trim();
        if (!titulo) { aviso('error', 'Escribe un título antes de guardar.'); $('bed-titulo').focus(); return; }
        if (estadoElegido() !== 'borrador' && portada.url && !$('bed-portada-alt').value.trim()) {
            aviso('error', 'Escribe el texto alternativo de la portada antes de publicar.');
            $('bed-portada-alt').focus();
            return;
        }
        guardando = true;
        var btn = $('bed-guardar');
        btn.disabled = true;
        recolectar().then(function (payload) {
            return fetch(C.urls.guardar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json(); });
        }).then(function (d) {
            if (!d.ok) {
                if (d.campo === 'portada_alt') $('bed-portada-alt').focus();
                if (d.campo === 'publicado_at') $('bed-fecha').focus();
                throw new Error(d.mensaje || 'No se pudo guardar');
            }
            var eraNuevo = !C.id;
            C.id = d.id;
            C.estadoGuardado = d.estado;
            $('bed-slug').value = d.slug;
            $('bed-slug').dataset.manual = '1';
            if (d.publicado_at) $('bed-fecha').value = d.publicado_at;
            var radio = document.querySelector('input[name="bed-estado"][value="' + d.estado + '"]');
            if (radio) radio.checked = true;
            var pill = $('bed-pill-estado');
            pill.textContent = { borrador: 'Borrador', programado: 'Programado', publicado: 'Publicado' }[d.estado];
            pill.className = 'admin-pill' + (d.estado === 'publicado' ? ' admin-pill--exito' : (d.estado === 'programado' ? ' admin-pill--info' : ''));
            Object.keys(d.creados || {}).forEach(function (temp) {
                Object.keys(tsTax).forEach(function (tax) {
                    var ts = tsTax[tax];
                    if (ts.options[temp]) {
                        var texto = ts.options[temp].text;
                        ts.removeOption(temp);
                        ts.addOption({ value: String(d.creados[temp]), text: texto });
                        ts.addItem(String(d.creados[temp]), true);
                    }
                });
            });
            try { localStorage.removeItem(CLAVE_LOCAL); } catch (e) {}
            CLAVE_LOCAL = 'blog-borrador-' + C.id;
            actualizarBoton();
            marcarLimpio();
            aviso('ok', d.mensaje);
            if (eraNuevo) history.replaceState(null, '', C.urls.editar + '?id=' + C.id);
        }).catch(function (e) {
            aviso('error', e.message || 'Error de conexión');
        }).finally(function () {
            guardando = false;
            btn.disabled = false;
        });
    }

    function vistaPrevia() {
        recolectar().then(function (payload) {
            $('bed-preview-payload').value = JSON.stringify(payload);
            window.open('', 'blog-preview');
            $('bed-form-preview').submit();
        });
    }

    // ─── Copia local de seguridad ───────────────────────────
    var guardarCopiaLocal = debounce(function () {
        if (!sucio || !editor) return;
        recolectar().then(function (p) {
            try { localStorage.setItem(CLAVE_LOCAL, JSON.stringify({ t: Date.now(), p: p })); } catch (e) {}
        });
    }, 1500);

    function revisarCopiaLocal() {
        var copia = null;
        try { copia = JSON.parse(localStorage.getItem(CLAVE_LOCAL) || 'null'); } catch (e) {}
        if (!copia || !copia.p || copia.t < (C.actualizado || 0) + 5000) return;
        $('bed-recuperar').hidden = false;
        $('bed-recuperar-si').addEventListener('click', function () {
            var p = copia.p;
            $('bed-titulo').value = p.titulo || '';
            $('bed-extracto').value = p.extracto || '';
            $('bed-slug').value = p.slug || '';
            $('bed-meta-title').value = p.meta_title || '';
            $('bed-meta-desc').value = p.meta_description || '';
            $('bed-keyword').value = p.keyword_principal || '';
            $('bed-portada-alt').value = p.portada_alt || '';
            portada = { ruta: p.portada_ruta || '', url: p.portada_ruta ? C.base + '/' + p.portada_ruta : '' };
            pintarPortada();
            editor.render(p.contenido && p.contenido.blocks ? p.contenido : { blocks: [] }).then(function () {
                refrescarEstructura(); analizarSeo(); marcarSucio();
            });
            ['bed-titulo', 'bed-extracto'].forEach(function (id) { autoAltura($(id)); });
            $('bed-recuperar').hidden = true;
            aviso('info', 'Copia recuperada. Revisa y pulsa Guardar.');
        });
        $('bed-recuperar-no').addEventListener('click', function () {
            try { localStorage.removeItem(CLAVE_LOCAL); } catch (e) {}
            $('bed-recuperar').hidden = true;
        });
    }

    // ═══════════════════════════════════════════════════════
    // ARRANQUE
    // ═══════════════════════════════════════════════════════
    function engancharCampos() {
        ['bed-titulo', 'bed-extracto'].forEach(function (id) {
            var ta = $(id);
            autoAltura(ta);
            ta.addEventListener('input', function () { autoAltura(ta); marcarSucio(); analizarSeo(); });
            ta.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
        });
        $('bed-titulo').addEventListener('input', function () {
            if ($('bed-slug').dataset.manual !== '1') $('bed-slug').value = slugificar(this.value);
        });
        $('bed-slug').addEventListener('input', function () { this.dataset.manual = '1'; });
        $('bed-slug').addEventListener('blur', function () { this.value = slugificar(this.value) || slugificar($('bed-titulo').value); analizarSeo(); });
        ['bed-slug', 'bed-keyword', 'bed-meta-title', 'bed-meta-desc', 'bed-canonical', 'bed-fecha'].forEach(function (id) {
            $(id).addEventListener('input', function () { marcarSucio(); analizarSeo(); });
        });
        ['bed-autor', 'bed-revisor', 'bed-destacado', 'bed-indice', 'bed-noindex'].forEach(function (id) { $(id).addEventListener('change', marcarSucio); });
        $('bed-ctx-servicios').addEventListener('change', marcarSucio);
        document.querySelectorAll('input[name="bed-estado"]').forEach(function (r) {
            r.addEventListener('change', function () {
                actualizarBoton(); marcarSucio();
                if (r.value === 'programado' && !$('bed-fecha').value) $('bed-fecha').focus();
            });
        });
        document.addEventListener('blog:cambio', function () { marcarSucio(); refrescoDiferido(); });

        $('bed-guardar').addEventListener('click', guardar);
        $('bed-preview').addEventListener('click', vistaPrevia);
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); guardar(); }
        });
        window.addEventListener('beforeunload', function (e) { if (sucio) { e.preventDefault(); e.returnValue = ''; } });

        if ($('bed-eliminar')) $('bed-eliminar').addEventListener('click', function () {
            window.confirmar({
                titulo: 'Eliminar artículo',
                mensaje: 'Se elimina el artículo <strong>' + ($('bed-titulo').value || '').replace(/</g, '&lt;') + '</strong>. Esta acción no se puede deshacer.',
                textoOK: 'Eliminar', peligroso: true,
                onOK: function () {
                    fetch(C.urls.guardar, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'eliminar', id: C.id, csrf_token: C.csrf }) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d.ok) throw new Error(d.mensaje);
                            sucio = false;
                            try { localStorage.removeItem(CLAVE_LOCAL); } catch (e) {}
                            location.href = 'blog-articulos.php?ok=' + encodeURIComponent('Artículo eliminado');
                        }).catch(function (e) { aviso('error', e.message); });
                }
            });
        });
        if ($('bed-duplicar')) $('bed-duplicar').addEventListener('click', function () {
            if (sucio) { aviso('info', 'Guarda los cambios antes de duplicar.'); return; }
            fetch(C.urls.guardar, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'duplicar', id: C.id, csrf_token: C.csrf }) })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (!d.ok) throw new Error(d.mensaje); location.href = C.urls.editar + '?id=' + d.id; })
                .catch(function (e) { aviso('error', e.message); });
        });
    }

    // La barra de acciones va pegada debajo del header sticky del admin
    function medirAlturas() {
        var cab = document.querySelector('.admin-header');
        var barra = document.querySelector('.blog-editor__barra');
        var raiz = document.documentElement.style;
        raiz.setProperty('--bed-header', (cab && getComputedStyle(cab).position === 'sticky' ? cab.offsetHeight : 0) + 'px');
        raiz.setProperty('--bed-barra', (barra ? barra.offsetHeight : 56) + 'px');
    }

    document.addEventListener('DOMContentLoaded', function () {
        medirAlturas();
        window.addEventListener('resize', debounce(medirAlturas, 150));
        engancharSelects();
        engancharCampos();
        engancharPortada();
        engancharCategorias();
        crearEditor();
        engancharPegado();
        engancharEstructura();
        engancharPaleta();
        actualizarBoton();
    });
})();
