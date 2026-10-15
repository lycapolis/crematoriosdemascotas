<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FICHAS DE DEMOSTRACIÓN — una por plan (00, 01, 02, 03)
 * ═══════════════════════════════════════════════════════════
 * Crea 4 fichas con estado «demo» (activo = 0, noindex, fuera de listados,
 * mapas y sitemap; se abren solo por URL directa) para enseñar a clientes
 * potenciales cómo se ve cada plan y para probar conversiones y avisos con
 * la plataforma real. Los planes se leen en vivo de la tabla `tiers`, así que
 * cualquier cambio en el configurador de planes se refleja en las demo.
 *
 * Requisito: migración add_estado_demo.sql ya corrida.
 *
 * Uso:
 *   CLI:      php admin/migrations/seed_fichas_demo.php             → crea (idempotente)
 *             php admin/migrations/seed_fichas_demo.php --borrar    → borra las demo
 *   Browser:  /admin/migrations/seed_fichas_demo.php[?borrar=1]  (super_admin)
 *
 * Borrar elimina: las fichas demo, sus imágenes (filas y archivos), sus
 * reseñas y también los leads y clics registrados contra ellas (son datos de
 * prueba). Los leads/clics de fichas reales no se tocan.
 */

$esCli = PHP_SAPI === 'cli';
if ($esCli) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
    require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
    $borrar = in_array('--borrar', $argv ?? [], true);
} else {
    require_once dirname(__DIR__) . '/auth.php';
    requerirAutenticacion();
    requiereSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
    $borrar = !empty($_GET['borrar']);
}
require_once dirname(__DIR__, 2) . '/includes/funciones.php';
require_once dirname(__DIR__, 2) . '/includes/insignias.php';

$pdo  = obtenerConexion();
$raiz = dirname(__DIR__, 2);
$log  = function (string $m) { echo $m . PHP_EOL; };

// ─── Datos de contacto de prueba ────────────────────────────
// Teléfono/WhatsApp = número de pruebas del equipo (WHATSAPP_SOPORTE_ES_B2C).
$wa       = defined('WHATSAPP_SOPORTE_ES_B2C') ? WHATSAPP_SOPORTE_ES_B2C : '34631256751';
$telPunto = '+' . substr($wa, 0, 2) . ' ' . substr($wa, 2, 3) . ' ' . substr($wa, 5, 2) . ' ' . substr($wa, 7, 2) . ' ' . substr($wa, 9);

// ─── Definición de las 4 demo ───────────────────────────────
// fotos/resenas: más contenido a mayor plan, para que se note la diferencia.
$demos = [
    '00' => ['nombre' => 'Demo Gratis',   'slug' => 'demo-gratis',   'prov' => 30, 'ciudad' => 'Zaragoza', 'cp' => '50001', 'lat' => 41.6488, 'lng' => -0.8891, 'fotos' => 3,  'color' => [120, 130, 140], 'resenas' => 2],
    '01' => ['nombre' => 'Demo Básico',   'slug' => 'demo-basico',   'prov' => 25, 'ciudad' => 'Sevilla',  'cp' => '41001', 'lat' => 37.3891, 'lng' => -5.9845, 'fotos' => 6,  'color' => [70, 130, 120],  'resenas' => 3],
    '02' => ['nombre' => 'Demo Estándar', 'slug' => 'demo-estandar', 'prov' => 28, 'ciudad' => 'Valencia', 'cp' => '46001', 'lat' => 39.4699, 'lng' => -0.3763, 'fotos' => 10, 'color' => [60, 105, 160],  'resenas' => 5],
    '03' => ['nombre' => 'Demo Premium',  'slug' => 'demo-premium',  'prov' => 18, 'ciudad' => 'Madrid',   'cp' => '28001', 'lat' => 40.4168, 'lng' => -3.7038, 'fotos' => 16, 'color' => [140, 90, 60],   'resenas' => 6],
];

