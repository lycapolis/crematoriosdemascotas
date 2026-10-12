-- Migración: sistema de insignias (badges) + puntaje de ranking.
--
-- Ejecutar con utf8mb4 (si no, se rompen los acentos):
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/create_insignias.sql
--
-- Modelo:
--   insignias         → catálogo editable desde admin/insignias.php (nombre, textos, icono Lucide,
--                       color propio, tipo auto/manual, regla JSON, puntos de ranking).
--   insignias_ficha   → insignias que tiene cada ficha. fuente='auto' las recalcula el motor
--                       (includes/insignias.php); fuente='manual' las otorga el equipo.
--   crematorios.puntaje_ranking → cache del puntaje (se recalcula junto con las insignias).
--
-- Es re-ejecutable: no duplica el catálogo ni repite la migración de datos.

CREATE TABLE IF NOT EXISTS insignias (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug             VARCHAR(40)  NOT NULL,
    nombre           VARCHAR(60)  NOT NULL,
    tooltip          VARCHAR(255) NOT NULL COMMENT 'Texto corto del hover (lenguaje simple)',
    glosario         TEXT         NOT NULL COMMENT 'Explicación larga para el glosario público',
    icono            VARCHAR(40)  NOT NULL COMMENT 'Nombre del icono Lucide',
    color            VARCHAR(7)   NOT NULL COMMENT 'Color propio del icono (#RRGGBB); puede salir de la paleta',
    tipo             ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    regla            TEXT         NULL     COMMENT 'JSON con la regla (solo tipo=auto)',
    niveles          TEXT         NULL     COMMENT 'JSON [{nivel,nombre,tooltip}] para insignias con niveles',
    puntos           INT          NOT NULL DEFAULT 0 COMMENT 'Puntos de ranking (x nivel si tiene niveles)',
    visible_tarjeta  TINYINT(1)   NOT NULL DEFAULT 1,
    visible_ficha    TINYINT(1)   NOT NULL DEFAULT 1,
    activa           TINYINT(1)   NOT NULL DEFAULT 1,
    orden            INT          NOT NULL DEFAULT 100 COMMENT 'Prioridad de aparición (menor = primero)',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS insignias_ficha (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crematorio_id INT UNSIGNED NOT NULL,
    insignia_id   INT UNSIGNED NOT NULL,
    fuente        ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    nivel         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    otorgada_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    otorgada_por  INT UNSIGNED NULL COMMENT 'admins.id si fue manual',
    PRIMARY KEY (id),
    UNIQUE KEY uk_ficha_insignia (crematorio_id, insignia_id),
    KEY idx_insignia (insignia_id),
    CONSTRAINT fk_insf_crem FOREIGN KEY (crematorio_id) REFERENCES crematorios (id) ON DELETE CASCADE,
    CONSTRAINT fk_insf_ins  FOREIGN KEY (insignia_id)   REFERENCES insignias (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crematorios
    ADD COLUMN IF NOT EXISTS puntaje_ranking DECIMAL(8,2) NOT NULL DEFAULT 0
        COMMENT 'Cache del puntaje de ranking (insignias + calidad). Lo recalcula includes/insignias.php',
    ADD COLUMN IF NOT EXISTS verificado_legacy TINYINT(1) NULL
        COMMENT 'Copia del antiguo `verificado` (importado sin llamada humana). Solo respaldo',
    ADD INDEX IF NOT EXISTS idx_puntaje_ranking (puntaje_ranking);

-- ─── Catálogo inicial ────────────────────────────────────────────────────────
-- Reglas (campo `regla`, JSON):
--   {"tipo":"columna","col":"atencion_24h","valor":1}
--   {"tipo":"columna_o","cols":["a","b"]}            → alguna columna = 1
--   {"tipo":"completitud","min":90}                  → % de completitud de la ficha
--   {"tipo":"rating","min_rating":4.5,"min_resenas":10}  → rating combinado Google + portal
--   {"tipo":"popularidad","dias":90,"min_contactos":3,"percentiles":[50,80]}  → por provincia
--   "requiere":["slug",...] en cualquier regla → exige además esas insignias.
INSERT IGNORE INTO insignias (slug, nombre, tooltip, glosario, icono, color, tipo, regla, niveles, puntos, visible_tarjeta, visible_ficha, orden) VALUES
('verificado_humano', 'Verificado por humano',
 'Una persona de nuestro equipo habló por teléfono con este negocio y comprobó que sus datos son correctos.',
 'Una persona de nuestro equipo llamó al negocio, comprobó que atienden el teléfono y validó la información de la ficha (contacto, servicios y dirección). Es la señal de confianza más fuerte: los datos son fiables y el negocio responde.',
 'badge-check', '#16A34A', 'manual', NULL, NULL, 30, 1, 1, 10),
('promocionado', 'Promocionado',
 'Este negocio contrata publicidad para aparecer mejor posicionado en su zona.',
 'El negocio paga por tener más visibilidad en su zona de servicio. Lo indicamos siempre con claridad: es publicidad, no una valoración de calidad.',
 'megaphone', '#DB2777', 'manual', NULL, NULL, 40, 1, 1, 20),
('destacado', 'Destacado',
 'Ficha destacada por mérito: perfil completo, muy bien valorada y verificada por nuestro equipo.',
 'Se concede automáticamente a los negocios que reúnen todo a la vez: ficha completa, valoración alta con suficientes opiniones (Google y reseñas del portal) y verificación por parte de nuestro equipo. No se compra.',
 'bookmark', '#D97706', 'auto',
 '{"tipo":"rating","min_rating":4.5,"min_resenas":10,"completitud_min":90,"requiere":["verificado_humano"]}', NULL, 20, 1, 1, 30),
('popular', 'Popular',
 'Los dueños de mascotas contactan mucho con este negocio.',
 'Mide cuánta gente contacta con el negocio desde nuestra web (llamadas, WhatsApp, mapa y web) en los últimos 90 días, comparado con los demás negocios de su provincia. Hay tres niveles: Popular, Muy popular y Más popular.',
 'flame', '#EA580C', 'auto',
 '{"tipo":"popularidad","dias":90,"min_contactos":3,"percentiles":[50,80]}',
 '[{"nivel":1,"nombre":"Popular","tooltip":"Popular: muchas familias de su provincia contactan con este negocio."},{"nivel":2,"nombre":"Muy popular","tooltip":"Muy popular: está entre los negocios más contactados de su provincia."},{"nivel":3,"nombre":"Más popular","tooltip":"Más popular: es el negocio más contactado de su provincia."}]',
 8, 1, 1, 40),
('rapida_atencion', 'Rápida atención',
 'Atienden y responden con rapidez cuando los contactas.',
 'Nuestro equipo comprobó, al llamar, que este negocio contesta rápido. Es útil cuando necesitas ayuda con urgencia.',
 'zap', '#F59E0B', 'manual', NULL, NULL, 10, 1, 1, 50),
('atencion_24h', 'Atención 24 horas',
 'Atienden las 24 horas del día, todos los días.',
 'El negocio indica que atiende a cualquier hora. Muy útil porque el fallecimiento de una mascota no avisa.',
 'clock', '#2563EB', 'auto', '{"tipo":"columna","col":"atencion_24h","valor":1}', NULL, 15, 1, 1, 60),
('a_domicilio', 'A domicilio',
 'Recogen a tu mascota en casa y/o te entregan las cenizas en tu domicilio.',
 'El negocio ofrece servicio a domicilio: pueden recoger a tu mascota en tu casa, entregarte las cenizas, o las dos cosas. Consulta en la ficha cuál ofrece exactamente.',
 'truck', '#0D9488', 'auto', '{"tipo":"columna_o","cols":["recogida_domicilio","entrega_domicilio"]}', NULL, 10, 1, 1, 70),
('bien_valorado', 'Bien valorado',
 'Las familias que lo han usado lo valoran muy bien.',
 'Valoración media de 4 estrellas o más, con un mínimo de opiniones. Calculamos la media con las reseñas de Google y las reseñas de nuestro portal.',
 'star', '#EAB308', 'auto', '{"tipo":"rating","min_rating":4.0,"min_resenas":5}', NULL, 10, 1, 1, 80),
('perfil_completo', 'Perfil completo',
 'Ficha con la información completa: contacto, horarios, servicios, fotos y descripción.',
 'La ficha tiene casi todos los datos rellenados (contacto, horarios, servicios, zona, fotos y descripción). Cuanta más información, mejor puedes comparar.',
 'list-checks', '#0891B2', 'auto', '{"tipo":"completitud","min":90}', NULL, 15, 1, 1, 90),
('google_verificado', 'Google verificado',
 'Su perfil de Google Business está verificado por el propietario.',
 'El negocio reclamó y verificó su perfil en Google, así que quien lo gestiona es realmente el propietario.',
 'shield-check', '#4285F4', 'auto', '{"tipo":"columna","col":"google_verificado","valor":1}', NULL, 5, 0, 1, 100),
('registrado', 'Registrado',
 'Fue dado de alta por sus propios dueños, que mantienen la información al día.',
 'El propio negocio se registró en nuestro directorio y se encarga de actualizar su información.',
 'user-check', '#7C3AED', 'auto', '{"tipo":"columna","col":"origen","valor":"registro"}', NULL, 10, 0, 1, 110);

-- ─── Migración de datos (una sola vez) ───────────────────────────────────────
-- 1) Los `destacado` manuales eran pauta/criterio editorial → pasan a «Promocionado».
INSERT IGNORE INTO insignias_ficha (crematorio_id, insignia_id, fuente)
SELECT c.id, i.id, 'manual'
  FROM crematorios c
  JOIN insignias i ON i.slug = 'promocionado'
 WHERE c.destacado = 1 AND c.verificado_legacy IS NULL;

-- 2) `verificado` venía del importador (86 de 90 fichas) sin llamada humana. Se respalda y se
--    pone a 0: «Verificado por humano» solo lo concede el equipo desde ahora.
UPDATE crematorios SET verificado_legacy = verificado, verificado = 0 WHERE verificado_legacy IS NULL;
