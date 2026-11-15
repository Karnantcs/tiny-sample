<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use DateTimeImmutable;
use DateTimeZone;
use NinjaShows\Checkout;
use PHPUnit\Framework\TestCase;

/**
 * Tests the checkout form validation. These run without a WordPress install.
 */
final class CheckoutTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2025-06-15 12:00:00', new DateTimeZone('UTC'));
    }

    /** @return array<string, string> */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'quantity' => '2',
            'card_number' => '4242 4242 4242 4242',
            'expiry_month' => '12',
            'expiry_year' => '29',
            'cvc' => '123',
        ], $overrides);
    }

    public function testValidFormReturnsCleanOrder(): void
    {
        $result = Checkout::validate($this->validInput(), 10, $this->now);

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'quantity' => 2,
            'card_number' => '4242424242424242',
            'expiry_month' => 12,
            'expiry_year' => 2029,
            'cvc' => '123',
        ], $result['order']);
    }

    public function testMissingNameIsReported(): void
    {
        $result = Checkout::validate($this->validInput(['name' => '   ']), 10, $this->now);

        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertNull($result['order']);
    }

    public function testBadEmailIsReported(): void
    {
        $result = Checkout::validate($this->validInput(['email' => 'not-an-email']), 10, $this->now);

        $this->assertArrayHasKey('email', $result['errors']);
    }

    /** @return array<string, array{0: string}> */
    public static function badQuantities(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'not a number' => ['two'],
            'above the order limit' => ['11'],
            'empty' => [''],
        ];
    }

    /** @dataProvider badQuantities */
    public function testBadQuantityIsReported(string $quantity): void
    {
        $result = Checkout::validate($this->validInput(['quantity' => $quantity]), 50, $this->now);

        $this->assertArrayHasKey('quantity', $result['errors']);
    }

    public function testQuantityIsLimitedToTicketsLeft(): void
    {
        $result = Checkout::validate($this->validInput(['quantity' => '4']), 3, $this->now);

        $this->assertSame('Choose between 1 and 3 pairs.', $result['errors']['quantity']);
    }

    public function testSoldOutShoeIsReported(): void
    {
        $result = Checkout::validate($this->validInput(), 0, $this->now);

        $this->assertSame('This shoe is sold out.', $result['errors']['quantity']);
    }

    public function testLuhnInvalidCardIsReported(): void
    {
        $result = Checkout::validate($this->validInput(['card_number' => '4242424242424241']), 10, $this->now);

        $this->assertArrayHasKey('card_number', $result['errors']);
    }

    public function testExpiredCardIsReported(): void
    {
        $result = Checkout::validate($this->validInput(['expiry_month' => '05', 'expiry_year' => '2025']), 10, $this->now);

        $this->assertArrayHasKey('expiry', $result['errors']);
    }

    public function testExpiryAcceptsTwoOrFourDigitYears(): void
    {
        $this->assertSame(
            ['month' => 3, 'year' => 2027],
            Checkout::parseExpiry('3', '27', $this->now)
        );
        $this->assertSame(
            ['month' => 3, 'year' => 2027],
            Checkout::parseExpiry('03', '2027', $this->now)
        );
        $this->assertNull(Checkout::parseExpiry('13', '2027', $this->now));
        $this->assertNull(Checkout::parseExpiry('3', '202', $this->now));
    }

    public function testBadCvcIsReported(): void
    {
        $result = Checkout::validate($this->validInput(['cvc' => '12']), 10, $this->now);

        $this->assertArrayHasKey('cvc', $result['errors']);
    }

    public function testEveryProblemIsReportedAtOnce(): void
    {
        $result = Checkout::validate([], 10, $this->now);

        $this->assertSame(['name', 'email', 'quantity', 'card_number', 'expiry', 'cvc'], array_keys($result['errors']));
        $this->assertNull($result['order']);
    }

    public function testNonStringFieldsAreIgnored(): void
    {
        $result = Checkout::validate($this->validInput(['name' => ['Ada']]), 10, $this->now);

        $this->assertArrayHasKey('name', $result['errors']);
    }
}
