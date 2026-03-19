<?php

namespace Payroad\Application\UseCase\Webhook;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;

final class HandleWebhookUseCase
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher
    ) {}

    public function execute(HandleWebhookCommand $command): void
    {
        $provider = $this->providers->getByName($command->providerName);
        $result   = $provider->parseWebhook($command->payload, $command->headers);

        $attempt = $this->attempts->findByProviderReference($command->providerName, $result->providerReference)
            ?? throw new AttemptNotFoundException($result->providerReference);

        // Apply status transition if the provider signals a status change.
        // Skip if the attempt is already terminal — handles duplicate webhook delivery (at-least-once providers).
        $transitionApplied = false;
        if ($result->statusChanged && !$attempt->getStatus()->isTerminal()) {
            $attempt->applyTransition($result->newStatus, $result->providerStatus, $result->reason);
            $transitionApplied = true;
        }

        // Update flow-specific data (e.g. confirmation count, txHash) even without a status change.
        if ($result->updatedSpecificData !== null) {
            $attempt->updateSpecificData($result->updatedSpecificData);
        }

        $this->attempts->save($attempt);
        $this->dispatcher->dispatch(...$attempt->releaseEvents());

        // Propagate terminal attempt status to the parent payment.
        // Only when the transition was actually applied — prevents double-propagation on replayed webhooks.
        if ($transitionApplied && $result->newStatus->isTerminal()) {
            $payment = $this->payments->findById($attempt->getPaymentId())
                ?? throw new PaymentNotFoundException($attempt->getPaymentId());

            if ($result->newStatus->isSuccess()) {
                $payment->markSucceeded($attempt->getId());
            } else {
                $payment->markRetryable();
            }

            $this->payments->save($payment);
            $this->dispatcher->dispatch(...$payment->releaseEvents());
        }
    }
}
