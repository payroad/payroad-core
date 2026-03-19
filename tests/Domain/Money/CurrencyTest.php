<?php

namespace Tests\Domain\Money;

use InvalidArgumentException;
use Payroad\Domain\Money\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    // ── Construction & validation ─────────────────────────────────────────────

    public function testValidThreeLetterFiatCodeIsAccepted(): void
    {
        $currency = new Currency('USD', 2);
        $this->assertSame('USD', $currency->code);
        $this->assertSame(2, $currency->precision);
    }

    public function testFourLetterCryptoCodeIsAccepted(): void
    {
        $currency = new Currency('USDT', 6);
        $this->assertSame('USDT', $currency->code);
        $this->assertSame(6, $currency->precision);
    }

    public function testTwoLetterCodeIsAccepted(): void
    {
        $currency = new Currency('BT', 8);
        $this->assertSame('BT', $currency->code);
    }

    public function testAlphanumericCodeIsAccepted(): void
    {
        $currency = new Currency('ERC20', 6);
        $this->assertSame('ERC20', $currency->code);
    }

    public function testZeroPrecisionIsAccepted(): void
    {
        $currency = new Currency('JPY', 0);
        $this->assertSame(0, $currency->precision);
    }

    public function testMaxPrecisionEighteenIsAccepted(): void
    {
        $currency = new Currency('ETH', 18);
        $this->assertSame(18, $currency->precision);
    }

    public function testEmptyCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('', 2);
    }

    public function testOneLetterCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('U', 2);
    }

    public function testElevenLetterCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('TOOLONGCODE', 2);
    }

    public function testLowercaseCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('usd', 2);
    }

    public function testCodeWithSpecialCharactersThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('US-D', 2);
    }

    public function testNegativePrecisionThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('USD', -1);
    }

    public function testPrecisionAbove18ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('ETH', 19);
    }

    // ── equals() ──────────────────────────────────────────────────────────────

    public function testEqualsReturnsTrueForSameCodeAndPrecision(): void
    {
        $a = new Currency('USD', 2);
        $b = new Currency('USD', 2);
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCode(): void
    {
        $a = new Currency('USD', 2);
        $b = new Currency('EUR', 2);
        $this->assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForSameCodeDifferentPrecision(): void
    {
        $a = new Currency('XYZ', 2);
        $b = new Currency('XYZ', 6);
        $this->assertFalse($a->equals($b));
    }

    // ── __toString() ──────────────────────────────────────────────────────────

    public function testToStringReturnsCode(): void
    {
        $this->assertSame('JPY', (string) new Currency('JPY', 0));
        $this->assertSame('USDT', (string) new Currency('USDT', 6));
    }
}
