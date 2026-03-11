<?php

namespace Payroad\Domain\Payment;

enum PaymentMethodType: string
{
    case CARD   = 'card';
    case CRYPTO = 'crypto';
    case P2P    = 'p2p';
    case CASH   = 'cash';
}
