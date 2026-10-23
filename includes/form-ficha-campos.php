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
 *   $ffTrasContacto  (opcional) HTML que se pinta justo debajo del panel de datos de contacto
 *   $ffCiudadesListado (opcional, modo actualización) ciudades para el selector de cobertura
 *
 * Clases/ids principales: .ff-honeypot, .ff-grid, .ff-chip, .ff-nube (data-target, data-modo), #formulario-registro (lo pone la página).
 * Nubes de etiquetas (.ff-nube): data-modo="lista" (servicios: alterna una etiqueta en una lista con comas),
 * "linea" (horarios: alterna una línea) y "frase" (precios: añade una idea de redacción).
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

// Servicios por categoría. Los 10 que tienen casilla propia en la ficha (actFichaServiciosFlags) van tal cual;
// el resto son texto libre que revisa el equipo.
$ffServiciosCategorias = actFichaServiciosCatalogo();
$ffHorariosSugeridos = actFichaHorariosSugeridos();
$ffPreciosIdeas = [
    'Cremación individual desde … €', 'Cremación colectiva desde … €', 'Recogida a domicilio desde … € (según zona)',
    'Según el peso: hasta 10 kg … €, de 10 a 30 kg … €', 'Urna básica incluida; urnas personalizadas desde … €', 'Presupuesto sin compromiso',
];

// Cobertura: lo guardado en la ficha se separa por comas; lo que no esté en las listas se añade para no perderlo
$ffSepara = fn(string $t) => array_values(array_filter(array_map('trim', explode(',', $t)), fn($x) => $x !== ''));
$ffZonasSel    = $ffSepara((string)($ffDatos['zona_cobertura'] ?? ''));
$ffCiudadesSel = $ffSepara((string)($ffDatos['ciudades_cobertura'] ?? ''));
// Zonas ofrecidas: las comunidades y, solo de las que tienen varias provincias, sus provincias (sin duplicados
// como «Cantabria» o «Madrid» + «Comunidad de Madrid»)
$ffZonasConocidas = [];
$ffZonasProvincias = [];
foreach ($GEO_ES as $ccaa => $provs) {
    $ffZonasConocidas[mb_strtolower($ccaa)] = true;
    if (count($provs) > 1) foreach ($provs as $pv) { $ffZonasConocidas[mb_strtolower($pv)] = true; $ffZonasProvincias[] = $pv; }
}
$ffZonasExtra = array_values(array_filter($ffZonasSel, fn($z) => !isset($ffZonasConocidas[mb_strtolower($z)])));
$ffCiudadesLista = is_array($ffCiudadesListado ?? null) ? $ffCiudadesListado : [];
$ffCiudadesMinus = array_map('mb_strtolower', $ffCiudadesLista);
foreach ($ffCiudadesSel as $ffCs) if (!in_array(mb_strtolower($ffCs), $ffCiudadesMinus, true)) $ffCiudadesLista[] = $ffCs;
$ffMin = fn(array $a) => array_map('mb_strtolower', $a);
$ffSeleccionada = fn(string $v, array $sel) => in_array(mb_strtolower($v), array_map('mb_strtolower', $sel), true) ? 'selected' : '';
?>
<style>
    .ff-honeypot { position: absolute; left: -9999px; top: -9999px; height: 0; width: 0; overflow: hidden; }
    .ff-grid { display: flex; flex-direction: column; }
    @media (min-width: 768px) { .ff-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro); } }
    .ff-grid--dir { grid-template-columns: 2fr 1fr; }
    .ff-chip {
        font-family: inherit; font-size: var(--fs-uno); cursor: pointer;
        background: var(--color-cinco); color: var(--color-dos);
        border: 1px solid rgba(90, 62, 47, .45); border-radius: 999px;
        padding: .35rem .85rem; transition: all .15s ease;
    }
    .ff-chip:hover { border-color: var(--color-uno); color: var(--color-uno); background: var(--color-ocho); }
    .ff-chip.activo { background: var(--color-uno); color: var(--color-ocho); border-color: var(--color-uno); font-weight: 600; }
    /* Las tres nubes comparten tamaño, colores y estado activo; cambian solo la forma, el borde y un icono inicial */
    .ff-chip { display: inline-flex; align-items: center; gap: .4rem; }
    .ff-chip .icono { width: 14px; height: 14px; flex: none; }
    .ff-nube[data-modo="linea"] .ff-chip { border-radius: var(--radio-dos); font-variant-numeric: tabular-nums; }   /* Horarios: rectángulo suave + reloj */
    .ff-nube[data-modo="frase"] .ff-chip { border-style: dashed; }                                                   /* Precios: borde discontinuo + etiqueta */
    .ff-mas-redes > summary { cursor: pointer; color: var(--color-uno); font-weight: 600; margin-bottom: var(--espacio-tres); }
    .ff-mas-redes[open] > summary { margin-bottom: var(--espacio-tres); }
    .ff-ayuda { margin-top: var(--espacio-uno); font-size: var(--fs-uno); }
    .ff-ayuda > summary { cursor: pointer; color: var(--color-uno); }
    .ff-ayuda--ia { margin: 0 0 var(--espacio-tres); font-size: var(--fs-dos); }
    .ff-ayuda--ia p { margin: var(--espacio-uno) 0 var(--espacio-dos); color: var(--color-seis); }
    .ff-ayuda ol { margin: var(--espacio-uno) 0 0; padding-left: 1.2rem; color: var(--color-seis); line-height: 1.6; }
    .ff-nube { margin-bottom: var(--espacio-tres); }
    .ff-nube__grupo { margin-bottom: var(--espacio-dos); }
    .ff-nube__titulo { display: block; font-size: var(--fs-uno); color: var(--color-seis); margin-bottom: var(--espacio-uno); }
    .ff-nube__chips { display: flex; flex-wrap: wrap; gap: var(--espacio-dos); }
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
        <?php if ($ffEsAlta): ?>
        Estos datos son privados y solo los usaremos para comunicarnos contigo.
        <?php else: ?>
        Para avisarte cuando revisemos tu actualización. <strong>No se publican.</strong>
        <?php endif; ?>
    </p>

    <div class="field">
        <label class="field__label" for="contacto_nombre"><?php echo $ffEsAlta ? 'Nombre completo' : 'Tu nombre'; ?> *</label>
        <input type="text" id="contacto_nombre" name="contacto_nombre" class="field__input" required
               placeholder="<?php echo $ffEsAlta ? 'Tu nombre completo' : 'Nombre y apellidos'; ?>" value="<?php echo $ffV('contacto_nombre'); ?>">
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="contacto_email"><?php echo $ffEsAlta ? 'Email' : 'Tu email'; ?> *</label>
            <input type="email" id="contacto_email" name="contacto_email" class="field__input" required
                   placeholder="tu@email.com" value="<?php echo $ffV('contacto_email'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="contacto_telefono"><?php echo $ffEsAlta ? 'Teléfono' : 'Tu teléfono'; ?> *</label>
            <input type="tel" id="contacto_telefono" name="contacto_telefono" class="field__input" required
                   placeholder="+34 612 34 56 78" value="<?php echo $ffV('contacto_telefono'); ?>">
        </div>
    </div>
