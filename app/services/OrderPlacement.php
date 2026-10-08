<?php

/**
 * Turns a paid Stripe PaymentIntent into an order.
 *
 * Three callers reach this for the same payment, in any order and possibly at
 * the same moment:
 *   - the checkout page, right after an in-page payment (card, Apple Pay,
 *     Google Pay);
 *   - the return page, after a redirect payment (Revolut Pay, PayPal);
 *   - the payment_intent.succeeded webhook, the backstop for a customer who
 *     paid and never came back.
 * So place() is idempotent — a payment that already has an order returns that
 * order — and the UNIQUE index on order_payments.payment_intent_id settles a
 * race between two of them.
 *
 * Anything that stops a PAID intent becoming an order refunds it. A customer
 * is never left charged with no order to show for it. The exceptions decide
 * nothing and leave it to the webhook: a database error, and a request from
 * a session that isn't the intent's owner.
 */
class OrderPlacement
{
    /** metadata.checkout on every intent this checkout creates. */
    public const TAG = 'v2';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param ?int   $sessionUserId the caller's user; null for the webhook, and for a
     *                              return page that proved possession with the client secret
     * @param ?array $pi            the intent, if the caller already fetched it
     * @return array status 'placed' | 'exists' | 'failed', plus order_id/tracking
     *               or http/error/refunded (null = nothing decided, try again later)
     */
    public function place(string $piId, ?int $sessionUserId, ?array $pi = null): array
    {
        // A database error means "don't know" — never "no order yet" or "no
        // pending checkout", both of which lead to a refund. Nothing is
        // decided; a later caller (Stripe retries the webhook) will be.
        try {
            $result = $this->attempt($piId, $sessionUserId, $pi);
        } catch (PDOException $e) {
            Log::error('order placement: database error', ['pi' => $piId, 'error' => $e->getMessage()]);
            return $this->failed(503, I18n::t('checkout.errors.paid_unconfirmed'));
        }

        // Someone else's order is not shown to this session.
        if ($result['status'] === 'exists' && $sessionUserId !== null && $result['user_id'] !== $sessionUserId) {
            return $this->failed(403, I18n::t('checkout.errors.other_session'));
        }
        return $result;
    }

