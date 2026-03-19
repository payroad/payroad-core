<?php

namespace Payroad\Domain\PaymentFlow\P2P;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class P2PPaymentAttempt extends PaymentAttempt
{
    private P2PAttemptData  $data;
    private P2PStateMachine $machine;

    private function __construct(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount, P2PAttemptData $data)
    {
        parent::__construct($id, $paymentId, $providerName, $amount);
        $this->data    = $data;
        $this->machine = new P2PStateMachine();
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
