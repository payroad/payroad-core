<?php

namespace Tests\Application\UseCase\Card;

use DateTimeImmutable;
use DomainException;
use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentExpiredException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\Card\InitiateCardAttemptCommand;
use Payroad\Application\UseCase\Card\InitiateCardAttemptUseCase;
use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\Payment;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMetadata;
use Payroad\Domain\Payment\PaymentStatus;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\CardAttemptContext;
use Payroad\Port\Provider\Card\CardProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSpecificData;

final class InitiateCardAttemptUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CardProviderInterface&MockObject             $cardProvider;
    private InitiateCardAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments     = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cardProvider = $this->createMock(CardProviderInterface::class);

        $this->cardProvider
            ->method('initiateCardAttempt')
            ->willReturnCallback(function (PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) {
                $attempt = CardPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubSpecificData());
                $attempt->setProviderReference('mock-ref-' . $id->value);
                return $attempt;
            });

        $this->providers->method('forCard')->willReturn($this->cardProvider);

        $this->useCase = new InitiateCardAttemptUseCase(
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
            Money::ofMinor(1000, new Currency('USD', 2)),
            CustomerId::of('customer-1'),
            new PaymentMetadata(),
            $expiresAt
        );
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateCardAttemptCommand
    {
        return new InitiateCardAttemptCommand(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            new CardAttemptContext('1.2.3.4', 'Mozilla/5.0'),
        );
    }

    public function testExecuteReturnsCardPaymentAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CardPaymentAttempt::class, $result);
    }

    public function testExecuteCallsProviderWithContext(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $context   = new CardAttemptContext('1.2.3.4', 'Mozilla/5.0', 'US');
        $command   = new InitiateCardAttemptCommand($attemptId, $payment->getId(), 'stub', $context);

        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->cardProvider
            ->expects($this->once())
            ->method('initiateCardAttempt')
            ->with(
                $this->equalTo($attemptId),
                $this->isInstanceOf(PaymentId::class),
                'stub',
                $this->isInstanceOf(Money::class),
                $this->equalTo($context),
            )
            ->willReturnCallback(function (PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) {
                $attempt = CardPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubSpecificData());
                $attempt->setProviderReference('mock-ref-' . $id->value);
                return $attempt;
            });

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
            ->with($this->isInstanceOf(CardPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testExecuteDispatchesEvents(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DomainEvent::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);
        $this->useCase->execute(new InitiateCardAttemptCommand(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', new CardAttemptContext('1.2.3.4', 'ua')
        ));
    }

    public function testThrowsWhenPaymentExpired(): void
    {
        $payment = $this->makePayment(new DateTimeImmutable('-1 hour'));
        $this->payments->method('findById')->willReturn($payment);
        $this->payments->method('save');

        $this->expectException(PaymentExpiredException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentIsTerminal(): void
    {
        $payment = $this->makePayment();
        $payment->markSucceeded(PaymentAttemptId::generate());
        $payment->releaseEvents();

        $this->payments->method('findById')->willReturn($payment);

        $this->expectException(DomainException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenActiveAttemptExists(): void
    {
        $payment = $this->makePayment();
        $active  = CardPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$active]);

        $this->expectException(ActiveAttemptExistsException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testAllowsRetryAfterTerminalAttempt(): void
    {
        $payment = $this->makePayment();
        $failed  = CardPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
        $failed->markFailed('failed');

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$failed]);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CardPaymentAttempt::class, $result);
    }

    public function testReturnsExistingAttemptOnIdempotentRetry(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $existing  = CardPaymentAttempt::create($attemptId, $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
        $existing->setProviderReference('pi_existing');

        $this->attempts->method('findById')->willReturn($existing);

        $this->cardProvider->expects($this->never())->method('initiateCardAttempt');

        $command = new InitiateCardAttemptCommand($attemptId, $payment->getId(), 'stub', new CardAttemptContext('1.2.3.4', 'ua'));
        $result  = $this->useCase->execute($command);

        $this->assertSame($existing, $result);
    }
}