</div>

<?php if (!empty($ffTrasContacto)) echo $ffTrasContacto;   // hueco opcional bajo «Tus datos de contacto» (lo usa verificar-ficha.php) ?>

<!-- SECCIÓN 2: Datos del negocio -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="home" class="icono"></i>
        Datos del negocio
    </h3>

    <div class="field">
        <label class="field__label" for="nombre_negocio">Nombre del negocio<?php echo $ffAst; ?></label>
        <input type="text" id="nombre_negocio" name="nombre_negocio" class="field__input" <?php echo $ffReq; ?>
               placeholder="Nombre comercial de tu negocio" value="<?php echo $ffV('nombre_negocio'); ?>">
        <?php if ($ffEsAlta): ?>
        <small class="field__hint">
            Solo el nombre comercial, <strong>sin la ciudad</strong>. Ej: «Crematorio Huella Amiga» (no «Crematorio Huella Amiga Madrid»). La URL no se puede cambiar luego.
        </small>
        <?php else: ?>
        <small class="field__hint">Solo el nombre comercial, sin ciudad ni servicios. No añadas palabras clave aquí.</small>
        <?php endif; ?>
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="email_clientes"><?php echo $ffEsAlta ? 'Email para clientes' : 'Email de atención al público'; ?><?php echo $ffAst; ?></label>
            <input type="email" id="email_clientes" name="email_clientes" class="field__input" <?php echo $ffReq; ?>
                   placeholder="contacto@tunegocio.com" value="<?php echo $ffV('email_clientes'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="telefono_clientes"><?php echo $ffEsAlta ? 'Teléfono para clientes' : 'Teléfono de atención al público'; ?><?php echo $ffAst; ?></label>
            <input type="tel" id="telefono_clientes" name="telefono_clientes" class="field__input" <?php echo $ffReq; ?>
                   placeholder="+34 912 34 56 78" value="<?php echo $ffV('telefono_clientes'); ?>">
        </div>
    </div>

    <?php if (!$ffEsAlta): ?>
    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" class="field__input" placeholder="+34 612 34 56 78" value="<?php echo $ffV('whatsapp'); ?>">
            <label class="field__opcion" style="margin-top: var(--espacio-uno);">
                <input type="checkbox" class="field__check" id="whatsapp_mismo"><span>Es el mismo número que el teléfono</span>
            </label>
        </div>
    </div>
    <script>
    (function () {
        var wa = document.getElementById('whatsapp'), tel = document.getElementById('telefono_clientes'), chk = document.getElementById('whatsapp_mismo');
        if (!wa || !tel || !chk) return;
        function digitos(v) { return String(v || '').replace(/\D/g, '').slice(-9); }
        chk.checked = digitos(wa.value) !== '' && digitos(wa.value) === digitos(tel.value);
        chk.addEventListener('change', function () { if (chk.checked) wa.value = tel.value; });
        tel.addEventListener('input', function () { if (chk.checked) wa.value = tel.value; });
        wa.addEventListener('input', function () { chk.checked = digitos(wa.value) !== '' && digitos(wa.value) === digitos(tel.value); });
    })();
    </script>
    <?php endif; ?>

    <?php if ($ffEsAlta): ?>
    <div class="ff-grid ff-grid--dir">
        <div class="field">
            <label class="field__label" for="direccion">Dirección completa<?php echo $ffAst; ?></label>
            <input type="text" id="direccion" name="direccion" class="field__input" <?php echo $ffReq; ?>
                   placeholder="Calle, número, localidad" value="<?php echo $ffV('direccion'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="codigo_postal">Código postal<?php echo $ffAst; ?></label>
            <input type="text" id="codigo_postal" name="codigo_postal" class="field__input" <?php echo $ffReq; ?>
                   placeholder="28001" value="<?php echo $ffV('codigo_postal'); ?>">
        </div>
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
                   data-ph-bloqueado="<?php echo $ffEsAlta ? 'Elige antes país, comunidad y provincia' : 'Primero elige la provincia'; ?>"
                   placeholder="<?php echo $ffEsAlta ? 'Elige antes país, comunidad y provincia' : 'Primero elige la provincia'; ?>" value="<?php echo $ffV('ciudad'); ?>">
            <?php if ($ffEsAlta): ?><small class="field__hint">Escribe la ciudad o municipio tal cual; nuestro equipo lo verifica al revisar tu ficha.</small><?php endif; ?>
        </div>
    </div>
    <?php else: /* actualización: todas las fichas son de España, el país no se pregunta */ ?>
    <input type="hidden" id="pais" name="pais" value="España">

    <div class="field">
        <label class="field__label" for="direccion">Dirección completa</label>
        <input type="text" id="direccion" name="direccion" class="field__input"
               placeholder="Calle, número, localidad" value="<?php echo $ffV('direccion'); ?>">
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="comunidad">Comunidad / Región<?php echo $ffAst; ?></label>
            <select id="comunidad" name="comunidad" class="field__select field__select--enhanced" <?php echo $ffReq; ?>>
                <option value="">— Elige comunidad / región —</option>
                <?php foreach (array_keys($GEO_ES) as $ccaa): ?>
                <option value="<?php echo htmlspecialchars($ccaa); ?>" <?php echo $ccaa === $ffComunidad ? 'selected' : ''; ?>><?php echo htmlspecialchars($ccaa); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="provincia">Provincia<?php echo $ffAst; ?></label>
            <select id="provincia" name="provincia" class="field__select field__select--enhanced" <?php echo $ffReq; ?>
                    data-valor="<?php echo $ffV('provincia'); ?>">
                <option value="">— Elige primero la comunidad —</option>
            </select>
        </div>
    </div>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="ciudad">Ciudad<?php echo $ffAst; ?></label>
            <input type="text" id="ciudad" name="ciudad" class="field__input" <?php echo $ffReq; ?>
                   <?php echo (!$ffEsAlta && !empty($ffDatos['provincia'])) ? '' : 'disabled'; ?>
                   data-ph-bloqueado="<?php echo $ffEsAlta ? 'Elige antes país, comunidad y provincia' : 'Primero elige la provincia'; ?>"
                   placeholder="<?php echo $ffEsAlta ? 'Elige antes país, comunidad y provincia' : 'Primero elige la provincia'; ?>" value="<?php echo $ffV('ciudad'); ?>">
            <?php if ($ffEsAlta): ?><small class="field__hint">Escribe la ciudad o municipio tal cual; nuestro equipo lo verifica al revisar tu ficha.</small><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="codigo_postal">Código postal</label>
            <input type="text" id="codigo_postal" name="codigo_postal" class="field__input" 
                   placeholder="28001" value="<?php echo $ffV('codigo_postal'); ?>">
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$ffEsAlta): ?>
    <div class="field">
        <label class="field__label" for="contactos_extra">¿Otros contactos o direcciones? <span style="font-weight: normal; color: var(--color-seis);">(opcional)</span></label>
        <textarea id="contactos_extra" name="contactos_extra" class="field__textarea" rows="2"
                  placeholder="Ej.: WhatsApp +34 612 34 56 78, segundo teléfono, otra sede en Toledo…"><?php echo $ffV('contactos_extra'); ?></textarea>
    </div>
    <?php endif; ?>

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
                ciudad.placeholder = ciudad.getAttribute('data-ph-bloqueado') || 'Elige antes país, comunidad y provincia';
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

