<?php

namespace Payroad\Application\UseCase\HandleWebhook;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Port\DomainEventDispatcherInterface;
use Payroad\Port\PaymentAttemptRepositoryInterface;
use Payroad\Port\PaymentRepositoryInterface;
use Payroad\Port\ProviderRegistryInterface;
use Payroad\Port\StateMachineRegistryInterface;

final class HandleWebhookUseCase
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private StateMachineRegistryInterface     $stateMachines,
        private DomainEventDispatcherInterface    $dispatcher
    ) {}

    public function execute(HandleWebhookCommand $command): void
    {
        $provider = $this->providers->getByType($command->providerType);
        $result   = $provider->parseWebhook($command->payload, $command->headers);

        $attempt = $this->attempts->findByProviderReference($command->providerType, $result->providerReference)
            ?? throw new AttemptNotFoundException($result->providerReference);

        // Apply status transition if the provider signals a status change.
        if ($result->statusChanged) {
            $stateMachine = $this->stateMachines->getByMethodType($attempt->getMethodType());
            $stateMachine->applyTransition(
                $attempt,
                $result->newStatus,
                $result->providerStatus,
                $result->reason
            );
        }

        // Update flow-specific data (e.g. confirmation count, txHash) even without a status change.
        if ($result->updatedSpecificData !== null) {
            $attempt->updateSpecificData($result->updatedSpecificData);
        }

        $this->attempts->save($attempt);
        $this->dispatcher->dispatch(...$attempt->releaseEvents());

        // Propagate success to the parent payment.
        if ($result->newStatus === AttemptStatus::SUCCEEDED) {
            $payment = $this->payments->findById($attempt->getPaymentId())
                ?? throw new PaymentNotFoundException($attempt->getPaymentId());

            $payment->markSucceeded($attempt->getId());
            $this->payments->save($payment);
            $this->dispatcher->dispatch(...$payment->releaseEvents());
        }
    }
}
