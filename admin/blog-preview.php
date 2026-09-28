<?php
/**
 * Vista previa de un artículo SIN guardar: recibe el mismo payload que
 * blog-guardar-ajax.php (POST "payload") y renderiza la plantilla pública
 * articulo.php con noindex y un aviso de "Vista previa".
 */

require_once __DIR__ . '/_blog-comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('La sesión expiró. Vuelve al editor y recarga la página.');
}
$p = json_decode((string) ($_POST['payload'] ?? ''), true);
if (!is_array($p)) { http_response_code(400); exit('Datos de vista previa inválidos.'); }

$pdo = obtenerConexion();
$bloques = is_array($p['contenido']['blocks'] ?? null) ? $p['contenido']['blocks'] : [];
$palabras = blogContarPalabras($bloques);

$buscarAutor = function ($id) use ($pdo) {
    if (!(int) $id) return null;
    $st = $pdo->prepare("SELECT * FROM blog_autores WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
};
$autor   = $buscarAutor($p['autor_id'] ?? 0);
$revisor = $buscarAutor($p['revisor_id'] ?? 0);

$cat = null;
if ((int) ($p['categoria_principal_id'] ?? 0)) {
    $st = $pdo->prepare("SELECT nombre, slug FROM blog_terminos WHERE id = :id");
    $st->execute([':id' => (int) $p['categoria_principal_id']]);
    $cat = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$original = (int) ($p['id'] ?? 0) ? blogArticuloPorId((int) $p['id']) : null;

$articulo = [
    'id'               => (int) ($p['id'] ?? 0),
    'titulo'           => trim($p['titulo'] ?? '') ?: 'Artículo sin título',
    'slug'             => trim($p['slug'] ?? '') ?: 'vista-previa',
    'extracto'         => trim($p['extracto'] ?? ''),
    'contenido_json'   => json_encode(['blocks' => $bloques], JSON_UNESCAPED_UNICODE),
    'portada_ruta'     => $p['portada_ruta'] ?? '',
    'portada_alt'      => $p['portada_alt'] ?? '',
    'publicado_at'     => !empty($p['publicado_at']) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $p['publicado_at']))) : blogAhora(),
    'actualizado_contenido_at' => $original['actualizado_contenido_at'] ?? null,
    'updated_at'       => blogAhora(),
    'mostrar_indice'   => !empty($p['mostrar_indice']) ? 1 : 0,
    'meta_title'       => $p['meta_title'] ?? '',
    'meta_description' => $p['meta_description'] ?? '',
    'keyword_principal'=> $p['keyword_principal'] ?? '',
    'canonical_url'    => '',
    'noindex'          => 1,
    'palabras'         => $palabras,
    'lectura_min'      => max(1, (int) ceil($palabras / 200)),
    'categoria_nombre' => $cat['nombre'] ?? null,
    'categoria_slug'   => $cat['slug'] ?? null,
    'autor_nombre'     => $autor['nombre'] ?? null,
    'autor_cargo'      => $autor['cargo'] ?? null,
    'autor_foto'       => $autor['foto'] ?? null,
    'autor_bio'        => $autor['bio'] ?? null,
    'autor_credenciales' => $autor['credenciales'] ?? null,
    'autor_web'        => $autor['web'] ?? null,
    'revisor_nombre'   => $revisor['nombre'] ?? null,
    'revisor_cargo'    => $revisor['cargo'] ?? null,
];

// Términos seleccionados (los nuevos "nuevo:X" se muestran como etiqueta provisional)
$terminos = [];
$taxPorId = array_column(blogTaxonomias(), null, 'id');
foreach (($p['terminos'] ?? []) as $taxId => $lista) {
    $tx = $taxPorId[(int) $taxId] ?? null;
    if (!$tx || !is_array($lista)) continue;
    foreach ($lista as $v) {
        if (is_numeric($v)) {
            $st = $pdo->prepare("SELECT * FROM blog_terminos WHERE id = :id");
            $st->execute([':id' => (int) $v]);
            if ($t = $st->fetch(PDO::FETCH_ASSOC)) $terminos[$tx['slug']][] = $t;
        } elseif (is_string($v) && str_starts_with($v, 'nuevo:')) {
            $n = trim(substr($v, 6));
            $terminos[$tx['slug']][] = ['id' => 0, 'nombre' => $n, 'slug' => slugificar($n)];
        }
    }
}

$contexto = [];
foreach (($p['contexto'] ?? []) as $tipo => $valores) {
    foreach ((array) $valores as $v) $contexto[] = ['tipo' => $tipo, 'valor' => (string) $v];
}

$BLOG_PREVIEW = ['articulo' => $articulo, 'terminos' => $terminos, 'contexto' => $contexto];
include dirname(__DIR__) . '/articulo.php';
