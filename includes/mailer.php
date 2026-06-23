<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MAILER — envío de emails HTML con fallback
 * ═══════════════════════════════════════════════════════════
 *
 * Wrapper sobre PHPMailer (SMTP autenticado, Titan/Hostinger) con
 * fallback automático a mail() nativo si SMTP_HOST está vacío.
 *
 * Uso:
 *   require_once __DIR__ . '/mailer.php';
 *   enviarMailHtml($destino, $asunto, $bodyHtml, $bodyText, $replyTo);
 *
 * El llamador es responsable de las plantillas HTML/texto.
 * Esta capa solo se ocupa de transporte + headers.
 * ═══════════════════════════════════════════════════════════
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!function_exists('enviarMailHtml')) {

    /**
     * Envía un email HTML con su versión texto plano.
     *
     * @param string      $destino   Email del destinatario
     * @param string      $asunto    Asunto
     * @param string      $bodyHtml  Cuerpo HTML
     * @param string      $bodyText  Cuerpo plain-text (fallback para clientes que no rendericen HTML)
     * @param string|null $replyTo   Reply-To opcional (ej: email del lead para que el negocio pueda responder directo)
     * @param string|null $destinoNombre Nombre del destinatario (mejora deliverabilidad)
     * @return array{ok:bool, error?:string, transporte:string}
     */
    function enviarMailHtml(
        string $destino,
        string $asunto,
        string $bodyHtml,
        string $bodyText,
        ?string $replyTo = null,
        ?string $destinoNombre = null
    ): array {
        $destino = trim($destino);
        if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'email destino inválido', 'transporte' => 'none'];
        }

        $smtpDisponible = defined('SMTP_HOST') && SMTP_HOST !== ''
                       && defined('SMTP_USER') && SMTP_USER !== ''
                       && defined('SMTP_PASS') && SMTP_PASS !== '';

        if ($smtpDisponible && file_exists(__DIR__ . '/../assets/librerias/phpmailer/PHPMailer.php')) {
            return enviarMailHtmlSmtp($destino, $asunto, $bodyHtml, $bodyText, $replyTo, $destinoNombre);
        }

        // Fallback: mail() nativo
        return enviarMailHtmlNativo($destino, $asunto, $bodyHtml, $bodyText, $replyTo);
    }

    /**
     * Envío vía SMTP autenticado (Titan / Hostinger).
     */
    function enviarMailHtmlSmtp(
        string $destino,
        string $asunto,
        string $bodyHtml,
        string $bodyText,
        ?string $replyTo,
        ?string $destinoNombre
    ): array {
        $libDir = __DIR__ . '/../assets/librerias/phpmailer';
        require_once $libDir . '/Exception.php';
        require_once $libDir . '/PHPMailer.php';
        require_once $libDir . '/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            // Verbose en local, silencio en producción
            $mail->SMTPDebug  = (defined('DEBUG_MODE') && DEBUG_MODE) ? SMTP::DEBUG_OFF : SMTP::DEBUG_OFF;

            $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USER;
            $fromName  = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : (defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Sitio');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($destino, $destinoNombre ?: '');

            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText;

            $mail->send();
            return ['ok' => true, 'transporte' => 'smtp'];
        } catch (PHPMailerException $e) {
            error_log('[mailer] SMTP error: ' . $mail->ErrorInfo);
            return ['ok' => false, 'error' => $mail->ErrorInfo, 'transporte' => 'smtp'];
        } catch (\Throwable $e) {
            error_log('[mailer] SMTP exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'transporte' => 'smtp'];
        }
    }

    /**
     * Fallback: mail() nativo. Sin auth → en producción suele caer en spam,
     * pero suficiente para desarrollo local o si todavía no hay credenciales SMTP.
     */
    function enviarMailHtmlNativo(
        string $destino,
        string $asunto,
        string $bodyHtml,
        string $bodyText,
        ?string $replyTo
    ): array {
        $boundary = 'b_' . md5((string)mt_rand());
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName  = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : (defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Sitio');

        $headers  = "From: " . encodeHeaderUtf8($fromName) . " <{$fromEmail}>\r\n";
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyText)) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyHtml)) . "\r\n";

        $body .= "--{$boundary}--";

        $asuntoEncoded = encodeHeaderUtf8($asunto);
        $ok = @mail($destino, $asuntoEncoded, $body, $headers);
        return $ok
            ? ['ok' => true, 'transporte' => 'mail()']
            : ['ok' => false, 'error' => 'mail() returned false', 'transporte' => 'mail()'];
    }

    /**
     * Encoda un header UTF-8 (asunto, From) en formato RFC 2047 si contiene
     * caracteres no-ASCII. Necesario para que asuntos con acentos no se vean
     * como "?Notificaci?n" en algunos clientes.
     */
    function encodeHeaderUtf8(string $valor): string
    {
        if (preg_match('/[^\x20-\x7E]/', $valor)) {
            return '=?UTF-8?B?' . base64_encode($valor) . '?=';
        }
        return $valor;
    }
}
