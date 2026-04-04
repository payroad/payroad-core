<?php

namespace Tests\Application\UseCase\Card;

use DomainException;
use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\Exception\SavedPaymentMethodNotFoundException;
use Payroad\Application\UseCase\Card\InitiateCardAttemptWithSavedMethodCommand;
use Payroad\Application\UseCase\Card\InitiateCardAttemptWithSavedMethodUseCase;
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
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardSavedPaymentMethod;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\TokenizingCardProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Repository\SavedPaymentMethodRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubSavedCardData;
use Tests\Stub\StubSpecificData;

final class InitiateCardAttemptWithSavedMethodUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject            $payments;
    private PaymentAttemptRepositoryInterface&MockObject     $attempts;
    private SavedPaymentMethodRepositoryInterface&MockObject $savedMethods;
    private ProviderRegistryInterface&MockObject             $providers;
    private DomainEventDispatcherInterface&MockObject        $dispatcher;
    private TokenizingCardProviderInterface&MockObject       $cardProvider;
    private InitiateCardAttemptWithSavedMethodUseCase $useCase;

    private CardSavedPaymentMethod $savedMethod;

    protected function setUp(): void
    {
        $this->payments      = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts      = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->savedMethods  = $this->createMock(SavedPaymentMethodRepositoryInterface::class);
        $this->providers     = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher    = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cardProvider  = $this->createMock(TokenizingCardProviderInterface::class);

        $this->savedMethod = CardSavedPaymentMethod::create(
            SavedPaymentMethodId::generate(),
            CustomerId::of('customer-1'),
            'stub',
            'tok_abc123',
            new StubSavedCardData(),
        );
        $this->savedMethod->releaseEvents();

        $this->cardProvider
            ->method('initiateAttemptWithSavedMethod')
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    CardPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubSpecificData())
            );

        $this->providers->method('forCard')->willReturn($this->cardProvider);
        $this->attempts->method('findById')->willReturn(null);

        $this->useCase = new InitiateCardAttemptWithSavedMethodUseCase(
            new AttemptInitiationGuard($this->payments, $this->attempts),
            $this->payments,
            $this->attempts,
            $this->savedMethods,
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
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateCardAttemptWithSavedMethodCommand
    {
        return new InitiateCardAttemptWithSavedMethodCommand(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            $this->savedMethod->getId(),
        );
    }

    public function testExecuteReturnsCardPaymentAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->savedMethods->method('findById')->willReturn($this->savedMethod);
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CardPaymentAttempt::class, $result);
    }

    public function testExecutePassesProviderTokenToProvider(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $command   = new InitiateCardAttemptWithSavedMethodCommand($attemptId, $payment->getId(), 'stub', $this->savedMethod->getId());

        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->savedMethods->method('findById')->willReturn($this->savedMethod);
        $this->payments->method('findById')->willReturn($payment);

        $this->cardProvider
            ->expects($this->once())
            ->method('initiateAttemptWithSavedMethod')
            ->with(
                $this->equalTo($attemptId),
                $this->isInstanceOf(PaymentId::class),
                'stub',
                $this->isInstanceOf(Money::class),
                'tok_abc123',
            )
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    CardPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubSpecificData())
            );

        $this->useCase->execute($command);
    }

    public function testExecuteMarksPaymentAsProcessing(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->savedMethods->method('findById')->willReturn($this->savedMethod);
        $this->payments->method('findById')->willReturn($payment);

        $this->useCase->execute($this->makeCommand($payment));

        $this->assertSame(PaymentStatus::PROCESSING, $payment->getStatus());
    }

    public function testExecuteSavesAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->savedMethods->method('findById')->willReturn($this->savedMethod);
        $this->payments->method('findById')->willReturn($payment);

        $this->attempts
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(CardPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->useCase->execute(new InitiateCardAttemptWithSavedMethodCommand(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', SavedPaymentMethodId::generate()
        ));
    }

    public function testThrowsWhenSavedMethodNotFound(): void
    {
        $payment = $this->makePayment();
        $this->payments->method('findById')->willReturn($payment);
        $this->savedMethods->method('findById')->willReturn(null);

        $this->expectException(SavedPaymentMethodNotFoundException::class);

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenSavedMethodNotUsable(): void
    {
        $payment = $this->makePayment();
        $this->payments->method('findById')->willReturn($payment);

        $expiredMethod = CardSavedPaymentMethod::create(
            SavedPaymentMethodId::generate(),
            CustomerId::of('customer-1'),
            'stub',
            'tok_expired',
            new StubSavedCardData(),
        );
        $expiredMethod->expire();
        $expiredMethod->releaseEvents();

        $this->savedMethods->method('findById')->willReturn($expiredMethod);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new InitiateCardAttemptWithSavedMethodCommand(
            PaymentAttemptId::generate(), $payment->getId(), 'stub', $expiredMethod->getId()
        ));
    }

    public function testThrowsWhenActiveAttemptExists(): void
    {
        $payment = $this->makePayment();
        $active  = CardPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());

        $this->payments->method('findById')->willReturn($payment);
        $this->savedMethods->method('findById')->willReturn($this->savedMethod);
        $this->attempts->method('findByPaymentId')->willReturn([$active]);

        $this->expectException(ActiveAttemptExistsException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testReturnsExistingAttemptOnIdempotentRetry(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $existing  = CardPaymentAttempt::create($attemptId, $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubSpecificData());
        $existing->setProviderReference('pi_existing');

        $this->attempts->method('findById')->willReturn($existing);

        $this->cardProvider->expects($this->never())->method('initiateAttemptWithSavedMethod');

        $command = new InitiateCardAttemptWithSavedMethodCommand($attemptId, $payment->getId(), 'stub', $this->savedMethod->getId());
        $result  = $this->useCase->execute($command);

        $this->assertSame($existing, $result);
    }
}
