<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardStateMachine;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Tests\Stub\StubSpecificData;
use PHPUnit\Framework\TestCase;

final class CardStateMachineTest extends TestCase
{
    private CardStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CardStateMachine();
    }

    private function makeAttempt(): CardPaymentAttempt
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        return CardPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToAwaitingConfirmationIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::AWAITING_CONFIRMATION));
    }

    public function testPendingToProcessingIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::PROCESSING));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::FAILED));
    }

    public function testPendingToSucceededIsAllowed(): void
    {
        // Stripe Variant B: provider confirms inline, no intermediate PROCESSING state.
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::SUCCEEDED));
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

    public function testAwaitingConfirmationToCanceledIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::CANCELED));
    }

    public function testAwaitingConfirmationToSucceededIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::SUCCEEDED));
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

    public function testProcessingToCanceledIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::PROCESSING, AttemptStatus::CANCELED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToFailedIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::SUCCEEDED, AttemptStatus::FAILED));
    }

    // ── semantic transition methods ───────────────────────────────────────

    public function testApplyTransitionOnAttemptAppliesValidTransition(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markProcessing('processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
        $this->assertSame('processing', $attempt->getProviderStatus());
    }

    public function testApplyTransitionOnAttemptThrowsOnInvalidTransition(): void
    {
        $attempt = $this->makeAttempt();

        $this->expectException(InvalidTransitionException::class);
        $attempt->markExpired('expired'); // PENDING → EXPIRED is not allowed
    }
}
