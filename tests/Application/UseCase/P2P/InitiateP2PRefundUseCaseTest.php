<?php

namespace Tests\Application\UseCase\P2P;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\Exception\PaymentNotRefundableException;
use Payroad\Application\Exception\RefundExceedsPaymentAmountException;
use Payroad\Application\UseCase\P2P\InitiateP2PRefundCommand;
use Payroad\Application\UseCase\P2P\InitiateP2PRefundUseCase;
use Payroad\Application\UseCase\Shared\RefundInitiationGuard;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Channel\P2P\P2PPaymentAttempt;
use Payroad\Domain\Channel\P2P\P2PRefund;
use Payroad\Domain\Refund\RefundId;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\P2P\P2PProviderInterface;
use Payroad\Port\Provider\P2P\P2PRefundContext;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Repository\RefundRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubP2PData;
use Tests\Stub\StubP2PRefundData;

final class InitiateP2PRefundUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private RefundRepositoryInterface&MockObject         $refunds;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private P2PProviderInterface&MockObject              $p2pProvider;
    private InitiateP2PRefundUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments     = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->refunds      = $this->createMock(RefundRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->p2pProvider  = $this->createMock(P2PProviderInterface::class);

        $this->providers->method('forP2P')->willReturn($this->p2pProvider);
        $this->refunds->method('nextId')->willReturn(RefundId::generate());

        $this->p2pProvider
            ->method('initiateRefund')
            ->willReturnCallback(
                fn(RefundId $id, PaymentId $paymentId, PaymentAttemptId $attemptId, string $providerName, Money $amount) =>
                    P2PRefund::create($id, $paymentId, $attemptId, $providerName, $amount, new StubP2PRefundData())
            );

        $this->useCase = new InitiateP2PRefundUseCase(
            new RefundInitiationGuard($this->payments),
            $this->attempts,
            $this->refunds,
            $this->providers,
            $this->dispatcher,
        );
    }

    private function makeSucceededPayment(): Payment
    {
        $payment   = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('c1'),
            new PaymentMetadata()
        );
        $attemptId = PaymentAttemptId::generate();
        $payment->markProcessing();
        $payment->markSucceeded($attemptId);
        $payment->releaseEvents();
        return $payment;
    }

    private function makeAttempt(Payment $payment): P2PPaymentAttempt
    {
        $attempt = P2PPaymentAttempt::create(
            $payment->getSuccessfulAttemptId(),
            $payment->getId(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubP2PData()
        );
        $attempt->setProviderReference('transfer_abc123');
        $attempt->releaseEvents();
        return $attempt;
    }

    public function testExecuteReturnsP2PRefund(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $result = $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));

        $this->assertInstanceOf(P2PRefund::class, $result);
    }

    public function testExecuteCallsForP2POnRegistry(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $this->providers
            ->expects($this->once())
            ->method('forP2P')
            ->with($attempt->getProviderName());

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));
    }

    public function testExecuteSavesRefund(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $this->refunds
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(P2PRefund::class));

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));
    }

    public function testExecuteDispatchesEvents(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch');

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));
    }

    public function testExecutePassesContextToProvider(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);
        $context = new P2PRefundContext(reason: 'requested_by_customer');

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $this->p2pProvider
            ->expects($this->once())
            ->method('initiateRefund')
            ->with(
                $this->isInstanceOf(RefundId::class),
                $this->isInstanceOf(PaymentId::class),
                $this->isInstanceOf(PaymentAttemptId::class),
                $this->isType('string'),
                $this->isInstanceOf(Money::class),
                $this->isType('string'),
                $this->equalTo($context),
            )
            ->willReturnCallback(
                fn(RefundId $id, PaymentId $paymentId, PaymentAttemptId $attemptId, string $providerName, Money $amount) =>
                    P2PRefund::create($id, $paymentId, $attemptId, $providerName, $amount, new StubP2PRefundData())
            );

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2)),
            $context
        ));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->useCase->execute(new InitiateP2PRefundCommand(
            PaymentId::generate(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));
    }

    public function testThrowsWhenPaymentNotRefundable(): void
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('c1'),
            new PaymentMetadata()
        );
        // PENDING status — not refundable
        $this->payments->method('findById')->willReturn($payment);

        $this->expectException(PaymentNotRefundableException::class);

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(500, new Currency('USD', 2))
        ));
    }

    public function testThrowsWhenRefundExceedsAmount(): void
    {
        $payment = $this->makeSucceededPayment();
        $attempt = $this->makeAttempt($payment);

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(RefundExceedsPaymentAmountException::class);

        $this->useCase->execute(new InitiateP2PRefundCommand(
            $payment->getId(),
            Money::ofMinor(9999, new Currency('USD', 2))
        ));
    }
}
