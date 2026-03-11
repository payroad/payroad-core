<?php

namespace Payroad\Domain\Payment;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptId;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Event\Payment\PaymentCanceled;
use Payroad\Domain\Event\Payment\PaymentCreated;
use Payroad\Domain\Event\Payment\PaymentExpired;
use Payroad\Domain\Event\Payment\PaymentFailed;
use Payroad\Domain\Event\Payment\PaymentSucceeded;
use Payroad\Domain\Money\Money;

/**
 * Thin business-document aggregate.
 * Represents the merchant's intent to collect a payment.
 * Does NOT contain attempts — they are a separate aggregate.
 */
final class Payment
{
    private PaymentId       $id;
    private Money           $amount;
    private MerchantId      $merchantId;
    private CustomerId      $customerId;
    private IdempotencyKey  $idempotencyKey;
    private PaymentStatus   $status;
    private ?AttemptId      $successfulAttemptId = null;
    private PaymentMetadata $metadata;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $expiresAt;

    /** Incremented on every save. Used for optimistic locking. */
    private int $version = 0;

    private array $recordedEvents = [];

    private function __construct(
        PaymentId       $id,
        Money           $amount,
        MerchantId      $merchantId,
        CustomerId      $customerId,
        IdempotencyKey  $idempotencyKey,
        PaymentMetadata $metadata,
        ?DateTimeImmutable $expiresAt
    ) {
        $this->id             = $id;
        $this->amount         = $amount;
        $this->merchantId     = $merchantId;
        $this->customerId     = $customerId;
        $this->idempotencyKey = $idempotencyKey;
        $this->status         = PaymentStatus::PENDING;
        $this->metadata       = $metadata;
        $this->createdAt      = new DateTimeImmutable();
        $this->expiresAt      = $expiresAt;
    }

    public static function create(
        Money           $amount,
        MerchantId      $merchantId,
        CustomerId      $customerId,
        IdempotencyKey  $idempotencyKey,
        PaymentMetadata $metadata  = new PaymentMetadata(),
        ?DateTimeImmutable $expiresAt = null
    ): self {
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $payment = new self(
            PaymentId::generate(),
            $amount,
            $merchantId,
            $customerId,
            $idempotencyKey,
            $metadata,
            $expiresAt
        );

        $payment->record(new PaymentCreated(
            $payment->id,
            $payment->amount,
            $payment->merchantId,
            $payment->customerId,
        ));

        return $payment;
    }

    /** Called by the application service when the first attempt is initiated. */
    public function markProcessing(): void
    {
        if ($this->status !== PaymentStatus::PENDING) {
            return;
        }

        $this->status = PaymentStatus::PROCESSING;
    }

    public function markSucceeded(AttemptId $attemptId): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status               = PaymentStatus::SUCCEEDED;
        $this->successfulAttemptId  = $attemptId;

        $this->record(new PaymentSucceeded($this->id, $attemptId));
    }

    public function markFailed(): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status = PaymentStatus::FAILED;
        $this->record(new PaymentFailed($this->id));
    }

    public function cancel(): void
    {
        if ($this->status->isTerminal()) {
            throw new \LogicException(
                "Cannot cancel a payment in terminal status \"{$this->status->value}\"."
            );
        }

        $this->status = PaymentStatus::CANCELED;
        $this->record(new PaymentCanceled($this->id));
    }

    public function expire(): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status = PaymentStatus::EXPIRED;
        $this->record(new PaymentExpired($this->id));
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null
            && new DateTimeImmutable() > $this->expiresAt;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): PaymentId                  { return $this->id; }
    public function getAmount(): Money                   { return $this->amount; }
    public function getMerchantId(): MerchantId          { return $this->merchantId; }
    public function getCustomerId(): CustomerId          { return $this->customerId; }
    public function getIdempotencyKey(): IdempotencyKey  { return $this->idempotencyKey; }
    public function getStatus(): PaymentStatus           { return $this->status; }
    public function getMetadata(): PaymentMetadata       { return $this->metadata; }
    public function getSuccessfulAttemptId(): ?AttemptId { return $this->successfulAttemptId; }
    public function getCreatedAt(): DateTimeImmutable    { return $this->createdAt; }
    public function getExpiresAt(): ?DateTimeImmutable   { return $this->expiresAt; }
    public function getVersion(): int                    { return $this->version; }

    // ── Event recording ──────────────────────────────────────────────────────

    private function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events               = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
