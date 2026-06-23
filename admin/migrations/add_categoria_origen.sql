-- Migración: trazabilidad de quién asignó la categoría de una imagen.
-- 'ia'    → la asignó el procesamiento LLM (procesar-llm-ajax / lote)
-- 'admin' → la asignó/editó un admin a mano (imagen-categoria.php)
-- NULL    → todavía sin categoría (pendiente) o legacy sin trazar
-- Usado por el chip "Auto/Manual" en la card de imagen (img-card-admin.php).

ALTER TABLE crematorio_imagenes
    ADD COLUMN IF NOT EXISTS categoria_origen ENUM('ia','admin') DEFAULT NULL AFTER estado_llm;

-- Backfill conservador: imágenes ya procesadas sin trazar → asumir 'ia'
-- (el grueso del catálogo se categorizó con el procesador LLM).
UPDATE crematorio_imagenes
   SET categoria_origen = 'ia'
 WHERE categoria_origen IS NULL
   AND estado_llm = 'procesada'
   AND categoria IS NOT NULL
   AND categoria <> '';
