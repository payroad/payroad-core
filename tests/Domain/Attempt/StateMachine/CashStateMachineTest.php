<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Cash\CashPaymentAttempt;
use Payroad\Domain\PaymentFlow\Cash\CashStateMachine;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Tests\Stub\StubCashData;
use PHPUnit\Framework\TestCase;

final class CashStateMachineTest extends TestCase
{
    private CashStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CashStateMachine();
    }

    private function makeAttempt(): CashPaymentAttempt
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        return CashPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCashData());
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

    public function testAwaitingConfirmationToSucceededIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::SUCCEEDED));
    }

    public function testAwaitingConfirmationToExpiredIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::EXPIRED));
    }

    /**
     * Cash has no FAILED transition from AWAITING_CONFIRMATION — only EXPIRED.
     */
    public function testAwaitingConfirmationToFailedIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::FAILED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToExpiredIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::SUCCEEDED, AttemptStatus::EXPIRED));
    }

    // ── applyTransition on the attempt ───────────────────────────────────────

    public function testApplyTransitionOnAttemptThrowsOnInvalidTransition(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');

        $this->expectException(InvalidTransitionException::class);
        $attempt->applyTransition(AttemptStatus::FAILED, 'failed');
    }

    public function testApplyTransitionOnAttemptAppliesValidTransition(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, 'awaiting_confirmation');

        $this->assertSame(AttemptStatus::AWAITING_CONFIRMATION, $attempt->getStatus());
    }
}
