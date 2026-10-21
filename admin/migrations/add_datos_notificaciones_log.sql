-- ═══════════════════════════════════════════════════════════
-- MIGRATION: notificaciones_log.datos (reintentos automáticos por cron)
-- ═══════════════════════════════════════════════════════════
-- Guarda una copia de los datos del evento SOLO mientras la fila está «pendiente» o «fallida»,
-- para que cron/reintentar-notificaciones.php pueda reenviarla. Al quedar «enviada» u «omitida»
-- se vacía (no se conservan datos personales en el registro).
--
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < add_datos_notificaciones_log.sql
-- (en producción: phpMyAdmin → pestaña SQL, con la base seleccionada)
-- ═══════════════════════════════════════════════════════════

ALTER TABLE notificaciones_log
    ADD COLUMN datos MEDIUMTEXT NULL AFTER error;
