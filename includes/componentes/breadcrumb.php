<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BREADCRUMB — partial reusable
 * ═══════════════════════════════════════════════════════════
 *
 * Uso:
 *   $migas = [
 *       ['Inicio',  BASE_URL . '/'],
 *       ['España',  BASE_URL . '/espana/'],
 *       ['Cataluña', BASE_URL . '/espana/cataluna/'],
 *       ['Barcelona', null],   // null = página actual (sin link)
 *   ];
 *   include ROOT_PATH . '/includes/componentes/breadcrumb.php';
 *
 * Estilo idéntico al breadcrumb que ya está en directorio.php.
 * Imprime nada si $migas está vacío o no es array.
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 */

if (!isset($migas) || !is_array($migas) || empty($migas)) return;
?>
<nav class="breadcrumb" aria-label="Migas de pan">
    <?php foreach ($migas as $i => $miga):
        [$label, $url] = $miga;
        $esUltimo = ($i === count($migas) - 1);
    ?>
        <?php if ($url !== null && !$esUltimo): ?>
            <a href="<?php echo $url; ?>"><?php echo limpiar($label); ?></a>
        <?php else: ?>
            <span class="breadcrumb__actual"><?php echo limpiar($label); ?></span>
        <?php endif; ?>
        <?php if (!$esUltimo): ?>
            <i data-lucide="chevron-right" class="icono breadcrumb__sep"></i>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
