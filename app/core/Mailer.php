<?php

/**
 * Minimal mail abstraction. Supports two transports controlled by env:
 *   MAIL_TRANSPORT=log   → write the message to error_log (dev default).
 *   MAIL_TRANSPORT=mail  → use PHP's built-in mail() function. Requires an
 *                          SMTP configuration on the host OR a properly
 *                          configured sendmail / Windows mail wrapper.
 *
 * A full SMTP client (PHPMailer, Symfony Mailer) is a drop-in replacement
 * for the `mail` branch when the project is ready for it.
 */
class Mailer {
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        $transport   = Env::get('MAIL_TRANSPORT', 'log');
        $fromAddress = Env::get('MAIL_FROM_ADDRESS', 'no-reply@costaspressjr.local');
        $fromName    = Env::get('MAIL_FROM_NAME', 'Costaspressjr');

        if ($textBody === '') {
            $textBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));
        }

        $boundary = '=_mailer_' . bin2hex(random_bytes(12));
        $headers  = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . self::formatFrom($fromName, $fromAddress),
            'Reply-To: ' . $fromAddress,
            'X-Mailer: costaspressjr',
        ];

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--$boundary--";

        if ($transport === 'mail') {
            return @mail($to, $subject, $body, implode("\r\n", $headers));
        }

        // Default: log it. Useful during development before SMTP is wired up.
        //
        // error_log() alone was not enough to work with: under `php -S` it goes
        // to the server process's stderr, which is gone the moment the console
        // is hidden. Password-reset and verification links were therefore
        // unrecoverable in dev. Also append to a file so the message — and its
        // link — can actually be read back.
        error_log("[Mailer:log] To: $to | Subject: $subject\n--text--\n$textBody\n");

        $logDir = __DIR__ . '/../../storage';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        if (is_dir($logDir) && is_writable($logDir)) {
            $entry = sprintf(
                "[%s] To: %s\nSubject: %s\n%s\n%s\n\n",
                date('Y-m-d H:i:s'), $to, $subject, str_repeat('-', 60), $textBody
            );
            @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        }
        return true;
    }

    private static function formatFrom(string $name, string $address): string {
        $safeName = preg_replace('/[\r\n]+/', '', $name);
        if (preg_match('/[^\x20-\x7E]/', $safeName)) {
            $safeName = '=?UTF-8?B?' . base64_encode($safeName) . '?=';
        } else {
            $safeName = '"' . addcslashes($safeName, '"\\') . '"';
        }
        return $safeName . ' <' . $address . '>';
    }
}
