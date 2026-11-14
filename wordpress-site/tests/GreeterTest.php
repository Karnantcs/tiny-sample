<?php

namespace NinjaHello\Tests;

use NinjaHello\Greeter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the greeting logic. These run without a WordPress install.
 */
final class GreeterTest extends TestCase
{
    public function testGreetsGivenName(): void
    {
        $this->assertSame('Hello, Ada!', Greeter::greeting('Ada'));
    }

    public function testEmptyNameFallsBackToWorld(): void
    {
        $this->assertSame('Hello, world!', Greeter::greeting(''));
    }

    public function testWhitespaceOnlyNameFallsBackToWorld(): void
    {
        $this->assertSame('Hello, world!', Greeter::greeting("  \t\n "));
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $this->assertSame('Hello, Grace!', Greeter::greeting('  Grace  '));
    }

    public function testInnerWhitespaceIsKept(): void
    {
        $this->assertSame('Hello, Mary Ann!', Greeter::greeting('Mary Ann'));
    }

    public function testMultibyteNameIsKept(): void
    {
        $this->assertSame('Hello, Zoë!', Greeter::greeting('Zoë'));
    }

    public function testGreetingIsPlainTextNotEscaped(): void
    {
        // Escaping for HTML is the shortcode's job, not the greeter's.
        $this->assertSame('Hello, <b>x</b>!', Greeter::greeting('<b>x</b>'));
    }

    public function testDefaultNameConstant(): void
    {
        $this->assertSame('world', Greeter::DEFAULT_NAME);
    }
}
