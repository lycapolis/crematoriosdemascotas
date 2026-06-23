<?php
/**
 * ═══════════════════════════════════════════════════════════
 * REGISTRAR NEGOCIO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Registrar tu Crematorio - Crematorios de Mascotas';
$pagina_actual = 'registrar-negocio';
$GEO_ES = include __DIR__ . '/includes/geo_es.php'; // CCAA → provincias (cascada España)
include 'includes/header.php';
?>

<style>
    /* ═══════════════════════════════════════════════════════════
       REGISTRAR NEGOCIO - Responsive Styles
       ═══════════════════════════════════════════════════════════ */

    /* MÓVIL (Base) - Todo en cascada, sidebar al final */
    .registro-seccion {
        padding: var(--espacio-cuatro) var(--espacio-tres); /* móvil: 24 arriba/abajo · 16 a los lados */
    }

    .registro-layout {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-cuatro); /* parejo con la separación entre secciones */
    }

    .registro-form-grid {
        display: flex;
        flex-direction: column;
    }

    .registro-sidebar {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-cuatro);
    }

    /* ═══════════════════════════════════════════════════════════
       TABLET (768px - 1023px) - Sidebar en fila de 3
       ═══════════════════════════════════════════════════════════ */
    @media (min-width: 768px) {
        .registro-seccion {
            padding: var(--espacio-cinco) !important;
        }

        .registro-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--espacio-cuatro);
        }

        .registro-sidebar {
            flex-direction: row;
        }

        .registro-sidebar article {
            flex: 1;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       DESKTOP (1024px+) - Sidebar lateral sticky
       ═══════════════════════════════════════════════════════════ */
    @media (min-width: 1024px) {
        .registro-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
        }

        .registro-sidebar {
            flex-direction: column;
            position: sticky;
            top: 120px;
            align-self: start;
        }
    }
