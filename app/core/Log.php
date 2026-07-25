<?php

/**
 * Tiny logging shim. Today everything goes to PHP's error_log, but having one
 * front door means swapping in Sentry, Bugsnag, or a self-hosted log
 * aggregator later is a one-file change. The signature mirrors PSR-3 loosely
 * so existing call sites need minimal updates.
 *
 * Usage:
 *   Log::error('Cart save failed', ['order' => $orderId, 'reason' => $msg]);
 *   Log::warning('Rate limit hit', ['ip_hash' => $hash]);
 *
 * Existing `error_log("…")` calls keep working — this is opt-in.
 */
class Log {
    public static function info(string $message, array $context = []): void {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::write('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::write('ERROR', $message, $context);
    }

    public static function exception(\Throwable $e, array $context = []): void {
        $context['file'] = $e->getFile();
        $context['line'] = $e->getLine();
        $context['type'] = get_class($e);
        self::write('ERROR', $e->getMessage(), $context);
    }

    private static function write(string $level, string $message, array $context): void {
        $line = '[' . $level . '] ' . $message;
        if (!empty($context)) {
            // Compact, single-line JSON so log aggregators can parse it.
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        error_log($line);
    }
}
