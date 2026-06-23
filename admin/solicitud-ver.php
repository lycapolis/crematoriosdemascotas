<?php
/**
 * ═══════════════════════════════════════════════════════════
 * VER SOLICITUD DE REGISTRO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$admin = obtenerAdminActual();
$pdo = obtenerConexion();

// Obtener ID
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: solicitudes.php');
    exit;
}

// Obtener solicitud (con slug del crematorio si fue aprobada)
$sql = "SELECT s.*, a.nombre AS moderador_nombre, c.slug AS crematorio_slug
        FROM solicitudes_registro s
        LEFT JOIN admins a ON s.moderado_por = a.id
        LEFT JOIN crematorios c ON s.crematorio_id = c.id
        WHERE s.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    header('Location: solicitudes.php');
    exit;
}

// Marcar como vista para este admin (se usa al aprobar/rechazar desde el listado)
if (!isset($_SESSION['solicitudes_vistas']) || !is_array($_SESSION['solicitudes_vistas'])) {
    $_SESSION['solicitudes_vistas'] = [];
}
$_SESSION['solicitudes_vistas'][$id] = time();

// Obtener imágenes
$sqlImg = "SELECT * FROM solicitud_imagenes WHERE solicitud_id = :id ORDER BY tipo DESC, orden ASC";
$stmtImg = $pdo->prepare($sqlImg);
$stmtImg->execute([':id' => $id]);
$imagenes = $stmtImg->fetchAll();

// Separar logo y galería (normalizar rutas para URLs)
$logo = null;
$galeria = [];
foreach ($imagenes as $img) {
    // Normalizar ruta (convertir backslashes a forward slashes para URLs)
    $img['ruta'] = str_replace('\\', '/', $img['ruta']);
    if ($img['tipo'] === 'logo') {
        $logo = $img;
    } else {
        $galeria[] = $img;
    }
}

$titulo_pagina = 'Solicitud #' . $id . ' - Admin';
include 'header.php';

// Pill por estado
$estadoPill = match ($solicitud['estado']) {
    'pendiente' => 'admin-pill--alerta',
    'aprobada'  => 'admin-pill--exito',
    'rechazada' => 'admin-pill--error',
    default     => '',
};
?>

<div class="admin-page admin-page--narrow">

    <!-- Volver -->
    <a href="solicitudes.php" class="admin-link" style="display:inline-flex; align-items:center; gap:.35rem; margin-bottom: var(--espacio-tres); font-size: var(--admin-body-sm);">
        <i data-lucide="arrow-left" class="icono" style="width:14px; height:14px;"></i>
        Volver a solicitudes
    </a>

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <div style="min-width: 0; flex: 1;">
            <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap; margin-bottom: .3rem;">
                <h1 class="admin-page-title"><?php echo htmlspecialchars($solicitud['nombre_negocio']); ?></h1>
                <span class="admin-pill <?php echo $estadoPill; ?>">
                    <?php echo ucfirst($solicitud['estado']); ?>
                </span>
            </div>
        </div>
        <p class="admin-page-subtitle" style="font-variant-numeric: tabular-nums;">
            Solicitud #<?php echo (int)$solicitud['id']; ?>
            <span class="admin-dash"></span>
            <?php echo date('d/m/Y H:i', strtotime($solicitud['created_at'])); ?>
        </p>
    </header>

    <!-- Contenido principal -->
    <div style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

        <!-- ═══ Contacto comercial (privado) ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="user" class="icono" style="width:18px; height:18px;"></i>
                    Contacto comercial (privado)
                </h2>
            </div>
            <div class="admin-section__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--espacio-cuatro);">
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Nombre</div>
                        <div style="font-weight: 600; color: var(--admin-tinta-fuerte); margin-top: .25rem;"><?php echo htmlspecialchars($solicitud['contacto_nombre']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Email</div>
                        <div style="margin-top: .25rem;">
                            <a href="mailto:<?php echo htmlspecialchars($solicitud['contacto_email']); ?>" class="admin-link">
                                <?php echo htmlspecialchars($solicitud['contacto_email']); ?>
                            </a>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Teléfono</div>
                        <div style="margin-top: .25rem; font-variant-numeric: tabular-nums;">
                            <a href="tel:<?php echo htmlspecialchars($solicitud['contacto_telefono']); ?>" class="admin-link">
                                <?php echo htmlspecialchars($solicitud['contacto_telefono']); ?>
                            </a>
                        </div>
                    </div>
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
            <div class="admin-section__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--espacio-cuatro);">
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Nombre del negocio</div>
                        <div style="font-weight: 700; font-size: var(--admin-body); color: var(--admin-tinta-fuerte); margin-top: .25rem;"><?php echo htmlspecialchars($solicitud['nombre_negocio']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Email para clientes</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['email_clientes'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Teléfono para clientes</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;"><?php echo htmlspecialchars($solicitud['telefono_clientes'] ?? '—'); ?></div>
                    </div>
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
            <div class="admin-section__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--espacio-cuatro);">
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">País</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['pais']); ?></div>
                    </div>
                    <?php if ($solicitud['comunidad']): ?>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Comunidad</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['comunidad']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Provincia</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['provincia']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Ciudad</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['ciudad']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Código postal</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;"><?php echo htmlspecialchars($solicitud['codigo_postal'] ?? '—'); ?></div>
                    </div>
                </div>
                <div style="margin-top: var(--espacio-cuatro); padding-top: var(--espacio-tres); border-top: 1px solid var(--admin-linea);">
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Dirección completa</div>
                    <div style="margin-top: .25rem; font-weight: 600; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['direccion']); ?></div>
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

                <div>
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom: .4rem;">
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Descripción</div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-tenue); font-variant-numeric: tabular-nums;">
                            <?php echo mb_strlen($solicitud['descripcion']); ?> caracteres
                        </div>
                    </div>
                    <div style="padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); line-height: 1.6; color: var(--admin-tinta); font-size: var(--admin-body-sm);">
                        <?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?>
                    </div>
                </div>

                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .4rem;">Servicios</div>
                    <div style="padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); line-height: 1.6; color: var(--admin-tinta); font-size: var(--admin-body-sm);">
                        <?php echo nl2br(htmlspecialchars($solicitud['servicios'] ?? '—')); ?>
                    </div>
                </div>

                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .4rem;">Horarios</div>
                    <div style="padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); line-height: 1.6; color: var(--admin-tinta); font-size: var(--admin-body-sm);">
                        <?php echo nl2br(htmlspecialchars($solicitud['horarios'] ?? '—')); ?>
                    </div>
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
            <div class="admin-section__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--espacio-cuatro);">
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Sitio web</div>
                        <div style="margin-top: .25rem;">
                            <?php if ($solicitud['sitio_web']): ?>
                            <a href="<?php echo htmlspecialchars($solicitud['sitio_web']); ?>" target="_blank" class="admin-link" style="word-break: break-all;">
                                <?php echo htmlspecialchars($solicitud['sitio_web']); ?>
                            </a>
                            <?php else: ?>
                            <span style="color: var(--admin-tinta-tenue);">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Google Maps</div>
                        <div style="margin-top: .25rem;">
                            <?php if ($solicitud['google_maps_url']): ?>
                            <a href="<?php echo htmlspecialchars($solicitud['google_maps_url']); ?>" target="_blank" class="admin-link">
                                Abrir en Maps ↗
                            </a>
                            <?php else: ?>
                            <span style="color: var(--admin-tinta-tenue);">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">WhatsApp</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;">
                            <?php echo htmlspecialchars($solicitud['whatsapp'] ?? '—'); ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Facebook</div>
                        <div style="margin-top: .25rem;">
                            <?php if ($solicitud['facebook']): ?>
                            <a href="<?php echo htmlspecialchars($solicitud['facebook']); ?>" target="_blank" class="admin-link">Ver perfil ↗</a>
                            <?php else: ?>
                            <span style="color: var(--admin-tinta-tenue);">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Instagram</div>
                        <div style="margin-top: .25rem;">
                            <?php if ($solicitud['instagram']): ?>
                            <a href="<?php echo htmlspecialchars($solicitud['instagram']); ?>" target="_blank" class="admin-link">Ver perfil ↗</a>
                            <?php else: ?>
                            <span style="color: var(--admin-tinta-tenue);">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ Imágenes ═══ -->
        <?php if ($logo || !empty($galeria)): ?>
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="image" class="icono" style="width:18px; height:18px;"></i>
                    Imágenes
                    <?php $totalImgs = ($logo ? 1 : 0) + count($galeria); ?>
                    <span class="admin-section__count"><?php echo $totalImgs; ?></span>
                </h2>
            </div>
            <div class="admin-section__body" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                <?php
                // Preparar array unificado para el lightbox (logo + galería en orden)
                $lbImgs = [];
                if ($logo) {
                    $lbImgs[] = [
                        'id'     => (int)$logo['id'],
                        'src'    => BASE_URL . '/' . str_replace('\\', '/', $logo['ruta']),
                        'nombre' => basename($logo['ruta']),
                        'label'  => 'Logo',
                    ];
                }
                foreach ($galeria as $img) {
                    $lbImgs[] = [
                        'id'     => (int)$img['id'],
                        'src'    => BASE_URL . '/' . str_replace('\\', '/', $img['ruta']),
                        'nombre' => basename($img['ruta']),
                        'label'  => 'Galería · #' . (int)$img['orden'],
                    ];
                }
                ?>

                <?php $puedeEliminar = $solicitud['estado'] === 'pendiente'; ?>

                <?php if ($logo): ?>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .5rem;">Logo</div>
                    <div style="position: relative; display: inline-block;">
                        <button type="button" class="sol-lb-thumb" data-lb-idx="0" style="all: unset; cursor: zoom-in; display: inline-block;">
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars(str_replace('\\','/',$logo['ruta'])); ?>"
                                 alt="Logo"
                                 style="max-width: 200px; border-radius: var(--admin-r-sm); border: 1px solid var(--admin-linea); display: block; transition: transform .2s, box-shadow .2s;"
                                 onmouseover="this.style.boxShadow='var(--admin-sombra-media)'"
                                 onmouseout="this.style.boxShadow='none'">
                        </button>
                        <?php if ($puedeEliminar): ?>
                        <button type="button"
                                onclick="eliminarImgSolicitud(<?php echo (int)$logo['id']; ?>, this)"
                                title="Eliminar logo"
                                aria-label="Eliminar logo"
                                style="position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border-radius: 50%; background: rgba(220, 38, 38, .92); color: #fff; border: 0; cursor: pointer; display: grid; place-items: center; transition: transform .12s, background .15s; box-shadow: 0 2px 6px rgba(0,0,0,.2);"
                                onmouseover="this.style.transform='scale(1.08)'; this.style.background='rgba(220,38,38,1)';"
                                onmouseout="this.style.transform=''; this.style.background='rgba(220,38,38,.92)';">
                            <i data-lucide="x" class="icono" style="width: 14px; height: 14px;"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($galeria)): ?>
                <div>
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .5rem;">Galería (<?php echo count($galeria); ?> <?php echo count($galeria) === 1 ? 'imagen' : 'imágenes'; ?>)</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--espacio-tres);">
                        <?php $offset = $logo ? 1 : 0; foreach ($galeria as $i => $img): ?>
                        <div style="position: relative;">
                            <button type="button" class="sol-lb-thumb" data-lb-idx="<?php echo $i + $offset; ?>"
                                    style="all: unset; cursor: zoom-in; position: relative; aspect-ratio: 1; overflow: hidden; border-radius: var(--admin-r-sm); border: 1px solid var(--admin-linea); transition: border-color .15s, box-shadow .15s; display: block; width: 100%;"
                                    onmouseover="this.style.borderColor='var(--admin-brand)'; this.style.boxShadow='var(--admin-sombra-media)';"
                                    onmouseout="this.style.borderColor='var(--admin-linea)'; this.style.boxShadow='none';">
                                <img src="<?php echo BASE_URL . '/' . htmlspecialchars(str_replace('\\','/',$img['ruta'])); ?>"
                                     alt="Imagen galería"
                                     style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                <span style="position: absolute; bottom: 6px; right: 6px; background: rgba(44, 36, 23, 0.85); color: #fff; padding: .15rem .5rem; border-radius: 999px; font-size: var(--admin-kicker); font-weight: 700; font-variant-numeric: tabular-nums;">
                                    <?php echo (int)$img['orden']; ?>
                                </span>
                            </button>
                            <?php if ($puedeEliminar): ?>
                            <button type="button"
                                    onclick="eliminarImgSolicitud(<?php echo (int)$img['id']; ?>, this)"
                                    title="Eliminar imagen"
                                    aria-label="Eliminar imagen"
                                    style="position: absolute; top: 6px; left: 6px; width: 24px; height: 24px; border-radius: 50%; background: rgba(220, 38, 38, .92); color: #fff; border: 0; cursor: pointer; display: grid; place-items: center; transition: transform .12s, background .15s; box-shadow: 0 2px 6px rgba(0,0,0,.2);"
                                    onmouseover="this.style.transform='scale(1.08)'; this.style.background='rgba(220,38,38,1)';"
                                    onmouseout="this.style.transform=''; this.style.background='rgba(220,38,38,.92)';">
                                <i data-lucide="x" class="icono" style="width: 13px; height: 13px;"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ═══ Comentarios adicionales ═══ -->
        <?php if ($solicitud['comentarios_admin']): ?>
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="message-circle" class="icono" style="width:18px; height:18px;"></i>
                    Comentarios adicionales
                </h2>
            </div>
            <div class="admin-section__body">
                <div style="padding: var(--espacio-tres); background: var(--admin-papel-alt); border-radius: var(--admin-r-sm); line-height: 1.6; color: var(--admin-tinta); font-size: var(--admin-body-sm);">
                    <?php echo nl2br(htmlspecialchars($solicitud['comentarios_admin'])); ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ═══ Información técnica ═══ -->
        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="shield-check" class="icono" style="width:18px; height:18px;"></i>
                    Consentimientos (RGPD)
                </h2>
            </div>
            <div class="admin-section__body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:var(--espacio-cuatro);">
                    <div>
                        <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Inclusión en el directorio</div>
                        <div style="margin-top:.35rem;">
                            <span class="admin-pill <?php echo !empty($solicitud['consentimiento']) ? 'admin-pill--exito' : 'admin-pill--alerta'; ?>">
                                <?php echo !empty($solicitud['consentimiento']) ? 'Aceptado' : 'No registrado'; ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Comunicaciones / ofertas (marketing)</div>
                        <div style="margin-top:.35rem;">
                            <span class="admin-pill <?php echo !empty($solicitud['consentimiento_comunicaciones']) ? 'admin-pill--exito' : 'admin-pill--alerta'; ?>">
                                <?php echo !empty($solicitud['consentimiento_comunicaciones']) ? 'Aceptado' : 'No registrado'; ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Fecha del consentimiento</div>
                        <div style="margin-top:.25rem; color:var(--admin-tinta-fuerte); font-variant-numeric:tabular-nums; font-size:var(--admin-body-sm);">
                            <?php echo !empty($solicitud['consentimiento_fecha']) ? date('d/m/Y H:i:s', strtotime($solicitud['consentimiento_fecha'])) : '—'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section" style="margin-bottom: 0;">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="info" class="icono" style="width:18px; height:18px;"></i>
                    Información técnica
                </h2>
            </div>
            <div class="admin-section__body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--espacio-cuatro);">
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">IP</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-family: monospace; font-size: var(--admin-body-sm);"><?php echo htmlspecialchars($solicitud['ip_address'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Enviado desde</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); word-break: break-all; font-size: var(--admin-body-sm);"><?php echo htmlspecialchars($solicitud['page_url'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Fecha creación</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums; font-size: var(--admin-body-sm);"><?php echo date('d/m/Y H:i:s', strtotime($solicitud['created_at'])); ?></div>
                    </div>
                    <?php if ($solicitud['moderado_en']): ?>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Moderado por</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte);"><?php echo htmlspecialchars($solicitud['moderador_nombre'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Fecha moderación</div>
                        <div style="margin-top: .25rem; color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums; font-size: var(--admin-body-sm);"><?php echo date('d/m/Y H:i:s', strtotime($solicitud['moderado_en'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($solicitud['crematorio_id'] && $solicitud['crematorio_slug']): ?>
                    <div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600;">Ficha creada</div>
                        <div style="margin-top: .25rem;">
                            <a href="<?php echo BASE_URL . '/' . urlencode($solicitud['crematorio_slug']); ?>" target="_blank" class="admin-link">
                                Ver ficha pública #<?php echo (int)$solicitud['crematorio_id']; ?> ↗
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($solicitud['motivo_rechazo']): ?>
                <div style="margin-top: var(--espacio-cuatro); padding-top: var(--espacio-tres); border-top: 1px solid var(--admin-linea);">
                    <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); font-weight: 600; margin-bottom: .35rem;">Motivo de rechazo</div>
                    <div style="padding: var(--espacio-tres); background: var(--admin-tone-error-bg); border-radius: var(--admin-r-sm); color: var(--admin-tone-error-fg); font-size: var(--admin-body-sm); line-height: 1.55;">
                        <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ═══ Acciones según estado ═══ -->
        <?php if ($solicitud['estado'] === 'pendiente'): ?>
        <div style="display: flex; gap: var(--espacio-dos); justify-content: center; padding: var(--espacio-cuatro); background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); flex-wrap: wrap;">
            <a href="solicitud-editar.php?id=<?php echo $solicitud['id']; ?>" class="boton dos">
                <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                Editar
            </a>
            <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'aprobar')" class="boton tres">
                <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
                Aprobar y crear ficha
            </button>
            <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'rechazar')" class="boton"
                    style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);">
                <i data-lucide="x" class="icono" style="width:14px; height:14px;"></i>
                Rechazar
            </button>
        </div>
        <?php elseif ($solicitud['estado'] === 'rechazada'): ?>
        <div style="display: flex; gap: var(--espacio-dos); justify-content: center; padding: var(--espacio-cuatro); background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); flex-wrap: wrap;">
            <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'reevaluar')" class="boton dos"
                    title="Devolver a pendiente para reevaluarla">
                <i data-lucide="rotate-ccw" class="icono" style="width:14px; height:14px;"></i>
                Reevaluar
            </button>
            <button onclick="accionSolicitud(<?php echo $solicitud['id']; ?>, 'eliminar')" class="boton"
                    style="background: var(--color-siete); color: var(--color-ocho); border-color: var(--color-siete);"
                    title="Eliminar definitivamente esta solicitud y sus imágenes">
                <i data-lucide="trash-2" class="icono" style="width:14px; height:14px;"></i>
                Eliminar definitivamente
            </button>
        </div>
        <?php elseif ($solicitud['estado'] === 'aprobada' && !empty($solicitud['crematorio_slug'])): ?>
        <div style="display: flex; gap: var(--espacio-dos); justify-content: center; padding: var(--espacio-cuatro); background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); flex-wrap: wrap;">
            <a href="<?php echo BASE_URL . '/' . urlencode($solicitud['crematorio_slug']); ?>" target="_blank" class="boton tres">
                <i data-lucide="external-link" class="icono" style="width:14px; height:14px;"></i>
                Ver ficha pública
            </a>
            <a href="editar-ficha-negocio.php?id=<?php echo (int)$solicitud['crematorio_id']; ?>" class="boton dos">
                <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                Editar ficha
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if (!empty($lbImgs)): ?>
<!-- ═══ Lightbox ═══ -->
<div id="sol-lb" style="display:none; position:fixed; inset:0; z-index:200; background:rgba(28,20,12,.86); align-items:center; justify-content:center; padding:var(--espacio-cuatro); flex-direction:column; gap:var(--espacio-tres);">
    <button type="button" id="sol-lb-close" aria-label="Cerrar"
            style="position:absolute; top:var(--espacio-cuatro); right:var(--espacio-cuatro); width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
            onmouseover="this.style.background='rgba(255,255,255,.22)'"
            onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <i data-lucide="x" class="icono" style="width:20px; height:20px;"></i>
    </button>
    <div style="position:relative; width:100%; max-width:1100px; flex:1; display:flex; align-items:center; justify-content:center; min-height:0;">
        <button type="button" id="sol-lb-prev" aria-label="Anterior"
                style="position:absolute; left:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'"
                onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-left" class="icono" style="width:24px; height:24px;"></i>
        </button>
        <img id="sol-lb-img" src="" alt=""
             style="max-width:100%; max-height:80vh; border-radius:var(--admin-r-md); box-shadow:0 8px 40px rgba(0,0,0,.4); object-fit:contain;">
        <button type="button" id="sol-lb-next" aria-label="Siguiente"
                style="position:absolute; right:0; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; border:0; cursor:pointer; display:grid; place-items:center; transition:background .15s;"
                onmouseover="this.style.background='rgba(255,255,255,.22)'"
                onmouseout="this.style.background='rgba(255,255,255,.12)'">
            <i data-lucide="chevron-right" class="icono" style="width:24px; height:24px;"></i>
        </button>
    </div>
    <div style="text-align:center; color:rgba(255,255,255,.92); font-size:var(--admin-body-sm); display:flex; flex-direction:column; align-items:center; gap:.25rem;">
        <div id="sol-lb-label" style="font-weight:600; color:#fff;"></div>
        <div id="sol-lb-name" style="font-family:monospace; color:rgba(255,255,255,.7); font-size:var(--admin-body-sm);"></div>
        <div id="sol-lb-counter" style="color:rgba(255,255,255,.55); font-size:var(--admin-kicker); font-variant-numeric:tabular-nums; letter-spacing:.04em; text-transform:uppercase; margin-top:.25rem;"></div>
        <?php if ($puedeEliminar): ?>
        <button type="button" id="sol-lb-delete"
                style="margin-top:.6rem; display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1rem; background:rgba(220,38,38,.92); color:#fff; border:0; border-radius:var(--admin-r-sm); cursor:pointer; font-size:var(--admin-body-sm); font-weight:600; transition:background .15s, transform .12s; box-shadow:0 2px 8px rgba(0,0,0,.25);"
                onmouseover="this.style.background='rgba(220,38,38,1)'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.background='rgba(220,38,38,.92)'; this.style.transform='';">
            <i data-lucide="trash-2" class="icono" style="width:16px; height:16px;"></i>
            <span>Eliminar imagen</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const imgs = <?php echo json_encode(array_map(fn($i) => [
        'id'     => $i['id'],
        'src'    => $i['src'],
        'nombre' => $i['nombre'],
        'label'  => $i['label'],
    ], $lbImgs), JSON_UNESCAPED_UNICODE); ?>;
    if (!imgs.length) return;

    const puedeEliminar = <?php echo $puedeEliminar ? 'true' : 'false'; ?>;

    const lb        = document.getElementById('sol-lb');
    const img       = document.getElementById('sol-lb-img');
    const label     = document.getElementById('sol-lb-label');
    const name      = document.getElementById('sol-lb-name');
    const counter   = document.getElementById('sol-lb-counter');
    const closeBtn  = document.getElementById('sol-lb-close');
    const prevBtn   = document.getElementById('sol-lb-prev');
    const nextBtn   = document.getElementById('sol-lb-next');
    const delBtn    = document.getElementById('sol-lb-delete');

    let idx = 0;

    function render() {
        const item = imgs[idx];
        img.src = item.src;
        img.alt = item.label;
        label.textContent = item.label;
        name.textContent = item.nombre;
        counter.textContent = (idx + 1) + ' / ' + imgs.length;
        prevBtn.style.display = imgs.length > 1 ? '' : 'none';
        nextBtn.style.display = imgs.length > 1 ? '' : 'none';
    }
    function open(i) {
        idx = i;
        render();
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function close() {
        lb.style.display = 'none';
        document.body.style.overflow = '';
    }
    function prev() { idx = (idx - 1 + imgs.length) % imgs.length; render(); }
    function next() { idx = (idx + 1) % imgs.length; render(); }

    function eliminarActual() {
        if (!puedeEliminar) return;
        const item = imgs[idx];
        if (!item) return;
        // Reusar la función global — ya hace confirm + fetch + reload
        eliminarImgSolicitud(item.id, delBtn);
    }

    document.querySelectorAll('.sol-lb-thumb').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            open(parseInt(btn.dataset.lbIdx, 10) || 0);
        });
    });
    closeBtn.addEventListener('click', close);
    prevBtn.addEventListener('click', prev);
    nextBtn.addEventListener('click', next);
    if (delBtn) delBtn.addEventListener('click', eliminarActual);
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    document.addEventListener('keydown', e => {
        if (lb.style.display !== 'flex') return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') prev();
        if (e.key === 'ArrowRight') next();
    });
})();
</script>
<?php endif; ?>

<script>
// Eliminar imagen individual de la solicitud (solo permitido si está pendiente)
function eliminarImgSolicitud(imagenId, btn) {
    confirmar({
        titulo: 'Eliminar imagen',
        mensaje: 'Se borra el archivo y la fila en BD (irreversible). El resto de la solicitud queda intacto.<br><br>¿Eliminar esta imagen?',
        textoOK: 'Eliminar',
        peligroso: true,
        onOK: function () { proceder(); }
    });

    function proceder() {
    btn.disabled = true;
    btn.style.opacity = '.5';

    const body = new URLSearchParams({
        imagen_id: imagenId,
        solicitud_id: <?php echo (int)$solicitud['id']; ?>
    });

    fetch('<?php echo BASE_URL; ?>/admin/solicitud-imagen-eliminar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Recargar la página para actualizar el array del lightbox correctamente
            location.reload();
        } else {
            btn.disabled = false;
            btn.style.opacity = '';
            toast.error(data.mensaje || data.error || 'No se pudo eliminar');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.style.opacity = '';
        toast.error('Error de conexión');
    });
    } // fin proceder()
}

function accionSolicitud(id, accion) {
    const body = new URLSearchParams({ id: id, accion: accion });

    function enviar() {
        fetch('<?php echo BASE_URL; ?>/admin/solicitud-accion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (accion === 'aprobar' && data.slug) {
                    toast.ok('Solicitud aprobada. Ficha creada: ' + data.slug);
                    setTimeout(function () { location.href = 'solicitudes.php?estado=aprobada'; }, 1100);
                } else if (accion === 'eliminar') {
                    location.href = 'solicitudes.php?estado=rechazada';
                } else if (accion === 'reevaluar') {
                    location.href = 'solicitud-ver.php?id=' + id;
                } else {
                    location.href = 'solicitudes.php';
                }
            } else {
                toast.error(data.mensaje || 'Error al procesar');
            }
        })
        .catch(() => toast.error('Error de conexión'));
    }

    if (accion === 'rechazar') {
        // NOTA: prompt() nativo se mantiene por ahora — input modal es mejora futura.
        const motivo = prompt('Motivo del rechazo (opcional):');
        if (motivo === null) return;
        if (motivo) body.append('motivo', motivo);
        enviar();

    } else if (accion === 'aprobar') {
        confirmar({
            titulo: 'Aprobar solicitud',
            mensaje: '¿Aprobar esta solicitud y crear la ficha en el directorio?',
            textoOK: 'Aprobar',
            onOK: enviar
        });

    } else if (accion === 'reevaluar') {
        confirmar({
            titulo: 'Reevaluar solicitud',
            mensaje: 'Vuelve a la cola de pendientes, se borra el motivo de rechazo anterior y podés volver a aprobarla o rechazarla.',
            textoOK: 'Reevaluar',
            onOK: enviar
        });

    } else if (accion === 'eliminar') {
        confirmar({
            titulo: 'Eliminar solicitud definitivamente',
            mensaje: 'Se borra la fila completa (todos los datos) y todas las imágenes adjuntas (archivos + BD). No afecta a ninguna ficha pública.<br><br>⚠ Acción irreversible.',
            textoOK: 'Eliminar',
            peligroso: true,
            onOK: function () { body.append('confirmar', '1'); enviar(); }
        });
    }
}
</script>

<?php include 'footer.php'; ?>
