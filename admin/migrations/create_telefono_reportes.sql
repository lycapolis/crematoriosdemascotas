-- Migración: avisos públicos «¿No funciona este teléfono? Avísanos» (ficha 11).
--
-- Ejecutar con utf8mb4 (si no, se rompen los acentos):
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/create_telefono_reportes.sql
--
-- telefono_reportes → un aviso anónimo de un visitante sobre el teléfono de una ficha.
-- Re-ejecutable: no duplica la tabla.

CREATE TABLE IF NOT EXISTS telefono_reportes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id INT UNSIGNED NOT NULL,
    telefono      VARCHAR(50)  NOT NULL COMMENT 'Número que mostraba la ficha al avisar',
    motivo        ENUM('no_contesta','numero_equivocado','otro') NULL COMMENT 'Opcional: qué pasó',
    estado        ENUM('pendiente','revisado') NOT NULL DEFAULT 'pendiente',
    notas_admin   TEXT NULL,
    atendida_por  INT UNSIGNED NULL COMMENT 'admins.id de quien lo marcó como revisado',
    resuelto_en   DATETIME NULL,
    ip_hash       CHAR(64) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado, created_at),
    KEY idx_ficha_tel (crematorio_id, telefono, estado),
    KEY idx_ip (ip_hash, created_at),
    CONSTRAINT fk_telrep_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
