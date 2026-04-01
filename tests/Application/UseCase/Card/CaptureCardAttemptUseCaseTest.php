<?php

namespace Tests\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Card\CaptureCardAttemptCommand;
use Payroad\Application\UseCase\Card\CaptureCardAttemptUseCase;
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
use Payroad\Port\Provider\Card\CaptureResult;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class CaptureCardAttemptUseCaseTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private PaymentRepositoryInterface&MockObject        $payments;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CapturableCardProviderInterface&MockObject   $cardProvider;
    private CaptureCardAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->payments     = $this->createMock(PaymentRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cardProvider = $this->createMock(CapturableCardProviderInterface::class);

        $this->providers->method('forCard')->willReturn($this->cardProvider);

        $this->useCase = new CaptureCardAttemptUseCase(
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

    public function testProviderCalledWithProviderReference(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider
            ->expects($this->once())
            ->method('captureAttempt')
            ->with('pi_abc123', null)
            ->willReturn(new CaptureResult(AttemptStatus::PROCESSING, 'capture_pending'));

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));
    }

    public function testPaymentMarkedSucceededOnSyncCapture(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->cardProvider
            ->method('captureAttempt')
            ->willReturn(new CaptureResult(AttemptStatus::SUCCEEDED, 'captured'));

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->getStatus());
    }

    public function testPaymentNotTouchedOnAsyncCapture(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider
            ->method('captureAttempt')
            ->willReturn(new CaptureResult(AttemptStatus::PROCESSING, 'capture_pending'));

        $this->payments->expects($this->never())->method('findById');

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));
    }

    public function testPartialAmountPassedToProvider(): void
    {
        $payment       = $this->makePayment();
        $attempt       = $this->makeAuthorizedAttempt($payment->getId());
        $partialAmount = Money::ofMinor(500, new Currency('USD', 2));

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider
            ->expects($this->once())
            ->method('captureAttempt')
            ->with('pi_abc123', $partialAmount)
            ->willReturn(new CaptureResult(AttemptStatus::PROCESSING, 'capture_pending'));

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId(), $partialAmount));
    }

    public function testThrowsWhenAttemptNotFound(): void
    {
        $this->attempts->method('findById')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);
        $this->useCase->execute(new CaptureCardAttemptCommand(PaymentAttemptId::generate()));
    }

    public function testPaymentMarkedRetryableWhenCaptureFails(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->payments->method('findById')->willReturn($payment);
        $this->cardProvider
            ->method('captureAttempt')
            ->willReturn(new CaptureResult(AttemptStatus::FAILED, 'capture_failed', 'insufficient_funds'));

        $this->payments->expects($this->once())->method('save')->with($payment);

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));

        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function testPaymentNotTouchedOnNonTerminalCapture(): void
    {
        $payment = $this->makePayment();
        $attempt = $this->makeAuthorizedAttempt($payment->getId());

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider
            ->method('captureAttempt')
            ->willReturn(new CaptureResult(AttemptStatus::PROCESSING, 'capture_pending'));

        $this->payments->expects($this->never())->method('findById');

        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));
    }

    public function testThrowsWhenAttemptNotAuthorized(): void
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData()
        );
        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(\DomainException::class);
        $this->useCase->execute(new CaptureCardAttemptCommand($attempt->getId()));
    }
}
