<?php

namespace Payroad\Domain\Attempt;

use DateTimeImmutable;
use Payroad\Domain\AggregateRootTrait;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptCanceled;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\Attempt\Event\AttemptExpired;
use Payroad\Domain\Attempt\Event\AttemptFailed;
use Payroad\Domain\Attempt\Event\AttemptStatusChanged;
use Payroad\Domain\Attempt\Event\AttemptSucceeded;
use Payroad\Domain\Attempt\Exception\InvalidTransitionException;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentMethodType;

/**
 * Abstract base aggregate for all payment attempt types.
 * Use typed subclasses: CardPaymentAttempt, CryptoPaymentAttempt, P2PPaymentAttempt, CashPaymentAttempt.
 */
abstract class PaymentAttempt
{
    use AggregateRootTrait;

    private PaymentAttemptId  $id;
    private PaymentId         $paymentId;
    private string            $providerName;
    private Money             $amount;
    private ?string           $providerReference = null;
    private AttemptStatus     $status;
    private string            $providerStatus;
    private DateTimeImmutable $createdAt;

    protected function __construct(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        AttemptStatus      $status            = AttemptStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        $this->id                = $id;
        $this->paymentId         = $paymentId;
        $this->providerName      = $providerName;
        $this->amount            = $amount;
        $this->status            = $status;
        $this->providerStatus    = $providerStatus;
        $this->providerReference = $providerReference;
        $this->createdAt         = $createdAt ?? new DateTimeImmutable();
    }

    abstract public function getMethodType(): PaymentMethodType;

    /** Returns the flow-specific data for this attempt. */
    abstract public function getData(): AttemptData;

    /**
     * Updates flow-specific data (e.g. confirmation count, txHash).
     * Each subclass validates that the concrete type matches its expected interface.
     */
    abstract public function updateSpecificData(AttemptData $data): void;

    abstract protected function stateMachine(): AttemptStateMachineInterface;

    /**
     * Override in channel subclasses to emit channel-specific semantic events
     * (e.g. AttemptAuthorized for Card, AttemptPartiallyCaptured for Card, AttemptPartiallyPaid for Crypto).
     * Base implementation returns null (no event) for statuses not handled above.
     */
    protected function channelSemanticEvent(AttemptStatus $status): ?DomainEvent
    {
        return null;
    }

    // ── Semantic transition methods ───────────────────────────────────────────

    public function markAuthorized(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::AUTHORIZED, $providerStatus);
    }

    public function markAwaitingConfirmation(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, $providerStatus);
    }

    public function markProcessing(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::PROCESSING, $providerStatus);
    }

    public function markPartiallyCaptured(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::PARTIALLY_CAPTURED, $providerStatus);
    }

    public function markPartiallyPaid(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::PARTIALLY_PAID, $providerStatus);
    }

    public function markSucceeded(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::SUCCEEDED, $providerStatus);
    }

    public function markFailed(string $providerStatus, string $reason = ''): void
    {
        $this->applyTransition(AttemptStatus::FAILED, $providerStatus, $reason);
    }

    public function markCanceled(string $providerStatus, string $reason = ''): void
    {
        $this->applyTransition(AttemptStatus::CANCELED, $providerStatus, $reason);
    }

    public function markExpired(string $providerStatus): void
    {
        $this->applyTransition(AttemptStatus::EXPIRED, $providerStatus);
    }

    /**
     * Validates and applies a status transition via the embedded state machine.
     * Throws InvalidTransitionException if the transition is not allowed.
     */
    private function applyTransition(
        AttemptStatus $to,
        string        $providerStatus,
        string        $reason = ''
    ): void {
        if (!$this->stateMachine()->canTransition($this->status, $to)) {
            throw new InvalidTransitionException($this->status, $to, $this->getMethodType()->value);
        }
        $this->doTransition($to, $providerStatus, $reason);
    }

    /** Called by the provider after the external API returns a reference. */
    public function setProviderReference(string $reference): void
    {
        if (trim($reference) === '') {
            throw new \InvalidArgumentException(
                "Provider reference for attempt \"{$this->id->value}\" cannot be empty."
            );
        }

        $this->providerReference = $reference;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): PaymentAttemptId     { return $this->id; }
    public function getPaymentId(): PaymentId     { return $this->paymentId; }
    public function getProviderName(): string      { return $this->providerName; }
    public function getAmount(): Money             { return $this->amount; }
    public function getProviderReference(): ?string { return $this->providerReference; }

    /**
     * Returns the provider reference or throws if it has not been set yet.
     * Use in domain operations that require a confirmed external reference (capture, void, refund).
     *
     * @throws \DomainException
     */
    public function getRequiredProviderReference(): string
    {
        return $this->providerReference
            ?? throw new \DomainException(
                "Attempt \"{$this->id->value}\" has no provider reference."
            );
    }
    public function getStatus(): AttemptStatus    { return $this->status; }
    public function getProviderStatus(): string   { return $this->providerStatus; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    // ── Internal transition ───────────────────────────────────────────────────

    private function doTransition(
        AttemptStatus $newStatus,
        string        $newProviderStatus,
        string        $reason = ''
    ): void {
        $oldStatus = $this->status;

        $this->status         = $newStatus;
        $this->providerStatus = $newProviderStatus;

        $this->record(new AttemptStatusChanged(
            $this->id,
            $this->paymentId,
            $oldStatus,
            $newStatus,
            $newProviderStatus,
        ));

        $semanticEvent = match ($newStatus) {
            AttemptStatus::SUCCEEDED => new AttemptSucceeded($this->id, $this->paymentId, $this->getMethodType()),
            AttemptStatus::FAILED                => new AttemptFailed($this->id, $this->paymentId, $this->getMethodType(), $reason),
            AttemptStatus::CANCELED              => new AttemptCanceled($this->id, $this->paymentId, $this->getMethodType(), $reason),
            AttemptStatus::EXPIRED               => new AttemptExpired($this->id, $this->paymentId, $this->getMethodType()),
            default                              => $this->channelSemanticEvent($newStatus),
        };

        if ($semanticEvent !== null) {
            $this->record($semanticEvent);
        }
    }
}
