-- ═══════════════════════════════════════════════════════════
-- CREMATORIOS DE MASCOTAS - SISTEMA DE RESEÑAS
-- Archivo: 04_resenas.sql
-- ═══════════════════════════════════════════════════════════

USE crematorios_mascotas;

-- ───────────────────────────────────────────────────────────
-- TABLA: admins (Administradores del panel)
-- ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Credenciales
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Estado
    activo TINYINT(1) DEFAULT 1,
    ultimo_login TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indices
    UNIQUE KEY uk_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar admin por defecto
-- Password: admin123 (cambiar en producción)
INSERT INTO admins (nombre, email, password_hash) VALUES
('Administrador', 'admin@crematoriosdemascotas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');


-- ───────────────────────────────────────────────────────────
-- TABLA: resenas (Reseñas de usuarios)
-- ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS resenas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relación con crematorio
    crematorio_id INT UNSIGNED NOT NULL,

    -- Datos del autor
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,

    -- Contenido de la reseña
    comentario TEXT NOT NULL,
    calificacion TINYINT UNSIGNED NOT NULL,

    -- Estado de moderación
    -- 'pendiente' = recién enviada, esperando revisión
    -- 'aprobada' = visible en la ficha
    -- 'rechazada' = no se muestra (spam, inapropiada, etc)
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',

    -- Moderación
    moderado_por INT UNSIGNED DEFAULT NULL,
    moderado_en TIMESTAMP NULL DEFAULT NULL,
    motivo_rechazo VARCHAR(500) DEFAULT NULL,

    -- Metadatos
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    page_url VARCHAR(500) DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indices
    INDEX idx_crematorio (crematorio_id),
    INDEX idx_estado (estado),
    INDEX idx_calificacion (calificacion),
    INDEX idx_created (created_at),
    INDEX idx_crematorio_estado (crematorio_id, estado),

    -- Foreign Keys
    CONSTRAINT fk_resena_crematorio
        FOREIGN KEY (crematorio_id) REFERENCES crematorios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_resena_moderador
        FOREIGN KEY (moderado_por) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───────────────────────────────────────────────────────────
-- VISTA: Reseñas aprobadas con info del crematorio
-- ───────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_resenas_aprobadas AS
SELECT
    r.id,
    r.crematorio_id,
    r.nombre AS autor_nombre,
    r.comentario,
    r.calificacion,
    r.created_at,
    c.nombre AS crematorio_nombre,
    c.slug AS crematorio_slug
FROM resenas r
INNER JOIN crematorios c ON r.crematorio_id = c.id
WHERE r.estado = 'aprobada'
ORDER BY r.created_at DESC;


-- ───────────────────────────────────────────────────────────
-- VISTA: Estadísticas de reseñas por crematorio
-- ───────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_estadisticas_resenas AS
SELECT
    c.id AS crematorio_id,
    c.nombre AS crematorio_nombre,
    COUNT(r.id) AS total_resenas,
    COALESCE(AVG(r.calificacion), 0) AS promedio_calificacion,
    SUM(CASE WHEN r.calificacion = 1 THEN 1 ELSE 0 END) AS resenas_1,
    SUM(CASE WHEN r.calificacion = 2 THEN 1 ELSE 0 END) AS resenas_2,
    SUM(CASE WHEN r.calificacion = 3 THEN 1 ELSE 0 END) AS resenas_3,
    SUM(CASE WHEN r.calificacion = 4 THEN 1 ELSE 0 END) AS resenas_4,
    SUM(CASE WHEN r.calificacion = 5 THEN 1 ELSE 0 END) AS resenas_5
FROM crematorios c
LEFT JOIN resenas r ON r.crematorio_id = c.id AND r.estado = 'aprobada'
GROUP BY c.id, c.nombre;
