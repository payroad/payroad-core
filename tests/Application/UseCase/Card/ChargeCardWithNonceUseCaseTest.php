<?php

namespace Tests\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Card\ChargeCardWithNonceCommand;
use Payroad\Application\UseCase\Card\ChargeCardWithNonceUseCase;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\ChargeResult;
use Payroad\Port\Provider\Card\TwoStepCardProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class ChargeCardWithNonceUseCaseTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private PaymentRepositoryInterface&MockObject        $payments;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private TwoStepCardProviderInterface&MockObject      $twoStepProvider;
    private ChargeCardWithNonceUseCase $useCase;

    protected function setUp(): void
    {
        $this->attempts        = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->payments        = $this->createMock(PaymentRepositoryInterface::class);
        $this->providers       = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher      = $this->createMock(DomainEventDispatcherInterface::class);
        $this->twoStepProvider = $this->createMock(TwoStepCardProviderInterface::class);

        $this->providers->method('forCard')->willReturn($this->twoStepProvider);

        $this->useCase = new ChargeCardWithNonceUseCase(
            $this->attempts,
            $this->payments,
            $this->providers,
            $this->dispatcher,
        );
    }

    private function makePayment(): Payment
    {
        $payment = Payment::create(
            PaymentId::generate(),
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata(),
        );
        $payment->markProcessing();
        $payment->releaseEvents();
        return $payment;
    }

    private function makePendingAttempt(Payment $payment): CardPaymentAttempt
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'braintree',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubSpecificData(),
        );
        $attempt->setProviderReference('bt_' . $attempt->getId()->value);
        $attempt->releaseEvents();
        return $attempt;
    }

    private function makeCommand(CardPaymentAttempt $attempt): ChargeCardWithNonceCommand
    {
        return new ChargeCardWithNonceCommand(
            $attempt->getId(),
            'fake-nonce-abc123',
            Money::ofMinor(1000, new Currency('USD', 2)),
        );
    }

    public function testExecuteReturnsSucceededAttemptOnSuccessfulCharge(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->twoStepProvider->method('chargeWithNonce')->willReturn(
            new ChargeResult('txn_real_123', AttemptStatus::SUCCEEDED, 'settled')
        );

        $result = $this->useCase->execute($this->makeCommand($attempt));

        $this->assertSame(AttemptStatus::SUCCEEDED, $result->getStatus());
        $this->assertSame('txn_real_123', $result->getProviderReference());
    }

    public function testExecuteReplacesTemporaryReferenceWithRealTransactionId(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->twoStepProvider->method('chargeWithNonce')->willReturn(
            new ChargeResult('txn_real_456', AttemptStatus::SUCCEEDED, 'settled')
        );

        $this->useCase->execute($this->makeCommand($attempt));

        $this->assertSame('txn_real_456', $attempt->getProviderReference());
    }

    public function testExecuteMarksPaymentSucceededWhenAttemptSucceeds(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->twoStepProvider->method('chargeWithNonce')->willReturn(
            new ChargeResult('txn_real_789', AttemptStatus::SUCCEEDED, 'settled')
        );

        $this->useCase->execute($this->makeCommand($attempt));

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testExecuteLeavesPaymentUnchangedWhenAttemptIsProcessing(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->twoStepProvider->method('chargeWithNonce')->willReturn(
            new ChargeResult('txn_async_001', AttemptStatus::PROCESSING, 'submitted_for_settlement')
        );

        $this->payments->expects($this->never())->method('findById');

        $this->useCase->execute($this->makeCommand($attempt));

        $this->assertSame(AttemptStatus::PROCESSING, $attempt->getStatus());
        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testExecuteThrowsWhenAttemptNotFound(): void
    {
        $this->attempts->method('findById')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);
        $this->useCase->execute(new ChargeCardWithNonceCommand(
            PaymentAttemptId::generate(),
            'nonce',
            Money::ofMinor(1000, new Currency('USD', 2)),
        ));
    }

    public function testExecuteThrowsWhenProviderDoesNotSupportNonce(): void
    {
        $payment  = $this->makePayment();
        $attempt  = $this->makePendingAttempt($payment);
        $nonTwoStep = $this->createMock(\Payroad\Port\Provider\Card\CardProviderInterface::class);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->providers->method('forCard')->willReturn($nonTwoStep);

        $this->expectException(\DomainException::class);
        $this->useCase->execute($this->makeCommand($attempt));
    }

    public function testExecuteSavesAttemptAfterCharge(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makePendingAttempt($payment);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->twoStepProvider->method('chargeWithNonce')->willReturn(
            new ChargeResult('txn_save_test', AttemptStatus::SUCCEEDED, 'settled')
        );

        $this->attempts
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(CardPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($attempt));
    }
}
