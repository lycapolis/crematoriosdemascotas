-- ═══════════════════════════════════════════════════════════
-- CREAR TABLA CIUDADES
-- ═══════════════════════════════════════════════════════════
USE crematorios_mascotas;

-- Crear tabla ciudades
CREATE TABLE ciudades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provincia_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_slug (slug),
    INDEX idx_provincia (provincia_id),

    CONSTRAINT fk_ciudad_provincia
        FOREIGN KEY (provincia_id) REFERENCES provincias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poblar con ciudades únicas de crematorios
INSERT INTO ciudades (provincia_id, nombre, slug)
SELECT DISTINCT
    c.provincia_id,
    c.ciudad,
    LOWER(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(c.ciudad, ' ', '-'),
                ',', ''),
            'á', 'a'),
        'é', 'e')
    )
FROM crematorios c
WHERE c.ciudad IS NOT NULL AND c.ciudad != ''
ORDER BY c.provincia_id, c.ciudad;

-- Verificar resultado
SELECT 'Ciudades creadas:' AS info, COUNT(*) AS total FROM ciudades;
