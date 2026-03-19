<?php

namespace Payroad\Domain\PaymentFlow\P2P;

use Payroad\Domain\Attempt\AttemptData;

/**
 * Data interface for P2P (peer-to-peer bank transfer) payment attempts.
 * Implementations live in provider packages (e.g. payroad/transferwise-provider).
 */
interface P2PAttemptData extends AttemptData
{
    /** Bank account number or card number to transfer funds to. */
    public function getTransferTarget(): string;

    /** Human-readable name of the receiving bank or payment system. */
    public function getRecipientBankName(): string;

    /** Full name of the account holder. */
    public function getRecipientHolderName(): string;
}
