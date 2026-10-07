<?php

/**
 * Production's last line of defence: an uncaught exception is logged with
 * the URL it happened on, and the visitor gets a plain apology page instead
 * of a stack trace.
 */
class ErrorHandler {
    public static function register(): void {
        set_exception_handler(function (Throwable $e): void {
            Log::exception($e, ['url' => $_SERVER['REQUEST_URI'] ?? null]);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Something went wrong</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;color:#1e293b;"><h1>Something went wrong.</h1><p>We\'re having trouble loading this page. Please try again in a moment.</p><p><a href="/">Back to homepage</a></p></body></html>';
            exit;
        });
    }
}
