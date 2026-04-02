<?php

namespace Payroad\Domain\Channel\Cash;

use DateTimeImmutable;
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class CashPaymentAttempt extends PaymentAttempt
{
    private CashAttemptData  $data;
    private CashStateMachine $machine;

    public function __construct(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CashAttemptData    $data,
        AttemptStatus      $status            = AttemptStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CashStateMachine();
    }

    /**
     * Asserts that the given attempt belongs to the cash flow and returns it typed.
     * @throws \DomainException if the attempt belongs to a different flow
     */
    public static function fromAttempt(PaymentAttempt $attempt): self
    {
        if (!$attempt instanceof self) {
            throw new \DomainException(
                "Expected cash attempt, got {$attempt->getMethodType()->value} attempt \"{$attempt->getId()->value}\"."
            );
        }
        return $attempt;
    }

    public static function create(
        PaymentAttemptId $id,
        PaymentId       $paymentId,
        string          $providerName,
        Money           $amount,
        CashAttemptData $data
    ): self {
        $attempt = new self($id, $paymentId, $providerName, $amount, $data);

        $attempt->record(new AttemptInitiated(
            $attempt->getId(),
            $paymentId,
            PaymentMethodType::CASH,
            $providerName,
        ));

        return $attempt;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CASH;
    }

    protected function stateMachine(): AttemptStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CashAttemptData
    {
        return $this->data;
    }

    public function updateCashData(CashAttemptData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(AttemptData $data): void
    {
        if (!$data instanceof CashAttemptData) {
            throw new \InvalidArgumentException('CashPaymentAttempt requires CashAttemptData.');
        }
        $this->updateCashData($data);
    }
}
