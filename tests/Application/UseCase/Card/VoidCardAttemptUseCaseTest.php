<?php

namespace Tests\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Card\VoidCardAttemptCommand;
use Payroad\Application\UseCase\Card\VoidCardAttemptUseCase;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\CapturableCardProviderInterface;
use Payroad\Port\Provider\Card\VoidResult;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class VoidCardAttemptUseCaseTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private PaymentRepositoryInterface&MockObject        $payments;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CapturableCardProviderInterface&MockObject   $cardProvider;
    private VoidCardAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->payments     = $this->createMock(PaymentRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cardProvider = $this->createMock(CapturableCardProviderInterface::class);

        $this->providers->method('forCard')->willReturn($this->cardProvider);
        $this->cardProvider
            ->method('voidAttempt')
            ->willReturn(new VoidResult(AttemptStatus::CANCELED, 'voided'));

        $this->useCase = new VoidCardAttemptUseCase(
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
            new PaymentMetadata()
        );
        $payment->markProcessing();
        $payment->releaseEvents();
        return $payment;
    }

    private function makeAuthorizedAttempt(PaymentId $paymentId): CardPaymentAttempt
    {
        $attempt = CardPaymentAttempt::create(PaymentAttemptId::generate(), $paymentId, 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
        $attempt->setProviderReference('pi_abc123');
        $attempt->markAuthorized('authorized');
        $attempt->releaseEvents();
        return $attempt;
    }

    public function testVoidCallsProviderWithProviderReference(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);

        $this->cardProvider
            ->expects($this->once())
            ->method('voidAttempt')
            ->with('pi_abc123')
            ->willReturn(new VoidResult(AttemptStatus::CANCELED, 'voided'));

        $this->useCase->execute(new VoidCardAttemptCommand($attempt->getId()));
    }

    public function testAttemptTransitionsToCanceled(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute(new VoidCardAttemptCommand($attempt->getId()));

        $this->assertSame(AttemptStatus::CANCELED, $attempt->getStatus());
    }

    public function testPaymentReturnsToPendingAfterVoid(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute(new VoidCardAttemptCommand($attempt->getId()));

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testThrowsWhenAttemptNotFound(): void
    {
        $this->attempts->method('findById')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);
        $this->useCase->execute(new VoidCardAttemptCommand(PaymentAttemptId::generate()));
    }

    public function testThrowsWhenAttemptNotAuthorized(): void
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData()
        );
        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(\DomainException::class);
        $this->useCase->execute(new VoidCardAttemptCommand($attempt->getId()));
    }
}
