<?php
/**
 * ═══════════════════════════════════════════════════════════
 * NUBE DE CIUDADES — partial reusable
 * ═══════════════════════════════════════════════════════════
 *
 * Componente para internal linking SEO: muestra ciudades con
 * crematorios como botones que enlazan a /espana/{prov}/{ciu}/.
 *
 * Parametrizable por scope:
 *   - 'todas'     → top ciudades del país (ej. home / espana.php)
 *   - 'comunidad' → ciudades de una CCAA (comunidad.php)
 *   - 'provincia' → ciudades de una provincia (provincia.php)
 *   - 'cercanas'  → otras ciudades de la misma CCAA (ciudad.php / ficha.php)
 *
 * Uso:
 *   $nubeScope       = 'provincia';
 *   $nubeContextoId  = $provincia['id'];
 *   $nubeTitulo      = 'Ciudades de Barcelona'; // opcional
 *   $nubeLimite      = 30;                       // opcional, default 30
 *   include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
 *
 * Si la query no devuelve resultados, no imprime nada.
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 */

$scope      = $nubeScope      ?? 'todas';
$contextoId = (int)($nubeContextoId ?? 0);
$limite     = (int)($nubeLimite ?? 30);

$titulosPorScope = [
    'todas'     => 'Crematorios de mascotas por ciudad',
    'comunidad' => 'Ciudades en esta comunidad',
    'provincia' => 'Ciudades en esta provincia',
    'cercanas'  => 'Otras ciudades cercanas',
];
$titulo = $nubeTitulo ?? ($titulosPorScope[$scope] ?? 'Ciudades');

// Query según scope
$pdo = obtenerConexion();
if (!$pdo) return;

// El slug sale del catálogo `ciudades` (ASCII, único por provincia): ya no se arma desde el texto de la ficha.
$sql = "SELECT
            ci.nombre AS nombre,
            ci.slug AS slug,
            p.slug AS provincia_slug,
            COUNT(*) AS total
        FROM crematorios c
        JOIN ciudades ci ON ci.id = c.ciudad_id
        LEFT JOIN provincias p ON ci.provincia_id = p.id
        WHERE ci.activo = 1 AND ci.estado_revision <> 'pendiente'
          AND c.estado = 'activa'";

$params = [];
switch ($scope) {
    case 'comunidad':
        $sql .= " AND p.comunidad_id = :cid";
        $params[':cid'] = $contextoId;
        break;
    case 'provincia':
        $sql .= " AND c.provincia_id = :pid";
        $params[':pid'] = $contextoId;
        break;
    case 'cercanas':
        // Misma CCAA que la provincia dada, excluyéndola
        $sql .= " AND p.comunidad_id = (SELECT comunidad_id FROM provincias WHERE id = :pid_act)
                  AND c.provincia_id != :pid_act2";
        $params[':pid_act']  = $contextoId;
        $params[':pid_act2'] = $contextoId;
        break;
    case 'todas':
    default:
        // sin filtro extra
        break;
}

$sql .= " GROUP BY ci.id, ci.nombre, ci.slug, p.slug
          ORDER BY total DESC, ci.nombre ASC
          LIMIT " . $limite;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$_nubeCiudades = $stmt->fetchAll();

if (empty($_nubeCiudades)) return;

// Limpiar variables del scope del partial para no contaminar el caller
unset($nubeScope, $nubeContextoId, $nubeLimite, $nubeTitulo, $scope, $contextoId, $limite, $sql, $params, $stmt, $titulosPorScope);
?>
<section class="seccion nube-ciudades">
    <div class="contenedor">
        <div class="seccion__encabezado">
            <h2 class="seccion__titulo"><?php echo limpiar($titulo); ?></h2>
        </div>
        <div class="ciudades-grid">
            <?php foreach ($_nubeCiudades as $ciudad): ?>
            <a href="<?php echo generarUrl('ciudad', $ciudad['slug'], $ciudad['provincia_slug']); ?>" class="boton tres">
                <?php echo limpiar($ciudad['nombre']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php unset($_nubeCiudades, $titulo, $ciudad); ?>
