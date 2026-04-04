<?php

namespace Tests\Application\UseCase\Crypto;

use DateTimeImmutable;
use DomainException;
use Payroad\Application\Exception\ActiveAttemptExistsException;
use Payroad\Application\Exception\PaymentExpiredException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Application\UseCase\Crypto\InitiateCryptoAttemptCommand;
use Payroad\Application\UseCase\Crypto\InitiateCryptoAttemptUseCase;
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
use Payroad\Domain\Channel\Crypto\CryptoPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Crypto\CryptoAttemptContext;
use Payroad\Port\Provider\Crypto\CryptoProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Stub\StubCryptoData;
use Tests\Stub\StubSpecificData;

final class InitiateCryptoAttemptUseCaseTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject        $payments;
    private PaymentAttemptRepositoryInterface&MockObject $attempts;
    private ProviderRegistryInterface&MockObject         $providers;
    private DomainEventDispatcherInterface&MockObject    $dispatcher;
    private CryptoProviderInterface&MockObject           $cryptoProvider;
    private InitiateCryptoAttemptUseCase $useCase;

    protected function setUp(): void
    {
        $this->payments       = $this->createMock(PaymentRepositoryInterface::class);
        $this->attempts       = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->providers      = $this->createMock(ProviderRegistryInterface::class);
        $this->dispatcher     = $this->createMock(DomainEventDispatcherInterface::class);
        $this->cryptoProvider = $this->createMock(CryptoProviderInterface::class);

        $this->cryptoProvider
            ->method('initiateCryptoAttempt')
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    CryptoPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubCryptoData())
            );

        $this->providers->method('forCrypto')->willReturn($this->cryptoProvider);
        $this->attempts->method('findById')->willReturn(null);

        $this->useCase = new InitiateCryptoAttemptUseCase(
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
            Money::ofMinor(5000, new Currency('USDT', 6)),
            CustomerId::of('customer-1'),
            new PaymentMetadata(),
            $expiresAt,
        );
        $payment->releaseEvents();
        return $payment;
    }

    private function makeCommand(Payment $payment): InitiateCryptoAttemptCommand
    {
        return new InitiateCryptoAttemptCommand(
            PaymentAttemptId::generate(),
            $payment->getId(),
            'stub',
            new CryptoAttemptContext('trc20'),
        );
    }

    public function testExecuteReturnsCryptoPaymentAttempt(): void
    {
        $payment = $this->makePayment();
        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CryptoPaymentAttempt::class, $result);
    }

    public function testExecuteCallsProviderWithContext(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $context   = new CryptoAttemptContext('erc20', 'memo-123');
        $command   = new InitiateCryptoAttemptCommand($attemptId, $payment->getId(), 'stub', $context);

        $this->attempts->method('findByPaymentId')->willReturn([]);
        $this->payments->method('findById')->willReturn($payment);

        $this->cryptoProvider
            ->expects($this->once())
            ->method('initiateCryptoAttempt')
            ->with(
                $this->equalTo($attemptId),
                $this->isInstanceOf(PaymentId::class),
                'stub',
                $this->isInstanceOf(Money::class),
                $this->equalTo($context),
            )
            ->willReturnCallback(
                fn(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount) =>
                    CryptoPaymentAttempt::create($id, $paymentId, 'stub', $amount, new StubCryptoData())
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
            ->with($this->isInstanceOf(CryptoPaymentAttempt::class));

        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->useCase->execute(new InitiateCryptoAttemptCommand(
            PaymentAttemptId::generate(), PaymentId::generate(), 'stub', new CryptoAttemptContext('erc20')
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
        $active  = CryptoPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCryptoData());

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$active]);

        $this->expectException(ActiveAttemptExistsException::class);
        $this->useCase->execute($this->makeCommand($payment));
    }

    public function testAllowsRetryAfterTerminalAttempt(): void
    {
        $payment = $this->makePayment();
        $failed  = CryptoPaymentAttempt::create(PaymentAttemptId::generate(), $payment->getId(), 'stub', Money::ofMinor(1000, new Currency('USD', 2)), new StubCryptoData());
        $failed->markFailed('failed');

        $this->payments->method('findById')->willReturn($payment);
        $this->attempts->method('findByPaymentId')->willReturn([$failed]);

        $result = $this->useCase->execute($this->makeCommand($payment));

        $this->assertInstanceOf(CryptoPaymentAttempt::class, $result);
    }

    public function testReturnsExistingAttemptOnIdempotentRetry(): void
    {
        $payment   = $this->makePayment();
        $attemptId = PaymentAttemptId::generate();
        $existing  = CryptoPaymentAttempt::create($attemptId, $payment->getId(), 'stub', Money::ofMinor(5000, new Currency('USDT', 6)), new StubCryptoData());

        $this->attempts->method('findById')->willReturn($existing);

        $this->cryptoProvider->expects($this->never())->method('initiateCryptoAttempt');

        $command = new InitiateCryptoAttemptCommand($attemptId, $payment->getId(), 'stub', new CryptoAttemptContext('trc20'));
        $result  = $this->useCase->execute($command);

        $this->assertSame($existing, $result);
    }
}
