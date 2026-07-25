<?php

class Asset {
    private static ?string $publicRoot = null;

    public static function setPublicRoot(string $path): void {
        self::$publicRoot = rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Return a web-relative asset URL with a `?v=mtime` cache-buster.
     * Falls back to no suffix if the file can't be stat'd so unknown paths still work.
     */
    public static function url(string $path): string {
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        if (self::$publicRoot === null) {
            self::$publicRoot = rtrim(str_replace('\\', '/', __DIR__ . '/../../public'), '/');
        }

        $filePath = self::$publicRoot . $path;
        $mtime = @filemtime($filePath);
        return $mtime ? $path . '?v=' . $mtime : $path;
    }
}
