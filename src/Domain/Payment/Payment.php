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

    public function __construct(
        PaymentId          $id,
        Money              $amount,
        CustomerId         $customerId,
        PaymentMetadata    $metadata            = new PaymentMetadata(),
        ?DateTimeImmutable $expiresAt           = null,
        PaymentStatus      $status              = PaymentStatus::PENDING,
        ?PaymentAttemptId  $successfulAttemptId = null,
        ?DateTimeImmutable $createdAt           = null,
        ?Money             $refundedAmount      = null,
    ) {
        $this->id                  = $id;
        $this->amount              = $amount;
        $this->customerId          = $customerId;
        $this->status              = $status;
        $this->successfulAttemptId = $successfulAttemptId;
        $this->metadata            = $metadata;
        $this->createdAt           = $createdAt ?? new DateTimeImmutable();
        $this->expiresAt           = $expiresAt;
        $this->refundedAmount      = $refundedAmount ?? Money::ofMinor(0, $amount->getCurrency());
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
        if ($this->status === PaymentStatus::SUCCEEDED) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException(
                "Cannot mark payment \"{$this->id->value}\" as succeeded: "
                . "already in terminal status \"{$this->status->value}\"."
            );
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

    /**
     * Asserts that a new attempt can be initiated against this payment.
     *
     * @throws \DomainException if the payment has expired or is in a terminal status
     */
    public function assertCanInitiateAttempt(): void
    {
        if ($this->isExpired()) {
            throw new \DomainException(
                "Payment \"{$this->id->value}\" has expired and cannot accept new attempts."
            );
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException(
                "Cannot initiate attempt on payment \"{$this->id->value}\" in terminal status \"{$this->status->value}\"."
            );
        }
    }

    /**
     * Asserts that a refund of the given amount can be initiated against this payment.
     *
     * @throws \DomainException if the payment is not refundable or the amount exceeds the refundable balance
     */
    public function assertCanInitiateRefund(Money $amount): void
    {
        if (!$this->status->isRefundable()) {
            throw new \DomainException(
                "Payment \"{$this->id->value}\" in status \"{$this->status->value}\" is not refundable."
            );
        }

        if ($amount->isGreaterThan($this->getRefundableAmount())) {
            throw new \DomainException(
                "Refund amount exceeds the refundable balance for payment \"{$this->id->value}\"."
            );
        }
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): PaymentId                         { return $this->id; }
    public function getAmount(): Money                          { return $this->amount; }
    public function getCustomerId(): CustomerId                 { return $this->customerId; }
    public function getStatus(): PaymentStatus                  { return $this->status; }
    public function getMetadata(): PaymentMetadata              { return $this->metadata; }
    public function getSuccessfulAttemptId(): ?PaymentAttemptId { return $this->successfulAttemptId; }

    /**
     * Returns the successful attempt ID or throws if no successful attempt exists.
     * Use in refund flows that require a confirmed successful attempt.
     *
     * @throws \DomainException
     */
    public function getRequiredSuccessfulAttemptId(): PaymentAttemptId
    {
        return $this->successfulAttemptId
            ?? throw new \DomainException(
                "Payment \"{$this->id->value}\" has no successful attempt to refund against."
            );
    }
    public function getCreatedAt(): DateTimeImmutable           { return $this->createdAt; }
    public function getExpiresAt(): ?DateTimeImmutable          { return $this->expiresAt; }
    public function getRefundedAmount(): Money                  { return $this->refundedAmount; }
    public function getRefundableAmount(): Money                { return $this->amount->subtract($this->refundedAmount); }
}
