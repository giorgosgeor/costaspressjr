<?php

/**
 * Checkout: the payment page, the PaymentIntent behind it and the
 * confirmation page every payment returns to.
 */
class CheckoutController extends Controller {
    /**
     * GET /checkout — the payment page. Everything is on one page: the
     * customer's details, how they collect, and the Stripe Payment Element,
     * with the order summary beside it. Nothing to pay for → back to the cart.
     */
    public function show(): void {
        $userId = $this->effectiveUserId(false);
        [$cartItems, $cartTotal] = $userId ? (new Cart($this->db))->contents($userId) : [[], 0];
        if (!$cartItems) {
            header('Location: /cart');
            return;
        }

        $checkout     = $this->checkoutOptions();
        $accountEmail = $this->accountEmail();
        $accountPhone = null;
        if (Auth::check()) {
            $stmt = $this->db->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([Auth::userId()]);
            $accountPhone = ((string)$stmt->fetchColumn()) ?: null;
        }
        $this->render('checkout/show', ['cartItems' => $cartItems, 'cartTotal' => $cartTotal, 'checkout' => $checkout, 'accountEmail' => $accountEmail, 'accountPhone' => $accountPhone]);
    }

    /**
     * Payment step: validate the collection choice, price the cart, and create
     * the PaymentIntent that the Payment Element confirms.
     *
     * Everything needed to place the order is written to pending_checkouts
     * here, BEFORE the customer pays. A redirect payment (Revolut Pay, PayPal)
     * can come back in a different browser, and the webhook has no session.
     */
    public function createPaymentIntent(): void {
        header('Content-Type: application/json');
        // Guests check out too. No guest row yet means no cart — nothing to pay.
        $userId = $this->effectiveUserId(false);
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_empty')]);
            return;
        }

        $data   = json_decode(file_get_contents('php://input'), true);
        $data   = is_array($data) ? $data : [];
        // The checkbox above Pay is required; checking it here too means a
        // payment cannot be started without it, whatever the browser did.
        if (($data['terms_accepted'] ?? false) !== true) {
            http_response_code(422);
            echo json_encode(['error' => I18n::t('checkout.errors.terms')]);
            return;
        }
        $choice = Pickup::validateChoice($this->db, $data, $this->accountEmail());
        if (isset($choice['error'])) {
            http_response_code(422);
            echo json_encode(['error' => $choice['error']]);
            return;
        }

        $cartModel = new \Cart($this->db);
        $cartId    = $cartModel->getOrCreateCartId($userId);

        // A NULL unit_price row would silently drop out of SUM() here while the
        // order still counts the item — the charge would then never match the
        // order total. Refuse to create the intent instead.
        $bad = $this->db->prepare("SELECT COUNT(*) FROM cart_items WHERE cart_id = ? AND (unit_price IS NULL OR unit_price <= 0)");
        $bad->execute([$cartId]);
        if ((int)$bad->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.bad_price')]);
            return;
        }

        $stmt = $this->db->prepare("SELECT SUM((unit_price + custom_design_fee) * quantity) FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cartId]);
        $itemsTotal = (float)($stmt->fetchColumn() ?: 0);
        if ($itemsTotal <= 0) {
            http_response_code(400);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_empty')]);
            return;
        }
        $amountCents = (int)round(($itemsTotal + $choice['fee']) * 100);

        // The customer is looking at a total. If the cart changed in another
        // tab since the page loaded, stop rather than charge a different sum.
        if (isset($data['expected_amount']) && (int)$data['expected_amount'] !== $amountCents) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_changed'), 'reload' => true]);
            return;
        }

