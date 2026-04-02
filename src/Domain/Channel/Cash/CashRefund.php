<?php

namespace Payroad\Domain\Channel\Cash;

use DateTimeImmutable;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Event\RefundInitiated;
use Payroad\Domain\Refund\Refund;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Provider\RefundData;
use Payroad\Port\Provider\Cash\CashRefundData;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStateMachineInterface;

final class CashRefund extends Refund
{
    private CashRefundData        $data;
    private CashRefundStateMachine $machine;

    public function __construct(
        RefundId           $id,
        PaymentId          $paymentId,
        PaymentAttemptId   $originalAttemptId,
        string             $providerName,
        Money              $amount,
        CashRefundData     $data,
        RefundStatus       $status            = RefundStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $originalAttemptId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CashRefundStateMachine();
    }

    public static function create(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount,
        CashRefundData   $data
    ): self {
        $refund = new self($id, $paymentId, $originalAttemptId, $providerName, $amount, $data);

        $refund->record(new RefundInitiated(
            $refund->getId(),
            $paymentId,
            $originalAttemptId,
            PaymentMethodType::CASH,
            $providerName,
            $amount,
        ));

        return $refund;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CASH;
    }

    protected function stateMachine(): RefundStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CashRefundData
    {
        return $this->data;
    }

    public function updateCashData(CashRefundData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(RefundData $data): void
    {
        if (!$data instanceof CashRefundData) {
            throw new \InvalidArgumentException('CashRefund requires CashRefundData.');
        }
        $this->updateCashData($data);
    }
}
