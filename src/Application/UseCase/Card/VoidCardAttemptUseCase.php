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

final class VoidCardAttemptUseCase
{
    public function __construct(
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentRepositoryInterface        $payments,
        private ProviderRegistryInterface         $providers,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    public function execute(VoidCardAttemptCommand $command): void
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
                "Cannot void attempt \"{$command->attemptId->value}\": must be AUTHORIZED (current: {$attempt->getStatus()->value})."
            );
        }

        $providerReference = $attempt->getProviderReference()
            ?? throw new \DomainException(
                "Attempt \"{$command->attemptId->value}\" has no provider reference — cannot void."
            );

        $result = $this->providers
            ->forCard($attempt->getProviderName())
            ->voidAttempt($providerReference);

        $attempt->applyTransition($result->newStatus, $result->providerStatus, $result->reason);

        $this->attempts->save($attempt);

        $events = $attempt->releaseEvents();

        $payment = $this->payments->findById($attempt->getPaymentId())
            ?? throw new PaymentNotFoundException($attempt->getPaymentId());
        $payment->markRetryable();
        $this->payments->save($payment);
        $events = [...$events, ...$payment->releaseEvents()];

        $this->dispatcher->dispatch(...$events);
    }
}
