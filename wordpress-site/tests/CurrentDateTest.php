<?php

namespace NinjaHello\Tests;

use DateTimeImmutable;
use DateTimeZone;
use NinjaHello\CurrentDate;
use PHPUnit\Framework\TestCase;

/**
 * Tests the date text used by [ninja_time]. These run without a WordPress install.
 */
final class CurrentDateTest extends TestCase
{
    public function testFormatsTheDateAsYearMonthDay(): void
    {
        $now = new DateTimeImmutable('2026-11-14 09:30:00', new DateTimeZone('UTC'));

        $this->assertSame('2026-11-14', CurrentDate::format($now));
    }

    public function testPadsSingleDigitMonthAndDay(): void
    {
        $now = new DateTimeImmutable('2027-03-05 12:00:00', new DateTimeZone('UTC'));

        $this->assertSame('2027-03-05', CurrentDate::format($now));
    }

    public function testUsesTheDateInTheTimezoneOfTheGivenTime(): void
    {
        // 23:30 on 14 Nov in New York is already 15 Nov in UTC.
        $newYork = new DateTimeImmutable('2026-11-14 23:30:00', new DateTimeZone('America/New_York'));
        $utc = $newYork->setTimezone(new DateTimeZone('UTC'));

        $this->assertSame('2026-11-14', CurrentDate::format($newYork));
        $this->assertSame('2026-11-15', CurrentDate::format($utc));
    }

    public function testFormatIsPlainTextWithNoMarkup(): void
    {
        $now = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', CurrentDate::format($now));
    }

    public function testFormatConstantIsYearMonthDay(): void
    {
        $this->assertSame('Y-m-d', CurrentDate::FORMAT);
    }
}
