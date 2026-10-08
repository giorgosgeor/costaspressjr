<?php

/**
 * A customer cancelling their own order for a full refund.
 *
 * Allowed only while the order is 'pending' and paid. Once the shop moves it
 * to 'processing' the printing has started and it is no longer the
 * customer's to undo from the website — they contact the shop instead.
 *
 * Order of work matters:
 *   1. The order is claimed with a conditional UPDATE (pending → cancelled),
 *      so a customer cancelling at the moment staff start the order can't
 *      both win: whichever write lands first decides.
 *   2. Stripe refunds the payment. Stripe refuses a second full refund of
 *      the same charge, so a double submit cannot pay out twice.
 *   3. If Stripe refuses, the order goes back to 'pending' — nothing changed
 *      for the customer except an error message, and they can try again.
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
     * Cancel and refund. Only the customer's own order.
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
        if (!$order || !self::canCancel($order)) {
            return 'not_allowed';
        }

        // 1. Claim it. Zero rows means staff moved it on a moment ago.
        $claim = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $claim->execute([$orderId, $userId]);
        if ($claim->rowCount() !== 1) {
            return 'not_allowed';
        }

        // 2. Refund it.
        $piId = (string)$order['payment_intent_id'];
        try {
            Stripe::refundPaymentIntent($piId, 'Order #' . $orderId . ' cancelled by the customer on the website', 'requested_by_customer');
        } catch (Throwable $e) {
            $alreadyRefunded = $e instanceof StripeApiException && $e->stripeCode === 'charge_already_refunded';
            if (!$alreadyRefunded) {
                // 3. Put it back the way it was.
                $this->db->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND status = 'cancelled'")
                    ->execute([$orderId]);
                Log::error('customer cancellation: refund failed', ['order' => $orderId, 'pi' => $piId, 'error' => $e->getMessage()]);
                return 'refund_failed';
            }
        }

        // The refund is Stripe's record; mark it here at once, then read the
        // exact state back (the charge.refunded webhook does the same later).
        $this->db->prepare("UPDATE order_payments SET status = 'refunded' WHERE payment_intent_id = ?")
            ->execute([$piId]);
        try {
            (new OrderPlacement($this->db))->syncPaymentStatus($piId);
        } catch (Throwable $e) {
            Log::warning('customer cancellation: status sync deferred to the webhook', ['order' => $orderId, 'error' => $e->getMessage()]);
        }

        PaymentAlert::orderCancelled($this->db, $orderId, $piId, (float)$order['total_price']);
        return 'cancelled';
    }
}
