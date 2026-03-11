<?php

namespace Tests\Domain\Payment;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Payroad\Domain\Event\Payment\PaymentCanceled;
use Payroad\Domain\Event\Payment\PaymentCreated;
use Payroad\Domain\Event\Payment\PaymentExpired;
use Payroad\Domain\Event\Payment\PaymentSucceeded;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Domain\Attempt\AttemptId;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private Money $amount;
    private MerchantId $merchantId;
    private CustomerId $customerId;
    private IdempotencyKey $idempotencyKey;

    protected function setUp(): void
    {
        $this->amount         = Money::ofMinor(1000, Currency::of('USD'));
        $this->merchantId     = MerchantId::of('merchant-1');
        $this->customerId     = CustomerId::of('customer-1');
        $this->idempotencyKey = IdempotencyKey::of('idem-key-1');
    }

    private function createPayment(?DateTimeImmutable $expiresAt = null): Payment
    {
        return Payment::create(
            $this->amount,
            $this->merchantId,
            $this->customerId,
            $this->idempotencyKey,
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
            Money::ofMinor(0, Currency::of('USD')),
            $this->merchantId,
            $this->customerId,
            $this->idempotencyKey
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
        $attemptId = AttemptId::generate();

        $payment->markSucceeded($attemptId);

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertTrue($payment->getSuccessfulAttemptId()->equals($attemptId));
    }

    public function testMarkSucceededIsIdempotentWhenAlreadyTerminal(): void
    {
        $payment   = $this->createPayment();
        $payment->releaseEvents();
        $attemptId = AttemptId::generate();

        $payment->markSucceeded($attemptId);
        $payment->releaseEvents();

        // Should not throw, should be a no-op
        $payment->markSucceeded(AttemptId::generate());
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testMarkSucceededRecordsPaymentSucceededEvent(): void
    {
        $payment   = $this->createPayment();
        $payment->releaseEvents();
        $attemptId = AttemptId::generate();

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

    public function testCancelThrowsLogicExceptionWhenAlreadyTerminal(): void
    {
        $payment = $this->createPayment();
        $payment->releaseEvents();
        $payment->markSucceeded(AttemptId::generate());
        $payment->releaseEvents();

        $this->expectException(LogicException::class);
        $payment->cancel();
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
}
