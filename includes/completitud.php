<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  COMPLETITUD DE FICHA — definición declarativa única
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Una sola fuente de verdad para:
 *   - qué se mide en la completitud de una ficha (id + label + grupo),
 *   - el % general (para el panel lateral),
 *   - el % de "datos base" (grupo input) que habilita la generación de la
 *     DESCRIPCIÓN AVANZADA con IA (gate ≥ 90 %).
 *
 *  Por qué dos %:
 *   - `pct`     → todos los checks (lo que ve el admin en el panel).
 *   - `pct_ia`  → SOLO el grupo 'input' (datos crudos). La Descripción y la
 *     Meta SEO son 'output' (las genera/asiste la IA) y NO cuentan para el
 *     gate: incluirlas sería circular (nunca llegarías a 90 % sin lo mismo
 *     que querés generar).
 *
 *  Pensado para volverse config-por-rubro más adelante (multi-rubro): hoy la
 *  definición vive acá centralizada; el día que haya config del directorio,
 *  `definicionCompletitud()` leerá de ahí sin tocar los consumidores.
 *
 *  Naming agnóstico de rubro a propósito (plataforma reutilizable).
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Definición declarativa de los checks, EN ORDEN de presentación.
 * grupo:
 *   'input'  → dato crudo que carga el negocio/admin. Cuenta para el gate IA.
 *   'output' → contenido que genera/asiste la IA (descripción, meta SEO).
 *              NO cuenta para el gate IA.
 *
 * @return array<int,array{id:string,label:string,grupo:string}>
 */
function definicionCompletitud(): array {
    return [
        ['id' => 'telefono',    'label' => 'Teléfono',           'grupo' => 'input'],
        ['id' => 'email',       'label' => 'Email',              'grupo' => 'input'],
        ['id' => 'website',     'label' => 'Sitio web',          'grupo' => 'input'],
        ['id' => 'direccion',   'label' => 'Dirección',          'grupo' => 'input'],
        ['id' => 'coordenadas', 'label' => 'Coordenadas GPS',    'grupo' => 'input'],
        ['id' => 'descripcion', 'label' => 'Descripción (≥150)', 'grupo' => 'output'],
        ['id' => 'horarios',    'label' => 'Horarios',           'grupo' => 'input'],
        ['id' => 'zona',        'label' => 'Zona cobertura',     'grupo' => 'input'],
        ['id' => 'servicios',   'label' => 'Servicios definidos','grupo' => 'input'],
        ['id' => 'meta_seo',    'label' => 'Meta SEO',           'grupo' => 'output'],
        ['id' => 'imagenes',    'label' => 'Imágenes',           'grupo' => 'input'],
        ['id' => 'logo',        'label' => 'Logo',               'grupo' => 'input'],
    ];
}

/**
 * Flags de imágenes a partir del listado de imágenes de una ficha.
 * Lógica única (la usan tanto editar-ficha como el gate server-side) para
 * que front y servidor midan EXACTAMENTE igual.
 *
 * @param array $imagenes filas de crematorio_imagenes
 * @return array{img:bool,logo:bool,portada:bool}
 */
function flagsImagenesFicha(array $imagenes): array {
    return [
        'img'     => (bool) array_filter($imagenes, fn($i) => ($i['estado_llm'] ?? '') === 'procesada'),
        'logo'    => (bool) array_filter($imagenes, fn($i) => ($i['tipo'] ?? '') === 'logo' && ($i['categoria'] ?? '') === 'logo'),
        'portada' => (bool) array_filter($imagenes, fn($i) => ($i['tipo'] ?? '') === 'foto' && ($i['estado_llm'] ?? '') === 'procesada'),
    ];
}

/**
 * Evalúa cada check contra los datos de la ficha.
 * Devuelve el MISMO shape que el antiguo calcularCompletitud()
 * (claves id => bool) para no romper las vistas que leen $checks['x'].
 *
 * @return array<string,bool>
 */
function evaluarCompletitud(array $c, bool $tieneImg, bool $tieneLogo): array {
    return [
        'telefono'    => !empty($c['telefono']) || !empty($c['telefono_clientes']),
        'email'       => !empty($c['email'])    || !empty($c['email_clientes']),
        'website'     => !empty($c['website']),
        'direccion'   => !empty($c['direccion_completa']),
        'coordenadas' => !empty($c['latitud'])  && !empty($c['longitud']),
        'descripcion' => !empty($c['descripcion']) && mb_strlen($c['descripcion']) >= 150,
        'horarios'    => !empty($c['horarios']),
        'zona'        => !empty($c['zona_cobertura']),
        'servicios'   => (($c['cremacion_individual'] ?? null) !== null || ($c['cremacion_colectiva'] ?? null) !== null
                          || ($c['recogida_domicilio'] ?? null) !== null || ($c['entrega_domicilio'] ?? null) !== null),
        'meta_seo'    => !empty($c['meta_description_seo']),
        'imagenes'    => $tieneImg,
        'logo'        => $tieneLogo,
    ];
}

/**
 * Resumen calculado a partir de $checks (salida de evaluarCompletitud).
 *
 * @param array<string,bool> $checks
 * @return array{
 *   completados:int, total:int, pct:int,
 *   completados_ia:int, total_ia:int, pct_ia:int,
 *   faltan:string[], faltan_ia:string[],
 *   labels:array<string,string>, grupos:array<string,string>
 * }
 */
function resumenCompletitud(array $checks): array {
    $def     = definicionCompletitud();
    $labels  = [];
    $grupos  = [];
    foreach ($def as $d) { $labels[$d['id']] = $d['label']; $grupos[$d['id']] = $d['grupo']; }

    $total = $completados = 0;
    $totalIa = $completadosIa = 0;
    $faltan = $faltanIa = [];

    foreach ($def as $d) {
        $id = $d['id'];
        $ok = !empty($checks[$id]);
        $esInput = $d['grupo'] === 'input';

        $total++;
        if ($ok) $completados++; else $faltan[] = $d['label'];

        if ($esInput) {
            $totalIa++;
            if ($ok) $completadosIa++; else $faltanIa[] = $d['label'];
        }
    }

    return [
        'completados'    => $completados,
        'total'          => $total,
        'pct'            => $total   ? (int) round($completados   / $total   * 100) : 0,
        'completados_ia' => $completadosIa,
        'total_ia'       => $totalIa,
        'pct_ia'         => $totalIa ? (int) round($completadosIa / $totalIa * 100) : 0,
        'faltan'         => $faltan,
        'faltan_ia'      => $faltanIa,
        'labels'         => $labels,
        'grupos'         => $grupos,
    ];
}

/**
 * Completitud autocontenida a partir de un id de ficha: carga la fila y sus
 * imágenes con la MISMA lógica que editar-ficha y devuelve checks + resumen.
 * Pensada para el gate server-side (AJAX) — no se puede saltar por URL.
 *
 * @return array{checks:array<string,bool>}&array<string,mixed>  resumen + 'checks'
 *         o null si la ficha no existe.
 */
function completitudDesdeId(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM crematorios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $cr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cr) return null;

    $imgStmt = $pdo->prepare("SELECT tipo, categoria, estado_llm FROM crematorio_imagenes WHERE crematorio_id = :id");
    $imgStmt->execute([':id' => $id]);
    $flags = flagsImagenesFicha($imgStmt->fetchAll(PDO::FETCH_ASSOC));

    $checks  = evaluarCompletitud($cr, $flags['img'], $flags['logo']);
    $resumen = resumenCompletitud($checks);
    $resumen['checks'] = $checks;
    return $resumen;
}
