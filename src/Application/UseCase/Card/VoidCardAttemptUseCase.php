<?php

namespace Payroad\Application\UseCase\Card;

use Payroad\Application\Exception\AttemptNotFoundException;
use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Provider\Card\CapturableCardProviderInterface;
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
        $attempt = CardPaymentAttempt::fromAttempt(
            $this->attempts->findById($command->attemptId) ?? throw new AttemptNotFoundException($command->attemptId)
        );

        $attempt->assertCanBeVoided();

        $provider = $this->providers->forCard($attempt->getProviderName());

        if (!$provider instanceof CapturableCardProviderInterface) {
            throw new \DomainException(
                "Provider \"{$attempt->getProviderName()}\" does not support void."
            );
        }

        $result = $provider->voidAttempt($attempt->getRequiredProviderReference());

        $attempt->markCanceled($result->providerStatus);

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
