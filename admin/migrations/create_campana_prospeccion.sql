-- Migración: campaña de prospección a los negocios (tramo 1: base + camino de la llamada).
--
-- Ejecutar con utf8mb4 (si no, se rompen los acentos):
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/create_campana_prospeccion.sql
--
-- Contenido:
--   campana_fichas       → estado de la campaña de cada ficha (una fila por ficha; la crea la página
--                          firmada al primer uso o el motor de envío del tramo 3).
--   solicitudes_llamada  → cada vez que un negocio pide que le llamemos desde verificar-ficha.php.
--   insignias            → alta de «Datos actualizados por el negocio» (manual, 10 pts, solo en la ficha).
--
-- Es re-ejecutable: no duplica tablas ni la insignia.

CREATE TABLE IF NOT EXISTS campana_fichas (
    crematorio_id  INT UNSIGNED NOT NULL,
    estado         ENUM('sin_enviar','enviado_1','enviado_2','enviado_3','respondio','llamada','formulario','baja')
                   NOT NULL DEFAULT 'sin_enviar',
    ultimo_envio   DATETIME NULL COMMENT 'Último email de campaña enviado (lo rellena el motor del tramo 3)',
    respondio_en   DATETIME NULL COMMENT 'Primera vez que el negocio usó el enlace firmado',
    baja_en        DATETIME NULL COMMENT 'Pulsó «No me interesa»',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (crematorio_id),
    KEY idx_estado (estado),
    CONSTRAINT fk_campana_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitudes_llamada (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id       INT UNSIGNED NOT NULL,
    telefono_original   VARCHAR(50)  NULL COMMENT 'Teléfono que mostraba la ficha al pedir la llamada (vacío si no había)',
    telefono_confirmado VARCHAR(50)  NOT NULL COMMENT 'Teléfono al que llamar; si cambió, ya es el público de la ficha',
    telefono_cambiado   TINYINT(1)   NOT NULL DEFAULT 0,
    estado              ENUM('pendiente','verificado','no_contesta','reagendar') NOT NULL DEFAULT 'pendiente',
    intentos            TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Llamadas ya intentadas',
    notas_admin         TEXT NULL,
    atendida_por        INT UNSIGNED NULL COMMENT 'admins.id de quien registró el último resultado',
    resultado_en        DATETIME NULL,
    ip_hash             CHAR(64) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado, created_at),
    KEY idx_crem (crematorio_id),
    CONSTRAINT fk_llamada_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insignia acumulativa: al otorgar «Verificado por humano» el sistema otorga también esta (10 + 30 = 40 pts).
-- visible_tarjeta = 0: en el listado solo se ve la más fuerte; en la ficha se ven las dos.
INSERT IGNORE INTO insignias (slug, nombre, tooltip, glosario, icono, color, tipo, regla, niveles, puntos, visible_tarjeta, visible_ficha, orden) VALUES
('datos_actualizados', 'Datos actualizados por el negocio',
 'El propio negocio ha revisado y confirmado los datos de esta ficha.',
 'El negocio ha confirmado o actualizado él mismo los datos de su ficha (por ejemplo, su teléfono de atención al público) y nuestro equipo lo ha revisado. Significa que la información la mantiene quien mejor la conoce.',
 'file-check', '#0EA5E9', 'manual', NULL, NULL, 10, 0, 1, 15);
