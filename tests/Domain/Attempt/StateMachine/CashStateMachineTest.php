<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\StateMachine\CashStateMachine;
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

final class CashStateMachineTest extends TestCase
{
    private CashStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CashStateMachine();
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
            PaymentMethodType::CASH,
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

    private function makeSucceededAttempt(): PaymentAttempt
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $attempt->transitionTo(AttemptStatus::SUCCEEDED, 'succeeded');
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

    public function testAwaitingConfirmationToSucceededIsAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    public function testAwaitingConfirmationToExpiredIsAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::EXPIRED));
    }

    /**
     * Cash has no FAILED transition from AWAITING_CONFIRMATION — only EXPIRED.
     */
    public function testAwaitingConfirmationToFailedIsNotAllowed(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToExpiredIsNotAllowed(): void
    {
        $attempt = $this->makeSucceededAttempt();
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::EXPIRED));
    }

    // ── applyTransition ───────────────────────────────────────────────────────

    public function testApplyTransitionThrowsInvalidTransitionExceptionOnInvalid(): void
    {
        $attempt = $this->makeAwaitingConfirmationAttempt();

        $this->expectException(InvalidTransitionException::class);
        // FAILED from AWAITING_CONFIRMATION is not allowed for cash
        $this->sm->applyTransition($attempt, AttemptStatus::FAILED, 'failed');
    }

    public function testApplyTransitionAppliesValidTransition(): void
    {
        $attempt = $this->makePendingAttempt();
        $attempt->releaseEvents();

        $this->sm->applyTransition($attempt, AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');

        $this->assertSame(AttemptStatus::AWAITING_CONFIRMATION, $attempt->getStatus());
    }
}