</div>

<?php if (!$ffEsAlta): ?>
<!-- SECCIÓN: Zona de cobertura (solo al actualizar una ficha existente) -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="map" class="icono"></i>
        Zona de cobertura
    </h3>
    <p class="panel__intro">¿Dónde atiendes? Así te encuentran las familias de esas zonas.</p>


    <div class="field">
        <label class="field__label" for="cobertura_zonas">Comunidades y provincias</label>
        <select id="cobertura_zonas" name="cobertura_zonas[]" class="field__select ff-multi" multiple data-placeholder="Elige las zonas que cubres">
            <optgroup label="Comunidades y regiones">
                <?php foreach (array_keys($GEO_ES) as $ccaa): ?>
                <option value="<?php echo htmlspecialchars($ccaa); ?>" <?php echo $ffSeleccionada($ccaa, $ffZonasSel); ?>><?php echo htmlspecialchars($ccaa); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Provincias (comunidades con varias)">
                <?php foreach ($ffZonasProvincias as $pv): ?>
                <option value="<?php echo htmlspecialchars($pv); ?>" <?php echo $ffSeleccionada($pv, $ffZonasSel); ?>><?php echo htmlspecialchars($pv); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php if ($ffZonasExtra): ?>
            <optgroup label="Otras (ya en tu ficha)">
                <?php foreach ($ffZonasExtra as $ffZ): ?>
                <option value="<?php echo htmlspecialchars($ffZ); ?>" selected><?php echo htmlspecialchars($ffZ); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
        </select>
    </div>

    <div class="field">
        <label class="field__label" for="cobertura_ciudades">Ciudades</label>
        <select id="cobertura_ciudades" name="cobertura_ciudades[]" class="field__select ff-multi" multiple data-create="1" data-placeholder="Elige o escribe ciudades y pulsa Intro">
            <?php foreach ($ffCiudadesLista as $ffCiu): ?>
            <option value="<?php echo htmlspecialchars($ffCiu); ?>" <?php echo $ffSeleccionada($ffCiu, $ffCiudadesSel); ?>><?php echo htmlspecialchars($ffCiu); ?></option>
            <?php endforeach; ?>
        </select>
        <small class="field__hint">Si no aparece, escríbela: la revisaremos.</small>
    </div>

    <div class="field">
        <label class="field__label" for="cobertura_texto">¿Algo más sobre tu zona? <span style="font-weight: normal; color: var(--color-seis);">(opcional)</span></label>
        <textarea id="cobertura_texto" name="cobertura_texto" class="field__textarea" rows="2"
                  placeholder="Ej.: Recogemos también en pueblos cercanos, consúltanos."><?php echo $ffV('cobertura_texto'); ?></textarea>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof TomSelect === 'undefined') return;
    document.querySelectorAll('.ff-multi').forEach(function (el) {
        if (el.tomselect) return;
        new TomSelect(el, {
            plugins: ['remove_button'],
            create: el.dataset.create === '1',
            createOnBlur: el.dataset.create === '1',
            persist: false,
            delimiter: ',',
            maxOptions: 600,
            hidePlaceholder: true,
            dropdownParent: 'body',
            placeholder: el.dataset.placeholder || ''
        });
    });
});
</script>
<?php endif; ?>

