-- ═══════════════════════════════════════════════════════════
-- MIGRATION: bajas por categoría en notificaciones_supresiones (tarea 03c)
-- ═══════════════════════════════════════════════════════════
-- `suprimido` sigue siendo la baja TOTAL. Las tres columnas nuevas permiten apagar
-- solo un tipo de aviso desde preferencias-notificaciones.php:
--   baja_resenas   → avisos de reseñas y respuestas
--   baja_leads     → avisos de leads
--   baja_informes  → informes de actividad (teaser, futuro KPI mensual)
--
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < add_categorias_a_supresiones.sql
-- (en producción: phpMyAdmin). Solo una vez.
-- ═══════════════════════════════════════════════════════════

ALTER TABLE notificaciones_supresiones
    ADD COLUMN baja_resenas  TINYINT(1) NOT NULL DEFAULT 0 AFTER suprimido,
    ADD COLUMN baja_leads    TINYINT(1) NOT NULL DEFAULT 0 AFTER baja_resenas,
    ADD COLUMN baja_informes TINYINT(1) NOT NULL DEFAULT 0 AFTER baja_leads;
