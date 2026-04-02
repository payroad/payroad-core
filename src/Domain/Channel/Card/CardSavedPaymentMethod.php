<?php

namespace Payroad\Domain\Channel\Card;

use DateTimeImmutable;
use Payroad\Domain\PaymentMethodType;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\SavedPaymentMethod\Event\PaymentMethodSaved;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethod;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;
use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodStatus;

final class CardSavedPaymentMethod extends SavedPaymentMethod
{
    private CardSavedPaymentMethodData $data;

    public function __construct(
        SavedPaymentMethodId       $id,
        CustomerId                 $customerId,
        string                     $providerName,
        string                     $providerToken,
        CardSavedPaymentMethodData $data,
        SavedPaymentMethodStatus   $status    = SavedPaymentMethodStatus::ACTIVE,
        ?DateTimeImmutable         $createdAt = null,
    ) {
        parent::__construct($id, $customerId, $providerName, $providerToken, $status, $createdAt);
        $this->data = $data;
    }

    public static function create(
        SavedPaymentMethodId       $id,
        CustomerId                 $customerId,
        string                     $providerName,
        string                     $providerToken,
        CardSavedPaymentMethodData $data,
    ): self {
        $method = new self($id, $customerId, $providerName, $providerToken, $data);

        $method->record(new PaymentMethodSaved(
            $method->getId(),
            $method->getCustomerId(),
            PaymentMethodType::CARD,
            $method->getProviderName(),
        ));

        return $method;
    }

    public function getMethodType(): PaymentMethodType
    {
        return PaymentMethodType::CARD;
    }

    public function getData(): CardSavedPaymentMethodData
    {
        return $this->data;
    }
}
