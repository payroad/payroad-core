<?php

namespace Tests\Application\UseCase;

use DateTimeImmutable;
use DomainException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\InitiateAttempt\InitiateAttemptCommand;
use Payroad\Application\UseCase\InitiateAttempt\InitiateAttemptUseCase;
use Payroad\Domain\Attempt\PaymentAttempt;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class InitiateAttemptUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject $providers;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private PaymentProviderInterface&MockObject $provider;
    private InitiateAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments   = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts   = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers  = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher = $this->createMock(DomainEventDispatcherInterface::class);
        $this->provider   = $this->createMock(PaymentProviderInterface::class);

        $this->provider
            ->method('buildInitialSpecificData')
            ->willReturn(new StubSpecificData());

        $this->providers
            ->method('getByType')
            ->willReturn($this->provider);

        $this->useCase = new InitiateAttemptUseCase(
            $this->payments,
            $this->attempts,
            $this->providers,
            $this->dispatcher
        );
    }

    private function makePayment(?DateTimeImmutable $expiresAt = null): Payment
    {
        $payment = Payment::create(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of('idem-key-' . uniqid()),
            new PaymentMetadata(),
            $expiresAt
        );
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateAttemptCommand
    {
        return new InitiateAttemptCommand(
            $payment->getId(),
            PaymentMethodType::CARD,
            'stub'
        );
    }

    public function testExecuteCreatesAndSavesAttempt(): void
    {
        $payment = $this->makePayment();
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);

        $this->attempts
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(PaymentAttempt::class));

        $result = $this->useCase->execute($command);

        $this->assertInstanceOf(PaymentAttempt::class, $result);
    }

    public function testExecuteCallsProviderInitiate(): void
    {
        $payment = $this->makePayment();
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);

        $this->provider
            ->expects($this->once())
            ->method('initiate')
            ->with(
                $this->isInstanceOf(PaymentAttempt::class),
                $this->isInstanceOf(Money::class)
            );

        $this->useCase->execute($command);
    }

    public function testExecuteMarksPaymentAsProcessing(): void
    {
        $payment = $this->makePayment();
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute($command);

        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testExecuteThrowsPaymentNotFoundExceptionWhenPaymentNotFound(): void
    {
        $payment = $this->makePayment();
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);
        $this->useCase->execute($command);
    }

    public function testExecuteThrowsDomainExceptionWhenPaymentIsExpired(): void
    {
        $past    = new DateTimeImmutable('-1 hour');
        $payment = $this->makePayment($past);
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->payments->method('save');

        $this->expectException(DomainException::class);
        $this->useCase->execute($command);
    }

    public function testExecuteThrowsDomainExceptionWhenPaymentStatusIsTerminalSucceeded(): void
    {
        $payment = $this->makePayment();
        // Force payment into SUCCEEDED status
        $payment->markSucceeded(\Payroad\Domain\Attempt\AttemptId::generate());
        $payment->releaseEvents();

        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);

        $this->expectException(DomainException::class);
        $this->useCase->execute($command);
    }

    public function testExecuteDispatchesAttemptAndPaymentEvents(): void
    {
        $payment = $this->makePayment();
        $command = $this->makeCommand($payment);

        $this->payments->method('findById')->willReturn($payment);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($command);
    }
}
