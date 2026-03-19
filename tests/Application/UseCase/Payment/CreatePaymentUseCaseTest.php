<?php

namespace Tests\Application\UseCase\Payment;

use Payroad\Application\UseCase\Payment\CreatePaymentCommand;
use Payroad\Application\UseCase\Payment\CreatePaymentUseCase;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreatePaymentUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $payments;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private CreatePaymentUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments   = $this->createMock(PaymentRepositoryInterface::class);
        $this->dispatcher = $this->createMock(DomainEventDispatcherInterface::class);
        $this->useCase    = new CreatePaymentUseCase($this->payments, $this->dispatcher);

        $this->payments->method('nextId')->willReturn(PaymentId::generate());
    }

    private function makeCommand(): CreatePaymentCommand
    {
        return new CreatePaymentCommand(
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );
    }

    private function makeExistingPayment(): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(500, new Currency('USD', 2)),
            CustomerId::of('customer-2'),
        );
        $payment->releaseEvents();
        return $payment;
    }

    public function testExecuteReturnsNewPaymentWhenNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);
        $this->payments->expects($this->once())->method('save');

        $payment = $this->useCase->execute($this->makeCommand());

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function testExecuteSavesThePaymentToRepository(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->payments
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Payment::class));

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteDispatchesEvents(): void
    {
        $this->payments->method('findById')->willReturn(null);
        $this->payments->method('save');

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteReturnsExistingPaymentWhenIdAlreadyExists(): void
    {
        $existing = $this->makeExistingPayment();
        $this->payments->method('findById')->willReturn($existing);

        $result = $this->useCase->execute($this->makeCommand());

        $this->assertSame($existing, $result);
    }

    public function testExecuteDoesNotSaveWhenReturningExistingPayment(): void
    {
        $this->payments->method('findById')->willReturn($this->makeExistingPayment());
        $this->payments->expects($this->never())->method('save');

        $this->useCase->execute($this->makeCommand());
    }

    public function testExecuteDoesNotDispatchEventsWhenReturningExistingPayment(): void
    {
        $this->payments->method('findById')->willReturn($this->makeExistingPayment());
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->useCase->execute($this->makeCommand());
    }
}
