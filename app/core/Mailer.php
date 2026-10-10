<?php

/**
 * Minimal mail abstraction. Supports three transports controlled by env:
 *   MAIL_TRANSPORT=log   → write the message to error_log (dev default).
 *   MAIL_TRANSPORT=smtp  → talk SMTP to SMTP_HOST (production). Works with
 *                          any provider: Brevo, Postmark, SES, Gmail, the
 *                          host's own mailbox. See .env.example.
 *   MAIL_TRANSPORT=mail  → use PHP's built-in mail() function. Requires an
 *                          SMTP configuration on the host OR a properly
 *                          configured sendmail / Windows mail wrapper. Most
 *                          shared hosts send this unauthenticated, so it
 *                          tends to land in spam — prefer smtp.
 */
class Mailer {
    /** Messages later() has queued, as send() arguments. */
    private static array $queue = [];

    /**
     * send(), but once the reply has reached the browser: a slow mail server
     * (SMTP can take seconds) never holds up a page, and the reply's timing
     * no longer shows whether an email went out, which would tell a stranger
     * whether an address has an account (audit S6, P8). On a server that
     * can't finish a reply early (PHP's built-in server, mod_php) it still
     * goes, at the end of the request. Nobody waits for the outcome, so
     * failures are only logged; use send() when the page reports it.
     */
    public static function later(string $to, string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): void {
        if (!self::$queue) {
            register_shutdown_function([self::class, 'sendQueued']);
        }
        self::$queue[] = [$to, $subject, $htmlBody, $textBody, $replyTo];
    }

    /** @internal The shutdown function later() registers. */
    public static function sendQueued(): void {
        // The session first, so the visitor's next page isn't kept waiting
        // on its lock; then the reply itself.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();       // PHP-FPM
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();     // LiteSpeed, common on shared hosting
        }
        ignore_user_abort(true);
        while ($message = array_shift(self::$queue)) {
            try {
                self::send(...$message);
            } catch (Throwable $e) {
                error_log('[Mailer] queued message failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param string|null $replyTo where replies go (the contact form sets the
     *                             customer's address); defaults to the sender.
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = '', ?string $replyTo = null): bool {
        $transport   = Env::get('MAIL_TRANSPORT', 'log');
        $fromAddress = Env::get('MAIL_FROM_ADDRESS', 'no-reply@costaspressjr.local');
        $fromName    = Env::get('MAIL_FROM_NAME', 'Costaspressjr');

        // Every address below lands in a header or an SMTP command, where a
        // stray CR/LF would let one value smuggle in another header.
        foreach ([$to, $fromAddress, $replyTo] as $address) {
            if ($address !== null && !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                error_log('[Mailer] refused invalid address: ' . json_encode($address));
                return false;
            }
        }
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));

        if ($textBody === '') {
            $textBody = trim(strip_tags(preg_replace(['/<br\s*\/?>/i', '/<\/p>/i'], ["\n", "\n\n"], $htmlBody)));
        }

        if ($transport === 'log') {
            return self::sendToLog($to, $subject, $textBody);
        }

        // Greek subjects must be MIME-encoded; raw UTF-8 in a header shows up
        // garbled in some clients and is a spam signal in others.
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        $boundary = '=_mailer_' . bin2hex(random_bytes(12));
        $headers  = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($fromAddress, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . self::formatFrom($fromName, $fromAddress),
            'Reply-To: ' . ($replyTo ?? $fromAddress),
            'X-Mailer: costaspressjr',
        ];

        // Quoted-printable keeps every line under SMTP's 998-character limit.
        // The HTML bodies are built as one long line, which 8bit would send
        // as-is and some servers reject or truncate.
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= self::qp($textBody) . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= self::qp($htmlBody) . "\r\n\r\n";
        $body .= "--$boundary--";

        if ($transport === 'smtp') {
            $headers[] = 'To: ' . $to;
            $headers[] = 'Subject: ' . $encodedSubject;
            try {
                self::sendSmtp($fromAddress, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
                return true;
            } catch (\RuntimeException $e) {
                error_log('[Mailer:smtp] to ' . $to . ' failed: ' . $e->getMessage());
                return false;
            }
        }

        if ($transport === 'mail') {
            return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        }

        error_log('[Mailer] unknown MAIL_TRANSPORT "' . $transport . '" — message to ' . $to . ' not sent');
        return false;
    }

    /**
     * Dev transport.
     *
     * error_log() alone was not enough to work with: under `php -S` it goes
     * to the server process's stderr, which is gone the moment the console
     * is hidden. Password-reset and verification links were therefore
     * unrecoverable in dev. Also append to a file so the message — and its
     * link — can actually be read back.
     */
    private static function sendToLog(string $to, string $subject, string $textBody): bool {
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

    /**
     * One message over one SMTP connection. Throws RuntimeException with the
     * server's reply on any failure; the password never appears in it.
     *
     *   SMTP_ENCRYPTION=tls   STARTTLS, usually port 587 (default)
     *   SMTP_ENCRYPTION=ssl   implicit TLS, usually port 465
     *   SMTP_ENCRYPTION=none  plain text — local test servers only
     */
    private static function sendSmtp(string $from, string $to, string $data): void {
        $host       = (string)Env::get('SMTP_HOST', '');
        $encryption = strtolower((string)Env::get('SMTP_ENCRYPTION', 'tls'));
        $port       = (int)Env::get('SMTP_PORT', $encryption === 'ssl' ? '465' : '587');
        $username   = (string)Env::get('SMTP_USERNAME', '');
        $password   = (string)Env::get('SMTP_PASSWORD', '');
        $timeout    = 15;
        if ($host === '') {
            throw new \RuntimeException('SMTP_HOST is not configured');
        }

        // Certificates are always verified: a man in the middle on the way to
        // the mail server would otherwise collect SMTP_PASSWORD.
        $context = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'peer_name'        => $host,
        ]]);
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $fp = @stream_socket_client("$scheme://$host:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            throw new \RuntimeException("connect to $host:$port failed: $errstr ($errno)");
        }
        stream_set_timeout($fp, $timeout);

