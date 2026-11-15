<?php

namespace NinjaShows\Tests;

require_once __DIR__ . '/NinjaShowsLoader.php';

use NinjaShows\Money;
use PHPUnit\Framework\TestCase;

/**
 * Tests price parsing and formatting. These run without a WordPress install.
 */
final class MoneyTest extends TestCase
{
    public function testWholeAmountBecomesCents(): void
    {
        $this->assertSame(4500, Money::parseToCents('45'));
    }

    public function testOneOrTwoDecimalsAreRead(): void
    {
        $this->assertSame(4550, Money::parseToCents('45.5'));
        $this->assertSame(4500, Money::parseToCents('45.00'));
        $this->assertSame(4505, Money::parseToCents('45.05'));
    }

    public function testThousandsSeparatorIsAllowed(): void
    {
        $this->assertSame(125099, Money::parseToCents('1,250.99'));
    }

    public function testZeroIsAllowed(): void
    {
        $this->assertSame(0, Money::parseToCents('0'));
    }

    /** @return array<string, array{0: string}> */
    public static function invalidPrices(): array
    {
        return [
            'empty' => [''],
            'letters' => ['abc'],
            'negative' => ['-5'],
            'three decimals' => ['1.234'],
            'trailing dot' => ['45.'],
            'leading dot' => ['.5'],
            'too many digits' => ['12345678'],
            'currency sign' => ['$45'],
        ];
    }

    /** @dataProvider invalidPrices */
    public function testInvalidPricesAreRejected(string $input): void
    {
        $this->assertNull(Money::parseToCents($input));
    }

    public function testFormatsCentsWithSymbolAndSeparators(): void
    {
        $this->assertSame('$45.00', Money::format(4500));
        $this->assertSame('$1,250.99', Money::format(125099));
        $this->assertSame('$0.05', Money::format(5));
        $this->assertSame('$0.00', Money::format(0));
    }
}
