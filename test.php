<?php
/**
 * Página de prueba para verificar header y footer
 * Eliminar después de verificar que todo funciona
 */

$titulo_pagina = "Test - Crematorios de Mascotas";
$pagina_actual = "inicio";
include 'includes/header.php';
?>

    <!-- Contenido de prueba -->
    <main class="seccion" style="max-width: var(--contenedor-cuatro); margin: 0 auto; padding: var(--espacio-cinco);">
            <h1>Página de Prueba</h1>
            <p>Si ves el header arriba y el footer abajo, todo funciona correctamente.</p>

            <h2>Checklist de verificación:</h2>
            <ul>
                <li>Header sticky (haz scroll para probar)</li>
                <li>Logo con icono paw-print</li>
                <li>Menú desktop con 4 enlaces + botón contacto</li>
                <li>Menú móvil (redimensiona a menos de 768px)</li>
                <li>Iconos Lucide visibles</li>
                <li>Footer con 4 columnas</li>
                <li>Redes sociales con hover</li>
            </ul>

            <h2>Variables usadas:</h2>
            <ul>
                <li><code>$titulo_pagina</code> = "<?php echo htmlspecialchars($titulo_pagina); ?>"</li>
                <li><code>$pagina_actual</code> = "<?php echo htmlspecialchars($pagina_actual); ?>"</li>
            </ul>

            <!-- Contenido largo para probar sticky -->
            <div style="height: 100vh; background: var(--color-cinco); border-radius: var(--radio-dos); display: flex; align-items: center; justify-content: center; margin-top: var(--espacio-cinco);">
                <p>Haz scroll para probar el header sticky</p>
            </div>
    </main>

<?php include 'includes/footer.php'; ?>
