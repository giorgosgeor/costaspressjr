<?php

class Stripe {
    /**
     * Pinned so responses keep their shape whatever the account's default
     * version is set to in the Dashboard — OrderPlacement relies on
     * latest_charge, which versions before 2022-11-15 don't have. Change it
     * deliberately, after testing the checkout against the new version.
     */
    public const API_VERSION = '2026-08-26.dahlia';

    /** A dropped connection or a Stripe-side error is tried once more. */
    private const MAX_ATTEMPTS = 2;

    private static function request(string $method, string $endpoint, array $params = []): array {
        $key = Env::get('STRIPE_SECRET_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY is not configured');
        }

        $url = 'https://api.stripe.com/v1' . $endpoint;
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: ' . self::API_VERSION,
        ];
        // The retry reuses the key, so a POST that timed out after Stripe had
        // already acted on it (a refund, say) gets the original result back
        // instead of being done a second time.
        if ($method === 'POST') {
            $headers[] = 'Idempotency-Key: ' . bin2hex(random_bytes(16));
        }

        for ($attempt = 1; ; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD        => $key . ':',
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 30,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            } else {
                $qs = $params ? ('?' . http_build_query($params)) : '';
                curl_setopt($ch, CURLOPT_URL, $url . $qs);
            }

            $body   = curl_exec($ch);
            $errno  = curl_errno($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // No curl_close(): it has done nothing since PHP 8.0 and is
            // deprecated as of 8.5 — with display_errors on, its notice landed
            // in the JSON response and broke the checkout. The handle is freed
            // when $ch is reassigned or goes out of scope.

            $retryable = $errno || $body === false || $status === 409 || $status === 429 || $status >= 500;
            if (!$retryable || $attempt >= self::MAX_ATTEMPTS) {
                break;
            }
            usleep(500000);
        }

        if ($errno || $body === false) {
            throw new StripeApiException('Stripe API connection failed');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new StripeApiException('Invalid Stripe API response', null, $status);
        }

        if (isset($data['error'])) {
            throw new StripeApiException(
                $data['error']['message'] ?? 'Stripe error',
                isset($data['error']['code']) ? (string)$data['error']['code'] : null,
                $status
            );
        }

        return $data;
    }

    public static function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array {
        $params = [
            'amount'   => $amountCents,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
        ];
        foreach ($metadata as $k => $v) {
            $params['metadata[' . $k . ']'] = $v;
        }
        return self::request('POST', '/payment_intents', $params);
    }

    public static function retrievePaymentIntent(string $id): array {
        return self::request('GET', '/payment_intents/' . urlencode($id), [
            'expand[]' => 'latest_charge',
        ]);
    }

    /** One page of PaymentIntents, newest first (see database/reconcile_payments.php). */
    public static function listPaymentIntents(array $params): array {
        return self::request('GET', '/payment_intents', $params);
    }

    /** The most recent dispute on an intent, or null. */
    public static function latestDispute(string $paymentIntentId): ?array {
        $list = self::request('GET', '/disputes', [
            'payment_intent' => $paymentIntentId,
            'limit'          => 1,
        ]);
        return $list['data'][0] ?? null;
    }

    /**
     * Verify a webhook delivery and return the decoded event, or null if the
     * signature is missing, wrong, or older than $tolerance seconds.
     *
     * Stripe signs "{timestamp}.{raw body}" with HMAC-SHA256 using the
     * endpoint's signing secret and sends it as "t=…,v1=…" in the
     * Stripe-Signature header. Any v1 entry may match (secret rotation).
     */
    public static function verifyWebhook(string $payload, string $sigHeader, int $tolerance = 300): ?array {
        $secret = (string)Env::get('STRIPE_WEBHOOK_SECRET', '');
        if ($secret === '' || $sigHeader === '') {
            return null;
        }

        $timestamp  = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') $timestamp = (int)$v;
            if ($k === 'v1') $signatures[] = $v;
        }
        if (!$timestamp || !$signatures || abs(time() - $timestamp) > $tolerance) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                $event = json_decode($payload, true);
                return is_array($event) ? $event : null;
            }
        }
        return null;
    }

    /**
     * Full refund of a PaymentIntent's charge. Stripe refuses a second full
     * refund of the same charge (code charge_already_refunded), so calling
     * this twice cannot pay out twice.
     *
     * $why goes in metadata: Stripe's own `reason` only offers duplicate,
     * fraudulent and requested_by_customer, and none of them describes a
     * checkout that couldn't become an order. Pass $stripeReason when one
     * of them does (a customer cancelling: 'requested_by_customer').
     */
    public static function refundPaymentIntent(string $id, string $why = '', string $stripeReason = ''): array {
        $params = ['payment_intent' => $id];
        if ($why !== '') {
            $params['metadata[reason]'] = mb_substr($why, 0, 500);
        }
        if ($stripeReason !== '') {
            $params['reason'] = $stripeReason;
        }
        return self::request('POST', '/refunds', $params);
    }
}
