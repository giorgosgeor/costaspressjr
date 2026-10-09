<?php

/**
 * Stripe's server-to-server notifications (POST /stripe/webhook).
 */
class StripeWebhookController extends Controller {
    /**
     * POST /stripe/webhook — the backstop. If the customer paid and closed the
     * tab before coming back, this still places the order (or refunds it).
     * Payments on Stripe's hosted Checkout page arrive here as
     * payment_intent.succeeded too; their intent's checkout_ref metadata
     * leads OrderPlacement to the checkout they belong to.
     * It also keeps order_payments.status in step with refunds and disputes
     * made outside the site.
     * Configure in Stripe: events payment_intent.succeeded, charge.refunded,
     * charge.dispute.created and charge.dispute.closed; signing secret in
     * STRIPE_WEBHOOK_SECRET.
     */
    public function handle(): void {
        header('Content-Type: application/json');
        $payload = (string)file_get_contents('php://input');
        $event   = \Stripe::verifyWebhook($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
        if (!$event) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid signature']);
            return;
        }

        // The payload is only trusted for the intent id; everything else is
        // re-fetched from the API.
        $type   = (string)($event['type'] ?? '');
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        $retry  = false;

        if ($type === 'payment_intent.succeeded') {
            $piId = is_string($object['id'] ?? null) ? $object['id'] : '';
            if (preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
                $result = (new OrderPlacement($this->db))->place($piId, null);
                // Retry while the payment is neither an order nor refunded:
                // the Stripe API or the database was unreachable, or the
                // refund itself failed.
                $refunded = $result['refunded'] ?? null;
                $retry = $result['status'] === 'failed'
                    && ($refunded === false || ($refunded === null && ($result['http'] ?? 0) >= 500));
            }
        } elseif (in_array($type, ['charge.refunded', 'charge.dispute.created', 'charge.dispute.closed'], true)) {
            // Charges and disputes both carry the intent they belong to.
            $piId = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : '';
            if (preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
                try {
                    $retry = !(new OrderPlacement($this->db))->syncPaymentStatus($piId);
                } catch (\PDOException $e) {
                    error_log('payment status sync: database error: ' . $e->getMessage());
                    $retry = true;
                }
            }
        }

        if ($retry) {
            http_response_code(500);
            echo json_encode(['retry' => true]);
            return;
        }
        echo json_encode(['received' => true]);
    }
}
