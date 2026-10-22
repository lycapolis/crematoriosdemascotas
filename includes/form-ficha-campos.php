<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CAMPOS COMPARTIDOS DEL FORMULARIO DE FICHA DE NEGOCIO
 * ═══════════════════════════════════════════════════════════
 *
 * Lo incluyen:
 *   - registrar-negocio.php  → modo 'alta' (negocio nuevo; todo lo esencial es obligatorio)
 *   - verificar-ficha.php    → modo 'actualizacion' (ficha existente, precargada; nada es obligatorio salvo
 *                              los datos de contacto: un campo vacío significa «no cambiar»)
 *
 * Variables que debe definir quien lo incluye:
 *   $ffModo   'alta' | 'actualizacion'
 *   $ffDatos  array clave => valor con lo que se precarga (puede estar vacío)
 *   $GEO_ES   cascada comunidad → provincias (includes/geo_es.php)
 *
 * Clases/ids principales: .ff-honeypot, .ff-grid, .ff-chip, #servicios-chips, #formulario-registro (lo pone la página).
 * No incluye el <form>, los consentimientos ni el botón de envío: eso lo decide cada página.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/actualizacion-ficha.php';   // lista de servicios sugeridos

$ffModo  = ($ffModo ?? 'alta') === 'actualizacion' ? 'actualizacion' : 'alta';
$ffDatos = is_array($ffDatos ?? null) ? $ffDatos : [];
$ffEsAlta = $ffModo === 'alta';
$ffReq    = $ffEsAlta ? 'required' : '';
$ffAst    = $ffEsAlta ? ' *' : '';
$ffV      = fn(string $k, string $def = '') => htmlspecialchars((string)($ffDatos[$k] ?? $def), ENT_QUOTES, 'UTF-8');

// Comunidad: la de los datos, o la que contiene la provincia precargada
$ffComunidad = (string)($ffDatos['comunidad'] ?? '');
if ($ffComunidad === '' && !empty($ffDatos['provincia'])) {
    foreach ($GEO_ES as $ccaa => $provs) {
        foreach ($provs as $p) if (mb_strtolower($p) === mb_strtolower((string)$ffDatos['provincia'])) { $ffComunidad = $ccaa; break 2; }
    }
}
$ffPais = (string)($ffDatos['pais'] ?? 'España');
$ffServiciosSugeridos = array_keys(actFichaServiciosFlags());
?>
<style>
    .ff-honeypot { position: absolute; left: -9999px; top: -9999px; height: 0; width: 0; overflow: hidden; }
    .ff-grid { display: flex; flex-direction: column; }
    @media (min-width: 768px) { .ff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro); } }
    .verificar__formulario .ff-grid { display: flex; flex-direction: column; gap: 0; }
    .ff-chip {
        font-family: inherit; font-size: var(--fs-uno); cursor: pointer;
        background: var(--color-ocho); color: var(--color-seis);
        border: 1px solid var(--color-cinco); border-radius: 999px;
        padding: .35rem .85rem; transition: all .15s ease;
    }
    .ff-chip:hover { border-color: var(--color-uno); color: var(--color-uno); }
    .ff-chip.activo { background: var(--color-uno); color: var(--color-ocho); border-color: var(--color-uno); font-weight: 600; }
</style>

<!-- Honeypot: campo invisible que solo los bots completan -->
<div class="ff-honeypot" aria-hidden="true">
    <label for="website-url-extra">No completar este campo</label>
    <input type="text" id="website-url-extra" name="website_url" tabindex="-1" autocomplete="off" value="">
</div>
<!-- Time-trap: timestamp del render para detectar envíos demasiado rápidos -->
<input type="hidden" name="form_render_ts" value="<?php echo time(); ?>">

