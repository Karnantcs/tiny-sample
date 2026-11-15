<?php
/**
 * Contract for a payment gateway. The shop talks only to this interface, so a
 * real gateway can replace the mock later without changing the checkout.
 *
 * @package NinjaShows
 */

namespace NinjaShows\Payments;

interface PaymentGateway
{
    /**
     * Charge the card for one order. Must not throw for a declined card:
     * return PaymentResult::declined() instead.
     */
    public function charge(PaymentRequest $request): PaymentResult;
}
