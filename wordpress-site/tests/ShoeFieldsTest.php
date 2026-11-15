<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use NinjaShows\ShoeFields;
use PHPUnit\Framework\TestCase;

/**
 * Tests the shoe size and stock parsing. These run without a WordPress install.
 */
final class ShoeFieldsTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function validSizes(): array
    {
        return [
            'US with a number' => [' US 9 ', 'US 9'],
            'half size' => ['UK 8.5', 'UK 8.5'],
            'EU' => ['EU 43', 'EU 43'],
            'slash' => ['W 7/8', 'W 7/8'],
        ];
    }

    /** @dataProvider validSizes */
    public function testValidSizesAreKeptTrimmed(string $input, string $expected): void
    {
        $this->assertSame($expected, ShoeFields::parseSize($input));
    }

    /** @return array<string, array{0: string}> */
    public static function invalidSizes(): array
    {
        return [
            'empty' => [''],
            'only spaces' => ['   '],
            'too long' => [str_repeat('9', ShoeFields::MAX_SIZE_LENGTH + 1)],
            'starts with a dot' => ['.5'],
            'angle brackets' => ['<9>'],
        ];
    }

    /** @dataProvider invalidSizes */
    public function testInvalidSizesAreRejected(string $input): void
    {
        $this->assertNull(ShoeFields::parseSize($input));
    }

    public function testStockCountsAreWholeNumbers(): void
    {
        $this->assertSame(0, ShoeFields::parseStock('0'));
        $this->assertSame(12, ShoeFields::parseStock(' 12 '));
    }

    public function testBadStockCountsAreRejected(): void
    {
        $this->assertNull(ShoeFields::parseStock('-1'));
        $this->assertNull(ShoeFields::parseStock('abc'));
        $this->assertNull(ShoeFields::parseStock(''));
        $this->assertNull(ShoeFields::parseStock('1000000'));
    }
}
