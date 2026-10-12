<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MOTOR DE INSIGNIAS + PUNTAJE DE RANKING
 * ═══════════════════════════════════════════════════════════
 *
 * Catálogo en BD (`insignias`), asignaciones en `insignias_ficha`.
 *   - Automáticas: las evalúa recalcularInsignias() según la `regla` JSON del catálogo.
 *   - Manuales: las otorga el equipo (insigniaFijarManual()); el motor no las toca.
 *
 * Tras cada recálculo se sincronizan dos columnas de `crematorios` que el resto del
 * sitio ya lee: `verificado` (= tiene «Verificado por humano») y `destacado`
 * (= tiene «Destacado» o «Promocionado»), más `puntaje_ranking`.
 *
 * Tipos de regla soportados (ver migración create_insignias.sql):
 *   columna · columna_o · completitud · rating · popularidad · resena_reciente   (+ "requiere": [slugs])
 */

require_once __DIR__ . '/completitud.php';

// ─── Catálogo ───────────────────────────────────────────────────────────────

/**
 * Catálogo de insignias ordenado por prioridad de aparición.
 * @return array<int,array<string,mixed>> (con 'niveles' y 'regla' ya decodificados)
 */
function insigniasCatalogo(PDO $pdo, bool $soloActivas = true): array
{
    $sql = "SELECT * FROM insignias" . ($soloActivas ? " WHERE activa = 1" : "") . " ORDER BY orden, id";
    $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as &$f) {
        $f['regla_arr']   = $f['regla']   ? (json_decode($f['regla'], true)   ?: []) : [];
        $f['niveles_arr'] = $f['niveles'] ? (json_decode($f['niveles'], true) ?: []) : [];
    }
    return $filas;
}

/**
 * Insignias que tiene una ficha, listas para pintar (nombre/tooltip ya resueltos por nivel).
 *
 * @param string $donde 'tarjeta' | 'ficha' | 'todas'
 * @return array<int,array{slug:string,nombre:string,tooltip:string,icono:string,color:string,nivel:int,fuente:string}>
 */
function insigniasDeFicha(PDO $pdo, int $crematorioId, string $donde = 'todas'): array
{
    return insigniasDeFichas($pdo, [$crematorioId], $donde)[$crematorioId] ?? [];
}

/**
 * Versión por lotes (una consulta para todas las tarjetas de un listado).
 * @param int[] $ids
 * @return array<int,array<int,array<string,mixed>>> crematorio_id => insignias
 */
function insigniasDeFichas(PDO $pdo, array $ids, string $donde = 'todas'): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $filtro = $donde === 'tarjeta' ? ' AND i.visible_tarjeta = 1' : ($donde === 'ficha' ? ' AND i.visible_ficha = 1' : '');
    $st = $pdo->prepare(
        "SELECT f.crematorio_id, f.nivel, f.fuente, i.slug, i.nombre, i.tooltip, i.icono, i.color, i.niveles
           FROM insignias_ficha f
           JOIN insignias i ON i.id = f.insignia_id AND i.activa = 1
          WHERE f.crematorio_id IN ($marcas) $filtro
          ORDER BY i.orden, i.id"
    );
    $st->execute($ids);

    $res = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nombre  = $r['nombre'];
        $tooltip = $r['tooltip'];
        if ($r['niveles']) {
            foreach (json_decode($r['niveles'], true) ?: [] as $n) {
                if ((int) ($n['nivel'] ?? 0) === (int) $r['nivel']) {
                    $nombre  = $n['nombre']  ?? $nombre;
                    $tooltip = $n['tooltip'] ?? $tooltip;
                }
            }
        }
        $res[(int) $r['crematorio_id']][] = [
            'slug'    => $r['slug'],
            'nombre'  => $nombre,
            'tooltip' => $tooltip,
            'icono'   => $r['icono'],
            'color'   => $r['color'],
            'nivel'   => (int) $r['nivel'],
            'fuente'  => $r['fuente'],
        ];
    }
    return $res;
}

/**
 * Convierte «slug => nivel» (salida de insigniasCalcularFicha) en la lista pintable, con el mismo
 * formato y orden que insigniasDeFichas(). Para el laboratorio (sin BD de asignaciones).
 *
 * @param array<int,array<string,mixed>> $catalogo salida de insigniasCatalogo()
 * @param array<string,int> $tiene
 */