    private function attempt(string $piId, ?int $sessionUserId, ?array $pi): array
    {
        if ($existing = $this->orderForIntent($piId)) {
            return $this->existing($existing);
        }

        if ($pi === null) {
            try {
                $pi = Stripe::retrievePaymentIntent($piId);
            } catch (Throwable $e) {
                Log::error('order placement: could not retrieve intent', ['pi' => $piId, 'error' => $e->getMessage()]);
                return $this->failed(502, I18n::t('checkout.errors.verify'));
            }
        }

        if (($pi['status'] ?? '') !== 'succeeded') {
            return $this->failed(402, I18n::t('checkout.errors.not_paid'));
        }

        // Refunded by an earlier caller: say so, don't try again.
        $charge = is_array($pi['latest_charge'] ?? null) ? $pi['latest_charge'] : [];
        if (!empty($charge['refunded']) || (int)($charge['amount_refunded'] ?? 0) > 0) {
            return ['status' => 'failed', 'http' => 409, 'error' => I18n::t('checkout.errors.refunded'), 'refunded' => true];
        }

        $pending = $this->pendingFor($piId);
        if (!$pending) {
            if (($pi['metadata']['checkout'] ?? '') !== self::TAG) {
                // Not an intent this checkout created — not ours to refund.
                Log::warning('order placement: intent was not created by this checkout', ['pi' => $piId]);
                return $this->failed(404, I18n::t('checkout.errors.generic'));
            }
            return $this->refund($piId, 409, 'no pending checkout for intent');
        }
        $ownerId = (int)$pending['user_id'];

        if ((string)($pi['metadata']['user_id'] ?? '') !== (string)$ownerId) {
            return $this->refund($piId, 409, 'intent metadata does not match its pending checkout');
        }
        // Logging in from another tab mid-checkout moves the cart to the
        // account, so this happens innocently — but it is also what a request
        // carrying someone else's intent id looks like. Not this caller's
        // decision: the webhook, which has no session, places or refunds it.
        if ($sessionUserId !== null && $sessionUserId !== $ownerId) {
            Log::warning('order placement: intent belongs to a different session user', ['pi' => $piId]);
            return $this->failed(403, I18n::t('checkout.errors.other_session'));
        }

        $cartId = (int)$pending['cart_id'];
        $items  = $this->cartItems($cartId);
        if (!$items) {
            return $this->refund($piId, 409, 'cart empty after payment');
        }

        $totalProducts = 0;
        $itemsTotal    = 0.0;
        foreach ($items as $item) {
            // unit_price (retail) is authoritative — base_price is the raw
            // supplier cost and must never stand in for it.
            $unit = (float)($item['unit_price'] ?? 0);
            if ($unit <= 0) {
                return $this->refund($piId, 409, 'cart item without a valid price');
            }
            $qty = (int)($item['quantity'] ?? 0);
            $totalProducts += $qty;
            $itemsTotal    += ($unit + (float)($item['custom_design_fee'] ?? 0)) * $qty;
        }
        $fee        = (float)$pending['shipping_fee'];
        $totalPrice = round($itemsTotal + $fee, 2);

        // The cart changed (another tab) between the charge and now.
        $charged = (int)($pi['amount'] ?? 0);
        if (abs($charged - (int)round($totalPrice * 100)) > 1) {
            return $this->refund($piId, 409, "amount mismatch: charged $charged, cart " . (int)round($totalPrice * 100));
        }

        $point = $pending['pickup_point'] ? json_decode($pending['pickup_point'], true) : null;
        $shippingText = Pickup::orderText(
            $pending['delivery_method'], is_array($point) ? $point : null,
            $pending['contact_name'], $pending['contact_phone'], $pending['contact_email']
        );

        // A guest's contact email also goes on the guest user row, unless it
        // belongs to a registered account.
        if (!empty($pending['contact_email'])) {
            try {
                $this->db->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'guest'")
                    ->execute([$pending['contact_email'], $ownerId]);
            } catch (PDOException $e) {
            }
        }

        $trackingToken = self::trackingToken();
        $pay = self::paymentDetails($charge);

        try {
            $this->db->beginTransaction();

            // orders.status is the FULFILMENT lifecycle and starts at
            // 'pending'; whether it is paid lives in order_payments.status.
            $this->db->prepare("
                INSERT INTO orders
                    (user_id, status, tracking_token, total_price, total_products, shipping_address,
                     delivery_method, pickup_point_id, pickup_point, shipping_fee)
                VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $ownerId, $trackingToken, $totalPrice, $totalProducts, $shippingText,
                $pending['delivery_method'], $pending['pickup_point_id'], $pending['pickup_point'], $fee,
            ]);
            $orderId = (int)$this->db->lastInsertId();

            $this->copyItems($orderId, $items);

            $this->db->prepare("
                INSERT INTO order_payments
                    (order_id, payment_method, card_brand, card_holder, card_last4, card_exp_month, card_exp_year, billing_zip, amount, status, payment_intent_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)
            ")->execute([
                $orderId, $pay['method'], $pay['brand'], $pay['holder'], $pay['last4'],
                $pay['exp_month'], $pay['exp_year'], $pay['zip'], $totalPrice, $piId,
            ]);

            $this->db->prepare("DELETE FROM cart_item_uploads WHERE cart_item_id IN (SELECT id FROM cart_items WHERE cart_id = ?)")->execute([$cartId]);
            $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
            $this->db->prepare("DELETE FROM pending_checkouts WHERE payment_intent_id = ?")->execute([$piId]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Log::error('order placement: insert failed', ['pi' => $piId, 'error' => $e->getMessage()]);
            // refund() first checks whether a concurrent caller placed it.
            return $this->refund($piId, 500, 'order insert failed');
        }

