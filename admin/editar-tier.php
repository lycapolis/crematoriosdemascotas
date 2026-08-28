<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('tiers');

$pdo = obtenerConexion();

$tierId = trim($_GET['id'] ?? '');
$esNuevo = ($tierId === '');

$tier = [
    'id'                         => '',
    'nombre'                     => '',
    'descripcion'                => '',
    'precio_mensual'             => '',
    'logo_mostrar'               => 1,
    'logo_fuentes'               => ['local', 'url'],
    'portada_mostrar'            => 1,
    'portada_fuentes'            => ['local', 'url'],
    'galeria_principal_mostrar'  => 0,
    'galeria_principal_fuentes'  => [],
    'galeria_categorias_mostrar' => 0,
    'galeria_categorias_fuentes' => [],
    'contacto_reglas'            => [],
    'activo'                     => 1,
];

if (!$esNuevo) {
    $row = $pdo->prepare("SELECT * FROM tiers WHERE id = :id");
    $row->execute([':id' => $tierId]);
    $found = $row->fetch(PDO::FETCH_ASSOC);
    if (!$found) {
        header('Location: tiers.php?error=' . urlencode('Plan no encontrado'));
        exit;
    }
    $tier = array_merge($tier, $found);
    $tier['logo_fuentes']               = json_decode($tier['logo_fuentes'], true) ?? [];
    $tier['portada_fuentes']            = json_decode($tier['portada_fuentes'], true) ?? [];
    $tier['galeria_principal_fuentes']  = json_decode($tier['galeria_principal_fuentes'], true) ?? [];
    $tier['galeria_categorias_fuentes'] = json_decode($tier['galeria_categorias_fuentes'], true) ?? [];
    $tier['contacto_reglas']            = json_decode($tier['contacto_reglas'] ?? '', true) ?? [];
}

$error = isset($_GET['form_error']) ? htmlspecialchars(urldecode($_GET['form_error'])) : '';

$titulo_pagina = ($esNuevo ? 'Nuevo Plan' : 'Editar Plan ' . htmlspecialchars($tierId)) . ' — Admin';
include 'header.php';
?>

