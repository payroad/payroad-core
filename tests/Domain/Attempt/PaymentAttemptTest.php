<?php

namespace Tests\Domain\Attempt;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Event\Attempt\AttemptFailed;
use Payroad\Domain\Event\Attempt\AttemptInitiated;
use Payroad\Domain\Event\Attempt\AttemptStatusChanged;
use Payroad\Domain\Event\Attempt\AttemptSucceeded;
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

final class PaymentAttemptTest extends TestCase
{
    private function makeAttempt(): PaymentAttempt
    {
        $payment = Payment::create(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of('idem-key-1'),
            new PaymentMetadata()
        );

        return PaymentAttempt::create(
            $payment->getId(),
            PaymentMethodType::CARD,
            'stub',
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

    public function testSetProviderReferenceUpdatesProviderReference(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $this->assertNull($attempt->getProviderReference());
        $attempt->setProviderReference('ref-123');
        $this->assertSame('ref-123', $attempt->getProviderReference());
    }

    public function testUpdateSpecificDataReplacesSpecificData(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $newData = new StubSpecificData();
        $attempt->updateSpecificData($newData);

        $this->assertSame($newData, $attempt->getSpecificData());
    }

    public function testTransitionToUpdatesStatusAndProviderStatus(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
        $this->assertSame('processing', $attempt->getProviderStatus());
    }

    public function testTransitionToRecordsAttemptStatusChangedEventWithCorrectOldAndNewStatus(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
        $events = $attempt->releaseEvents();

        $statusChangedEvents = array_filter($events, fn($e) => $e instanceof AttemptStatusChanged);
        $this->assertCount(1, $statusChangedEvents);

        /** @var AttemptStatusChanged $event */
        $event = array_values($statusChangedEvents)[0];
        $this->assertSame(AttemptStatus::PENDING, $event->oldStatus);
        $this->assertSame(AttemptStatus::PROCESSING, $event->newStatus);
    }

    public function testTransitionToSucceededRecordsAttemptSucceededEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();
        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::SUCCEEDED, 'succeeded');
        $events = $attempt->releaseEvents();

        $succeededEvents = array_filter($events, fn($e) => $e instanceof AttemptSucceeded);
        $this->assertCount(1, $succeededEvents);
    }

    public function testTransitionToFailedRecordsAttemptFailedEventWithReason(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::FAILED, 'failed', 'insufficient_funds');
        $events = $attempt->releaseEvents();

        $failedEvents = array_filter($events, fn($e) => $e instanceof AttemptFailed);
        $this->assertCount(1, $failedEvents);

        /** @var AttemptFailed $event */
        $event = array_values($failedEvents)[0];
        $this->assertSame('insufficient_funds', $event->reason);
    }

    public function testTransitionToCanceledRecordsAttemptFailedEvent(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::CANCELED, 'canceled');
        $events = $attempt->releaseEvents();

        $failedEvents = array_filter($events, fn($e) => $e instanceof AttemptFailed);
        $this->assertCount(1, $failedEvents);
    }

    public function testTransitionToProcessingDoesNotRecordAttemptSucceededOrAttemptFailed(): void
    {
        $attempt = $this->makeAttempt();
        $attempt->releaseEvents();

        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
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
}