</style>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion registro-seccion">
        <div class="contenedor">
            <div class="registro-layout">

                <!-- FORMULARIO -->
                <div id="formulario-container">
                    <div id="formulario-card">
                        <header style="margin: 0 0 var(--espacio-cuatro);">
                            <h1 style="font-size: var(--fs-siete); margin: 0 0 var(--espacio-dos); line-height: 1.15;">Registra tu Crematorio</h1>
                            <p style="margin: 0; color: var(--admin-tinta-suave); font-size: var(--fs-cuatro); line-height: 1.5;">Únete a nuestro directorio y conecta con familias que buscan servicios de cremación para sus mascotas.</p>
                        </header>

                        <!-- Alerta -->
                        <div id="alerta" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

                        <!-- Cartel buenas prácticas -->
                        <div class="callout" style="margin-bottom: var(--espacio-cuatro);">
                            <i data-lucide="info" class="callout__icon"></i>
                            <div class="callout__body">
                                <strong>Antes de empezar — leé esto:</strong>
                                <ul>
                                    <li>Escribe el nombre <strong>sin la ciudad</strong>. La dirección web de tu ficha se genera automáticamente como <em>tu-crematorio-ciudad</em> y <strong>no se puede cambiar después</strong> (es importante para que Google te encuentre).</li>
                                    <li>Revisa que la <strong>ciudad y la dirección coincidan</strong> (no mezclar la ciudad de un sitio con la dirección de otro).</li>
                                    <li>Pega las URLs de <strong>sitio web y redes sociales completas</strong> (con <code>https://</code>) y verifica que estén bien escritas.</li>
                                </ul>
                            </div>
                        </div>

                        <form id="formulario-registro" onsubmit="enviarFormulario(event)" enctype="multipart/form-data" novalidate style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                            <!-- Honeypot: campo invisible que solo los bots completan -->
                            <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; width:0; overflow:hidden;">
                                <label for="website-url-extra">No completar este campo</label>
                                <input type="text" id="website-url-extra" name="website_url" tabindex="-1" autocomplete="off" value="">
                            </div>
                            <!-- Time-trap: timestamp del render para detectar envíos demasiado rápidos -->
                            <input type="hidden" name="form_render_ts" value="<?php echo time(); ?>">

                            <!-- SECCIÓN 1: Datos de contacto comercial -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="user" class="icono"></i>
                                    Datos de Contacto Comercial
                                </h3>
                                <p class="panel__intro">
                                    Estos datos son privados y solo los usaremos para comunicarnos contigo.
                                </p>

                                <div class="field">
                                    <label class="field__label" for="contacto_nombre">Nombre completo *</label>
                                    <input
                                        type="text"
                                        id="contacto_nombre"
                                        name="contacto_nombre"
                                        class="field__input"
                                        required
                                        placeholder="Tu nombre completo"
                                    >
                                </div>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="contacto_email">Email *</label>
                                        <input
                                            type="email"
                                            id="contacto_email"
                                            name="contacto_email"
                                            class="field__input"
                                            required
                                            placeholder="tu@email.com"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="contacto_telefono">Teléfono *</label>
                                        <input
                                            type="tel"
                                            id="contacto_telefono"
                                            name="contacto_telefono"
                                            class="field__input"
                                            required
                                            placeholder="Ej: +34 600 000 000"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: Datos del crematorio -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="home" class="icono"></i>
                                    Datos del Crematorio
                                </h3>

                                <div class="field">
                                    <label class="field__label" for="nombre_negocio">Nombre del crematorio *</label>
                                    <input
                                        type="text"
                                        id="nombre_negocio"
                                        name="nombre_negocio"
                                        class="field__input"
                                        required
                                        placeholder="Nombre comercial de tu crematorio"
                                    >
                                    <small class="field__hint">
                                        Solo el nombre comercial, <strong>sin la ciudad</strong>. Ej: «Crematorio Huella Amiga» (no «Crematorio Huella Amiga Madrid»). La URL no se puede cambiar luego.
                                    </small>
                                </div>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="email_clientes">Email para clientes *</label>
                                        <input
                                            type="email"
                                            id="email_clientes"
                                            name="email_clientes"
                                            class="field__input"
                                            required
                                            placeholder="contacto@tucrematorio.com"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="telefono_clientes">Teléfono para clientes *</label>
                                        <input
                                            type="tel"
                                            id="telefono_clientes"
                                            name="telefono_clientes"
                                            class="field__input"
                                            required
                                            placeholder="Ej: +34 900 000 000"
                                        >
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="direccion">Dirección completa *</label>
                                    <input
                                        type="text"
                                        id="direccion"
                                        name="direccion"
                                        class="field__input"
                                        required
                                        placeholder="Calle, número, colonia"
                                    >
                                </div>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="pais">País *</label>
                                        <select id="pais" name="pais" class="field__select field__select--enhanced" required>
                                            <option value="España" selected>España</option>
                                            <option value="Otro">Otro</option>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="comunidad">Comunidad / Región *</label>
                                        <select id="comunidad" name="comunidad" class="field__select field__select--enhanced" required>
                                            <option value="">— Elige comunidad / región —</option>
                                            <?php foreach (array_keys($GEO_ES) as $ccaa): ?>
                                            <option value="<?php echo htmlspecialchars($ccaa); ?>"><?php echo htmlspecialchars($ccaa); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="provincia">Provincia *</label>
                                        <select id="provincia" name="provincia" class="field__select field__select--enhanced" required>
                                            <option value="">— Elige primero la comunidad —</option>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="ciudad">Ciudad *</label>
                                        <input
                                            type="text"
                                            id="ciudad"
                                            name="ciudad"
                                            class="field__input"
                                            required
                                            disabled
                                            placeholder="Elige antes país, comunidad y provincia"
                                        >
                                        <small class="field__hint">Escribe la ciudad o municipio tal cual; nuestro equipo lo verifica al revisar tu ficha.</small>
                                    </div>
                                </div>

                                <script>
                                (function () {
                                    var GEO = <?php echo json_encode($GEO_ES, JSON_UNESCAPED_UNICODE); ?>;
                                    var cc  = document.getElementById('comunidad');
                                    var pv  = document.getElementById('provincia');
                                    var pais   = document.getElementById('pais');
                                    var ciudad = document.getElementById('ciudad');
                                    if (!cc || !pv) return;

                                    function val(el) { return el ? String(el.value || '').trim() : ''; }

                                    // Ciudad bloqueada hasta tener País + Comunidad + Provincia.
                                    function toggleCiudad() {
                                        var ok = val(pais) && val(cc) && val(pv);
                                        if (!ciudad) return;
                                        ciudad.disabled = !ok;
                                        if (ok) {
                                            ciudad.placeholder = 'Ej: Alcalá de Henares';
                                        } else {
                                            ciudad.value = '';
                                            ciudad.placeholder = 'Elige antes país, comunidad y provincia';
                                        }
                                    }

                                    // Rellena el <select> de provincia según la CCAA elegida.
                                    // Soporta Tom Select (si ya está inicializado) y select nativo.
                                    function fillProvincias() {
                                        var lista = GEO[cc.value] || [];
                                        var ts = pv.tomselect;
                                        if (ts) {
                                            ts.clear(true);
                                            ts.clearOptions();
                                            if (!lista.length) {
                                                ts.addOption({ value: '', text: '— Elige primero la comunidad —' });
                                            } else {
                                                ts.addOption({ value: '', text: '— Elige provincia —' });
                                                lista.forEach(function (p) { ts.addOption({ value: p, text: p }); });
                                            }
                                            ts.refreshOptions(false);
                                            // si la CCAA tiene una sola provincia, autoseleccionarla
                                            if (lista.length === 1) ts.setValue(lista[0], true);
                                        } else {
                                            pv.innerHTML = '';
                                            var ph = document.createElement('option');
                                            ph.value = '';
                                            ph.textContent = lista.length ? '— Elige provincia —' : '— Elige primero la comunidad —';
                                            pv.appendChild(ph);
                                            lista.forEach(function (p) {
                                                var o = document.createElement('option');
                                                o.value = p; o.textContent = p;
                                                pv.appendChild(o);
                                            });
                                            if (lista.length === 1) pv.value = lista[0];
                                        }
                                        toggleCiudad();
                                    }

                                    cc.addEventListener('change', fillProvincias);
                                    pv.addEventListener('change', toggleCiudad);
                                    if (pais) pais.addEventListener('change', toggleCiudad);
                                    // Tom Select se inicializa en el footer (DOMContentLoaded). Esperar a
                                    // que exista la instancia de provincia antes del primer fill.
                                    (function waitTS(n) {
                                        if (pv.tomselect || n > 40) { fillProvincias(); toggleCiudad(); return; }
                                        setTimeout(function () { waitTS(n + 1); }, 50);
                                    })(0);
                                })();
                                </script>

                                <div class="field">
                                    <label class="field__label" for="codigo_postal">Código Postal *</label>
                                    <input
                                        type="text"
                                        id="codigo_postal"
                                        name="codigo_postal"
                                        class="field__input"
                                        required
                                        placeholder="28001"
                                    >
                                </div>
                            </div>

                            <!-- SECCIÓN: Sobre tu negocio -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="file-text" class="icono"></i>
                                    Descripción y Servicios
                                </h3>

                                <div class="field">
                                    <label class="field__label" for="descripcion">Descripción del negocio * <span style="font-weight: normal; color: var(--color-seis);">(mínimo 150 caracteres)</span></label>
                                    <textarea
                                        id="descripcion"
                                        name="descripcion"
                                        class="field__textarea"
                                        required
                                        minlength="150"
                                        placeholder="Describe tu crematorio, años de experiencia, valores, servicios especiales, etc. Cuanto más detallada sea la descripción, mejor posicionará tu ficha."
                                        rows="6"
                                    ></textarea>
                                    <small id="descripcion-contador" class="field__hint">0 / 150 caracteres mínimo</small>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="servicios">Servicios que ofreces *</label>
                                    <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
                                        Toca los servicios típicos para añadirlos. Si sumas otros servicios o productos, <strong>separa cada uno con una coma</strong>.
                                    </small>
                                    <div id="servicios-chips" style="display: flex; flex-wrap: wrap; gap: var(--espacio-dos); margin-bottom: var(--espacio-tres);">
                                        <?php
                                        $serviciosSugeridos = [
                                            'Cremación individual', 'Cremación colectiva',
                                            'Recogida a domicilio', 'Entrega a domicilio',
                                            'Atención 24h', 'Sala velatoria',
                                            'Souvenirs / recuerdos', 'Urna incluida',
                                            'Carta de condolencias', 'Molde de huella',
                                        ];
                                        foreach ($serviciosSugeridos as $s):
                                        ?>
                                        <button type="button" class="serv-chip" data-serv="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <textarea
                                        id="servicios"
                                        name="servicios"
                                        class="field__textarea"
                                        required
                                        placeholder="Ej: Cremación individual, cremación grupal, urnas personalizadas, ceremonias de despedida, recogida a domicilio, etc."
                                        rows="4"
                                    ></textarea>
                                </div>
                                <style>
                                    .serv-chip {
                                        font-family: inherit; font-size: var(--fs-uno); cursor: pointer;
                                        background: var(--color-ocho); color: var(--color-seis);
                                        border: 1px solid var(--color-cinco); border-radius: 999px;
                                        padding: .35rem .85rem; transition: all .15s ease;
                                    }
                                    .serv-chip:hover { border-color: var(--color-uno); color: var(--color-uno); }
                                    .serv-chip.activo {
                                        background: var(--color-uno); color: var(--color-ocho);
                                        border-color: var(--color-uno); font-weight: 600;
                                    }
                                </style>
                                <script>
                                (function () {
                                    var ta    = document.getElementById('servicios');
                                    var chips = document.querySelectorAll('#servicios-chips .serv-chip');
                                    if (!ta || !chips.length) return;

                                    function tokens() {
                                        return ta.value.split(/[,\n]+/).map(function (t) { return t.trim(); })
                                                 .filter(function (t) { return t.length; });
                                    }
                                    function setTokens(arr) { ta.value = arr.join(', '); }
                                    function refresh() {
                                        var low = tokens().map(function (t) { return t.toLowerCase(); });
                                        chips.forEach(function (c) {
                                            var on = low.indexOf(c.dataset.serv.toLowerCase()) !== -1;
                                            c.classList.toggle('activo', on);
                                            c.setAttribute('aria-pressed', on ? 'true' : 'false');
                                        });
                                    }
                                    chips.forEach(function (c) {
                                        c.addEventListener('click', function () {
                                            var serv = c.dataset.serv;
                                            var arr  = tokens();
                                            var i    = arr.findIndex(function (t) { return t.toLowerCase() === serv.toLowerCase(); });
                                            if (i !== -1) { arr.splice(i, 1); } else { arr.push(serv); }
                                            setTokens(arr);
                                            refresh();
                                            ta.dispatchEvent(new Event('input'));
                                        });
                                    });
                                    ta.addEventListener('input', refresh);
                                    refresh();
                                })();
                                </script>

                                <div class="field">
                                    <label class="field__label" for="horarios">Horarios de atención *</label>
                                    <textarea
                                        id="horarios"
                                        name="horarios"
                                        class="field__textarea"
                                        required
                                        placeholder="Ej: Lunes a Viernes 9:00-18:00, Sábados 9:00-14:00, Domingos cerrado. Urgencias 24h."
                                        rows="3"
                                    ></textarea>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="precios">Precios o tarifas orientativas</label>
                                    <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
                                        Opcional. Si querés, contanos tus precios o rangos (ej: "Cremación individual desde 120€, colectiva 60€"). Nos ayuda a mostrar tu ficha más completa. Si preferís no indicarlos, dejá el campo vacío.
                                    </small>
                                    <textarea
                                        id="precios"
                                        name="precios"
                                        class="field__textarea"
                                        placeholder="Ej: Cremación individual perro pequeño desde 120€. Cremación colectiva 60€. Recogida a domicilio según zona."
                                        rows="3"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- SECCIÓN 3: Presencia en línea -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="globe" class="icono"></i>
                                    Presencia en Línea (Opcional)
                                </h3>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="sitio_web">Sitio web</label>
                                        <input
                                            type="url"
                                            id="sitio_web"
                                            name="sitio_web"
                                            class="field__input"
                                            placeholder="https://tusitio.com"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="google_maps_url">Google My Business / Maps</label>
                                        <input
                                            type="url"
                                            id="google_maps_url"
                                            name="google_maps_url"
                                            class="field__input"
                                            placeholder="https://g.page/tunegocio o URL de Google Maps"
                                        >
                                    </div>
                                </div>

                                <div class="registro-form-grid">
                                    <div class="field">
                                        <label class="field__label" for="whatsapp">WhatsApp</label>
                                        <input
                                            type="tel"
                                            id="whatsapp"
                                            name="whatsapp"
                                            class="field__input"
                                            placeholder="Número con código de país"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="facebook">Facebook</label>
                                        <input
                                            type="url"
                                            id="facebook"
                                            name="facebook"
                                            class="field__input"
                                            placeholder="https://facebook.com/tupagina"
                                        >
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="instagram">Instagram</label>
                                    <input
                                        type="url"
                                        id="instagram"
                                        name="instagram"
                                        class="field__input"
                                        placeholder="https://instagram.com/tuperfil"
                                    >
                                </div>
                            </div>

                            <!-- SECCIÓN 4: Imágenes (Opcional) -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="image" class="icono"></i>
                                    Imágenes (Opcional)
                                </h3>
                                <p class="panel__intro">
                                    Sube imágenes de tu crematorio para destacar en el directorio. Formatos: JPG, PNG, GIF o WebP. Máximo 5MB por imagen.
                                </p>

                                <!-- Logo -->
                                <div class="field" style="margin-bottom: var(--espacio-cuatro);">
                                    <label class="field__label" for="logo">Logo de tu crematorio</label>
                                    <div class="upload-zona" id="zona-logo" onclick="document.getElementById('logo').click()">
                                        <input
                                            type="file"
                                            id="logo"
                                            name="logo"
                                            accept="image/jpeg,image/png,image/gif,image/webp"
                                            style="display: none;"
                                            onchange="previsualizarLogo(this)"
                                        >
                                        <div id="preview-logo" class="upload-preview" style="display: none;"></div>
                                        <div id="placeholder-logo" class="upload-placeholder">
                                            <i data-lucide="upload" class="icono" style="width: 32px; height: 32px; color: var(--color-seis-claro);"></i>
                                            <span style="color: var(--color-seis-claro);">Haz clic para subir tu logo</span>
                                            <small style="color: var(--color-seis-claro);">Recomendado: 300x300 px</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Galería -->
                                <div class="field">
                                    <label class="field__label" for="galeria">Galería de imágenes (máximo 10)</label>
                                    <div class="upload-zona galeria" id="zona-galeria" onclick="document.getElementById('galeria').click()">
                                        <input
                                            type="file"
                                            id="galeria"
                                            name="galeria[]"
                                            accept="image/jpeg,image/png,image/gif,image/webp"
                                            multiple
                                            style="display: none;"
                                            onchange="previsualizarGaleria(this)"
                                        >
                                        <div id="preview-galeria" class="upload-galeria-preview"></div>
                                        <div id="placeholder-galeria" class="upload-placeholder">
                                            <i data-lucide="images" class="icono" style="width: 32px; height: 32px; color: var(--color-seis-claro);"></i>
                                            <span style="color: var(--color-seis-claro);">Haz clic para subir imágenes de tu local</span>
                                            <small style="color: var(--color-seis-claro);">Fachada, instalaciones, recepción, etc.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 5: Comentarios adicionales -->
                            <div class="panel">
                                <h3 class="panel__title">
                                    <i data-lucide="message-circle" class="icono"></i>
                                    Comentarios Adicionales (Opcional)
                                </h3>

                                <div class="field">
                                    <label class="field__label" for="comentarios_admin">¿Algo más que quieras contarnos?</label>
                                    <textarea
                                        id="comentarios_admin"
                                        name="comentarios_admin"
                                        class="field__textarea"
                                        placeholder="Preguntas, comentarios o información adicional que quieras compartir con nuestro equipo. Esta información no se publicará."
                                        rows="3"
                                    ></textarea>
                                    <small class="field__hint">Esta información es privada y no se mostrará en tu ficha.</small>
                                </div>
                            </div>

                            <!-- Consentimiento (ambos requeridos) + submit -->
                            <div style="display: flex; flex-direction: column; gap: var(--espacio-dos);">
                                <label class="field__opcion" style="align-items: flex-start; gap: 0.6rem;">
                                    <input type="checkbox" class="field__check" id="consentimiento" name="consentimiento" value="1">
                                    <span>Acepto que revisen mi información para incluirla en el directorio de Crematorios de Mascotas.</span>
                                </label>

                                <label class="field__opcion" style="align-items: flex-start; gap: 0.6rem;">
                                    <input type="checkbox" class="field__check" id="consentimiento_comunicaciones" name="consentimiento_comunicaciones" value="1">
                                    <span>Acepto recibir notificaciones útiles relacionadas con mi ficha de negocio y ofertas comerciales.</span>
                                </label>
                            </div>

                            <button type="submit" class="boton uno grande" style="width: 100%;">
                                Enviar Solicitud de Registro
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Página de éxito (oculta por defecto) -->
                <div id="exito-container" style="display: none;">
                    <div class="panel" style="text-align: center;">
                        <div class="caracteristica__icono" style="width: 72px; height: 72px;">
                            <i data-lucide="check-circle" class="icono" style="width: 36px; height: 36px;"></i>
                        </div>

                        <h2>¡Solicitud Enviada!</h2>

                        <p style="font-size: var(--fs-cuatro); color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">
                            Gracias por tu interés en unirte a nuestro directorio.<br>
                            Revisaremos tu información y te contactaremos pronto.
                        </p>

                        <div style="display: flex; gap: var(--espacio-tres); justify-content: center; flex-wrap: wrap;">
                            <a href="index.php" class="boton uno">
                                <i data-lucide="home" class="icono"></i>
                                Volver al Inicio
                            </a>

                            <a href="directorio.php" class="boton dos">
                                <i data-lucide="map" class="icono"></i>
                                Ver Directorio
                            </a>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR - Beneficios -->
                <aside class="registro-sidebar">

                    <!-- Beneficio 1 -->
                    <article class="panel">
                        <div class="caracteristica__icono" style="width: 56px; height: 56px; margin-bottom: var(--espacio-tres);">
                            <i data-lucide="users" class="icono"></i>
                        </div>
                        <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Mayor Visibilidad</h3>
                        <p style="color: var(--color-seis-claro); margin: 0;">
                            Llega a familias que buscan activamente servicios de cremación para sus mascotas.
                        </p>
                    </article>

                    <!-- Beneficio 2 -->
                    <article class="panel">
                        <div class="caracteristica__icono" style="width: 56px; height: 56px; margin-bottom: var(--espacio-tres);">
                            <i data-lucide="star" class="icono"></i>
                        </div>
                        <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Reseñas y Confianza</h3>
                        <p style="color: var(--color-seis-claro); margin: 0;">
                            Las reseñas de clientes satisfechos aumentan la confianza en tu negocio.
                        </p>
                    </article>

                    <!-- Beneficio 3 -->
                    <article class="panel">
                        <div class="caracteristica__icono" style="width: 56px; height: 56px; margin-bottom: var(--espacio-tres);">
                            <i data-lucide="plus-square" class="icono"></i>
                        </div>
                        <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">100% Gratuito</h3>
                        <p style="color: var(--color-seis-claro); margin: 0;">
                            El registro básico en nuestro directorio es completamente gratuito.
                        </p>
                    </article>

                </aside>
            </div>
        </div>
    </section>

    <!-- Script específico de la página -->
    <script>
        // Contador de caracteres para descripción
        document.addEventListener('DOMContentLoaded', function() {
            const descripcion = document.getElementById('descripcion');
            const contador = document.getElementById('descripcion-contador');

            function actualizarContador() {
                const length = descripcion.value.length;
                const minimo = 150;

                if (length < minimo) {
                    contador.innerHTML = `${length} / ${minimo} caracteres mínimo <span style="color: var(--color-siete);">(faltan ${minimo - length})</span>`;
                } else {
                    contador.innerHTML = `<span style="color: var(--color-tres);">${length} caracteres ✓</span>`;
                }
            }

            descripcion.addEventListener('input', actualizarContador);
            actualizarContador();
        });

        // Enviar formulario
        function enviarFormulario(event) {
            event.preventDefault();

            const alerta = document.getElementById('alerta');

            // Validar campos requeridos
            const contactoNombre = document.getElementById('contacto_nombre').value.trim();
            const contactoEmail = document.getElementById('contacto_email').value.trim();
            const contactoTelefono = document.getElementById('contacto_telefono').value.trim();
            const nombreNegocio = document.getElementById('nombre_negocio').value.trim();
            const emailClientes = document.getElementById('email_clientes').value.trim();
            const telefonoClientes = document.getElementById('telefono_clientes').value.trim();
            const pais = document.getElementById('pais').value.trim();
            const comunidad = document.getElementById('comunidad').value.trim();
            const provincia = document.getElementById('provincia').value.trim();
            const ciudad = document.getElementById('ciudad').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            const codigoPostal = document.getElementById('codigo_postal').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const servicios = document.getElementById('servicios').value.trim();
            const horarios = document.getElementById('horarios').value.trim();

            // Validaciones
            const errores = [];

            // Contacto comercial
            if (!contactoNombre) errores.push('El nombre de contacto es requerido');
            if (!contactoEmail || !validarEmail(contactoEmail)) errores.push('Email de contacto válido es requerido');
            if (!contactoTelefono) errores.push('El teléfono de contacto es requerido');

            // Datos del negocio
            if (!nombreNegocio) errores.push('El nombre del crematorio es requerido');
            if (!emailClientes || !validarEmail(emailClientes)) errores.push('Email para clientes válido es requerido');
            if (!telefonoClientes) errores.push('El teléfono para clientes es requerido');

            // Ubicación
            if (!pais) errores.push('El país es requerido');
            if (!comunidad) errores.push('La comunidad / región es requerida');
            if (!provincia) errores.push('La provincia es requerida');
            if (!ciudad) errores.push('La ciudad es requerida');
            if (!direccion) errores.push('La dirección es requerida');
            if (!codigoPostal) errores.push('El código postal es requerido');

            // Contenido
            if (!descripcion) {
                errores.push('La descripción es requerida');
            } else if (descripcion.length < 150) {
                errores.push(`La descripción debe tener al menos 150 caracteres (actual: ${descripcion.length})`);
            }
            if (!servicios) errores.push('Los servicios son requeridos');
            if (!horarios) errores.push('Los horarios son requeridos');

            // Lleva el scroll + foco al primer campo de la lista que esté vacío
            // (orden de la página, de arriba hacia abajo).
            function irACampo(id) {
                var el = document.getElementById(id);
                if (!el) return;
                var cont = el.closest('.field, .field__opcion') || el;
                cont.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () {
                    try { (el.tomselect ? el.tomselect : el).focus(); } catch (e) {}
                }, 350);
            }
            function primerFaltante(ids) {
                for (var i = 0; i < ids.length; i++) {
                    var el = document.getElementById(ids[i]);
                    if (!el) continue;
                    var vacio = (el.type === 'checkbox') ? !el.checked : !String(el.value || '').trim();
                    if (vacio) return ids[i];
                }
                return null;
            }
            var ORDEN_CAMPOS = [
                'contacto_nombre', 'contacto_email', 'contacto_telefono',
                'nombre_negocio', 'email_clientes', 'telefono_clientes',
                'direccion', 'pais', 'comunidad', 'provincia', 'ciudad',
                'codigo_postal', 'descripcion', 'servicios', 'horarios',
                'consentimiento', 'consentimiento_comunicaciones'
            ];

            if (errores.length > 0) {
                const lista = 'Faltan datos obligatorios:<br>• ' + errores.join('<br>• ');
                if (window.toast) { toast.error(lista); }
                else { mostrarAlerta(errores.join('<br>'), 'error'); }
                var faltante = primerFaltante(ORDEN_CAMPOS);
                if (faltante) irACampo(faltante);
                return;
            }

            // Consentimientos obligatorios (ambos)
            const consentimiento = document.getElementById('consentimiento');
            const consentimientoCom = document.getElementById('consentimiento_comunicaciones');
            if (!consentimiento.checked || !consentimientoCom.checked) {
                const msg = 'Para enviar la solicitud tienes que marcar ambas casillas de consentimiento.';
                if (window.toast) { toast.error(msg); } else { mostrarAlerta(msg, 'error'); }
                irACampo(!consentimiento.checked ? 'consentimiento' : 'consentimiento_comunicaciones');
                return;
            }

            // Enviar vía AJAX
            const boton = document.querySelector('#formulario-registro button[type="submit"]');
            boton.disabled = true;
            boton.textContent = 'Enviando...';

            const formData = new FormData(document.getElementById('formulario-registro'));
            formData.append('page_url', window.location.href);

            fetch('<?php echo BASE_URL; ?>/procesar-registro.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    if (typeof dataLayer !== 'undefined') {
                        dataLayer.push({
                            'event': 'form_submit_success',
                            'form_id': data.form_id,
                            'form_name': data.form_name
                        });
                    }
                    mostrarExito();
                } else {
                    mostrarAlerta(data.mensaje || 'Error al enviar. Inténtalo de nuevo.', 'error');
                    boton.disabled = false;
                    boton.textContent = 'Enviar Solicitud de Registro';
                }
            })
            .catch(() => {
                mostrarAlerta('Error de conexión. Inténtalo de nuevo.', 'error');
                boton.disabled = false;
                boton.textContent = 'Enviar Solicitud de Registro';
            });
        }

        // Mostrar página de éxito
        function mostrarExito() {
            document.getElementById('formulario-container').style.display = 'none';
            document.getElementById('exito-container').style.display = 'block';

            // Scroll al inicio
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Reinicializar íconos
            setTimeout(() => lucide.createIcons(), 100);
        }

        // Mostrar alerta
        function mostrarAlerta(mensaje, tipo) {
            const alerta = document.getElementById('alerta');
            alerta.innerHTML = mensaje;
            alerta.className = 'alerta ' + tipo;
            alerta.style.display = 'flex';

            // Scroll hacia la alerta
            alerta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Validar email
        function validarEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        // Previsualizar logo
        function previsualizarLogo(input) {
            const preview = document.getElementById('preview-logo');
            const placeholder = document.getElementById('placeholder-logo');

            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Validar tamaño
                if (file.size > 5 * 1024 * 1024) {
                    mostrarAlerta('El logo excede 5MB', 'error');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Logo preview" style="max-width: 150px; max-height: 150px; border-radius: var(--radio-uno);">
                        <button type="button" onclick="eliminarLogo()" style="position: absolute; top: 5px; right: 5px; background: var(--color-siete); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer;">×</button>
                    `;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }

        function eliminarLogo() {
            document.getElementById('logo').value = '';
            document.getElementById('preview-logo').style.display = 'none';
            document.getElementById('preview-logo').innerHTML = '';
            document.getElementById('placeholder-logo').style.display = 'flex';
        }

        // Previsualizar galería
        function previsualizarGaleria(input) {
            const preview = document.getElementById('preview-galeria');
            const placeholder = document.getElementById('placeholder-galeria');

            if (input.files && input.files.length > 0) {
                // Limitar a 10 imágenes
                if (input.files.length > 10) {
                    mostrarAlerta('Máximo 10 imágenes permitidas', 'error');
                    return;
                }

                preview.innerHTML = '';
                let errores = [];

                Array.from(input.files).forEach((file, index) => {
                    if (file.size > 5 * 1024 * 1024) {
                        errores.push(`Imagen ${index + 1} excede 5MB`);
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'galeria-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Imagen ${index + 1}">
                            <span class="galeria-numero">${index + 1}</span>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });

                if (errores.length > 0) {
                    mostrarAlerta(errores.join('<br>'), 'error');
                }

                placeholder.style.display = 'none';
            }
        }

        function eliminarGaleria() {
            document.getElementById('galeria').value = '';
            document.getElementById('preview-galeria').innerHTML = '';
            document.getElementById('placeholder-galeria').style.display = 'flex';
        }
    </script>

<?php include 'includes/footer.php'; ?>
