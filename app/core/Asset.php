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

    /**
     * Stylesheets joined, in order, and minified into one file,
     * public/css/build/<name>.css (audit P5: the site's CSS was 221 KB of
     * comments and indentation). Its first line names the hash of the
     * sources it was built from; when one changes it is built again (on a
     * developer's machine: commit it with the CSS). Returns the <link> tags:
     * the built file's, or, where it can't be written, the sources' own.
     */
    public static function styles(string $name, array $sources): string {
        if (self::$publicRoot === null) {
            self::url('/');
        }
        $hash = '';
        foreach ($sources as $source) {
            $hash .= (string)@md5_file(self::$publicRoot . $source);
        }
        $path  = '/css/build/' . $name . '.css';
        $file  = self::$publicRoot . $path;
        $stamp = '/* ' . md5($hash) . ' */';

        $current = is_file($file) && @file_get_contents($file, false, null, 0, strlen($stamp)) === $stamp;
        if (!$current) {
            $css = '';
            foreach ($sources as $source) {
                $css .= self::minifyCss((string)@file_get_contents(self::$publicRoot . $source)) . "\n";
            }
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $current = (is_dir(dirname($file)) || @mkdir(dirname($file), 0755, true))
                && @file_put_contents($tmp, $stamp . "\n/* Built by Asset::styles() from " . implode(', ', $sources) . "; edit those. */\n" . $css) !== false
                && @rename($tmp, $file);
            @unlink($tmp);
        }
        $link = fn(string $href) => '<link rel="stylesheet" href="' . htmlspecialchars(self::url($href), ENT_QUOTES, 'UTF-8') . '">';
        return $current ? $link($path) : implode("\n    ", array_map($link, $sources));
    }

    /**
     * Comments and spare whitespace out of a stylesheet, and nothing else.
     * Whitespace goes only where it can't matter: next to { } ; , after ":"
     * and "(", and before ")". Whitespace before ":" ("a :hover"), in
     * "and (" and around + and - (calc) is kept. Strings and url(…) are
     * copied as they are.
     */
    private static function minifyCss(string $css): string {
        $out = '';
        $space = false;     // whitespace seen since the last character written
        $len = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $c = $css[$i];
            if ($c === '/' && ($css[$i + 1] ?? '') === '*') {             // comment
                // Not whitespace: ".a/* x */.b" is ".a.b", not ".a .b".
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if (ctype_space($c)) {
                $space = true;
                continue;
            }
            if ($space && $out !== '' && !str_contains('{};,:(', substr($out, -1)) && !str_contains('{};,)', $c)) {
                $out .= ' ';
            }
            $space = false;
            if ($c === '"' || $c === "'") {                                // string
                $j = $i + 1;
                while ($j < $len && $css[$j] !== $c) {
                    $j += $css[$j] === '\\' ? 2 : 1;
                }
                $out .= substr($css, $i, $j - $i + 1);
                $i = $j;
            } elseif ($c === '\\') {                                        // escape
                $out .= substr($css, $i, 2);
                $i++;
            } elseif (($c === 'u' || $c === 'U') && strcasecmp(substr($css, $i, 4), 'url(') === 0
                      && !preg_match('/[\w-]$/', $out)) {                  // url(…)
                $j = $i + 4;
                while ($j < $len && $css[$j] !== ')') {
                    if ($css[$j] === '"' || $css[$j] === "'") {
                        $q = $css[$j++];
                        while ($j < $len && $css[$j] !== $q) $j += $css[$j] === '\\' ? 2 : 1;
                    }
                    $j++;
                }
                $out .= substr($css, $i, $j - $i + 1);
                $i = $j;
            } elseif ($c === '}' && str_ends_with($out, ';')) {
                $out = substr($out, 0, -1) . '}';                           // last ; of a block
            } else {
                $out .= $c;
            }
        }
        return trim($out);
    }
}
