<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\StateMachine\P2PStateMachine;
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

final class P2PStateMachineTest extends TestCase
{
    private P2PStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new P2PStateMachine();
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
            PaymentMethodType::P2P,
            'stub',
            new StubSpecificData()
        );
    }

    private function makeAwaitingConfirmationAttempt(): PaymentAttempt
    {
        $attempt = $this->makePendingAttempt();
        $attempt->transitionTo(AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');
        return $attempt;
    }

    private function makeProcessingAttempt(): PaymentAttempt
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
        return $attempt;
    }

    private function makeFailedAttempt(): PaymentAttempt
    {
        $attempt = $this->makePendingAttempt();
        $attempt->transitionTo(AttemptStatus::FAILED, 'failed');
        return $attempt;
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToAwaitingConfirmationIsAllowed(): void
    {
        $attempt = $this->makePendingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::AWAITING_CONFIRMATION));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $attempt = $this->makePendingAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    // ── AWAITING_CONFIRMATION transitions ────────────────────────────────────

    public function testAwaitingConfirmationToProcessingIsAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::PROCESSING));
    }

    public function testAwaitingConfirmationToFailedIsAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testAwaitingConfirmationToExpiredIsAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::EXPIRED));
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

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testFailedToSucceededIsNotAllowed(): void
    {
        $attempt = $this->makeFailedAttempt();
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
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

        $this->sm->applyTransition($attempt, AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');

        $this->assertSame(AttemptStatus::AWAITING_CONFIRMATION, $attempt->getStatus());
    }
}
