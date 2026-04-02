<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\Event\AttemptAuthorized;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardStateMachine;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Tests\Stub\StubSpecificData;
use PHPUnit\Framework\TestCase;

final class CardStateMachineAuthorizeTest extends TestCase
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

    // ── PENDING → AUTHORIZED ─────────────────────────────────────────────────

    public function testPendingToAuthorizedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::PENDING, AttemptStatus::AUTHORIZED));
    }

    // ── AUTHORIZED transitions ────────────────────────────────────────────────

    public function testAuthorizedToProcessingIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AUTHORIZED, AttemptStatus::PROCESSING));
    }

    public function testAuthorizedToSucceededIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AUTHORIZED, AttemptStatus::SUCCEEDED));
    }

    public function testAuthorizedToCanceledIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AUTHORIZED, AttemptStatus::CANCELED));
    }

    public function testAuthorizedToFailedIsAllowed(): void
    {
        $this->assertTrue($this->sm->canTransition(AttemptStatus::AUTHORIZED, AttemptStatus::FAILED));
    }

    public function testAuthorizedToAwaitingConfirmationIsNotAllowed(): void
    {
        $this->assertFalse($this->sm->canTransition(AttemptStatus::AUTHORIZED, AttemptStatus::AWAITING_CONFIRMATION));
    }

    // ── markAuthorized records AttemptAuthorized event ──────────────────────

    public function testApplyTransitionToAuthorizedRecordsAttemptAuthorizedEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markAuthorized('authorized');

        $events = $attempt->releaseEvents();
        $authorized = array_filter($events, fn($e) => $e instanceof AttemptAuthorized);

        $this->assertCount(1, $authorized);
        $this->assertSame(AttemptStatus::AUTHORIZED, $attempt->getStatus());
    }

    public function testAuthorizedIsNotTerminal(): void
    {
        $this->assertFalse(AttemptStatus::AUTHORIZED->isTerminal());
    }

    public function testAuthorizedIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::AUTHORIZED->isSuccess());
    }

    public function testAuthorizedIsNotFailure(): void
    {
        $this->assertFalse(AttemptStatus::AUTHORIZED->isFailure());
    }

    // ── Full authorize + capture flow ─────────────────────────────────────────

    public function testFullAuthorizeCaptureFlowSucceeds(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markAuthorized('authorized');
        $attempt->markSucceeded('captured');

        $this->assertSame(AttemptStatus::SUCCEEDED, $attempt->getStatus());
    }

    public function testFullAuthorizeVoidFlow(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markAuthorized('authorized');
        $attempt->markCanceled('voided');

        $this->assertSame(AttemptStatus::CANCELED, $attempt->getStatus());
    }

    public function testCannotTransitionFromAuthorizedToAuthorized(): void
    {
        $this->expectException(InvalidTransitionException::class);
        $attempt = $this->makeAttempt();
        $attempt->markAuthorized('authorized');
        $attempt->markAuthorized('authorized');
    }
}
