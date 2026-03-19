<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\SavedPaymentMethodNotFoundException;
use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Repository\SavedPaymentMethodRepositoryInterface;

final class InitiateCardAttemptWithSavedMethodUseCase
{
    public function __construct(
        private AttemptInitiationGuard                $guard,
        private PaymentRepositoryInterface            $payments,
        private PaymentAttemptRepositoryInterface     $attempts,
        private SavedPaymentMethodRepositoryInterface $savedMethods,
        private ProviderRegistryInterface             $providers,
        private DomainEventDispatcherInterface        $dispatcher,
    ) {}

    public function execute(InitiateCardAttemptWithSavedMethodCommand $command): CardPaymentAttempt
    {
        $payment = $this->guard->loadPayment($command->paymentId);
        $this->guard->guardNoActiveAttempt($command->paymentId);

        $savedMethod = $this->savedMethods->findById($command->savedMethodId)
            ?? throw new SavedPaymentMethodNotFoundException($command->savedMethodId);

        if (!$savedMethod->getStatus()->isUsable()) {
            throw new \DomainException(
                "Saved payment method \"{$command->savedMethodId->value}\" is not usable (status: \"{$savedMethod->getStatus()->value}\")."
            );
        }

        $id      = $this->attempts->nextId();
        $attempt = $this->providers
            ->forCard($command->providerName)
            ->initiateAttemptWithSavedMethod(
                $id,
                $payment->getId(),
                $command->providerName,
                $payment->getAmount(),
                $savedMethod->getProviderToken(),
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
