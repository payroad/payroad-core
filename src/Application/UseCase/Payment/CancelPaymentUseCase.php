<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentAttemptRepositoryInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

final class CancelPaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface        $payments,
        private PaymentAttemptRepositoryInterface $attempts,
        private DomainEventDispatcherInterface    $dispatcher,
    ) {}

    /**
     * Cancels a payment that is still in a non-terminal status.
     * Idempotent: if the payment is already canceled or otherwise terminal, returns silently.
     *
     * @throws PaymentNotFoundException
     * @throws \DomainException if the payment has a non-terminal attempt (e.g. AUTHORIZED) that must be voided first
     */
    public function execute(CancelPaymentCommand $command): void
    {
        $payment = $this->payments->findById($command->paymentId)
            ?? throw new PaymentNotFoundException($command->paymentId);

        if ($payment->getStatus()->isTerminal()) {
            return;
        }

        $activeAttempts = array_filter(
            $this->attempts->findByPaymentId($command->paymentId),
            fn(PaymentAttempt $a) => !$a->getStatus()->isTerminal()
        );

        if (count($activeAttempts) > 0) {
            throw new \DomainException(
                "Cannot cancel payment \"{$command->paymentId->value}\": it has non-terminal attempts. Void or wait for them to complete first."
            );
        }

        $payment->cancel();

        $this->payments->save($payment);
        $this->dispatcher->dispatch(...$payment->releaseEvents());
    }
}