<div class="admin-page admin-page--narrow">

    <!-- Volver -->
    <a href="tiers.php" class="admin-link" style="display:inline-flex; align-items:center; gap:.35rem; margin-bottom: var(--espacio-tres); font-size: var(--admin-body-sm);">
        <i data-lucide="arrow-left" class="icono" style="width:14px; height:14px;"></i>
        Volver a planes
    </a>

    <!-- ═══ Page header ═══ -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">
            <?php echo $esNuevo ? 'Nuevo plan' : 'Editar plan ' . htmlspecialchars($tierId); ?>
        </h1>
    </header>

    <!-- Feedback ?form_error → toast (puente en footer). Banner inline removido. -->

    <form id="form-tier" method="POST" action="guardar-tier.php">
        <input type="hidden" name="id_original" value="<?php echo htmlspecialchars($tierId); ?>">

        <!-- ═══ SECCIÓN: Datos generales ═══ -->
        <section class="admin-section">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="tag" class="icono" style="width:18px; height:18px;"></i>
                    Datos generales
                </h2>
            </div>

            <div class="admin-section__body" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                <div style="display:grid; grid-template-columns: 200px 1fr; gap: var(--espacio-cuatro); align-items: start;">
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label">
                            ID del plan
                            <span class="field__req">requerido</span>
                            <?php if (!$esNuevo): ?>
                            <span title="Único, no modificable tras crear" style="display: inline-flex; align-items: center; cursor: help; color: var(--admin-tinta-tenue); margin-left: -.15rem;">
                                <i data-lucide="info" class="icono" style="width: 13px; height: 13px;"></i>
                            </span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="id" required maxlength="10"
                               value="<?php echo htmlspecialchars($tier['id']); ?>"
                               <?php echo !$esNuevo ? 'readonly style="opacity:.6; background: var(--admin-papel-alt); cursor: not-allowed;" title="No se puede modificar el ID tras crear el plan"' : ''; ?>
                               placeholder="ej: 04"
                               class="field__input">
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label">
                            Nombre
                            <span class="field__req">requerido</span>
                        </label>
                        <input type="text" name="nombre" required maxlength="100"
                               value="<?php echo htmlspecialchars($tier['nombre']); ?>"
                               placeholder="ej: Plan 04 — Enterprise"
                               class="field__input">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 180px; gap: var(--espacio-cuatro);">
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label">Descripción</label>
                        <textarea name="descripcion" rows="2" class="field__textarea"
                                  placeholder="Qué incluye este plan…"><?php echo htmlspecialchars($tier['descripcion'] ?? ''); ?></textarea>
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field__label">Precio / mes</label>
                        <div class="field__group">
                            <input type="number" name="precio_mensual" min="0" step="0.01"
                                   value="<?php echo htmlspecialchars($tier['precio_mensual'] ?? ''); ?>"
                                   placeholder="0.00"
                                   class="field__input">
                            <span class="field__suffix">€</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ═══ SECCIÓN: Reglas de contenido ═══ -->
        <section class="admin-section">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="layers" class="icono" style="width:18px; height:18px;"></i>
                    Reglas de contenido
                </h2>
                <p class="admin-section__hint">
                    Define qué secciones se muestran y desde qué fuentes se sirven las imágenes. El orden de las fuentes define prioridad.
                </p>
            </div>

            <div class="admin-section__body admin-section__body--flat" style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
                <?php
                $secciones = [
                    ['key' => 'logo',               'label' => 'Logo',                  'desc' => 'Imagen de marca en el sidebar'],
                    ['key' => 'portada',            'label' => 'Portada',               'desc' => 'Imagen grande sobre "Sobre el negocio"'],
                    ['key' => 'galeria_principal', 'label' => 'Galería principal',     'desc' => 'Grid de imágenes en orden aleatorio'],
                    ['key' => 'galeria_categorias','label' => 'Galerías por categoría','desc' => 'Secciones instalaciones, recuerdos, equipo…'],
                ];
                foreach ($secciones as $sec):
                    $mostrarKey = $sec['key'] . '_mostrar';
                    $fuentesKey = $sec['key'] . '_fuentes';
                    $isMostrar  = (bool) $tier[$mostrarKey];
                ?>
                <div style="background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); overflow: hidden; box-shadow: var(--admin-sombra-suave);">
                    <!-- Toggle header -->
                    <label style="display:flex; align-items:center; gap: var(--espacio-tres); padding: var(--espacio-tres) var(--espacio-cuatro); cursor: pointer; background: var(--admin-papel-alt); user-select: none;"
                           for="toggle_<?php echo $sec['key']; ?>">
                        <input type="hidden"   name="<?php echo $mostrarKey; ?>" value="0">
                        <input type="checkbox" class="field__switch" name="<?php echo $mostrarKey; ?>" value="1"
                               id="toggle_<?php echo $sec['key']; ?>"
                               <?php echo $isMostrar ? 'checked' : ''; ?>
                               onchange="toggleSeccion('<?php echo $sec['key']; ?>', this.checked)">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: var(--admin-body-sm); font-weight: 600; color: var(--admin-tinta-fuerte);">
                                <?php echo $sec['label']; ?>
                            </div>
                            <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin-top: .15rem;">
                                <?php echo $sec['desc']; ?>
                            </div>
                        </div>
                        <span id="badge_<?php echo $sec['key']; ?>"
                              class="admin-pill <?php echo $isMostrar ? 'admin-pill--exito' : ''; ?>">
                            <?php echo $isMostrar ? 'Incluido' : 'No incluido'; ?>
                        </span>
                    </label>
                    <!-- Fuentes -->
                    <div id="fuentes_<?php echo $sec['key']; ?>"
                         style="padding: var(--espacio-tres) var(--espacio-cuatro); border-top: 1px solid var(--admin-linea); display: <?php echo $isMostrar ? 'flex' : 'none'; ?>; flex-direction: column; gap: var(--espacio-dos);">
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave);">
                            Fuentes permitidas (marca las que aplican — la primera tiene mayor prioridad):
                        </div>
                        <div style="display: flex; gap: var(--espacio-tres); flex-wrap: wrap;">
                            <?php foreach (['local' => 'Local (servidor)', 'url' => 'URL (externas)'] as $fuente => $label):
                                $checked   = in_array($fuente, $tier[$fuentesKey]) ? 'checked' : '';
                                $pillClass = $fuente === 'local' ? 'admin-pill--info' : 'admin-pill--alerta';
                            ?>
                            <label class="field__opcion" style="gap: .5rem;">
                                <input type="checkbox" class="field__check" name="<?php echo $sec['key']; ?>_fuentes[]" value="<?php echo $fuente; ?>" <?php echo $checked; ?>>
                                <span class="admin-pill <?php echo $pillClass; ?>"><?php echo $label; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ═══ SECCIÓN: Reglas de contacto (WhatsApp) ═══ -->
        <section class="admin-section">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="message-circle" class="icono" style="width:18px; height:18px;"></i>
                    Reglas de contacto
                </h2>
                <p class="admin-section__hint">
                    Define si el CTA/burbuja apunta al <em>número del negocio</em> o a <em>soporte B2C</em>.
                    Si dejas ambos en "— por defecto", se usa el fallback: sidebar → negocio; burbuja → soporte en tiers 00–02.
                </p>
            </div>

            <div class="admin-section__body admin-section__body--flat" style="display: flex; flex-direction: column; gap: var(--espacio-tres);">
                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label">CTA sidebar (WhatsApp del negocio)</label>
                    <select name="contacto_sidebar" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="">— por defecto (sidebar: negocio)</option>
                        <option value="soporte" <?php echo ($tier['contacto_reglas']['sidebar'] ?? '') === 'soporte' ? 'selected' : ''; ?>>Soporte B2C</option>
                        <option value="negocio" <?php echo ($tier['contacto_reglas']['sidebar'] ?? '') === 'negocio' ? 'selected' : ''; ?>>Número del negocio</option>
                    </select>
                    <p class="admin-section__hint" style="margin-top:.3rem">Por defecto solo el tier '00' redirige a soporte.</p>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="field__label">Burbuja flotante (WhatsApp)</label>
                    <select name="contacto_burbuja" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="">— por defecto (burbuja: negocio)</option>
                        <option value="soporte" <?php echo ($tier['contacto_reglas']['burbuja'] ?? '') === 'soporte' ? 'selected' : ''; ?>>Soporte B2C</option>
                        <option value="negocio" <?php echo ($tier['contacto_reglas']['burbuja'] ?? '') === 'negocio' ? 'selected' : ''; ?>>Número del negocio</option>
                    </select>
                    <p class="admin-section__hint" style="margin-top:.3rem">Por defecto solo tiers 00–02 redirigen a soporte; el tier '03' o superior va al negocio.</p>
                </div>
            </div>
        </section>

        <!-- ═══ SECCIÓN: Estado ═══ -->
        <section class="admin-section">
            <div class="admin-section__heading">
                <h2 class="admin-section__title">
                    <i data-lucide="power" class="icono" style="width:18px; height:18px;"></i>
                    Estado
                </h2>
            </div>
            <div class="admin-section__body--flat">
                <label style="display:flex; align-items: center; gap: var(--espacio-tres); padding: var(--espacio-tres) var(--espacio-cuatro); background: var(--admin-superficie); border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); box-shadow: var(--admin-sombra-suave); cursor: pointer;">
                    <input type="hidden"   name="activo" value="0">
                    <input type="checkbox" class="field__switch" name="activo" value="1"
                           <?php echo $tier['activo'] ? 'checked' : ''; ?>>
                    <div>
                        <div style="font-size: var(--admin-body-sm); font-weight: 600; color: var(--admin-tinta-fuerte);">Plan activo</div>
                        <div style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); margin-top: .15rem;">
                            Los planes inactivos no aparecen como opción al asignar fichas. Las que ya lo tienen asignado no se ven afectadas.
                        </div>
                    </div>
                </label>
            </div>
        </section>

        <!-- Spacer para barra fija inferior -->
        <div style="height: 80px;"></div>
    </form>
</div>

<!-- ═══ Barra fija de acciones ═══ -->
<div style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; background: var(--admin-superficie); border-top: 1px solid var(--admin-linea); padding: var(--espacio-tres) var(--espacio-cuatro); display: flex; justify-content: center; gap: var(--espacio-dos); box-shadow: 0 -2px 12px rgba(74, 59, 42, 0.08);">
    <a href="tiers.php" class="boton dos">Cancelar</a>
    <button type="submit" form="form-tier" class="boton uno">
        <i data-lucide="check" class="icono" style="width:14px; height:14px;"></i>
        <?php echo $esNuevo ? 'Crear plan' : 'Guardar cambios'; ?>
    </button>
</div>

<script>
function toggleSeccion(key, on) {
    const fuentes = document.getElementById('fuentes_' + key);
    const badge   = document.getElementById('badge_'   + key);
    if (fuentes) fuentes.style.display = on ? 'flex' : 'none';
    if (badge) {
        badge.textContent = on ? 'Incluido' : 'No incluido';
        badge.classList.toggle('admin-pill--exito', on);
    }
}
</script>

<?php include 'footer.php'; ?>
