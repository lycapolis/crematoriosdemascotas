-- ═══════════════════════════════════════════════════════════
-- MIGRATION: configuración de throttling de formularios (lead-capture)
-- ═══════════════════════════════════════════════════════════
-- Permite a un super_admin activar/desactivar y ajustar las reglas de
-- throttling del widget lead-capture (modal que intercepta clicks en
-- tel/wa/maps/web) sin tocar código. Ver admin/configuracion-formularios.php,
-- obtenerConfigFormularios() en includes/funciones.php y
-- assets/js/lead-capture.js (consume window.LC_THROTTLE).
--
-- El seed de abajo replica EXACTAMENTE los valores que estaban hardcodeados
-- en lead-capture.js antes de esta migración — pero con throttling_activo=0
-- (desactivado por defecto, a pedido: "que funcionen siempre por ahora").
-- Al activarlo desde el panel se aplican estos mismos límites.
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS formularios_config (
    clave               VARCHAR(40)  NOT NULL PRIMARY KEY,
    throttling_activo   TINYINT(1)   NOT NULL DEFAULT 0,
    cap_global_sesion   INT          NOT NULL DEFAULT 4,
    skip_minutos        INT          NOT NULL DEFAULT 10,
    submit_horas        INT          NOT NULL DEFAULT 24,
    cookie_dias         INT          NOT NULL DEFAULT 1,
    actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por     INT          DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO formularios_config
    (clave, throttling_activo, cap_global_sesion, skip_minutos, submit_horas, cookie_dias)
VALUES
    ('lead_capture', 0, 4, 10, 24, 1);
