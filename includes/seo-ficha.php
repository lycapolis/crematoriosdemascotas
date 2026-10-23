<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SEO DE LA FICHA — regla única de título y descripción (lo que se ve en Google)
 * ═══════════════════════════════════════════════════════════
 *
 * Funciones puras (sin base de datos): reciben datos simples y devuelven texto. Las usan la ficha pública,
 * la maqueta de Google de verificar-ficha.php y la del editor del admin, para que la regla no se duplique.
 *
 * Título (~60 caracteres):  «{Nombre} – Crematorio de mascotas en {Ciudad}»
 *   - Si el nombre ya contiene «crematorio»: «{Nombre} en {Ciudad}».
 *   - Sin la marca del portal al final (gasta caracteres).
 *
 * Descripción (~155 caracteres), por prioridad:
 *   1. La meta curada (meta_description_seo), tal cual.
 *   2. Una frase armada con datos reales: tipo y ciudad, servicios clave, zona que cubre y horario de urgencias.
 *      No inventa: si falta un dato, esa parte no aparece. Sin valoración, teléfono, precios ni superlativos.
 *   3. El comienzo de la descripción del negocio, recortado en límite de palabra.
 * ═══════════════════════════════════════════════════════════
 */

if (!defined('SEO_FICHA_TITULO_MAX')) define('SEO_FICHA_TITULO_MAX', 60);
if (!defined('SEO_FICHA_DESC_MAX'))   define('SEO_FICHA_DESC_MAX', 155);

