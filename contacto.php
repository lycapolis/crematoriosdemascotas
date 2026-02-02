<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CONTACTO - CREMATORIOS DE MASCOTAS
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

$titulo_pagina = 'Contacto - Crematorios de Mascotas';
$pagina_actual = 'contacto';
include 'includes/header.php';
?>

<style>
    /* Responsive para tarjetas de contacto */
    @media (max-width: 767px) {
        .contacto-tarjeta {
            padding: var(--espacio-tres) !important;
        }
    }
</style>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor">
            <h1>Contáctanos</h1>
            <h2 class="seccion__descripcion estilo-h5 seis">
                ¿Tienes alguna pregunta o sugerencia? Estamos aquí para ayudarte.
            </h2>
            
            
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div class="layout-dos-columnas">

                <!-- COLUMNA 1: Información de contacto -->
                <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">
                    <div>
                        <h2>Información de Contacto</h2>
                        <p style="color: var(--color-seis-claro);">
                            Puedes comunicarte con nosotros a través de los siguientes medios
                            o completando el formulario de contacto.
                        </p>
                    </div>

                    <!-- Email -->
                    <article class="item-tres contacto-tarjeta" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos); border: 1px solid var(--color-cinco);">
                        <div class="caracteristica__icono" style="width: 48px; height: 48px; margin: 0;">
                            <i data-lucide="mail" class="icono"></i>
                        </div>
                        <div>
                            <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-uno);">Email</h3>
                            <p style="color: var(--color-seis-claro); margin: 0;">
                                <a href="mailto:contacto@crematoriosdemascotas.com" style="color: var(--color-uno); text-decoration: none; word-break: break-all;">contacto@crematoriosdemascotas.com</a>
                            </p>
                        </div>
                    </article>

                    <!-- Teléfono -->
                    <article class="item-tres contacto-tarjeta" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos); border: 1px solid var(--color-cinco);">
                        <div class="caracteristica__icono" style="width: 48px; height: 48px; margin: 0;">
                            <i data-lucide="phone" class="icono"></i>
                        </div>
                        <div>
                            <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-uno);">Teléfono</h3>
                            <p style="color: var(--color-seis-claro); margin: 0;">
                                <a href="tel:+34612345678" style="color: var(--color-uno); text-decoration: none;">+34 612 345 678</a>
                            </p>
                        </div>
                    </article>

                    <!-- Horario -->
                    <article class="item-tres contacto-tarjeta" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos); border: 1px solid var(--color-cinco);">
                        <div class="caracteristica__icono" style="width: 48px; height: 48px; margin: 0;">
                            <i data-lucide="clock" class="icono"></i>
                        </div>
                        <div>
                            <h3 style="font-size: var(--fs-dos); margin-bottom: var(--espacio-uno);">Horario de Atención</h3>
                            <p style="color: var(--color-seis-claro); margin: 0;">
                                Lunes a Viernes: 9:00 - 18:00<br>
                                Sábados: 9:00 - 14:00
                            </p>
                        </div>
                    </article>
                </div>

                <!-- COLUMNA 2: Formulario -->
                <div class="tarjeta simple" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border: 1px solid var(--color-cinco);">
                    <h2 style="font-size: var(--fs-cuatro); margin-bottom: var(--espacio-cuatro);">Envíanos un Mensaje</h2>

                    <!-- Alerta -->
                    <div id="alerta" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

                    <!-- Formulario -->
                    <form id="formulario-contacto" onsubmit="enviarFormulario(event)">

                        <!-- Nombre -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" class="campo" required placeholder="Tu nombre completo">
                        </div>

                        <!-- Email -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="email">Email *</label>
                            <input type="email" id="email" name="email" class="campo" required placeholder="tu@email.com">
                        </div>

                        <!-- Teléfono -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="campo" placeholder="+34 600 000 000">
                        </div>

                        <!-- Asunto -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="asunto">Asunto</label>
                            <select id="asunto" name="asunto" class="seleccion">
                                <option value="consulta">Consulta general</option>
                                <option value="sugerencia">Sugerencia</option>
                                <option value="problema">Reportar problema</option>
                                <option value="negocio">Información para negocios</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <!-- Mensaje -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="mensaje">Mensaje *</label>
                            <textarea id="mensaje" name="mensaje" class="area-texto" required placeholder="Escribe tu mensaje aquí..." rows="6"></textarea>
                        </div>

                        <!-- Botón submit -->
                        <button type="submit" class="boton uno grande" style="width: 100%;">
                            Enviar Mensaje
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- Script específico de la página -->
    <script>
        // Enviar formulario (simulación)
        function enviarFormulario(event) {
            event.preventDefault();

            const alerta = document.getElementById('alerta');
            const formulario = document.getElementById('formulario-contacto');

            // Validar campos
            const nombre = document.getElementById('nombre').value.trim();
            const email = document.getElementById('email').value.trim();
            const mensaje = document.getElementById('mensaje').value.trim();

            if (!nombre || !email || !mensaje) {
                mostrarAlerta('Por favor completa todos los campos requeridos.', 'error');
                return;
            }

            if (!validarEmail(email)) {
                mostrarAlerta('Por favor ingresa un email válido.', 'error');
                return;
            }

            // Enviar vía AJAX
            const boton = document.querySelector('#formulario-contacto button[type="submit"]');
            boton.disabled = true;
            boton.textContent = 'Enviando...';

            const formData = new FormData(formulario);
            formData.append('tipo', 'contacto');
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
                    mostrarAlerta('¡Mensaje enviado! Te responderemos lo antes posible.', 'exito');
                    formulario.reset();
                } else {
                    mostrarAlerta(data.mensaje || 'Error al enviar. Inténtalo de nuevo.', 'error');
                }
                boton.disabled = false;
                boton.textContent = 'Enviar Mensaje';
            })
            .catch(() => {
                mostrarAlerta('Error de conexión. Inténtalo de nuevo.', 'error');
                boton.disabled = false;
                boton.textContent = 'Enviar Mensaje';
            });
        }

        // Mostrar alerta
        function mostrarAlerta(mensaje, tipo) {
            const alerta = document.getElementById('alerta');
            alerta.textContent = mensaje;
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
