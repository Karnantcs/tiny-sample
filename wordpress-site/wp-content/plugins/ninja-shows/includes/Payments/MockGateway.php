<?php
/**
 * Mock payment gateway. It takes no money and answers with fixed responses,
 * so the shop can be tried end to end before a real gateway is connected.
 *
 * Test card numbers:
 *   4242 4242 4242 4242  approved
 *   4000 0000 0000 0002  declined (card_declined)
 *   4000 0000 0000 9995  declined (insufficient_funds)
 * Any other number that passes the Luhn check is approved.
 *
 * @package NinjaShows
 */

namespace NinjaShows\Payments;

use NinjaShows\CardValidator;

final class MockGateway implements PaymentGateway
{
    /** Declined test cards: number => [code, message]. */
    private const DECLINED_CARDS = [
        '4000000000000002' => ['card_declined', 'The card was declined.'],
        '4000000000009995' => ['insufficient_funds', 'The card has insufficient funds.'],
    ];

    public function charge(PaymentRequest $request): PaymentResult
    {
        if ($request->amountCents <= 0) {
            return PaymentResult::declined('invalid_amount', 'The amount must be more than zero.');
        }

        if (!CardValidator::isValidNumber($request->cardNumber)) {
            return PaymentResult::declined('invalid_card_number', 'The card number is not valid.');
        }

        if (isset(self::DECLINED_CARDS[$request->cardNumber])) {
            [$code, $message] = self::DECLINED_CARDS[$request->cardNumber];

            return PaymentResult::declined($code, $message);
        }

        return PaymentResult::approved('mock_' . bin2hex(random_bytes(8)));
    }
}
