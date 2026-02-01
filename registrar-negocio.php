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
include 'includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-tres">
        <div class="contenedor">
            <h1>Registra tu Crematorio</h1>
            <h2 class="seccion__descripcion estilo-h5 seis">
                Únete a nuestro directorio y conecta con familias que buscan servicios de cremación para sus mascotas.
            </h2>
            
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div style="display: grid; grid-template-columns: 1fr 380px; gap: var(--espacio-cinco);">

                <!-- FORMULARIO -->
                <div id="formulario-container">
                    <div class="tarjeta simple" style="padding: var(--espacio-cinco); background: var(--color-ocho); border: 1px solid var(--color-cinco);">
                        <h2 style="font-size: var(--fs-seis); margin-bottom: var(--espacio-cinco); padding-bottom: var(--espacio-tres); border-bottom: 2px solid var(--color-uno);">Información de Registro</h2>

                        <!-- Alerta -->
                        <div id="alerta" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

                        <form id="formulario-registro" onsubmit="enviarFormulario(event)">

                            <!-- SECCIÓN 1: Datos de contacto -->
                            <div style="margin-bottom: var(--espacio-cinco);">
                                <h3 style="font-size: var(--fs-tres); color: var(--color-uno); margin-bottom: var(--espacio-cuatro); display: flex; align-items: center; gap: var(--espacio-dos);">
                                    <i data-lucide="user" class="icono"></i>
                                    Datos de Contacto
                                </h3>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="nombre">Nombre completo *</label>
                                        <input
                                            type="text"
                                            id="nombre"
                                            name="nombre"
                                            class="campo"
                                            required
                                            placeholder="Tu nombre completo"
                                        >
                                    </div>

                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="email">Email *</label>
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            class="campo"
                                            required
                                            placeholder="tu@email.com"
                                        >
                                    </div>
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="telefono">Teléfono *</label>
                                    <input
                                        type="tel"
                                        id="telefono"
                                        name="telefono"
                                        class="campo"
                                        required
                                        placeholder="Ej: +34 600 000 000"
                                    >
                                </div>
                            </div>

                            <!-- SECCIÓN 2: Datos del crematorio -->
                            <div style="margin-bottom: var(--espacio-cinco);">
                                <h3 style="font-size: var(--fs-tres); color: var(--color-uno); margin-bottom: var(--espacio-cuatro); display: flex; align-items: center; gap: var(--espacio-dos);">
                                    <i data-lucide="home" class="icono"></i>
                                    Datos del Crematorio
                                </h3>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="nombre_negocio">Nombre del crematorio *</label>
                                    <input
                                        type="text"
                                        id="nombre_negocio"
                                        name="nombre_negocio"
                                        class="campo"
                                        required
                                        placeholder="Nombre comercial de tu crematorio"
                                    >
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="direccion">Dirección completa *</label>
                                    <input
                                        type="text"
                                        id="direccion"
                                        name="direccion"
                                        class="campo"
                                        required
                                        placeholder="Calle, número, colonia"
                                    >
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="ciudad">Ciudad *</label>
                                        <input
                                            type="text"
                                            id="ciudad"
                                            name="ciudad"
                                            class="campo"
                                            required
                                            placeholder="Madrid"
                                        >
                                    </div>

                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="estado">Provincia *</label>
                                        <input
                                            type="text"
                                            id="estado"
                                            name="estado"
                                            class="campo"
                                            required
                                            placeholder="Madrid"
                                        >
                                    </div>
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="codigo_postal">Código Postal</label>
                                    <input
                                        type="text"
                                        id="codigo_postal"
                                        name="codigo_postal"
                                        class="campo"
                                        placeholder="28001"
                                    >
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="descripcion">Descripción del negocio *</label>
                                    <textarea
                                        id="descripcion"
                                        name="descripcion"
                                        class="area-texto"
                                        required
                                        placeholder="Describe tu crematorio, años de experiencia, valores, etc."
                                        rows="6"
                                    ></textarea>
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="servicios">Servicios que ofreces</label>
                                    <textarea
                                        id="servicios"
                                        name="servicios"
                                        class="area-texto"
                                        placeholder="Ej: Cremación individual, cremación grupal, urnas, ceremonias, etc."
                                        rows="4"
                                    ></textarea>
                                </div>

                                <div class="formulario-grupo">
                                    <label class="formulario-etiqueta" for="horarios">Horarios de atención</label>
                                    <textarea
                                        id="horarios"
                                        name="horarios"
                                        class="area-texto"
                                        placeholder="Ej: Lunes a Viernes 9:00-18:00, Sábados 9:00-14:00"
                                        rows="3"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- SECCIÓN 3: Presencia en línea -->
                            <div style="margin-bottom: var(--espacio-cinco);">
                                <h3 style="font-size: var(--fs-tres); color: var(--color-uno); margin-bottom: var(--espacio-cuatro); display: flex; align-items: center; gap: var(--espacio-dos);">
                                    <i data-lucide="globe" class="icono"></i>
                                    Presencia en Línea (Opcional)
                                </h3>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="sitio_web">Sitio web</label>
                                        <input
                                            type="url"
                                            id="sitio_web"
                                            name="sitio_web"
                                            class="campo"
                                            placeholder="https://tusitio.com"
                                        >
                                    </div>

                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="whatsapp">WhatsApp</label>
                                        <input
                                            type="tel"
                                            id="whatsapp"
                                            name="whatsapp"
                                            class="campo"
                                            placeholder="Número con código de país"
                                        >
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="facebook">Facebook</label>
                                        <input
                                            type="url"
                                            id="facebook"
                                            name="facebook"
                                            class="campo"
                                            placeholder="https://facebook.com/tupagina"
                                        >
                                    </div>

                                    <div class="formulario-grupo">
                                        <label class="formulario-etiqueta" for="instagram">Instagram</label>
                                        <input
                                            type="url"
                                            id="instagram"
                                            name="instagram"
                                            class="campo"
                                            placeholder="https://instagram.com/tuperfil"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Botón submit -->
                            <button type="submit" class="boton uno grande" style="width: 100%;">
                                Enviar Solicitud de Registro
                            </button>

                            <p style="font-size: var(--fs-uno); color: var(--color-seis-claro); margin-top: var(--espacio-cuatro); text-align: center;">
                                Al enviar este formulario, aceptas que revisemos tu información
                                para incluirla en nuestro directorio.
                            </p>
                        </form>
                    </div>
                </div>

                <!-- Página de éxito (oculta por defecto) -->
                <div id="exito-container" style="display: none;">
                    <div class="item-cinco">
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
                <aside style="position: sticky; top: 100px; align-self: start;">

                    <!-- Beneficio 1 -->
                    <article class="item-cuatro" style="margin-bottom: var(--espacio-cuatro);">
                        <div class="caracteristica__icono" style="width: 56px; height: 56px; margin-bottom: var(--espacio-tres);">
                            <i data-lucide="users" class="icono"></i>
                        </div>
                        <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Mayor Visibilidad</h3>
                        <p style="color: var(--color-seis-claro); margin: 0;">
                            Llega a familias que buscan activamente servicios de cremación para sus mascotas.
                        </p>
                    </article>

                    <!-- Beneficio 2 -->
                    <article class="item-cuatro" style="margin-bottom: var(--espacio-cuatro);">
                        <div class="caracteristica__icono" style="width: 56px; height: 56px; margin-bottom: var(--espacio-tres);">
                            <i data-lucide="star" class="icono"></i>
                        </div>
                        <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-dos);">Reseñas y Confianza</h3>
                        <p style="color: var(--color-seis-claro); margin: 0;">
                            Las reseñas de clientes satisfechos aumentan la confianza en tu negocio.
                        </p>
                    </article>

                    <!-- Beneficio 3 -->
                    <article class="item-cuatro">
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
        // Enviar formulario
        function enviarFormulario(event) {
            event.preventDefault();

            const alerta = document.getElementById('alerta');

            // Validar campos requeridos
            const nombre = document.getElementById('nombre').value.trim();
            const email = document.getElementById('email').value.trim();
            const telefono = document.getElementById('telefono').value.trim();
            const nombreNegocio = document.getElementById('nombre_negocio').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            const ciudad = document.getElementById('ciudad').value.trim();
            const estado = document.getElementById('estado').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();

            // Validaciones
            const errores = [];

            if (!nombre) errores.push('El nombre de contacto es requerido');
            if (!email || !validarEmail(email)) errores.push('Email válido es requerido');
            if (!telefono) errores.push('El teléfono es requerido');
            if (!nombreNegocio) errores.push('El nombre del crematorio es requerido');
            if (!direccion) errores.push('La dirección es requerida');
            if (!ciudad) errores.push('La ciudad es requerida');
            if (!estado) errores.push('La provincia es requerida');
            if (!descripcion) errores.push('La descripción es requerida');

            if (errores.length > 0) {
                mostrarAlerta(errores.join('<br>'), 'error');
                return;
            }

            // Enviar vía AJAX
            const boton = document.querySelector('#formulario-registro button[type="submit"]');
            boton.disabled = true;
            boton.textContent = 'Enviando...';

            const formData = new FormData(document.getElementById('formulario-registro'));
            formData.append('tipo', 'registro');
            formData.append('page_url', window.location.href);

            fetch('<?php echo BASE_URL; ?>/procesar-formulario.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    dataLayer.push({
                        'event': 'form_submit_success',
                        'form_id': data.form_id,
                        'form_name': data.form_name
                    });
                    mostrarExito();
                } else {
                    mostrarAlerta(data.mensaje, 'error');
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
    </script>

<?php include 'includes/footer.php'; ?>
