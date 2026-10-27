-- ═══════════════════════════════════════════════════════════
-- MIGRATION: jerarquía de zonas agnóstica de país (tarea 12, pantalla «Zonas»)
-- ═══════════════════════════════════════════════════════════
-- Jerarquía: país → región (comunidades_autonomas) → provincia → ciudad.
-- Solo se agrega el país a la región (nivel de arriba). Todas las regiones
-- actuales son de España ('ES'); la región «Otros» (la usa «New Mexico») pasa a 'US'.
-- Es idempotente. Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < zonas_pais_1.sql
-- ═══════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS _mig_zonas_pais_1;

DELIMITER //
CREATE PROCEDURE _mig_zonas_pais_1()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunidades_autonomas' AND COLUMN_NAME = 'pais_codigo') THEN
        ALTER TABLE comunidades_autonomas
            ADD COLUMN pais_codigo CHAR(2) NOT NULL DEFAULT 'ES' COMMENT 'ISO 3166-1 alfa-2' AFTER slug,
            ADD KEY idx_pais (pais_codigo);
        UPDATE comunidades_autonomas SET pais_codigo = 'US' WHERE slug = 'otros';
    END IF;
END//
DELIMITER ;

CALL _mig_zonas_pais_1();
DROP PROCEDURE _mig_zonas_pais_1;
