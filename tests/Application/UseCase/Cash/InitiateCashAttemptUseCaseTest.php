<?php

namespace Tests\Application\UseCase\Cash;

use DateTimeImmutable;
use DomainException;
use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\Cash\InitiateCashAttemptCommand;
use Payroad\Application\UseCase\Cash\InitiateCashAttemptUseCase;
use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Domain\PaymentFlow\Cash\CashPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Cash\CashAttemptContext;
use Payroad\Port\Provider\Cash\CashProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubCashData;

final class InitiateCashAttemptUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CashProviderInterface&MockObject             $cashProvider;
    private InitiateCashAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments     = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cashProvider = $this->createMock(CashProviderInterface::class);

        $this->cashProvider
            ->method('initiateCashAttempt')
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    CashPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubCashData())
            );

        $this->providers->method('forCash')->willReturn($this->cashProvider);
        $this->attempts->method('nextId')->willReturn(PaymentAttemptId::generate());
        $this->attempts->method('findByPaymentId')->willReturn([]);

        $this->useCase = new InitiateCashAttemptUseCase(
            new AttemptInitiationGuard($this->payments, $this->attempts),
            $this->payments,
            $this->attempts,
            $this->providers,
            $this->dispatcher,
        );
    }

    private function makePayment(?DateTimeImmutable $expiresAt = null): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(3000, new Currency('MXN', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata(),
            $expiresAt,
        );
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateCashAttemptCommand
    {
        return new InitiateCashAttemptCommand(
            $payment->getId(),
            'stub',
            new CashAttemptContext('+525512345678'),
        );
    }

    public function testExecuteReturnsCashPaymentAttempt(): void
    {
        $payment = $this->makePayment();
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CashPaymentAttempt::class, $result);
    }

    public function testExecuteCallsProviderWithContext(): void
    {
        $payment = $this->makePayment();
        $context = new CashAttemptContext('+525512345678', 'customer@example.com', 'oxxo');
        $command = new InitiateCashAttemptCommand($payment->getId(), 'stub', $context);

        $this->payments->method('findById')->willReturn($payment);

        $this->cashProvider
            ->expects($this->once())
            ->method('initiateCashAttempt')
            ->with(
                $this->isInstanceOf(PaymentAttemptId::class),
                $this->isInstanceOf(PaymentId::class),
                'stub',
                $this->isInstanceOf(Money::class),
                $this->equalTo($context),
            );

        $this->useCase->execute($command);
    }

    public function testExecuteMarksPaymentAsProcessing(): void
    {
        $payment = $this->makePayment();
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute($this->makeCommand($payment));

        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testExecuteSavesAttempt(): void
    {
        $payment = $this->makePayment();
        $this->payments->method('findById')->willReturn($payment);

        $this->attempts
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(CashPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->useCase->execute(new InitiateCashAttemptCommand(
            PaymentId::generate(), 'stub', new CashAttemptContext('+1234567890')
        ));
    }

    public function testThrowsWhenPaymentExpired(): void
    {
        $payment = $this->makePayment(new DateTimeImmutable('-1 hour'));
        $this->payments->method('findById')->willReturn($payment);

        $this->expectException(DomainException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenActiveAttemptExists(): void
    {
        $payment = $this->makePayment();
        $active  = CashPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCashData());

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$active]);

        $this->expectException(ActiveAttemptExistsException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testAllowsRetryAfterTerminalAttempt(): void
    {
        $payment = $this->makePayment();
        $failed  = CashPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCashData());
        $failed->applyTransition(AttemptStatus::FAILED, 'failed');

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$failed]);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CashPaymentAttempt::class, $result);
    }
}
