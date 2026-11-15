<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use DateTimeImmutable;
use NinjaShows\CardValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests the card format checks. These run without a WordPress install.
 */
final class CardValidatorTest extends TestCase
{
    public function testNumberWithSpacesOrDashesIsNormalized(): void
    {
        $this->assertSame('4242424242424242', CardValidator::normalizeNumber(' 4242 4242-4242 4242 '));
    }

    public function testLuhnValidNumberIsAccepted(): void
    {
        $this->assertTrue(CardValidator::isValidNumber('4242424242424242'));
    }

    public function testNumberFailingLuhnIsRejected(): void
    {
        $this->assertFalse(CardValidator::isValidNumber('4242424242424241'));
    }

    public function testNumberOfWrongLengthIsRejected(): void
    {
        $this->assertFalse(CardValidator::isValidNumber('42424242'));
        $this->assertFalse(CardValidator::isValidNumber('42424242424242424242'));
    }

    public function testNonDigitsAreRejected(): void
    {
        $this->assertFalse(CardValidator::isValidNumber('4242abcd42424242'));
        $this->assertFalse(CardValidator::isValidNumber(''));
    }

    public function testLastFourDigits(): void
    {
        $this->assertSame('4242', CardValidator::lastFour('4242424242424242'));
    }

    public function testCvcLengths(): void
    {
        $this->assertTrue(CardValidator::isValidCvc('123'));
        $this->assertTrue(CardValidator::isValidCvc('1234'));
        $this->assertFalse(CardValidator::isValidCvc('12'));
        $this->assertFalse(CardValidator::isValidCvc('12345'));
        $this->assertFalse(CardValidator::isValidCvc('1a3'));
    }

    public function testExpiryInTheCurrentMonthIsStillValid(): void
    {
        $now = new DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('UTC'));
        $this->assertTrue(CardValidator::isUnexpired(6, 2025, $now));
    }

    public function testExpiryInAPastMonthIsRejected(): void
    {
        $now = new DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('UTC'));
        $this->assertFalse(CardValidator::isUnexpired(5, 2025, $now));
        $this->assertFalse(CardValidator::isUnexpired(12, 2024, $now));
    }

    public function testFutureYearsAreValidUpToTwentyYears(): void
    {
        $now = new DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('UTC'));
        $this->assertTrue(CardValidator::isUnexpired(1, 2026, $now));
        $this->assertTrue(CardValidator::isUnexpired(12, 2045, $now));
        $this->assertFalse(CardValidator::isUnexpired(1, 2046, $now));
    }

    public function testMonthOutOfRangeIsRejected(): void
    {
        $now = new DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('UTC'));
        $this->assertFalse(CardValidator::isUnexpired(0, 2030, $now));
        $this->assertFalse(CardValidator::isUnexpired(13, 2030, $now));
    }
}
