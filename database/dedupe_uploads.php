<?php
/**
 * Collapse duplicate uploaded artwork — PER USER.
 *
 * The same image uploaded twice used to produce two randomly-named files. This
 * points every row for one user's identical image at a single file and deletes
 * the now-unreferenced copies.
 *
 * Scoped per user on purpose. Sharing one file between accounts would mean one
 * person deleting a design could break someone else's, and it would quietly
 * reveal that another account uploaded the same picture. Two users with the
 * same image keep their own copy.
 *
 * A file is only deleted after every row that referenced it has been repointed
 * at a file with identical contents that exists on disk.
 *
 * Run:  php database/dedupe_uploads.php          (dry run — reports only)
 *       php database/dedupe_uploads.php --apply  (makes the changes)
 */

require_once __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = require __DIR__ . '/../app/config/database.php';

$apply = in_array('--apply', $argv, true);
echo $apply ? "APPLYING changes.\n\n" : "DRY RUN — nothing will be changed. Pass --apply to commit.\n\n";

$hasHash = $pdo->query("SHOW COLUMNS FROM custom_design_uploads LIKE 'file_hash'")->fetch();
if (!$hasHash) {
    echo "file_hash column missing — run database/add_upload_dedup.php first.\n";
    exit(1);
}

// Group by (user, hash): one canonical file per image per user.
$groups = $pdo->query("
    SELECT cd.user_id, u.file_hash,
           COUNT(*) AS rows_n,
           COUNT(DISTINCT u.stored_file_path) AS paths_n
    FROM custom_design_uploads u
    JOIN custom_designs cd ON cd.id = u.design_id
    WHERE u.file_hash IS NOT NULL
    GROUP BY cd.user_id, u.file_hash
    HAVING paths_n > 1
    ORDER BY paths_n DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$groups) { echo "No per-user duplicates found.\n"; exit(0); }

$rowsQ = $pdo->prepare("
    SELECT u.id, u.stored_file_path
    FROM custom_design_uploads u
    JOIN custom_designs cd ON cd.id = u.design_id
    WHERE cd.user_id = ? AND u.file_hash = ?
    ORDER BY u.id ASC
");
$repoint = $pdo->prepare("UPDATE custom_design_uploads SET stored_file_path = ? WHERE id = ?");

$totalRepointed = 0; $totalDeleted = 0; $totalBytes = 0;

foreach ($groups as $g) {
    $rowsQ->execute([$g['user_id'], $g['file_hash']]);
    $rows = $rowsQ->fetchAll(PDO::FETCH_ASSOC);

    // Keep the oldest file that actually exists on disk.
    $keep = null;
    foreach ($rows as $r) {
        if (is_file(__DIR__ . '/../' . $r['stored_file_path'])) { $keep = $r['stored_file_path']; break; }
    }
    if ($keep === null) {
        echo "user {$g['user_id']} hash " . substr($g['file_hash'], 0, 12) . "… — no surviving file, skipping.\n";
        continue;
    }

    $obsolete = [];
    $groupIds = [];
    $repointedHere = 0;
    foreach ($rows as $r) {
        $groupIds[] = (int)$r['id'];
        if ($r['stored_file_path'] === $keep) continue;
        if ($apply) $repoint->execute([$keep, $r['id']]);
        $repointedHere++;
        $obsolete[$r['stored_file_path']] = true;
    }

    echo "user {$g['user_id']}  hash " . substr($g['file_hash'], 0, 12) . "…  "
       . "rows={$g['rows_n']} files={$g['paths_n']} -> keeping " . basename($keep) . "\n";

    // Rows in this group are all moving to $keep, so they must be excluded from
    // the reference check — otherwise a dry run sees the un-updated rows still
    // pointing at the old path and concludes nothing can be deleted.
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

    foreach (array_keys($obsolete) as $path) {
        // Never delete a file some OTHER row points at — a different user's row
        // with the same image, for instance.
        $stillUsed = $pdo->prepare("
            SELECT COUNT(*) FROM custom_design_uploads
            WHERE stored_file_path = ? AND id NOT IN ($placeholders)
        ");
        $stillUsed->execute(array_merge([$path], $groupIds));
        if ((int)$stillUsed->fetchColumn() > 0) {
            echo "    keeping file (still referenced elsewhere): " . basename($path) . "\n";
            continue;
        }
        $full = __DIR__ . '/../' . $path;
        if (is_file($full)) {
            $totalBytes += filesize($full);
            if ($apply) @unlink($full);
            $totalDeleted++;
            echo "    " . ($apply ? "deleted  " : "would delete  ") . basename($path) . "\n";
        }
    }
    $totalRepointed += $repointedHere;
}

printf("\n%s %d row(s) repointed, %d file(s) %s, %s freed.\n",
    $apply ? 'DONE:' : 'WOULD DO:',
    $totalRepointed, $totalDeleted, $apply ? 'deleted' : 'to delete',
    $totalBytes >= 1048576 ? round($totalBytes / 1048576, 2) . ' MB' : round($totalBytes / 1024) . ' KB');

if ($apply) {
    // Empty design folders left behind once their files moved.
    $base = __DIR__ . '/../public/images/designs/uploads';
    foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (count(scandir($dir)) === 2) { @rmdir($dir); echo "removed empty folder " . basename($dir) . "\n"; }
    }
}
