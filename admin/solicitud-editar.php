<?php
/**
 * ═══════════════════════════════════════════════════════════
 * EDITAR SOLICITUD DE REGISTRO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin = obtenerAdminActual();
$pdo = obtenerConexion();

// Obtener ID
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: solicitudes.php');
    exit;
}

// Obtener solicitud
$sql = "SELECT * FROM solicitudes_registro WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    header('Location: solicitudes.php');
    exit;
}

// Solo permitir editar solicitudes pendientes
if ($solicitud['estado'] !== 'pendiente') {
    header('Location: solicitud-ver.php?id=' . $id);
    exit;
}

// Procesar formulario
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos
    $datos = [
        'contacto_nombre'   => trim($_POST['contacto_nombre'] ?? ''),
        'contacto_email'    => trim($_POST['contacto_email'] ?? ''),
        'contacto_telefono' => trim($_POST['contacto_telefono'] ?? ''),
        'nombre_negocio'    => trim($_POST['nombre_negocio'] ?? ''),
        'email_clientes'    => trim($_POST['email_clientes'] ?? ''),
        'telefono_clientes' => trim($_POST['telefono_clientes'] ?? ''),
        'pais'              => trim($_POST['pais'] ?? 'España'),
        'comunidad'         => trim($_POST['comunidad'] ?? ''),
        'provincia'         => trim($_POST['provincia'] ?? ''),
        'ciudad'            => trim($_POST['ciudad'] ?? ''),
        'direccion'         => trim($_POST['direccion'] ?? ''),
        'codigo_postal'     => trim($_POST['codigo_postal'] ?? ''),
        'descripcion'       => trim($_POST['descripcion'] ?? ''),
        'servicios'         => trim($_POST['servicios'] ?? ''),
        'horarios'          => trim($_POST['horarios'] ?? ''),
        'precios'           => trim($_POST['precios'] ?? ''),
        'comentarios_admin' => trim($_POST['comentarios_admin'] ?? ''),
        'sitio_web'         => trim($_POST['sitio_web'] ?? ''),
        'google_maps_url'   => trim($_POST['google_maps_url'] ?? ''),
        'whatsapp'          => trim($_POST['whatsapp'] ?? ''),
        'facebook'          => trim($_POST['facebook'] ?? ''),
        'instagram'         => trim($_POST['instagram'] ?? ''),
    ];

    // Validar campos obligatorios
    $errores = [];
    if (empty($datos['contacto_nombre'])) $errores[] = 'Nombre de contacto es requerido';
    if (empty($datos['contacto_email'])) $errores[] = 'Email de contacto es requerido';
    if (empty($datos['contacto_telefono'])) $errores[] = 'Teléfono de contacto es requerido';
    if (empty($datos['nombre_negocio'])) $errores[] = 'Nombre del negocio es requerido';
    if (empty($datos['provincia'])) $errores[] = 'Provincia es requerida';
    if (empty($datos['ciudad'])) $errores[] = 'Ciudad es requerida';
    if (empty($datos['direccion'])) $errores[] = 'Dirección es requerida';
    if (empty($datos['descripcion'])) $errores[] = 'Descripción es requerida';
    if (mb_strlen($datos['descripcion']) < 150) $errores[] = 'Descripción debe tener al menos 150 caracteres';

    if (!empty($errores)) {
        $error = implode('<br>', $errores);
    } else {
        // Actualizar
        $sql = "UPDATE solicitudes_registro SET
                    contacto_nombre = :contacto_nombre,
                    contacto_email = :contacto_email,
                    contacto_telefono = :contacto_telefono,
                    nombre_negocio = :nombre_negocio,
                    email_clientes = :email_clientes,
                    telefono_clientes = :telefono_clientes,
                    pais = :pais,
                    comunidad = :comunidad,
                    provincia = :provincia,
                    ciudad = :ciudad,
                    direccion = :direccion,
                    codigo_postal = :codigo_postal,
                    descripcion = :descripcion,
                    servicios = :servicios,
                    horarios = :horarios,
                    precios = :precios,
                    comentarios_admin = :comentarios_admin,
                    sitio_web = :sitio_web,
                    google_maps_url = :google_maps_url,
                    whatsapp = :whatsapp,
                    facebook = :facebook,
                    instagram = :instagram
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $datos['id'] = $id;

        if ($stmt->execute($datos)) {
            $mensaje = 'Solicitud actualizada correctamente';
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM solicitudes_registro WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $solicitud = $stmt->fetch();
        } else {
            $error = 'Error al actualizar la solicitud';
        }
    }
}

$titulo_pagina = 'Editar Solicitud #' . $id . ' - Admin';
include 'header.php';
?>

<div class="admin-page admin-page--narrow">

    <!-- Volver -->
    <a href="solicitud-ver.php?id=<?php echo $id; ?>" class="admin-link" style="display:inline-flex; align-items:center; gap:.35rem; margin-bottom: var(--espacio-tres); font-size: var(--admin-body-sm);">
        <i data-lucide="arrow-left" class="icono" style="width:14px; height:14px;"></i>
        Volver a ver solicitud
    </a>

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Editar solicitud</h1>
        <p class="admin-page-subtitle" style="font-variant-numeric: tabular-nums;">
            #<?php echo (int)$id; ?>
            <span class="admin-dash"></span>
            <?php echo htmlspecialchars($solicitud['nombre_negocio']); ?>
        </p>
    </header>

    <!-- ═══ Banners feedback ═══ -->
    <?php if ($mensaje): ?>
    <div class="admin-banner admin-banner--ok">
        <i data-lucide="check-circle" class="icono"></i>
        <div class="admin-banner__contenido">
            <span><?php echo $mensaje; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="admin-banner admin-banner--error">
        <i data-lucide="alert-triangle" class="icono"></i>
        <div class="admin-banner__contenido">
            <strong class="admin-banner__titulo">Hay errores en el formulario</strong>
            <span><?php echo $error; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

        <!-- ═══ Contacto comercial ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="user" class="icono" style="width:18px; height:18px;"></i>
                    Contacto comercial (privado)
                </h2>
            </div>
            <div class="admin-section__body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--espacio-cuatro);">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="contacto_nombre">Nombre <span class="field__req">requerido</span></label>
                    <input type="text" id="contacto_nombre" name="contacto_nombre" class="field__input" required
                           value="<?php echo htmlspecialchars($solicitud['contacto_nombre']); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="contacto_email">Email <span class="field__req">requerido</span></label>
                    <input type="email" id="contacto_email" name="contacto_email" class="field__input" required
                           value="<?php echo htmlspecialchars($solicitud['contacto_email']); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="contacto_telefono">Teléfono <span class="field__req">requerido</span></label>
                    <input type="tel" id="contacto_telefono" name="contacto_telefono" class="field__input" required
                           value="<?php echo htmlspecialchars($solicitud['contacto_telefono']); ?>">
                </div>
            </div>
        </section>

        <!-- ═══ Datos del negocio ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="building-2" class="icono" style="width:18px; height:18px;"></i>
                    Datos del negocio
                </h2>
            </div>
            <div class="admin-section__body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--espacio-cuatro);">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="nombre_negocio">Nombre del negocio <span class="field__req">requerido</span></label>
                    <input type="text" id="nombre_negocio" name="nombre_negocio" class="field__input" required
                           value="<?php echo htmlspecialchars($solicitud['nombre_negocio']); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="email_clientes">Email para clientes</label>
                    <input type="email" id="email_clientes" name="email_clientes" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['email_clientes'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="telefono_clientes">Teléfono para clientes</label>
                    <input type="tel" id="telefono_clientes" name="telefono_clientes" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['telefono_clientes'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <!-- ═══ Ubicación ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="map-pin" class="icono" style="width:18px; height:18px;"></i>
                    Ubicación
                </h2>
            </div>
            <div class="admin-section__body" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--espacio-cuatro);">
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label" for="pais">País <span class="field__req">requerido</span></label>
                        <select id="pais" name="pais" class="field__select" required>
                            <?php
                            $paises = ['España', 'México', 'Argentina', 'Colombia', 'Chile', 'Perú', 'Ecuador', 'Venezuela', 'Uruguay', 'Paraguay', 'Bolivia', 'Costa Rica', 'Panamá', 'Guatemala', 'Honduras', 'El Salvador', 'Nicaragua', 'República Dominicana', 'Puerto Rico', 'Cuba', 'Otro'];
                            foreach ($paises as $p):
                            ?>
                            <option value="<?php echo $p; ?>" <?php echo ($solicitud['pais'] ?? 'España') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label" for="comunidad">Comunidad / Región</label>
                        <input type="text" id="comunidad" name="comunidad" class="field__input"
                               value="<?php echo htmlspecialchars($solicitud['comunidad'] ?? ''); ?>">
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label" for="provincia">Provincia <span class="field__req">requerido</span></label>
                        <input type="text" id="provincia" name="provincia" class="field__input" required
                               value="<?php echo htmlspecialchars($solicitud['provincia']); ?>">
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label" for="ciudad">Ciudad <span class="field__req">requerido</span></label>
                        <input type="text" id="ciudad" name="ciudad" class="field__input" required
                               value="<?php echo htmlspecialchars($solicitud['ciudad']); ?>">
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label" for="codigo_postal">Código postal</label>
                        <input type="text" id="codigo_postal" name="codigo_postal" class="field__input"
                               value="<?php echo htmlspecialchars($solicitud['codigo_postal'] ?? ''); ?>">
                    </div>
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="direccion">Dirección completa <span class="field__req">requerido</span></label>
                    <input type="text" id="direccion" name="direccion" class="field__input" required
                           value="<?php echo htmlspecialchars($solicitud['direccion']); ?>">
                </div>
            </div>
        </section>

        <!-- ═══ Contenido ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="file-text" class="icono" style="width:18px; height:18px;"></i>
                    Contenido
                </h2>
            </div>
            <div class="admin-section__body" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="descripcion">
                        Descripción
                        <span class="field__req">requerido</span>
                        <span class="field__req">mín. 150 caracteres</span>
                    </label>
                    <textarea id="descripcion" name="descripcion" class="field__textarea" rows="6" required minlength="150"><?php echo htmlspecialchars($solicitud['descripcion']); ?></textarea>
                    <p class="field__hint" id="contador-descripcion">
                        <span id="contador-num"><?php echo mb_strlen($solicitud['descripcion']); ?></span> / 150 caracteres mínimo
                    </p>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="servicios">Servicios</label>
                    <textarea id="servicios" name="servicios" class="field__textarea" rows="4"><?php echo htmlspecialchars($solicitud['servicios'] ?? ''); ?></textarea>
                </div>

                <div class="field">
                    <label class="field__label" for="horarios">Horarios</label>
                    <textarea id="horarios" name="horarios" class="field__textarea" rows="4"><?php echo htmlspecialchars($solicitud['horarios'] ?? ''); ?></textarea>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="precios">Precios o tarifas (opcional)</label>
                    <textarea id="precios" name="precios" class="field__textarea" rows="3"><?php echo htmlspecialchars($solicitud['precios'] ?? ''); ?></textarea>
                </div>
            </div>
        </section>

        <!-- ═══ Presencia online ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="globe" class="icono" style="width:18px; height:18px;"></i>
                    Presencia en línea
                </h2>
            </div>
            <div class="admin-section__body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--espacio-cuatro);">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="sitio_web">Sitio web</label>
                    <input type="url" id="sitio_web" name="sitio_web" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['sitio_web'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="google_maps_url">URL de Google Maps</label>
                    <input type="url" id="google_maps_url" name="google_maps_url" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['google_maps_url'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="whatsapp">WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['whatsapp'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="facebook">Facebook</label>
                    <input type="url" id="facebook" name="facebook" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['facebook'] ?? ''); ?>">
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="instagram">Instagram</label>
                    <input type="url" id="instagram" name="instagram" class="field__input"
                           value="<?php echo htmlspecialchars($solicitud['instagram'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <!-- ═══ Comentarios internos ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="message-circle" class="icono" style="width:18px; height:18px;"></i>
                    Notas internas
                </h2>
            </div>
            <div class="admin-section__body">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label" for="comentarios_admin">Comentarios (no se muestran públicamente)</label>
                    <textarea id="comentarios_admin" name="comentarios_admin" class="field__textarea" rows="4"><?php echo htmlspecialchars($solicitud['comentarios_admin'] ?? ''); ?></textarea>
                </div>
            </div>
        </section>

        <!-- ═══ Acciones ═══ -->
        <div style="display: flex; gap: var(--espacio-dos); justify-content: flex-end; padding: var(--espacio-cuatro); background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); flex-wrap: wrap;">
            <a href="solicitud-ver.php?id=<?php echo $id; ?>" class="boton dos">
                Cancelar
            </a>
            <button type="submit" class="boton uno">
                <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                Guardar cambios
            </button>
        </div>

    </form>
</div>

<script>
// Contador de caracteres para descripción
(function() {
    const descripcion = document.getElementById('descripcion');
    const contador    = document.getElementById('contador-descripcion');
    const num         = document.getElementById('contador-num');
    if (!descripcion || !contador || !num) return;

    function actualizarContador() {
        const len = descripcion.value.length;
        num.textContent = len;
        const ok = len >= 150;
        contador.style.color = ok ? 'var(--admin-tone-exito-fg)' : 'var(--admin-tone-alerta-fg)';
        // Estado visual del propio textarea (demo del patrón error/success de Fase 3)
        descripcion.classList.toggle('field__textarea--success', ok);
        descripcion.classList.toggle('field__textarea--error', !ok && len > 0);
    }
    descripcion.addEventListener('input', actualizarContador);
    actualizarContador();
})();
</script>

<?php include 'footer.php'; ?>
