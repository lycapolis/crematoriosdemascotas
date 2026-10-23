-- Migración: campaña de prospección, tramo 3 (motor de envío de los emails 1-3).
--
-- Ejecutar con utf8mb4 (si no, se rompen los acentos):
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/create_campana_motor.sql
--
-- Contenido:
--   campana_config  → ajustes del motor (clave/valor). Nace PAUSADA: pausada=1.
--   campana_envios  → un registro por email de campaña enviado (paso 1-3). dia_es = día en hora de España,
--                     calculado por el PHP con zona explícita (sirve para el tope diario).
--   campana_clics   → clics en los botones del email (los registra verificar-ficha.php).
--
-- Es re-ejecutable.

CREATE TABLE IF NOT EXISTS campana_config (
    clave      VARCHAR(40)  NOT NULL,
    valor      VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO campana_config (clave, valor) VALUES ('pausada', '1'), ('tope_diario', '10');

CREATE TABLE IF NOT EXISTS campana_envios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id INT UNSIGNED NOT NULL,
    paso          TINYINT UNSIGNED NOT NULL COMMENT '1 invitación, 2 recordatorio, 3 último aviso',
    email         VARCHAR(190) NOT NULL,
    prueba        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = envío de prueba a una ficha demo',
    dia_es        DATE NOT NULL COMMENT 'Día en hora de España (Europe/Madrid)',
    enviado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dia (dia_es, prueba),
    KEY idx_crem (crematorio_id, paso),
    CONSTRAINT fk_cenv_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campana_clics (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id INT UNSIGNED NOT NULL,
    paso          TINYINT UNSIGNED NOT NULL,
    boton         ENUM('llamada','formulario') NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crem (crematorio_id, paso, boton),
    CONSTRAINT fk_cclic_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
