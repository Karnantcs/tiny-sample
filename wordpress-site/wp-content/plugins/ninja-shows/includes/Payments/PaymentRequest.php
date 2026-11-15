<?php
/**
 * What the shop sends to a payment gateway for one charge.
 *
 * @package NinjaShows
 */

namespace NinjaShows\Payments;

final class PaymentRequest
{
    /**
     * @param int    $amountCents Total to charge, in cents.
     * @param string $currency    Three-letter currency code, e.g. USD.
     * @param string $cardNumber  Card number, digits only. Never stored by the shop.
     * @param int    $expiryMonth Card expiry month (1-12).
     * @param int    $expiryYear  Card expiry year (four digits).
     * @param string $cvc         Security code. Never stored by the shop.
     * @param string $reference   The shop's order reference, sent so the gateway can match the charge.
     */
    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $cardNumber,
        public readonly int $expiryMonth,
        public readonly int $expiryYear,
        public readonly string $cvc,
        public readonly string $reference,
    ) {
    }
}