<!-- SECCIÓN: Descripción y servicios -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="list-checks" class="icono"></i>
        Servicios, horarios y precios
    </h3>

    <?php
    // Servicios: en la actualización las etiquetas se MARCAN (sin copiarse a otro sitio) y la caja de abajo solo sirve para añadir nuevas
    $ffSrvSel = $ffEsAlta ? [] : $ffSepara((string)($ffDatos['servicios'] ?? ''));
    $ffSrvCat = $ffServiciosCategorias;
    if (!$ffEsAlta) {
        $ffEnCat = [];
        foreach ($ffServiciosCategorias as $ffItems) foreach ($ffItems as $ffIt) $ffEnCat[mb_strtolower($ffIt)] = true;
        $ffOtros = array_values(array_filter($ffSrvSel, fn($x) => !isset($ffEnCat[mb_strtolower($x)])));
        if ($ffOtros) $ffSrvCat['Otros (ya en tu ficha)'] = $ffOtros;
    }
    $ffSrvMin = $ffMin($ffSrvSel);
    ?>
    <div class="field">
        <label class="field__label" for="<?php echo $ffEsAlta ? 'servicios' : 'servicios_propios'; ?>">Servicios que ofreces<?php echo $ffAst; ?></label>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
            <?php if ($ffEsAlta): ?>
            Toca los servicios que ofreces para añadirlos. Si sumas otros, <strong>separa cada uno con una coma</strong>.
            <?php else: ?>
            Toca los que ofreces. Si falta alguno, escríbelo abajo y pulsa Intro o coma: lo revisaremos.
            <?php endif; ?>
        </small>
        <div class="ff-nube" id="servicios-chips" data-target="<?php echo $ffEsAlta ? 'servicios' : 'servicios_sel'; ?>" data-modo="<?php echo $ffEsAlta ? 'lista' : 'sel'; ?>">
            <?php foreach ($ffSrvCat as $ffCat => $ffItems): ?>
            <div class="ff-nube__grupo">
                <span class="ff-nube__titulo"><?php echo htmlspecialchars($ffCat); ?></span>
                <div class="ff-nube__chips">
                    <?php foreach ($ffItems as $ffIt): $ffOn = in_array(mb_strtolower($ffIt), $ffSrvMin, true); ?>
                    <button type="button" class="ff-chip<?php echo $ffOn ? ' activo' : ''; ?>" aria-pressed="<?php echo $ffOn ? 'true' : 'false'; ?>" data-valor="<?php echo htmlspecialchars($ffIt); ?>"><?php echo htmlspecialchars($ffIt); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($ffEsAlta): ?>
        <textarea id="servicios" name="servicios" class="field__textarea" <?php echo $ffReq; ?> rows="4"
                  placeholder="Ej: Cremación individual, cremación grupal, urnas personalizadas, ceremonias de despedida, recogida a domicilio, etc."><?php echo $ffV('servicios'); ?></textarea>
        <?php else: ?>
        <select id="servicios_sel" name="servicios_sel[]" multiple hidden aria-hidden="true" tabindex="-1">
            <?php foreach ($ffSrvCat as $ffItems) foreach ($ffItems as $ffIt): ?>
            <option value="<?php echo htmlspecialchars($ffIt); ?>" <?php echo in_array(mb_strtolower($ffIt), $ffSrvMin, true) ? 'selected' : ''; ?>></option>
            <?php endforeach; ?>
        </select>
        <?php $ffPropios = $ffSepara((string)($ffDatos['servicios_propios'] ?? '')); ?>
        <select id="servicios_propios" name="servicios_propios[]" class="field__select ff-multi" multiple data-create="1" data-placeholder="¿Falta alguno? Escríbelo y pulsa Intro o coma">
            <?php foreach ($ffPropios as $ffIt): ?>
            <option value="<?php echo htmlspecialchars($ffIt); ?>" selected><?php echo htmlspecialchars($ffIt); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php
    // Horarios: etiquetas que se MARCAN + una caja vacía para notas o horarios distintos
    $ffHorLineas = array_values(array_filter(array_map('trim', preg_split('/\n+/', (string)($ffDatos['horarios'] ?? '')) ?: [])));
    $ffHorMin    = $ffMin($ffHorarioSug = $ffHorariosSugeridos);
    $ffHorSel    = array_values(array_filter($ffHorLineas, fn($l) => in_array(mb_strtolower($l), $ffHorMin, true)));
    // La caja de abajo arranca vacía (son notas nuevas); solo se rellena si el envío tuvo errores y hay que conservar lo escrito
    $ffHorExtra  = is_array($ffDatos['horarios_extra_post'] ?? null) ? $ffDatos['horarios_extra_post'] : [];
    $ffHorSelMin = $ffMin($ffHorSel);
    ?>
    <div class="field">
        <label class="field__label" for="<?php echo $ffEsAlta ? 'horarios' : 'horarios_extra'; ?>">Horarios de atención<?php echo $ffAst; ?></label>
        <?php if ($ffEsAlta): ?>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">Toca los horarios más habituales para añadirlos y completa lo que falte en el cuadro.</small>
        <?php endif; ?>
        <div class="ff-nube" data-target="<?php echo $ffEsAlta ? 'horarios' : 'horarios_sel'; ?>" data-modo="<?php echo $ffEsAlta ? 'linea' : 'sel'; ?>">
            <div class="ff-nube__chips">
                <?php foreach ($ffHorariosSugeridos as $ffHor): $ffOn = !$ffEsAlta && in_array(mb_strtolower($ffHor), $ffHorSelMin, true); ?>
                <button type="button" class="ff-chip<?php echo $ffOn ? ' activo' : ''; ?>" aria-pressed="<?php echo $ffOn ? 'true' : 'false'; ?>" data-valor="<?php echo htmlspecialchars($ffHor); ?>"><i data-lucide="clock" class="icono"></i><?php echo htmlspecialchars($ffHor); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($ffEsAlta): ?>
        <textarea id="horarios" name="horarios" class="field__textarea" <?php echo $ffReq; ?> rows="3"
                  placeholder="Ej: Lunes a viernes 9:00-18:00, sábados 9:00-14:00, domingos cerrado. Urgencias 24h."><?php echo $ffV('horarios'); ?></textarea>
        <?php else: ?>
        <select id="horarios_sel" name="horarios_sel[]" multiple hidden aria-hidden="true" tabindex="-1">
            <?php foreach ($ffHorariosSugeridos as $ffHor): ?>
            <option value="<?php echo htmlspecialchars($ffHor); ?>" <?php echo in_array(mb_strtolower($ffHor), $ffHorSelMin, true) ? 'selected' : ''; ?>></option>
            <?php endforeach; ?>
        </select>
        <select id="horarios_extra" name="horarios_extra[]" class="field__select ff-multi" multiple data-create="1" data-placeholder="¿Tienes otros horarios distintos? Escribe todo aquí y pulsa Intro o coma">
            <?php foreach ($ffHorExtra as $ffHor): ?>
            <option value="<?php echo htmlspecialchars($ffHor); ?>" selected><?php echo htmlspecialchars($ffHor); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="precios">Precios o tarifas orientativas</label>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">
            Opcional. Si quieres, cuéntanos tus precios o rangos (ej: «Cremación individual desde 120 €, colectiva 60 €»). Nos ayuda a mostrar tu ficha más completa. Si prefieres no indicarlos, deja el campo vacío.
        </small>
        <small class="field__hint" style="margin-bottom: var(--espacio-dos);">Toca una idea para añadirla al cuadro y cambia los puntos suspensivos por tu precio.</small>
        <div class="ff-nube" data-target="precios" data-modo="frase">
            <div class="ff-nube__chips">
                <?php foreach ($ffPreciosIdeas as $ffIdea): ?>
                <button type="button" class="ff-chip" data-valor="<?php echo htmlspecialchars($ffIdea); ?>"><i data-lucide="plus" class="icono"></i><?php echo htmlspecialchars($ffIdea); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <textarea id="precios" name="precios" class="field__textarea" rows="3"
                  placeholder="Ej: Cremación individual perro pequeño desde 120 €. Cremación colectiva 60 €. Recogida a domicilio según zona."><?php echo $ffV('precios'); ?></textarea>
    </div>