        try {
            $ehloName = parse_url((string)Env::get('APP_URL', ''), PHP_URL_HOST) ?: 'localhost';

            self::expect($fp, [220]);
            $features = self::command($fp, "EHLO $ehloName", [250]);

            if ($encryption === 'tls') {
                self::command($fp, 'STARTTLS', [220]);
                $methods = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!@stream_socket_enable_crypto($fp, true, $methods)) {
                    throw new \RuntimeException('STARTTLS handshake failed');
                }
                // The pre-TLS feature list can't be trusted; ask again.
                $features = self::command($fp, "EHLO $ehloName", [250]);
            }

            if ($username !== '') {
                if (preg_match('/^250[ -]AUTH[ =].*\bPLAIN\b/mi', $features)) {
                    self::command($fp, 'AUTH PLAIN ' . base64_encode("\0$username\0$password"), [235], 'AUTH PLAIN');
                } else {
                    self::command($fp, 'AUTH LOGIN', [334]);
                    self::command($fp, base64_encode($username), [334], 'AUTH LOGIN username');
                    self::command($fp, base64_encode($password), [235], 'AUTH LOGIN password');
                }
            }

            self::command($fp, "MAIL FROM:<$from>", [250]);
            self::command($fp, "RCPT TO:<$to>", [250, 251]);
            self::command($fp, 'DATA', [354]);
            // A line that starts with "." would end DATA early; SMTP escapes it
            // by doubling the dot (RFC 5321 §4.5.2).
            $data = preg_replace('/^\./m', '..', $data);
            self::command($fp, $data . "\r\n.", [250], 'message body');
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }

    /** Send one command and require one of $codes back. $label replaces the command in errors. */
    private static function command($fp, string $line, array $codes, ?string $label = null): string {
        if (@fwrite($fp, $line . "\r\n") === false) {
            throw new \RuntimeException('write failed during ' . ($label ?? $line));
        }
        return self::expect($fp, $codes, $label ?? $line);
    }

    /** Read a (possibly multi-line) reply and check its code. */
    private static function expect($fp, array $codes, string $after = 'connect'): string {
        $reply = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $reply .= $line;
            // The last line of a reply has a space after the code, not a dash.
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($reply, 0, 3);
        if (!in_array($code, $codes, true)) {
            $meta = stream_get_meta_data($fp);
            $why  = $meta['timed_out'] ? 'timed out' : trim($reply);
            throw new \RuntimeException("after $after: " . ($why !== '' ? $why : 'connection closed'));
        }
        return $reply;
    }

    /** CRLF-normalise, then quoted-printable encode (CRLF stays a hard line break). */
    private static function qp(string $s): string {
        return quoted_printable_encode(preg_replace("/\r\n|\r|\n/", "\r\n", $s));
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
