<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use NinjaShows\Payments\MockGateway;
use NinjaShows\Payments\PaymentRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests the mock payment gateway. These run without a WordPress install.
 */
final class MockGatewayTest extends TestCase
{
    private function request(int $amount = 9000, string $card = '4242424242424242'): PaymentRequest
    {
        return new PaymentRequest($amount, 'USD', $card, 12, 2029, '123', 'NS-TEST');
    }

    public function testTestCardIsApproved(): void
    {
        $result = (new MockGateway())->charge($this->request());

        $this->assertTrue($result->approved);
        $this->assertMatchesRegularExpression('/^mock_[0-9a-f]{16}$/', $result->transactionId);
    }

    public function testEachApprovalGetsANewTransactionId(): void
    {
        $gateway = new MockGateway();

        $this->assertNotSame(
            $gateway->charge($this->request())->transactionId,
            $gateway->charge($this->request())->transactionId
        );
    }

    public function testDeclineCardIsDeclined(): void
    {
        $result = (new MockGateway())->charge($this->request(9000, '4000000000000002'));

        $this->assertFalse($result->approved);
        $this->assertSame('card_declined', $result->code);
        $this->assertSame('The card was declined.', $result->message);
        $this->assertSame('', $result->transactionId);
    }

    public function testInsufficientFundsCardIsDeclined(): void
    {
        $result = (new MockGateway())->charge($this->request(9000, '4000000000009995'));

        $this->assertFalse($result->approved);
        $this->assertSame('insufficient_funds', $result->code);
    }

    public function testZeroAmountIsDeclined(): void
    {
        $result = (new MockGateway())->charge($this->request(0));

        $this->assertFalse($result->approved);
        $this->assertSame('invalid_amount', $result->code);
    }

    public function testInvalidCardNumberIsDeclined(): void
    {
        $result = (new MockGateway())->charge($this->request(9000, '4242424242424241'));

        $this->assertFalse($result->approved);
        $this->assertSame('invalid_card_number', $result->code);
    }
}