</div>

<!-- SECCIÓN 3: Presencia en línea -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="globe" class="icono"></i>
        <?php echo $ffEsAlta ? 'Presencia en línea (opcional)' : 'Web y redes sociales (opcional)'; ?>
    </h3>

    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="sitio_web">Sitio web</label>
            <input type="url" id="sitio_web" name="sitio_web" class="field__input" placeholder="https://tusitio.com" value="<?php echo $ffV('sitio_web'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="google_maps_url"><?php echo $ffEsAlta ? 'Google Business / Maps' : 'Tu perfil en Google Maps (opcional)'; ?></label>
            <input type="url" id="google_maps_url" name="google_maps_url" class="field__input"
                   placeholder="<?php echo $ffEsAlta ? 'https://g.page/tunegocio o URL de Google Maps' : 'Pega aquí el enlace'; ?>" value="<?php echo $ffV('google_maps_url'); ?>">
            <?php if (!$ffEsAlta): ?>
            <details class="ff-ayuda">
                <summary>¿Dónde encuentro este enlace?</summary>
                <ol>
                    <li>Busca tu negocio en Google Maps.</li>
                    <li>Pulsa <strong>Compartir</strong>.</li>
                    <li>Copia el enlace y pégalo aquí.</li>
                </ol>
            </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($ffEsAlta): ?>
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
    <?php else: /* actualización: el WhatsApp está en «Datos del negocio», junto al teléfono */ ?>
    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="facebook">Facebook</label>
            <input type="url" id="facebook" name="facebook" class="field__input" placeholder="https://facebook.com/tupagina" value="<?php echo $ffV('facebook'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="instagram">Instagram</label>
            <input type="url" id="instagram" name="instagram" class="field__input" placeholder="https://instagram.com/tuperfil" value="<?php echo $ffV('instagram'); ?>">
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$ffEsAlta): ?>
    <div class="ff-grid">
        <div class="field">
            <label class="field__label" for="tiktok">TikTok</label>
            <input type="url" id="tiktok" name="tiktok" class="field__input" placeholder="https://tiktok.com/@tuperfil" value="<?php echo $ffV('tiktok'); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="youtube">YouTube</label>
            <input type="url" id="youtube" name="youtube" class="field__input" placeholder="https://youtube.com/@tucanal" value="<?php echo $ffV('youtube'); ?>">
        </div>
    </div>
    <?php
    $ffMasRedes = [
        'x'         => ['X (Twitter)', 'red_x',     'https://x.com/tuperfil'],
        'linkedin'  => ['LinkedIn',    'linkedin',  'https://linkedin.com/company/tunegocio'],
        'pinterest' => ['Pinterest',   'pinterest', 'https://pinterest.com/tuperfil'],
        'telegram'  => ['Telegram',    'telegram',  'https://t.me/tucanal'],
    ];
    $ffMasRedesAbierto = false;
    foreach (['x', 'linkedin', 'pinterest', 'telegram', 'red_otra_nombre', 'red_otra_url'] as $ffK) if (trim((string)($ffDatos[$ffK] ?? '')) !== '') $ffMasRedesAbierto = true;
    $ffOtraVisible = trim((string)($ffDatos['red_otra_nombre'] ?? '')) !== '' || trim((string)($ffDatos['red_otra_url'] ?? '')) !== '';
    ?>
    <details class="ff-mas-redes"<?php echo $ffMasRedesAbierto ? ' open' : ''; ?>>
        <summary>+ Más redes (X, LinkedIn, Pinterest, Telegram u otra)</summary>
        <div class="field">
            <label class="field__label" for="mas_redes_sel">Añadir una red</label>
            <select id="mas_redes_sel" class="field__select">
                <option value="">Elige una red…</option>
                <?php foreach ($ffMasRedes as $ffK => $ffR): ?>
                <option value="<?php echo $ffK; ?>" <?php echo trim((string)($ffDatos[$ffK] ?? '')) !== '' ? 'hidden' : ''; ?>><?php echo htmlspecialchars($ffR[0]); ?></option>
                <?php endforeach; ?>
                <option value="otra" <?php echo $ffOtraVisible ? 'hidden' : ''; ?>>Otra…</option>
            </select>
        </div>
        <div class="ff-grid">
            <?php foreach ($ffMasRedes as $ffK => $ffR): ?>
            <div class="field" data-red="<?php echo $ffK; ?>" <?php echo trim((string)($ffDatos[$ffK] ?? '')) !== '' ? '' : 'hidden'; ?>>
                <label class="field__label" for="<?php echo $ffR[1]; ?>"><?php echo htmlspecialchars($ffR[0]); ?></label>
                <input type="url" id="<?php echo $ffR[1]; ?>" name="<?php echo $ffK; ?>" class="field__input" placeholder="<?php echo $ffR[2]; ?>" value="<?php echo $ffV($ffK); ?>">
            </div>
            <?php endforeach; ?>
            <div class="field" data-red="otra" <?php echo $ffOtraVisible ? '' : 'hidden'; ?>>
                <label class="field__label" for="red_otra_nombre">Otra red (nombre)</label>
                <input type="text" id="red_otra_nombre" name="red_otra_nombre" class="field__input" maxlength="40" placeholder="Ej: Threads, Bluesky, Vimeo" value="<?php echo $ffV('red_otra_nombre'); ?>">
            </div>
            <div class="field" data-red="otra" <?php echo $ffOtraVisible ? '' : 'hidden'; ?>>
                <label class="field__label" for="red_otra_url">Otra red (enlace)</label>
                <input type="url" id="red_otra_url" name="red_otra_url" class="field__input" placeholder="https://…" value="<?php echo $ffV('red_otra_url'); ?>">
            </div>
        </div>
        <script>
        (function () {
            var sel = document.getElementById('mas_redes_sel');
            if (!sel) return;
            sel.addEventListener('change', function () {
                var k = sel.value; if (!k) return;
                document.querySelectorAll('.ff-mas-redes [data-red="' + k + '"]').forEach(function (f) { f.hidden = false; });
                var op = sel.querySelector('option[value="' + k + '"]'); if (op) op.hidden = true;
                var primero = document.querySelector('.ff-mas-redes [data-red="' + k + '"] input'); if (primero) primero.focus();
                sel.value = '';
            });
        })();
        </script>
    </details>
    <?php endif; ?>
