<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ACTUALIZACIÓN DE FICHA POR EL NEGOCIO — catálogo de campos, comparación y aplicación
 * ═══════════════════════════════════════════════════════════
 *
 * Lo usan:
 *   - verificar-ficha.php            → precarga el formulario con los datos actuales y calcula qué ha cambiado.
 *   - admin/actualizaciones-pendientes.php → aplica a la ficha lo que el admin aprueba, campo a campo.
 *
 * Dos modos de aplicación (columna `modo` del catálogo):
 *   'directo'        → al aprobar, el dato pasa a la ficha pública (admin = ficha pública).
 *   'texto_original' → servicios, horarios y precios llegan como texto libre. Al aprobar se archivan en
 *                      texto_origen_json (referencia inmutable del editor) y el admin los estructura con la
 *                      IA desde editar-ficha-negocio.php; hasta entonces la ficha pública no cambia.
 *
 * Regla: un campo vacío en el formulario NO es un cambio (nunca se borra un dato por omisión).
 * El texto original que envió el negocio se conserva siempre (actualizaciones_ficha.datos_enviados_json).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/funciones.php';

if (!function_exists('actFichaCatalogo')) {

    /** clave => [etiqueta, modo, grupo] (el orden es el de la pantalla de revisión). */
    function actFichaCatalogo(): array
    {
        return [
            'nombre_negocio'     => ['Nombre del negocio (título de la ficha)', 'directo', 'Datos básicos'],
            'telefono_clientes'  => ['Teléfono para clientes',   'directo',        'Contacto'],
            'email_clientes'     => ['Email para clientes',      'directo',        'Contacto'],
            'direccion'          => ['Dirección',                'directo',        'Ubicación'],
            'provincia'          => ['Provincia',                'directo',        'Ubicación'],
            'ciudad'             => ['Ciudad',                   'directo',        'Ubicación'],
            'codigo_postal'      => ['Código postal',            'directo',        'Ubicación'],
            'zona_cobertura'     => ['Zona de cobertura (provincias / comunidades)', 'directo', 'Cobertura'],
            'ciudades_cobertura' => ['Ciudades de cobertura',    'directo',        'Cobertura'],
            'descripcion'        => ['Descripción',              'directo',        'Contenido'],
            'servicios'          => ['Servicios',                'texto_original', 'Contenido'],
            'horarios'           => ['Horarios',                 'texto_original', 'Contenido'],
            'precios'            => ['Precios o tarifas',        'texto_original', 'Contenido'],
            'sitio_web'          => ['Sitio web',                'directo',        'Presencia en línea'],
            'google_maps_url'    => ['Google Maps',              'directo',        'Presencia en línea'],
            'whatsapp'           => ['WhatsApp',                 'directo',        'Presencia en línea'],
            'facebook'           => ['Facebook',                 'directo',        'Presencia en línea'],
            'instagram'          => ['Instagram',                'directo',        'Presencia en línea'],
        ];
    }

    /** Servicios del formulario ↔ columnas booleanas de la ficha. */
    function actFichaServiciosFlags(): array
    {
        return [
            'Cremación individual'    => 'cremacion_individual',
            'Cremación colectiva'     => 'cremacion_colectiva',
            'Recogida a domicilio'    => 'recogida_domicilio',
            'Entrega a domicilio'     => 'entrega_domicilio',
            'Atención 24h'            => 'atencion_24h',
            'Sala velatoria'          => 'sala_velatoria',
            'Souvenirs / recuerdos'   => 'souvenires',
            'Urna incluida'           => 'urna',
            'Carta de condolencias'   => 'carta',
            'Molde de huella'         => 'molde',
        ];
    }

    /** Clave del texto original (texto_origen_json) que corresponde a un campo en modo 'texto_original'. */
    function actFichaClaveTextoOrigen(string $campo): string
    {
        return ['servicios' => 'servicios_texto', 'horarios' => 'horarios_texto', 'precios' => 'precios_texto'][$campo] ?? '';
    }

    /** Lista separada por comas/saltos → «a, b, c» (comparable y presentable). */
    function actFichaNormLista(string $s): string
    {
        $partes = preg_split('/[,;\n]+/u', $s) ?: [];
        $partes = array_values(array_filter(array_map(fn($p) => trim(preg_replace('/\s+/u', ' ', $p)), $partes), fn($p) => $p !== ''));
        return implode(', ', $partes);
    }

    /** Espacios normalizados, para comparar sin falsos cambios. */
    function actFichaNormTexto(string $s): string
    {
        return trim(preg_replace('/[ \t]+/u', ' ', str_replace("\r\n", "\n", $s)));
    }

    /** Normaliza un valor según el campo (para comparar y para guardar). */
    function actFichaNormalizar(string $campo, string $valor): string
    {
        if ($campo === 'zona_cobertura' || $campo === 'ciudades_cobertura') return actFichaNormLista($valor);
        if ($campo === 'servicios') return actFichaNormLista($valor);
        if ($campo === 'telefono_clientes') {
            $n = telefonoEspanaNormalizar($valor);
            return $n ?? actFichaNormTexto($valor);
        }
        if ($campo === 'whatsapp') return actFichaNormTexto($valor);
        return actFichaNormTexto($valor);
    }

    /**
     * Datos actuales de la ficha tal como los pinta el formulario (misma transformación que se usa
     * para comparar, así «no tocar nada» nunca cuenta como cambio).
     *
     * @return array{ficha:array, valores:array<string,string>}|null
     */
    function actFichaValoresActuales(\PDO $pdo, int $fichaId): ?array
    {
        $st = $pdo->prepare("SELECT c.*, p.nombre AS provincia_nombre
                             FROM crematorios c LEFT JOIN provincias p ON p.id = c.provincia_id
                             WHERE c.id = ? LIMIT 1");
        $st->execute([$fichaId]);
        $c = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$c) return null;

        $origen = json_decode((string)($c['texto_origen_json'] ?? ''), true);
        $origen = is_array($origen) ? $origen : [];

        // Redes
        $redes = json_decode((string)($c['redes_json'] ?? ''), true);
        $entradas = is_array($redes) ? ($redes['entries'] ?? []) : [];
        $red = function (string $nombre) use ($entradas): string {
            foreach ($entradas as $e) if (($e['red'] ?? '') === $nombre) return (string)($e['url'] ?? '');
            return '';
        };

        // Email de atención a clientes (cache plana; si no, el general)
        $email = trim((string)($c['email_clientes'] ?? '')) ?: trim((string)($c['email'] ?? ''));

        // Servicios: casillas verdaderas; si no hay ninguna definida, el texto antiguo
        $servs = [];
        foreach (actFichaServiciosFlags() as $etq => $col) if (!empty($c[$col])) $servs[] = $etq;
        $serviciosTxt = $servs ? implode(', ', $servs) : actFichaNormLista((string)($c['servicios'] ?? ''));

        // Horarios: tramos separados por «|» en la ficha → uno por línea en el formulario
        $horarios = implode("\n", array_filter(array_map('trim', explode('|', (string)($c['horario_texto'] ?? '')))));

        // Precios: texto original archivado; si no hay, un resumen de los precios estructurados
        $precios = trim((string)($origen['precios_texto'] ?? ''));
        if ($precios === '' && !empty($c['precios_json'])) {
            $lineas = [];
            foreach (json_decode((string)$c['precios_json'], true) ?: [] as $it) {
                $n = trim((string)($it['nombre'] ?? ''));
                if ($n === '') continue;
                $min = $it['min'] ?? null; $max = $it['max'] ?? null;
                $imp = '';
                if ($min !== null && $min !== '' && $max !== null && $max !== '' && (string)$min !== (string)$max) $imp = "{$min}–{$max} €";
                elseif ($min !== null && $min !== '') $imp = (($it['tipo'] ?? '') === 'desde' ? 'desde ' : '') . "{$min} €";
                $lineas[] = $imp !== '' ? "{$n}: {$imp}" : $n;
            }
            $precios = implode("\n", $lineas);
        }

        $valores = [
            'nombre_negocio'     => (string)($c['nombre'] ?? ''),
            'telefono_clientes'  => (string)(fichaTelefonoMostrado($c) ?? ''),
            'email_clientes'     => $email,
            'direccion'          => (string)($c['direccion_completa'] ?? ''),
            'provincia'          => (string)($c['provincia_nombre'] ?? ''),
            'ciudad'             => (string)($c['ciudad'] ?? ''),
            'codigo_postal'      => (string)($c['codigo_postal'] ?? ''),
            'zona_cobertura'     => actFichaNormLista((string)($c['zona_cobertura'] ?? '')),
            'ciudades_cobertura' => actFichaNormLista((string)($c['ciudades_cobertura'] ?? '')),
            'descripcion'        => (string)($c['descripcion'] ?? ''),
            'servicios'          => $serviciosTxt,
            'horarios'           => $horarios,
            'precios'            => $precios,
            'sitio_web'          => (string)($c['website'] ?? ''),
            'google_maps_url'    => (string)($c['google_maps_url'] ?? ''),
            'whatsapp'           => (string)($c['whatsapp'] ?? ''),
            'facebook'           => $red('facebook'),
            'instagram'          => $red('instagram'),
        ];
        foreach ($valores as $k => $v) $valores[$k] = actFichaNormalizar($k, $v);

        return ['ficha' => $c, 'valores' => $valores];
    }

    /**
     * Cambios propuestos: solo los campos informados cuyo valor difiere del actual.
     * @param array<string,string> $actual   valores de actFichaValoresActuales()
     * @param array<string,mixed>  $enviado  campos del formulario ($_POST)
     * @return array<string,array{actual:string,propuesto:string}>
     */
    function actFichaDiff(array $actual, array $enviado): array
    {
        $cambios = [];
        foreach (array_keys(actFichaCatalogo()) as $campo) {
            $nuevo = actFichaNormalizar($campo, (string)($enviado[$campo] ?? ''));
            if ($nuevo === '') continue;                       // vacío = sin cambio
            $ant = (string)($actual[$campo] ?? '');
            if ($nuevo === $ant) continue;
            $cambios[$campo] = ['actual' => $ant, 'propuesto' => $nuevo];
        }
        return $cambios;
    }

    /** Errores de validación de los cambios propuestos (textos para el negocio, en español de España). */
    function actFichaValidarCambios(array $cambios): array
    {
        $err = [];
        $url = fn($v) => (bool)preg_match('~^https?://[^\s]+\.[^\s]+$~i', $v);
        foreach ($cambios as $campo => $c) {
            $v = $c['propuesto'];
            switch ($campo) {
                case 'nombre_negocio':
                    if (mb_strlen($v) < 3 || mb_strlen($v) > 150) $err[] = 'El nombre del negocio debe tener entre 3 y 150 caracteres.';
                    break;
                case 'telefono_clientes':
                    if (telefonoEspanaNormalizar($v) === null) $err[] = 'El teléfono para clientes no parece un teléfono de España (9 dígitos, por ejemplo 612 345 678).';
                    break;
                case 'email_clientes':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = 'El email para clientes no es válido.';
                    break;
                case 'descripcion':
                    if (mb_strlen($v) < 150) $err[] = 'La descripción debe tener al menos 150 caracteres (ahora tiene ' . mb_strlen($v) . ').';
                    break;
                case 'sitio_web': case 'google_maps_url': case 'facebook': case 'instagram':
                    if (!$url($v)) $err[] = actFichaCatalogo()[$campo][0] . ': escribe la dirección completa, empezando por https://';
                    break;
                case 'whatsapp':
                    if (!preg_match('/^\+?[\d\s().\-]{7,20}$/', $v)) $err[] = 'El WhatsApp debe ser un número, con código de país si es posible.';
                    break;
            }
            if (mb_strlen($v) > 5000) $err[] = actFichaCatalogo()[$campo][0] . ': el texto es demasiado largo.';
        }
        return $err;
    }

    // ─── Aplicación a la ficha (lo llama el admin al aprobar) ───────────────────

    /**
     * Aplica un campo aprobado a la ficha. Lanza \RuntimeException con un texto claro si no se puede.
     * Debe llamarse dentro de una transacción del llamador.
     * @return string aviso opcional para el admin (p. ej. «archivado como texto original»)
     */
    function actFichaAplicarCampo(\PDO $pdo, int $fichaId, string $campo, string $valor): string
    {
        $cat = actFichaCatalogo();
        if (!isset($cat[$campo])) throw new \RuntimeException("Campo desconocido: {$campo}");
        $ahora = date('Y-m-d H:i:s');

        $sel = $pdo->prepare("SELECT * FROM crematorios WHERE id = ? LIMIT 1 FOR UPDATE");
        $sel->execute([$fichaId]);
        $c = $sel->fetch(\PDO::FETCH_ASSOC);
        if (!$c) throw new \RuntimeException('La ficha ya no existe.');

        $upd = fn(string $col, $v) => $pdo->prepare("UPDATE crematorios SET {$col} = ? WHERE id = ?")->execute([$v, $fichaId]);

        switch ($campo) {
            case 'telefono_clientes':
                $n = telefonoEspanaNormalizar($valor);
                if ($n === null) throw new \RuntimeException('Teléfono no válido: ' . $valor);
                telefonoReemplazarPorNegocio($pdo, $fichaId, $n);
                return '';

            case 'email_clientes':
                $lista = json_decode((string)($c['emails_json'] ?? ''), true);
                $lista = is_array($lista) ? $lista : [];
                $hecho = false;
                foreach (['clientes', 'general'] as $tipo) {
                    foreach ($lista as $i => $e) {
                        if (($e['tipo'] ?? '') === $tipo) {
                            $lista[$i]['email_anterior'] = $e['email'] ?? '';
                            $lista[$i]['email']  = $valor;
                            $lista[$i]['origen'] = 'negocio';
                            $hecho = true; break 2;
                        }
                    }
                }
                if (!$hecho) {
                    $lista[] = ['id' => 'n' . substr(bin2hex(random_bytes(4)), 0, 7), 'origen' => 'negocio', 'tipo' => 'clientes',
                                'label' => 'Atención a clientes', 'email' => $valor, 'visible' => true];
                }
                $upd('emails_json', json_encode($lista, JSON_UNESCAPED_UNICODE));
                sincronizarCamposFlat($pdo, $fichaId);
                return '';

            case 'nombre_negocio': $upd('nombre', $valor); return 'el nombre es el título de la ficha; la dirección web (slug) no cambia';
            case 'direccion':     $upd('direccion_completa', $valor); return '';
            case 'ciudad':        $upd('ciudad', $valor); return '';
            case 'codigo_postal': $upd('codigo_postal', $valor); return '';
            case 'zona_cobertura':     $upd('zona_cobertura', $valor); return '';
            case 'ciudades_cobertura': $upd('ciudades_cobertura', $valor); return '';
            case 'sitio_web':       $upd('website', $valor); return '';
            case 'google_maps_url': $upd('google_maps_url', $valor); return '';
            case 'whatsapp':        $upd('whatsapp', $valor); return '';

            case 'provincia':
                $q = $pdo->prepare("SELECT id FROM provincias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
                $q->execute([$valor]);
                $pid = $q->fetchColumn();
                if (!$pid) throw new \RuntimeException("La provincia «{$valor}» no existe en el catálogo; créala antes o rechaza este campo.");
                $upd('provincia_id', (int)$pid);
                return '';

            case 'descripcion':
                $lista = json_decode((string)($c['descripciones_json'] ?? ''), true);
                $lista = is_array($lista) ? $lista : [];
                if (!$lista && trim((string)($c['descripcion'] ?? '')) !== '') {   // conserva la actual como histórica
                    $lista[] = ['id' => 'd0', 'origen' => 'importado', 'valor' => $c['descripcion'], 'activo' => false, 'creado_at' => $ahora, 'editado_at' => null];
                }
                foreach ($lista as $i => $e) $lista[$i]['activo'] = false;
                $lista[] = ['id' => 'd' . substr(bin2hex(random_bytes(4)), 0, 7), 'origen' => 'manual_negocio', 'valor' => $valor,
                            'activo' => true, 'creado_at' => $ahora, 'editado_at' => null];
                $upd('descripciones_json', json_encode($lista, JSON_UNESCAPED_UNICODE));
                sincronizarCamposFlat($pdo, $fichaId);
                return '';

            case 'facebook':
            case 'instagram':
                $redes = json_decode((string)($c['redes_json'] ?? ''), true);
                $redes = is_array($redes) ? $redes : ['modo' => 'iconos', 'entries' => []];
                $redes['entries'] = $redes['entries'] ?? [];
                $hecho = false;
                foreach ($redes['entries'] as $i => $e) {
                    if (($e['red'] ?? '') === $campo) { $redes['entries'][$i]['url'] = $valor; $hecho = true; break; }
                }
                if (!$hecho) $redes['entries'][] = ['red' => $campo, 'url' => $valor, 'label' => ucfirst($campo), 'visible' => true];
                $upd('redes_json', json_encode($redes, JSON_UNESCAPED_UNICODE));
                return '';

            case 'servicios':
            case 'horarios':
            case 'precios':
                $origen = json_decode((string)($c['texto_origen_json'] ?? ''), true);
                if (!is_array($origen) || !$origen) $origen = ['fuente' => 'manual_negocio', 'fecha' => date('Y-m-d')];
                $origen[actFichaClaveTextoOrigen($campo)] = $valor;
                $upd('texto_origen_json', json_encode($origen, JSON_UNESCAPED_UNICODE));
                return 'archivado como texto original: estructúralo con la IA en el editor de la ficha';
        }
        return '';
    }

    /** Copia una imagen aprobada a la ficha (nombre único por actualización, sin pisar las existentes). */
    function actFichaAplicarImagen(\PDO $pdo, int $fichaId, string $slug, array $img, int $actId): bool
    {
        require_once __DIR__ . '/ImagenHelper.php';
        $slugUnico = $slug . '-act' . $actId . '-' . (int)$img['id'];
        $r = \ImagenHelper::copiarACrematorio([$img['ruta']], $slugUnico, $img['tipo'], $fichaId, 'manual_negocio');
        return !empty($r);
    }
}
