<?php

namespace Payroad\Domain\Payment;

use DateTimeImmutable;
use Payroad\Domain\AggregateRootTrait;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Payment\Event\PaymentCanceled;
use Payroad\Domain\Payment\Event\PaymentCreated;
use Payroad\Domain\Payment\Event\PaymentExpired;
use Payroad\Domain\Payment\Event\PaymentProcessingStarted;
use Payroad\Domain\Payment\Event\PaymentRetryAvailable;
use Payroad\Domain\Payment\Event\PaymentFailed;
use Payroad\Domain\Payment\Event\PaymentPartiallyRefunded;
use Payroad\Domain\Payment\Event\PaymentRefunded;
use Payroad\Domain\Payment\Event\PaymentSucceeded;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Refund\RefundId;

/**
 * Thin business-document aggregate.
 * Represents the application's intent to collect a payment from a customer.
 * Does NOT contain attempts — they are a separate aggregate.
 */
final class Payment
{
    use AggregateRootTrait;

    private PaymentId           $id;
    private Money               $amount;
    private CustomerId          $customerId;
    private PaymentStatus       $status;
    private ?PaymentAttemptId   $successfulAttemptId = null;
    private PaymentMetadata     $metadata;
    private DateTimeImmutable   $createdAt;
    private ?DateTimeImmutable  $expiresAt;
    private Money               $refundedAmount;

    private function __construct(
        PaymentId       $id,
        Money           $amount,
        CustomerId      $customerId,
        PaymentMetadata $metadata,
        ?DateTimeImmutable $expiresAt
    ) {
        $this->id             = $id;
        $this->amount         = $amount;
        $this->customerId     = $customerId;
        $this->status         = PaymentStatus::PENDING;
        $this->metadata       = $metadata;
        $this->createdAt      = new DateTimeImmutable();
        $this->expiresAt      = $expiresAt;
        $this->refundedAmount = Money::ofMinor(0, $amount->getCurrency());
    }

    public static function create(
        PaymentId       $id,
        Money           $amount,
        CustomerId      $customerId,
        PaymentMetadata $metadata  = new PaymentMetadata(),
        ?DateTimeImmutable $expiresAt = null
    ): self {
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $payment = new self($id, $amount, $customerId, $metadata, $expiresAt);

        $payment->record(new PaymentCreated(
            $payment->id,
            $payment->amount,
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
        $this->record(new PaymentProcessingStarted($this->id));
    }

    /**
     * Called when an attempt fails and the payment is open for a new attempt.
     * Moves PROCESSING → PENDING so the next InitiateAttempt call is allowed.
     */
    public function markRetryable(): void
    {
        if ($this->status !== PaymentStatus::PROCESSING) {
            return;
        }

        $this->status = PaymentStatus::PENDING;
        $this->record(new PaymentRetryAvailable($this->id));
    }

    public function markSucceeded(PaymentAttemptId $attemptId): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status              = PaymentStatus::SUCCEEDED;
        $this->successfulAttemptId = $attemptId;

        $this->record(new PaymentSucceeded($this->id, $attemptId));
    }

    /**
     * Marks the payment as permanently failed.
     *
     * Called by the application layer when no further attempts are permitted —
     * for example, when a retry-limit policy is exceeded or fraud is detected.
     * Distinct from CANCELED (merchant-initiated) and EXPIRED (TTL-based).
     * Terminal — no new attempts can be initiated after this point.
     */
    public function markFailed(): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status = PaymentStatus::FAILED;
        $this->record(new PaymentFailed($this->id));
    }

    /**
     * Records a successful refund against this payment.
     * Updates the running refundedAmount and transitions status to
     * PARTIALLY_REFUNDED or REFUNDED depending on whether the full amount is returned.
     *
     * Called by HandleRefundWebhookUseCase when a refund reaches SUCCEEDED.
     *
     * @throws \DomainException if the payment is not in a refundable status
     *                          or if the refund amount would exceed the payment amount.
     */
    public function addRefund(RefundId $refundId, Money $amount): void
    {
        if (!$this->status->isRefundable()) {
            throw new \DomainException(
                "Cannot add refund to payment \"{$this->id->value}\" in status \"{$this->status->value}\"."
            );
        }

        $newRefundedAmount = $this->refundedAmount->add($amount);

        if ($newRefundedAmount->isGreaterThan($this->amount)) {
            throw new \DomainException(
                "Refund amount exceeds the payment amount for payment \"{$this->id->value}\"."
            );
        }

        $this->refundedAmount = $newRefundedAmount;

        if ($this->refundedAmount->equals($this->amount)) {
            $this->status = PaymentStatus::REFUNDED;
            $this->record(new PaymentRefunded($this->id, $refundId, $this->refundedAmount));
        } else {
            $this->status = PaymentStatus::PARTIALLY_REFUNDED;
            $this->record(new PaymentPartiallyRefunded($this->id, $refundId, $amount, $this->refundedAmount));
        }
    }

    public function cancel(): void
    {
        if ($this->status->isTerminal()) {
            return;
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

    public function getId(): PaymentId                         { return $this->id; }
    public function getAmount(): Money                          { return $this->amount; }
    public function getCustomerId(): CustomerId                 { return $this->customerId; }
    public function getStatus(): PaymentStatus                  { return $this->status; }
    public function getMetadata(): PaymentMetadata              { return $this->metadata; }
    public function getSuccessfulAttemptId(): ?PaymentAttemptId { return $this->successfulAttemptId; }
    public function getCreatedAt(): DateTimeImmutable           { return $this->createdAt; }
    public function getExpiresAt(): ?DateTimeImmutable          { return $this->expiresAt; }
    public function getRefundedAmount(): Money                  { return $this->refundedAmount; }
    public function getRefundableAmount(): Money                { return $this->amount->subtract($this->refundedAmount); }
}
