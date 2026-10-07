<?php
/**
 * Refresh the ACS pickup points from the ACS web service.
 *
 *   php database/sync_acs_points.php
 *
 * Needs ACS_API_KEY, ACS_COMPANY_ID, ACS_COMPANY_PASSWORD, ACS_USER_ID and
 * ACS_USER_PASSWORD in .env (issued by ACS). Safe to run from cron — daily is
 * plenty; the admin page has a button for the same thing.
 */

require __DIR__ . '/../app/bootstrap.php';

$db = require __DIR__ . '/../app/config/database.php';

if (!AcsClient::configured()) {
    fwrite(STDERR, "ACS credentials are not set in .env — see .env.example.\n");
    exit(1);
}

try {
    $r = Pickup::syncFromAcs($db);
    echo "ACS points: {$r['added']} added, {$r['updated']} updated, {$r['deactivated']} deactivated.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Sync failed: ' . $e->getMessage() . "\n");
    exit(1);
}
