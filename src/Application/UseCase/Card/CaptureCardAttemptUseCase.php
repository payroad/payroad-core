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
        $attempt = CardPaymentAttempt::fromAttempt(
            $this->attempts->findById($command->attemptId) ?? throw new AttemptNotFoundException($command->attemptId)
        );

        $attempt->assertCanBeCaptured($command->amount);

        $provider = $this->providers->forCard($attempt->getProviderName());

        if (!$provider instanceof CapturableCardProviderInterface) {
            throw new \DomainException(
                "Provider \"{$attempt->getProviderName()}\" does not support explicit capture."
            );
        }

        $result = $provider->captureAttempt($attempt->getRequiredProviderReference(), $command->amount);

        match ($result->newStatus) {
            \Payroad\Domain\Attempt\AttemptStatus::SUCCEEDED          => $attempt->markSucceeded($result->providerStatus),
            \Payroad\Domain\Attempt\AttemptStatus::PROCESSING         => $attempt->markProcessing($result->providerStatus),
            \Payroad\Domain\Attempt\AttemptStatus::PARTIALLY_CAPTURED => $attempt->markPartiallyCaptured($result->providerStatus),
            \Payroad\Domain\Attempt\AttemptStatus::FAILED             => $attempt->markFailed($result->providerStatus),
            default => throw new \LogicException("Unexpected capture result status: {$result->newStatus->value}"),
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
    }
}