function insigniasPintables(array $catalogo, array $tiene, string $donde = 'todas'): array
{
    $res = [];
    foreach ($catalogo as $i) { // el catálogo ya viene ordenado por prioridad
        if (!isset($tiene[$i['slug']])) continue;
        if ($donde === 'tarjeta' && !$i['visible_tarjeta']) continue;
        if ($donde === 'ficha' && !$i['visible_ficha']) continue;
        $nivel = (int) $tiene[$i['slug']];
        $nombre = $i['nombre'];
        $tooltip = $i['tooltip'];
        foreach ($i['niveles_arr'] as $n) {
            if ((int) ($n['nivel'] ?? 0) === $nivel) {
                $nombre  = $n['nombre']  ?? $nombre;
                $tooltip = $n['tooltip'] ?? $tooltip;
            }
        }
        $res[] = ['slug' => $i['slug'], 'nombre' => $nombre, 'tooltip' => $tooltip, 'icono' => $i['icono'],
                  'color' => $i['color'], 'nivel' => $nivel, 'fuente' => $i['tipo']];
    }
    return $res;
}

// ─── Manuales ───────────────────────────────────────────────────────────────

/**
 * Otorga o quita una insignia MANUAL y recalcula esa ficha.
 */
function insigniaFijarManual(PDO $pdo, int $crematorioId, string $slug, bool $activa, ?int $adminId = null): void
{
    $st = $pdo->prepare("SELECT id FROM insignias WHERE slug = :s AND tipo = 'manual'");
    $st->execute([':s' => $slug]);
    $insId = $st->fetchColumn();
    if (!$insId) return;

    if ($activa) {
        $pdo->prepare(
            "INSERT INTO insignias_ficha (crematorio_id, insignia_id, fuente, otorgada_por)
             VALUES (:c, :i, 'manual', :a)
             ON DUPLICATE KEY UPDATE fuente = 'manual'"
        )->execute([':c' => $crematorioId, ':i' => $insId, ':a' => $adminId]);
    } else {
        $pdo->prepare("DELETE FROM insignias_ficha WHERE crematorio_id = :c AND insignia_id = :i AND fuente = 'manual'")
            ->execute([':c' => $crematorioId, ':i' => $insId]);
    }
}

// ─── Cálculo ────────────────────────────────────────────────────────────────

/**
 * Rating combinado: Google (cache en crematorios) + reseñas propias aprobadas del portal.
 * Las filas de `resenas` con fuente google/trustindex se ignoran: ya están en el cache de Google.
 *
 * @return array{rating:float,total:int}
 */
function insigniaRatingCombinado(array $cr, array $portal): array
{
    $gN = (int) ($cr['reviews_total'] ?? 0);
    $gR = (float) ($cr['rating'] ?? 0);
    $pN = (int) ($portal['n'] ?? 0);
    $pS = (float) ($portal['suma'] ?? 0);

    $total = $gN + $pN;
    if ($total === 0) return ['rating' => 0.0, 'total' => 0];
    return ['rating' => ($gR * $gN + $pS) / $total, 'total' => $total];
}

/**
 * Recalcula las insignias automáticas, el puntaje y las columnas cache.
 *
 * @param int|null $crematorioId una ficha, o null para todas
 * @return array{fichas:int,asignadas:int,quitadas:int}
 */
