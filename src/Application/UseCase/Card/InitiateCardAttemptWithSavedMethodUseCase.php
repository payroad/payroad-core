<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\SavedPaymentMethodNotFoundException;
use Payroad\Application\UseCase\Shared\AttemptInitiationGuard;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\TokenizingCardProviderInterface;
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
        $existing = $this->attempts->findById($command->attemptId);
        if ($existing !== null) {
            return CardPaymentAttempt::fromAttempt($existing);
        }

        $payment = $this->guard->loadPayment($command->paymentId);
        $this->guard->guardNoActiveAttempt($command->paymentId);

        $savedMethod = $this->savedMethods->findById($command->savedMethodId)
            ?? throw new SavedPaymentMethodNotFoundException($command->savedMethodId);

        if (!$savedMethod->getStatus()->isUsable()) {
            throw new \DomainException(
                "Saved payment method \"{$command->savedMethodId->value}\" is not usable (status: \"{$savedMethod->getStatus()->value}\")."
            );
        }

        $provider = $this->providers->forCard($command->providerName);

        if (!$provider instanceof TokenizingCardProviderInterface) {
            throw new \DomainException(
                "Provider \"{$command->providerName}\" does not support saved payment methods."
            );
        }

        $attempt = $provider->initiateAttemptWithSavedMethod(
                $command->attemptId,
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
