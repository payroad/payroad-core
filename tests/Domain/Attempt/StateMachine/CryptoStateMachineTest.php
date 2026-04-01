<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\PaymentFlow\Crypto\CryptoStateMachine;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Tests\Stub\StubCryptoData;
use PHPUnit\Framework\TestCase;

final class CryptoStateMachineTest extends TestCase
{
    private CryptoStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CryptoStateMachine();
    }

    private function makeAttempt(): CryptoPaymentAttempt
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );

        return CryptoPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCryptoData());
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToProcessingIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::PROCESSING));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::FAILED));
    }

    // Direct PENDING → SUCCEEDED is intentionally allowed for providers that confirm
    // instantly without an intermediate status (e.g. CoinGate "paid" event).
    public function testPendingToSucceededIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::SUCCEEDED));
    }

    public function testPendingToPartiallyPaidIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::PARTIALLY_PAID));
    }

    public function testPendingToAwaitingConfirmationIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::AWAITING_CONFIRMATION));
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

    public function testProcessingToExpiredIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PROCESSING, AttemptStatus::EXPIRED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToFailedIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::SUCCEEDED, AttemptStatus::FAILED));
    }

    // ── semantic transition methods ───────────────────────────────────────

    public function testApplyTransitionOnAttemptThrowsOnInvalidTransition(): void
    {
        $attempt = $this->makeAttempt();

        $this->expectException(InvalidTransitionException::class);
        // PENDING → AWAITING_CONFIRMATION is not a valid crypto transition
        $attempt->markAwaitingConfirmation('awaiting');
    }

    public function testApplyTransitionOnAttemptAppliesValidTransition(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markProcessing('processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
    }
}