<!-- SECCIÓN 1: Datos de contacto comercial -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="user" class="icono"></i>
        <?php echo $ffEsAlta ? 'Datos de contacto comercial' : 'Tus datos de contacto'; ?>
    </h3>
    <p class="panel__intro">
        Estos datos son privados y solo los usaremos para comunicarnos contigo<?php echo $ffEsAlta ? '' : ' sobre esta actualización'; ?>.
    </p>

    <div class="field">
        <label class="field__label" for="contacto_nombre">Nombre completo *</label>
        <input type="text" id="contacto_nombre" name="contacto_nombre" class="field__input" required
               placeholder="Tu nombre completo" value="<?php echo $ffV('contacto_nombre'); ?>">
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="contacto_email">Email *</label>
            <input type="email" id="contacto_email" name="contacto_email" class="field__input" required
                   placeholder="tu@email.com" value="<?php echo $ffV('contacto_email'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="contacto_telefono">Teléfono *</label>
            <input type="tel" id="contacto_telefono" name="contacto_telefono" class="field__input" required
                   placeholder="Ej: +34 600 000 000" value="<?php echo $ffV('contacto_telefono'); ?>">
        </div>
    </div>
</div>

<!-- SECCIÓN 2: Datos del negocio -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="home" class="icono"></i>
        Datos del negocio
    </h3>
    <?php if (!$ffEsAlta): ?>
    <p class="panel__intro">Están precargados con lo que ahora muestra tu ficha. Cambia solo lo que quieras actualizar; nuestro equipo lo revisará antes de publicarlo.</p>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="nombre_negocio">Nombre del negocio<?php echo $ffAst; ?></label>
        <input type="text" id="nombre_negocio" name="nombre_negocio" class="field__input" <?php echo $ffReq; ?>
               <?php echo $ffEsAlta ? '' : 'readonly'; ?>
               placeholder="Nombre comercial de tu negocio" value="<?php echo $ffV('nombre_negocio'); ?>">
        <?php if ($ffEsAlta): ?>
        <small class="field__hint">
            Solo el nombre comercial, <strong>sin la ciudad</strong>. Ej: «Crematorio Huella Amiga» (no «Crematorio Huella Amiga Madrid»). La URL no se puede cambiar luego.
        </small>
        <?php else: ?>
        <small class="field__hint">El nombre y la dirección web de la ficha no se cambian desde aquí. Si necesitas cambiarlos, escríbenos.</small>
        <?php endif; ?>
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="email_clientes">Email para clientes<?php echo $ffAst; ?></label>
            <input type="email" id="email_clientes" name="email_clientes" class="field__input" <?php echo $ffReq; ?>
                   placeholder="contacto@tunegocio.com" value="<?php echo $ffV('email_clientes'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="telefono_clientes">Teléfono para clientes<?php echo $ffAst; ?></label>
            <input type="tel" id="telefono_clientes" name="telefono_clientes" class="field__input" <?php echo $ffReq; ?>
                   placeholder="Ej: +34 900 000 000" value="<?php echo $ffV('telefono_clientes'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="direccion">Dirección completa<?php echo $ffAst; ?></label>
        <input type="text" id="direccion" name="direccion" class="field__input" <?php echo $ffReq; ?>
               placeholder="Calle, número, localidad" value="<?php echo $ffV('direccion'); ?>">
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="pais">País<?php echo $ffAst; ?></label>
            <select id="pais" name="pais" class="field__select field__select--enhanced" <?php echo $ffReq; ?>>
                <option value="España" <?php echo $ffPais !== 'Otro' ? 'selected' : ''; ?>>España</option>
                <option value="Otro" <?php echo $ffPais === 'Otro' ? 'selected' : ''; ?>>Otro</option>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="comunidad">Comunidad / Región<?php echo $ffAst; ?></label>
            <select id="comunidad" name="comunidad" class="field__select field__select--enhanced" <?php echo $ffReq; ?>>
                <option value="">— Elige comunidad / región —</option>
                <?php foreach (array_keys($GEO_ES) as $ccaa): ?>
                <option value="<?php echo htmlspecialchars($ccaa); ?>" <?php echo $ccaa === $ffComunidad ? 'selected' : ''; ?>><?php echo htmlspecialchars($ccaa); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="provincia">Provincia<?php echo $ffAst; ?></label>
            <select id="provincia" name="provincia" class="field__select field__select--enhanced" <?php echo $ffReq; ?>
                    data-valor="<?php echo $ffV('provincia'); ?>">
                <option value="">— Elige primero la comunidad —</option>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="ciudad">Ciudad<?php echo $ffAst; ?></label>
            <input type="text" id="ciudad" name="ciudad" class="field__input" <?php echo $ffReq; ?>
                   <?php echo (!$ffEsAlta && !empty($ffDatos['provincia'])) ? '' : 'disabled'; ?>
                   placeholder="Elige antes país, comunidad y provincia" value="<?php echo $ffV('ciudad'); ?>">
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

        var provPrecargada   = pv.getAttribute('data-valor') || '';
        var ciudadPrecargada = ciudad ? ciudad.value : '';

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

        // Datos precargados (modo actualización): provincia y ciudad actuales, una sola vez al iniciar
        function aplicarPrecarga() {
            if (!provPrecargada) return;
            var lista = GEO[cc.value] || [];
            if (lista.indexOf(provPrecargada) === -1) return;
            if (pv.tomselect) pv.tomselect.setValue(provPrecargada, true); else pv.value = provPrecargada;
            toggleCiudad();
            if (ciudad && !ciudad.value) ciudad.value = ciudadPrecargada;
        }

        cc.addEventListener('change', fillProvincias);
        pv.addEventListener('change', toggleCiudad);
        if (pais) pais.addEventListener('change', toggleCiudad);
        // Tom Select se inicializa en el footer (DOMContentLoaded). Esperar a
        // que exista la instancia de provincia antes del primer fill.
        (function waitTS(n) {
            if (pv.tomselect || n > 40) { fillProvincias(); aplicarPrecarga(); return; }
            setTimeout(function () { waitTS(n + 1); }, 50);
        })(0);
    })();
    </script>

    <div class="field">
        <label class="field__label" for="codigo_postal">Código postal<?php echo $ffAst; ?></label>
        <input type="text" id="codigo_postal" name="codigo_postal" class="field__input" <?php echo $ffReq; ?>
               placeholder="28001" value="<?php echo $ffV('codigo_postal'); ?>">
    </div>
