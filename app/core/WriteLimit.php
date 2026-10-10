<?php

/**
 * How often one visitor may write to the server: design saves, cart lines and
 * preview images all create rows and files (audit S3). Each kind is counted
 * per browser session (in the session) and per IP address (in write_log) over
 * the last hour. The IP limit is the higher one, so a few people sharing an
 * address (an office, a mobile network) don't block each other, while a
 * script that drops its cookies to get fresh sessions is still capped.
 */
final class WriteLimit {
    // kind => [per session, per IP], per hour.
    private const LIMITS = [
        'design'  => [30, 60],    // design saves and updates
        'cart'    => [60, 120],   // cart lines: one per size ordered
        'preview' => [100, 200],  // preview images: one batch per save or cart line
    ];
    private const WINDOW_SECONDS = 3600;

    /**
     * Count one write of $kind and return true, or return false without
     * counting when the visitor has reached a limit.
     */
    public static function allow(PDO $db, string $kind): bool {
        [$perSession, $perIp] = self::LIMITS[$kind];

        $since = time() - self::WINDOW_SECONDS;
        $recent = array_values(array_filter(
            (array)($_SESSION['write_log'][$kind] ?? []),
            fn($t) => is_int($t) && $t > $since
        ));
        if (count($recent) >= $perSession) {
            return false;
        }

        // Hashed like login_attempts, so no raw IP is stored. created_at is
        // MySQL's clock, so the window is measured on it too.
        $ipHash = ClientIp::hash();
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM write_log WHERE ip_hash = ? AND kind = ? AND created_at > NOW() - INTERVAL " . self::WINDOW_SECONDS . " SECOND");
            $stmt->execute([$ipHash, $kind]);
            if ((int)$stmt->fetchColumn() >= $perIp) {
                return false;
            }
            $db->prepare("INSERT INTO write_log (ip_hash, kind) VALUES (?, ?)")->execute([$ipHash, $kind]);
            if (random_int(1, 100) === 1) {
                $db->exec("DELETE FROM write_log WHERE created_at < NOW() - INTERVAL 1 DAY");
            }
        } catch (PDOException $e) {
            // write_log missing (migration not run): the session limit still applies.
            error_log('Write limit check failed: ' . $e->getMessage());
        }

        $recent[] = time();
        $_SESSION['write_log'][$kind] = $recent;
        return true;
    }

    /** The 429 reply for a visitor over a limit, as JSON for the page's script. */
    public static function refuse(): void {
        http_response_code(429);
        header('Retry-After: ' . self::WINDOW_SECONDS);
        header('Content-Type: application/json');
        echo json_encode(['error' => I18n::t('limit.too_many')]);
    }
}
