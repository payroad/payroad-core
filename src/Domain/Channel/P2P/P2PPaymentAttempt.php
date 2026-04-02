<?php

namespace Payroad\Domain\Channel\P2P;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Channel\P2P\Event\AttemptAwaitingTransfer;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class P2PPaymentAttempt extends PaymentAttempt
{
    private P2PAttemptData  $data;
    private P2PStateMachine $machine;

    public function __construct(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        P2PAttemptData     $data,
        AttemptStatus      $status            = AttemptStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new P2PStateMachine();
    }

    /**
     * Asserts that the given attempt belongs to the P2P flow and returns it typed.
     * @throws \DomainException if the attempt belongs to a different flow
     */
    public static function fromAttempt(PaymentAttempt $attempt): self
    {
        if (!$attempt instanceof self) {
            throw new \DomainException(
                "Expected P2P attempt, got {$attempt->getMethodType()->value} attempt \"{$attempt->getId()->value}\"."
            );
        }
        return $attempt;
    }

    public static function create(
        PaymentAttemptId $id,
        PaymentId      $paymentId,
        string         $providerName,
        Money          $amount,
        P2PAttemptData $data
    ): self {
        $attempt = new self($id, $paymentId, $providerName, $amount, $data);

        $attempt->record(new AttemptInitiated(
            $attempt->getId(),
            $paymentId,
            PaymentMethodType::P2P,
            $providerName,
        ));

        return $attempt;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::P2P;
    }

    protected function stateMachine(): AttemptStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): P2PAttemptData
    {
        return $this->data;
    }

    protected function channelSemanticEvent(AttemptStatus $status): ?DomainEvent
    {
        return match ($status) {
            AttemptStatus::AWAITING_CONFIRMATION => new AttemptAwaitingTransfer($this->getId(), $this->getPaymentId()),
            default                              => null,
        };
    }

    public function updateP2PData(P2PAttemptData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(AttemptData $data): void
    {
        if (!$data instanceof P2PAttemptData) {
            throw new \InvalidArgumentException('P2PPaymentAttempt requires P2PAttemptData.');
        }
        $this->updateP2PData($data);
    }
}
