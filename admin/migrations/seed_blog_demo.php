<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BLOG — contenido de ejemplo (idempotente)
 * ═══════════════════════════════════════════════════════════
 * Carga: categorías (con subcategoría), etiquetas, una taxonomía libre de
 * ejemplo ("Tipos de mascota"), el autor "Equipo de Crematorios de
 * Mascotas", 5 módulos especiales + 1 regla automática y 4 artículos
 * publicados para mostrar la plantilla al equipo. Se reemplazan después
 * por los artículos reales.
 *
 * Ejecutar UNA vez, después de create_blog.sql:
 *   CLI:      php admin/migrations/seed_blog_demo.php
 *   Browser:  /admin/migrations/seed_blog_demo.php (logueado como super_admin)
 *
 * Si los artículos de ejemplo ya existen, no duplica nada.
 */

$esCli = PHP_SAPI === 'cli';
if ($esCli) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
    require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
} else {
    require_once dirname(__DIR__) . '/auth.php';
    requerirAutenticacion();
    requiereSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/includes/funciones.php';
require_once dirname(__DIR__, 2) . '/includes/blog.php';

$pdo = obtenerConexion();
$log = function (string $m) { echo $m . PHP_EOL; };

// ─── Helpers de bloques Editor.js ───────────────────────────
$n = 0;
$id = function () use (&$n) { return 'demo' . (++$n); };
$p  = fn(string $t) => ['id' => $id(), 'type' => 'paragraph', 'data' => ['text' => $t]];
$h2 = fn(string $t) => ['id' => $id(), 'type' => 'header', 'data' => ['text' => $t, 'level' => 2]];
$h3 = fn(string $t) => ['id' => $id(), 'type' => 'header', 'data' => ['text' => $t, 'level' => 3]];
$ul = fn(array $items, string $estilo = 'unordered') => ['id' => $id(), 'type' => 'list', 'data' => [
    'style' => $estilo, 'meta' => new stdClass(),
    'items' => array_map(fn($i) => ['content' => $i, 'meta' => new stdClass(), 'items' => []], $items)]];
$aviso = fn(string $tipo, string $titulo, string $t) => ['id' => $id(), 'type' => 'aviso', 'data' => ['tipo' => $tipo, 'titulo' => $titulo, 'text' => $t]];
$faq = fn(array $items) => ['id' => $id(), 'type' => 'faq', 'data' => ['titulo' => 'Preguntas frecuentes',
    'items' => array_map(fn($x) => ['pregunta' => $x[0], 'respuesta' => $x[1]], $items)]];
$mod = fn(int $mid) => ['id' => $id(), 'type' => 'modulo', 'data' => ['modulo_id' => $mid]];
$cita = fn(string $t, string $c = '') => ['id' => $id(), 'type' => 'quote', 'data' => ['text' => $t, 'caption' => $c, 'alignment' => 'left']];
$tabla = fn(array $filas) => ['id' => $id(), 'type' => 'table', 'data' => ['withHeadings' => true, 'content' => $filas]];
$sep = fn() => ['id' => $id(), 'type' => 'delimiter', 'data' => new stdClass()];

// ─── ¿Ya está cargado? ──────────────────────────────────────
$slugsDemo = ['cremacion-individual-o-colectiva', 'que-hacer-cuando-fallece-tu-mascota', 'duelo-por-la-perdida-de-una-mascota', 'ideas-para-recordar-a-tu-mascota'];
$ph = implode(',', array_fill(0, count($slugsDemo), '?'));
$st = $pdo->prepare("SELECT COUNT(*) FROM blog_articulos WHERE slug IN ($ph)");
$st->execute($slugsDemo);
if ((int) $st->fetchColumn() > 0) { $log('Los artículos de ejemplo ya existen. No se cargó nada.'); exit; }

$pdo->beginTransaction();
try {
    // ─── Taxonomías ─────────────────────────────────────────
    $taxCat = (int) $pdo->query("SELECT id FROM blog_taxonomias WHERE slug = 'categoria'")->fetchColumn();
    $taxEtq = (int) $pdo->query("SELECT id FROM blog_taxonomias WHERE slug = 'etiqueta'")->fetchColumn();
    $pdo->exec("INSERT IGNORE INTO blog_taxonomias (nombre, nombre_singular, slug, descripcion, jerarquica, publica, sistema, orden)
                VALUES ('Tipos de mascota', 'Tipo de mascota', 'tipo-de-mascota', 'Ejemplo de taxonomía libre: clasifica por animal.', 0, 1, 0, 3)");
    $taxMasc = (int) $pdo->query("SELECT id FROM blog_taxonomias WHERE slug = 'tipo-de-mascota'")->fetchColumn();

    $termino = function (int $tax, string $nombre, ?int $padre = null, string $desc = '', int $orden = 0) use ($pdo): int {
        $slug = slugificar($nombre);
        $st = $pdo->prepare("SELECT id FROM blog_terminos WHERE taxonomia_id = :t AND slug = :s");
        $st->execute([':t' => $tax, ':s' => $slug]);
        if ($idx = $st->fetchColumn()) return (int) $idx;
        $pdo->prepare("INSERT INTO blog_terminos (taxonomia_id, parent_id, nombre, slug, descripcion, orden) VALUES (:t, :p, :n, :s, :d, :o)")
            ->execute([':t' => $tax, ':p' => $padre, ':n' => $nombre, ':s' => $slug, ':d' => $desc ?: null, ':o' => $orden]);
        return (int) $pdo->lastInsertId();
    };

    $cGuias    = $termino($taxCat, 'Guías prácticas', null, 'Pasos claros y consejos prácticos para los momentos difíciles: qué hacer, a quién llamar y qué decisiones tomar.', 1);
    $cCrem     = $termino($taxCat, 'Cremación de mascotas', null, 'Todo sobre la cremación de mascotas: cómo funciona, qué opciones existen y cómo elegir el servicio adecuado.', 2);
    $cTipos    = $termino($taxCat, 'Tipos de cremación', $cCrem, 'Diferencias entre cremación individual, colectiva y otras modalidades.', 1);
    $cDuelo    = $termino($taxCat, 'Duelo y acompañamiento', null, 'Cómo afrontar la pérdida de una mascota y acompañar a la familia, incluidos los más pequeños.', 3);
    $cRecuerdo = $termino($taxCat, 'Recuerdos y homenajes', null, 'Urnas, huellas, joyas y otras ideas para mantener vivo el recuerdo de tu mascota.', 4);
    $termino($taxCat, 'Precios y presupuestos', null, 'Qué influye en el precio de la cremación de una mascota y cómo pedir presupuesto.', 5);

    $e = fn(string $n) => $termino($taxEtq, $n);
    $m = fn(string $n) => $termino($taxMasc, $n);
    $mPerro = $m('Perros'); $mGato = $m('Gatos'); $m('Conejos y pequeños mamíferos');

    // ─── Autor ──────────────────────────────────────────────
    $st = $pdo->prepare("SELECT id FROM blog_autores WHERE slug = 'equipo-de-crematorios-de-mascotas'");
    $st->execute();
    $autor = (int) $st->fetchColumn();
    if (!$autor) {
        $pdo->prepare("INSERT INTO blog_autores (nombre, slug, cargo, bio, foto, activo) VALUES (:n, :s, :c, :b, :f, 1)")->execute([
            ':n' => 'Equipo de Crematorios de Mascotas',
            ':s' => 'equipo-de-crematorios-de-mascotas',
            ':c' => 'Equipo editorial',
            ':b' => 'Somos el equipo que está detrás del directorio de crematorios de mascotas en España. Revisamos la información de cada negocio y preparamos guías claras para ayudarte a tomar decisiones con calma en un momento difícil.',
            ':f' => 'assets/img/blog-demo/avatar-equipo.webp',
        ]);
        $autor = (int) $pdo->lastInsertId();
    }

    // ─── Módulos ────────────────────────────────────────────
    $modulo = function (string $nombre, string $tipo, array $cfg) use ($pdo): int {
        $pdo->prepare("INSERT INTO blog_modulos (nombre, tipo, config_json, activo) VALUES (:n, :t, :c, 1)")
            ->execute([':n' => $nombre, ':t' => $tipo, ':c' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return (int) $pdo->lastInsertId();
    };
    $mBuscar = $modulo('CTA · Buscar crematorio', 'cta', [
        'titulo' => 'Encuentra un crematorio de mascotas cerca de ti',
        'texto' => 'Compara servicios, horarios y reseñas de crematorios en toda España.',
        'boton_texto' => 'Ver el directorio', 'boton_url' => '/directorio.php', 'estilo' => 'suave', 'icono' => 'search']);
    $mDestacado = $modulo('Negocio destacado · automático', 'ficha_destacada', [
        'modo' => 'auto', 'ficha_id' => 0, 'titulo' => 'Crematorio recomendado', 'etiqueta' => 'Destacado']);
    $mLead = $modulo('WhatsApp · Ayuda para elegir', 'lead', [
        'titulo' => '¿Necesitas ayuda para elegir?',
        'texto' => 'Cuéntanos dónde estás y qué necesitas: te orientamos sin coste y sin compromiso.',
        'boton_texto' => 'Escríbenos por WhatsApp',
        'mensaje_wa' => 'Hola, me gustaría recibir ayuda para elegir un crematorio para mi mascota.']);
    $mBanner = $modulo('Banner · Promociona tu crematorio', 'banner', [
        'imagen' => 'assets/img/blog-demo/banner-directorio.webp', 'imagen_mobile' => 'assets/img/blog-demo/banner-directorio-movil.webp',
        'url' => '/promociona-tu-crematorio.php', 'alt' => '¿Tienes un crematorio de mascotas? Aparece en el directorio', 'etiqueta' => 'Para negocios']);
    $mUrgente = $modulo('CTA · Atención 24 horas', 'cta', [
        'titulo' => '¿Necesitas un servicio ahora mismo?',
        'texto' => 'Muchos crematorios atienden las 24 horas y recogen a domicilio.',
        'boton_texto' => 'Ver crematorios 24 h', 'boton_url' => '/directorio.php?atencion_24h=1', 'estilo' => 'intenso', 'icono' => 'phone']);

    // Regla automática: la CTA del directorio después del 3.er párrafo en todos los artículos
    $pdo->prepare("INSERT INTO blog_modulo_reglas (modulo_id, parrafo, ambito) VALUES (:m, 3, 'todos')")->execute([':m' => $mBuscar]);

    // ─── Artículos ──────────────────────────────────────────
    $articulos = [];

    // 1 ─ Cremación individual o colectiva
    $articulos[] = [
        'titulo' => 'Cremación individual o colectiva de mascotas: diferencias y cómo elegir',
        'slug' => 'cremacion-individual-o-colectiva',
        'extracto' => 'Te explicamos en qué se diferencian la cremación individual y la colectiva, qué ocurre con las cenizas en cada caso y qué preguntas hacer al crematorio antes de decidir.',
        'keyword' => 'cremación individual o colectiva',
        'meta_title' => 'Cremación individual o colectiva de mascotas: cuál elegir',
        'meta_description' => 'Diferencias entre la cremación individual y la colectiva de mascotas: qué pasa con las cenizas, qué incluye cada servicio y qué preguntar antes de elegir.',
        'portada' => 'assets/img/blog-demo/cremacion-individual-colectiva.webp',
        'portada_alt' => 'Ilustración de huellas de mascota sobre fondo cálido',
        'categoria' => $cTipos, 'terminos' => [$cCrem, $cTipos, $e('Cremación individual'), $e('Cremación colectiva'), $e('Cenizas'), $mPerro, $mGato],
        'contexto' => [['servicio', 'cremacion_individual'], ['servicio', 'cremacion_colectiva']],
        'dias' => 0, 'destacado' => 0,
        'bloques' => [
            $p('Cuando llega el momento de despedir a una mascota, una de las primeras decisiones es elegir el <b>tipo de cremación</b>. Las dos modalidades más habituales son la cremación individual y la colectiva, y la diferencia principal está en lo que ocurre con las cenizas.'),
            $p('No hay una opción mejor que otra: cada familia tiene sus necesidades, sus creencias y su presupuesto. En esta guía te explicamos qué implica cada una para que puedas decidir con tranquilidad.'),
            $h2('¿Qué es la cremación individual?'),
            $p('En la <b>cremación individual</b> tu mascota se incinera sola, sin otros animales en la cámara. Por eso, las cenizas que te entregan corresponden únicamente a tu compañero.'),
            $p('Es la opción que eligen la mayoría de las familias que desean conservar las cenizas en casa, esparcirlas en un lugar especial o guardarlas en una urna o joya conmemorativa.'),
            $ul([
                'Recibes las cenizas de tu mascota, normalmente en una urna básica o en la que elijas.',
                'Muchos crematorios entregan un <b>certificado de cremación</b> que identifica al animal.',
                'Algunos centros permiten estar presente o despedirse en una sala antes del servicio.',
            ]),
            $mod($mDestacado),
            $h2('¿Qué es la cremación colectiva?'),
            $p('En la <b>cremación colectiva</b> se incineran varios animales a la vez. Al terminar, las cenizas no se pueden separar, por lo que <b>no se entregan a la familia</b>. El crematorio se encarga de su destino final de forma respetuosa, según la normativa aplicable.'),
            $p('Es una opción más económica y adecuada para quienes no desean conservar las cenizas, pero sí quieren que su mascota tenga una despedida digna y un tratamiento correcto de sus restos.'),
            $h2('Diferencias principales'),
            $tabla([
                ['', 'Cremación individual', 'Cremación colectiva'],
                ['Entrega de cenizas', 'Sí, solo las de tu mascota', 'No'],
                ['Certificado', 'Habitual', 'Depende del crematorio'],
                ['Precio', 'Más alto', 'Más económico'],
                ['Despedida en sala', 'Según el centro', 'Poco habitual'],
            ]),
            $aviso('consejo', 'Pide siempre el detalle por escrito', 'Los servicios incluidos cambian de un crematorio a otro: urna, recogida a domicilio, certificado o plazo de entrega. Pide que te detallen qué incluye el precio antes de confirmar.'),
            $h2('Cómo elegir: preguntas que conviene hacer'),
            $ul([
                '¿La cremación es realmente individual? ¿Cómo identifican a mi mascota durante el proceso?',
                '¿Qué incluye el precio: recogida, urna, certificado, entrega a domicilio?',
                '¿En cuánto tiempo me entregan las cenizas?',
                '¿Puedo despedirme o estar presente?',
                '¿Qué opciones de urnas o recuerdos ofrecen?',
            ], 'ordered'),
            $p('Si todavía no sabes a qué crematorio acudir, en nuestro <a href="/directorio.php">directorio de crematorios de mascotas</a> puedes filtrar por <b>cremación individual</b> o <b>colectiva</b> y comparar servicios y reseñas de tu zona. También te puede ayudar nuestra guía sobre <a href="/blog/que-hacer-cuando-fallece-tu-mascota">qué hacer cuando fallece tu mascota</a>.'),
            $faq([
                ['¿Puedo cambiar de opinión después de elegir la cremación colectiva?', 'Una vez realizada la cremación colectiva no es posible recuperar las cenizas. Si tienes dudas, coméntalo con el crematorio antes de firmar la autorización.'],
                ['¿La cremación individual es siempre más cara?', 'Por lo general sí, porque la cámara se utiliza para un solo animal. El precio final depende del peso de la mascota y de los servicios que incluyas.'],
                ['¿Qué pasa con las cenizas en la cremación colectiva?', 'El crematorio les da un destino final respetuoso de acuerdo con la normativa. Puedes preguntar cómo lo hacen en su caso concreto.'],
            ]),
        ],
    ];

    // 2 ─ Qué hacer cuando fallece tu mascota
    $articulos[] = [
        'titulo' => 'Qué hacer cuando fallece tu mascota: guía paso a paso',
        'slug' => 'que-hacer-cuando-fallece-tu-mascota',
        'extracto' => 'Las primeras horas son las más difíciles. Esta guía te acompaña paso a paso: a quién llamar, cómo cuidar el cuerpo de tu mascota y qué opciones de despedida existen.',
        'keyword' => 'qué hacer cuando fallece tu mascota',
        'meta_title' => 'Qué hacer cuando fallece tu mascota: guía paso a paso',
        'meta_description' => 'Qué hacer en las primeras horas tras la muerte de tu mascota: a quién llamar, cómo cuidar su cuerpo, trámites y opciones de cremación o entierro.',
        'portada' => 'assets/img/blog-demo/que-hacer-cuando-fallece.webp',
        'portada_alt' => 'Ilustración de un rastro de huellas de mascota',
        'categoria' => $cGuias, 'terminos' => [$cGuias, $e('Primeros pasos'), $e('Veterinario'), $e('Recogida a domicilio'), $mPerro, $mGato],
        'contexto' => [['servicio', 'recogida_domicilio'], ['servicio', 'atencion_24h']],
        'dias' => 1, 'destacado' => 1,
        'bloques' => [
            $p('La muerte de una mascota suele llegar con una mezcla de tristeza y desconcierto. Es normal no saber qué hacer en ese momento. Aquí tienes una guía sencilla, paso a paso, para que puedas centrarte en despedirte sin preocuparte por lo demás.'),
            $p('Cada situación es distinta: no es lo mismo una despedida en la clínica veterinaria que un fallecimiento inesperado en casa. Adapta estos pasos a tu caso y, ante cualquier duda, consulta con tu veterinario.'),
            $h2('1. Tómate un momento'),
            $p('No hay prisa inmediata. Si tu mascota ha fallecido en casa, puedes tomarte unos minutos para despedirte con calma y avisar a las personas de la familia que quieran hacerlo.'),
            $mod($mUrgente),
            $h2('2. Llama a tu veterinario'),
            $p('Tu veterinario puede confirmar el fallecimiento, orientarte sobre los siguientes pasos y, en muchos casos, gestionar directamente la cremación con un crematorio de confianza. Además, si tu mascota tenía microchip, conviene <b>comunicar la baja en el registro</b> correspondiente de tu comunidad autónoma.'),
            $h2('3. Cuida el cuerpo de tu mascota'),
            $p('Mientras decides qué hacer, colócala en un lugar fresco, sobre una manta o una toalla, en una postura tranquila. Si van a pasar varias horas hasta la recogida, pregunta a tu veterinario o al crematorio cómo conservarla correctamente.'),
            $aviso('importante', 'Consulta la normativa de tu zona', 'Enterrar a una mascota en un jardín o en un terreno particular puede no estar permitido en muchos municipios. Antes de hacerlo, infórmate en tu ayuntamiento o elige un servicio autorizado de cremación o cementerio de mascotas.'),
            $h2('4. Elige cómo despedirte'),
            $p('Las opciones más habituales son la cremación (individual o colectiva) y el entierro en un cementerio de mascotas. Si tienes dudas sobre la primera, te lo explicamos en <a href="/blog/cremacion-individual-o-colectiva">cremación individual o colectiva: diferencias y cómo elegir</a>.'),
            $h3('Servicios que pueden ayudarte'),
            $ul([
                '<b>Recogida a domicilio</b>: el crematorio va a buscar a tu mascota a casa o a la clínica.',
                '<b>Atención 24 horas</b>: útil si el fallecimiento ocurre de noche o en festivo.',
                '<b>Sala de despedida</b>: un espacio tranquilo para decir adiós antes de la cremación.',
                '<b>Entrega de cenizas a domicilio</b>: para no tener que desplazarte.',
            ]),
            $mod($mLead),
            $h2('5. Date tiempo para el duelo'),
            $p('Perder a un compañero de vida duele, y ese dolor merece respeto. Si quieres leer más sobre cómo afrontarlo, y cómo explicárselo a los niños, te recomendamos nuestra guía sobre el <a href="/blog/duelo-por-la-perdida-de-una-mascota">duelo por la pérdida de una mascota</a>.'),
            $faq([
                ['¿Cuánto tiempo puedo esperar antes de la cremación?', 'Depende de la temperatura y de cómo se conserve el cuerpo. Lo más recomendable es contactar con el crematorio o con tu veterinario lo antes posible para que te indiquen los plazos.'],
                ['¿Puede encargarse el veterinario de todo?', 'Muchas clínicas trabajan con crematorios y pueden gestionar el servicio. También puedes contactar tú directamente con el crematorio que prefieras.'],
                ['¿Tengo que dar de baja el microchip?', 'Sí, es recomendable comunicar el fallecimiento al registro de identificación de animales de tu comunidad autónoma. Tu veterinario puede ayudarte con el trámite.'],
            ]),
        ],
    ];

    // 3 ─ Duelo
    $articulos[] = [
        'titulo' => 'Duelo por la pérdida de una mascota: cómo afrontarlo y acompañar a los niños',
        'slug' => 'duelo-por-la-perdida-de-una-mascota',
        'extracto' => 'El dolor por la pérdida de una mascota es real y merece su tiempo. Te contamos qué es normal sentir, cómo cuidarte y cómo hablar con los niños sobre lo ocurrido.',
        'keyword' => 'duelo por la pérdida de una mascota',
        'meta_title' => 'Duelo por la pérdida de una mascota: cómo afrontarlo',
        'meta_description' => 'Qué es normal sentir tras la muerte de una mascota, cómo transitar el duelo y cómo explicárselo a los niños con palabras sencillas y honestas.',
        'portada' => 'assets/img/blog-demo/duelo-mascota.webp',
        'portada_alt' => 'Ilustración suave de una huella de mascota',
        'categoria' => $cDuelo, 'terminos' => [$cDuelo, $e('Duelo'), $e('Niños'), $e('Emociones'), $mPerro, $mGato],
        'contexto' => [],
        'dias' => 3, 'destacado' => 0,
        'bloques' => [
            $p('Una mascota forma parte de la familia. Comparte rutinas, paseos, sofá y años de vida. Por eso, cuando se va, el vacío puede ser enorme. Sentir tristeza, culpa o incluso enfado es completamente normal.'),
            $p('En este artículo encontrarás ideas para transitar el duelo a tu ritmo y algunas pautas para acompañar a los más pequeños de la casa.'),
            $h2('Lo que sientes es normal'),
            $p('El duelo por una mascota no es "menos" que otros duelos. Cada persona lo vive a su manera y con sus tiempos. Algunas emociones frecuentes son:'),
            $ul(['Tristeza y llanto inesperado al ver sus cosas.', 'Sensación de culpa ("¿podría haber hecho algo más?").', 'Echar de menos las rutinas diarias.', 'Alivio, si tu mascota estaba sufriendo, seguido a veces de culpa por sentirlo.']),
            $cita('No hace falta tener prisa por "estar bien". Date permiso para echarle de menos.'),
            $h2('Pequeños gestos que ayudan'),
            $ul([
                'Habla de tu mascota con personas que la conocieron.',
                'Escribe una carta de despedida o reúne sus fotos favoritas.',
                'Mantén alguna rutina, como el paseo, para cuidar tu bienestar.',
                'Crea un pequeño homenaje: una urna, una huella o un rincón con su recuerdo.',
            ]),
            $p('Si te apetece, en <a href="/blog/ideas-para-recordar-a-tu-mascota">ideas para recordar a tu mascota</a> encontrarás propuestas para crear ese homenaje.'),
            $mod($mLead),
            $h2('Cómo hablar con los niños'),
            $p('Los niños necesitan información sencilla y honesta, adaptada a su edad. Evita expresiones como "se ha quedado dormido" o "se ha ido de viaje", porque pueden generar miedo o confusión.'),
            $h3('Algunas pautas'),
            $ul([
                'Explica lo ocurrido con palabras claras: "ha muerto, su cuerpo ha dejado de funcionar".',
                'Deja que pregunten y responde con calma, aunque repitan las mismas preguntas.',
                'Permite que participen en la despedida: un dibujo, una carta o elegir una foto.',
                'Comparte también tu tristeza: les enseña que es normal sentirse así.',
            ], 'ordered'),
            $aviso('consejo', 'Cuándo pedir ayuda', 'Si la tristeza se mantiene durante mucho tiempo o te impide hacer tu vida, habla con un profesional de la psicología. Pedir ayuda es una forma de cuidarte.'),
            $faq([
                ['¿Cuánto dura el duelo por una mascota?', 'No hay un tiempo fijo. Para algunas personas son semanas y para otras meses. Lo importante es permitirte vivirlo sin juzgarte.'],
                ['¿Es buena idea adoptar otra mascota enseguida?', 'Depende de cada familia. Muchas personas prefieren esperar a sentirse preparadas para dar la bienvenida a un nuevo compañero sin sentir que "reemplaza" al anterior.'],
            ]),
        ],
    ];

    // 4 ─ Recuerdos
    $articulos[] = [
        'titulo' => 'Ideas para recordar a tu mascota: urnas, huellas y otros homenajes',
        'slug' => 'ideas-para-recordar-a-tu-mascota',
        'extracto' => 'Urnas, moldes de huella, joyas con cenizas o un rincón especial en casa: repasamos las formas más habituales de mantener vivo el recuerdo de tu mascota.',
        'keyword' => 'recuerdos de mascotas',
        'meta_title' => 'Recuerdos de mascotas: urnas, huellas y otras ideas',
        'meta_description' => 'Ideas de recuerdos de mascotas para homenajear a tu compañero: urnas, moldes de huella, joyas con cenizas, fotografías y rincones de memoria en casa.',
        'portada' => 'assets/img/blog-demo/recuerdos-mascota.webp',
        'portada_alt' => 'Ilustración de huellas de mascota en tonos cálidos',
        'categoria' => $cRecuerdo, 'terminos' => [$cRecuerdo, $e('Urnas'), $e('Huella'), $e('Cenizas'), $e('Homenajes')],
        'contexto' => [['servicio', 'urna'], ['servicio', 'molde'], ['servicio', 'souvenires']],
        'dias' => 6, 'destacado' => 0,
        'bloques' => [
            $p('Despedirse no significa olvidar. Muchas familias encuentran consuelo en conservar un <b>recuerdo físico</b> de su mascota: algo que puedan ver, tocar o llevar consigo.'),
            $p('Estos son algunos de los recuerdos de mascotas más habituales. Muchos crematorios los ofrecen como parte de sus servicios o como complemento.'),
            $h2('Urnas para cenizas'),
            $p('Si eliges una <a href="/blog/cremacion-individual-o-colectiva">cremación individual</a>, recibirás las cenizas de tu mascota. Hay urnas de muchos materiales y estilos: cerámica, madera, metal, biodegradables para plantar un árbol o con espacio para una foto.'),
            $mod($mDestacado),
            $h2('Molde de huella'),
            $p('El molde de la huella es uno de los recuerdos más emotivos. Se toma la impresión de la pata de tu mascota en arcilla, yeso u otros materiales, y se conserva para siempre. Pregunta al crematorio si lo ofrece: suele hacerse antes de la cremación.'),
            $h2('Joyas y pequeños recuerdos'),
            $ul([
                'Colgantes o anillos que guardan una pequeña cantidad de cenizas.',
                'Mechones de pelo en un relicario o marco.',
                'Placas grabadas con su nombre y fechas.',
                'Cuadros o ilustraciones a partir de una fotografía.',
            ]),
            $aviso('consejo', 'Decide sin prisa', 'No hace falta elegir todos los recuerdos el mismo día. Si el crematorio guarda las cenizas unos días o te entrega una urna básica, puedes decidir más adelante con más calma.'),
            $h2('Un rincón de memoria en casa'),
            $p('Una estantería con su foto, su collar y la urna; una planta que plantaste en su honor; o un álbum con sus mejores momentos. Son gestos sencillos que ayudan a transitar el <a href="/blog/duelo-por-la-perdida-de-una-mascota">duelo</a>.'),
            $sep(),
            $p('¿Buscas un crematorio que ofrezca urnas, moldes de huella u otros recuerdos? En el <a href="/directorio.php">directorio</a> puedes filtrar por estos servicios.'),
            $mod($mBanner),
        ],
    ];

    // ─── Insertar ───────────────────────────────────────────
    $ins = $pdo->prepare("INSERT INTO blog_articulos
        (titulo, slug, extracto, contenido_json, portada_ruta, portada_alt, autor_id, categoria_principal_id, estado, publicado_at,
         destacado, mostrar_indice, meta_title, meta_description, keyword_principal, palabras, lectura_min)
        VALUES (:t, :s, :ex, :c, :pr, :pa, :au, :cat, 'publicado', :pub, :dest, 1, :mt, :md, :kw, :pal, :lec)");
    foreach ($articulos as $a) {
        $palabras = blogContarPalabras(json_decode(json_encode($a['bloques']), true));
        $ins->execute([
            ':t' => $a['titulo'], ':s' => $a['slug'], ':ex' => $a['extracto'],
            ':c' => json_encode(['time' => time() * 1000, 'blocks' => $a['bloques'], 'version' => '2.30.8'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':pr' => $a['portada'], ':pa' => $a['portada_alt'], ':au' => $autor, ':cat' => $a['categoria'],
            ':pub' => date('Y-m-d H:i:s', strtotime('-' . $a['dias'] . ' days -2 hours')),
            ':dest' => $a['destacado'], ':mt' => $a['meta_title'], ':md' => $a['meta_description'], ':kw' => $a['keyword'],
            ':pal' => $palabras, ':lec' => max(1, (int) ceil($palabras / 200)),
        ]);
        $aid = (int) $pdo->lastInsertId();
        $stT = $pdo->prepare("INSERT IGNORE INTO blog_articulo_termino (articulo_id, termino_id) VALUES (:a, :t)");
        foreach (array_unique($a['terminos']) as $t) $stT->execute([':a' => $aid, ':t' => $t]);
        $stC = $pdo->prepare("INSERT IGNORE INTO blog_articulo_contexto (articulo_id, tipo, valor) VALUES (:a, :t, :v)");
        foreach ($a['contexto'] as [$tipo, $valor]) $stC->execute([':a' => $aid, ':t' => $tipo, ':v' => $valor]);
        $log("✓ Artículo #$aid: {$a['titulo']} ($palabras palabras)");
    }

    $pdo->commit();
    $log('Listo: categorías, etiquetas, autor, 5 módulos, 1 regla automática y 4 artículos de ejemplo.');
} catch (Throwable $e) {
    $pdo->rollBack();
    $log('ERROR: ' . $e->getMessage());
    exit(1);
}
