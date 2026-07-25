<?php

class Stripe {
    private static function request(string $method, string $endpoint, array $params = []): array {
        $key = Env::get('STRIPE_SECRET_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY is not configured');
        }

        $url = 'https://api.stripe.com/v1' . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $key . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
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

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || $body === false) {
            throw new \RuntimeException('Stripe API connection failed');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Stripe API response');
        }

        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']['message'] ?? 'Stripe error');
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
}
