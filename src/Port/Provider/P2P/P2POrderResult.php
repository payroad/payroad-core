<?php

declare(strict_types=1);

namespace Payroad\Port\Provider\P2P;

use Payroad\Domain\Attempt\AttemptStatus;

/**
 * Result returned by TwoStepP2PProviderInterface::authorizeOrder().
 */
final readonly class P2POrderResult
{
    public function __construct(
        public string        $orderId,
        public AttemptStatus $newStatus,
        public string        $providerStatus,
    ) {}
}
