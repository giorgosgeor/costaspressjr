<?php

/**
 * What a production server must have configured before it serves anyone.
 *
 * A half-configured production site fails in ways nobody sees until a
 * customer does: emails silently logged instead of sent, reset links
 * built from the request's Host header, or — worst — Stripe test keys,
 * which let anyone "pay" with card 4242 for real goods. public/index.php
 * refuses to serve while problems() isn't empty; the log names what is
 * missing, and /health returns 500 so an uptime monitor notices.
 */
class ProductionCheck {
    /** @return string[] one line per problem; empty when the server is ready */
    public static function problems(): array {
        $problems = [];

        // The shop targets PHP 8.5 (security fixes until the end of 2029).
        // Shared hosts pick the PHP version per site and often default to an
        // older one, so a forgotten switch shows up here, not as odd bugs.
        if (PHP_VERSION_ID < 80500) {
            $problems[] = 'PHP 8.5 or newer is required (this server runs ' . PHP_VERSION . ')';
        }
        if (!str_starts_with((string)Env::get('APP_URL', ''), 'https://')) {
            $problems[] = 'APP_URL must be the https:// address of the site';
        }
        foreach (['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET'] as $key) {
            if ((string)Env::get($key, '') === '') {
                $problems[] = "$key is empty";
            }
        }
        $secretIsTest = str_contains((string)Env::get('STRIPE_SECRET_KEY', ''), '_test_');
        $publicIsTest = str_contains((string)Env::get('STRIPE_PUBLISHABLE_KEY', ''), '_test_');
        if ($secretIsTest !== $publicIsTest) {
            $problems[] = 'STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY are from different modes (test/live)';
        } elseif ($secretIsTest && Env::get('STRIPE_ALLOW_TEST_KEYS', '') !== '1') {
            $problems[] = 'Stripe keys are TEST keys (set STRIPE_ALLOW_TEST_KEYS=1 only for a dry run)';
        }
        $mailTransport = Env::get('MAIL_TRANSPORT', 'log');
        if (!in_array($mailTransport, ['smtp', 'mail'], true)) {
            $problems[] = 'MAIL_TRANSPORT must be smtp (or mail), not "' . $mailTransport . '"';
        } elseif ($mailTransport === 'smtp' && (string)Env::get('SMTP_HOST', '') === '') {
            $problems[] = 'SMTP_HOST is empty';
        }

        return $problems;
    }
}