// 16 fotos de ejemplo (assets/img/demo/demo-NN.webp): archivo, categoría, texto alternativo
$fotos = [
    ['exterior',              'Fachada exterior de un crematorio de mascotas'],
    ['interior_sala',         'Sala de despedida con cesta de mimbre y decoración floral'],
    ['recuerdos_souvenires',  'Manos sosteniendo un corazón decorativo en recuerdo de una mascota'],
    ['interior_recepcion',    'Recepción moderna con mostrador y plantas decorativas'],
    ['interior_recepcion',    'Sala de espera con sillas y mesas'],
    ['interior_amenities',    'Monumento en forma de corazón en los jardines del centro'],
    ['produccion_tecnologia', 'Instalaciones técnicas con horno crematorio para mascotas'],
    ['recuerdos_souvenires',  'Urna cerámica blanca con corazón rojo para cenizas de mascota'],
    ['recuerdos_souvenires',  'Marco de madera con foto de mascota y huella'],
    ['recuerdos_souvenires',  'Urna de cristal con forma de gato junto a la mascota'],
    ['recuerdos_souvenires',  'Urnas decorativas en forma de corazón'],
    ['interior_sala',         'Sala de velatorio con flores para despedir a la mascota'],
    ['recuerdos_souvenires',  'Urna decorativa en forma de corazón rojo y blanco para cenizas'],
    ['recuerdos_souvenires',  'Urna funeraria blanca con huellas para mascotas'],
    ['recuerdos_souvenires',  'Urna funeraria blanca con huellas de mascotas'],
    ['recuerdos_souvenires',  'Figura decorativa de perro en cristal transparente'],
];

$resenasTxt = [
    [5, 'Marta G.',  'Nos acompañaron con muchísimo cariño en un momento muy difícil. Todo fue claro y respetuoso.'],
    [5, 'Javier R.', 'Recogida a domicilio puntual y trato exquisito. Recibimos las cenizas en una urna preciosa.'],
    [4, 'Lucía P.',  'Muy buena atención y explicaron cada paso. Repetiría el servicio si fuera necesario.'],
    [5, 'Andrés M.', 'Profesionales y humanos. La sala de despedida es acogedora y tranquila.'],
    [5, 'Carmen S.', 'Atención 24 horas de verdad: nos contestaron de madrugada y fueron muy amables.'],
    [4, 'Pablo T.',  'Todo correcto y a un precio razonable. Nos dieron un certificado y una huella de recuerdo.'],
];

// Fotos que envían los clientes con su reseña (assets/img/demo/demo-cliente-NN.webp): 1, 1, 2 y 2
$fotosClienteTxt = [
    'Perro y gato descansando juntos, foto enviada por una clienta',
    'Perro mayor descansando en su cama, foto enviada por un cliente',
];
$fotosClientePorPlan = ['00' => 1, '01' => 1, '02' => 2, '03' => 2];

// Respuestas del negocio a las primeras reseñas (se ve que el negocio interactúa): 1, 1, 2 y 2
$respuestasTxt = [
    'Gracias por escribirnos, Marta. Sabemos lo duro que es este momento y nos alegra haber podido estar a vuestro lado. Os mandamos un abrazo muy grande.',
    'Gracias por tomaros el tiempo de contarlo, Javier. Mucho ánimo en estos días; si en algún momento necesitáis cualquier cosa, aquí estamos.',
];
$respuestasPorPlan = ['00' => 1, '01' => 1, '02' => 2, '03' => 2];

// ─── Borrado ────────────────────────────────────────────────
$slugs = array_column($demos, 'slug');
$ph    = implode(',', array_fill(0, count($slugs), '?'));
$st    = $pdo->prepare("SELECT id, slug FROM crematorios WHERE slug IN ($ph) AND estado = 'demo'");
$st->execute($slugs);
$existentes = $st->fetchAll(PDO::FETCH_KEY_PAIR); // id => slug

$borrarCarpeta = function (string $dir) use (&$borrarCarpeta) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? $borrarCarpeta($p) : @unlink($p);
    }
    @rmdir($dir);
};

