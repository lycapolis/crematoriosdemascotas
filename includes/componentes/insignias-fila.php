<?php
/**
 * Componente compartido: fila de insignias (solo icono Lucide con color propio + tooltip).
 * Lo usan la tarjeta de listados, la ficha pública y el admin → admin = ficha pública.
 *
 * Cada icono enlaza (nueva pestaña) al detalle de esa insignia en el glosario:
 *   /insignias.php#<slug>
 *
 * Variables esperadas:
 *   $insigniasFila   (array)  — salida de insigniasDeFicha() (slug, nombre, tooltip, icono, color)
 *   $insigniasMax    (int)    — opcional; máximo visible, el resto va en «+N» (0 = todas). Def. 0
 *   $insigniasTam    (string) — opcional; '' (pequeña, tarjeta) | 'grande' (ficha)
 *   $insigniasTitulo (bool)   — opcional; añade title="nombre" nativo (tablas con overflow, donde el
 *                               tooltip CSS quedaría recortado: listado del admin)
 *   $insigniasAnidada (bool)  — opcional; true si la fila está DENTRO de un <a> (tarjetas de los
 *                               mapas): no se pueden anidar enlaces, así que el icono es un <span>
 *                               que abre el glosario con un pequeño JS.
 *
 * Sin texto visible: cada icono lleva aria-label y tooltip (hover / foco / toque).
 */
$insigniasFila = $insigniasFila ?? [];
if (!$insigniasFila) return;

$insigniasMax     = (int) ($insigniasMax ?? 0);
$insigniasTam     = $insigniasTam ?? '';
$insigniasAnidada = !empty($insigniasAnidada);
$visibles         = $insigniasMax > 0 ? array_slice($insigniasFila, 0, $insigniasMax) : $insigniasFila;
$ocultas          = $insigniasMax > 0 ? array_slice($insigniasFila, $insigniasMax) : [];
$glosarioBase     = BASE_URL . '/insignias.php';
?>
<div class="insignias<?php echo $insigniasTam ? ' insignias--' . limpiar($insigniasTam) : ''; ?>" role="list">
    <?php foreach ($visibles as $ins):
        $href  = $glosarioBase . '#' . $ins['slug'];
        $attrs = 'class="insignia tiene-tooltip insignia--' . limpiar($ins['slug']) . '" role="listitem"'
               . ' aria-label="' . limpiar($ins['nombre'] . ': ' . $ins['tooltip']) . '"'
               . (!empty($insigniasTitulo) ? ' title="' . limpiar($ins['nombre']) . '"' : '')
               . ' data-tooltip="' . limpiar($ins['tooltip']) . '"'
               . ' style="--insignia-color: ' . limpiar($ins['color']) . ';"';
    ?>
        <?php if ($insigniasAnidada): ?>
        <span <?php echo $attrs; ?> tabindex="0" data-href="<?php echo limpiar($href); ?>">
            <i data-lucide="<?php echo limpiar($ins['icono']); ?>" class="icono"></i>
        </span>
        <?php else: ?>
        <a <?php echo $attrs; ?> href="<?php echo limpiar($href); ?>" target="_blank" rel="noopener">
            <i data-lucide="<?php echo limpiar($ins['icono']); ?>" class="icono"></i>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($ocultas): ?>
    <a class="insignia insignia--mas tiene-tooltip" role="listitem"
       href="<?php echo limpiar($glosarioBase); ?>" target="_blank" rel="noopener"
       aria-label="<?php echo limpiar('Más insignias: ' . implode(', ', array_column($ocultas, 'nombre'))); ?>"
       data-tooltip="<?php echo limpiar('Y también: ' . implode(', ', array_column($ocultas, 'nombre'))); ?>">
        +<?php echo count($ocultas); ?>
    </a>
    <?php endif; ?>
</div>
<?php if ($insigniasAnidada && empty($GLOBALS['__insignias_js'])): $GLOBALS['__insignias_js'] = true; ?>
<script>
// Insignias dentro de una tarjeta-enlace: abren el glosario en otra pestaña sin navegar la tarjeta
document.addEventListener('click', function (e) {
    var el = e.target.closest('.insignia[data-href]');
    if (!el) return;
    e.preventDefault(); e.stopPropagation();
    window.open(el.getAttribute('data-href'), '_blank', 'noopener');
}, true);
</script>
<?php endif; ?>
