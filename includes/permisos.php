<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SISTEMA DE PERMISOS — Roles + Etiquetas de capacidad
 * ═══════════════════════════════════════════════════════════
 *
 * Modelo:
 *   - 3 roles base en `admins.rol`: super_admin | admin | user
 *   - Etiquetas (capacidades) en `admins.etiquetas` (JSON array)
 *   - super_admin ignora etiquetas — tiene todo permitido
 *   - admin/user: el permiso efectivo es el AND de tener la etiqueta
 *
 * Ver migración 17 — sql/17_admins_roles_etiquetas.sql
 */

// ─── Catálogo de etiquetas (capacidades) ─────────────────────────────────────
//
// Para sumar una etiqueta nueva al sistema:
//   1. Agregar la clave + descripción acá.
//   2. (Opcional) sumarla al default de algún rol si aplica.
//   3. Usarla con requierePermiso('clave') en los endpoints que la necesiten.

const ETIQUETAS_DISPONIBLES = [
    'moderacion'       => 'Aprobar/rechazar reseñas + marcar SPAM',
    'edicion_fichas'   => 'Editar contenido de fichas (descripción, contactos, horarios, etc.)',
    'eliminacion'      => 'Borrar definitivamente (fichas, reseñas, imágenes) — capacidad peligrosa',
    'ia'               => 'Usar tokens de IA (procesar imágenes, generar textos, alt texts)',
    'tiers'            => 'Configurar tiers, categorías y planes comerciales',
    'solicitudes'      => 'Aprobar/rechazar registros de negocios públicos',
    'imagenes'         => 'Subir/categorizar imágenes (incluye cola LLM)',
    'gestionar_admins' => 'Crear/editar otros admins (limitado — solo super_admin puede tocar a otros super_admin)',
];

// ─── Defaults por rol al crearse ─────────────────────────────────────────────
const ETIQUETAS_DEFAULT_POR_ROL = [
    'super_admin' => [],                                                       // ignora — tiene todo
    'admin'       => ['moderacion', 'edicion_fichas', 'ia', 'solicitudes', 'imagenes'],
    'user'        => [],                                                       // arranca vacío, se agregan manualmente
];

// ─── Roles disponibles ───────────────────────────────────────────────────────
const ROLES_DISPONIBLES = [
    'super_admin' => 'Super Admin — dueño del sistema, ignora etiquetas',
    'admin'       => 'Admin — operador con capacidades amplias',
    'user'        => 'User — acceso limitado, se habilita por etiquetas',
];

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

/**
 * Decodifica las etiquetas de un admin (campo JSON) a array.
 * @param array|null $admin Fila de admins.* o array desde sesión.
 * @return array<string>
 */
function etiquetasDelAdmin(?array $admin): array
{
    if (empty($admin)) return [];
    $raw = $admin['etiquetas'] ?? null;
    if (is_array($raw)) return $raw;                    // ya viene decodificado
    if (!is_string($raw) || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * ¿El admin tiene esta capacidad?
 *
 * @param array|null $admin Datos del admin (al menos 'rol' y 'etiquetas')
 * @param string     $cap   Clave del catálogo ETIQUETAS_DISPONIBLES
 * @return bool
 */
function tienePermiso(?array $admin, string $cap): bool
{
    if (empty($admin)) return false;
    if (($admin['rol'] ?? '') === 'super_admin') return true;
    return in_array($cap, etiquetasDelAdmin($admin), true);
}

/**
 * Versión "guard" que aborta si no tiene el permiso.
 * Para usar en cima de endpoints sensibles, después de requerirAutenticacion().
 *
 * En AJAX/JSON: devuelve 403 con JSON.
 * En request HTML normal: muestra página de "Sin permisos" y exit.
 */
function requierePermiso(string $cap): void
{
    $admin = obtenerAdminActual();
    if (tienePermiso($admin, $cap)) return;

    // Detectar si la respuesta esperada es JSON (AJAX típicamente)
    $aceptaJson = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
    );

    if ($aceptaJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'        => false,
            'mensaje'   => 'No tenés permiso para esta acción (requiere etiqueta: ' . $cap . ')',
            'permiso'   => $cap,
        ]);
        exit;
    }

    http_response_code(403);
    $titulo_pagina = 'Sin permisos — Admin';
    ?><!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>Sin permisos</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 3rem 2rem; max-width: 600px; margin: 0 auto; color: #374151; }
        .box { background:#fef2f2; border:1px solid #fca5a5; border-radius:8px; padding:1.5rem; }
        h1 { color:#991b1b; margin:0 0 .5rem 0; font-size:1.3rem; }
        code { background:#f3f4f6; padding:.1rem .35rem; border-radius:4px; }
        a { color:#1d4ed8; text-decoration:none; margin-top:1rem; display:inline-block; }
    </style>
    </head><body>
    <div class="box">
        <h1>⚠ Sin permisos suficientes</h1>
        <p>Para esta acción necesitás la etiqueta <code><?php echo htmlspecialchars($cap); ?></code>: <em><?php echo htmlspecialchars(ETIQUETAS_DISPONIBLES[$cap] ?? '(capacidad sin descripción)'); ?></em>.</p>
        <p>Si creés que deberías tener acceso, contactá con un super admin.</p>
        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php">← Volver al panel</a>
    </div>
    </body></html><?php
    exit;
}

/**
 * ¿El admin actual es super_admin?
 */
function esSuperAdmin(?array $admin = null): bool
{
    $admin = $admin ?? obtenerAdminActual();
    return !empty($admin) && ($admin['rol'] ?? '') === 'super_admin';
}

/**
 * Versión "guard" para super_admin solamente.
 */
function requiereSuperAdmin(): void
{
    if (esSuperAdmin()) return;

    $aceptaJson = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    );

    if ($aceptaJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => 'Solo super_admin puede hacer esta acción']);
        exit;
    }

    http_response_code(403);
    echo '<div style="padding:2rem;font-family:system-ui;color:#991b1b;"><h2>Sin permisos</h2><p>Esta acción requiere rol <code>super_admin</code>.</p><a href="' . BASE_URL . '/admin/dashboard.php">← Volver</a></div>';
    exit;
}
