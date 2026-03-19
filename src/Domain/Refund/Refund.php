<?php

namespace Payroad\Domain\Refund;

use DateTimeImmutable;
use Payroad\Domain\AggregateRootTrait;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Event\RefundFailed;
use Payroad\Domain\Refund\Event\RefundStatusChanged;
use Payroad\Domain\Refund\Event\RefundSucceeded;
use Payroad\Domain\Refund\Exception\InvalidRefundTransitionException;
use Payroad\Port\Provider\RefundData;

/**
 * Abstract base aggregate for all refund types.
 * Use typed subclasses: CardRefund, CryptoRefund, P2PRefund, CashRefund.
 */
abstract class Refund
{
    use AggregateRootTrait;

    private RefundId          $id;
    private PaymentId         $paymentId;
    private PaymentAttemptId  $originalAttemptId;
    private string            $providerName;
    private Money             $amount;
    private ?string           $providerReference = null;
    private RefundStatus      $status;
    private string            $providerStatus;
    private DateTimeImmutable $createdAt;

    protected function __construct(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount
    ) {
        $this->id                = $id;
        $this->paymentId         = $paymentId;
        $this->originalAttemptId = $originalAttemptId;
        $this->providerName      = $providerName;
        $this->amount            = $amount;
        $this->status            = RefundStatus::PENDING;
        $this->providerStatus    = 'pending';
        $this->createdAt         = new DateTimeImmutable();
    }

    abstract public function getMethodType(): PaymentMethodType;

    /** Returns the flow-specific data for this refund. */
    abstract public function getData(): RefundData;

    /**
     * Updates flow-specific refund data (e.g. return tx hash, acquirer reference number).
     * Each subclass validates that the concrete type matches its expected interface.
     */
    abstract public function updateSpecificData(RefundData $data): void;

    abstract protected function stateMachine(): RefundStateMachineInterface;

    /**
     * Validates and applies a status transition via the embedded state machine.
     * Throws InvalidRefundTransitionException if the transition is not allowed.
     */
    public function applyTransition(
        RefundStatus $to,
        string       $providerStatus,
        string       $reason = ''
    ): void {
        if (!$this->stateMachine()->canTransition($this->status, $to)) {
            throw new InvalidRefundTransitionException($this->status, $to);
        }

        $this->doTransition($to, $providerStatus, $reason);
    }

    /** Called by the provider after the external API returns a reference. */
    public function setProviderReference(string $reference): void
    {
        $this->providerReference = $reference;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): RefundId                          { return $this->id; }
    public function getPaymentId(): PaymentId                  { return $this->paymentId; }
    public function getOriginalAttemptId(): PaymentAttemptId   { return $this->originalAttemptId; }
    public function getProviderName(): string                   { return $this->providerName; }
    public function getAmount(): Money                         { return $this->amount; }
    public function getProviderReference(): ?string            { return $this->providerReference; }
    public function getStatus(): RefundStatus                  { return $this->status; }
    public function getProviderStatus(): string                { return $this->providerStatus; }
    public function getCreatedAt(): DateTimeImmutable          { return $this->createdAt; }

    // ── Internal transition ───────────────────────────────────────────────────

    private function doTransition(
        RefundStatus $newStatus,
        string       $newProviderStatus,
        string       $reason = ''
    ): void {
        $oldStatus = $this->status;

        $this->status         = $newStatus;
        $this->providerStatus = $newProviderStatus;

        $this->record(new RefundStatusChanged(
            $this->id,
            $this->paymentId,
            $oldStatus,
            $newStatus,
            $newProviderStatus,
        ));

        if ($newStatus->isSuccess()) {
            $this->record(new RefundSucceeded($this->id, $this->paymentId, $this->amount));
        } elseif ($newStatus->isFailure()) {
            $this->record(new RefundFailed($this->id, $this->paymentId, $reason));
        }
    }
}
