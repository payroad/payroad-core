<?php

namespace Tests\Domain\Money;

use InvalidArgumentException;
use Payroad\Domain\Money\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testValidThreeLetterUppercaseCodeIsAccepted(): void
    {
        $currency = new Currency('USD');
        $this->assertSame('USD', $currency->code);
    }

    public function testOfNormalisesLowercaseToUppercase(): void
    {
        $currency = Currency::of('usd');
        $this->assertSame('USD', $currency->code);
    }

    public function testOfAcceptsMixedCase(): void
    {
        $currency = Currency::of('Eur');
        $this->assertSame('EUR', $currency->code);
    }

    public function testOfAlreadyUppercasePassesThrough(): void
    {
        $currency = Currency::of('GBP');
        $this->assertSame('GBP', $currency->code);
    }

    public function testInvalidCodeWithNumbersThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('U1D');
    }

    public function testTwoLetterCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('US');
    }

    public function testFourLetterCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('USDT');
    }

    public function testEmptyCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Currency('');
    }

    public function testEqualsReturnsTrueForSameCode(): void
    {
        $a = new Currency('USD');
        $b = new Currency('USD');
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCode(): void
    {
        $a = new Currency('USD');
        $b = new Currency('EUR');
        $this->assertFalse($a->equals($b));
    }

    public function testToStringReturnsCode(): void
    {
        $currency = new Currency('JPY');
        $this->assertSame('JPY', (string) $currency);
    }
}
