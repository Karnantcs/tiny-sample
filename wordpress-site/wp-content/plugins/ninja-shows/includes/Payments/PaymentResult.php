<?php
/**
 * The answer from a payment gateway to one charge.
 *
 * @package NinjaShows
 */

namespace NinjaShows\Payments;

final class PaymentResult
{
    private function __construct(
        public readonly bool $approved,
        public readonly string $transactionId,
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    /** An approved charge with the gateway's transaction id. */
    public static function approved(string $transactionId): self
    {
        return new self(true, $transactionId, '', '');
    }

    /** A charge that was not approved. $code is a short machine-readable reason. */
    public static function declined(string $code, string $message): self
    {
        return new self(false, '', $code, $message);
    }
}
