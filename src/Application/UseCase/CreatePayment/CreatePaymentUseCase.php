<?php

namespace Payroad\Application\UseCase\CreatePayment;

use Payroad\Domain\Payment\Payment;
use Payroad\Port\DomainEventDispatcherInterface;
use Payroad\Port\PaymentRepositoryInterface;

final class CreatePaymentUseCase
{
    public function __construct(
        private PaymentRepositoryInterface    $payments,
        private DomainEventDispatcherInterface $dispatcher
    ) {}

    /**
     * Idempotent: if a payment with the same idempotency key already exists,
     * returns it without creating a duplicate.
     */
    public function execute(CreatePaymentCommand $command): Payment
    {
        $existing = $this->payments->findByIdempotencyKey($command->idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        $payment = Payment::create(
            $command->amount,
            $command->merchantId,
            $command->customerId,
            $command->idempotencyKey,
            $command->metadata,
            $command->expiresAt
        );

        $this->payments->save($payment);
        $this->dispatcher->dispatch(...$payment->releaseEvents());

        return $payment;
    }
}
