<?php

namespace Tests\Domain\Attempt;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\Event\AttemptCanceled;
use Payroad\Domain\Attempt\Event\AttemptFailed;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\Event\AttemptStatusChanged;
use Payroad\Domain\Attempt\Event\AttemptSucceeded;
use Payroad\Domain\Channel\Card\Event\AttemptRequiresConfirmation;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\PaymentMethodType;
use Tests\Stub\StubSpecificData;
use PHPUnit\Framework\TestCase;

final class PaymentAttemptTest extends TestCase
{
    private function makeAttempt(): CardPaymentAttempt
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
            new StubSpecificData()
        );
    }

    public function testCreateSetsInitialStatusPendingAndProviderStatusPending(): void
    {
        $attempt = $this->makeAttempt();

        $this->assertSame(AttemptStatus::PENDING, $attempt->getStatus());
        $this->assertSame('pending', $attempt->getProviderStatus());
    }

    public function testCreateRecordsAttemptInitiatedEvent(): void
    {
        $attempt = $this->makeAttempt();
        $events  = $attempt->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(AttemptInitiated::class, $events[0]);
    }

    public function testCreateSetsCorrectMethodType(): void
    {
        $attempt = $this->makeAttempt();

        $this->assertSame(PaymentMethodType::CARD, $attempt->getMethodType());
    }

    public function testSetProviderReferenceUpdatesProviderReference(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $this->assertNull($attempt->getProviderReference());
        $attempt->setProviderReference('ref-123');
        $this->assertSame('ref-123', $attempt->getProviderReference());
    }

    public function testUpdateDataReplacesTypedData(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $newData = new StubSpecificData();
        $attempt->updateCardData($newData);

        $this->assertSame($newData, $attempt->getData());
    }

    public function testApplyTransitionUpdatesStatusAndProviderStatus(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markProcessing('processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
        $this->assertSame('processing', $attempt->getProviderStatus());
    }

    public function testApplyTransitionRecordsAttemptStatusChangedEventWithCorrectOldAndNewStatus(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markProcessing('processing');
        $events = $attempt->releaseEvents();

        $statusChangedEvents = array_filter($events, fn($e) => $e instanceof AttemptStatusChanged);
        $this->assertCount(1, $statusChangedEvents);

        /** @var AttemptStatusChanged $event */
        $event = array_values($statusChangedEvents)[0];
        $this->assertSame(AttemptStatus::PENDING, $event->oldStatus);
        $this->assertSame(AttemptStatus::PROCESSING, $event->newStatus);
    }

    public function testApplyTransitionToSucceededRecordsAttemptSucceededEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();
        $attempt->markProcessing('processing');
        $attempt->releaseEvents();

        $attempt->markSucceeded('succeeded');
        $events = $attempt->releaseEvents();

        $succeededEvents = array_filter($events, fn($e) => $e instanceof AttemptSucceeded);
        $this->assertCount(1, $succeededEvents);
    }

    public function testApplyTransitionToFailedRecordsAttemptFailedEventWithReason(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markFailed('failed', 'insufficient_funds');
        $events = $attempt->releaseEvents();

        $failedEvents = array_filter($events, fn($e) => $e instanceof AttemptFailed);
        $this->assertCount(1, $failedEvents);

        /** @var AttemptFailed $event */
        $event = array_values($failedEvents)[0];
        $this->assertSame('insufficient_funds', $event->reason);
    }

    public function testApplyTransitionToAwaitingConfirmationRecordsAttemptRequiresConfirmationEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markAwaitingConfirmation('awaiting_confirmation');
        $events = $attempt->releaseEvents();

        $actionEvents = array_filter($events, fn($e) => $e instanceof AttemptRequiresConfirmation);
        $this->assertCount(1, $actionEvents);
    }

    public function testApplyTransitionToCanceledRecordsAttemptCanceledEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        // Card flow: CANCELED is reachable from AWAITING_CONFIRMATION
        $attempt->markAwaitingConfirmation('awaiting_confirmation');
        $attempt->releaseEvents();
        $attempt->markCanceled('canceled');
        $events = $attempt->releaseEvents();

        $canceledEvents = array_filter($events, fn($e) => $e instanceof AttemptCanceled);
        $this->assertCount(1, $canceledEvents);
    }

    public function testApplyTransitionToProcessingDoesNotRecordAttemptSucceededOrAttemptFailed(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->markProcessing('processing');
        $events = $attempt->releaseEvents();

        $succeededEvents = array_filter($events, fn($e) => $e instanceof AttemptSucceeded);
        $failedEvents    = array_filter($events, fn($e) => $e instanceof AttemptFailed);

        $this->assertCount(0, $succeededEvents);
        $this->assertCount(0, $failedEvents);
    }

    public function testReleaseEventsClearsBuffer(): void
    {
        $attempt = $this->makeAttempt();

        $firstRelease  = $attempt->releaseEvents();
        $secondRelease = $attempt->releaseEvents();

        $this->assertCount(1, $firstRelease);
        $this->assertCount(0, $secondRelease);
    }

    public function testGetVersionReturnsZeroInitially(): void
    {
        $attempt = $this->makeAttempt();
        $this->assertSame(0, $attempt->getVersion());
    }

    public function testIncrementVersionIncrementsOnEachCall(): void
    {
        $attempt = $this->makeAttempt();

        $attempt->incrementVersion();
        $this->assertSame(1, $attempt->getVersion());

        $attempt->incrementVersion();
        $this->assertSame(2, $attempt->getVersion());
    }
}
