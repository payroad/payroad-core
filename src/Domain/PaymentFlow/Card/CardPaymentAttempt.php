<?php

namespace Payroad\Domain\PaymentFlow\Card;

use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Attempt\AttemptStateMachineInterface;
use Payroad\Domain\Attempt\AttemptData;
use Payroad\Domain\Attempt\Event\AttemptInitiated;
use Payroad\Domain\Attempt\PaymentAttempt;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;

final class CardPaymentAttempt extends PaymentAttempt
{
    private CardAttemptData  $data;
    private CardStateMachine $machine;

    private function __construct(PaymentAttemptId $id, PaymentId $paymentId, string $providerName, Money $amount, CardAttemptData $data)
    {
        parent::__construct($id, $paymentId, $providerName, $amount);
        $this->data    = $data;
        $this->machine = new CardStateMachine();
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

    public function updateCardData(CardAttemptData $data): void
    {
        $this->data = $data;
    }

    public function updateSpecificData(AttemptData $data): void
    {
        if (!$data instanceof CardAttemptData) {
            throw new \InvalidArgumentException('CardPaymentAttempt requires CardAttemptData.');
        }
        $this->updateCardData($data);
    }
}
