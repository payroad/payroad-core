<?php

namespace Tests\Domain\Attempt\StateMachine;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\StateMachine\CardStateMachine;
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

final class CardStateMachineTest extends TestCase
{
    private CardStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new CardStateMachine();
    }

    private function makeAttemptInStatus(AttemptStatus $status): PaymentAttempt
    {
        $payment = Payment::create(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of('idem-key-' . uniqid()),
            new PaymentMetadata()
        );

        $attempt = PaymentAttempt::create(
            $payment->getId(),
            PaymentMethodType::CARD,
            'stub',
            new StubSpecificData()
        );

        // Transition the attempt to the desired starting status
        $this->advanceAttemptTo($attempt, $status);

        return $attempt;
    }

    private function advanceAttemptTo(PaymentAttempt $attempt, AttemptStatus $target): void
    {
        if ($target === AttemptStatus::PENDING) {
            return;
        }

        // Map target to a reachable path for card state machine
        $paths = [
            AttemptStatus::AWAITING_CONFIRMATION->value => [AttemptStatus::AWAITING_CONFIRMATION],
            AttemptStatus::PROCESSING->value            => [AttemptStatus::PROCESSING],
            AttemptStatus::FAILED->value                => [AttemptStatus::FAILED],
            AttemptStatus::SUCCEEDED->value             => [AttemptStatus::PROCESSING, AttemptStatus::SUCCEEDED],
            AttemptStatus::CANCELED->value              => [AttemptStatus::AWAITING_CONFIRMATION, AttemptStatus::CANCELED],
        ];

        if (isset($paths[$target->value])) {
            foreach ($paths[$target->value] as $step) {
                $attempt->transitionTo($step, $step->value);
            }
        }
    }

    // ── PENDING transitions ───────────────────────────────────────────────────

    public function testPendingToAwaitingConfirmationIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::AWAITING_CONFIRMATION));
    }

    public function testPendingToProcessingIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::PROCESSING));
    }

    public function testPendingToFailedIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testPendingToSucceededIsNotAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    // ── AWAITING_CONFIRMATION transitions ────────────────────────────────────

    public function testAwaitingConfirmationToProcessingIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::AWAITING_CONFIRMATION);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::PROCESSING));
    }

    public function testAwaitingConfirmationToFailedIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::AWAITING_CONFIRMATION);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testAwaitingConfirmationToCanceledIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::AWAITING_CONFIRMATION);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::CANCELED));
    }

    public function testAwaitingConfirmationToSucceededIsNotAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::AWAITING_CONFIRMATION);
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    // ── PROCESSING transitions ────────────────────────────────────────────────

    public function testProcessingToSucceededIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PROCESSING);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::SUCCEEDED));
    }

    public function testProcessingToFailedIsAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PROCESSING);
        $this->assertTrue($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    public function testProcessingToCanceledIsNotAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PROCESSING);
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::CANCELED));
    }

    // ── Terminal status transitions ───────────────────────────────────────────

    public function testSucceededToFailedIsNotAllowed(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::SUCCEEDED);
        $this->assertFalse($this->sm->canTransition($attempt, AttemptStatus::FAILED));
    }

    // ── applyTransition ───────────────────────────────────────────────────────

    public function testApplyTransitionCallsTransitionToOnAttempt(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);
        $attempt->releaseEvents();

        $this->sm->applyTransition($attempt, AttemptStatus::PROCESSING, 'processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
        $this->assertSame('processing', $attempt->getProviderStatus());
    }

    public function testApplyTransitionThrowsInvalidTransitionExceptionOnInvalidTransition(): void
    {
        $attempt = $this->makeAttemptInStatus(AttemptStatus::PENDING);

        $this->expectException(InvalidTransitionException::class);
        $this->sm->applyTransition($attempt, AttemptStatus::SUCCEEDED, 'succeeded');
    }
}
