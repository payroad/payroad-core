<?php

namespace Payroad\Application\UseCase\Payment;

use Payroad\Application\Exception\PaymentNotFoundException;
use Payroad\Port\Event\DomainEventDispatcherInterface;
use Payroad\Port\Repository\PaymentRepositoryInterface;

/**
 * Marks a payment as permanently FAILED.
 * Called by policy-enforcement logic when no further attempts are permitted —
 * for example, when a retry-limit is exceeded or fraud is detected.
 * Idempotent: if the payment is already terminal, returns silently.
 *
 * @throws PaymentNotFoundException
 */
final class FailPaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface     $payments,
        private DomainEventDispatcherInterface $dispatcher,
    ) {}

    public function execute(FailPaymentCommand $command): void
    {
        $payment = $this->payments->findById($command->paymentId)
            ?? throw new PaymentNotFoundException($command->paymentId);

        if ($payment->getStatus()->isTerminal()) {
            return;
        }

        $payment->markFailed();

        $this->payments->save($payment);
        $this->dispatcher->dispatch(...$payment->releaseEvents());
    }
}
