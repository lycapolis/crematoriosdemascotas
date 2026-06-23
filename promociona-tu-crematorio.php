<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LANDING B2B — Promociona tu crematorio (lead comercial)
 * Maqueta inicial. Mismo destino que el popup: leads_comerciales
 * (procesar-formulario.php tipo=lead_comercial, origen=landing).
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Promociona tu crematorio de mascotas - Crematorios de Mascotas';
$pagina_actual = 'promociona';
include 'includes/header.php';
?>

<style>
    .promo-hero {
        background: var(--color-cuatro);
        text-align: center;
        padding: var(--espacio-seis) var(--espacio-cuatro);
    }
    .promo-hero__kicker {
        display: inline-block;
        font-size: var(--fs-uno);
        font-weight: var(--peso-negrita);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--color-uno);
        margin-bottom: var(--espacio-tres);
    }
    .promo-hero h1 { margin: 0 0 var(--espacio-tres); }
    .promo-hero__sub {
        max-width: 620px;
        margin: 0 auto var(--espacio-cinco);
        color: var(--color-seis-claro);
        font-size: var(--fs-cuatro);
        line-height: var(--lh-tres);
    }
    .promo-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--espacio-cuatro);
    }
    .promo-icono {
        width: 44px; height: 44px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--radio-dos);
        background: var(--color-uno-claro);
        color: var(--color-uno);
        margin-bottom: var(--espacio-tres);
    }
    .promo-pasos { counter-reset: paso; display: grid; grid-template-columns: 1fr; gap: var(--espacio-cuatro); }
    .promo-paso { display: flex; gap: var(--espacio-tres); align-items: flex-start; }
    .promo-paso__num {
        counter-increment: paso;
        flex-shrink: 0;
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--radio-full);
        background: var(--color-uno); color: var(--color-ocho);
        font-weight: var(--peso-negrita);
    }
    .promo-paso__num::before { content: counter(paso); }

    @media (min-width: 768px) {
        .promo-grid { grid-template-columns: repeat(2, 1fr); }
        .promo-pasos { grid-template-columns: repeat(3, 1fr); }
        .promo-paso { flex-direction: column; }
    }
    @media (min-width: 1024px) {
        .promo-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media (max-width: 640px) {
        .promo-hero { padding: var(--espacio-cinco) var(--espacio-tres); }
    }
</style>

<!-- ═══ HERO ═══ -->
<section class="promo-hero">
    <div class="contenedor">
        <span class="promo-hero__kicker">Para dueños de crematorios</span>
        <h1>Haz crecer tu crematorio de mascotas</h1>
        <p class="promo-hero__sub">
            Conecta con familias que buscan tu servicio en el momento más difícil.
            Te ayudamos a destacar en el directorio de referencia de cremación de mascotas en España.
        </p>
        <a href="#contacto" class="boton uno grande">
            <i data-lucide="megaphone" class="icono"></i>
            Quiero promocionar mi crematorio
        </a>
    </div>
</section>

<!-- ═══ BENEFICIOS ═══ -->
<section class="seccion">
    <div class="contenedor">
        <h2 class="estilo-h3" style="text-align:center; margin-bottom:var(--espacio-cinco);">Por qué destacar con nosotros</h2>
        <div class="promo-grid">
            <div class="panel">
                <div class="promo-icono"><i data-lucide="search"></i></div>
                <h3 class="estilo-h5">Más visibilidad</h3>
                <p style="color:var(--color-seis-claro); margin:0;">Apareces ante familias que buscan activamente un crematorio en tu zona.</p>
            </div>
            <div class="panel">
                <div class="promo-icono"><i data-lucide="star"></i></div>
                <h3 class="estilo-h5">Confianza</h3>
                <p style="color:var(--color-seis-claro); margin:0;">Reseñas verificadas y una ficha completa que transmiten seriedad.</p>
            </div>
            <div class="panel">
                <div class="promo-icono"><i data-lucide="trending-up"></i></div>
                <h3 class="estilo-h5">Posicionamiento</h3>
                <p style="color:var(--color-seis-claro); margin:0;">Tu ficha optimizada para Google y los buscadores con IA.</p>
            </div>
            <div class="panel">
                <div class="promo-icono"><i data-lucide="heart-handshake"></i></div>
                <h3 class="estilo-h5">Sin esfuerzo</h3>
                <p style="color:var(--color-seis-claro); margin:0;">Nosotros mantenemos tu perfil; tú te enfocas en acompañar a las familias.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ CÓMO FUNCIONA ═══ -->
<section class="seccion" style="background:var(--color-cinco);">
    <div class="contenedor">
        <h2 class="estilo-h3" style="text-align:center; margin-bottom:var(--espacio-cinco);">Cómo funciona</h2>
        <div class="promo-pasos">
            <div class="promo-paso">
                <div class="promo-paso__num"></div>
                <div>
                    <h3 class="estilo-h5">Cuéntanos sobre tu crematorio</h3>
                    <p style="color:var(--color-seis-claro); margin:0;">Rellena el formulario con tus datos y lo que te interesa.</p>
                </div>
            </div>
            <div class="promo-paso">
                <div class="promo-paso__num"></div>
                <div>
                    <h3 class="estilo-h5">Te contactamos</h3>
                    <p style="color:var(--color-seis-claro); margin:0;">Revisamos tu caso y te proponemos las opciones a tu medida, sin compromiso.</p>
                </div>
            </div>
            <div class="promo-paso">
                <div class="promo-paso__num"></div>
                <div>
                    <h3 class="estilo-h5">Tu ficha destaca</h3>
                    <p style="color:var(--color-seis-claro); margin:0;">Empiezas a recibir más contactos de familias de tu zona.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ FORMULARIO ═══ -->
<section class="seccion" id="contacto">
    <div class="contenedor" style="max-width:640px;">
        <div class="callout" style="margin-bottom:var(--espacio-cuatro);">
            <i data-lucide="info" class="callout__icon"></i>
            <div class="callout__body">
                <strong>El registro básico en el directorio es gratuito.</strong> Aquí gestionamos la
                promoción destacada y los servicios de visibilidad. Déjanos tus datos y te explicamos
                las opciones — sin compromiso.
            </div>
        </div>

        <div class="panel" id="promo-card">
            <h2 class="panel__title">
                <i data-lucide="megaphone" class="icono"></i>
                Cuéntanos sobre tu crematorio
            </h2>

            <form id="form-promo-landing" onsubmit="enviarPromoLanding(event)" style="display:flex; flex-direction:column; gap:var(--espacio-tres);">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-nombre">Nombre y apellido <span class="field__req">obligatorio</span></label>
                    <input type="text" id="pl-nombre" class="field__input" required>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-negocio">Nombre del crematorio <span class="field__req">obligatorio</span></label>
                    <input type="text" id="pl-negocio" class="field__input" required>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-email">Email <span class="field__req">obligatorio</span></label>
                    <input type="email" id="pl-email" class="field__input" required>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-telefono">Teléfono <span class="field__req">obligatorio</span></label>
                    <input type="tel" id="pl-telefono" class="field__input" required>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-ciudad">Ciudad</label>
                    <input type="text" id="pl-ciudad" class="field__input" placeholder="Opcional">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label" for="pl-mensaje">Mensaje</label>
                    <textarea id="pl-mensaje" class="field__textarea" rows="3" placeholder="Cuéntanos qué te interesa: destacar tu ficha, más visibilidad, etc. (opcional)"></textarea>
                </div>
                <button type="submit" class="boton uno grande" style="width:100%; justify-content:center;">
                    <i data-lucide="send" class="icono"></i>
                    Enviar consulta
                </button>
            </form>
        </div>
    </div>
</section>

<script>
function enviarPromoLanding(e) {
    e.preventDefault();
    var g = function (id) { return (document.getElementById(id).value || '').trim(); };
    var nombre = g('pl-nombre'), negocio = g('pl-negocio'), email = g('pl-email'),
        telefono = g('pl-telefono'), ciudad = g('pl-ciudad'), mensaje = g('pl-mensaje');

    var faltan = [];
    if (!nombre)  faltan.push('Nombre y apellido');
    if (!negocio) faltan.push('Nombre del crematorio');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) faltan.push('Email válido');
    if (!telefono) faltan.push('Teléfono');
    if (faltan.length) {
        var m = 'Faltan datos:<br>• ' + faltan.join('<br>• ');
        if (window.toast) toast.error(m); else alert(faltan.join('\n'));
        return;
    }

    var fd = new FormData();
    fd.append('tipo', 'lead_comercial');
    fd.append('nombre', nombre);
    fd.append('nombre_negocio', negocio);
    fd.append('email', email);
    fd.append('telefono', telefono);
    fd.append('ciudad', ciudad);
    fd.append('mensaje', mensaje);
    fd.append('origen', 'landing');
    fd.append('page_url', window.location.href);

    var btn = e.target.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

    fetch('<?php echo BASE_URL; ?>/procesar-formulario.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) {
                document.getElementById('promo-card').innerHTML =
                    '<div style="text-align:center; padding:var(--espacio-cuatro) 0;">' +
                    '<div class="promo-icono" style="margin:0 auto var(--espacio-tres);"><i data-lucide="check-circle"></i></div>' +
                    '<h2 class="estilo-h4">¡Gracias! Recibimos tu consulta</h2>' +
                    '<p style="color:var(--color-seis-claro); margin:0;">Te contactamos pronto para contarte las opciones.</p>' +
                    '</div>';
                if (window.lucide) lucide.createIcons();
                if (window.toast) toast.ok('Consulta enviada correctamente.');
            } else {
                if (window.toast) toast.error(d.mensaje || 'No se pudo enviar. Inténtalo de nuevo.');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="send" class="icono"></i> Enviar consulta'; if (window.lucide) lucide.createIcons(); }
            }
        })
        .catch(function () {
            if (window.toast) toast.error('Error de conexión. Inténtalo de nuevo.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="send" class="icono"></i> Enviar consulta'; if (window.lucide) lucide.createIcons(); }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
