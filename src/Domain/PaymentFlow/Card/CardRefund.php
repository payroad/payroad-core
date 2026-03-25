<?php

namespace Payroad\Domain\PaymentFlow\Card;

use DateTimeImmutable;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Event\RefundInitiated;
use Payroad\Domain\Refund\Refund;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Provider\RefundData;
use Payroad\Port\Provider\Card\CardRefundData;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStateMachineInterface;

final class CardRefund extends Refund
{
    private CardRefundData        $data;
    private CardRefundStateMachine $machine;

    public function __construct(
        RefundId           $id,
        PaymentId          $paymentId,
        PaymentAttemptId   $originalAttemptId,
        string             $providerName,
        Money              $amount,
        CardRefundData     $data,
        RefundStatus       $status            = RefundStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $originalAttemptId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CardRefundStateMachine();
    }

    public static function create(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount,
        CardRefundData   $data
    ): self {
        $refund = new self($id, $paymentId, $originalAttemptId, $providerName, $amount, $data);

        $refund->record(new RefundInitiated(
            $refund->getId(),
            $paymentId,
            $originalAttemptId,
            PaymentMethodType::CARD,
            $providerName,
            $amount,
        ));

        return $refund;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CARD;
    }

    protected function stateMachine(): RefundStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CardRefundData
    {
        return $this->data;
    }

    public function updateCardData(CardRefundData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(RefundData $data): void
    {
        if (!$data instanceof CardRefundData) {
            throw new \InvalidArgumentException('CardRefund requires CardRefundData.');
        }
        $this->updateCardData($data);
    }
}