if (!function_exists('seoFichaTitulo')) {

    /** Minúsculas sin tildes, para comparar textos. */
    function seoFichaNorm(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }

    /** «a, b, c» (o saltos de línea / «|») → ['a','b','c'] sin vacíos ni repetidos. */
    function seoFichaLista(string $s): array
    {
        $out = [];
        foreach (preg_split('/[,;|\n]+/u', $s) ?: [] as $p) {
            $p = trim(preg_replace('/\s+/u', ' ', $p));
            if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
        }
        return $out;
    }

    /** Recorta en límite de palabra (con «…» si recorta). */
    function seoFichaRecortar(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) return $s;
        $corte = mb_substr($s, 0, $max - 1);
        $sp = mb_strrpos($corte, ' ');
        if ($sp !== false && $sp > $max * 0.6) $corte = mb_substr($corte, 0, $sp);
        return rtrim($corte, " ,;:.–-") . '…';
    }

    function seoFichaTitulo(string $nombre, string $ciudad): string
    {
        $nombre = trim($nombre);
        $ciudad = trim($ciudad);
        $tieneTipo = strpos(seoFichaNorm($nombre), 'crematorio') !== false;
        $t = $tieneTipo ? $nombre : $nombre . ' – Crematorio de mascotas';
        // Si el nombre ya trae la ciudad, no se repite
        if ($ciudad !== '' && strpos(seoFichaNorm($nombre), seoFichaNorm($ciudad)) === false) $t .= ' en ' . $ciudad;
        return $t;
    }

    /** «a», «a y b», «a, b y c». */
    function seoFichaUnir(array $items): string
    {
        $items = array_values($items);
        $n = count($items);
        if ($n <= 1) return $items[0] ?? '';
        return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$n - 1];
    }

    /** Servicios clave que ayudan a decidir, en este orden, a partir de las etiquetas de la ficha. */
    function seoFichaServiciosClave(array $servicios): array
    {
        $todo = seoFichaNorm(implode(' | ', $servicios));
        $out = [];
        if (strpos($todo, 'recogida') !== false && strpos($todo, 'domicilio') !== false) $out[] = 'recogida a domicilio';
        if (preg_match('/\b24\s*(h|horas)\b/', $todo)) $out[] = 'atención 24 h';
        if (strpos($todo, 'cremacion individual') !== false) $out[] = 'cremación individual';
        return $out;
    }

    /**
     * @param array{meta?:string,ciudad?:string,servicios?:array,cobertura?:array,horarios?:array,descripcion?:string} $d
     */
    function seoFichaDescripcion(array $d): string
    {
        $max = SEO_FICHA_DESC_MAX;

        // 1. Meta curada
        $meta = trim((string)($d['meta'] ?? ''));
        if ($meta !== '') return $meta;

        // 2. Frase con datos reales
        $ciudad = trim((string)($d['ciudad'] ?? ''));
        $base   = 'Crematorio de mascotas' . ($ciudad !== '' ? ' en ' . $ciudad : '');
        $clave  = seoFichaServiciosClave((array)($d['servicios'] ?? []));

        $zonas = [];
        foreach ((array)($d['cobertura'] ?? []) as $z) {
            $z = trim((string)$z);
            if ($z !== '' && seoFichaNorm($z) !== seoFichaNorm($ciudad) && !in_array($z, $zonas, true)) $zonas[] = $z;
        }
        $zonas = array_slice($zonas, 0, 2);

        $urgencias = '';
        if (!in_array('atención 24 h', $clave, true)) {
            foreach ((array)($d['horarios'] ?? []) as $h) {
                $h = trim((string)$h);
                if ($h !== '' && strpos(seoFichaNorm($h), 'urgencia') !== false && mb_strlen($h) <= 40) { $urgencias = $h; break; }
            }
        }

        $frases = [];
        if ($zonas)     $frases[] = 'Atiende en ' . seoFichaUnir($zonas) . '.';
        if ($urgencias) $frases[] = rtrim($urgencias, '.') . '.';

        if ($clave || $frases) {
            $txt = $base . ($clave ? ': ' . seoFichaUnir($clave) : '') . '.';
            foreach ($frases as $f) {
                if (mb_strlen($txt . ' ' . $f) <= $max) $txt .= ' ' . $f;
            }
            if (mb_strlen($txt) <= $max) return $txt;
            // Demasiado largo: quita servicios de uno en uno antes de recortar
            while (count($clave) > 1) {
                array_pop($clave);
                $txt = $base . ': ' . seoFichaUnir($clave) . '.';
                if (mb_strlen($txt) <= $max) return $txt;
            }
            return seoFichaRecortar($txt, $max);
        }

        // 3. Comienzo de la descripción del negocio
        $desc = trim(preg_replace('/\s+/u', ' ', str_replace('**', '', strip_tags((string)($d['descripcion'] ?? '')))));
        if ($desc !== '') return seoFichaRecortar($desc, $max);

        // Solo se conoce la ciudad: la frase base es verdadera y no inventa nada
        return $ciudad !== '' ? $base . '.' : '';
    }

    /** Servicios de la fila de `crematorios` como etiquetas (casillas verdaderas; si no hay, el texto antiguo). */
    function seoFichaServiciosDeFila(array $f): array
    {
        $out = [];
        foreach ([
            'atencion_24h' => 'Atención 24 horas', 'recogida_domicilio' => 'Recogida a domicilio', 'entrega_domicilio' => 'Entrega a domicilio',
            'cremacion_individual' => 'Cremación individual', 'cremacion_colectiva' => 'Cremación colectiva', 'sala_velatoria' => 'Sala velatoria',
        ] as $col => $etq) {
            if (!empty($f[$col])) $out[] = $etq;
        }
        return $out ?: seoFichaLista((string)($f['servicios'] ?? ''));
    }

    /** Datos que usa la regla, sacados de una fila de `crematorios`. */
    function seoFichaDatosDeFila(array $f): array
    {
        return [
            'meta'        => (string)($f['meta_description_seo'] ?? ''),
            'ciudad'      => (string)($f['ciudad'] ?? ''),
            'servicios'   => seoFichaServiciosDeFila($f),
            'cobertura'   => array_merge(seoFichaLista((string)($f['zona_cobertura'] ?? '')), seoFichaLista((string)($f['ciudades_cobertura'] ?? ''))),
            'horarios'    => seoFichaLista((string)($f['horario_texto'] ?? '')),
            'descripcion' => (string)($f['descripcion'] ?? ''),
        ];
    }
}
