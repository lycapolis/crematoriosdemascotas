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

$titulo_pagina = 'Registrar tu negocio - Crematorios de Mascotas';
$pagina_actual = 'registrar-negocio';
$GEO_ES = include __DIR__ . '/includes/geo_es.php'; // CCAA → provincias (cascada España)
require_once __DIR__ . '/includes/actualizacion-ficha.php';
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
                            <h1 style="font-size: var(--fs-siete); margin: 0 0 var(--espacio-dos); line-height: 1.15;">Registra tu negocio</h1>
                            <p style="margin: 0; color: var(--admin-tinta-suave); font-size: var(--fs-cuatro); line-height: 1.5;">Únete a nuestro directorio y conecta con familias que buscan servicios de cremación para sus mascotas.</p>
                        </header>

                        <!-- Alerta -->
                        <div id="alerta" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

                        <!-- Cartel buenas prácticas -->
                        <div class="callout" style="margin-bottom: var(--espacio-cuatro);">
                            <i data-lucide="info" class="callout__icon"></i>
                            <div class="callout__body">
                                <strong>Antes de empezar — lee esto:</strong>
                                <ul>
                                    <li>Escribe el nombre <strong>sin la ciudad</strong>. La dirección web de tu ficha se genera automáticamente como <em>tu-crematorio-ciudad</em> y <strong>no se puede cambiar después</strong> (es importante para que Google te encuentre).</li>
                                    <li>Revisa que la <strong>ciudad y la dirección coincidan</strong> (no mezclar la ciudad de un sitio con la dirección de otro).</li>
                                    <li>Pega las URLs de <strong>sitio web y redes sociales completas</strong> (con <code>https://</code>) y verifica que estén bien escritas.</li>
                                </ul>
                            </div>
                        </div>

                        <form id="formulario-registro" onsubmit="enviarFormulario(event)" enctype="multipart/form-data" novalidate style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                            <?php
                            // Campos compartidos con el formulario de actualización de verificar-ficha.php
                            $ffModo  = 'alta';
                            $ffDatos = [];
                            include __DIR__ . '/includes/form-ficha-campos.php';
                            ?>

                            <!-- Consentimientos: el de revisión es obligatorio; el de comunicaciones comerciales, opcional -->
                            <div style="display: flex; flex-direction: column; gap: var(--espacio-dos);">
                                <label class="field__opcion" style="align-items: flex-start; gap: 0.6rem;">
                                    <input type="checkbox" class="field__check" id="consentimiento" name="consentimiento" value="1">
                                    <span>Acepto que revisen mi información para incluirla en el directorio de Crematorios de Mascotas y que se me comuniquen novedades sobre mi ficha. *</span>
                                </label>

                                <label class="field__opcion" style="align-items: flex-start; gap: 0.6rem;">
                                    <input type="checkbox" class="field__check" id="consentimiento_comunicaciones" name="consentimiento_comunicaciones" value="1">
                                    <span>Quiero recibir también ofertas comerciales y novedades de servicios para mi negocio (opcional).</span>
                                </label>
                            </div>

                            <button type="submit" class="boton uno grande" style="width: 100%;">
                                Enviar solicitud de registro
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
            if (!nombreNegocio) errores.push('El nombre del negocio es requerido');
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
                'consentimiento'
            ];

            if (errores.length > 0) {
                const lista = 'Faltan datos obligatorios:<br>• ' + errores.join('<br>• ');
                if (window.toast) { toast.error(lista); }
                else { mostrarAlerta(errores.join('<br>'), 'error'); }
                var faltante = primerFaltante(ORDEN_CAMPOS);
                if (faltante) irACampo(faltante);
                return;
            }

            // Consentimiento de revisión obligatorio (el de ofertas comerciales es opcional)
            const consentimiento = document.getElementById('consentimiento');
            if (!consentimiento.checked) {
                const msg = 'Para enviar la solicitud tienes que aceptar que revisemos tu información.';
                if (window.toast) { toast.error(msg); } else { mostrarAlerta(msg, 'error'); }
                irACampo('consentimiento');
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
                    boton.textContent = 'Enviar solicitud de registro';
                }
            })
            .catch(() => {
                mostrarAlerta('Error de conexión. Inténtalo de nuevo.', 'error');
                boton.disabled = false;
                boton.textContent = 'Enviar solicitud de registro';
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