<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\StateMachine\CryptoStateMachine;
use Payroad\Domain\Exception\InvalidTransitionException;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentMethodType;
use Tests\Stub\StubSpecificData;
use PHPUnit\Framework\TestCase;

final class CryptoStateMachineTest extends TestCase
{
    private CryptoStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CryptoStateMachine();
    }

    private function makePendingAttempt(): PaymentAttempt
    {
        $payment = Payment::create(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of('idem-key-' . uniqid()),
            new PaymentMetadata()
        );

        return PaymentAttempt::create(
            $payment->getId(),
            PaymentMethodType::CRYPTO,
            'stub',
            new StubSpecificData()
        );
    }

    private function makeProcessingAttempt(): PaymentAttempt
    {
        $attempt = $this->makePendingAttempt();
        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
        return $attempt;
    }

    private function makeSucceededAttempt(): PaymentAttempt
    {
        $attempt = $this->makeProcessingAttempt();
        $attempt->transitionTo(AttemptStatus::SUCCEEDED, 'succeeded');
        return $attempt;
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToProcessingIsAllowed(): void
    {
        $attempt = $this->makePendingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::PROCESSING));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $attempt = $this->makePendingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testPendingToSucceededIsNotAllowed(): void
    {
        $attempt = $this->makePendingAttempt();
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    // ── PROCESSING transitions ────────────────────────────────────────────────

    public function testProcessingToSucceededIsAllowed(): void
    {
        $attempt = $this->makeProcessingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    public function testProcessingToFailedIsAllowed(): void
    {
        $attempt = $this->makeProcessingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testProcessingToExpiredIsAllowed(): void
    {
        $attempt = $this->makeProcessingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::EXPIRED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToFailedIsNotAllowed(): void
    {
        $attempt = $this->makeSucceededAttempt();
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    // ── applyTransition ───────────────────────────────────────────────────────

    public function testApplyTransitionThrowsInvalidTransitionExceptionOnInvalid(): void
    {
        $attempt = $this->makePendingAttempt();

        $this->expectException(InvalidTransitionException::class);
        $this->sm->applyTransition($attempt, AttemptStatus::SUCCEEDED, 'succeeded');
    }

    public function testApplyTransitionAppliesValidTransition(): void
    {
        $attempt = $this->makePendingAttempt();
        $attempt->releaseEvents();

        $this->sm->applyTransition($attempt, AttemptStatus::PROCESSING, 'processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
    }
}
