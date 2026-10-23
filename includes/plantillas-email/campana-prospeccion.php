<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLAS EMAIL — campaña de prospección a los negocios (emails 1-3)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailCampana(int $paso, array $ficha, string $email): array
 *     $paso:  1 = invitación · 2 = recordatorio (~7 días) · 3 = último aviso (~14 días después)
 *     $ficha: id (int), nombre (string)
 *     return: ['asunto','html','texto']
 *
 * Los botones llevan al enlace firmado de verificar-ficha.php con &c=<paso>&a=<botón>, que sirve
 * para contar los clics. Pie: titular legal (Lycapolis LLC), baja de un clic y política de privacidad / términos.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailCampana')) {

    function renderEmailCampana(int $paso, array $ficha, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = (string)($ficha['nombre'] ?? '');
        $id     = (int)($ficha['id'] ?? 0);
        $base   = notifUrlVerificarFicha($id) . '&c=' . $paso;
        $urlLlamada    = $base . '&a=llamada#verificar-llamada';
        $urlFormulario = $base . '&a=formulario#verificar-formulario';

        $pie = "Recibes este mensaje porque {$nombre} figura en el directorio {$sitio}, que edita Lycapolis LLC, y este es el email de contacto que consta en su ficha (procedente de fuentes públicas). "
             . "Solo te escribiremos con información útil para tu sector; si no quieres más mensajes, puedes darte de baja con un clic.";

        $comun = [
            'saludo'         => 'Hola:',
            'pie_motivo'     => $pie,
            'url_baja'       => notifUrlBaja($email),
            'texto_baja'     => 'Darme de baja',
            'enlace_terminos' => true,
        ];

        if ($paso === 1) {
            $asunto = "Verifica gratis la ficha de {$nombre}";
            $p = [
                'asunto'    => $asunto,
                'preheader' => 'Tu negocio ya está en el directorio. Verificarlo es gratis y no te compromete a nada.',
                'parrafos'  => [
                    "En {$sitio} acercamos a los dueños de mascotas con los negocios que los acompañan en un momento difícil, y nos comprometemos a que cada familia tenga la mejor experiencia posible.",
                    'Tu negocio, <strong>' . $e($nombre) . '</strong>, ya tiene ficha en nuestro directorio. Queremos que sus datos sean correctos, y por eso te proponemos verificarla: es <strong>gratis, sin ningún riesgo y sin compromiso</strong>.',
                    'Si aceptas una breve llamada de nuestro equipo, tu ficha recibe <strong>gratis la insignia «Verificado por humano»</strong>: transmite confianza a las familias y te da acceso a un canal gratuito de contactos que podrás aprovechar más adelante, solo si quieres. No habrá publicidad ni spam: únicamente información útil de tu sector.',
                    'Elige lo que te resulte más cómodo:',
                ],
                'cta'              => ['texto' => 'Quiero que me llamen', 'url' => $urlLlamada],
                'parrafos_despues' => ['O, si lo prefieres, hazlo tú a tu ritmo:'],
                'cta2'             => ['texto' => 'Prefiero completarlo yo', 'url' => $urlFormulario],
            ];
        } elseif ($paso === 2) {
            $asunto = "Recordatorio: verifica la ficha de {$nombre}";
            $p = [
                'asunto'    => $asunto,
                'preheader' => 'Sigue siendo gratis y sin compromiso. Te lleva un par de minutos.',
                'parrafos'  => [
                    'Hace unos días te escribimos para que verificaras la ficha de <strong>' . $e($nombre) . '</strong> en ' . $e($sitio) . '. Por si se te pasó: es <strong>gratis y sin compromiso</strong>, y solo sirve para que los datos que ven las familias sean correctos.',
                    'Si nos dejas llamarte, tu ficha recibe <strong>gratis la insignia «Verificado por humano»</strong>. También puedes completar los datos tú mismo:',
                ],
                'cta'              => ['texto' => 'Quiero que me llamen', 'url' => $urlLlamada],
                'parrafos_despues' => [],
                'cta2'             => ['texto' => 'Prefiero completarlo yo', 'url' => $urlFormulario],
            ];
        } else {
            $asunto = "Último aviso sobre la ficha de {$nombre}";
            $p = [
                'asunto'    => $asunto,
                'preheader' => 'Es nuestro último mensaje. Completar tu ficha es gratis y lleva unos minutos.',
                'parrafos'  => [
                    'Este es nuestro <strong>último mensaje</strong> sobre la ficha de <strong>' . $e($nombre) . '</strong> en ' . $e($sitio) . '; después no volveremos a escribirte sobre esto.',
                    'Si quieres que los datos de tu negocio estén al día, lo más rápido es completarlos en el formulario: solo tienes que revisar lo que ya tenemos y corregir lo que haga falta. Nuestro equipo lo revisa antes de publicar nada, y es <strong>gratis y sin compromiso</strong>.',
                ],
                'cta'              => ['texto' => 'Completar mi ficha', 'url' => $urlFormulario],
                'parrafos_despues' => ['¿Prefieres hablarlo por teléfono? Pide que te llamemos y tu ficha recibirá <strong>gratis la insignia «Verificado por humano»</strong>:'],
                'cta2'             => ['texto' => 'Quiero que me llamen', 'url' => $urlLlamada],
            ];
        }

        $capa = renderEmailLayoutUsuario($p + $comun);
        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
