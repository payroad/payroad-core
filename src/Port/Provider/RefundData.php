<?php

namespace Payroad\Port\Provider;

/**
 * Base interface for provider-specific refund data.
 * Concrete implementations live in provider integration packages (e.g. payroad/stripe-provider).
 *
 * Use typed sub-interfaces (CardRefundData, CryptoRefundData, etc.) instead of this directly.
 */
interface RefundData
{
}
