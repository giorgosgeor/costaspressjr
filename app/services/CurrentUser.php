<?php

/**
 * Facts about the signed-in visitor that the page layout needs on every
 * page. They used to be queried inside the layout templates, which only
 * worked where a controller happened to have a $db variable in scope — on
 * almost every page neither lookup ran.
 */
class CurrentUser {
    /** True when the signed-in customer hasn't confirmed their email yet. */
    public static function needsEmailVerification(?PDO $db): bool {
        if (!Auth::check() || !$db) {
            return false;
        }
        try {
            $stmt = $db->prepare("SELECT email_verified_at FROM users WHERE id = ?");
            $stmt->execute([Auth::userId()]);
            $row = $stmt->fetch();
            return $row && $row['email_verified_at'] === null;
        } catch (PDOException $e) {
            // email_verified_at may not exist yet if the migration hasn't run.
            return false;
        }
    }

    /** How long the "confirm your email" banner stays away after Resend. */
    private const VERIFY_BANNER_SNOOZE = 600;

    /**
     * What the "confirm your email" banner says on this page, or null for no
     * banner. Right after the customer asks for a new link it says 'sent',
     * once; then it stays away for ten minutes so it doesn't nag while the
     * email is on its way, and comes back ('unverified', with the Resend
     * button) only if the address still isn't confirmed by then.
     */
    public static function verifyBanner(?PDO $db): ?string {
        if (!self::needsEmailVerification($db)) {
            return null;
        }
        if (!empty($_SESSION['verify_banner_sent'])) {
            unset($_SESSION['verify_banner_sent']);
            return 'sent';
        }
        if ((int)($_SESSION['verify_banner_hidden_until'] ?? 0) > time()) {
            return null;
        }
        return 'unverified';
    }

    /** Called when a new verification link is requested (see verifyBanner). */
    public static function snoozeVerifyBanner(): void {
        $_SESSION['verify_banner_sent'] = true;
        $_SESSION['verify_banner_hidden_until'] = time() + self::VERIFY_BANNER_SNOOZE;
    }

    /**
     * Whether the cookie notice was already accepted. Guests keep the answer
     * in a cookie the browser checks; signed-in customers keep it on their
     * account, read once per session and then served from the session.
     */
    public static function cookieAccepted(?PDO $db, mixed $user = null): int {
        if (!Auth::check()) {
            return 0;
        }
        if (isset($_SESSION['cookie_accepted'])) {
            return (int)$_SESSION['cookie_accepted'];
        }
        // $user is whatever the page has under that name: a users row, or
        // false/null where a lookup found nobody.
        if (is_array($user) && isset($user['cookie_accepted'])) {
            return $_SESSION['cookie_accepted'] = (int)$user['cookie_accepted'];
        }
        if (!$db) {
            return 0;
        }
        try {
            $stmt = $db->prepare("SELECT cookie_accepted FROM users WHERE id = ?");
            $stmt->execute([Auth::userId()]);
            return $_SESSION['cookie_accepted'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            // Leave it at 0 and show the notice rather than failing the page.
            return 0;
        }
    }
}
