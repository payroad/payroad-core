<?php

namespace Tests\Application\UseCase\P2P;

use DateTimeImmutable;
use DomainException;
use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentExpiredException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\P2P\InitiateP2PAttemptCommand;
use Payroad\Application\UseCase\P2P\InitiateP2PAttemptUseCase;
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
use Payroad\Domain\Channel\P2P\P2PPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\P2P\P2PAttemptContext;
use Payroad\Port\Provider\P2P\P2PProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubP2PData;

final class InitiateP2PAttemptUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private P2PProviderInterface&MockObject              $p2pProvider;
    private InitiateP2PAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments    = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts    = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers   = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher  = $this->createMock(DomainEventDispatcherInterface::class);
        $this->p2pProvider = $this->createMock(P2PProviderInterface::class);

        $this->p2pProvider
            ->method('initiateP2PAttempt')
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    P2PPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubP2PData())
            );

        $this->providers->method('forP2P')->willReturn($this->p2pProvider);
        $this->attempts->method('findById')->willReturn(null);

        $this->useCase = new InitiateP2PAttemptUseCase(
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
            Money::ofMinor(20000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata(),
            $expiresAt,
        );
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateP2PAttemptCommand
    {
        return new InitiateP2PAttemptCommand(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            new P2PAttemptContext('John Doe'),
        );
    }

    public function testExecuteReturnsP2PPaymentAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(P2PPaymentAttempt::class, $result);
    }

    public function testExecuteCallsProviderWithContext(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $context   = new P2PAttemptContext('Jane Smith', 'DEUTDEDB');
        $command   = new InitiateP2PAttemptCommand($attemptId, $payment->getId(), 'stub', $context);

        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->p2pProvider
            ->expects($this->once())
            ->method('initiateP2PAttempt')
            ->with(
                $this->equalTo($attemptId),
                $this->isInstanceOf(PaymentId::class),
                'stub',
                $this->isInstanceOf(Money::class),
                $this->equalTo($context),
            )
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    P2PPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubP2PData())
            );

        $this->useCase->execute($command);
    }

    public function testExecuteMarksPaymentAsProcessing(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute($this->makeCommand($payment));

        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testExecuteSavesAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->attempts
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(P2PPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->useCase->execute(new InitiateP2PAttemptCommand(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', new P2PAttemptContext('Unknown')
        ));
    }

    public function testThrowsWhenPaymentExpired(): void
    {
        $payment = $this->makePayment(new DateTimeImmutable('-1 hour'));
        $this->payments->method('findById')->willReturn($payment);

        $this->expectException(PaymentExpiredException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenActiveAttemptExists(): void
    {
        $payment = $this->makePayment();
        $active  = P2PPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubP2PData());

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$active]);

        $this->expectException(ActiveAttemptExistsException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testAllowsRetryAfterTerminalAttempt(): void
    {
        $payment = $this->makePayment();
        $failed  = P2PPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubP2PData());
        $failed->markFailed('failed');

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$failed]);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(P2PPaymentAttempt::class, $result);
    }

    public function testReturnsExistingAttemptOnIdempotentRetry(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $existing  = P2PPaymentAttempt::create($attemptId, $payment->getId(), 'stub', Money::ofMinor(20000, new Currency('USD', 2)), new StubP2PData());

        $this->attempts->method('findById')->willReturn($existing);

        $this->p2pProvider->expects($this->never())->method('initiateP2PAttempt');

        $command = new InitiateP2PAttemptCommand($attemptId, $payment->getId(), 'stub', new P2PAttemptContext('John Doe'));
        $result  = $this->useCase->execute($command);

        $this->assertSame($existing, $result);
    }
}
