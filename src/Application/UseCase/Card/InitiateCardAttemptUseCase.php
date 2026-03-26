<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class InitiateCardAttemptUseCase
{
    public function __construct(
        private AttemptInitiationGuard            $guard,
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(InitiateCardAttemptCommand $command): CardPaymentAttempt
    {
        $payment = $this->guard->loadPayment($command->paymentId);
        $this->guard->guardNoActiveAttempt($command->paymentId);

        $id      = $this->attempts->nextId();
        $attempt = $this->providers
            ->forCard($command->providerName)
            ->initiateCardAttempt(
                $id,
                $payment->getId(),
                $command->providerName,
                $payment->getAmount(),
                $command->context,
            );

        $payment->markProcessing();

        $this->attempts->save($attempt);

        // Some providers (e.g. mock, Braintree sync) resolve synchronously inside
        // initiateCardAttempt(). If the attempt is already terminal, propagate to the
        // payment immediately — no webhook will arrive to do it later.
        if ($attempt->getStatus()->isTerminal()) {
            if ($attempt->getStatus()->isSuccess()) {
                $payment->markSucceeded($attempt->getId());
            } else {
                $payment->markRetryable();
            }
        }

        $this->payments->save($payment);

        $this->dispatcher->dispatch(
            ...$attempt->releaseEvents(),
            ...$payment->releaseEvents(),
        );

        return $attempt;
    }
}
