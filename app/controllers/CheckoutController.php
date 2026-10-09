<?php

/**
 * Checkout: the page where the customer says who collects and where, the
 * Stripe-hosted Checkout page it continues to for the payment, and the
 * confirmation page every payment returns to.
 */
class CheckoutController extends Controller {
    /**
     * GET /checkout — the customer's details and how they collect, with the
     * order summary beside it; "Continue to payment" goes on to Stripe's
     * page. Nothing to pay for → back to the cart.
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
     * POST /api/create-checkout-session — "Continue to payment". Validates the
     * collection choice, prices the cart and creates the Stripe-hosted
     * Checkout page the customer pays on; the browser is sent to its URL.
     *
     * Everything needed to place the order is written to pending_checkouts
     * here, BEFORE the customer pays: the webhook has no session. Stripe only
     * creates the PaymentIntent when the customer pays, so the row is saved
     * under checkout_ref, which travels in the intent's metadata and lets
     * OrderPlacement find it again.
     */
    public function createCheckoutSession(): void {
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
        // The checkbox above the button is required; checking it here too
        // means a payment cannot be started without it, whatever the browser did.
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
        // Each cancellation costs the shop Stripe's fee, so an account that
        // keeps ordering and cancelling has new orders paused until the oldest
        // cancellation is a month old (OrderCancellation::tooManyRecent).
        // Production only: testing orders and cancels all the time.
        if (Env::get('APP_ENV', 'production') === 'production'
            && OrderCancellation::tooManyRecent($this->db, (int)$userId)) {
            http_response_code(429);
            echo json_encode(['error' => I18n::t('checkout.errors.too_many_cancellations')]);
            return;
        }

        $cartModel = new \Cart($this->db);
        $cartId    = $cartModel->getOrCreateCartId($userId);

        // A row without a price would be charged as nothing while the order
        // still counts the item — the charge would then never match the order
        // total. Refuse to start the payment instead.
        $bad = $this->db->prepare("SELECT COUNT(*) FROM cart_items WHERE cart_id = ? AND (unit_price IS NULL OR unit_price <= 0)");
        $bad->execute([$cartId]);
        if ((int)$bad->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.bad_price')]);
            return;
        }

        // The cart as Stripe's page lists it. The shop's prices already include
        // VAT and are charged exactly as shown: each line says so
        // (tax_behavior inclusive) and Stripe calculates no tax of its own
        // (no automatic_tax), so the charge is the total the customer saw
        // (OrderPlacement refunds one that doesn't match).
        [$items] = $cartModel->contents($userId);
        $lineItems  = [];
        $itemsCents = 0;
        foreach ($items as $item) {
            $unitCents = (int)round((float)$item['unit_total'] * 100);
            $qty       = (int)$item['quantity'];
            $details   = array_filter([
                $item['size_name'] ?? null,
                $item['color_name'] ?? null,
                !empty($item['premade_design_name'])
                    ? I18n::t('cart.item.design_label', ['name' => $item['premade_design_name']])
                    : ((!empty($item['is_custom_design']) || !empty($item['custom_design_fee'])) ? I18n::t('cart.item.custom') : null),
            ]);
            $lineItems[] = self::lineItem((string)($item['product_name'] ?? ''), implode(' · ', $details), $unitCents, $qty);
            $itemsCents += $unitCents * $qty;
        }
        if ($itemsCents <= 0) {
            http_response_code(400);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_empty')]);
            return;
        }
        // Stripe takes at most 100 lines; a cart that big becomes one line.
        if (count($lineItems) > 99) {
            $lineItems = [self::lineItem(I18n::t('checkout.summary.title'), '', $itemsCents, 1)];
        }
        $feeCents = (int)round((float)$choice['fee'] * 100);
        if ($feeCents > 0) {
            $lineItems[] = self::lineItem(I18n::t('checkout.pickup.acs'), (string)($choice['point']['name'] ?? ''), $feeCents, 1);
        }
        $amountCents = $itemsCents + $feeCents;

        // The customer is looking at a total. If the cart changed in another
        // tab since the page loaded, stop rather than charge a different sum.
        if (isset($data['expected_amount']) && (int)$data['expected_amount'] !== $amountCents) {
            http_response_code(409);
            echo json_encode(['error' => I18n::t('checkout.errors.cart_changed'), 'reload' => true]);
            return;
        }

        $ref = bin2hex(random_bytes(16));
        $metadata = [
            'user_id'      => $userId,
            'cart_id'      => $cartId,
            'checkout'     => OrderPlacement::TAG,
            'delivery'     => $choice['method'],
            // The confirmation email's language; the webhook that may
            // place the order has no session to read it from.
            'locale'       => I18n::locale(),
            // How OrderPlacement finds this checkout again (see above).
            'checkout_ref' => $ref,
        ];

        $params = [
            // As configured in Stripe's Checkout Studio
            // (STRIPE_INTEGRATION_TODO.md lists what each needs), except:
            // - no automatic_tax: prices already include VAT (see above);
            //   it would also hide Google Pay, which needs a shipping address
            //   alongside it;
            // - no saved_payment_method_options: Stripe refuses it without a
            //   Customer, and the shop keeps none, so a saved card could
            //   never be offered back.
            'ui_mode'                    => 'hosted_page',
            'mode'                       => 'payment',
            'billing_address_collection' => 'auto',
            'phone_number_collection'    => ['enabled' => 'true'],
            'allow_promotion_codes'      => 'false',
            'submit_type'                => 'auto',
            'name_collection'            => ['individual' => ['enabled' => 'true', 'optional' => 'true']],
            'integration_identifier'     => 'hosted_web_0001',
            'origin_context'             => 'web',
            // This shop's own.
            'line_items'          => $lineItems,
            'customer_email'      => $choice['email'] ?: null,
            'locale'              => I18n::locale() === 'el' ? 'el' : 'en',
            'client_reference_id' => $ref,
            'metadata'            => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'success_url'         => app_url('/checkout/complete') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'          => app_url('/checkout'),
        ];
        // Stripe's own terms checkbox (Checkout Studio) needs a Terms of
        // service URL in the account's public details, which Stripe takes only
        // with the full business details, and those also unlock live payments.
        // So it is asked with live keys only; testing goes without it. The
        // shop's own terms checkbox applies either way.
        if (!str_contains((string)Env::get('STRIPE_SECRET_KEY', ''), '_test_')) {
            $params['consent_collection'] = ['terms_of_service' => 'required'];
        }

        try {
            $session = \Stripe::createCheckoutSession($params);
        } catch (\Throwable $e) {
            error_log('Stripe checkout session error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.payment_init')]);
            return;
        }
        if (!is_string($session['url'] ?? null) || !str_starts_with($session['url'], 'https://')) {
            error_log('Stripe checkout session without a URL: ' . ($session['id'] ?? '?'));
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.payment_init')]);
            return;
        }

        try {
            $this->db->prepare("
                INSERT INTO pending_checkouts
                    (payment_intent_id, checkout_ref, user_id, cart_id, delivery_method, pickup_point_id, pickup_point,
                     contact_name, contact_phone, contact_email, shipping_fee, amount_cents)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $ref, $userId, $cartId, $choice['method'],
                $choice['point'] ? (int)$choice['point']['id'] : null, Pickup::snapshot($choice['point']),
                $choice['name'], $choice['phone'], $choice['email'], $choice['fee'], $amountCents,
            ]);
            // Unpaid checkouts leave rows behind; paid ones are deleted when
            // the order is placed. A week outlasts Stripe's webhook retries.
            if (random_int(1, 20) === 1) {
                $this->db->exec("DELETE FROM pending_checkouts WHERE created_at < NOW() - INTERVAL 7 DAY");
            }
        } catch (PDOException $e) {
            // Nothing has been charged — the customer never reaches the page.
            error_log('pending checkout insert failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => I18n::t('checkout.errors.generic')]);
            return;
        }

        echo json_encode(['url' => $session['url']]);
    }

    /** One line of Stripe's Checkout page, in cents, VAT already included (see createCheckoutSession). */
    private static function lineItem(string $name, string $description, int $unitCents, int $quantity): array {
        $product = ['name' => $name !== '' ? $name : I18n::t('checkout.summary.title')];
        if ($description !== '') {
            $product['description'] = $description;
        }
        return [
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => $unitCents,
                'tax_behavior' => 'inclusive',
                'product_data' => $product,
            ],
            'quantity' => $quantity,
        ];
    }

    /**
     * GET /checkout/complete — where every payment ends. Stripe's hosted
     * Checkout page sends the customer here with ?session_id=cs_…. Payments
     * started on the old in-page form came back from Stripe.js with
     * ?payment_intent=…&payment_intent_client_secret=…, which still works.
     */
    public function complete(): void {
        if (isset($_GET['session_id'])) {
            $this->completeSession((string)$_GET['session_id']);
            return;
        }

        $piId   = (string)($_GET['payment_intent'] ?? '');
        $secret = (string)($_GET['payment_intent_client_secret'] ?? '');
        if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $piId) || $secret === '') {
            header('Location: /cart');
            return;
        }

        try {
            $pi = \Stripe::retrievePaymentIntent($piId);
        } catch (\Throwable $e) {
            error_log('checkout complete: retrieve failed: ' . $e->getMessage());
            $this->render('checkout/complete', ['state' => 'failed', 'message' => I18n::t('checkout.errors.verify'), 'result' => null]);
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
            $this->showPlaced($piId, $pi);
            return;
        }
        // Still clearing, or back from the bank without paying (cancelled or declined).
        $this->render('checkout/complete', ['state' => $status === 'processing' ? 'processing' : 'not_paid', 'message' => null, 'result' => null]);
    }

    /**
     * The return from Stripe's hosted Checkout page. Knowing the session id
     * is what lets this browser see the order, as the client secret does
     * above: Stripe puts it only in this redirect.
     */
    private function completeSession(string $sessionId): void {
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            header('Location: /cart');
            return;
        }
        try {
            $session = \Stripe::retrieveCheckoutSession($sessionId);
        } catch (\Throwable $e) {
            error_log('checkout complete: session retrieve failed: ' . $e->getMessage());
            $this->render('checkout/complete', ['state' => 'failed', 'message' => I18n::t('checkout.errors.verify'), 'result' => null]);
            return;
        }

        $piId = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : '';
        if (($session['payment_status'] ?? '') === 'paid' && preg_match('/^pi_[A-Za-z0-9_]+$/', $piId)) {
            $this->showPlaced($piId, null);
            return;
        }
        // Complete but unpaid: a method that takes days to clear, and the
        // webhook places the order when it does. Anything else wasn't paid.
        $state = ($session['status'] ?? '') === 'complete' ? 'processing' : 'not_paid';
        $this->render('checkout/complete', ['state' => $state, 'message' => null, 'result' => null]);
    }

    /** Place the order for a paid intent (or find it already placed) and show it. */
    private function showPlaced(string $piId, ?array $pi): void {
        $result = (new OrderPlacement($this->db))->place($piId, null, $pi);
        if ($result['status'] === 'failed') {
            $this->render('checkout/complete', ['state' => 'failed', 'message' => $result['error'], 'result' => $result]);
            return;
        }
        if ((int)$result['user_id'] === (int)$this->effectiveUserId(false)) {
            $_SESSION['cart_count'] = 0;
        }
        $this->render('checkout/complete', [
            'state'   => 'placed',
            'message' => null,
            'result'  => $result,
            'placed'  => $this->orderForConfirmation((int)$result['order_id']),
        ]);
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
