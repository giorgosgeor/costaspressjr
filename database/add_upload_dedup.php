<?php
/**
 * Content hashing for uploaded artwork, so the same image isn't stored over and
 * over.
 *
 * Every upload currently writes a brand-new randomly-named file, even when the
 * bytes are identical to something the user already uploaded. One picture used
 * on four designs became four files on disk and four rows in "My Uploads".
 *
 * A SHA-256 of the file contents identifies duplicates exactly — same hash means
 * same bytes. This adds the column and backfills it for existing rows; the
 * reuse-on-save logic lives in CustomDesign::saveBase64Image(), and
 * dedupe_uploads.php collapses the duplicates that already exist.
 *
 * Run:  php database/add_upload_dedup.php
 * Idempotent: skips the column if present, only hashes rows still NULL.
 */

require_once __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = require __DIR__ . '/../app/config/database.php';

$exists = $pdo->query("SHOW COLUMNS FROM custom_design_uploads LIKE 'file_hash'")->fetch();
if (!$exists) {
    $pdo->exec("ALTER TABLE custom_design_uploads ADD COLUMN file_hash CHAR(64) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE custom_design_uploads ADD INDEX idx_upload_hash (file_hash)");
    echo "added file_hash column and index.\n";
} else {
    echo "file_hash already exists — skipping.\n";
}

$rows = $pdo->query("
    SELECT id, stored_file_path
    FROM custom_design_uploads
    WHERE file_hash IS NULL AND stored_file_path IS NOT NULL AND stored_file_path <> ''
")->fetchAll(PDO::FETCH_ASSOC);

$upd     = $pdo->prepare("UPDATE custom_design_uploads SET file_hash = ? WHERE id = ?");
$hashed  = 0;
$missing = 0;
foreach ($rows as $r) {
    $full = __DIR__ . '/../' . $r['stored_file_path'];
    if (!is_file($full)) { $missing++; continue; }
    $upd->execute([hash_file('sha256', $full), $r['id']]);
    $hashed++;
}
echo "hashed $hashed row(s); $missing row(s) had no file on disk.\n";

$dupes = $pdo->query("
    SELECT file_hash, COUNT(*) c, COUNT(DISTINCT stored_file_path) paths
    FROM custom_design_uploads
    WHERE file_hash IS NOT NULL
    GROUP BY file_hash HAVING paths > 1
")->fetchAll(PDO::FETCH_ASSOC);
echo count($dupes) . " image(s) are stored under more than one path.\n";
foreach ($dupes as $d) {
    echo "  {$d['file_hash']}  rows={$d['c']}  files={$d['paths']}\n";
}
echo $dupes ? "\nRun database/dedupe_uploads.php to collapse them.\n" : "";
