<?php

namespace Payroad\Domain\PaymentFlow\Crypto;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class CryptoPaymentAttempt extends PaymentAttempt
{
    private CryptoAttemptData  $data;
    private CryptoStateMachine $machine;

    private function __construct(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount, CryptoAttemptData $data)
    {
        parent::__construct($id, $paymentId, $providerName, $amount);
        $this->data    = $data;
        $this->machine = new CryptoStateMachine();
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
