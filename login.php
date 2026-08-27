<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LOGIN - ACCESO ADMIN - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Este archivo dejó de ser el login real (antes era un mockup
 * estático sin backend, ver historial de versiones anteriores).
 * El login funcional vive en /admin/login.php — acá solo
 * redirigimos para no dejar una puerta falsa pública.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';

header('Location: ' . BASE_URL . '/admin/login.php', true, 301);
exit;
