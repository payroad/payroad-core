<?php

namespace Payroad\Application\UseCase\Webhook;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Cash\CashPaymentAttempt;
use Payroad\Domain\Channel\Crypto\CryptoPaymentAttempt;
use Payroad\Domain\Channel\P2P\P2PPaymentAttempt;
use Payroad\Domain\PaymentMethodType;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\WebhookResult;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class HandleWebhookUseCase
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private DomainEventDispatcherInterface    $dispatcher
    ) {}

    public function execute(HandleWebhookCommand $command): void
    {
        $result = $command->result;

        $attempt = $this->attempts->findByProviderReference($command->providerName, $result->providerReference)
            ?? throw new AttemptNotFoundException($result->providerReference);

        // Apply status transition if the provider signals a status change.
        // Skip if the attempt is already terminal — handles duplicate webhook delivery (at-least-once providers).
        $transitionApplied = false;
        if ($result->statusChanged && !$attempt->getStatus()->isTerminal()) {
            $this->applyChannelTransition($attempt, $result);
            $transitionApplied = true;
        }

        // Update flow-specific data (e.g. confirmation count, txHash) even without a status change.
        if ($result->updatedSpecificData !== null) {
            $attempt->updateSpecificData($result->updatedSpecificData);
        }

        // Replace the lookup reference with the real provider transaction ID when provided.
        // Required for two-step providers (e.g. Braintree) where initiation uses a temporary
        // reference that must be replaced with the actual transaction ID for refunds to work.
        if ($result->newProviderReference !== null) {
            $attempt->setProviderReference($result->newProviderReference);
        }

        $this->attempts->save($attempt);
        $this->dispatcher->dispatch(...$attempt->releaseEvents());

        // Propagate terminal attempt status to the parent payment.
        // Only when the transition was actually applied — prevents double-propagation on replayed webhooks.
        if ($transitionApplied && $result->newStatus->isTerminal()) {
            $payment = $this->payments->findById($attempt->getPaymentId())
                ?? throw new PaymentNotFoundException($attempt->getPaymentId());

            if ($result->newStatus->isSuccess()) {
                // Guard against race condition: payment may have been moved to a different
                // terminal status (e.g. FAILED via FailPaymentUseCase) between the attempt
                // succeeding and this webhook being processed. Silently skip — the attempt
                // transition was already applied and persisted above.
                if ($payment->getStatus()->isTerminal()) {
                    return;
                }
                $payment->markSucceeded($attempt->getId());
            } else {
                $payment->markRetryable();
            }

            $this->payments->save($payment);
            $this->dispatcher->dispatch(...$payment->releaseEvents());
        }
    }

    private function applyChannelTransition(\Payroad\Domain\Attempt\PaymentAttempt $attempt, WebhookResult $result): void
    {
        match ($attempt->getMethodType()) {
            PaymentMethodType::CARD   => $this->applyCardTransition(CardPaymentAttempt::fromAttempt($attempt), $result),
            PaymentMethodType::CRYPTO => $this->applyCryptoTransition(CryptoPaymentAttempt::fromAttempt($attempt), $result),
            PaymentMethodType::P2P    => $this->applyP2PTransition(P2PPaymentAttempt::fromAttempt($attempt), $result),
            PaymentMethodType::CASH   => $this->applyCashTransition(CashPaymentAttempt::fromAttempt($attempt), $result),
        };
    }

    private function applyCardTransition(CardPaymentAttempt $attempt, WebhookResult $result): void
    {
        match ($result->newStatus) {
            AttemptStatus::AUTHORIZED            => $attempt->markAuthorized($result->providerStatus),
            AttemptStatus::AWAITING_CONFIRMATION => $attempt->markAwaitingConfirmation($result->providerStatus),
            AttemptStatus::PROCESSING            => $attempt->markProcessing($result->providerStatus),
            AttemptStatus::PARTIALLY_CAPTURED    => $attempt->markPartiallyCaptured($result->providerStatus),
            AttemptStatus::SUCCEEDED             => $attempt->markSucceeded($result->providerStatus),
            AttemptStatus::FAILED                => $attempt->markFailed($result->providerStatus, $result->reason),
            AttemptStatus::CANCELED              => $attempt->markCanceled($result->providerStatus, $result->reason),
            AttemptStatus::EXPIRED               => $attempt->markExpired($result->providerStatus),
            default => throw new \LogicException("Unexpected status for card webhook: {$result->newStatus->value}"),
        };
    }

    private function applyCryptoTransition(CryptoPaymentAttempt $attempt, WebhookResult $result): void
    {
        match ($result->newStatus) {
            AttemptStatus::AWAITING_CONFIRMATION => $attempt->markAwaitingConfirmation($result->providerStatus),
            AttemptStatus::PROCESSING            => $attempt->markProcessing($result->providerStatus),
            AttemptStatus::PARTIALLY_PAID        => $attempt->markPartiallyPaid($result->providerStatus),
            AttemptStatus::SUCCEEDED             => $attempt->markSucceeded($result->providerStatus),
            AttemptStatus::FAILED                => $attempt->markFailed($result->providerStatus, $result->reason),
            AttemptStatus::CANCELED              => $attempt->markCanceled($result->providerStatus, $result->reason),
            AttemptStatus::EXPIRED               => $attempt->markExpired($result->providerStatus),
            default => throw new \LogicException("Unexpected status for crypto webhook: {$result->newStatus->value}"),
        };
    }

    private function applyP2PTransition(P2PPaymentAttempt $attempt, WebhookResult $result): void
    {
        match ($result->newStatus) {
            AttemptStatus::AWAITING_CONFIRMATION => $attempt->markAwaitingConfirmation($result->providerStatus),
            AttemptStatus::PROCESSING            => $attempt->markProcessing($result->providerStatus),
            AttemptStatus::SUCCEEDED             => $attempt->markSucceeded($result->providerStatus),
            AttemptStatus::FAILED                => $attempt->markFailed($result->providerStatus, $result->reason),
            AttemptStatus::CANCELED              => $attempt->markCanceled($result->providerStatus, $result->reason),
            AttemptStatus::EXPIRED               => $attempt->markExpired($result->providerStatus),
            default => throw new \LogicException("Unexpected status for P2P webhook: {$result->newStatus->value}"),
        };
    }

    private function applyCashTransition(CashPaymentAttempt $attempt, WebhookResult $result): void
    {
        match ($result->newStatus) {
            AttemptStatus::AWAITING_CONFIRMATION => $attempt->markAwaitingConfirmation($result->providerStatus),
            AttemptStatus::SUCCEEDED             => $attempt->markSucceeded($result->providerStatus),
            AttemptStatus::FAILED                => $attempt->markFailed($result->providerStatus, $result->reason),
            AttemptStatus::EXPIRED               => $attempt->markExpired($result->providerStatus),
            default => throw new \LogicException("Unexpected status for cash webhook: {$result->newStatus->value}"),
        };
    }
}
