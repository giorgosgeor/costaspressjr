<?php

/**
 * Time-based one-time passwords (RFC 6238): the 6-digit codes authenticator
 * apps show, an HMAC-SHA1 of the current 30-second time step keyed with a
 * secret shared once at setup. Admins' second sign-in step
 * (TwoFactorController).
 */
final class Totp {
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // RFC 4648

    /** A new random 160-bit secret, in the base32 form the apps take. */
    public static function newSecret(): string {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * The time step $code belongs to, checking the current step and one on
     * either side (phone clocks drift), or null. Steps up to $lastStep are
     * refused, so a code that was accepted once can't be replayed.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $now = null): ?int {
        $key = self::base32Decode($secret);
        if ($key === '' || !preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }
        $current = intdiv($now ?? time(), self::PERIOD);
        for ($step = $current - 1; $step <= $current + 1; $step++) {
            if ($lastStep !== null && $step <= $lastStep) continue;
            if (hash_equals(self::codeAt($key, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    /** The otpauth:// link an authenticator app opens to add the account. */
    public static function uri(string $secret, string $account, string $issuer): string {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** The code for one time step, from the raw (decoded) key. */
    public static function codeAt(string $key, int $step): string {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string)($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    /** The raw key, or '' when $secret isn't base32 (spaces are allowed). */
    private static function base32Decode(string $secret): string {
        $bits = '';
        foreach (str_split(strtoupper(str_replace(' ', '', $secret))) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) return '';
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }
}
