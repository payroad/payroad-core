<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethod;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\SavedPaymentMethodRepositoryInterface;

final class SavePaymentMethodUseCase
{
    public function __construct(
        private PaymentAttemptRepositoryInterface    $attempts,
        private SavedPaymentMethodRepositoryInterface $savedMethods,
        private ProviderRegistryInterface             $providers,
        private DomainEventDispatcherInterface        $dispatcher,
    ) {}

    public function execute(SavePaymentMethodCommand $command): SavedPaymentMethod
    {
        $attempt = $this->attempts->findById($command->attemptId)
            ?? throw new AttemptNotFoundException($command->attemptId);

        if (!$attempt instanceof CardPaymentAttempt) {
            throw new \DomainException(
                "Cannot save payment method from attempt \"{$command->attemptId->value}\": attempt is not a card attempt."
            );
        }

        if (!$attempt->getStatus()->isSuccess()) {
            throw new \DomainException(
                "Cannot save payment method from attempt \"{$command->attemptId->value}\": attempt must be SUCCEEDED."
            );
        }

        $providerReference = $attempt->getProviderReference()
            ?? throw new \DomainException(
                "Attempt \"{$command->attemptId->value}\" has no provider reference."
            );

        $id     = $this->savedMethods->nextId();
        $method = $this->providers
            ->forCard($attempt->getProviderName())
            ->savePaymentMethod($id, $command->customerId, $providerReference);

        $this->savedMethods->save($method);
        $this->dispatcher->dispatch(...$method->releaseEvents());

        return $method;
    }
}
