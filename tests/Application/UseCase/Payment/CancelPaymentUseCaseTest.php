<?php

namespace Tests\Application\UseCase\Payment;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\Payment\CancelPaymentCommand;
use Payroad\Application\UseCase\Payment\CancelPaymentUseCase;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelPaymentUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CancelPaymentUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments   = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts   = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->dispatcher = $this->createMock(DomainEventDispatcherInterface::class);

        $this->useCase = new CancelPaymentUseCase($this->payments, $this->attempts, $this->dispatcher);
    }

    private function makePendingPayment(): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata()
        );
        $payment->releaseEvents();
        return $payment;
    }

    public function testExecuteCancelsPayment(): void
    {
        $payment = $this->makePendingPayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));

        $this->assertSame(PaymentStatus::CANCELED, $payment->getStatus());
    }

    public function testExecuteSavesPayment(): void
    {
        $payment = $this->makePendingPayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));
    }

    public function testExecuteDispatchesEvents(): void
    {
        $payment = $this->makePendingPayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->dispatcher->expects($this->once())->method('dispatch');

        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);
        $this->useCase->execute(new CancelPaymentCommand(PaymentId::generate()));
    }

    public function testIsIdempotentForAlreadyCanceledPayment(): void
    {
        $payment = $this->makePendingPayment();
        $payment->cancel();
        $payment->releaseEvents();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->payments->expects($this->never())->method('save');
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));

        $this->assertSame(PaymentStatus::CANCELED, $payment->getStatus());
    }

    public function testIsIdempotentForSucceededPayment(): void
    {
        $payment = $this->makePendingPayment();
        $payment->markProcessing();
        $payment->markSucceeded(PaymentAttemptId::generate());
        $payment->releaseEvents();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->payments->expects($this->never())->method('save');

        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testThrowsWhenPaymentHasNonTerminalAttempt(): void
    {
        $payment = $this->makePendingPayment();
        $payment->markProcessing();
        $payment->releaseEvents();

        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new \Tests\Stub\StubSpecificData()
        );
        $attempt->markAuthorized('authorized');
        $attempt->releaseEvents();

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$attempt]);

        $this->expectException(\DomainException::class);
        $this->useCase->execute(new CancelPaymentCommand($payment->getId()));
    }
}
