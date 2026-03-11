<?php

namespace Tests\Application\UseCase;

use Payroad\Application\UseCase\CreatePayment\CreatePaymentCommand;
use Payroad\Application\UseCase\CreatePayment\CreatePaymentUseCase;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\IdempotencyKey;
use Payroad\Domain\Payment\MerchantId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Port\DomainEventDispatcherInterface;
use Payroad\Port\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreatePaymentUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $payments;
    private DomainEventDispatcherInterface&MockObject $dispatcher;
    private CreatePaymentUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments    = $this->createMock(PaymentRepositoryInterface::class);
        $this->dispatcher  = $this->createMock(DomainEventDispatcherInterface::class);
        $this->useCase     = new CreatePaymentUseCase($this->payments, $this->dispatcher);
    }

    private function makeCommand(?string $idempotencyKey = null): CreatePaymentCommand
    {
        return new CreatePaymentCommand(
            Money::ofMinor(1000, Currency::of('USD')),
            MerchantId::of('merchant-1'),
            CustomerId::of('customer-1'),
            IdempotencyKey::of($idempotencyKey ?? 'idem-key-' . uniqid()),
            new PaymentMetadata()
        );
    }

    public function testExecuteReturnsNewPaymentWhenNoExistingPaymentFound(): void
    {
        $command = $this->makeCommand('new-key');

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn(null);

        $this->payments->expects($this->once())->method('save');

        $payment = $this->useCase->execute($command);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function testExecuteSavesThePaymentToRepository(): void
    {
        $command = $this->makeCommand();

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn(null);

        $this->payments
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Payment::class));

        $this->useCase->execute($command);
    }

    public function testExecuteDispatchesEvents(): void
    {
        $command = $this->makeCommand();

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn(null);

        $this->payments->method('save');

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($command);
    }

    public function testExecuteReturnsExistingPaymentWhenIdempotencyKeyAlreadyExists(): void
    {
        $command  = $this->makeCommand('existing-key');
        $existing = Payment::create(
            Money::ofMinor(500, Currency::of('USD')),
            MerchantId::of('merchant-2'),
            CustomerId::of('customer-2'),
            IdempotencyKey::of('existing-key')
        );
        $existing->releaseEvents();

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn($existing);

        $result = $this->useCase->execute($command);

        $this->assertSame($existing, $result);
    }

    public function testExecuteDoesNotSaveWhenReturningExistingPayment(): void
    {
        $command  = $this->makeCommand('existing-key');
        $existing = Payment::create(
            Money::ofMinor(500, Currency::of('USD')),
            MerchantId::of('merchant-2'),
            CustomerId::of('customer-2'),
            IdempotencyKey::of('existing-key')
        );
        $existing->releaseEvents();

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn($existing);

        $this->payments
            ->expects($this->never())
            ->method('save');

        $this->useCase->execute($command);
    }

    public function testExecuteDoesNotDispatchEventsWhenReturningExistingPayment(): void
    {
        $command  = $this->makeCommand('existing-key');
        $existing = Payment::create(
            Money::ofMinor(500, Currency::of('USD')),
            MerchantId::of('merchant-2'),
            CustomerId::of('customer-2'),
            IdempotencyKey::of('existing-key')
        );
        $existing->releaseEvents();

        $this->payments
            ->method('findByIdempotencyKey')
            ->willReturn($existing);

        $this->dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->useCase->execute($command);
    }
}
