<?php

/**
 * A customer may cancel their own order while it is still 'pending' (before
 * production). What comes back depends on what is in it (see /returns):
 *   - any pre-made design: everything they paid. Pre-made designs carry the
 *     EU 14-day right of withdrawal, and a withdrawal is refunded in full,
 *     so the shop absorbs Stripe's fee;
 *   - custom designs only: what they paid MINUS Stripe's processing fee.
 *     Made to the customer's own specifications, they have no right of
 *     withdrawal (Consumer Rights Directive, art. 16(c)), so cancelling is a
 *     courtesy, and Stripe keeps its fee on a refund — the shop passes that
 *     cost on rather than paying it.
 * Once the shop moves the order to 'processing' it can't be cancelled here.
 * Pre-made designs can still be withdrawn within 14 days of collection by
 * telling the shop, which refunds them from the Stripe dashboard.
 *
 * Someone who keeps ordering and cancelling costs the shop a fee each time;
 * tooManyRecent() lets the checkout pause new orders from such an account.
 *
 * Order of work matters:
 *   1. The order is claimed with a conditional UPDATE (pending → cancelled),
 *      so a customer cancelling at the moment staff start the order can't
 *      both win — and a double submit reaches Stripe only once.
 *   2. The fee is read from Stripe and the rest of the payment refunded.
 *   3. If either step fails, the order goes back to 'pending' — nothing
 *      changed for the customer except an error message.
 * The shop is emailed after every cancellation so nobody prints it.
 */
class OrderCancellation
{
    /** Cancelled orders an account may have in RECENT_DAYS days before new ones pause. */
    public const RECENT_LIMIT = 3;
    public const RECENT_DAYS  = 30;

    public function __construct(private PDO $db)
    {
    }

