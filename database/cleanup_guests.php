<?php
/**
 * Delete abandoned guest data (security audit S3): guest users untouched for
 * 30 days, with their carts, designs, uploaded images and previews.
 *
 * A guest is the user row a shopper without an account buys under
 * (Auth::effectiveUserId). Its session ends when the browser closes, after
 * which nobody can reach that cart or those designs again, yet they stayed
 * on disk for ever. "Untouched" means the account, its cart and cart lines,
 * its designs and any checkout it started are all older than 30 days.
 *
 * Always kept:
 *  - guests with an order. orders.user_id cascades, so deleting the user
 *    would delete the order.
 *  - guests owning a design another cart or an order uses. Signing in moves
 *    the guest's cart into the account (AuthController::mergeGuestCart) but
 *    leaves the designs on the guest row, and an order prints from them.
 *
 * Run daily from cron (DEPLOY.md step 6):
 *   php database/cleanup_guests.php          (dry run: reports only)
 *   php database/cleanup_guests.php --apply  (deletes)
 */

require __DIR__ . '/../app/bootstrap.php';
$pdo = require __DIR__ . '/../app/config/database.php';

const IDLE_DAYS = 30;

$apply = in_array('--apply', $argv, true);
echo date('Y-m-d H:i:s') . ' ' . ($apply ? "APPLYING changes.\n" : "DRY RUN: nothing will be changed. Pass --apply to delete.\n");

$cutoff = 'NOW() - INTERVAL ' . IDLE_DAYS . ' DAY';
$guests = $pdo->query("
    SELECT u.id
    FROM users u
    WHERE u.role = 'guest' AND u.email LIKE '%@guest.invalid'
      AND u.created_at < $cutoff
      AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)
      AND NOT EXISTS (SELECT 1 FROM carts c WHERE c.user_id = u.id
                      AND (c.created_at >= $cutoff OR c.updated_at >= $cutoff))
      AND NOT EXISTS (SELECT 1 FROM carts c JOIN cart_items ci ON ci.cart_id = c.id
                      WHERE c.user_id = u.id AND ci.created_at >= $cutoff)
      AND NOT EXISTS (SELECT 1 FROM custom_designs d WHERE d.user_id = u.id
                      AND (d.created_at >= $cutoff OR d.updated_at >= $cutoff))
      AND NOT EXISTS (SELECT 1 FROM pending_checkouts p WHERE p.user_id = u.id AND p.created_at >= $cutoff)
      AND NOT EXISTS (SELECT 1 FROM custom_designs d JOIN order_items oi ON oi.design_id = d.id
                      WHERE d.user_id = u.id)
      AND NOT EXISTS (SELECT 1 FROM custom_designs d JOIN cart_items ci ON ci.design_id = d.id
                      JOIN carts c ON c.id = ci.cart_id
                      WHERE d.user_id = u.id AND c.user_id <> u.id)
    ORDER BY u.id
")->fetchAll(PDO::FETCH_COLUMN);

$linesQ   = $pdo->prepare("SELECT ci.id, ci.path_token FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?");
$designsQ = $pdo->prepare("SELECT id FROM custom_designs WHERE user_id = ?");
$designs  = new CustomDesign($pdo);
$totals   = ['guests' => 0, 'lines' => 0, 'designs' => 0, 'kept' => 0];

foreach ($guests as $guestId) {
    $guestId = (int)$guestId;
    $linesQ->execute([$guestId]);
    $lines = $linesQ->fetchAll(PDO::FETCH_ASSOC);
    $designsQ->execute([$guestId]);
    $designIds = $designsQ->fetchAll(PDO::FETCH_COLUMN);
    echo "guest $guestId: " . count($lines) . " cart line(s), " . count($designIds) . " design(s)";

    if (!$apply) {
        echo "\n";
        $totals['guests']++;
        $totals['lines'] += count($lines);
        $totals['designs'] += count($designIds);
        continue;
    }

    // Cart rows first (cart_items and cart_item_uploads cascade), then the
    // lines' previews: the guest's designs are then used by no cart.
    $pdo->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$guestId]);
    foreach ($lines as $line) {
        Cart::deletePreviews((int)$line['id'], $line['path_token']);
    }

    // Each design with its files. delete() refuses one still in use; the
    // guest is then kept, since deleting the user would cascade to it.
    $refused = 0;
    foreach ($designIds as $designId) {
        if (!$designs->delete((int)$designId)) $refused++;
    }
    if ($refused) {
        echo ": KEPT, $refused design(s) still in use\n";
        $totals['kept']++;
        continue;
    }

    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$guestId]);
    $pdo->prepare("DELETE FROM pending_checkouts WHERE user_id = ?")->execute([$guestId]);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'guest'")->execute([$guestId]);
    echo ": deleted\n";
    $totals['guests']++;
    $totals['lines'] += count($lines);
    $totals['designs'] += count($designIds);
}

printf("%s %d guest(s), %d cart line(s), %d design(s)%s.\n",
    $apply ? 'DONE: deleted' : 'WOULD DELETE:',
    $totals['guests'], $totals['lines'], $totals['designs'],
    $totals['kept'] ? ", kept {$totals['kept']} guest(s) whose designs are in use" : '');
