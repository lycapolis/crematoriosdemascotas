<?php
/**
 * Panel Admin — Insignias (catálogo, reglas y puntos de ranking).
 *
 * Cada insignia se define en BD (tabla `insignias`): nombre, textos, icono Lucide, color propio,
 * regla automática (JSON) y puntos de ranking. El motor vive en includes/insignias.php.
 * Las insignias MANUALES (Verificado por humano, Rápida atención, Promocionado) se otorgan
 * ficha a ficha desde editar-ficha-negocio.php.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/insignias.php';

requerirAutenticacion();
requierePermiso('insignias');

$pdo = obtenerConexion();

function insigniasVolver(string $clave, string $texto): void
{
    header('Location: insignias.php?' . $clave . '=' . urlencode($texto));
    exit;
}

// ─── POST: guardar una insignia / recalcular todo ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
        insigniasVolver('error', 'La sesión expiró. Recarga la página e inténtalo de nuevo.');
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'recalcular') {
        $r = recalcularInsignias($pdo);
        insigniasVolver('ok', "Recalculadas {$r['fichas']} fichas: {$r['asignadas']} insignias nuevas, {$r['quitadas']} retiradas.");
    }

    if ($accion === 'guardar') {
        $id     = (int) ($_POST['id'] ?? 0);
        $actual = $pdo->prepare("SELECT * FROM insignias WHERE id = :id");
        $actual->execute([':id' => $id]);
        $ins = $actual->fetch(PDO::FETCH_ASSOC);
        if (!$ins) insigniasVolver('error', 'La insignia no existe.');

        $nombre   = trim((string) ($_POST['nombre'] ?? ''));
        $tooltip  = trim((string) ($_POST['tooltip'] ?? ''));
        $glosario = trim((string) ($_POST['glosario'] ?? ''));
        $icono    = strtolower(trim((string) ($_POST['icono'] ?? '')));
        $color    = strtoupper(trim((string) ($_POST['color'] ?? '')));
        $puntos   = max(0, min(500, (int) ($_POST['puntos'] ?? 0)));
        $orden    = max(0, min(9999, (int) ($_POST['orden'] ?? 100)));

        if ($nombre === '' || $tooltip === '' || $glosario === '') {
            insigniasVolver('error', "«{$ins['nombre']}»: nombre, texto del hover y texto del glosario son obligatorios.");
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $icono)) {
            insigniasVolver('error', "«{$ins['nombre']}»: el icono debe ser un nombre de Lucide (ej. badge-check).");
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            insigniasVolver('error', "«{$ins['nombre']}»: el color debe ser #RRGGBB.");
        }

        // Regla y niveles (JSON): solo se editan si la insignia los tiene
        $regla   = $ins['regla'];
        $niveles = $ins['niveles'];
        if ($ins['tipo'] === 'auto') {
            $reglaIn = trim((string) ($_POST['regla'] ?? ''));
            $dec = json_decode($reglaIn, true);
            if (!is_array($dec) || empty($dec['tipo']) || !in_array($dec['tipo'], ['columna','columna_o','completitud','rating','popularidad','resena_reciente'], true)) {
                insigniasVolver('error', "«{$ins['nombre']}»: la regla no es un JSON válido o su «tipo» no existe.");
            }
            $regla = json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($ins['niveles'] !== null && isset($_POST['niveles'])) {
            $dec = json_decode(trim((string) $_POST['niveles']), true);
            if (!is_array($dec)) insigniasVolver('error', "«{$ins['nombre']}»: los niveles no son un JSON válido.");
            $niveles = json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $pdo->prepare(
            "UPDATE insignias SET nombre = :n, tooltip = :t, glosario = :g, icono = :i, color = :c,
                    regla = :r, niveles = :nv, puntos = :p, orden = :o,
                    visible_tarjeta = :vt, visible_ficha = :vf, activa = :a
              WHERE id = :id"
        )->execute([
            ':n' => $nombre, ':t' => $tooltip, ':g' => $glosario, ':i' => $icono, ':c' => $color,
            ':r' => $regla, ':nv' => $niveles, ':p' => $puntos, ':o' => $orden,
            ':vt' => isset($_POST['visible_tarjeta']) ? 1 : 0,
            ':vf' => isset($_POST['visible_ficha'])   ? 1 : 0,
            ':a'  => isset($_POST['activa'])          ? 1 : 0,
            ':id' => $id,
        ]);

        insigniasVolver('ok', "«{$nombre}» guardada. Pulsa «Recalcular todo» para aplicar cambios de regla o de puntos al ranking.");
    }
    insigniasVolver('error', 'Acción desconocida.');
}

// ─── Datos ──────────────────────────────────────────────────────────────────
$catalogo = insigniasCatalogo($pdo, false);
$totales  = $pdo->query("SELECT insignia_id, COUNT(*) FROM insignias_ficha GROUP BY insignia_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalFichas = (int) $pdo->query("SELECT COUNT(*) FROM crematorios")->fetchColumn();
$csrf = generarTokenCSRF();

$titulo_pagina = 'Insignias — Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap:var(--espacio-tres); flex-wrap:wrap;">
            <h1 class="admin-page-title">Insignias</h1>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion" value="recalcular">
                <button type="submit" class="boton uno pequeno"
                        onclick="return confirm('¿Recalcular las insignias automáticas y el puntaje de las <?php echo $totalFichas; ?> fichas?');">
                    <i data-lucide="refresh-cw" class="icono" style="width:14px;height:14px;"></i>
                    Recalcular todo
                </button>
            </form>
            <a href="insignias-laboratorio.php" class="boton dos pequeno">
                <i data-lucide="flask-conical" class="icono" style="width:14px;height:14px;"></i>
                Laboratorio
            </a>
            <a href="<?php echo BASE_URL; ?>/insignias.php" target="_blank" class="boton dos pequeno">
                <i data-lucide="external-link" class="icono" style="width:14px;height:14px;"></i>
                Ver glosario público
            </a>
        </div>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo count($catalogo); ?></span> insignias
            <span class="admin-dash"></span>
            Los iconos y textos se ven en tarjetas y fichas; los puntos alimentan el ranking del directorio
        </p>
    </header>

    <div class="admin-banner admin-banner--info" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="info" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            <strong>Puntaje de una ficha</strong> = suma de los puntos de sus insignias (×nivel en las que tienen niveles)
            + calidad (valoración combinada Google + portal, hasta 20 puntos; completitud de la ficha, hasta 20 puntos).
            Las insignias <em>automáticas</em> se recalculan con el botón de arriba y al guardar una ficha;
            las <em>manuales</em> se otorgan en la edición de cada ficha.
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:var(--espacio-tres);">
    <?php foreach ($catalogo as $i):
        $n = (int) ($totales[$i['id']] ?? 0);
        $fid = 'ins-' . (int) $i['id'];
    ?>
    <form method="POST" class="ficha-card" id="<?php echo $fid; ?>" style="<?php echo $i['activa'] ? '' : 'opacity:.6;'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?php echo (int) $i['id']; ?>">

        <h2 class="ficha-card__title" style="display:flex; align-items:center; gap:var(--espacio-tres); flex-wrap:wrap;">
            <span class="insignia insignias--grande js-ins-preview" style="--insignia-color: <?php echo htmlspecialchars($i['color']); ?>; min-width:34px; height:34px;">
                <i data-lucide="<?php echo htmlspecialchars($i['icono']); ?>" class="icono" style="width:19px;height:19px;"></i>
            </span>
            <span><?php echo htmlspecialchars($i['nombre']); ?></span>
            <span class="admin-pill <?php echo $i['tipo'] === 'auto' ? 'admin-pill--info' : 'admin-pill--alerta'; ?>"><?php echo $i['tipo'] === 'auto' ? 'automática' : 'manual'; ?></span>
            <span class="admin-pill"><?php echo $n; ?> de <?php echo $totalFichas; ?> fichas</span>
            <?php if (!$i['activa']): ?><span class="admin-pill">desactivada</span><?php endif; ?>
        </h2>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:var(--espacio-tres); margin-bottom:var(--espacio-tres);">
            <div class="field" style="margin:0;">
                <label class="field__label" for="<?php echo $fid; ?>-nombre">Nombre</label>
                <input type="text" class="field__input" id="<?php echo $fid; ?>-nombre" name="nombre" maxlength="60" required value="<?php echo htmlspecialchars($i['nombre']); ?>">
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="<?php echo $fid; ?>-icono">Icono (Lucide)</label>
                <input type="text" class="field__input js-ins-icono" id="<?php echo $fid; ?>-icono" name="icono" maxlength="40" required value="<?php echo htmlspecialchars($i['icono']); ?>">
                <small class="admin-text-muted">Nombre en <a href="https://lucide.dev/icons/" target="_blank" rel="noopener">lucide.dev/icons</a></small>
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="<?php echo $fid; ?>-color">Color del icono</label>
                <input type="color" class="field__input js-ins-color" id="<?php echo $fid; ?>-color" name="color" value="<?php echo htmlspecialchars($i['color']); ?>" style="height:38px; padding:2px;">
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="<?php echo $fid; ?>-puntos">Puntos de ranking<?php echo $i['niveles'] ? ' (× nivel)' : ''; ?></label>
                <input type="number" class="field__input" id="<?php echo $fid; ?>-puntos" name="puntos" min="0" max="500" value="<?php echo (int) $i['puntos']; ?>">
            </div>
            <div class="field" style="margin:0;">
                <label class="field__label" for="<?php echo $fid; ?>-orden">Prioridad (menor = primero)</label>
                <input type="number" class="field__input" id="<?php echo $fid; ?>-orden" name="orden" min="0" max="9999" value="<?php echo (int) $i['orden']; ?>">
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="<?php echo $fid; ?>-tooltip">Texto al pasar el ratón (frase corta, lenguaje simple)</label>
            <input type="text" class="field__input" id="<?php echo $fid; ?>-tooltip" name="tooltip" maxlength="255" required value="<?php echo htmlspecialchars($i['tooltip']); ?>">
        </div>
        <div class="field">
            <label class="field__label" for="<?php echo $fid; ?>-glosario">Explicación del glosario público (castellano de España, «tú»)</label>
            <textarea class="field__textarea" id="<?php echo $fid; ?>-glosario" name="glosario" rows="3" required><?php echo htmlspecialchars($i['glosario']); ?></textarea>
        </div>

        <?php if ($i['tipo'] === 'auto'): ?>
        <div class="field">
            <label class="field__label" for="<?php echo $fid; ?>-regla">Regla automática (JSON)</label>
            <textarea class="field__textarea" id="<?php echo $fid; ?>-regla" name="regla" rows="2" spellcheck="false" style="font-family:ui-monospace,Consolas,monospace; font-size:.8rem;"><?php echo htmlspecialchars($i['regla'] ?? ''); ?></textarea>
            <small class="admin-text-muted">Tipos: columna · columna_o · completitud · rating · popularidad · resena_reciente. «requiere» exige otras insignias.</small>
        </div>
        <?php endif; ?>
        <?php if ($i['niveles'] !== null): ?>
        <div class="field">
            <label class="field__label" for="<?php echo $fid; ?>-niveles">Niveles (JSON): nombre y texto de cada nivel</label>
            <textarea class="field__textarea" id="<?php echo $fid; ?>-niveles" name="niveles" rows="3" spellcheck="false" style="font-family:ui-monospace,Consolas,monospace; font-size:.8rem;"><?php echo htmlspecialchars($i['niveles']); ?></textarea>
        </div>
        <?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--espacio-tres);">
            <div style="display:flex; gap:var(--espacio-cuatro); flex-wrap:wrap;">
                <label class="field__opcion"><input type="checkbox" class="field__switch" name="activa" value="1" <?php echo $i['activa'] ? 'checked' : ''; ?>> Activa</label>
                <label class="field__opcion"><input type="checkbox" class="field__switch" name="visible_tarjeta" value="1" <?php echo $i['visible_tarjeta'] ? 'checked' : ''; ?>> Visible en tarjetas</label>
                <label class="field__opcion"><input type="checkbox" class="field__switch" name="visible_ficha" value="1" <?php echo $i['visible_ficha'] ? 'checked' : ''; ?>> Visible en la ficha</label>
            </div>
            <button type="submit" class="boton uno pequeno">
                <i data-lucide="save" class="icono" style="width:14px;height:14px;"></i>
                Guardar
            </button>
        </div>
    </form>
    <?php endforeach; ?>
    </div>
</div>

<script>
// Vista previa en vivo del icono y el color de cada insignia
document.querySelectorAll('form[id^="ins-"]').forEach(function (f) {
    var prev  = f.querySelector('.js-ins-preview');
    var color = f.querySelector('.js-ins-color');
    var icono = f.querySelector('.js-ins-icono');
    color.addEventListener('input', function () { prev.style.setProperty('--insignia-color', color.value); });
    icono.addEventListener('change', function () {
        prev.innerHTML = '<i data-lucide="' + icono.value.trim().replace(/[^a-z0-9-]/g, '') + '" class="icono" style="width:19px;height:19px;"></i>';
        if (window.lucide) lucide.createIcons();
    });
});
</script>

<?php include 'footer.php'; ?>
