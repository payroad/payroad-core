<?php

namespace Tests\Domain\Payment;

use DateTimeImmutable;
use InvalidArgumentException;
use Payroad\Domain\Payment\Event\PaymentCanceled;
use Payroad\Domain\Payment\Event\PaymentCreated;
use Payroad\Domain\Payment\Event\PaymentExpired;
use Payroad\Domain\Payment\Event\PaymentFailed;
use Payroad\Domain\Payment\Event\PaymentProcessingStarted;
use Payroad\Domain\Payment\Event\PaymentRetryAvailable;
use Payroad\Domain\Payment\Event\PaymentSucceeded;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private Money $amount;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->amount     = Money::ofMinor(1000, new Currency('USD', 2));
        $this->customerId = CustomerId::of('customer-1');
    }

    private function createPayment(?DateTimeImmutable $expiresAt = null): Payment
    {
        return Payment::create(
            PaymentId::generate(),
            $this->amount,
            $this->customerId,
            new PaymentMetadata(),
            $expiresAt
        );
    }

    public function testCreateRecordsPaymentCreatedEvent(): void
    {
        $payment = $this->createPayment();
        $events  = $payment->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentCreated::class, $events[0]);
    }

    public function testCreateWithZeroAmountThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Payment::create(
            PaymentId::generate(),
            Money::ofMinor(0, new Currency('USD', 2)),
            $this->customerId,
        );
    }

    public function testInitialStatusIsPending(): void
    {
        $payment = $this->createPayment();
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testMarkProcessingTransitionsFromPendingToProcessing(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markProcessing();
        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testMarkProcessingRecordsPaymentProcessingStartedEvent(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markProcessing();
        $events = $payment->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentProcessingStarted::class, $events[0]);
    }

    public function testMarkProcessingDoesNotRecordEventWhenNotPending(): void
    {
        $payment = $this->createPayment();
        $payment->markProcessing();
        $payment->releaseEvents();

        $payment->markProcessing(); // already PROCESSING — must be no-op
        $this->assertEmpty($payment->releaseEvents());
    }

    public function testMarkProcessingIsIdempotentOnSecondCall(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markProcessing();
        $payment->markProcessing(); // second call should not throw
        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testMarkProcessingDoesNothingWhenNotPending(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();
        $payment->markProcessing();

        // From PROCESSING, calling markProcessing again should do nothing
        $payment->markProcessing();
        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testMarkSucceededSetsStatusSucceededAndSuccessfulAttemptId(): void
    {
        $payment   = $this->createPayment();
        $payment->releaseEvents();
        $attemptId = PaymentAttemptId::generate();

        $payment->markSucceeded($attemptId);

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertTrue($payment->getSuccessfulAttemptId()->equals($attemptId));
    }

    public function testMarkSucceededIsIdempotentWhenAlreadyTerminal(): void
    {
        $payment   = $this->createPayment();
        $payment->releaseEvents();
        $attemptId = PaymentAttemptId::generate();

        $payment->markSucceeded($attemptId);
        $payment->releaseEvents();

        // Should not throw, should be a no-op
        $payment->markSucceeded(PaymentAttemptId::generate());
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testMarkSucceededRecordsPaymentSucceededEvent(): void
    {
        $payment   = $this->createPayment();
        $payment->releaseEvents();
        $attemptId = PaymentAttemptId::generate();

        $payment->markSucceeded($attemptId);
        $events = $payment->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentSucceeded::class, $events[0]);
    }

    public function testMarkFailedSetsStatusFailed(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markFailed();
        $this->assertSame(PaymentStatus::FAILED, $payment->getStatus());
    }

    public function testMarkFailedRecordsPaymentFailedEvent(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markFailed();
        $events = $payment->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentFailed::class, $events[0]);
    }

    public function testMarkFailedIsNoOpWhenAlreadyTerminal(): void
    {
        $payment = $this->createPayment();
        $payment->markSucceeded(PaymentAttemptId::generate());
        $payment->releaseEvents();

        $payment->markFailed();

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertEmpty($payment->releaseEvents());
    }

    public function testCancelSetsStatusCanceledAndRecordsPaymentCanceledEvent(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->cancel();

        $this->assertSame(PaymentStatus::CANCELED, $payment->getStatus());
        $events = $payment->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentCanceled::class, $events[0]);
    }

    public function testCancelIsNoOpWhenAlreadyTerminal(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();
        $payment->markSucceeded(PaymentAttemptId::generate());
        $payment->releaseEvents();

        // cancel() on a terminal payment is a silent no-op — consistent with expire() and markFailed()
        $payment->cancel();

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertEmpty($payment->releaseEvents());
    }

    public function testExpireSetsStatusExpiredAndRecordsPaymentExpiredEvent(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->expire();

        $this->assertSame(PaymentStatus::EXPIRED, $payment->getStatus());
        $events = $payment->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentExpired::class, $events[0]);
    }

    public function testExpireIsIdempotentWhenAlreadyTerminal(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();
        $payment->expire();
        $payment->releaseEvents();

        // Should not throw, should be a no-op
        $payment->expire();
        $this->assertSame(PaymentStatus::EXPIRED, $payment->getStatus());
        $this->assertCount(0, $payment->releaseEvents());
    }

    public function testIsExpiredReturnsTrueWhenExpiresAtIsInThePast(): void
    {
        $past    = new DateTimeImmutable('-1 hour');
        $payment = $this->createPayment($past);
        $this->assertTrue($payment->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiresAtIsInTheFuture(): void
    {
        $future  = new DateTimeImmutable('+1 hour');
        $payment = $this->createPayment($future);
        $this->assertFalse($payment->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiresAtIsNull(): void
    {
        $payment = $this->createPayment(null);
        $this->assertFalse($payment->isExpired());
    }

    public function testReleaseEventsClearsBufferOnSecondCall(): void
    {
        $payment = $this->createPayment();

        $firstRelease  = $payment->releaseEvents();
        $secondRelease = $payment->releaseEvents();

        $this->assertCount(1, $firstRelease);
        $this->assertCount(0, $secondRelease);
    }

    public function testGetVersionReturnsZeroOnNewPayment(): void
    {
        $payment = $this->createPayment();
        $this->assertSame(0, $payment->getVersion());
    }

    public function testIncrementVersionIncrementsOnEachCall(): void
    {
        $payment = $this->createPayment();

        $payment->incrementVersion();
        $this->assertSame(1, $payment->getVersion());

        $payment->incrementVersion();
        $this->assertSame(2, $payment->getVersion());
    }

    public function testMarkRetryableMovesProcessingBackToPending(): void
    {
        $payment = $this->createPayment();
        $payment->markProcessing();

        $payment->markRetryable();

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testMarkRetryableRecordsPaymentRetryAvailableEvent(): void
    {
        $payment = $this->createPayment();
        $payment->markProcessing();
        $payment->releaseEvents();

        $payment->markRetryable();
        $events = $payment->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentRetryAvailable::class, $events[0]);
    }

    public function testMarkRetryableIsNoOpWhenNotProcessing(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();

        $payment->markRetryable(); // already PENDING — must be no-op
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertEmpty($payment->releaseEvents());
    }

    public function testMarkRetryableIsNoOpWhenAlreadyPending(): void
    {
        $payment = $this->createPayment();

        $payment->markRetryable();

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testMarkRetryableIsNoOpWhenTerminal(): void
    {
        $payment = $this->createPayment();
        $payment->markSucceeded(PaymentAttemptId::generate());

        $payment->markRetryable();

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }
}
