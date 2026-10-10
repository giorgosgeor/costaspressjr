<?php

/**
 * The visitor's IP address, which the sign-in, contact-form and write limits
 * count by (audit S9). Normally REMOTE_ADDR. Behind a proxy or CDN
 * (Cloudflare, or a host that ends HTTPS at a proxy) REMOTE_ADDR is the
 * proxy's, so every visitor would share a few addresses and one person's
 * failed sign-ins would lock everyone out. List the proxy's ranges in
 * TRUSTED_PROXIES and the address it forwards (CLIENT_IP_HEADER) is used
 * instead, but only for requests that really come from those ranges: anyone
 * can send the header themselves.
 */
final class ClientIp {
    /** The visitor's address. */
    public static function get(): string {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string)Env::get('TRUSTED_PROXIES', '')))));
        if (!$proxies || !self::inRanges($remote, $proxies)) {
            return $remote;
        }

        $name = (string)Env::get('CLIENT_IP_HEADER', '') ?: 'X-Forwarded-For';
        $header = (string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '');
        // X-Forwarded-For lists every hop, the client first. Reading from the
        // right, the first address that isn't one of the proxies is the
        // visitor; anything further left may have been sent by the visitor.
        foreach (array_reverse(array_map('trim', explode(',', $header))) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                break;
            }
            if (!self::inRanges($ip, $proxies)) {
                return $ip;
            }
        }
        return $remote;
    }

    /** The address as the limits store it: hashed, so no raw IP is kept. */
    public static function hash(): string {
        return hash('sha256', self::get() . '|costaspressjr');
    }

    /** Whether $ip is in one of $ranges: "173.245.48.0/20", "2400:cb00::/32" or a single address. */
    private static function inRanges(string $ip, array $ranges): bool {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }
        foreach ($ranges as $range) {
            [$network, $bits] = array_pad(explode('/', $range, 2), 2, null);
            $network = @inet_pton($network);
            if ($network === false || strlen($network) !== strlen($address)) {
                continue;   // malformed, or the other IP version
            }
            if ($bits === null) {
                $bits = strlen($address) * 8;
            } elseif (!ctype_digit($bits) || (int)$bits > strlen($address) * 8) {
                continue;   // a prefix like /99 is a typo, not a range to trust
            }
            $bits = (int)$bits;
            $whole = intdiv($bits, 8);
            if (substr($address, 0, $whole) !== substr($network, 0, $whole)) {
                continue;
            }
            $mask = (0xFF << (8 - $bits % 8)) & 0xFF;
            if ($bits % 8 === 0 || (ord($address[$whole]) & $mask) === (ord($network[$whole]) & $mask)) {
                return true;
            }
        }
        return false;
    }
}
