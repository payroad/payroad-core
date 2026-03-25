<?php

namespace Payroad\Domain\PaymentFlow\Crypto;

use DateTimeImmutable;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Refund\Event\RefundInitiated;
use Payroad\Domain\Refund\Refund;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Provider\RefundData;
use Payroad\Port\Provider\Crypto\CryptoRefundData;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStateMachineInterface;

final class CryptoRefund extends Refund
{
    private CryptoRefundData        $data;
    private CryptoRefundStateMachine $machine;

    public function __construct(
        RefundId           $id,
        PaymentId          $paymentId,
        PaymentAttemptId   $originalAttemptId,
        string             $providerName,
        Money              $amount,
        CryptoRefundData   $data,
        RefundStatus       $status            = RefundStatus::PENDING,
        string             $providerStatus    = 'pending',
        ?string            $providerReference = null,
        ?DateTimeImmutable $createdAt         = null,
    ) {
        parent::__construct($id, $paymentId, $originalAttemptId, $providerName, $amount, $status, $providerStatus, $providerReference, $createdAt);
        $this->data    = $data;
        $this->machine = new CryptoRefundStateMachine();
    }

    public static function create(
        RefundId         $id,
        PaymentId        $paymentId,
        PaymentAttemptId $originalAttemptId,
        string           $providerName,
        Money            $amount,
        CryptoRefundData $data
    ): self {
        $refund = new self($id, $paymentId, $originalAttemptId, $providerName, $amount, $data);

        $refund->record(new RefundInitiated(
            $refund->getId(),
            $paymentId,
            $originalAttemptId,
            PaymentMethodType::CRYPTO,
            $providerName,
            $amount,
        ));

        return $refund;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CRYPTO;
    }

    protected function stateMachine(): RefundStateMachineInterface
    {
        return $this->machine;
    }

    public function getData(): CryptoRefundData
    {
        return $this->data;
    }

    public function updateCryptoData(CryptoRefundData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(RefundData $data): void
    {
        if (!$data instanceof CryptoRefundData) {
            throw new \InvalidArgumentException('CryptoRefund requires CryptoRefundData.');
        }
        $this->updateCryptoData($data);
    }
}
