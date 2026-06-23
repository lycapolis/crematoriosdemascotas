<?php
/**
 * Sub-partial: form único de edición (tipo + categoría + alt text).
 * Oculto por defecto; lo abre abrirEdicion(), lo cierra cancelarEdicion().
 * Postea a imagen-categoria.php (extendido: también guarda alt_text).
 *
 * Espera $catCfg = [imgId, cremId, redir, cat, tipo, catsOpc, conTipo, altRaw].
 */
$_id      = (int) $catCfg['imgId'];
$_crem    = $catCfg['cremId'];
$_redir   = $catCfg['redir'] ?? '';
$_cat     = $catCfg['cat'] ?? '';
$_tipo    = $catCfg['tipo'] ?? 'galeria';
$_opc     = $catCfg['catsOpc'] ?? [];
$_conTipo = !empty($catCfg['conTipo']);
$_altRaw  = $catCfg['altRaw'] ?? '';
?>
                <form id="cat-form-<?php echo $_id; ?>"
                      method="POST" action="imagen-categoria.php"
                      style="display:none; flex-direction:column; gap:.4rem;">
                    <input type="hidden" name="imagen_id"     value="<?php echo $_id; ?>">
                    <input type="hidden" name="crematorio_id" value="<?php echo $_crem; ?>">
                    <?php if ($_redir !== ''): ?><input type="hidden" name="redir" value="<?php echo htmlspecialchars($_redir); ?>"><?php endif; ?>

                    <?php if ($_conTipo): ?>
                    <label class="field__label" style="margin-bottom: -.2rem;">Tipo</label>
                    <select name="tipo" class="field__select field__select--enhanced" data-ts-search="off">
                        <option value="galeria" <?php echo $_tipo === 'galeria' ? 'selected' : ''; ?>>Galería</option>
                        <option value="logo"    <?php echo $_tipo === 'logo'    ? 'selected' : ''; ?>>Logo</option>
                        <option value="portada" <?php echo $_tipo === 'portada' ? 'selected' : ''; ?>>Portada</option>
                    </select>
                    <?php endif; ?>

                    <label class="field__label" style="margin-bottom: -.2rem;">Categoría</label>
                    <select name="categoria" class="field__select field__select--enhanced" data-ts-search="off">
                        <?php foreach ($_opc as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $_cat === $val ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="field__label" style="margin-bottom: -.2rem;">Alt text</label>
                    <textarea name="alt_text" class="field__textarea" rows="3"
                              style="font-size: var(--admin-body-sm); resize:vertical;"><?php echo htmlspecialchars($_altRaw); ?></textarea>

                    <div style="display:flex; gap:.4rem;">
                        <button type="submit" class="boton uno pequeno" style="flex:1;">Guardar</button>
                        <button type="button" onclick="cancelarEdicion(<?php echo $_id; ?>)" class="boton dos pequeno" style="flex:1;">Cancelar</button>
                    </div>
                </form>
