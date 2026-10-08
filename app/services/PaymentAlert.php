<?php

/**
 * Emails the shop about payments that need a person.
 *
 *   refundFailed()     money taken, no order, and the automatic refund failed
 *                      — the one case the code can't settle by itself.
 *   reconcileReport()  the reconcile job had to place orders the webhook never
 *                      delivered (so the webhook may be broken), or found
 *                      payments it still can't settle.
 *   orderCancelled()   a customer cancelled a pending order on the website
 *                      and was refunded — so nobody prints it.
 *
 * Each payment is alerted about at most once per kind (payment_alerts table):
 * the webhook retries for days and the job runs every half hour.
 *
 * Recipients: ALERT_EMAIL (comma-separated), otherwise every admin account.
 * Nothing here throws — a mail problem must never break taking a payment.
 */
class PaymentAlert
{
    public static function refundFailed(PDO $db, string $piId, string $reason, string $error): void
    {
        try {
            if (!self::firstTime($db, $piId, 'refund_failed')) {
                return;
            }
            $pending = self::pending($db, $piId);
            self::send(
                $db,
                'Payment needs attention: paid, no order, refund failed',
                'A customer paid, the order could not be created, and the automatic refund failed. '
                . 'Either create the order by hand or refund the payment in the Stripe Dashboard '
                . '(search for the payment ID below).',
                [[
                    'Payment'      => $piId,
                    'Amount'       => $pending ? self::money((int)$pending['amount_cents']) : 'unknown',
                    'Customer'     => $pending ? self::customer($pending) : 'unknown',
                    'Why no order' => $reason,
                    'Refund error' => $error,
                ]]
            );
        } catch (Throwable $e) {
            Log::error('payment alert failed', ['pi' => $piId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * A customer cancelled their order from the website and was refunded
     * (OrderCancellation). It was still 'pending', but someone may already
     * have it on the bench — this tells them to stop.
     */
    public static function orderCancelled(PDO $db, int $orderId, string $piId, float $amount): void
    {
        try {
            if (!self::firstTime($db, $piId, 'customer_cancelled')) {
                return;
            }
            $stmt = $db->prepare("SELECT shipping_address FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            // shipping_address is the order's contact block: where, then name, phone, email.
            $contact = trim(implode(' · ', array_slice(array_filter(array_map('trim', explode("\n", (string)$stmt->fetchColumn()))), 1)));
            self::send(
                $db,
                'Order #' . $orderId . ' cancelled by the customer — refunded',
                'The customer cancelled order #' . $orderId . ' on the website before it went into processing, '
                . 'and the payment was refunded in full automatically. Please don\'t start or print it.',
                [[
                    'Order'    => '#' . $orderId,
                    'Refunded' => '€' . number_format($amount, 2),
                    'Payment'  => $piId,
                    'Customer' => $contact !== '' ? $contact : 'see the order',
                ]]
            );
        } catch (Throwable $e) {
            Log::error('cancellation alert failed', ['order' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param array $recovered  [['pi', 'order_id', 'amount' (cents)], …] orders the job placed
     * @param array $unresolved [['pi', 'amount' (cents), 'problem'], …] payments it couldn't settle
     * @return bool whether an email went out
     */
    public static function reconcileReport(PDO $db, array $recovered, array $unresolved): bool
    {
        try {
            // Only payments not already reported; a stuck one stays stuck
            // between runs and would otherwise be re-sent every half hour.
            $unresolved = array_values(array_filter($unresolved, static fn($u) => self::firstTime($db, $u['pi'], 'unresolved')));
            if (!$recovered && !$unresolved) {
                return false;
            }

            $blocks = [];
            foreach ($recovered as $r) {
                $blocks[] = ['Recovered order' => '#' . $r['order_id'], 'Payment' => $r['pi'], 'Amount' => self::money((int)$r['amount'])];
            }
            foreach ($unresolved as $u) {
                $pending  = self::pending($db, $u['pi']);
                $blocks[] = [
                    'Needs attention' => $u['problem'],
                    'Payment'         => $u['pi'],
                    'Amount'          => self::money((int)$u['amount']),
                    'Customer'        => $pending ? self::customer($pending) : 'unknown',
                ];
            }

            $intro = [];
            if ($recovered) {
                $intro[] = count($recovered) . ' paid order(s) had not been created, and the reconcile job created them now. '
                    . 'The Stripe webhook should have done that within seconds — check that it is configured and '
                    . 'that its recent deliveries in the Stripe Dashboard succeed.';
            }
            if ($unresolved) {
                $intro[] = count($unresolved) . ' paid payment(s) could not be settled automatically '
                    . '(no order and no refund). They need checking by hand in the Stripe Dashboard.';
            }
            return self::send(
                $db,
                $unresolved ? 'Payments need attention' : 'Recovered orders the webhook missed',
                implode("\n\n", $intro),
                $blocks
            );
        } catch (Throwable $e) {
            Log::error('reconcile alert failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** @param array $blocks list of [label => value] groups */
    private static function send(PDO $db, string $subject, string $intro, array $blocks): bool
    {
        $to = self::recipients($db);
        if (!$to) {
            Log::error('payment alert: no recipient — set ALERT_EMAIL in .env', ['subject' => $subject]);
            return false;
        }

        $text = $intro . "\n";
        $html = '<p>' . nl2br(htmlspecialchars($intro)) . '</p>';
        foreach ($blocks as $block) {
            $text .= "\n";
            $html .= '<table cellpadding="4" style="border-collapse:collapse;margin:12px 0;border:1px solid #D8D0BD">';
            foreach ($block as $label => $value) {
                $text .= str_pad($label . ':', 18) . $value . "\n";
                $html .= '<tr><th align="left" style="background:#F4F0E6">' . htmlspecialchars($label)
                       . '</th><td>' . htmlspecialchars((string)$value) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $subject = '[' . Env::get('MAIL_FROM_NAME', 'Costaspressjr') . '] ' . $subject;
        $sent = false;
        foreach ($to as $address) {
            $sent = Mailer::send($address, $subject, $html, $text) || $sent;
        }
        return $sent;
    }

    private static function recipients(PDO $db): array
    {
        $list = array_values(array_filter(
            array_map('trim', explode(',', (string)Env::get('ALERT_EMAIL', ''))),
            static fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
        ));
        if ($list) {
            return $list;
        }
        try {
            return $db->query("SELECT email FROM users WHERE role = 'admin' AND email <> ''")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /** True the first time a payment is alerted about for this kind. */
    private static function firstTime(PDO $db, string $piId, string $kind): bool
    {
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO payment_alerts (payment_intent_id, kind) VALUES (?, ?)");
            $stmt->execute([$piId, $kind]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            // Table missing (migration not run): a repeated email beats none.
            return true;
        }
    }

    private static function pending(PDO $db, string $piId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT amount_cents, contact_name, contact_phone, contact_email FROM pending_checkouts WHERE payment_intent_id = ?");
            $stmt->execute([$piId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function customer(array $pending): string
    {
        return implode(' · ', array_filter([$pending['contact_name'], $pending['contact_phone'], $pending['contact_email'] ?? null]));
    }

    private static function money(int $cents): string
    {
        return '€' . number_format($cents / 100, 2);
    }
}
