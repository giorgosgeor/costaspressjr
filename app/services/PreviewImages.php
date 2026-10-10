<?php

/**
 * The rendered previews of a design or a cart line (front.png, back.png, …),
 * kept in public/images/designs/previews/<folder>/ and listed as JSON in the
 * row's preview_images column. The studio draws them on an 800×800 canvas
 * and uploads them as data URLs.
 */
final class PreviewImages {
    private const DIR = 'images/designs/previews';
    private const VIEWS = ['front', 'back', 'left-sleeve', 'right-sleeve', 'front_design'];
    private const MIME_TO_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    // Far above anything the 800×800 canvas produces (the largest so far is
    // 839 KB), and low enough that previews can't be used to fill the disk.
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_SIDE = 1600;

    /**
     * Save each view's data URL into $folder, replacing that view's earlier
     * file. Views that are unknown, not an image, or too big are skipped.
     * Returns view => path relative to public/ for the views saved.
     */
    public static function store(string $folder, array $previews): array {
        $dir = public_path(self::DIR . '/' . $folder);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('Could not create preview folder ' . $folder);
            return [];
        }

        $saved = [];
        foreach ($previews as $view => $dataUrl) {
            if (!in_array($view, self::VIEWS, true) || !is_string($dataUrl)) continue;
            if (!preg_match('/^data:image\/[a-z]+;base64,/', $dataUrl)) continue;
            // Base64 is 4/3 the size of the data: reject before decoding.
            if (strlen($dataUrl) > self::MAX_BYTES * 4 / 3 + 100) continue;

            $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
            if ($raw === false || $raw === '' || strlen($raw) > self::MAX_BYTES) continue;

            // The file type comes from the bytes, never from the data URL's label.
            $info = @getimagesizefromstring($raw);
            $ext = is_array($info) ? (self::MIME_TO_EXT[$info['mime'] ?? ''] ?? null) : null;
            if ($ext === null || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) continue;

            $name = str_replace('-', '_', $view);
            foreach (self::MIME_TO_EXT as $oldExt) {
                if (is_file("$dir/$name.$oldExt")) @unlink("$dir/$name.$oldExt");
            }
            if (file_put_contents("$dir/$name.$ext", $raw) !== false) {
                $saved[$view] = self::DIR . "/$folder/$name.$ext";
            }
        }
        return $saved;
    }

    /**
     * Delete the previews in $folder (every view, whichever views the row
     * still lists), then the folder once empty. Each design and cart line has
     * its own folder; only files directly inside it are touched.
     */
    public static function deleteFolder(string $folder): void {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $folder)) return;
        $root = realpath(public_path(self::DIR));
        $dir = realpath(public_path(self::DIR . '/' . $folder));
        if ($root === false || $dir === false || dirname($dir) !== $root) return;

        foreach (self::VIEWS as $view) {
            foreach (self::MIME_TO_EXT as $ext) {
                $file = $dir . DIRECTORY_SEPARATOR . str_replace('-', '_', $view) . '.' . $ext;
                if (is_file($file)) @unlink($file);
            }
        }
        @rmdir($dir); // only succeeds once the folder is empty
    }
}
