<?php

namespace Tests\Application\UseCase\Card;

use DomainException;
use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\UseCase\Card\SavePaymentMethodCommand;
use Payroad\Application\UseCase\Card\SavePaymentMethodUseCase;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\PaymentFlow\Card\CardSavedPaymentMethod;
use Payroad\Domain\PaymentFlow\P2P\P2PPaymentAttempt;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\TokenizingCardProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\SavedPaymentMethodRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubP2PData;
use Tests\Stub\StubSavedCardData;
use Tests\Stub\StubSpecificData;

final class SavePaymentMethodUseCaseTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&MockObject    $attempts;
    private SavedPaymentMethodRepositoryInterface&MockObject $savedMethods;
    private ProviderRegistryInterface&MockObject             $providers;
    private DomainEventDispatcherInterface&MockObject        $dispatcher;
    private TokenizingCardProviderInterface&MockObject       $cardProvider;
    private SavePaymentMethodUseCase $useCase;

    protected function setUp(): void
    {
        $this->attempts     = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->savedMethods = $this->createMock(SavedPaymentMethodRepositoryInterface::class);
        $this->providers    = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher   = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cardProvider = $this->createMock(TokenizingCardProviderInterface::class);

        $this->providers->method('forCard')->willReturn($this->cardProvider);
        $this->savedMethods->method('nextId')->willReturn(SavedPaymentMethodId::generate());

        $this->useCase = new SavePaymentMethodUseCase(
            $this->attempts,
            $this->savedMethods,
            $this->providers,
            $this->dispatcher,
        );
    }

    private function makeSucceededAttempt(): CardPaymentAttempt
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            PaymentId::generate(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubSpecificData(),
        );
        $attempt->setProviderReference('ref-abc123');
        $attempt->applyTransition(AttemptStatus::PROCESSING, 'processing');
        $attempt->applyTransition(AttemptStatus::SUCCEEDED, 'succeeded');
        $attempt->releaseEvents();
        return $attempt;
    }

    private function makeSavedMethod(CardPaymentAttempt $attempt): CardSavedPaymentMethod
    {
        $method = CardSavedPaymentMethod::create(
            SavedPaymentMethodId::generate(),
            CustomerId::of('customer-1'),
            $attempt->getProviderName(),
            'tok_stub',
            new StubSavedCardData(),
        );
        $method->releaseEvents();
        return $method;
    }

    public function testExecuteReturnsSavedPaymentMethod(): void
    {
        $attempt = $this->makeSucceededAttempt();
        $method  = $this->makeSavedMethod($attempt);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider->method('savePaymentMethod')->willReturn($method);

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $result  = $this->useCase->execute($command);

        $this->assertInstanceOf(CardSavedPaymentMethod::class, $result);
    }

    public function testExecuteCallsForCardOnRegistry(): void
    {
        $attempt = $this->makeSucceededAttempt();
        $method  = $this->makeSavedMethod($attempt);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider->method('savePaymentMethod')->willReturn($method);

        $this->providers
            ->expects($this->once())
            ->method('forCard')
            ->with($attempt->getProviderName());

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }

    public function testExecuteSavesMethodToRepository(): void
    {
        $attempt = $this->makeSucceededAttempt();
        $method  = $this->makeSavedMethod($attempt);

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider->method('savePaymentMethod')->willReturn($method);

        $this->savedMethods
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(CardSavedPaymentMethod::class));

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }

    public function testExecuteDispatchesEvents(): void
    {
        $attempt = $this->makeSucceededAttempt();

        $method = CardSavedPaymentMethod::create(
            SavedPaymentMethodId::generate(),
            CustomerId::of('customer-1'),
            $attempt->getProviderName(),
            'tok_stub',
            new StubSavedCardData(),
        );

        $this->attempts->method('findById')->willReturn($attempt);
        $this->cardProvider->method('savePaymentMethod')->willReturn($method);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch');

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }

    public function testThrowsWhenAttemptNotFound(): void
    {
        $this->attempts->method('findById')->willReturn(null);

        $this->expectException(AttemptNotFoundException::class);

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), PaymentAttemptId::generate());
        $this->useCase->execute($command);
    }

    public function testThrowsWhenAttemptIsNotCardAttempt(): void
    {
        $attempt = P2PPaymentAttempt::create(
            PaymentAttemptId::generate(),
            PaymentId::generate(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubP2PData(),
        );
        $attempt->releaseEvents();

        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(DomainException::class);

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }

    public function testThrowsWhenAttemptNotSucceeded(): void
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            PaymentId::generate(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubSpecificData(),
        );
        $attempt->releaseEvents();

        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(DomainException::class);

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }

    public function testThrowsWhenAttemptHasNoProviderReference(): void
    {
        $attempt = CardPaymentAttempt::create(
            PaymentAttemptId::generate(),
            PaymentId::generate(),
            'stub',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new StubSpecificData(),
        );
        $attempt->applyTransition(AttemptStatus::PROCESSING, 'processing');
        $attempt->applyTransition(AttemptStatus::SUCCEEDED, 'succeeded');
        $attempt->releaseEvents();

        $this->attempts->method('findById')->willReturn($attempt);

        $this->expectException(DomainException::class);

        $command = new SavePaymentMethodCommand(CustomerId::of('customer-1'), $attempt->getId());
        $this->useCase->execute($command);
    }
}
