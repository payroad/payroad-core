<?php

namespace Tests\Application\UseCase\Webhook;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Webhook\HandleWebhookCommand;
use Payroad\Application\UseCase\Webhook\HandleWebhookUseCase;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Provider\WebhookResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubCryptoData;
use Tests\Stub\StubSpecificData;

final class HandleWebhookUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private HandleWebhookUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments   = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts   = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->dispatcher = $this->createMock(DomainEventDispatcherInterface::class);

        $this->useCase = new HandleWebhookUseCase(
            $this->payments,
            $this->attempts,
            $this->dispatcher
        );
    }

    private function makePayment(): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );
        $payment->markProcessing();
        $payment->releaseEvents();
        return $payment;
    }

    /** Creates a PENDING card attempt. */
    private function makePendingAttempt(Payment $payment): CardPaymentAttempt
    {
        $attempt = CardPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
        $attempt->setProviderReference('ref-abc');
        $attempt->releaseEvents();
        return $attempt;
    }

    /** Creates a card attempt already in PROCESSING (allows SUCCEEDED webhook). */
    private function makeProcessingAttempt(Payment $payment): CardPaymentAttempt
    {
        $attempt = $this->makePendingAttempt($payment);
        $attempt->markProcessing('processing');
        $attempt->releaseEvents();
        return $attempt;
    }

    private function makeCommand(WebhookResult $result, string $providerName = 'stub'): HandleWebhookCommand
    {
        return new HandleWebhookCommand($providerName, $result);
    }

    public function testExecuteAppliesTransitionWhenStatusChangedIsTrue(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
    }

    public function testExecuteOnlyUpdatesSpecificDataWhenStatusChangedIsFalse(): void
    {
        $payment     = $this->makePayment();
        $attempt     = $this->makePendingAttempt($payment);
        $updatedData = new StubSpecificData();

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: false,
            updatedSpecificData: $updatedData,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(AttemptStatus::PENDING, $attempt->getStatus());
        $this->assertSame($updatedData, $attempt->getData());
    }

    public function testExecuteMarksPaymentAsSucceededWhenAttemptSucceeds(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testExecuteThrowsAttemptNotFoundExceptionWhenAttemptNotFound(): void
    {
        $result = new WebhookResult(
            providerReference: 'ref-missing',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
        );

        $this->attempts->method('findByProviderReference')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);
        $this->useCase->execute($this->makeCommand($result));
    }

    public function testExecuteDispatchesAttemptEvents(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);
        $attempt->markProcessing('processing');

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: false,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($this->makeCommand($result));
    }

    public function testExecuteIsIdempotentWhenWebhookDeliveredTwice(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);

        // First delivery: apply SUCCEEDED transition.
        $attempt->markSucceeded('succeeded');
        $attempt->releaseEvents();

        // Second delivery: attempt is already terminal — must not throw.
        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        // Should complete without throwing InvalidTransitionException.
        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(AttemptStatus::SUCCEEDED, $attempt->getStatus());
    }

    public function testExecuteDispatchesPaymentEventsWhenPaymentSucceeded(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);

        $this->dispatcher->expects($this->exactly(2))->method('dispatch');

        $this->useCase->execute($this->makeCommand($result));
    }

    public function testExecuteMarksPaymentAsRetryableWhenAttemptFails(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::FAILED,
            providerStatus: 'failed',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    /**
     * @dataProvider failureStatusProvider
     */
    public function testExecuteMarksPaymentAsRetryableForAllFailureStatuses(AttemptStatus $status): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: $status,
            providerStatus: $status->value,
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public static function failureStatusProvider(): array
    {
        return [
            'failed'  => [AttemptStatus::FAILED],
            'expired' => [AttemptStatus::EXPIRED],
        ];
    }

    public function testExecuteDoesNotPropagateToPaymentWhenAttemptAlreadyTerminal(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeProcessingAttempt($payment);
        $attempt->markSucceeded('succeeded');
        $attempt->releaseEvents();

        // Duplicate webhook: attempt already SUCCEEDED — transition must be skipped.
        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        // Payment must not be loaded or saved — transition was not applied.
        $this->payments->expects($this->never())->method('findById');
        $this->payments->expects($this->never())->method('save');

        $this->useCase->execute($this->makeCommand($result));
    }

    public function testExecuteDoesNotTouchPaymentWhenAttemptTransitionsToNonTerminalStatus(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $result = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->payments->expects($this->never())->method('findById');

        $this->useCase->execute($this->makeCommand($result));
    }

    public function testExecuteHandlesPartiallyPaidWebhook(): void
    {
        $payment = $this->makePayment();

        // PARTIALLY_PAID is only valid for the crypto flow.
        $cryptoAttempt = CryptoPaymentAttempt::create(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'nowpayments',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubCryptoData(),
        );
        $cryptoAttempt->setProviderReference('ref-crypto-123');
        $cryptoAttempt->releaseEvents();

        $result = new WebhookResult(
            providerReference: 'ref-crypto-123',
            newStatus: AttemptStatus::PARTIALLY_PAID,
            providerStatus: 'partially_paid',
            statusChanged: true,
        );

        $this->attempts->method('findByProviderReference')->willReturn($cryptoAttempt);

        // PARTIALLY_PAID is not terminal — payment must not be touched.
        $this->payments->expects($this->never())->method('findById');

        $this->useCase->execute($this->makeCommand($result));

        $this->assertSame(AttemptStatus::PARTIALLY_PAID, $cryptoAttempt->getStatus());
    }
}
