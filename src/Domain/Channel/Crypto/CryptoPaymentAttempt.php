<?php

namespace Payroad\Domain\Channel\Crypto;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Channel\Crypto\Event\AttemptAwaitingPayment;
use Payroad\Domain\DomainEvent;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class CryptoPaymentAttempt extends PaymentAttempt
{
    private CryptoAttemptData  $data;
    private CryptoStateMachine $machine;

    public function __construct(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CryptoAttemptData  $data,
        AttemptStatus      $status            = AttemptStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CryptoStateMachine();
    }

    /**
     * Asserts that the given attempt belongs to the crypto flow and returns it typed.
     * @throws \DomainException if the attempt belongs to a different flow
     */
    public static function fromAttempt(PaymentAttempt $attempt): self
    {
        if (!$attempt instanceof self) {
            throw new \DomainException(
                "Expected crypto attempt, got {$attempt->getMethodType()->value} attempt \"{$attempt->getId()->value}\"."
            );
        }
        return $attempt;
    }

    public static function create(
        PaymentAttemptId  $id,
        PaymentId         $paymentId,
        string            $providerName,
        Money             $amount,
        CryptoAttemptData $data
    ): self {
        $attempt = new self($id, $paymentId, $providerName, $amount, $data);

        $attempt->record(new AttemptInitiated(
            $attempt->getId(),
            $paymentId,
            PaymentMethodType::CRYPTO,
            $providerName,
        ));

        return $attempt;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CRYPTO;
    }

    protected function stateMachine(): AttemptStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CryptoAttemptData
    {
        return $this->data;
    }

    public function markPartiallyPaid(string $providerStatus): void
    {
        $this->applyWebhookTransition(AttemptStatus::PARTIALLY_PAID, $providerStatus);
    }

    protected function channelSemanticEvent(AttemptStatus $status): ?DomainEvent
    {
        return match ($status) {
            AttemptStatus::AWAITING_CONFIRMATION => new AttemptAwaitingPayment($this->getId(), $this->getPaymentId()),
            default                              => null,
        };
    }

    public function updateCryptoData(CryptoAttemptData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(AttemptData $data): void
    {
        if (!$data instanceof CryptoAttemptData) {
            throw new \InvalidArgumentException('CryptoPaymentAttempt requires CryptoAttemptData.');
        }
        $this->updateCryptoData($data);
    }
}
