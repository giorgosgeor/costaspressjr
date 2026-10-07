<?php

/** A Stripe API failure that keeps the machine-readable error code. */
class StripeApiException extends \RuntimeException {
    /** Stripe's error.code (e.g. "charge_already_refunded"); null when Stripe was never reached. */
    public ?string $stripeCode;
    public int $httpStatus;

    public function __construct(string $message, ?string $stripeCode = null, int $httpStatus = 0) {
        parent::__construct($message);
        $this->stripeCode = $stripeCode;
        $this->httpStatus = $httpStatus;
    }
}
