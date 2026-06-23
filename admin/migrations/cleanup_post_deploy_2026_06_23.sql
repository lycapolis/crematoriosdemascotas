-- ═══════════════════════════════════════════════════════════
-- LIMPIEZA POST-DEPLOY 2026-06-23
-- ═══════════════════════════════════════════════════════════
-- Ejecutar UNA SOLA VEZ en producción después del deploy V2.
-- Limpia datos basura del seed antiguo + vistas legacy no usadas.
--
-- Cómo ejecutar (vía SSH):
--   1. SCP este archivo al server (o copialo a mano)
--   2. En el server:
--      mysql -u u951481392_crematorios_v2 -p u951481392_crematorios_v2 < cleanup_post_deploy_2026_06_23.sql
--      (te va a pedir la password de la BD)
--
-- O via phpMyAdmin: copiar-pegar el contenido en la pestaña SQL y ejecutar.
-- ═══════════════════════════════════════════════════════════

-- ─── 1. Archivar la ficha de test creada en el smoke test ──
-- ID 99 = "Test Deploy 2026-06-23" creado vía form de registro durante QA.
-- La dejamos como `archivada` (no se borra, se conserva para histórico).
UPDATE crematorios
SET estado = 'archivada', updated_at = NOW()
WHERE id = 99 AND nombre LIKE 'Test Deploy%';

-- ─── 2. Limpiar provincia/comunidad basura del seed antiguo ──
-- La "provincia" `new-mexico` y la "comunidad" `otros` quedaron del seed
-- inicial cuando el modelo geográfico todavía estaba en pruebas. No hay
-- fichas activas asociadas (todas las dummy ya se borraron).
-- Verificamos primero que NO haya crematorios apuntando ahí:
SELECT id, nombre, provincia_id FROM crematorios
WHERE provincia_id IN (
    SELECT id FROM provincias WHERE slug = 'new-mexico'
);
-- Si la query de arriba devuelve 0 filas, ejecutar el DELETE:
DELETE FROM provincias WHERE slug = 'new-mexico';
DELETE FROM comunidades_autonomas WHERE slug = 'otros';

-- ─── 3. Eliminar vistas v_* legacy no usadas por el código ──
-- Estas vistas eran helpers de query del esquema viejo. El código actual
-- de la V2 hace los joins directos en funciones.php — no las usa.
-- Verificado con: grep -r "v_crematorios_completo|v_estadisticas_..." → 0 hits
-- El dump las trae con DEFINER=root (no permitido en hosting compartido).
-- Borrarlas elimina el ruido del esquema.
DROP VIEW IF EXISTS v_crematorios_completo;
DROP VIEW IF EXISTS v_estadisticas_comunidad;
DROP VIEW IF EXISTS v_estadisticas_provincia;
DROP VIEW IF EXISTS v_estadisticas_resenas;
DROP VIEW IF EXISTS v_resenas_aprobadas;
DROP VIEW IF EXISTS v_solicitudes_pendientes;

-- ═══════════════════════════════════════════════════════════
-- Verificación final
-- ═══════════════════════════════════════════════════════════
SELECT 'crematorios activos' AS metric, COUNT(*) AS valor FROM crematorios WHERE estado='activa'
UNION ALL SELECT 'provincias',      COUNT(*) FROM provincias
UNION ALL SELECT 'comunidades',     COUNT(*) FROM comunidades_autonomas
UNION ALL SELECT 'vistas restantes', COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'v\_%';
