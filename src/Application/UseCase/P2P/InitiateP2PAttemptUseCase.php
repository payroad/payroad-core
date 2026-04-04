<?php

namespace Payroad\Application\UseCase\P2P;

use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\Channel\P2P\P2PPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class InitiateP2PAttemptUseCase
{
    public function __construct(
        private AttemptInitiationGuard            $guard,
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateP2PAttemptCommand $command): P2PPaymentAttempt
    {
        $existing = $this->attempts->findById($command->attemptId);
        if ($existing !== null) {
            return P2PPaymentAttempt::fromAttempt($existing);
        }

        $payment = $this->guard->loadPayment($command->paymentId);
        $this->guard->guardNoActiveAttempt($command->paymentId);

        $attempt = $this->providers
            ->forP2P($command->providerName)
            ->initiateP2PAttempt(
                $command->attemptId,
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
