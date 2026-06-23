<?php
/**
 * Sub-partial: excerpt (solo lectura) del alt text de una card.
 * La edición del alt vive ahora en el form único (_img-card-catform.php).
 * Espera $altCfg = [imgId, altDisplay].
 */
$_id   = (int) $altCfg['imgId'];
$_disp = $altCfg['altDisplay'] ?? '';
?>
                <div id="alt-label-<?php echo $_id; ?>" class="img-card__alt"<?php echo !$_disp ? ' style="color:var(--admin-tinta-tenue); font-style:italic;"' : ''; ?>>
                    <?php echo $_disp ? htmlspecialchars($_disp) : 'Sin alt text'; ?>
                </div>