function recalcularInsignias(PDO $pdo, ?int $crematorioId = null): array
{
    $catalogo = insigniasCatalogo($pdo, true);

    // ── Datos base ──
    $where = $crematorioId !== null ? 'WHERE id = ' . (int) $crematorioId : '';
    $fichas = [];
    foreach ($pdo->query("SELECT * FROM crematorios $where")->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $fichas[(int) $f['id']] = $f;
    }
    if (!$fichas) return ['fichas' => 0, 'asignadas' => 0, 'quitadas' => 0];
    $ids = array_keys($fichas);
    $in  = implode(',', $ids);

    // Imágenes (para completitud)
    $imgs = [];
    foreach ($pdo->query("SELECT crematorio_id, tipo, categoria, estado_llm FROM crematorio_imagenes WHERE crematorio_id IN ($in)") as $r) {
        $imgs[(int) $r['crematorio_id']][] = $r;
    }

    // Reseñas propias del portal
    $portal = [];
    foreach ($pdo->query("SELECT crematorio_id, COUNT(*) n, SUM(calificacion) suma FROM resenas
                           WHERE estado = 'aprobada' AND es_spam = 0 AND fuente = 'propio' AND crematorio_id IN ($in)
                           GROUP BY crematorio_id") as $r) {
        $portal[(int) $r['crematorio_id']] = ['n' => (int) $r['n'], 'suma' => (float) $r['suma']];
    }

    // Insignias actuales
    $actuales = []; // [crem][insignia_id] => [fuente, nivel]
    foreach ($pdo->query("SELECT crematorio_id, insignia_id, fuente, nivel FROM insignias_ficha WHERE crematorio_id IN ($in)") as $r) {
        $actuales[(int) $r['crematorio_id']][(int) $r['insignia_id']] = ['fuente' => $r['fuente'], 'nivel' => (int) $r['nivel']];
    }

    $porSlug = [];
    foreach ($catalogo as $i) $porSlug[$i['slug']] = $i;

    // Contactos recientes por ficha (para popularidad). Una sola consulta por ventana distinta.
    $contactosPorVentana = [];
    foreach ($catalogo as $i) {
        if (($i['regla_arr']['tipo'] ?? '') !== 'popularidad') continue;
        $dias = max(1, (int) ($i['regla_arr']['dias'] ?? 90));
        if (isset($contactosPorVentana[$dias])) continue;
        $mapa = [];
        // Clics de salida (excluye los cancelados) + leads que no vengan de un clic ya contado
        $sql = "SELECT crematorio_id, COUNT(*) n FROM (
                    SELECT crematorio_id FROM outbound_clicks
                     WHERE crematorio_id IS NOT NULL AND modal_action <> 'cancelled'
                       AND created_at >= (NOW() - INTERVAL $dias DAY)
                    UNION ALL
                    SELECT crematorio_id FROM leads_b2c
                     WHERE crematorio_id IS NOT NULL
                       AND created_at >= (NOW() - INTERVAL $dias DAY)
                       AND id NOT IN (SELECT lead_b2c_id FROM outbound_clicks WHERE lead_b2c_id IS NOT NULL)
                ) t GROUP BY crematorio_id";
        foreach ($pdo->query($sql) as $r) $mapa[(int) $r['crematorio_id']] = (int) $r['n'];
        $contactosPorVentana[$dias] = $mapa;
    }

    // Reseñas recientes del portal (regla `resena_reciente`): crematorio_id => nº de reseñas en la ventana
    $recientesPorVentana = [];
    foreach ($catalogo as $i) {
        if (($i['regla_arr']['tipo'] ?? '') !== 'resena_reciente') continue;
        $dias = max(1, (int) ($i['regla_arr']['dias'] ?? 30));
        $min  = max(1, min(5, (int) ($i['regla_arr']['min_calificacion'] ?? 4)));
        $clave = $dias . '-' . $min;
        if (isset($recientesPorVentana[$clave])) continue;
        $mapa = [];
        $sql = "SELECT crematorio_id, COUNT(*) n FROM resenas
                 WHERE estado = 'aprobada' AND es_spam = 0 AND fuente = 'propio'
                   AND calificacion >= $min
                   AND COALESCE(moderado_en, created_at) >= (NOW() - INTERVAL $dias DAY)
                   AND crematorio_id IN ($in)
                 GROUP BY crematorio_id";
        foreach ($pdo->query($sql) as $r) $mapa[(int) $r['crematorio_id']] = (int) $r['n'];
        $recientesPorVentana[$clave] = $mapa;
    }

    // Percentil de popularidad: se calcula sobre TODAS las fichas activas de la provincia,
    // aunque se recalcule una sola (si no, el ranking dependería de qué se recalculó).
    $provCtx = [];
    foreach ($contactosPorVentana as $dias => $mapa) {
        $prov = [];
        foreach ($pdo->query("SELECT id, provincia_id FROM crematorios WHERE estado = 'activa'") as $r) {
            $prov[(int) $r['provincia_id']][(int) $r['id']] = $mapa[(int) $r['id']] ?? 0;
        }
        $provCtx[$dias] = $prov;
    }

    $asignadas = $quitadas = 0;

    foreach ($fichas as $id => $cr) {
        // Completitud (misma lógica que editar-ficha)
        $flags = flagsImagenesFicha($imgs[$id] ?? []);
        $pct   = resumenCompletitud(evaluarCompletitud($cr, $flags['img'], $flags['logo']))['pct'];
        $rating = insigniaRatingCombinado($cr, $portal[$id] ?? []);

        // Manuales vigentes (por slug)
        $manuales = []; // slug => nivel
        foreach ($catalogo as $i) {
            $a = $actuales[$id][(int) $i['id']] ?? null;
            if ($a && $a['fuente'] === 'manual') $manuales[$i['slug']] = max(1, $a['nivel']);
        }

        $calc  = insigniasCalcularFicha($catalogo, $cr, $id, $pct, $rating, $manuales, $provCtx, $recientesPorVentana);
        $tiene = $calc['tiene'];

        // ── Persistir diferencias de las automáticas ──
        foreach ($catalogo as $i) {
            $insId  = (int) $i['id'];
            $previo = $actuales[$id][$insId] ?? null;
            if ($i['tipo'] === 'manual') continue;

            if (isset($tiene[$i['slug']])) {
                $nivel = $tiene[$i['slug']];
                if (!$previo || $previo['nivel'] !== $nivel) {
                    $pdo->prepare("INSERT INTO insignias_ficha (crematorio_id, insignia_id, fuente, nivel)
                                   VALUES (:c, :i, 'auto', :n)
                                   ON DUPLICATE KEY UPDATE nivel = VALUES(nivel), fuente = 'auto'")
                        ->execute([':c' => $id, ':i' => $insId, ':n' => $nivel]);
                    if (!$previo) $asignadas++;
                }
            } elseif ($previo && $previo['fuente'] === 'auto') {
                $pdo->prepare("DELETE FROM insignias_ficha WHERE crematorio_id = :c AND insignia_id = :i")
                    ->execute([':c' => $id, ':i' => $insId]);
                $quitadas++;
            }
        }
        // Insignias desactivadas en el catálogo dejan de contar para el puntaje (no se borran)

        $puntos = $calc['puntos'];

        $pdo->prepare("UPDATE crematorios SET puntaje_ranking = :p, verificado = :v, destacado = :d WHERE id = :id")
            ->execute([
                ':p'  => round($puntos, 2),
                ':v'  => isset($tiene['verificado_humano']) ? 1 : 0,
                ':d'  => (isset($tiene['destacado']) || isset($tiene['promocionado'])) ? 1 : 0,
                ':id' => $id,
            ]);
    }

    return ['fichas' => count($fichas), 'asignadas' => $asignadas, 'quitadas' => $quitadas];
}

/**
 * Núcleo PURO del cálculo (sin BD): qué insignias tiene una ficha y cuántos puntos suma.
 * Lo usan recalcularInsignias() (datos reales) y el laboratorio (admin/insignias-laboratorio.php,
 * datos simulados). Para simular popularidad / reseña reciente sin tablas, el array de la ficha
 * puede traer `_sim` => ['popular' => 0..3, 'resena_reciente' => bool].
 *
 * @param array<string,int> $manuales slug => nivel de las insignias manuales que tiene
 * @return array{tiene:array<string,int>,puntos:float,desglose:array<string,float>,calidad:float}
 */
function insigniasCalcularFicha(array $catalogo, array $cr, int $id, int $pct, array $rating, array $manuales, array $provCtx = [], array $recientes = []): array
{
    $tiene = $manuales;

    // Automáticas: primero las sin dependencias, luego las que "requieren" otras
    $autos = array_filter($catalogo, fn($i) => $i['tipo'] === 'auto');
    usort($autos, fn($a, $b) => (int) !empty($a['regla_arr']['requiere']) <=> (int) !empty($b['regla_arr']['requiere']));

    foreach ($autos as $i) {
        $r = $i['regla_arr'];
        $nivel = insigniaEvaluarRegla($r, $cr, $pct, $rating, $id, $provCtx, $recientes);
        if ($nivel > 0 && !empty($r['requiere'])) {
            foreach ($r['requiere'] as $req) {
                if (!isset($tiene[$req])) { $nivel = 0; break; }
            }
        }
        if ($nivel > 0) $tiene[$i['slug']] = $nivel;
    }

    // Puntaje: puntos de cada insignia (× nivel si tiene niveles) + calidad
    $porSlug = [];
    foreach ($catalogo as $i) $porSlug[$i['slug']] = $i;
    $desglose = [];
    $puntos   = 0.0;
    foreach ($tiene as $slug => $nivel) {
        if (!isset($porSlug[$slug])) continue; // insignia desactivada: no cuenta
        $p = (int) $porSlug[$slug]['puntos'] * (!empty($porSlug[$slug]['niveles_arr']) ? $nivel : 1);
        $desglose[$slug] = (float) $p;
        $puntos += $p;
    }
    $calidad = insigniaPuntajeCalidad($rating, $pct);

    return ['tiene' => $tiene, 'puntos' => $puntos + $calidad, 'desglose' => $desglose, 'calidad' => $calidad];
}

/**
 * Evalúa una regla. Devuelve el nivel concedido (0 = no cumple, 1+ = cumple).
 */
function insigniaEvaluarRegla(array $r, array $cr, int $pctCompletitud, array $rating, int $id, array $provCtx, array $recientes = []): int
{
    switch ($r['tipo'] ?? '') {
        case 'resena_reciente':
            if (isset($cr['_sim']['resena_reciente'])) return $cr['_sim']['resena_reciente'] ? 1 : 0;
            $clave = max(1, (int) ($r['dias'] ?? 30)) . '-' . max(1, min(5, (int) ($r['min_calificacion'] ?? 4)));
            return ($recientes[$clave][$id] ?? 0) > 0 ? 1 : 0;

        case 'columna':
            $v = $cr[$r['col'] ?? ''] ?? null;
            return ($v !== null && (string) $v === (string) ($r['valor'] ?? 1)) ? 1 : 0;

        case 'columna_o':
            foreach ($r['cols'] ?? [] as $col) {
                if (!empty($cr[$col])) return 1;
            }
            return 0;

        case 'completitud':
            return $pctCompletitud >= (int) ($r['min'] ?? 90) ? 1 : 0;

        case 'rating':
            if (isset($r['completitud_min']) && $pctCompletitud < (int) $r['completitud_min']) return 0;
            return ($rating['total'] >= (int) ($r['min_resenas'] ?? 0)
                 && $rating['rating'] >= (float) ($r['min_rating'] ?? 0)) ? 1 : 0;

        case 'popularidad':
            if (isset($cr['_sim']['popular'])) return max(0, min(3, (int) $cr['_sim']['popular']));
            $dias   = max(1, (int) ($r['dias'] ?? 90));
            $zona   = $provCtx[$dias][(int) ($cr['provincia_id'] ?? 0)] ?? [];
            $minFic = (int) ($r['min_fichas_zona'] ?? 3);
            $propio = (int) ($zona[$id] ?? 0);
            if (count($zona) < $minFic || $propio < (int) ($r['min_contactos'] ?? 1)) return 0;

            $menos = count(array_filter($zona, fn($n) => $n < $propio));
            $pct   = $menos / count($zona) * 100;
            [$p1, $p2] = $r['percentiles'] ?? [50, 80];
            if ($propio === max($zona)) return 3;
            if ($pct >= $p2) return 2;
            if ($pct >= $p1) return 1;
            return 0;
    }
    return 0;
}

/**
 * Parte «calidad» del puntaje (máx. 40): rating bayesiano (hasta 20) + completitud (hasta 20).
 * El bayesiano evita que 1 reseña de 5★ gane a 80 reseñas de 4,8★.
 */
function insigniaPuntajeCalidad(array $rating, int $pctCompletitud): float
{
    $m = 10;      // peso del "prior"
    $c = 4.0;     // media global de referencia
    $v = $rating['total'];
    $bayes = $v > 0 ? ($v / ($v + $m)) * $rating['rating'] + ($m / ($v + $m)) * $c : 0;
    return $bayes * 4 + $pctCompletitud * 0.2;
}
