<?php

namespace Tests\Domain\Attempt;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Card\ThreeDSData;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificDataWith3DS;

final class ThreeDSDataTest extends TestCase
{
    private function makeThreeDSAttempt(?ThreeDSData $data = null): CardPaymentAttempt
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        return CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubSpecificDataWith3DS($data)
        );
    }

    public function testGetThreeDSDataReturnsNullWhenNoChallenge(): void
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new \Tests\Stub\StubSpecificData()
        );

        $this->assertNull($attempt->getData()->getThreeDSData());
    }

    public function testGetThreeDSDataReturnsDataWhenPresent(): void
    {
        $attempt = $this->makeThreeDSAttempt();

        $tds = $attempt->getData()->getThreeDSData();
        $this->assertNotNull($tds);
        $this->assertSame('2.2', $tds->version);
    }

    public function testRequiresChallengeReturnsTrueWhenAcsUrlAndCreqPresent(): void
    {
        $tds = new ThreeDSData(
            version:    '2.2',
            redirectUrl: 'https://acs.example.com/challenge',
            creq:       base64_encode('{}'),
            acsUrl:     'https://acs.example.com/challenge',
        );

        $this->assertTrue($tds->requiresChallenge());
    }

    public function testRequiresChallengeReturnsFalseForFrictionlessFlow(): void
    {
        $tds = new ThreeDSData(
            version:    '2.2',
            redirectUrl: 'https://acs.example.com/method',
            methodUrl:  'https://acs.example.com/method',
        );

        $this->assertFalse($tds->requiresChallenge());
    }

    public function testIsV2ReturnsTrueForVersion2(): void
    {
        $this->assertTrue((new ThreeDSData('2.2', 'https://example.com'))->isV2());
        $this->assertTrue((new ThreeDSData('2.1', 'https://example.com'))->isV2());
    }

    public function testIsV2ReturnsFalseForVersion1(): void
    {
        $this->assertFalse((new ThreeDSData('1.0.2', 'https://example.com'))->isV2());
    }

    public function testThreeDSAttemptCanTransitionToAwaitingConfirmation(): void
    {
        $attempt = $this->makeThreeDSAttempt();
        $attempt->releaseEvents();

        $attempt->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_3ds');

        $this->assertSame(AttemptStatus::AWAITING_CONFIRMATION, $attempt->getStatus());
        $this->assertNotNull($attempt->getData()->getThreeDSData());
    }
}
