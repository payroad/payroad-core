<?php

namespace Payroad\Domain\SavedPaymentMethod;

use DateTimeImmutable;
use Payroad\Domain\AggregateRootTrait;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\SavedPaymentMethod\Event\PaymentMethodExpired;
use Payroad\Domain\SavedPaymentMethod\Event\PaymentMethodRemoved;

/**
 * Abstract base aggregate for tokenized payment methods.
 * Use typed subclasses: CardSavedPaymentMethod.
 */
abstract class SavedPaymentMethod
{
    use AggregateRootTrait;

    private SavedPaymentMethodId     $id;
    private CustomerId               $customerId;
    private string                   $providerName;
    private string                   $providerToken;
    private SavedPaymentMethodStatus $status;
    private DateTimeImmutable        $createdAt;

    protected function __construct(
        SavedPaymentMethodId     $id,
        CustomerId               $customerId,
        string                   $providerName,
        string                   $providerToken,
        SavedPaymentMethodStatus $status    = SavedPaymentMethodStatus::ACTIVE,
        ?DateTimeImmutable       $createdAt = null,
    ) {
        $this->id            = $id;
        $this->customerId    = $customerId;
        $this->providerName  = $providerName;
        $this->providerToken = $providerToken;
        $this->status        = $status;
        $this->createdAt     = $createdAt ?? new DateTimeImmutable();
    }

    abstract public function getMethodType(): PaymentMethodType;

    /** Returns the flow-specific data for this saved payment method (e.g. card fingerprint, network token). */
    abstract public function getData(): SavedPaymentMethodData;

    /**
     * Marks the saved method as removed and prevents future use.
     * Idempotent — safe to call if already removed.
     */
    public function remove(): void
    {
        if ($this->status === SavedPaymentMethodStatus::REMOVED) {
            return;
        }

        $this->status = SavedPaymentMethodStatus::REMOVED;
        $this->record(new PaymentMethodRemoved($this->id, $this->customerId));
    }

    /**
     * Called by the provider when the card has passed its expiry date.
     * Idempotent — safe to call if already expired or removed.
     */
    public function expire(): void
    {
        if ($this->status !== SavedPaymentMethodStatus::ACTIVE) {
            return;
        }

        $this->status = SavedPaymentMethodStatus::EXPIRED;
        $this->record(new PaymentMethodExpired($this->id, $this->customerId));
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): SavedPaymentMethodId        { return $this->id; }
    public function getCustomerId(): CustomerId          { return $this->customerId; }
    public function getProviderName(): string            { return $this->providerName; }
    public function getProviderToken(): string           { return $this->providerToken; }
    public function getStatus(): SavedPaymentMethodStatus { return $this->status; }
    public function getCreatedAt(): DateTimeImmutable    { return $this->createdAt; }
}
