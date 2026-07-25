<?php

class Upload {
    public const DEFAULT_MAX_BYTES = 10 * 1024 * 1024;

    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and save an uploaded image. Returns ['path' => relative, 'ext' => ext]
     * on success. On failure, returns ['error' => string].
     *
     * @param array  $file        Entry from $_FILES.
     * @param string $targetDir   Absolute directory to save into.
     * @param string $baseName    Filename without extension. This helper chooses the extension.
     * @param int    $maxBytes    Max file size in bytes.
     * @param string $publicRoot  Absolute path to /public (used to build a web-relative path).
     */
    public static function saveImage(array $file, string $targetDir, string $baseName, int $maxBytes = self::DEFAULT_MAX_BYTES, ?string $publicRoot = null): array {
        $err = self::validate($file, $maxBytes);
        if ($err !== null) return ['error' => $err];

        $mime = self::detectImageMime($file['tmp_name']);
        if ($mime === null) {
            return ['error' => 'Unsupported or invalid image file.'];
        }
        $ext = self::MIME_TO_EXT[$mime];

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return ['error' => 'Failed to create upload directory.'];
        }

        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);
        if ($safeBase === '' || $safeBase === null) $safeBase = 'file';

        self::removeSiblings($targetDir, $safeBase);

        $fullPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $safeBase . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['error' => 'Failed to store uploaded file.'];
        }

        @chmod($fullPath, 0644);

        $relative = null;
        if ($publicRoot !== null) {
            $normalizedRoot = rtrim(str_replace('\\', '/', realpath($publicRoot) ?: $publicRoot), '/');
            $normalizedFull = str_replace('\\', '/', realpath($fullPath) ?: $fullPath);
            if ($normalizedRoot !== '' && strpos($normalizedFull, $normalizedRoot . '/') === 0) {
                $relative = substr($normalizedFull, strlen($normalizedRoot) + 1);
            }
        }

        return [
            'path' => $relative,
            'fullPath' => $fullPath,
            'ext' => $ext,
            'mime' => $mime,
        ];
    }

    private static function validate(array $file, int $maxBytes): ?string {
        if (!isset($file['error'])) return 'Invalid upload payload.';

        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large.';
            case UPLOAD_ERR_PARTIAL:
                return 'File upload was interrupted.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return 'Server could not accept the upload.';
            default:
                return 'Upload failed.';
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'Invalid upload source.';
        }

        $size = $file['size'] ?? filesize($file['tmp_name']);
        if ($size === false || $size <= 0) return 'Uploaded file is empty.';
        if ($size > $maxBytes) return 'File exceeds maximum size.';

        return null;
    }

    /**
     * Returns the canonical MIME only if the file is a real, allowlisted image.
     */
    private static function detectImageMime(string $path): ?string {
        $info = @getimagesize($path);
        if (!is_array($info) || empty($info['mime'])) return null;

        $mime = strtolower($info['mime']);

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $finfoMime = strtolower((string)finfo_file($finfo, $path));
                finfo_close($finfo);
                if ($finfoMime !== '' && $finfoMime !== $mime) return null;
            }
        }

        return isset(self::MIME_TO_EXT[$mime]) ? $mime : null;
    }

    /**
     * Delete any existing files in $dir whose name (without extension) equals $baseName,
     * so uploading a .png after a .jpg replaces the old one instead of leaving both.
     */
    private static function removeSiblings(string $dir, string $baseName): void {
        foreach (self::MIME_TO_EXT as $ext) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
            if (is_file($candidate)) @unlink($candidate);
        }
    }
}
