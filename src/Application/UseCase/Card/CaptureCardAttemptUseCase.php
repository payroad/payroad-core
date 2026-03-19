<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\ProviderRegistryInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class CaptureCardAttemptUseCase
{
    public function __construct(
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentRepositoryInterface        $payments,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(CaptureCardAttemptCommand $command): void
    {
        $attempt = $this->attempts->findById($command->attemptId)
            ?? throw new AttemptNotFoundException($command->attemptId);

        if (!$attempt instanceof CardPaymentAttempt) {
            throw new \DomainException(
                "Attempt \"{$command->attemptId->value}\" is not a card attempt."
            );
        }

        if ($attempt->getStatus() !== AttemptStatus::AUTHORIZED) {
            throw new \DomainException(
                "Cannot capture attempt \"{$command->attemptId->value}\": must be AUTHORIZED (current: {$attempt->getStatus()->value})."
            );
        }

        if ($command->amount !== null && $command->amount->isGreaterThan($attempt->getAmount())) {
            throw new \DomainException(
                "Capture amount exceeds the authorized amount for attempt \"{$command->attemptId->value}\"."
            );
        }

        $providerReference = $attempt->getProviderReference()
            ?? throw new \DomainException(
                "Attempt \"{$command->attemptId->value}\" has no provider reference — cannot capture."
            );

        $result = $this->providers
            ->forCard($attempt->getProviderName())
            ->captureAttempt($providerReference, $command->amount);

        $attempt->applyTransition($result->newStatus, $result->providerStatus, $result->reason);

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
    }
}
