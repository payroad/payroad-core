<?php

namespace Payroad\Domain\PaymentFlow\P2P;

use DateTimeImmutable;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Event\RefundInitiated;
use Payroad\Domain\Refund\Refund;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Provider\RefundData;
use Payroad\Port\Provider\P2P\P2PRefundData;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStateMachineInterface;

final class P2PRefund extends Refund
{
    private P2PRefundData      $data;
    private P2PRefundStateMachine $machine;

    public function __construct(
        RefundId           $id,
        PaymentId          $paymentId,
        PaymentAttemptId   $originalAttemptId,
        string             $providerName,
        Money              $amount,
        P2PRefundData      $data,
        RefundStatus       $status            = RefundStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $originalAttemptId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new P2PRefundStateMachine();
    }

    public static function create(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount,
        P2PRefundData    $data
    ): self {
        $refund = new self($id, $paymentId, $originalAttemptId, $providerName, $amount, $data);

        $refund->record(new RefundInitiated(
            $refund->getId(),
            $paymentId,
            $originalAttemptId,
            PaymentMethodType::P2P,
            $providerName,
            $amount,
        ));

        return $refund;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::P2P;
    }

    protected function stateMachine(): RefundStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): P2PRefundData
    {
        return $this->data;
    }

    public function updateP2PData(P2PRefundData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(RefundData $data): void
    {
        if (!$data instanceof P2PRefundData) {
            throw new \InvalidArgumentException('P2PRefund requires P2PRefundData.');
        }
        $this->updateP2PData($data);
    }
}
