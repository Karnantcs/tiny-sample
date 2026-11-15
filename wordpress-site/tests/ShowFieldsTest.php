<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use NinjaShows\ShowFields;
use PHPUnit\Framework\TestCase;

/**
 * Tests the show date and ticket parsing. These run without a WordPress install.
 */
final class ShowFieldsTest extends TestCase
{
    public function testValidDateTimeIsKept(): void
    {
        $this->assertSame('2025-06-01T19:30', ShowFields::parseDate('2025-06-01T19:30'));
    }

    /** @return array<string, array{0: string}> */
    public static function invalidDates(): array
    {
        return [
            'empty' => [''],
            'impossible day' => ['2025-02-30T10:00'],
            'space instead of T' => ['2025-06-01 19:30'],
            'no time' => ['2025-06-01'],
            'bad hour' => ['2025-06-01T25:00'],
        ];
    }

    /** @dataProvider invalidDates */
    public function testInvalidDatesAreRejected(string $input): void
    {
        $this->assertNull(ShowFields::parseDate($input));
    }

    public function testTicketCountsAreWholeNumbers(): void
    {
        $this->assertSame(0, ShowFields::parseTickets('0'));
        $this->assertSame(40, ShowFields::parseTickets(' 40 '));
    }

    public function testBadTicketCountsAreRejected(): void
    {
        $this->assertNull(ShowFields::parseTickets('-1'));
        $this->assertNull(ShowFields::parseTickets('abc'));
        $this->assertNull(ShowFields::parseTickets(''));
        $this->assertNull(ShowFields::parseTickets('1000000'));
    }

    public function testDisplayFormat(): void
    {
        $this->assertSame('Sun 1 Jun 2025, 7:30 pm', ShowFields::formatForDisplay('2025-06-01T19:30'));
        $this->assertSame('', ShowFields::formatForDisplay('not a date'));
    }
}
