<?php

class Csrf {
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_csrf';
    private const HEADER_NAME = 'X-CSRF-Token';

    public static function token(): string {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function metaTag(): string {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $submitted): bool {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    public static function tokenFromRequest(): ?string {
        if (!empty($_POST[self::FIELD_NAME])) {
            return (string)$_POST[self::FIELD_NAME];
        }

        $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper(self::HEADER_NAME));
        if (!empty($_SERVER[$headerKey])) {
            return (string)$_SERVER[$headerKey];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json) && !empty($json[self::FIELD_NAME])) {
                    return (string)$json[self::FIELD_NAME];
                }
            }
        }

        return null;
    }

    public static function validateRequest(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

        if (self::check(self::tokenFromRequest())) return;

        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        if (!headers_sent()) {
            header('Cache-Control: no-store');
        }
        echo json_encode(['error' => 'CSRF token mismatch. Please refresh the page and try again.']);
        exit;
    }
}
