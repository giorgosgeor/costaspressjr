<?php

/**
 * Orders are not refundable. The one exception: a customer may cancel their
 * own order while it is still 'pending' (before production), and gets back
 * what they paid MINUS Stripe's processing fee — Stripe keeps its fee on a
 * refund, so the shop passes that cost on rather than paying it. Once the
 * shop moves the order to 'processing' it can't be cancelled at all.
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
    public function __construct(private PDO $db)
    {
    }

    /** Whether an order row (with its payment's status and intent) can be cancelled now. */
    public static function canCancel(array $order): bool
    {
        return ($order['status'] ?? '') === 'pending'
            && ($order['payment_status'] ?? '') === 'paid'
            && preg_match('/^pi_[A-Za-z0-9_]+$/', (string)($order['payment_intent_id'] ?? '')) === 1;
    }

    /**
     * What cancelling would give back, in cents: the payment, Stripe's fee
     * on it, and the refund (the difference). Null when Stripe can't say —
     * the order page then words it without figures.
     *
     * @return ?array{paid:int, fee:int, refund:int}
     */
    public static function quote(string $piId): ?array
    {
        try {
            $a = Stripe::paymentAmounts($piId);
        } catch (Throwable $e) {
            Log::warning('cancellation quote unavailable', ['pi' => $piId, 'error' => $e->getMessage()]);
            return null;
        }
        $refund = $a['amount'] - $a['fee'] - $a['refunded'];
        return ['paid' => $a['amount'], 'fee' => $a['fee'], 'refund' => max(0, $refund)];
    }

    /**
     * Cancel and refund (minus the fee). Only the customer's own order.
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

        // 2. The fee, then the refund of the rest.
        $piId  = (string)$order['payment_intent_id'];
        $quote = self::quote($piId);
        if ($quote === null || $quote['refund'] <= 0) {
            $undo();
            return $quote === null ? 'refund_failed' : 'not_allowed';
        }
        try {
            Stripe::refundPaymentIntent(
                $piId,
                sprintf('Order #%d cancelled by the customer before production; refunded minus the %s Stripe fee', $orderId, money($quote['fee'] / 100)),
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
        $this->db->prepare("UPDATE order_payments SET status = 'partially_refunded', refunded_amount = ? WHERE payment_intent_id = ?")
            ->execute([round($quote['refund'] / 100, 2), $piId]);
        try {
            (new OrderPlacement($this->db))->syncPaymentStatus($piId);
        } catch (Throwable $e) {
            Log::warning('customer cancellation: status sync deferred to the webhook', ['order' => $orderId, 'error' => $e->getMessage()]);
        }

        PaymentAlert::orderCancelled($this->db, $orderId, $piId, $quote['paid'] / 100, $quote['refund'] / 100, $quote['fee'] / 100);
        return 'cancelled';
    }
}
