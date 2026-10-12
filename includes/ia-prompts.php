<?php
/**
 * Prompts de IA editables desde el admin (admin/prompts-ia.php, solo super_admin).
 *
 * Cada prompt tiene DOS partes:
 *   - Parte creativa: editable. Valor por defecto = texto de este catálogo; si el
 *     super_admin la edita, se guarda en ia_prompts (y cada guardado en ia_prompts_versiones).
 *   - Contrato técnico: fijo en el código, solo lectura en el panel. Fija el formato JSON
 *     de respuesta y las salvaguardas; el código depende de él, por eso NO es editable.
 *
 * "Restaurar original" = borrar la fila de ia_prompts (vuelve el texto de este catálogo).
 * Si la migración create_ia_prompts.sql aún no se corrió, todo funciona con los originales.
 *
 * Uso:  $prompt = iaPromptRender($pdo, 'blog_imagen_prompt', ['titulo' => …, …]);
 *       llamarLLM($pdo, 'blog_imagen_prompt', $prompt);
 * Proveedor/modelo de la tarea: admin/configuracion-ia.php (campo 'tarea' del catálogo).
 */

// Salvaguardas fijas de toda imagen generada con IA: nada de texto/marcas, ni negocios o
// personas reales, ni escenas morbosas. No editable.
if (!defined('BLOG_IA_REGLAS')) {
    define('BLOG_IA_REGLAS', 'No text, no letters, no words, no logos, no watermarks, no signage, no brand names. No recognizable real people, no real businesses or facilities. Nothing graphic, morbid or medical: no visible remains, no spilled ashes, no cremation equipment.');
}

/**
 * Catálogo de prompts. Por clave:
 *   nombre, grupo, tarea (clave en ia_config_secciones), que_hace, donde_se_ve,
 *   texto (original), evitar (original, un tema por línea; null si el prompt no lo usa),
 *   variables [nombre => [desc, obligatoria, bloque]], contrato (string fijo),
 *   contrato_nota (aclaración solo para el panel, opcional),
 *   union (separador antes del contrato), ejemplo (valores para la vista previa).
 */
