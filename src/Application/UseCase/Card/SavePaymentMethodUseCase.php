<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethod;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\TokenizingCardProviderInterface;
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
        $attempt = CardPaymentAttempt::fromAttempt(
            $this->attempts->findById($command->attemptId) ?? throw new AttemptNotFoundException($command->attemptId)
        );

        $attempt->assertCanSaveMethod();

        $provider = $this->providers->forCard($attempt->getProviderName());

        if (!$provider instanceof TokenizingCardProviderInterface) {
            throw new \DomainException(
                "Provider \"{$attempt->getProviderName()}\" does not support card tokenization."
            );
        }

        $id     = $this->savedMethods->nextId();
        $method = $provider->savePaymentMethod($id, $command->customerId, $attempt->getRequiredProviderReference());

        $this->savedMethods->save($method);
        $this->dispatcher->dispatch(...$method->releaseEvents());

        return $method;
    }
}
