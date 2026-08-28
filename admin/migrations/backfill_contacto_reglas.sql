-- Backfill de contacto_reglas espejando el fallback vigente en resolverWaDestino()
-- (includes/funciones.php). Objetivo: que las reglas queden EXPLÍCITAS en la BD
-- para que editar-tier.php pueda modificarlas, sin cambiar el comportamiento actual.
--
-- Fallback vigente que se espeja:
--   sidebar → soporte solo si tier '00'; resto negocio
--   burbuja → soporte si tier ∈ ['00','01','02']; resto negocio
--
-- NOTA: NO usar el UPDATE original de add_contacto_reglas.sql (negocio/negocio
-- para todos): eso cambiaría el comportamiento de la burbuja en tiers 01–02.

UPDATE tiers
SET contacto_reglas = JSON_OBJECT(
    'sidebar', CASE WHEN id = '00' THEN 'soporte' ELSE 'negocio' END,
    'burbuja', CASE WHEN id IN ('00', '01', '02') THEN 'soporte' ELSE 'negocio' END
)
WHERE contacto_reglas IS NULL;
