<?php

namespace Tests\Domain\Money;

use InvalidArgumentException;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    private Currency $usd;
    private Currency $eur;

    protected function setUp(): void
    {
        $this->usd = new Currency('USD', 2);
        $this->eur = new Currency('EUR', 2);
    }

    public function testOfMinorStoresAmountCorrectly(): void
    {
        $money = Money::ofMinor(1999, $this->usd);
        $this->assertSame(1999, $money->getMinorAmount());
        $this->assertTrue($money->getCurrency()->equals($this->usd));
    }

    /**
     * @dataProvider decimalConversionProvider
     */
    public function testOfDecimalUsesBcmathCorrectly(string $decimal, int $expectedMinor): void
    {
        $money = Money::ofDecimal($decimal, $this->usd);
        $this->assertSame($expectedMinor, $money->getMinorAmount());
    }

    public static function decimalConversionProvider(): array
    {
        return [
            'classic float bug 1.15' => ['1.15', 115],
            'classic float bug 2.55' => ['2.55', 255],
            'classic float bug 19.99' => ['19.99', 1999],
            'round number' => ['10.00', 1000],
            'zero cents' => ['5.00', 500],
        ];
    }

    public function testAddReturnsSumInSameCurrency(): void
    {
        $a = Money::ofMinor(500, $this->usd);
        $b = Money::ofMinor(300, $this->usd);
        $result = $a->add($b);
        $this->assertSame(800, $result->getMinorAmount());
        $this->assertTrue($result->getCurrency()->equals($this->usd));
    }

    public function testSubtractReturnsDifference(): void
    {
        $a = Money::ofMinor(1000, $this->usd);
        $b = Money::ofMinor(300, $this->usd);
        $result = $a->subtract($b);
        $this->assertSame(700, $result->getMinorAmount());
    }

    public function testIsGreaterThanWorksCorrectly(): void
    {
        $larger  = Money::ofMinor(1000, $this->usd);
        $smaller = Money::ofMinor(500, $this->usd);
        $this->assertTrue($larger->isGreaterThan($smaller));
        $this->assertFalse($smaller->isGreaterThan($larger));
        $this->assertFalse($larger->isGreaterThan($larger));
    }

    public function testIsZeroReturnsTrueForZeroAmount(): void
    {
        $money = Money::ofMinor(0, $this->usd);
        $this->assertTrue($money->isZero());
    }

    public function testIsZeroReturnsFalseForNonZeroAmount(): void
    {
        $money = Money::ofMinor(1, $this->usd);
        $this->assertFalse($money->isZero());
    }

    public function testEqualsComparesBothAmountAndCurrency(): void
    {
        $a = Money::ofMinor(1000, $this->usd);
        $b = Money::ofMinor(1000, $this->usd);
        $c = Money::ofMinor(1000, $this->eur);
        $d = Money::ofMinor(500, $this->usd);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
        $this->assertFalse($a->equals($d));
    }

    public function testAddWithDifferentCurrenciesThrowsInvalidArgumentException(): void
    {
        $usdMoney = Money::ofMinor(500, $this->usd);
        $eurMoney = Money::ofMinor(300, $this->eur);

        $this->expectException(InvalidArgumentException::class);
        $usdMoney->add($eurMoney);
    }

    public function testNegativeAmountInConstructorThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::ofMinor(-1, $this->usd);
    }

    public function testToDecimalStringReturnsCorrectFormattedString(): void
    {
        $money = Money::ofMinor(1999, $this->usd);
        $this->assertSame('19.99', $money->toDecimalString());
    }

    public function testToDecimalStringForRoundAmount(): void
    {
        $money = Money::ofMinor(1000, $this->usd);
        $this->assertSame('10.00', $money->toDecimalString());
    }

    public function testToDecimalStringForZero(): void
    {
        $money = Money::ofMinor(0, $this->usd);
        $this->assertSame('0.00', $money->toDecimalString());
    }

    // ── ISO 4217 non-standard subunit exponents ───────────────────────────────

    public function testOfDecimalForJpyUsesZeroDecimalPlaces(): void
    {
        $jpy   = new Currency('JPY', 0);
        $money = Money::ofDecimal('1500', $jpy);
        $this->assertSame(1500, $money->getMinorAmount());
    }

    public function testOfDecimalForKrwUsesZeroDecimalPlaces(): void
    {
        $krw   = new Currency('KRW', 0);
        $money = Money::ofDecimal('50000', $krw);
        $this->assertSame(50000, $money->getMinorAmount());
    }

    public function testOfDecimalForKwdUsesThreeDecimalPlaces(): void
    {
        $kwd   = new Currency('KWD', 3);
        $money = Money::ofDecimal('10.500', $kwd);
        $this->assertSame(10500, $money->getMinorAmount());
    }

    public function testToDecimalStringForJpyReturnsWholeNumber(): void
    {
        $jpy   = new Currency('JPY', 0);
        $money = Money::ofMinor(1500, $jpy);
        $this->assertSame('1500', $money->toDecimalString());
    }

    public function testToDecimalStringForKwdReturnsThreeDecimalPlaces(): void
    {
        $kwd   = new Currency('KWD', 3);
        $money = Money::ofMinor(10500, $kwd);
        $this->assertSame('10.500', $money->toDecimalString());
    }

    // ── High-precision crypto (ETH/wei overflow guard) ────────────────────────

    public function testOfDecimalForEthDoesNotOverflow(): void
    {
        $eth   = new Currency('ETH', 18);
        $money = Money::ofDecimal('100', $eth);
        $this->assertSame('100000000000000000000', $money->getMinorAmountString());
    }

    public function testGetMinorAmountStringIsAlwaysSafe(): void
    {
        $eth   = new Currency('ETH', 18);
        $money = Money::ofDecimal('10', $eth);
        $this->assertSame('10000000000000000000', $money->getMinorAmountString());
    }

    public function testGetMinorAmountThrowsOverflowForLargeEthAmount(): void
    {
        $eth   = new Currency('ETH', 18);
        $money = Money::ofDecimal('100', $eth);
        $this->expectException(\OverflowException::class);
        $money->getMinorAmount();
    }

    public function testAddWithLargeEthAmountsIsCorrect(): void
    {
        $eth = new Currency('ETH', 18);
        $a   = Money::ofDecimal('50', $eth);
        $b   = Money::ofDecimal('50', $eth);
        $sum = $a->add($b);
        $this->assertSame('100000000000000000000', $sum->getMinorAmountString());
    }

    public function testToDecimalStringForEthIsCorrect(): void
    {
        $eth   = new Currency('ETH', 18);
        $money = Money::ofDecimal('1.5', $eth);
        $this->assertSame('1.500000000000000000', $money->toDecimalString());
    }
}
