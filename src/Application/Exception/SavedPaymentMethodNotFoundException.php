<?php

namespace Payroad\Application\Exception;

use Payroad\Domain\SavedPaymentMethod\SavedPaymentMethodId;

final class SavedPaymentMethodNotFoundException extends \RuntimeException
{
    public function __construct(SavedPaymentMethodId $id)
    {
        parent::__construct("Saved payment method \"{$id->value}\" not found.");
    }
}
