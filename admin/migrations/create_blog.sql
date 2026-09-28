-- ═══════════════════════════════════════════════════════════
-- BLOG — tablas del sistema de artículos (2026-09-28)
-- ═══════════════════════════════════════════════════════════
-- Naming agnóstico de rubro: articulos / negocio (ficha), no "crematorio".
-- El contenido del artículo se guarda como JSON de Editor.js (bloques) y se
-- renderiza a HTML en includes/blog.php al servir la página.
--
-- Ejecutar una sola vez (idempotente: CREATE TABLE IF NOT EXISTS).
-- Después, opcional: admin/migrations/seed_blog_demo.php para cargar
-- categorías, autor, módulos y 4 artículos de ejemplo.
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─── Autores (E-E-A-T) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS blog_autores (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(150) NOT NULL,
    slug          VARCHAR(160) NOT NULL,
    cargo         VARCHAR(150) NULL,
    bio           TEXT NULL,
    credenciales  VARCHAR(500) NULL,
    foto          VARCHAR(500) NULL,
    web           VARCHAR(500) NULL,
    redes_json    TEXT NULL,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_autores_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Taxonomías (categorías, etiquetas y libres tipo WordPress) ──
-- sistema=1 → no se puede borrar (categoria / etiqueta).
-- jerarquica=1 → los términos admiten padre (categorías).
-- publica=1 → tiene archivo público /blog/{taxonomia}/{termino}.
CREATE TABLE IF NOT EXISTS blog_taxonomias (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(100) NOT NULL,
    nombre_singular VARCHAR(100) NULL,
    slug         VARCHAR(100) NOT NULL,
    descripcion  VARCHAR(500) NULL,
    jerarquica   TINYINT(1) NOT NULL DEFAULT 0,
    publica      TINYINT(1) NOT NULL DEFAULT 1,
    sistema      TINYINT(1) NOT NULL DEFAULT 0,
    orden        INT NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_taxonomias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_terminos (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    taxonomia_id     INT UNSIGNED NOT NULL,
    parent_id        INT UNSIGNED NULL,
    nombre           VARCHAR(150) NOT NULL,
    slug             VARCHAR(160) NOT NULL,
    descripcion      TEXT NULL,
    meta_title       VARCHAR(200) NULL,
    meta_description VARCHAR(300) NULL,
    orden            INT NOT NULL DEFAULT 0,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_terminos_tax_slug (taxonomia_id, slug),
    KEY idx_blog_terminos_parent (parent_id),
    CONSTRAINT fk_blog_terminos_tax FOREIGN KEY (taxonomia_id) REFERENCES blog_taxonomias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Artículos ─────────────────────────────────────────────
-- estado: borrador | programado | publicado. Un "programado" sale solo
-- cuando publicado_at <= ahora (la comparación se hace con la hora de PHP).
CREATE TABLE IF NOT EXISTS blog_articulos (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo                 VARCHAR(255) NOT NULL,
    slug                   VARCHAR(200) NOT NULL,
    extracto               VARCHAR(600) NULL,
    contenido_json         LONGTEXT NULL,
    portada_ruta           VARCHAR(500) NULL,
    portada_alt            VARCHAR(255) NULL,
    autor_id               INT UNSIGNED NULL,
    revisor_id             INT UNSIGNED NULL,
    categoria_principal_id INT UNSIGNED NULL,
    estado                 ENUM('borrador','programado','publicado') NOT NULL DEFAULT 'borrador',
    publicado_at           DATETIME NULL,
    actualizado_contenido_at DATETIME NULL,
    destacado              TINYINT(1) NOT NULL DEFAULT 0,
    mostrar_indice         TINYINT(1) NOT NULL DEFAULT 1,
    meta_title             VARCHAR(200) NULL,
    meta_description       VARCHAR(300) NULL,
    keyword_principal      VARCHAR(150) NULL,
    canonical_url          VARCHAR(500) NULL,
    noindex                TINYINT(1) NOT NULL DEFAULT 0,
    palabras               INT UNSIGNED NOT NULL DEFAULT 0,
    lectura_min            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_by             INT UNSIGNED NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_articulos_slug (slug),
    KEY idx_blog_articulos_pub (estado, publicado_at),
    KEY idx_blog_articulos_cat (categoria_principal_id),
    CONSTRAINT fk_blog_articulos_autor   FOREIGN KEY (autor_id)   REFERENCES blog_autores (id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_articulos_revisor FOREIGN KEY (revisor_id) REFERENCES blog_autores (id) ON DELETE SET NULL,
    CONSTRAINT fk_blog_articulos_catp    FOREIGN KEY (categoria_principal_id) REFERENCES blog_terminos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_articulo_termino (
    articulo_id INT UNSIGNED NOT NULL,
    termino_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (articulo_id, termino_id),
    KEY idx_blog_at_termino (termino_id),
    CONSTRAINT fk_blog_at_articulo FOREIGN KEY (articulo_id) REFERENCES blog_articulos (id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_at_termino  FOREIGN KEY (termino_id)  REFERENCES blog_terminos (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Contexto: dónde se muestra el artículo como "relacionado" ──
-- tipo=ficha      → valor = crematorios.id
-- tipo=ciudad     → valor = ciudades.id
-- tipo=provincia  → valor = provincias.id
-- tipo=comunidad  → valor = comunidades_autonomas.id
-- tipo=servicio   → valor = nombre de columna de servicio (ej. cremacion_individual)
CREATE TABLE IF NOT EXISTS blog_articulo_contexto (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    articulo_id INT UNSIGNED NOT NULL,
    tipo        ENUM('ficha','ciudad','provincia','comunidad','servicio') NOT NULL,
    valor       VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_ctx (articulo_id, tipo, valor),
    KEY idx_blog_ctx_tipo_valor (tipo, valor),
    CONSTRAINT fk_blog_ctx_articulo FOREIGN KEY (articulo_id) REFERENCES blog_articulos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Biblioteca de módulos (bloques especiales reutilizables) ──
-- tipo: ficha_destacada | cta | banner | lead. config_json según tipo.
CREATE TABLE IF NOT EXISTS blog_modulos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(150) NOT NULL,
    tipo        ENUM('ficha_destacada','cta','banner','lead') NOT NULL,
    config_json TEXT NULL,
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserción automática: "poner el módulo X después del párrafo N en los
-- artículos de (todos | un término concreto)". Si el artículo ya contiene
-- ese módulo insertado a mano, la regla no lo duplica.
CREATE TABLE IF NOT EXISTS blog_modulo_reglas (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    modulo_id    INT UNSIGNED NOT NULL,
    parrafo      SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    ambito       ENUM('todos','termino') NOT NULL DEFAULT 'todos',
    termino_id   INT UNSIGNED NULL,
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_blog_reglas_modulo  FOREIGN KEY (modulo_id)  REFERENCES blog_modulos (id)  ON DELETE CASCADE,
    CONSTRAINT fk_blog_reglas_termino FOREIGN KEY (termino_id) REFERENCES blog_terminos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Imágenes subidas desde el editor ──────────────────────
CREATE TABLE IF NOT EXISTS blog_imagenes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    articulo_id INT UNSIGNED NULL,
    ruta        VARCHAR(500) NOT NULL,
    ruta_media  VARCHAR(500) NULL,
    ancho       SMALLINT UNSIGNED NULL,
    alto        SMALLINT UNSIGNED NULL,
    origen      ENUM('subida','url','pegada') NOT NULL DEFAULT 'subida',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_blog_imagenes_articulo (articulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Configuración general del blog (clave/valor) ──────────
CREATE TABLE IF NOT EXISTS blog_config (
    clave VARCHAR(80) NOT NULL,
    valor TEXT NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- noindex_global=1 → todo /blog sale con noindex (mientras los artículos
-- sean de ejemplo). Se cambia desde admin/blog-articulos.php.
INSERT IGNORE INTO blog_config (clave, valor) VALUES ('noindex_global', '1');

-- Taxonomías de sistema
INSERT IGNORE INTO blog_taxonomias (nombre, nombre_singular, slug, descripcion, jerarquica, publica, sistema, orden) VALUES
('Categorías', 'Categoría', 'categoria', 'Secciones principales del blog. Admiten subcategorías.', 1, 1, 1, 1),
('Etiquetas',  'Etiqueta',  'etiqueta',  'Palabras clave libres para agrupar artículos.',          0, 1, 1, 2);
