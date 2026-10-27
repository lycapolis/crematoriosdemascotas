-- ═══════════════════════════════════════════════════════════
-- MIGRATION: gestor de ciudades (tarea 12, tramo 12b)
-- ═══════════════════════════════════════════════════════════
-- Decisiones (2026-10-23):
--   · Se rehace la tabla `ciudades` (la vieja estaba desfasada: sus provincia_id
--     no coincidían con `provincias` y solo la usaba el blog por nombre/slug).
--     Queda respaldada como `backup_ciudades_20261023`.
--   · Solo se cargan las ciudades que existen hoy en el portal (script
--     seed_ciudades.php), no un catálogo de municipios.
--   · `crematorios.ciudad_id` es la fuente de verdad; `crematorios.ciudad`
--     queda como caché del nombre. `ciudad_original` guarda lo que escribió
--     el negocio.
--   · estado_revision: 'aprobada' (normal), 'dudosa' (existe y se muestra, pero
--     hay que revisarla a mano) o 'pendiente' (la propuso un negocio: no genera
--     página pública hasta que el admin la apruebe o la fusione).
--
-- ANTES DE CORRERLA EN PRODUCCIÓN: copia de seguridad de `ciudades` y `crematorios`
-- (phpMyAdmin → Exportar). Es idempotente: se puede correr dos veces sin efectos.
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < ciudades_modelo_1.sql
-- Después, correr el script de siembra:
--   php admin/migrations/seed_ciudades.php
-- ═══════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS _mig_ciudades_modelo_1;

DELIMITER //
CREATE PROCEDURE _mig_ciudades_modelo_1()
BEGIN
    -- 1) Rehacer `ciudades` (solo si todavía tiene el esquema viejo, sin `lat`)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ciudades' AND COLUMN_NAME = 'lat') THEN
        CREATE TABLE IF NOT EXISTS backup_ciudades_20261023 AS SELECT * FROM ciudades;
        DROP TABLE ciudades;
        CREATE TABLE ciudades (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            provincia_id    INT UNSIGNED NOT NULL,
            nombre          VARCHAR(100) NOT NULL,
            slug            VARCHAR(100) NOT NULL COMMENT 'ASCII, único dentro de la provincia',
            slug_alias_json LONGTEXT NULL COMMENT 'Slugs/escrituras antiguas (para 301): ["elche"]',
            lat             DECIMAL(10,7) NULL,
            lng             DECIMAL(10,7) NULL,
            estado_revision ENUM('aprobada','dudosa','pendiente') NOT NULL DEFAULT 'aprobada',
            nota            VARCHAR(255) NULL,
            activo          TINYINT(1) NOT NULL DEFAULT 1,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_provincia_slug (provincia_id, slug),
            KEY idx_estado (estado_revision),
            CONSTRAINT fk_ciudades_provincia FOREIGN KEY (provincia_id) REFERENCES provincias (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- 2) Enlace de la ficha con su ciudad
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crematorios' AND COLUMN_NAME = 'ciudad_id') THEN
        ALTER TABLE crematorios
            ADD COLUMN ciudad_id INT UNSIGNED NULL AFTER ciudad,
            ADD COLUMN ciudad_original VARCHAR(100) NULL AFTER ciudad_id,
            ADD KEY idx_crematorios_ciudad_id (ciudad_id),
            ADD CONSTRAINT fk_crematorios_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudades (id) ON DELETE SET NULL;
    END IF;
END//
DELIMITER ;

CALL _mig_ciudades_modelo_1();
DROP PROCEDURE _mig_ciudades_modelo_1;
