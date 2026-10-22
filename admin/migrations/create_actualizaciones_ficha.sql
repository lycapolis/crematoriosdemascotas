-- Migración: campaña de prospección, tramo 2 — el negocio propone cambios a su ficha (revisión humana).
--
-- Ejecutar con utf8mb4 (si no, se rompen los acentos):
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/create_actualizaciones_ficha.sql
--
-- Contenido:
--   actualizaciones_ficha         → un envío del formulario de verificar-ficha.php (quién, cuándo, qué envió completo).
--   actualizacion_ficha_campos    → una fila por cada dato que CAMBIA respecto a la ficha (actual vs. propuesto)
--                                   con la decisión del admin campo a campo.
--   actualizacion_ficha_imagenes  → logo / galería propuestos, con decisión por imagen.
--
-- Nada de lo que llega aquí se publica solo: lo aplica el admin desde admin/actualizaciones-pendientes.php.
-- Es re-ejecutable: no duplica tablas.

CREATE TABLE IF NOT EXISTS actualizaciones_ficha (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id        INT UNSIGNED NOT NULL,
    estado               ENUM('pendiente','revisada') NOT NULL DEFAULT 'pendiente',
    contacto_nombre      VARCHAR(150) NOT NULL,
    contacto_email       VARCHAR(190) NOT NULL,
    contacto_telefono    VARCHAR(50)  NOT NULL,
    comentarios          TEXT NULL COMMENT 'Mensaje privado del negocio al equipo (no se publica)',
    acepta_comunicaciones TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Casilla opcional de ofertas comerciales',
    datos_enviados_json  LONGTEXT NULL COMMENT 'Copia íntegra de lo que envió el negocio (archivo permanente)',
    desde_llamada        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el negocio había pedido la llamada',
    ip_hash              CHAR(64) NULL,
    mensaje_admin        TEXT NULL COMMENT 'Nota del admin al negocio al revisar (sale en el email si algo no se aprobó)',
    revisada_por         INT UNSIGNED NULL COMMENT 'admins.id',
    revisada_en          DATETIME NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado, created_at),
    KEY idx_crem (crematorio_id),
    CONSTRAINT fk_actficha_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actualizacion_ficha_campos (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actualizacion_id INT UNSIGNED NOT NULL,
    campo            VARCHAR(40)  NOT NULL COMMENT 'Clave del catálogo (includes/actualizacion-ficha.php)',
    valor_actual     TEXT NULL,
    valor_propuesto  TEXT NULL,
    estado           ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    decidido_en      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_act_campo (actualizacion_id, campo),
    CONSTRAINT fk_actcampo_act FOREIGN KEY (actualizacion_id) REFERENCES actualizaciones_ficha (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actualizacion_ficha_imagenes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actualizacion_id INT UNSIGNED NOT NULL,
    tipo             ENUM('logo','galeria') NOT NULL,
    nombre_original  VARCHAR(255) NULL,
    nombre_archivo   VARCHAR(255) NOT NULL,
    ruta             VARCHAR(500) NOT NULL,
    tamano           INT UNSIGNED NULL,
    orden            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    estado           ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    decidido_en      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_act (actualizacion_id),
    CONSTRAINT fk_actimg_act FOREIGN KEY (actualizacion_id) REFERENCES actualizaciones_ficha (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