function iaPromptsCatalogo(): array
{
    static $cat = null;
    if ($cat !== null) return $cat;

    $varsArticulo = [
        'titulo'   => ['desc' => 'Título del artículo',                        'obligatoria' => true,  'bloque' => false],
        'extracto' => ['desc' => 'Entradilla (resumen) del artículo',          'obligatoria' => false, 'bloque' => false],
        'keyword'  => ['desc' => 'Palabra clave principal',                    'obligatoria' => false, 'bloque' => false],
        'seccion'  => ['desc' => 'Sección concreta donde irá la imagen',       'obligatoria' => false, 'bloque' => false],
        'uso'      => ['desc' => 'Dónde se usa: portada o cuerpo del artículo', 'obligatoria' => false, 'bloque' => false],
    ];
    $ejemploArticulo = [
        'titulo'   => 'Cómo explicar a un niño que su perro ha muerto',
        'extracto' => 'Pautas sencillas para acompañar a los más pequeños en su primer duelo.',
        'keyword'  => 'duelo infantil mascota',
        'seccion'  => 'Qué decir y qué evitar',
        'uso'      => 'imagen dentro del cuerpo del artículo',
    ];

    $cat = [

        'blog_imagen_prompt' => [
            'nombre'      => 'Proponer imagen a partir del artículo',
            'grupo'       => 'Blog · imágenes',
            'tarea'       => 'blog_imagen_prompt',
            'que_hace'    => 'Lee los datos del artículo (título, entradilla, palabra clave y sección) y propone 3 búsquedas distintas para el banco de fotos y la descripción de una escena para generar la imagen con IA. Si faltan datos, lo avisa en lugar de inventar.',
            'donde_se_ve' => 'Blog → editar artículo → «Añadir imagen» (portada o bloque de imagen). En la pestaña «Banco de fotos» se rellena sola la 1.ª búsqueda y las otras salen como sugerencias debajo del buscador. En «Generar con IA» se rellena el campo «Escena a generar». Se lanza al abrir la pestaña y con el botón «Proponer a partir del artículo».',
            'texto'       => <<<'TXT'
Eres editor gráfico de un blog en España sobre cremación de mascotas y el duelo por su pérdida.
Tu trabajo: proponer la imagen ideal para este artículo ({uso}).

Lee el artículo y deduce de qué trata de verdad (el tema, la emoción, el momento del duelo o de la decisión en que está el lector). La imagen tiene que responder a ESE tema, no al tema genérico del blog.

Título: {titulo}
Entradilla: {extracto}
Palabra clave: {keyword}
Sección concreta donde irá: {seccion}

Criterios para las búsquedas de banco de fotos:
- Propón 3 búsquedas claramente DISTINTAS entre sí: cada una con un enfoque visual diferente (otro sujeto, otro lugar u otro plano). No repitas el mismo motivo con otras palabras.
- 2-4 palabras en inglés, concretas y visuales, como las escribiría alguien en Pexels.
- Ordénalas de la más a la menos adecuada al artículo.

Criterios para la escena de IA:
- Una sola escena concreta: sujeto (qué animal o persona y qué está haciendo), lugar y luz. Entre 40 y 70 palabras, en inglés.
- Cálida y respetuosa, sin dramatismo. Elige un detalle cotidiano y específico del artículo en lugar de un símbolo genérico del duelo.
- Evita los tópicos.

Temas a evitar (están sobreexplotados en este blog; no los uses salvo que el artículo trate justo de eso):
{evitar}
{ya_propuestas}
TXT,
            'evitar'      => "velas encendidas\nflores y ramos\nurnas y cajas conmemorativas\natardeceres en el campo\nmanos acariciando el pelaje\narcoíris y alas de ángel\nhuellas en la arena",
            'variables'   => $varsArticulo + [
                'evitar'        => ['desc' => 'La lista «Temas a evitar» de este prompt',                                   'obligatoria' => false, 'bloque' => true],
                'ya_propuestas' => ['desc' => 'Propuestas anteriores de este artículo (para que no se repita). Automático.', 'obligatoria' => false, 'bloque' => true],
            ],
            'contrato'    => <<<'TXT'
Devuelve SOLO un JSON válido con estas claves:
- "contexto_suficiente": true si los datos del artículo bastan para una propuesta fiable; false si has tenido que suponer (faltan entradilla o sección, el tema es ambiguo, o los datos parecen texto de relleno como "test", "lorem ipsum" o similares). No inventes el tema.
- "aviso": cadena vacía si contexto_suficiente es true; si es false, UNA frase en español de España que explique qué falta.
- "consultas": array de 3 cadenas con las búsquedas de banco de fotos (en inglés).
- "prompt": cadena con la escena para generar con IA (en inglés), sin texto, sin logotipos, sin personas reconocibles, sin nada morboso y sin mencionar el estilo artístico.
TXT,
            'union'       => "\n\n",
            'ejemplo'     => $ejemploArticulo + ['ya_propuestas' => ''],
        ],

        'blog_imagen_seo' => [
            'nombre'      => 'Texto SEO de la imagen (alt, nombre de archivo, título y pie)',
            'grupo'       => 'Blog · imágenes',
            'tarea'       => 'blog_imagen_seo',
            'que_hace'    => 'Mira la imagen elegida (del banco, generada con IA o subida) y escribe su texto alternativo (alt), nombre de archivo, título y pie de foto. En las portadas también marca qué zona no se debe recortar, y en las subidas detecta si ya lleva nuestra marca de agua.',
            'donde_se_ve' => 'Blog → editar artículo → al elegir o subir una imagen. Los textos aparecen en los campos Alt, Título y Pie de foto de esa imagen; el nombre de archivo se ve en la URL pública de la imagen.',
            'texto'       => <<<'TXT'
Eres un experto en SEO y accesibilidad para un blog sobre cremación de mascotas y duelo en España.
Mira la imagen y genera sus metadatos. Uso: {uso}.

Artículo: "{titulo}"
Entradilla: {extracto}
Palabra clave principal: {keyword}
Sección donde va la imagen: {seccion}
{alts_usados}
Criterios de redacción:
- alt: describe de forma concreta lo que SE VE en la imagen (no inventes nada que no aparezca) y conéctalo con el tema del artículo usando la palabra clave solo si encaja de forma natural. Español de España, sin "imagen de" ni "foto de" al inicio.
- titulo: título corto y descriptivo de la imagen.
- caption: pie de foto que aporte contexto útil al lector sobre el tema del artículo, en tono cálido y respetuoso. Déjalo vacío si no aporta nada.
TXT,
            'evitar'      => null,
            'variables'   => $varsArticulo + [
                'alts_usados' => ['desc' => 'Alt ya usados en el artículo (para no repetir). Automático.', 'obligatoria' => false, 'bloque' => true],
            ],
            'contrato'    => <<<'TXT'
Devuelve SOLO un JSON con estas claves:
- "alt": UNA sola frase completa de 60 a 120 caracteres.
- "slug": nombre de archivo de 3 a 6 palabras, minúsculas, separadas por guiones, sin acentos, sin artículos ni fechas. Ej.: "perro-mayor-jardin-atardecer".
- "titulo": título corto de la imagen (máx. 70 caracteres).
- "caption": pie de foto de UNA frase de 140 caracteres como máximo, o cadena vacía.
TXT,
            'contrato_nota' => 'El código añade además, según el caso: «foco» (en portadas, para el recorte 16:9) y «marca_previa» (en imágenes subidas, para detectar la marca de agua). Su formato también es fijo.',
            'union'       => "\n",
            'ejemplo'     => $ejemploArticulo + ['alts_usados' => ''],
        ],

        'blog_estilo_realista' => [
            'nombre'      => 'Estilo visual: fotografía realista cálida',
            'grupo'       => 'Blog · imágenes',
            'tarea'       => 'blog_imagen_generar',
            'que_hace'    => 'Frase de estilo que se añade a la escena cuando se genera una imagen con IA eligiendo «Fotografía realista cálida». Define el aspecto (luz, lente, ambiente).',
            'donde_se_ve' => 'Blog → editar artículo → «Añadir imagen» → «Generar con IA» → desplegable «Estilo». Se nota en el aspecto de la imagen generada.',
            'texto'       => 'Photorealistic editorial photograph, natural soft warm light, shallow depth of field, full-frame camera, 35mm lens, authentic and candid, calm and respectful mood.',
            'evitar'      => null,
            'variables'   => [],
            'contrato'    => BLOG_IA_REGLAS,
            'union'       => "\n",
            'ejemplo'     => [],
        ],

        'blog_estilo_acuarela' => [
            'nombre'      => 'Estilo visual: ilustración acuarela',
            'grupo'       => 'Blog · imágenes',
            'tarea'       => 'blog_imagen_generar',
            'que_hace'    => 'Frase de estilo que se añade a la escena cuando se genera una imagen con IA eligiendo «Ilustración acuarela».',
            'donde_se_ve' => 'Blog → editar artículo → «Añadir imagen» → «Generar con IA» → desplegable «Estilo». Se nota en el aspecto de la imagen generada.',
            'texto'       => 'Soft watercolor illustration, gentle warm pastel palette, delicate brush strokes, visible paper texture, comforting and tender.',
            'evitar'      => null,
            'variables'   => [],
            'contrato'    => BLOG_IA_REGLAS,
            'union'       => "\n",
            'ejemplo'     => [],
        ],

        'blog_estilo_minimalista' => [
            'nombre'      => 'Estilo visual: ilustración minimalista',
            'grupo'       => 'Blog · imágenes',
            'tarea'       => 'blog_imagen_generar',
            'que_hace'    => 'Frase de estilo que se añade a la escena cuando se genera una imagen con IA eligiendo «Ilustración minimalista».',
            'donde_se_ve' => 'Blog → editar artículo → «Añadir imagen» → «Generar con IA» → desplegable «Estilo». Se nota en el aspecto de la imagen generada.',
            'texto'       => 'Minimalist flat illustration, clean simple composition, soft muted palette, generous negative space, serene and calm.',
            'evitar'      => null,
            'variables'   => [],
            'contrato'    => BLOG_IA_REGLAS,
            'union'       => "\n",
            'ejemplo'     => [],
        ],
    ];

    return $cat;
}