</div>

<!-- SECCIÓN: Descripción (al final de los datos, para redactarla con todo lo anterior a mano) -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="file-text" class="icono"></i>
        <?php echo $ffEsAlta ? 'Descripción del negocio' : 'Cuéntanos sobre tu negocio'; ?>
    </h3>
    <?php if (!$ffEsAlta): ?>
    <p class="panel__intro">Ya has dado los datos: ahora cuenta quién eres. Por ejemplo: cuánto tiempo llevas, cómo tratáis a las familias y qué os diferencia.</p>

    <details class="ff-ayuda ff-ayuda--ia">
        <summary>¿Prefieres que te ayude una IA?</summary>
        <p>Copia este texto, pégalo en ChatGPT, Gemini o Claude y pega aquí el resultado.</p>
        <button type="button" class="boton dos" id="descripcion-copiar-prompt"><i data-lucide="copy" class="icono"></i> Copiar prompt para la IA</button>
    </details>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="descripcion"><?php echo $ffEsAlta ? 'Descripción del negocio' : 'Descripción'; ?><?php echo $ffAst; ?> <span style="font-weight: normal; color: var(--color-seis);"><?php echo $ffEsAlta ? '(entre 150 y 1800 caracteres)' : '(150–1800 caracteres)'; ?></span></label>
        <textarea id="descripcion" name="descripcion" class="field__textarea" <?php echo $ffReq; ?> minlength="150" maxlength="1800" rows="6" style="overflow: hidden; resize: none;"
                  placeholder="<?php echo $ffEsAlta ? 'Describe tu negocio, años de experiencia, valores, servicios especiales, etc. Cuanto más detallada sea la descripción, mejor posicionará tu ficha.' : 'Somos un crematorio familiar con más de 15 años de experiencia…'; ?>"><?php echo $ffV('descripcion'); ?></textarea>
        <small id="descripcion-contador" class="field__hint">0 / 1800 caracteres (mínimo 150)</small>
    </div>
</div>

