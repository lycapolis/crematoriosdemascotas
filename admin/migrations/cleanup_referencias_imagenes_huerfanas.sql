-- ═══════════════════════════════════════════════════════════
-- CLEANUP — Referencias huérfanas de portada/logo principal
-- ═══════════════════════════════════════════════════════════
-- Antes del fix del bug (commit que sigue al deploy V2), eliminar una
-- imagen NO limpiaba `crematorios.portada_principal_id` ni
-- `crematorios.logo_principal_id`. Si la imagen marcada como portada/logo
-- principal fue borrada en algún momento, estas columnas quedaron
-- apuntando a un ID que ya no existe → portada/logo activo "fantasma" y
-- la lógica de auto-asignación no entra porque la columna no es NULL.
--
-- Esta migración detecta esos casos y limpia la referencia a NULL.
-- Después, la próxima vez que se cargue la ficha, la auto-asignación
-- elige la siguiente imagen disponible.
--
-- Cómo ejecutar (vía SSH):
--   mysql -u u951481392_crematorios_v2 -p u951481392_crematorios_v2 < admin/migrations/cleanup_referencias_imagenes_huerfanas.sql
--
-- O vía phpMyAdmin: copiar-pegar en la pestaña SQL.
-- Idempotente: re-correrla no causa daño.
-- ═══════════════════════════════════════════════════════════

-- ─── DIAGNÓSTICO: cuántos crematorios tienen referencias huérfanas ──
SELECT 'Antes del cleanup:' AS info;

SELECT COUNT(*) AS portadas_huerfanas
FROM crematorios c
WHERE c.portada_principal_id IS NOT NULL
  AND c.portada_principal_id NOT IN (SELECT id FROM crematorio_imagenes);

SELECT COUNT(*) AS logos_huerfanos
FROM crematorios c
WHERE c.logo_principal_id IS NOT NULL
  AND c.logo_principal_id NOT IN (SELECT id FROM crematorio_imagenes);

-- ─── FIX: setear a NULL las referencias rotas ──────────────────────
UPDATE crematorios
SET portada_principal_id = NULL
WHERE portada_principal_id IS NOT NULL
  AND portada_principal_id NOT IN (SELECT id FROM crematorio_imagenes);

UPDATE crematorios
SET logo_principal_id = NULL
WHERE logo_principal_id IS NOT NULL
  AND logo_principal_id NOT IN (SELECT id FROM crematorio_imagenes);

-- ─── VERIFICACIÓN: ya debería decir 0 huérfanos ────────────────────
SELECT 'Después del cleanup:' AS info;

SELECT COUNT(*) AS portadas_huerfanas
FROM crematorios c
WHERE c.portada_principal_id IS NOT NULL
  AND c.portada_principal_id NOT IN (SELECT id FROM crematorio_imagenes);

SELECT COUNT(*) AS logos_huerfanos
FROM crematorios c
WHERE c.logo_principal_id IS NOT NULL
  AND c.logo_principal_id NOT IN (SELECT id FROM crematorio_imagenes);