/** Frase de «uso» de una imagen según sea portada o cuerpo. */
function iaPromptUsoFrase(string $uso): string
{
    return $uso === 'portada' ? 'imagen de portada (destacada) del artículo' : 'imagen dentro del cuerpo del artículo';
}

/** Fila guardada de un prompt (null si no hay o si la migración aún no se corrió). */
function iaPromptFila(PDO $pdo, string $clave): ?array
{
    static $cache = [];
    if (array_key_exists($clave, $cache)) return $cache[$clave];
    try {
        $st = $pdo->prepare('SELECT clave, texto, evitar, updated_at, updated_by FROM ia_prompts WHERE clave = :c LIMIT 1');
        $st->execute([':c' => $clave]);
        $fila = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $fila = null; // tabla inexistente → se usan los originales
    }
    return $cache[$clave] = $fila;
}

/** Texto vigente de la parte creativa (el guardado o, si no hay, el original). */
function iaPromptTexto(PDO $pdo, string $clave): string
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat) return '';
    $fila = iaPromptFila($pdo, $clave);
    return $fila ? (string) $fila['texto'] : $cat['texto'];
}

/** Lista «temas a evitar» vigente (texto, un tema por línea). */
function iaPromptEvitar(PDO $pdo, string $clave): string
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat || $cat['evitar'] === null) return '';
    $fila = iaPromptFila($pdo, $clave);
    return ($fila && $fila['evitar'] !== null) ? (string) $fila['evitar'] : $cat['evitar'];
}