<!-- SECCIÓN 4: Imágenes (opcional) -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="image" class="icono"></i>
        <?php echo $ffEsAlta ? 'Imágenes (opcional)' : 'Fotos y logo (opcional)'; ?>
    </h3>
    <p class="panel__intro">
        <?php if ($ffEsAlta): ?>
        Sube imágenes de tu negocio para destacar en el directorio. Formatos: JPG, PNG, GIF o WebP. Máximo 5 MB por imagen.
        <?php else: ?>
        Una buena foto da confianza a las familias.
        <?php endif; ?>
    </p>

    <!-- Logo -->
    <div class="field" style="margin-bottom: var(--espacio-cuatro);">
        <label class="field__label" for="logo"><?php echo $ffEsAlta ? 'Logo de tu negocio' : 'Tu logo'; ?></label>
        <div class="upload-zona" id="zona-logo" onclick="document.getElementById('logo').click()">
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp"
                   style="display: none;" onchange="previsualizarLogo(this)">
            <div id="preview-logo" class="upload-preview" style="display: none;"></div>
            <div id="placeholder-logo" class="upload-placeholder">
                <i data-lucide="upload" class="icono" style="width: 32px; height: 32px; color: var(--color-seis-claro);"></i>
                <span style="color: var(--color-seis-claro);"><?php echo $ffEsAlta ? 'Haz clic para subir tu logo' : 'Toca para subirlo'; ?></span>
                <small style="color: var(--color-seis-claro);"><?php echo $ffEsAlta ? 'Recomendado: 300x300 px' : 'Mejor si es cuadrado'; ?></small>
            </div>
        </div>
    </div>

    <!-- Galería -->
    <div class="field">
        <label class="field__label" for="galeria"><?php echo $ffEsAlta ? 'Galería de imágenes (máximo 10)' : 'Fotos de tu local (hasta 10)'; ?></label>
        <div class="upload-zona galeria" id="zona-galeria" onclick="document.getElementById('galeria').click()">
            <input type="file" id="galeria" name="galeria[]" accept="image/jpeg,image/png,image/gif,image/webp"
                   multiple style="display: none;" onchange="previsualizarGaleria(this)">
            <div id="preview-galeria" class="upload-galeria-preview"></div>
            <div id="placeholder-galeria" class="upload-placeholder">
                <i data-lucide="images" class="icono" style="width: 32px; height: 32px; color: var(--color-seis-claro);"></i>
                <span style="color: var(--color-seis-claro);"><?php echo $ffEsAlta ? 'Haz clic para subir imágenes de tu local' : 'Toca para subirlas'; ?></span>
                <small style="color: var(--color-seis-claro);"><?php echo $ffEsAlta ? 'Fachada, instalaciones, recepción, etc.' : 'Fachada, sala de despedida, recepción'; ?></small>
            </div>
        </div>
    </div>
    <?php if (!$ffEsAlta): ?><small class="field__hint">JPG, PNG o WebP · hasta 5 MB cada una</small><?php endif; ?>
</div>

<!-- SECCIÓN 5: Comentarios adicionales -->
<div class="panel">
    <h3 class="panel__title">
        <i data-lucide="message-circle" class="icono"></i>
        <?php echo $ffEsAlta ? 'Comentarios adicionales (opcional)' : 'Algo más (opcional)'; ?>
    </h3>

    <div class="field">
        <label class="field__label" for="comentarios_admin"><?php echo $ffEsAlta ? '¿Algo más que quieras contarnos?' : '¿Quieres contarnos algo más?'; ?></label>
        <textarea id="comentarios_admin" name="comentarios_admin" class="field__textarea" rows="3"
                  placeholder="<?php echo $ffEsAlta ? 'Preguntas, comentarios o información adicional que quieras compartir con nuestro equipo. Esta información no se publicará.' : 'Una duda, un dato que no cabe arriba…'; ?>"><?php echo $ffV('comentarios_admin'); ?></textarea>
        <small class="field__hint"><?php echo $ffEsAlta ? 'Esta información es privada y no se mostrará en tu ficha.' : 'Es privado: no se publica.'; ?></small>
    </div>
</div>

<script>
// ─── Teléfonos siempre en el mismo formato: «+34 612 34 56 78» (al salir del campo, si es un número de España válido) ───
document.addEventListener('DOMContentLoaded', function () {
    function formatear(v) {
        var s = String(v || '').replace(/[\s().\-]/g, '');
        if (s.indexOf('+34') === 0) s = s.slice(3);
        else if (s.indexOf('0034') === 0) s = s.slice(4);
        else if (s.length === 11 && s.indexOf('34') === 0) s = s.slice(2);
        if (!/^[6789]\d{8}$/.test(s)) return null;
        return '+34 ' + s.slice(0, 3) + ' ' + s.slice(3, 5) + ' ' + s.slice(5, 7) + ' ' + s.slice(7, 9);
    }
    ['contacto_telefono', 'telefono_clientes', 'whatsapp', 'verificar-tel-nuevo'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        function aplicar() { var f = formatear(el.value); if (f && f !== el.value) { el.value = f; el.dispatchEvent(new Event('input')); } }
        el.addEventListener('blur', aplicar);
        aplicar();
    });
});
</script>

<script>
// ─── Enlaces: si falta «https://», se añade al salir del campo ───
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="url"]').forEach(function (el) {
        el.addEventListener('blur', function () {
            var v = el.value.trim();
            if (v && !/^https?:\/\//i.test(v) && /^[^\s]+\.[^\s]+$/.test(v)) el.value = 'https://' + v.replace(/^\/+/, '');
        });
    });
});
</script>