    /** Whether any item of the order is a pre-made design (the 14-day right applies). */
    public static function hasPremade(PDO $db, int $orderId): bool
    {
        $stmt = $db->prepare("
            SELECT d.design_data
            FROM order_items oi
            JOIN order_item_designs d ON d.order_item_id = oi.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $data = json_decode((string)$json, true);
            if (is_array($data) && ($data['type'] ?? '') === 'premade') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether this account has had RECENT_LIMIT or more orders cancelled in
     * the last RECENT_DAYS days. The checkout then takes no new order from
     * it (the customer is asked to contact the shop) until the oldest falls
     * out of the window; orders already placed keep every right to cancel.
     * Cancellations by staff count too — three in a month is worth a word
     * with the customer either way.
     */
    public static function tooManyRecent(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM orders
            WHERE user_id = ? AND status = 'cancelled'
              AND created_at >= NOW() - INTERVAL " . self::RECENT_DAYS . " DAY
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() >= self::RECENT_LIMIT;
    }

    /** Whether an order row (with its payment's status and intent) can be cancelled now. */
    public static function canCancel(array $order): bool
    {
        return ($order['status'] ?? '') === 'pending'
            && ($order['payment_status'] ?? '') === 'paid'
            && preg_match('/^pi_[A-Za-z0-9_]+$/', (string)($order['payment_intent_id'] ?? '')) === 1;
    }

    /**
     * What cancelling would give back, in cents: the payment, the fee kept
     * from it, and the refund (the difference). $keepFee is false for an
     * order with a pre-made design: no fee is kept and everything comes back.
     * Null when Stripe can't say — the order page then words it without
     * figures.
     *
     * With $db, for showing the sums: Stripe's fee never changes, so once
     * known it is kept on the payment row and later views don't ask Stripe
     * (audit P8). The cancellation itself passes no $db and reads it all from
     * Stripe, since a refund made in the Dashboard may not be in the
     * database yet.
     *
     * @return ?array{paid:int, fee:int, refund:int}
     */
    public static function quote(string $piId, bool $keepFee = true, ?PDO $db = null): ?array
    {
        $a = $db ? self::storedAmounts($db, $piId) : null;
        if ($a === null) {
            try {
                $a = Stripe::paymentAmounts($piId);
            } catch (Throwable $e) {
                Log::warning('cancellation quote unavailable', ['pi' => $piId, 'error' => $e->getMessage()]);
                return null;
            }
            if ($db) {
                try {
                    $db->prepare("UPDATE order_payments SET stripe_fee_cents = ? WHERE payment_intent_id = ?")->execute([$a['fee'], $piId]);
                } catch (PDOException $e) {
                    // stripe_fee_cents missing until the migration runs: ask again next time.
                }
            }
        }
        $fee    = $keepFee ? $a['fee'] : 0;
        $refund = $a['amount'] - $fee - $a['refunded'];
        return ['paid' => $a['amount'], 'fee' => $fee, 'refund' => max(0, $refund)];
    }

    /**
     * The payment's sums in cents from its row, as quote() wants them, once
     * its fee has been read from Stripe; null before that.
     *
     * @return ?array{amount:int, fee:int, refunded:int}
     */
    private static function storedAmounts(PDO $db, string $piId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT amount, refunded_amount, stripe_fee_cents FROM order_payments WHERE payment_intent_id = ? LIMIT 1");
            $stmt->execute([$piId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
        if (!$row || $row['stripe_fee_cents'] === null) {
            return null;
        }
        return [
            'amount'   => (int)round((float)$row['amount'] * 100),
            'fee'      => (int)$row['stripe_fee_cents'],
            'refunded' => (int)round((float)($row['refunded_amount'] ?? 0) * 100),
        ];
    }

    /**
     * Cancel and refund (in full, or minus the fee — see above). Only the
     * customer's own order.
     *
     * @return string 'cancelled' | 'not_allowed' | 'refund_failed'
     */
    public function cancel(int $orderId, int $userId): string
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.status, o.total_price, op.status AS payment_status, op.payment_intent_id
            FROM orders o
            JOIN order_payments op ON op.order_id = o.id
            WHERE o.id = ? AND o.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        // A second submit (double click, back button) finds it done already;
        // that is the outcome they asked for, not a refusal.
        if ($order && $order['status'] === 'cancelled' && in_array($order['payment_status'], ['refunded', 'partially_refunded'], true)) {
            return 'cancelled';
        }
        if (!$order || !self::canCancel($order)) {
            return 'not_allowed';
        }
        // Read before anything changes: if this failed after the claim, the
        // order would be left cancelled with nothing refunded.
        $keepFee = !self::hasPremade($this->db, $orderId);

        // 1. Claim it. Zero rows means another request got there first:
        //    staff moving it on, or this customer's own second click.
        $claim = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $claim->execute([$orderId, $userId]);
        if ($claim->rowCount() !== 1) {
            $now = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
            $now->execute([$orderId]);
            return $now->fetchColumn() === 'cancelled' ? 'cancelled' : 'not_allowed';
        }
        $undo = function () use ($orderId): void {
            $this->db->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND status = 'cancelled'")
                ->execute([$orderId]);
        };

        // 2. The fee (none with a pre-made design), then the refund of the rest.
        $piId  = (string)$order['payment_intent_id'];
        $quote = self::quote($piId, $keepFee);
        if ($quote === null || $quote['refund'] <= 0) {
            $undo();
            return $quote === null ? 'refund_failed' : 'not_allowed';
        }
        try {
            Stripe::refundPaymentIntent(
                $piId,
                $keepFee
                    ? sprintf('Order #%d cancelled by the customer before production; refunded minus the %s Stripe fee', $orderId, money($quote['fee'] / 100))
                    : sprintf('Order #%d cancelled by the customer before production; refunded in full (pre-made design: right of withdrawal)', $orderId),
                'requested_by_customer',
                $quote['refund']
            );
        } catch (Throwable $e) {
            // 3. Put it back the way it was.
            $undo();
            Log::error('customer cancellation: refund failed', ['order' => $orderId, 'pi' => $piId, 'error' => $e->getMessage()]);
            return 'refund_failed';
        }

        // Record it at once, then read the exact state back from Stripe (the
        // charge.refunded webhook does the same later).
        $this->db->prepare("UPDATE order_payments SET status = ?, refunded_amount = ? WHERE payment_intent_id = ?")
            ->execute([$quote['fee'] > 0 ? 'partially_refunded' : 'refunded', round($quote['refund'] / 100, 2), $piId]);
        try {
            (new OrderPlacement($this->db))->syncPaymentStatus($piId);
        } catch (Throwable $e) {
            Log::warning('customer cancellation: status sync deferred to the webhook', ['order' => $orderId, 'error' => $e->getMessage()]);
        }

        PaymentAlert::orderCancelled($this->db, $orderId, $piId, $quote['paid'] / 100, $quote['refund'] / 100, $quote['fee'] / 100);
        return 'cancelled';
    }
}
