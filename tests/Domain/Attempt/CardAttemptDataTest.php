<?php

namespace Tests\Domain\Attempt;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\Payment\PaymentId;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

/**
 * Verifies that CardPaymentAttempt correctly stores and exposes
 * all CardAttemptData fields, both at creation and after updateCardData().
 */
final class CardAttemptDataTest extends TestCase
{
    private function makeAttempt(?StubSpecificData $data = null): CardPaymentAttempt
    {
        return CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            PaymentId::generate(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            $data ?? new StubSpecificData()
        );
    }

    public function testGetDataReturnsInitialData(): void
    {
        $data    = new StubSpecificData();
        $attempt = $this->makeAttempt($data);

        $this->assertSame($data, $attempt->getData());
    }

    public function testBinIsExposedCorrectly(): void
    {
        $this->assertSame('424242', $this->makeAttempt()->getData()->getBin());
    }

    public function testLast4IsExposedCorrectly(): void
    {
        $this->assertSame('4242', $this->makeAttempt()->getData()->getLast4());
    }

    public function testExpiryMonthIsExposedCorrectly(): void
    {
        $this->assertSame(12, $this->makeAttempt()->getData()->getExpiryMonth());
    }

    public function testExpiryYearIsExposedCorrectly(): void
    {
        $this->assertSame(2030, $this->makeAttempt()->getData()->getExpiryYear());
    }

    public function testCardholderNameIsExposedCorrectly(): void
    {
        $this->assertSame('John Doe', $this->makeAttempt()->getData()->getCardholderName());
    }

    public function testCardBrandIsExposedCorrectly(): void
    {
        $this->assertSame('visa', $this->makeAttempt()->getData()->getCardBrand());
    }

    public function testFundingTypeIsExposedCorrectly(): void
    {
        $this->assertSame('credit', $this->makeAttempt()->getData()->getFundingType());
    }

    public function testIssuingCountryIsExposedCorrectly(): void
    {
        $this->assertSame('US', $this->makeAttempt()->getData()->getIssuingCountry());
    }

    public function testRequiresUserActionReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->makeAttempt()->getData()->requiresUserAction());
    }

    public function testUpdateDataReplacesCardData(): void
    {
        $attempt  = $this->makeAttempt();
        $newData  = new StubSpecificData();

        $attempt->updateCardData($newData);

        $this->assertSame($newData, $attempt->getData());
    }

    public function testUpdateSpecificDataRejectsNonCardData(): void
    {
        $attempt = $this->makeAttempt();

        $this->expectException(\InvalidArgumentException::class);
        $attempt->updateSpecificData(new class implements \Payroad\Domain\Attempt\AttemptData {
            public function toArray(): array { return []; }
        });
    }

    public function testAttemptTransitionPreservesCardData(): void
    {
        $data    = new StubSpecificData();
        $attempt = $this->makeAttempt($data);

        $attempt->applyTransition(AttemptStatus::PROCESSING, 'processing');

        $this->assertSame($data, $attempt->getData());
        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
    }
}
