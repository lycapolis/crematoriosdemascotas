-- Entregas de datos de contacto de un lead al negocio (botón «Quiero recibir sus datos»).
-- Una fila por lead: la clave única impide duplicar la entrega si el negocio repite el clic.
-- Sirve para contar entregas por ficha (cupo de la tarea 03b). Sin FK a propósito:
-- el histórico se conserva aunque se borre la ficha o el lead.
-- Ejecutar con utf8mb4:  mysql --default-character-set=utf8mb4 ...

CREATE TABLE IF NOT EXISTS lead_entregas (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id       INT UNSIGNED NOT NULL,
    crematorio_id INT UNSIGNED NOT NULL COMMENT 'ficha a la que se entregó (para contar por ficha)',
    ip_hash       CHAR(64) NULL COMMENT 'sha256 de la IP del primer clic (no se guarda la IP)',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lead (lead_id),
    KEY idx_ficha_fecha (crematorio_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