/** Convierte «un tema por línea» en viñetas para el prompt. */
function iaPromptEvitarFormateado(string $lista): string
{
    $lineas = array_filter(array_map('trim', preg_split('/\R/u', $lista) ?: []), fn($l) => $l !== '');
    return $lineas ? '- ' . implode("\n- ", $lineas) : '(ninguno)';
}

/** Sustituye {variables} en un texto. Vacío en variables de «dato» → "(no indicado)". */
function iaPromptSustituir(string $texto, array $variablesCatalogo, array $vars): string
{
    $mapa = [];
    foreach ($variablesCatalogo as $nombre => $def) {
        $valor = trim((string) ($vars[$nombre] ?? ''));
        if ($valor === '' && empty($def['bloque'])) $valor = '(no indicado)';
        $mapa['{' . $nombre . '}'] = $valor;
    }
    return strtr($texto, $mapa);
}

/**
 * Prompt final listo para enviar: parte creativa (con variables) + contrato técnico fijo.
 * $vars: valores de las variables del catálogo (titulo, extracto, …; 'evitar' se rellena solo).
 */
function iaPromptRender(PDO $pdo, string $clave, array $vars = []): string
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat) return '';
    if (isset($cat['variables']['evitar'])) {
        $vars['evitar'] = iaPromptEvitarFormateado(iaPromptEvitar($pdo, $clave));
    }
    $texto = iaPromptSustituir(iaPromptTexto($pdo, $clave), $cat['variables'], $vars);
    $texto = rtrim($texto);
    return $cat['contrato'] !== '' ? $texto . ($cat['union'] ?? "\n\n") . $cat['contrato'] : $texto;
}

/** Errores de validación antes de guardar (p. ej. falta una variable obligatoria). */
function iaPromptValidar(string $clave, string $texto): array
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat) return ['Prompt desconocido.'];
    $errores = [];
    if (trim($texto) === '') $errores[] = 'El prompt no puede quedar vacío.';
    foreach ($cat['variables'] as $nombre => $def) {
        if (!empty($def['obligatoria']) && !str_contains($texto, '{' . $nombre . '}')) {
            $errores[] = 'Falta la variable {' . $nombre . '}: sin ella la IA no recibe ' . mb_strtolower($def['desc']) . '.';
        }
    }
    return $errores;
}

