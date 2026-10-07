<?php
/**
 * Recover paid orders that were never created.
 *
 *   php database/reconcile_payments.php               last 7 days
 *   php database/reconcile_payments.php --days=2
 *   php database/reconcile_payments.php --min-age=0   include payments made just now
 *
 * Normally a paid checkout becomes an order within seconds — on the
 * confirmation page, or through the payment_intent.succeeded webhook if the
 * customer never gets there. This is the net under both: if the site was down
 * for longer than Stripe keeps retrying the webhook (three days in live mode),
 * the order would otherwise never exist.
 *
 * It lists this checkout's succeeded PaymentIntents from Stripe and puts each
 * through OrderPlacement::place(), the same code the webhook uses. place() is
 * idempotent: a payment that already has an order costs one database query
 * and nothing else; a missing one is created, or refunded if it can't be.
 *
 * Payments younger than --min-age minutes (default 10) are left to the
 * webhook, which is still on its way — otherwise every run would "recover"
 * orders that were about to be placed anyway.
 *
 * The shop is emailed (PaymentAlert) when an order had to be recovered or a
 * payment can't be settled. Exit code 1 while anything is unsettled, so cron
 * can flag it. Safe to run as often as you like; every 30 minutes is plenty:
 *
 *   0,30 * * * *  php /path/to/database/reconcile_payments.php >> /path/to/storage/reconcile.log 2>&1
 */

require __DIR__ . '/../app/bootstrap.php';

// Messages from place() end up in the output and the alert email.
I18n::setLocale('en', false);

$opts   = getopt('', ['days::', 'min-age::']);
$days   = max(1, (int)($opts['days'] ?? 7));
$minAge = max(0, (int)($opts['min-age'] ?? 10));

$db        = require __DIR__ . '/../app/config/database.php';
$placement = new OrderPlacement($db);

$counts     = ['checked' => 0, 'had_order' => 0, 'refunded' => 0];
$recovered  = [];
$unresolved = [];

$params = [
    'limit'        => 100,
    'created[gte]' => time() - $days * 86400,
    'created[lte]' => time() - $minAge * 60,
];

try {
    do {
        $page = Stripe::listPaymentIntents($params);
        foreach ($page['data'] ?? [] as $pi) {
            // Only paid intents this checkout created. Anything else on the
            // account (other tools, the Dashboard) is none of our business.
            if (($pi['status'] ?? '') !== 'succeeded' || ($pi['metadata']['checkout'] ?? '') !== OrderPlacement::TAG) {
                continue;
            }
            $counts['checked']++;
            $id     = $pi['id'];
            $amount = (int)($pi['amount'] ?? 0);

            $r = $placement->place($id, null);
            if ($r['status'] === 'exists') {
                $counts['had_order']++;
            } elseif ($r['status'] === 'placed') {
                $recovered[] = ['pi' => $id, 'order_id' => $r['order_id'], 'amount' => $amount];
                echo "RECOVERED  $id -> order #{$r['order_id']}\n";
            } elseif (($r['refunded'] ?? null) === true) {
                // Refunded because it couldn't become an order (now or earlier).
                $counts['refunded']++;
            } else {
                // A failed refund has already emailed its own alert from
                // inside place(); it still counts as unsettled for the exit code.
                $refundFailed = ($r['refunded'] ?? null) === false;
                $problem = $refundFailed
                    ? 'Paid, no order, and the refund failed'
                    : 'Paid, no order yet — could not reach Stripe or the database (HTTP ' . ($r['http'] ?? '?') . ')';
                $unresolved[] = ['pi' => $id, 'amount' => $amount, 'problem' => $problem, 'alerted' => $refundFailed];
                echo "UNRESOLVED $id: $problem\n";
            }
        }
        $last = end($page['data']);
        $params['starting_after'] = $last['id'] ?? null;
    } while (!empty($page['has_more']) && $params['starting_after']);
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' reconcile failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$emailed = PaymentAlert::reconcileReport($db, $recovered, array_filter($unresolved, static fn($u) => !$u['alerted']));

printf(
    "%s  last %d day(s): %d paid checkout(s) — %d already had an order, %d recovered now, %d refunded, %d unresolved.%s\n",
    date('c'), $days, $counts['checked'], $counts['had_order'], count($recovered),
    $counts['refunded'], count($unresolved), $emailed ? ' Shop emailed.' : ''
);

exit($unresolved ? 1 : 0);
