<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\TwoStepCardProviderInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

/**
 * Completes a two-step card charge by submitting the client nonce server-side.
 *
 * Flow:
 *   1. InitiateCardAttemptUseCase creates the attempt (PENDING) and returns a clientToken.
 *   2. The frontend collects card details via the provider's Drop-in UI and obtains a nonce.
 *   3. This use case submits the nonce to the provider, applies the result, and propagates
 *      the terminal status to the parent payment.
 *
 * Used by: Braintree and any other TwoStepCardProviderInterface implementations.
 */
final class ChargeCardWithNonceUseCase
{
    public function __construct(
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentRepositoryInterface        $payments,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(ChargeCardWithNonceCommand $command): CardPaymentAttempt
    {
        $attempt = CardPaymentAttempt::fromAttempt(
            $this->attempts->findById($command->attemptId)
                ?? throw new AttemptNotFoundException($command->attemptId)
        );

        $provider = $this->providers->forCard($attempt->getProviderName());

        if (!$provider instanceof TwoStepCardProviderInterface) {
            throw new \DomainException(
                "Provider \"{$attempt->getProviderName()}\" does not support nonce-based charging."
            );
        }

        if ($attempt->getStatus() !== \Payroad\Domain\Attempt\AttemptStatus::PENDING) {
            throw new \DomainException(
                "Cannot charge attempt \"{$command->attemptId->value}\": must be PENDING (current: {$attempt->getStatus()->value})."
            );
        }

        if (!$command->amount->equals($attempt->getAmount())) {
            throw new \DomainException(
                "Charge amount {$command->amount->toDecimalString()} {$command->amount->getCurrency()->code} "
                . "does not match attempt amount {$attempt->getAmount()->toDecimalString()} {$attempt->getAmount()->getCurrency()->code}."
            );
        }

        $result = $provider->chargeWithNonce($command->nonce, $command->amount);

        // Replace the temporary placeholder reference with the real transaction ID.
        $attempt->setProviderReference($result->transactionId);

        match ($result->newStatus) {
            AttemptStatus::SUCCEEDED  => $attempt->markSucceeded($result->providerStatus),
            AttemptStatus::PROCESSING => $attempt->markProcessing($result->providerStatus),
            AttemptStatus::FAILED     => $attempt->markFailed($result->providerStatus),
            default => throw new \LogicException(
                "Unexpected status from chargeWithNonce: {$result->newStatus->value}"
            ),
        };

        $this->attempts->save($attempt);

        $events = $attempt->releaseEvents();

        if ($attempt->getStatus()->isTerminal()) {
            $payment = $this->payments->findById($attempt->getPaymentId())
                ?? throw new PaymentNotFoundException($attempt->getPaymentId());

            if ($attempt->getStatus()->isSuccess()) {
                $payment->markSucceeded($attempt->getId());
            } else {
                $payment->markRetryable();
            }

            $this->payments->save($payment);
            $events = [...$events, ...$payment->releaseEvents()];
        }

        $this->dispatcher->dispatch(...$events);

        return $attempt;
    }
}
