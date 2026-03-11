<?php

namespace Tests\Application\UseCase;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\HandleWebhook\HandleWebhookCommand;
use Payroad\Application\UseCase\HandleWebhook\HandleWebhookUseCase;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\StateMachine\AttemptStateMachineInterface;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentMethodType;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Port\DomainEventDispatcherInterface;
use Payroad\Port\PaymentAttemptRepositoryInterface;
use Payroad\Port\PaymentProviderInterface;
use Payroad\Port\PaymentRepositoryInterface;
use Payroad\Port\ProviderRegistryInterface;
use Payroad\Port\StateMachineRegistryInterface;
use Payroad\Port\WebhookResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class HandleWebhookUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject $providers;
    private StateMachineRegistryInterface&MockObject $stateMachines;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private PaymentProviderInterface&MockObject $provider;
    private AttemptStateMachineInterface&MockObject $stateMachine;
    private HandleWebhookUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments      = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts      = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers     = $this->createMock(ProviderRegistryInterface::class);
        $this->stateMachines = $this->createMock(StateMachineRegistryInterface::class);
        $this->dispatcher    = $this->createMock(DomainEventDispatcherInterface::class);
        $this->provider      = $this->createMock(PaymentProviderInterface::class);
        $this->stateMachine  = $this->createMock(AttemptStateMachineInterface::class);

        $this->providers->method('getByType')->willReturn($this->provider);
        $this->stateMachines->method('getByMethodType')->willReturn($this->stateMachine);

        $this->useCase = new HandleWebhookUseCase(
            $this->payments,
            $this->attempts,
            $this->providers,
            $this->stateMachines,
            $this->dispatcher
        );
    }

    private function makePayment(): Payment
    {
        $payment = Payment::create(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of('idem-key-' . uniqid()),
            new PaymentMetadata()
        );
        $payment->markProcessing();
        $payment->releaseEvents();
        return $payment;
    }

    private function makeAttempt(Payment $payment): PaymentAttempt
    {
        $attempt = PaymentAttempt::create(
            $payment->getId(),
            PaymentMethodType::CARD,
            'stub',
            new StubSpecificData()
        );
        $attempt->setProviderReference('ref-abc');
        $attempt->releaseEvents();
        return $attempt;
    }

    private function makeCommand(string $providerType = 'stub'): HandleWebhookCommand
    {
        return new HandleWebhookCommand($providerType, ['event' => 'payment.succeeded'], []);
    }

    public function testExecuteCallsParseWebhookAndAppliesTransitionWhenStatusChangedIsTrue(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAttempt($payment);

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: true,
        );

        $this->provider
            ->expects($this->once())
            ->method('parseWebhook')
            ->willReturn($webhookResult);

        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->stateMachine
            ->expects($this->once())
            ->method('applyTransition')
            ->with($attempt, AttemptStatus::PROCESSING, 'processing', '');

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteOnlyUpdatesSpecificDataWhenStatusChangedIsFalse(): void
    {
        $payment     = $this->makePayment();
        $attempt     = $this->makeAttempt($payment);
        $updatedData = new StubSpecificData();

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: false,
            updatedSpecificData: $updatedData,
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->stateMachine
            ->expects($this->never())
            ->method('applyTransition');

        $this->useCase->execute($this->makeCommand());

        $this->assertSame($updatedData, $attempt->getSpecificData());
    }

    public function testExecuteMarksPaymentAsSucceededWhenAttemptSucceeds(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAttempt($payment);

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        // stateMachine.applyTransition will call attempt.transitionTo(SUCCEEDED)
        $this->stateMachine
            ->method('applyTransition')
            ->willReturnCallback(function (PaymentAttempt $a, AttemptStatus $to, string $ps) {
                $a->transitionTo($to, $ps);
            });

        $this->payments->method('findById')->willReturn($payment);
        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute($this->makeCommand());

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testExecuteThrowsAttemptNotFoundExceptionWhenAttemptNotFound(): void
    {
        $webhookResult = new WebhookResult(
            providerReference: 'ref-missing',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);
        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteDoesNotTouchPaymentWhenAttemptDoesNotSucceed(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAttempt($payment);

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: true,
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->payments
            ->expects($this->never())
            ->method('findById');

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteDispatchesAttemptEvents(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAttempt($payment);
        // Give the attempt events to dispatch
        $attempt->transitionTo(AttemptStatus::PROCESSING, 'processing');
        // events are buffered; don't release here – use case will call releaseEvents

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::PROCESSING,
            providerStatus: 'processing',
            statusChanged: false,
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteDispatchesPaymentEventsWhenPaymentSucceeded(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAttempt($payment);

        $webhookResult = new WebhookResult(
            providerReference: 'ref-abc',
            newStatus: AttemptStatus::SUCCEEDED,
            providerStatus: 'succeeded',
            statusChanged: true,
        );

        $this->provider->method('parseWebhook')->willReturn($webhookResult);
        $this->attempts->method('findByProviderReference')->willReturn($attempt);

        $this->stateMachine
            ->method('applyTransition')
            ->willReturnCallback(function (PaymentAttempt $a, AttemptStatus $to, string $ps) {
                $a->transitionTo($to, $ps);
            });

        $this->payments->method('findById')->willReturn($payment);

        // Expect dispatch to be called at least twice: once for attempt events, once for payment events
        $this->dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch');

        $this->useCase->execute($this->makeCommand());
    }
}