if ($borrar) {
    // Leads de demo ya sueltos (la ficha se borró antes por otro lado): se reconocen por el nombre denormalizado
    $nombresDemo = array_column($demos, 'nombre');
    $phN = implode(',', array_fill(0, count($nombresDemo), '?'));
    $st = $pdo->prepare("DELETE FROM leads_b2c WHERE crematorio_id IS NULL AND crematorio_nombre IN ($phN)");
    $st->execute($nombresDemo);
    if ($st->rowCount()) $log('Borrados ' . $st->rowCount() . ' leads de demo que habían quedado sueltos.');
    if (!$existentes) { $log('No hay fichas demo que borrar.'); exit; }
    $pdo->beginTransaction();
    try {
        foreach ($existentes as $id => $slug) {
            $pdo->prepare('DELETE FROM leads_b2c WHERE crematorio_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM outbound_clicks WHERE crematorio_id = ?')->execute([$id]);
            // imágenes, reseñas e insignias caen por FK CASCADE
            $pdo->prepare('DELETE FROM crematorios WHERE id = ? AND estado = \'demo\'')->execute([$id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $log('ERROR, no se borró nada: ' . $e->getMessage());
        exit(1);
    }
    foreach ($existentes as $id => $slug) {
        $borrarCarpeta($raiz . '/uploads/img-fichas/' . str_pad((string) $id, 4, '0', STR_PAD_LEFT));
        $log("Borrada: $slug (id $id)");
    }
    exit;
}

// ─── Creación (idempotente) ─────────────────────────────────
$st = $pdo->prepare("SELECT slug FROM crematorios WHERE slug IN ($ph)");
$st->execute($slugs);
$yaHay = $st->fetchAll(PDO::FETCH_COLUMN);
if ($yaHay) {
    $log('Ya existen: ' . implode(', ', $yaHay) . '. No se creó nada (usa --borrar para rehacerlas).');
    exit;
}
$tiersOk = $pdo->query('SELECT id FROM tiers')->fetchAll(PDO::FETCH_COLUMN);
if (array_diff(array_keys($demos), $tiersOk)) { $log('Faltan planes en la tabla tiers.'); exit(1); }
$estadoCol = $pdo->query("SHOW COLUMNS FROM crematorios LIKE 'estado'")->fetch(PDO::FETCH_ASSOC);
if (strpos($estadoCol['Type'] ?? '', "'demo'") === false) { $log("Falta la migración add_estado_demo.sql (el ENUM estado no tiene 'demo')."); exit(1); }

$ahora = date('Y-m-d H:i:s');
$pdo->beginTransaction();
$archivosCreados = [];
try {
    foreach ($demos as $tier => $d) {
        $email = "info+plan{$tier}@crematoriosdemascotas.com";
        $dir   = 'Calle de la Demostración 1';
        $dirCompleta = "$dir, {$d['cp']} {$d['ciudad']}";
        $tel = $telPunto;

        $desc = "{$d['nombre']} es una ficha de demostración ubicada en {$d['ciudad']} que muestra cómo se presenta un negocio de cremación de mascotas con este plan en el directorio.\n\n"
              . "Ofrece cremación individual y colectiva, recogida y entrega a domicilio, sala de despedida y una amplia selección de urnas y recuerdos personalizados. Atiende las 24 horas, todos los días del año, con un trato cercano y respetuoso en cada despedida.\n\n"
              . "Los datos de esta ficha son ficticios y se usan únicamente para enseñar el funcionamiento del directorio.";
        $meta = "Cremación de mascotas en {$d['ciudad']}: ficha de demostración del plan {$d['nombre']}.";

        $telefonos = [
            ['id' => 'p1', 'origen' => 'manual', 'tipo' => 'principal', 'label' => 'Teléfono principal', 'numero' => $tel, 'visible' => true],
            ['id' => 'p2', 'origen' => 'manual', 'tipo' => 'clientes',  'label' => 'Atención a clientes', 'numero' => $tel, 'visible' => true],
        ];
        $emails = [['id' => 'e1', 'origen' => 'manual', 'tipo' => 'general', 'label' => 'General / Contacto', 'email' => $email, 'visible' => true]];
        $redes  = ['modo' => 'iconos', 'entries' => [
            ['id' => 'r1', 'red' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/', 'visible' => true],
            ['id' => 'r2', 'red' => 'facebook',  'label' => 'Facebook',  'url' => 'https://www.facebook.com/',  'visible' => true],
        ]];
        $horarios = [
            'presencial' => ['lv' => '09:00-19:00', 's' => '10:00-14:00', 'd' => null],
            'telefonica' => ['activa' => true, 'modo' => 'horario', 'telefono' => $tel, 'horario' => ['lv' => '24h', 's' => '24h', 'd' => '24h']],
            'nota'       => 'Atención telefónica 24 horas, todos los días.',
        ];
        $horarioTexto = 'L-V: 09:00-19:00 | S: 10:00-14:00 | D: Cerrado | Tel: 24h todos los días';
        $precios = [
            ['id' => 'pr1', 'tipo' => 'desde', 'nombre' => 'Cremación colectiva',  'descripcion' => 'Mascotas de hasta 10 kg.', 'min' => 60,  'max' => null, 'destacado' => false],
            ['id' => 'pr2', 'tipo' => 'rango', 'nombre' => 'Cremación individual', 'descripcion' => 'Según el peso; cenizas en urna.', 'min' => 120, 'max' => 260, 'destacado' => true],
            ['id' => 'pr3', 'tipo' => 'fijo',  'nombre' => 'Recogida a domicilio', 'descripcion' => 'Dentro del área de cobertura.', 'min' => 40, 'max' => null, 'destacado' => false],
        ];
        $unJson = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ent = fn($valor) => [['id' => 'd1', 'origen' => 'manual', 'valor' => $valor, 'activo' => 1, 'creado_at' => $ahora, 'editado_at' => null]];
        $msgWa = "🐾 {$d['nombre']}\n📍 {$d['ciudad']}\n📞 $tel\n🕐 24h\n🔥 Cremación individual y colectiva";

        // Rating a partir de las reseñas propias que se insertan abajo
        $rs = array_slice($resenasTxt, 0, $d['resenas']);
        $notas = array_column($rs, 0);
        $rating = round(array_sum($notas) / count($notas), 1);
        $conteo = array_count_values($notas);

        $pdo->prepare("INSERT INTO crematorios
            (tier, recibe_notif_leads, email_notif_leads, provincia_id, nombre, slug, telefono, whatsapp, email, website,
             direccion_completa, calle, ciudad, codigo_postal, latitud, longitud, rating, reviews_total,
             reviews_1, reviews_2, reviews_3, reviews_4, reviews_5, verificado, destacado, activo, estado, origen,
             horarios, horario_texto, telefonos_json, emails_json, redes_json, descripcion, descripciones_json,
             meta_description_seo, metas_json, precios_json, mensaje_whatsapp, mensajes_whatsapp_json,
             zona_cobertura, ciudades_cobertura, servicios, atencion_24h, recogida_domicilio, entrega_domicilio,
             cremacion_individual, cremacion_colectiva, sala_velatoria, urna, carta, molde, souvenires)
            VALUES
            (:tier, 1, :email, :prov, :nombre, :slug, :tel, :wa, :email2, :web,
             :dir, :calle, :ciudad, :cp, :lat, :lng, :rating, :total,
             :r1, :r2, :r3, :r4, :r5, 0, 0, 0, 'demo', 'manual',
             :horarios, :htxt, :tels, :emails, :redes, :desc, :descj,
             :meta, :metaj, :precios, :mwa, :mwaj,
             :zona, :ciudades, :servicios, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)")
            ->execute([
                ':tier' => $tier, ':email' => $email, ':prov' => $d['prov'], ':nombre' => $d['nombre'], ':slug' => $d['slug'],
                ':tel' => $tel, ':wa' => $wa, ':email2' => $email, ':web' => 'https://crematoriosdemascotas.com',
                ':dir' => $dirCompleta, ':calle' => $dir, ':ciudad' => $d['ciudad'], ':cp' => $d['cp'],
                ':lat' => $d['lat'], ':lng' => $d['lng'], ':rating' => $rating, ':total' => count($rs),
                ':r1' => $conteo[1] ?? 0, ':r2' => $conteo[2] ?? 0, ':r3' => $conteo[3] ?? 0, ':r4' => $conteo[4] ?? 0, ':r5' => $conteo[5] ?? 0,
                ':horarios' => $unJson($horarios), ':htxt' => $horarioTexto,
                ':tels' => $unJson($telefonos), ':emails' => $unJson($emails), ':redes' => $unJson($redes),
                ':desc' => $desc, ':descj' => $unJson($ent($desc)),
                ':meta' => $meta, ':metaj' => $unJson($ent($meta)),
                ':precios' => $unJson($precios), ':mwa' => $msgWa, ':mwaj' => $unJson($ent($msgWa)),
                ':zona' => $d['ciudad'] . ' y alrededores', ':ciudades' => $d['ciudad'],
                ':servicios' => 'Cremación individual, Cremación colectiva, Recogida a domicilio, Sala de despedida, Urnas y recuerdos',
            ]);
        $id = (int) $pdo->lastInsertId();

        // ── Imágenes: carpeta uploads/img-fichas/NNNN/ ──
        $rel   = 'uploads/img-fichas/' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . '/';
        $abs   = $raiz . '/' . $rel;
        if (!is_dir($abs) && !mkdir($abs, 0755, true)) throw new RuntimeException("No se pudo crear $abs");
        $insImg = $pdo->prepare("INSERT INTO crematorio_imagenes
            (crematorio_id, tipo, origen, categoria, alt_text, estado_llm, categoria_origen, visible, nombre_archivo, ruta, orden)
            VALUES (?, ?, 'desconocido', ?, ?, 'procesada', 'admin', 1, ?, ?, ?)");

        // Logo ficticio generado (círculo de color con la inicial del plan)
        $lienzo = imagecreatetruecolor(400, 400);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        imagefilledellipse($lienzo, 200, 200, 380, 380, imagecolorallocate($lienzo, ...$d['color']));
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledellipse($lienzo, 200, 200, 300, 300, $blanco);
        imagefilledellipse($lienzo, 200, 200, 240, 240, imagecolorallocate($lienzo, ...$d['color']));
        $logoNom = 'logo-001-demo-' . $tier . '.webp';
        imagewebp($lienzo, $abs . $logoNom, 85);
        imagedestroy($lienzo);
        $archivosCreados[] = $abs . $logoNom;
        $insImg->execute([$id, 'logo', 'logo', "Logotipo de {$d['nombre']}", $logoNom, $rel . $logoNom, 0]);

        for ($i = 0; $i < $d['fotos']; $i++) {
            $nom = sprintf('%03d-demo-galeria-%s.webp', $i + 1, $tier);
            $src = $raiz . '/assets/img/demo/' . sprintf('demo-%02d.webp', $i + 1);
            if (!copy($src, $abs . $nom)) throw new RuntimeException("No se pudo copiar $src");
            $archivosCreados[] = $abs . $nom;
            $insImg->execute([$id, 'galeria', $fotos[$i][0], $fotos[$i][1] . ' — ' . $d['ciudad'], $nom, $rel . $nom, $i + 1]);
        }

        // ── Reseñas propias aprobadas (sin disparar notificaciones) ──
        $insRes = $pdo->prepare("INSERT INTO resenas (crematorio_id, nombre, email, comentario, calificacion, fuente, estado, moderado_en, created_at)
                                 VALUES (?, ?, 'demo@crematoriosdemascotas.com', ?, ?, 'propio', 'aprobada', ?, ?)");
        $insResp = $pdo->prepare("INSERT INTO resenas_respuestas (resena_id, texto, estado, moderado_en, created_at)
                                  VALUES (?, ?, 'aprobada', ?, ?)");
        foreach ($rs as $k => [$nota, $autor, $txt]) {
            $fecha = date('Y-m-d H:i:s', strtotime("-" . (10 + $k * 17) . ' days'));
            $insRes->execute([$id, $autor, $txt, $nota, $fecha, $fecha]);
            $resenaId = (int) $pdo->lastInsertId();
            if ($k < $respuestasPorPlan[$tier]) {
                $fResp = date('Y-m-d H:i:s', strtotime($fecha . ' +1 day'));
                $insResp->execute([$resenaId, $respuestasTxt[$k], $fResp, $fResp]);
            }
            if ($k < $fotosClientePorPlan[$tier]) {
                // Foto de cliente ligada a la reseña: sale bajo la reseña, en «Fotos de clientes»
                // y en el carrusel con la reseña al lado (visibilidad por defecto: completa)
                $nom = sprintf('cliente-%03d-demo-%s.webp', $k + 1, $tier);
                $src = $raiz . '/assets/img/demo/' . sprintf('demo-cliente-%02d.webp', $k + 1);
                if (!copy($src, $abs . $nom)) throw new RuntimeException("No se pudo copiar $src");
                $archivosCreados[] = $abs . $nom;
                $pdo->prepare("INSERT INTO crematorio_imagenes
                    (crematorio_id, resena_id, tipo, origen, categoria, alt_text, estado_llm, categoria_origen, visible, nombre_archivo, ruta, orden)
                    VALUES (?, ?, 'cliente', 'resena_cliente', 'fotos_clientes', ?, 'procesada', 'admin', 1, ?, ?, 0)")
                    ->execute([$id, $resenaId, $fotosClienteTxt[$k], $nom, $rel . $nom]);
            }
        }

        sincronizarCamposFlat($pdo, $id);
        $log("Creada: {$d['nombre']} (id $id, plan $tier, {$d['ciudad']}, {$d['fotos']} fotos, " . count($rs) . " reseñas) → /{$d['slug']}");
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    foreach ($archivosCreados as $f) @unlink($f);
    $log('ERROR, no se creó nada: ' . $e->getMessage());
    exit(1);
}

// Insignias y puntaje de las demo (fuera de la transacción)
foreach ($pdo->query("SELECT id FROM crematorios WHERE estado = 'demo'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
    try { recalcularInsignias($pdo, (int) $id); } catch (Throwable $e) { $log("Aviso: insignias de $id no recalculadas: " . $e->getMessage()); }
}
$log('Listo. Enlaces: ' . implode(' · ', array_map(fn($s) => '/' . $s, $slugs)));