/** Guarda la versión editada y la registra en el historial. */
function iaPromptGuardar(PDO $pdo, string $clave, string $texto, ?string $evitar, ?int $adminId, string $nota = 'Guardado'): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO ia_prompts (clave, texto, evitar, updated_by) VALUES (:c, :t, :e, :a)
                       ON DUPLICATE KEY UPDATE texto = VALUES(texto), evitar = VALUES(evitar), updated_by = VALUES(updated_by)')
            ->execute([':c' => $clave, ':t' => $texto, ':e' => $evitar, ':a' => $adminId]);
        $pdo->prepare('INSERT INTO ia_prompts_versiones (clave, texto, evitar, nota, creado_por) VALUES (:c, :t, :e, :n, :a)')
            ->execute([':c' => $clave, ':t' => $texto, ':e' => $evitar, ':n' => $nota, ':a' => $adminId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Vuelve al texto original del código: borra la fila y deja constancia en el historial. */
function iaPromptRestaurar(PDO $pdo, string $clave, ?int $adminId): void
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat) return;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM ia_prompts WHERE clave = :c')->execute([':c' => $clave]);
        $pdo->prepare('INSERT INTO ia_prompts_versiones (clave, texto, evitar, nota, creado_por) VALUES (:c, :t, :e, :n, :a)')
            ->execute([':c' => $clave, ':t' => $cat['texto'], ':e' => $cat['evitar'], ':n' => 'Restaurado al original', ':a' => $adminId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Últimas versiones de un prompt (más recientes primero). */
function iaPromptVersiones(PDO $pdo, string $clave, int $limite = 15): array
{
    try {
        $st = $pdo->prepare('SELECT v.id, v.nota, v.creado_en, a.nombre AS autor
                             FROM ia_prompts_versiones v LEFT JOIN admins a ON a.id = v.creado_por
                             WHERE v.clave = :c ORDER BY v.id DESC LIMIT ' . (int) $limite);
        $st->execute([':c' => $clave]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Prompt final con un texto y una lista de «evitar» DADOS (sin guardar), para el probador.
 * Misma construcción que iaPromptRender(), pero sin leer la BD.
 */
function iaPromptRenderTexto(string $clave, string $texto, string $evitar, array $vars = []): string
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat) return '';
    if (isset($cat['variables']['evitar'])) $vars['evitar'] = iaPromptEvitarFormateado($evitar);
    $texto = rtrim(iaPromptSustituir($texto, $cat['variables'], $vars));
    return $cat['contrato'] !== '' ? $texto . ($cat['union'] ?? "\n\n") . $cat['contrato'] : $texto;
}

/**
 * ¿Se puede probar este prompt desde el panel? Solo tareas de texto con datos de ejemplo
 * (las de visión necesitan una imagen y los estilos solo se usan al generar una imagen).
 */
function iaPromptProbable(PDO $pdo, string $clave): bool
{
    $cat = iaPromptsCatalogo()[$clave] ?? null;
    if (!$cat || !$cat['ejemplo']) return false;
    try {
        $st = $pdo->prepare('SELECT tipo FROM ia_config_secciones WHERE seccion = :s LIMIT 1');
        $st->execute([':s' => $cat['tarea']]);
        return $st->fetchColumn() === 'texto';
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Meta-prompt de «Mejorar con IA»: pide una versión mejorada de la parte editable.
 * Solo se le envía la parte creativa: el contrato técnico y el formato JSON no entran.
 */
function iaPromptMejorarMeta(string $clave, string $texto, string $instruccion): string
{
    $cat = iaPromptsCatalogo()[$clave];
    $vars = [];
    foreach ($cat['variables'] as $nombre => $def) {
        $vars[] = '- {' . $nombre . '}: ' . $def['desc'] . (!empty($def['obligatoria']) ? ' (OBLIGATORIA)' : '');
    }
    $instruccion = trim($instruccion);
    return "Eres un experto en ingeniería de prompts. Mejora el siguiente prompt, que se usa en un directorio web de crematorios de mascotas.\n\n"
        . 'QUÉ HACE ESTE PROMPT: ' . $cat['que_hace'] . "\n\n"
        . "VARIABLES (se sustituyen por datos reales al usarlo; consérvalas EXACTAMENTE con sus llaves, sin renombrarlas):\n"
        . ($vars ? implode("\n", $vars) : '(ninguna)') . "\n\n"
        . "REGLAS:\n"
        . "- Mantén el idioma (español de España), el propósito y el tono de duelo respetuoso.\n"
        . "- Conserva todas las variables obligatorias; no inventes variables nuevas.\n"
        . "- No añadas el formato de respuesta ni instrucciones de JSON: ya se añaden aparte.\n"
        . "- Hazlo más claro, concreto y robusto; no lo alargues sin necesidad.\n"
        . "- No inventes datos ni ejemplos que sesguen las respuestas.\n"
        . ($instruccion !== '' ? "\nINSTRUCCIÓN DEL EDITOR (prioritaria): " . mb_substr($instruccion, 0, 500) . "\n" : '')
        . "\nPROMPT ACTUAL:\n<<<\n" . $texto . "\n>>>\n\n"
        . "Responde SOLO con un JSON válido, sin texto antes ni después:\n"
        . '{"texto": "el prompt mejorado completo", "resumen": "2-4 frases en español que explican qué cambiaste y por qué"}';
}