        try {
            $intent = \Stripe::createPaymentIntent($amountCents, 'eur', [
                'user_id'  => $userId,
                'cart_id'  => $cartId,
                'checkout' => OrderPlacement::TAG,
                'delivery' => $choice['method'],
            ]);
        } catch (\Throwable $e) {
            error_log('Stripe createPaymentIntent error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.payment_init')]);
            return;
        }

        try {
            $this->db->prepare("
                INSERT INTO pending_checkouts
                    (payment_intent_id, user_id, cart_id, delivery_method, pickup_point_id, pickup_point,
                     contact_name, contact_phone, contact_email, shipping_fee, amount_cents)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $intent['id'], $userId, $cartId, $choice['method'],
                $choice['point'] ? (int)$choice['point']['id'] : null, Pickup::snapshot($choice['point']),
                $choice['name'], $choice['phone'], $choice['email'], $choice['fee'], $amountCents,
            ]);
            // Unpaid intents leave rows behind; paid ones are deleted when the
            // order is placed. A week outlasts Stripe's webhook retries.
            if (random_int(1, 20) === 1) {
                $this->db->exec("DELETE FROM pending_checkouts WHERE created_at < NOW() - INTERVAL 7 DAY");
            }
        } catch (PDOException $e) {
            // Nothing has been charged yet — the intent is simply abandoned.
            error_log('pending checkout insert failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.generic')]);
            return;
        }

        echo json_encode(['clientSecret' => $intent['client_secret'], 'amount' => $amountCents]);
    }

    /**
     * GET /checkout/complete — where every payment ends. Stripe.js redirects
     * here after confirming (cards and wallets straight away, Revolut Pay and
     * PayPal after their own page) with ?payment_intent=…&payment_intent_client_secret=….
     */
    public function complete(): void {
        $piId   = (string)($_GET['payment_intent'] ?? '');
        $secret = (string)($_GET['payment_intent_client_secret'] ?? '');
        if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $piId) || $secret === '') {
            header('Location: /cart');
            return;
        }

        $result = null;
        try {
            $pi = \Stripe::retrievePaymentIntent($piId);
        } catch (\Throwable $e) {
            error_log('checkout complete: retrieve failed: ' . $e->getMessage());
            $state   = 'failed';
            $message = I18n::t('checkout.errors.verify');
            $this->render('checkout/complete', ['state' => $state, 'message' => $message, 'result' => $result]);
            return;
        }

        // The client secret proves this browser started the payment. That is
        // what lets the order be shown even when the customer's banking app
        // returns them in a browser with no session.
        if (!hash_equals((string)($pi['client_secret'] ?? ''), $secret)) {
            header('Location: /cart');
            return;
        }

        $status = $pi['status'] ?? '';
        if ($status === 'succeeded') {
            $result = (new OrderPlacement($this->db))->place($piId, null, $pi);
            if ($result['status'] === 'failed') {
                $state   = 'failed';
                $message = $result['error'];
            } else {
                $state = 'placed';
                if ((int)$result['user_id'] === (int)$this->effectiveUserId(false)) {
                    $_SESSION['cart_count'] = 0;
                }
                $placed = $this->orderForConfirmation((int)$result['order_id']);
            }
        } elseif ($status === 'processing') {
            $state = 'processing';
        } else {
            // Back from the bank without paying (cancelled or declined).
            $state = 'not_paid';
        }

        $this->render('checkout/complete', ['state' => $state, 'message' => $message ?? null, 'result' => $result, 'placed' => $placed ?? null]);
    }

    /** GET /api/pickup-points — the ACS points for the checkout map. */
    public function pickupPoints(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        $points = Pickup::acsAvailable($this->db) ? Pickup::activePoints($this->db) : [];
        echo json_encode(['points' => $points], JSON_UNESCAPED_UNICODE);
    }

    /** What the checkout can offer right now. */
    private function checkoutOptions(): array {
        return [
            'acsAvailable' => Pickup::acsAvailable($this->db),
            'acsFee'       => Pickup::acsFee(),
            'storeAddress' => Pickup::storeAddress(),
        ];
    }

    /** The logged-in customer's email; null for guests, who type one at checkout. */
    private function accountEmail(): ?string {
        if (!Auth::check()) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([Auth::userId()]);
        $email = (string)$stmt->fetchColumn();
        return $email !== '' ? $email : null;
    }

    /**
     * What the confirmation page shows: the order, its lines and how it was
     * paid. Null if it can't be read — the page still shows the number.
     */
    private function orderForConfirmation(int $orderId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT o.id, o.tracking_token, o.total_price, o.shipping_fee, o.delivery_method, o.pickup_point,
                       op.payment_method, op.card_brand, op.card_last4
                FROM orders o LEFT JOIN order_payments op ON op.order_id = o.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return null;
            }
            $stmt = $this->db->prepare("
                SELECT oi.quantity, oi.size_name, oi.color_name, oi.unit_price, oi.custom_design_fee,
                       oi.preview_images, p.name AS product_name, p.image_path AS product_image
                FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
                ORDER BY oi.id
            ");
            $stmt->execute([$orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $point = $order['pickup_point'] ? json_decode($order['pickup_point'], true) : null;
            $order['point'] = is_array($point) ? $point : null;
            return $order;
        } catch (PDOException $e) {
            error_log('order confirmation: ' . $e->getMessage());
            return null;
        }
    }
}
