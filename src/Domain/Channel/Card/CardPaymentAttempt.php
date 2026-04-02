<?php

namespace Payroad\Domain\Channel\Card;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptAuthorized;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Channel\Card\Event\AttemptRequiresConfirmation;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class CardPaymentAttempt extends PaymentAttempt
{
    private CardAttemptData  $data;
    private CardStateMachine $machine;

    public function __construct(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CardAttemptData    $data,
        AttemptStatus      $status            = AttemptStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CardStateMachine();
    }

    /**
     * Asserts that the given attempt belongs to the card flow and returns it typed.
     * @throws \DomainException if the attempt belongs to a different flow
     */
    public static function fromAttempt(PaymentAttempt $attempt): self
    {
        if (!$attempt instanceof self) {
            throw new \DomainException(
                "Expected card attempt, got {$attempt->getMethodType()->value} attempt \"{$attempt->getId()->value}\"."
            );
        }
        return $attempt;
    }

    public static function create(
        PaymentAttemptId $id,
        PaymentId       $paymentId,
        string          $providerName,
        Money           $amount,
        CardAttemptData $data
    ): self {
        $attempt = new self($id, $paymentId, $providerName, $amount, $data);

        $attempt->record(new AttemptInitiated(
            $attempt->getId(),
            $paymentId,
            PaymentMethodType::CARD,
            $providerName,
        ));

        return $attempt;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CARD;
    }

    protected function stateMachine(): AttemptStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CardAttemptData
    {
        return $this->data;
    }

    /** @throws \DomainException if the attempt is not AUTHORIZED or has no provider reference */
    public function assertCanBeVoided(): void
    {
        if ($this->getStatus() !== AttemptStatus::AUTHORIZED) {
            throw new \DomainException(
                "Cannot void attempt \"{$this->getId()->value}\": must be AUTHORIZED (current: {$this->getStatus()->value})."
            );
        }
        $this->getRequiredProviderReference();
    }

    /** @throws \DomainException if the attempt cannot be captured or the amount exceeds the authorized amount */
    public function assertCanBeCaptured(?Money $captureAmount): void
    {
        $capturable = [AttemptStatus::AUTHORIZED, AttemptStatus::PARTIALLY_CAPTURED];

        if (!in_array($this->getStatus(), $capturable, true)) {
            throw new \DomainException(
                "Cannot capture attempt \"{$this->getId()->value}\": must be AUTHORIZED or PARTIALLY_CAPTURED (current: {$this->getStatus()->value})."
            );
        }
        if ($captureAmount !== null && $captureAmount->isGreaterThan($this->getAmount())) {
            throw new \DomainException(
                "Capture amount exceeds the authorized amount for attempt \"{$this->getId()->value}\"."
            );
        }
        $this->getRequiredProviderReference();
    }

    /** @throws \DomainException if the attempt has not SUCCEEDED or has no provider reference */
    public function assertCanSaveMethod(): void
    {
        if (!$this->getStatus()->isSuccess()) {
            throw new \DomainException(
                "Cannot save payment method from attempt \"{$this->getId()->value}\": attempt must be SUCCEEDED."
            );
        }
        $this->getRequiredProviderReference();
    }

    public function updateCardData(CardAttemptData $data): void
    {
        $this->data = $data;
    }

    public function markAuthorized(string $providerStatus): void
    {
        $this->applyWebhookTransition(AttemptStatus::AUTHORIZED, $providerStatus);
    }

    public function markPartiallyCaptured(string $providerStatus): void
    {
        $this->applyWebhookTransition(AttemptStatus::PARTIALLY_CAPTURED, $providerStatus);
    }

    protected function channelSemanticEvent(AttemptStatus $status): ?DomainEvent
    {
        return match ($status) {
            AttemptStatus::AUTHORIZED            => new AttemptAuthorized($this->getId(), $this->getPaymentId(), $this->getMethodType()),
            AttemptStatus::AWAITING_CONFIRMATION => new AttemptRequiresConfirmation($this->getId(), $this->getPaymentId()),
            default                              => null,
        };
    }

    public function updateSpecificData(AttemptData $data): void
    {
        if (!$data instanceof CardAttemptData) {
            throw new \InvalidArgumentException('CardPaymentAttempt requires CardAttemptData.');
        }
        $this->updateCardData($data);
    }
}
