<?php

class Env {
    private static bool $loaded = false;

    public static function load(string $path): void {
        if (self::$loaded || !is_file($path)) return;
        self::$loaded = true;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;

            $eq = strpos($line, '=');
            if ($eq === false) continue;

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));

            if (strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"')
                 || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string {
        $value = getenv($key);
        if ($value !== false) return $value;
        return $_ENV[$key] ?? $default;
    }

    public static function required(string $key): string {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException("Missing required environment variable: $key");
        }
        return $value;
    }
}