<script>
// ─── Nubes de etiquetas (.ff-nube): servicios («lista»), horarios («linea») y precios («frase») ───
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ff-nube[data-target]').forEach(function (nube) {
        var ta = document.getElementById(nube.dataset.target);
        var modo = nube.dataset.modo;
        var chips = nube.querySelectorAll('.ff-chip');
        if (!ta || !chips.length) return;
        if (modo === 'sel') {   // actualización: las etiquetas se marcan; lo marcado viaja en un <select> oculto
            chips.forEach(function (c) {
                c.addEventListener('click', function () {
                    var on = !c.classList.contains('activo');
                    c.classList.toggle('activo', on);
                    c.setAttribute('aria-pressed', on ? 'true' : 'false');
                    Array.prototype.forEach.call(ta.options, function (o) { if (o.value === c.dataset.valor) o.selected = on; });
                });
            });
            return;
        }
        var sep = modo === 'lista' ? /[,\n]+/ : /\n+/;

        function partes() { return ta.value.split(sep).map(function (t) { return t.trim(); }).filter(function (t) { return t.length; }); }
        function guardar(arr) { ta.value = arr.join(modo === 'lista' ? ', ' : '\n'); }
        function pintar() {
            if (modo === 'frase') return;
            var bajo = partes().map(function (t) { return t.toLowerCase(); });
            chips.forEach(function (c) {
                var on = bajo.indexOf(c.dataset.valor.toLowerCase()) !== -1;
                c.classList.toggle('activo', on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                var v = c.dataset.valor;
                if (modo === 'frase') {
                    ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/, '') + '\n' : '') + v;
                } else {
                    var arr = partes();
                    var i = arr.findIndex(function (t) { return t.toLowerCase() === v.toLowerCase(); });
                    if (i !== -1) arr.splice(i, 1); else arr.push(v);
                    guardar(arr);
                }
                pintar();
                ta.dispatchEvent(new Event('input'));
            });
        });
        ta.addEventListener('input', pintar);
        pintar();
    });
});
</script>

<script>
// ─── Utilidades del formulario de ficha (contador de descripción y previsualización de imágenes) ───
document.addEventListener('DOMContentLoaded', function () {
    var descripcion = document.getElementById('descripcion');
    var contador = document.getElementById('descripcion-contador');
    if (!descripcion || !contador) return;
    var minimo = 150, maximo = 1800;
    // El cuadro crece con el texto: se ve la descripción completa, sin barra de desplazamiento
    function ajustarAlto() {
        var cs = getComputedStyle(descripcion);
        descripcion.style.height = 'auto';
        var h = descripcion.scrollHeight;
        h += cs.boxSizing === 'border-box'
            ? parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth)
            : -(parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom));
        descripcion.style.height = Math.ceil(h) + 'px';
    }
    // El texto puede recolocarse después de cargar (tipografía, ancho de la columna, ventana): se recalcula entonces
    var anchoPrevio = 0;
    if (window.ResizeObserver) {
        new ResizeObserver(function () {
            if (descripcion.offsetWidth !== anchoPrevio) { anchoPrevio = descripcion.offsetWidth; ajustarAlto(); }
        }).observe(descripcion);
    }
    window.addEventListener('load', ajustarAlto);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(ajustarAlto);
    function actualizar() {
        var n = descripcion.value.length;
        if (n < minimo) {
            contador.innerHTML = n + ' / ' + maximo + ' caracteres <span style="color: var(--color-siete);">(faltan ' + (minimo - n) + ' para el mínimo de ' + minimo + ')</span>';
        } else {
            contador.innerHTML = '<span style="color: var(--color-tres);">' + n + ' / ' + maximo + ' caracteres ✓ Suficiente; si quieres, añade más.</span>';
        }
        ajustarAlto();
    }
    window.addEventListener('resize', ajustarAlto);
    descripcion.addEventListener('input', actualizar);
    actualizar();
});

// ─── Texto para pedir la descripción a una IA (se arma con lo que ya se ha rellenado) ───
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('descripcion-copiar-prompt');
    if (!btn) return;
    function val(id) { var el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; }
    function marcados(id) {
        var el = document.getElementById(id); if (!el || !el.options) return [];
        return Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; });
    }
    function unicos(arr) {
        var vistos = {};
        return arr.filter(function (x) { var k = x.toLowerCase(); if (vistos[k]) return false; vistos[k] = true; return true; });
    }
    function armar() {
        var servicios = marcados('servicios_sel').concat(marcados('servicios_propios'));
        if (!servicios.length) servicios = val('servicios') ? [val('servicios')] : [];
        var zonas = unicos(marcados('cobertura_zonas').concat(marcados('cobertura_ciudades')));
        var actual = val('descripcion');
        var datos = [];
        if (val('nombre_negocio')) datos.push('- Nombre: ' + val('nombre_negocio'));
        if (val('ciudad')) datos.push('- Ciudad: ' + val('ciudad'));
        if (servicios.length) datos.push('- Servicios: ' + unicos(servicios).join(', '));
        if (zonas.length) datos.push('- Zona que cubre: ' + zonas.join(', '));
        var t = 'Eres redactor de fichas para un directorio de crematorios de mascotas en España. Escribe la descripción de mi negocio para su ficha pública.\n\n' +
            'Datos reales (no inventes nada que no esté aquí):\n' + (datos.length ? datos.join('\n') : '- (aún no he rellenado datos)') + '\n\n';
        if (actual) {
            t += 'Mi descripción actual (conserva lo que sea correcto y útil y mejora la redacción; no añadas datos que no estén en ella ni en la lista de arriba):\n--- inicio ---\n' + actual + '\n--- fin ---\n\n';
        }
        t += 'Antes de redactar, si falta algo importante (años de experiencia, cómo trato a las familias, qué me diferencia), hazme como máximo 3 preguntas.\n\n' +
            'Requisitos: entre 150 y 1800 caracteres; español de España; tono cercano y respetuoso, porque las familias están de duelo; sin superlativos como «el mejor» ni promesas que no pueda cumplir; uno o dos párrafos. No incluyas valoraciones, número de reseñas, horarios ni teléfonos: ya se muestran aparte en la ficha.\n\n' +
            'Cuando tengas lo necesario, responde SOLO con la descripción: sin introducción, sin comentarios y sin formato (sin negritas, listas ni títulos), para que pueda copiarla tal cual.';
        return t;
    }
    btn.addEventListener('click', function () {
        var texto = armar();
        function ok() {
            var antes = btn.innerHTML; btn.textContent = 'Copiado ✓';
            setTimeout(function () { btn.innerHTML = antes; if (window.lucide) lucide.createIcons(); }, 2000);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(texto).then(ok, function () { window.prompt('Copia este texto:', texto); }); }
        else { window.prompt('Copia este texto:', texto); }
    });
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