</div>

<?php if (!$ffEsAlta): ?>
<!-- SECCIÓN: Zona de cobertura (solo al actualizar una ficha existente) -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="map" class="icono"></i>
        Zona de cobertura
    </h3>
    <p class="panel__intro">¿En qué zonas atiendes? Así las familias de esas zonas te encuentran.</p>

    <div class="field">
        <label class="field__label" for="zona_cobertura">Provincias o comunidades que cubres</label>
        <input type="text" id="zona_cobertura" name="zona_cobertura" class="field__input"
               placeholder="Ej: Madrid, Toledo, Guadalajara" value="<?php echo $ffV('zona_cobertura'); ?>">
        <small class="field__hint">Sepáralas con comas.</small>
    </div>
    <div class="field">
        <label class="field__label" for="ciudades_cobertura">Ciudades o municipios que cubres</label>
        <textarea id="ciudades_cobertura" name="ciudades_cobertura" class="field__textarea" rows="2"
                  placeholder="Ej: Alcalá de Henares, Getafe, Leganés"><?php echo $ffV('ciudades_cobertura'); ?></textarea>
        <small class="field__hint">Sepáralas con comas.</small>
    </div>
</div>
<?php endif; ?>

<!-- SECCIÓN: Descripción y servicios -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="file-text" class="icono"></i>
        Descripción y servicios
    </h3>

    <div class="field">
        <label class="field__label" for="descripcion">Descripción del negocio<?php echo $ffAst; ?> <span style="font-weight: normal; color: var(--color-seis);">(mínimo 150 caracteres)</span></label>
        <textarea id="descripcion" name="descripcion" class="field__textarea" <?php echo $ffReq; ?> minlength="150" rows="6"
                  placeholder="Describe tu negocio, años de experiencia, valores, servicios especiales, etc. Cuanto más detallada sea la descripción, mejor posicionará tu ficha."><?php echo $ffV('descripcion'); ?></textarea>
        <small id="descripcion-contador" class="field__hint">0 / 150 caracteres mínimo</small>
    </div>

    <div class="field">
        <label class="field__label" for="servicios">Servicios que ofreces<?php echo $ffAst; ?></label>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
            Toca los servicios típicos para añadirlos. Si sumas otros servicios o productos, <strong>separa cada uno con una coma</strong>.
        </small>
        <div id="servicios-chips" style="display: flex; flex-wrap: wrap; gap: var(--espacio-dos); margin-bottom: var(--espacio-tres);">
            <?php foreach ($ffServiciosSugeridos as $s): ?>
            <button type="button" class="ff-chip" data-serv="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></button>
            <?php endforeach; ?>
        </div>
        <textarea id="servicios" name="servicios" class="field__textarea" <?php echo $ffReq; ?> rows="4"
                  placeholder="Ej: Cremación individual, cremación grupal, urnas personalizadas, ceremonias de despedida, recogida a domicilio, etc."><?php echo $ffV('servicios'); ?></textarea>
    </div>
    <script>
    (function () {
        var ta    = document.getElementById('servicios');
        var chips = document.querySelectorAll('#servicios-chips .ff-chip');
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
        <label class="field__label" for="horarios">Horarios de atención<?php echo $ffAst; ?></label>
        <textarea id="horarios" name="horarios" class="field__textarea" <?php echo $ffReq; ?> rows="3"
                  placeholder="Ej: Lunes a viernes 9:00-18:00, sábados 9:00-14:00, domingos cerrado. Urgencias 24h."><?php echo $ffV('horarios'); ?></textarea>
    </div>

    <div class="field">
        <label class="field__label" for="precios">Precios o tarifas orientativas</label>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
            Opcional. Si quieres, cuéntanos tus precios o rangos (ej: «Cremación individual desde 120 €, colectiva 60 €»). Nos ayuda a mostrar tu ficha más completa. Si prefieres no indicarlos, deja el campo vacío.
        </small>
        <textarea id="precios" name="precios" class="field__textarea" rows="3"
                  placeholder="Ej: Cremación individual perro pequeño desde 120 €. Cremación colectiva 60 €. Recogida a domicilio según zona."><?php echo $ffV('precios'); ?></textarea>
    </div>
</div>

<!-- SECCIÓN 3: Presencia en línea -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="globe" class="icono"></i>
        Presencia en línea (opcional)
    </h3>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="sitio_web">Sitio web</label>
            <input type="url" id="sitio_web" name="sitio_web" class="field__input" placeholder="https://tusitio.com" value="<?php echo $ffV('sitio_web'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="google_maps_url">Google Business / Maps</label>
            <input type="url" id="google_maps_url" name="google_maps_url" class="field__input"
                   placeholder="https://g.page/tunegocio o URL de Google Maps" value="<?php echo $ffV('google_maps_url'); ?>">
        </div>
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" class="field__input" placeholder="Número con código de país" value="<?php echo $ffV('whatsapp'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="facebook">Facebook</label>
            <input type="url" id="facebook" name="facebook" class="field__input" placeholder="https://facebook.com/tupagina" value="<?php echo $ffV('facebook'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="instagram">Instagram</label>
        <input type="url" id="instagram" name="instagram" class="field__input" placeholder="https://instagram.com/tuperfil" value="<?php echo $ffV('instagram'); ?>">
    </div>
</div>

<!-- SECCIÓN 4: Imágenes (opcional) -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="image" class="icono"></i>
        Imágenes (opcional)
    </h3>
    <p class="panel__intro">
        Sube imágenes de tu negocio para destacar en el directorio. Formatos: JPG, PNG, GIF o WebP. Máximo 5 MB por imagen.
        <?php if (!$ffEsAlta): ?>Las revisaremos antes de publicarlas.<?php endif; ?>
    </p>

    <!-- Logo -->
    <div class="field" style="margin-bottom: var(--espacio-cuatro);">
        <label class="field__label" for="logo">Logo de tu negocio</label>
        <div class="upload-zona" id="zona-logo" onclick="document.getElementById('logo').click()">
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp"
                   style="display: none;" onchange="previsualizarLogo(this)">
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
            <input type="file" id="galeria" name="galeria[]" accept="image/jpeg,image/png,image/gif,image/webp"
                   multiple style="display: none;" onchange="previsualizarGaleria(this)">
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
        Comentarios adicionales (opcional)
    </h3>

    <div class="field">
        <label class="field__label" for="comentarios_admin">¿Algo más que quieras contarnos?</label>
        <textarea id="comentarios_admin" name="comentarios_admin" class="field__textarea" rows="3"
                  placeholder="Preguntas, comentarios o información adicional que quieras compartir con nuestro equipo. Esta información no se publicará."><?php echo $ffV('comentarios_admin'); ?></textarea>
        <small class="field__hint">Esta información es privada y no se mostrará en tu ficha.</small>
    </div>
</div>

<script>
// ─── Utilidades del formulario de ficha (contador de descripción y previsualización de imágenes) ───
document.addEventListener('DOMContentLoaded', function () {
    var descripcion = document.getElementById('descripcion');
    var contador = document.getElementById('descripcion-contador');
    if (!descripcion || !contador) return;
    var minimo = 150;
    function actualizar() {
        var n = descripcion.value.length;
        if (n < minimo) {
            contador.innerHTML = n + ' / ' + minimo + ' caracteres mínimo <span style="color: var(--color-siete);">(faltan ' + (minimo - n) + ')</span>';
        } else {
            contador.innerHTML = '<span style="color: var(--color-tres);">' + n + ' caracteres ✓</span>';
        }
    }
    descripcion.addEventListener('input', actualizar);
    actualizar();
});

function ffAlerta(mensaje) {
    if (typeof mostrarAlerta === 'function') { mostrarAlerta(mensaje, 'error'); }
    else if (window.toast) { toast.error(mensaje); }
    else { alert(mensaje.replace(/<br>/g, '\n')); }
}

function previsualizarLogo(input) {
    var preview = document.getElementById('preview-logo');
    var placeholder = document.getElementById('placeholder-logo');
    if (input.files && input.files[0]) {
        var file = input.files[0];
        if (file.size > 5 * 1024 * 1024) { ffAlerta('El logo excede 5 MB'); input.value = ''; return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo preview" style="max-width: 150px; max-height: 150px; border-radius: var(--radio-uno);">' +
                '<button type="button" onclick="eliminarLogo()" style="position: absolute; top: 5px; right: 5px; background: var(--color-siete); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer;">×</button>';
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

function previsualizarGaleria(input) {
    var preview = document.getElementById('preview-galeria');
    var placeholder = document.getElementById('placeholder-galeria');
    if (input.files && input.files.length > 0) {
        if (input.files.length > 10) { ffAlerta('Máximo 10 imágenes permitidas'); return; }
        preview.innerHTML = '';
        var errores = [];
        Array.from(input.files).forEach(function (file, index) {
            if (file.size > 5 * 1024 * 1024) { errores.push('Imagen ' + (index + 1) + ' excede 5 MB'); return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                var div = document.createElement('div');
                div.className = 'galeria-item';
                div.innerHTML = '<img src="' + e.target.result + '" alt="Imagen ' + (index + 1) + '"><span class="galeria-numero">' + (index + 1) + '</span>';
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
        if (errores.length > 0) ffAlerta(errores.join('<br>'));
        placeholder.style.display = 'none';
    }
}

function eliminarGaleria() {
    document.getElementById('galeria').value = '';
    document.getElementById('preview-galeria').innerHTML = '';
    document.getElementById('placeholder-galeria').style.display = 'flex';
}
</script>
