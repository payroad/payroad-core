<?php

namespace Payroad\Domain\Attempt;

use DateTimeImmutable;
use Payroad\Domain\Attempt\StateMachine\AttemptStateMachineInterface;
use Payroad\Domain\Event\Attempt\AttemptFailed;
use Payroad\Domain\Event\Attempt\AttemptInitiated;
use Payroad\Domain\Event\Attempt\AttemptStatusChanged;
use Payroad\Domain\Event\Attempt\AttemptSucceeded;
use Payroad\Domain\Event\DomainEvent;
use Payroad\Domain\Flow\PaymentSpecificData;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Payment\PaymentMethodType;

final class PaymentAttempt
{
    private AttemptId           $id;
    private PaymentId           $paymentId;
    private PaymentMethodType   $methodType;
    private string              $providerType;
    private ?string             $providerReference = null;
    private AttemptStatus       $status;
    private string              $providerStatus;
    private PaymentSpecificData $specificData;
    private DateTimeImmutable   $createdAt;

    /** Incremented on every save. Used for optimistic locking. */
    private int $version = 0;

    private array $recordedEvents = [];

    private function __construct(
        PaymentId           $paymentId,
        PaymentMethodType   $methodType,
        string              $providerType,
        PaymentSpecificData $specificData
    ) {
        $this->id             = AttemptId::generate();
        $this->paymentId      = $paymentId;
        $this->methodType     = $methodType;
        $this->providerType   = $providerType;
        $this->status         = AttemptStatus::PENDING;
        $this->providerStatus = 'pending';
        $this->specificData   = $specificData;
        $this->createdAt      = new DateTimeImmutable();
    }

    public static function create(
        PaymentId           $paymentId,
        PaymentMethodType   $methodType,
        string              $providerType,
        PaymentSpecificData $specificData
    ): self {
        $attempt = new self($paymentId, $methodType, $providerType, $specificData);

        $attempt->record(new AttemptInitiated(
            $attempt->id,
            $paymentId,
            $methodType,
            $providerType,
        ));

        return $attempt;
    }

    /** Called by the provider after the external API returns a reference. */
    public function setProviderReference(string $reference): void
    {
        $this->providerReference = $reference;
    }

    /** Called by the provider to persist updated flow data (e.g. txHash, confirmations). */
    public function updateSpecificData(PaymentSpecificData $data): void
    {
        $this->specificData = $data;
    }

    /**
     * Applies a status transition. Intended to be called exclusively by
     * AttemptStateMachineInterface implementations — not from application or
     * infrastructure code directly.
     *
     * @internal
     */
    public function transitionTo(
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

        if ($newStatus->isSuccess()) {
            $this->record(new AttemptSucceeded($this->id, $this->paymentId, $this->methodType));
        } elseif ($newStatus->isFailure()) {
            $this->record(new AttemptFailed($this->id, $this->paymentId, $this->methodType, $reason));
        }
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): AttemptId               { return $this->id; }
    public function getPaymentId(): PaymentId         { return $this->paymentId; }
    public function getMethodType(): PaymentMethodType { return $this->methodType; }
    public function getProviderType(): string          { return $this->providerType; }
    public function getProviderReference(): ?string    { return $this->providerReference; }
    public function getStatus(): AttemptStatus         { return $this->status; }
    public function getProviderStatus(): string        { return $this->providerStatus; }
    public function getSpecificData(): PaymentSpecificData { return $this->specificData; }
    public function getCreatedAt(): DateTimeImmutable  { return $this->createdAt; }
    public function getVersion(): int                  { return $this->version; }

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