        return ['status' => 'placed', 'order_id' => $orderId, 'tracking' => $trackingToken, 'user_id' => $ownerId];
    }

    /**
     * Refund a paid intent that could not become an order — unless a
     * concurrent caller has just placed it, in which case that's the answer.
     * If the database can't say, the PDOException reaches place() and
     * nothing is refunded.
     */
    private function refund(string $piId, int $http, string $reason): array
    {
        if ($existing = $this->orderForIntent($piId)) {
            return $this->existing($existing);
        }
        try {
            Stripe::refundPaymentIntent($piId, $reason);
            $ok = true;
        } catch (Throwable $e) {
            // Two callers failing at once both try; Stripe refuses the second.
            $ok = $e instanceof StripeApiException && $e->stripeCode === 'charge_already_refunded';
            if (!$ok) {
                // The one case that needs a person: money taken, no order, no refund.
                Log::error('checkout failed after payment; REFUND FAILED', ['pi' => $piId, 'reason' => $reason, 'refund_error' => $e->getMessage()]);
                PaymentAlert::refundFailed($this->db, $piId, $reason, $e->getMessage());
                return ['status' => 'failed', 'http' => $http, 'error' => I18n::t('checkout.errors.refund_failed', ['ref' => $piId]), 'refunded' => false];
            }
        }
        Log::warning('checkout failed after payment; refunded', ['pi' => $piId, 'reason' => $reason]);
        try {
            $this->db->prepare("DELETE FROM pending_checkouts WHERE payment_intent_id = ?")->execute([$piId]);
        } catch (PDOException $e) {
        }
        return ['status' => 'failed', 'http' => $http, 'error' => I18n::t('checkout.errors.refunded'), 'refunded' => true];
    }

    private function failed(int $http, string $error): array
    {
        return ['status' => 'failed', 'http' => $http, 'error' => $error, 'refunded' => null];
    }

    private function existing(array $order): array
    {
        return ['status' => 'exists', 'order_id' => (int)$order['id'], 'tracking' => $order['tracking_token'], 'user_id' => (int)$order['user_id']];
    }

    /**
     * Mirror a refund or dispute made outside this site (the Stripe
     * Dashboard, a chargeback) onto the order's payment row. The status is
     * re-derived from the intent as it is now, so events arriving out of
     * order can't leave a stale one behind.
     *
     * @return bool false if Stripe couldn't be reached — worth a retry
     * @throws PDOException
     */
    public function syncPaymentStatus(string $piId): bool
    {
        if (!$this->orderForIntent($piId)) {
            // No order: refund() above already dealt with it, or not ours.
            return true;
        }

        try {
            $pi     = Stripe::retrievePaymentIntent($piId);
            $charge = is_array($pi['latest_charge'] ?? null) ? $pi['latest_charge'] : [];

            $status = 'paid';
            if (!empty($charge['refunded'])) {
                $status = 'refunded';
            } elseif ((int)($charge['amount_refunded'] ?? 0) > 0) {
                $status = 'partially_refunded';
            }

            // A won dispute (or a closed inquiry) leaves the refund state as is.
            if (!empty($charge['disputed'])) {
                $dispute = Stripe::latestDispute($piId);
                $dStatus = (string)($dispute['status'] ?? '');
                if ($dStatus === 'lost') {
                    $status = 'dispute_lost';
                } elseif ($dStatus !== 'won' && $dStatus !== 'warning_closed') {
                    $status = 'disputed';
                }
            }
        } catch (Throwable $e) {
            Log::error('payment status sync: could not read intent', ['pi' => $piId, 'error' => $e->getMessage()]);
            return false;
        }

        $refunded = (int)($charge['amount_refunded'] ?? 0);
        $this->db->prepare("UPDATE order_payments SET status = ?, refunded_amount = ? WHERE payment_intent_id = ?")
            ->execute([$status, $refunded > 0 ? round($refunded / 100, 2) : null, $piId]);
        return true;
    }

    /** @throws PDOException — callers must not read a failed lookup as "none". */
    private function orderForIntent(string $piId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.user_id, o.tracking_token
            FROM order_payments op JOIN orders o ON o.id = op.order_id
            WHERE op.payment_intent_id = ?
            LIMIT 1
        ");
        $stmt->execute([$piId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @throws PDOException — callers must not read a failed lookup as "none". */
    private function pendingFor(string $piId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM pending_checkouts WHERE payment_intent_id = ?");
        $stmt->execute([$piId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function cartItems(int $cartId): array
    {
        $stmt = $this->db->prepare("
            SELECT ci.*, p.base_price, ps.size_name, ac.color_name, ac.color_hex AS color_hex
            FROM cart_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_sizes ps ON ci.size_id = ps.id
            LEFT JOIN available_colors ac ON ci.color_id = ac.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cart rows → order_items (+ their design data and uploads). */
    private function copyItems(int $orderId, array $items): void
    {
        $itemStmt = $this->db->prepare("
            INSERT INTO order_items
            (order_id, product_id, variant_id, size_name, color_name, color_hex, quantity, unit_price, custom_design_fee, is_custom_design, design_id, preview_images)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $designStmt  = $this->db->prepare("INSERT INTO order_item_designs (order_item_id, design_data) VALUES (?, ?)");
        $uploadStmt  = $this->db->prepare("
            INSERT INTO order_item_uploads (order_item_id, original_filename, stored_file_path, placement, position_x, position_y, width, height)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $previewStmt = $this->db->prepare("SELECT preview_images FROM custom_designs WHERE id = ?");
        $uploadsStmt = $this->db->prepare("SELECT * FROM cart_item_uploads WHERE cart_item_id = ?");

        foreach ($items as $item) {
            $isCustom = !empty($item['is_custom_design']) || !empty($item['design_data']);

            // Previews generated at add-to-cart time carry the cart-selected
            // colour; fall back to the saved design's own previews.
            $designId = $item['design_id'] ?? null;
            $previews = $item['preview_images'] ?? null;
            if (!$previews && $designId) {
                $previewStmt->execute([$designId]);
                $previews = $previewStmt->fetchColumn() ?: null;
            }

            $itemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['variant_id'] ?? null,
                $item['size_name'] ?? null,
                $item['color_name'] ?? null,
                $item['color_hex'] ?? null,
                $item['quantity'],
                (float)$item['unit_price'],
                (float)($item['custom_design_fee'] ?? 0),
                $isCustom ? 1 : 0,
                $designId,
                $previews,
            ]);
            $orderItemId = (int)$this->db->lastInsertId();

            if (!empty($item['design_data'])) {
                $designStmt->execute([$orderItemId, $item['design_data']]);
            }

            $uploadsStmt->execute([$item['id']]);
            foreach ($uploadsStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $uploadStmt->execute([
                    $orderItemId,
                    $u['original_filename'] ?? null,
                    $u['stored_file_path'] ?? ($u['file_path'] ?? ''),
                    $u['placement'] ?? 'front',
                    $u['position_x'] ?? 0,
                    $u['position_y'] ?? 0,
                    $u['width'] ?? 80,
                    $u['height'] ?? 80,
                ]);
            }
        }
    }

    /**
     * What was used to pay, from the charge. Apple Pay and Google Pay arrive
     * as type "card" with a wallet; Revolut Pay and PayPal as their own types.
     */
    private static function paymentDetails(array $charge): array
    {
        $details = is_array($charge['payment_method_details'] ?? null) ? $charge['payment_method_details'] : [];
        $type    = (string)($details['type'] ?? 'card');
        $card    = is_array($details['card'] ?? null) ? $details['card'] : [];
        $method  = $type === 'card' && !empty($card['wallet']['type']) ? (string)$card['wallet']['type'] : $type;

        return [
            'method'    => substr($method, 0, 30),
            'brand'     => $card['brand'] ?? null,
            'last4'     => $card['last4'] ?? null,
            'exp_month' => isset($card['exp_month']) ? (int)$card['exp_month'] : null,
            'exp_year'  => isset($card['exp_year']) ? (int)$card['exp_year'] : null,
            'holder'    => isset($charge['billing_details']['name']) ? mb_substr((string)$charge['billing_details']['name'], 0, 100) : null,
            'zip'       => isset($charge['billing_details']['address']['postal_code']) ? mb_substr((string)$charge['billing_details']['address']['postal_code'], 0, 20) : null,
        ];
    }

    /**
     * Public tracking number — the customer's key to /track-order, guests
     * especially. Unambiguous alphabet, 12 chars ≈ 59 random bits.
     */
    private static function trackingToken(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $token = '';
        for ($i = 0; $i < 12; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $token;
    }
}
