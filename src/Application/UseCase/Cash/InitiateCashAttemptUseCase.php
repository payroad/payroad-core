<?php

namespace Payroad\Application\UseCase\Cash;

use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\PaymentFlow\Cash\CashPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class InitiateCashAttemptUseCase
{
    public function __construct(
        private AttemptInitiationGuard            $guard,
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateCashAttemptCommand $command): CashPaymentAttempt
    {
        $payment = $this->guard->loadPayment($command->paymentId);
        $this->guard->guardNoActiveAttempt($command->paymentId);

        $id      = $this->attempts->nextId();
        $attempt = $this->providers
            ->forCash($command->providerName)
            ->initiateCashAttempt(
                $id,
                $payment->getId(),
                $command->providerName,
                $payment->getAmount(),
                $command->context,
            );

        $payment->markProcessing();

        $this->attempts->save($attempt);
        $this->payments->save($payment);

        $this->dispatcher->dispatch(
            ...$attempt->releaseEvents(),
            ...$payment->releaseEvents(),
        );

        return $attempt;
    }
}
