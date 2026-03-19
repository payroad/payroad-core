<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\PaymentFlow\P2P\P2PPaymentAttempt;
use Payroad\Domain\PaymentFlow\P2P\P2PStateMachine;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Tests\Stub\StubP2PData;
use PHPUnit\Framework\TestCase;

final class P2PStateMachineTest extends TestCase
{
    private P2PStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new P2PStateMachine();
    }

    private function makeAttempt(): P2PPaymentAttempt
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        return P2PPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', new StubP2PData());
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToAwaitingConfirmationIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::AWAITING_CONFIRMATION));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::FAILED));
    }

    // ── AWAITING_CONFIRMATION transitions ────────────────────────────────────

    public function testAwaitingConfirmationToProcessingIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::PROCESSING));
    }

    public function testAwaitingConfirmationToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::FAILED));
    }

    public function testAwaitingConfirmationToExpiredIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::EXPIRED));
    }

    // ── PROCESSING transitions ────────────────────────────────────────────────

    public function testProcessingToSucceededIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PROCESSING, AttemptStatus::SUCCEEDED));
    }

    public function testProcessingToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PROCESSING, AttemptStatus::FAILED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testFailedToSucceededIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::FAILED, AttemptStatus::SUCCEEDED));
    }

    // ── applyTransition on the attempt ───────────────────────────────────────

    public function testApplyTransitionOnAttemptThrowsOnInvalidTransition(): void
    {
        $attempt = $this->makeAttempt();

        $this->expectException(InvalidTransitionException::class);
        $attempt->applyTransition(AttemptStatus::SUCCEEDED, 'succeeded');
    }

    public function testApplyTransitionOnAttemptAppliesValidTransition(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');

        $this->assertSame(AttemptStatus::AWAITING_CONFIRMATION, $attempt->getStatus());
    }
}
