<?php

namespace Payroad\Port\Provider;

/**
 * Marker interface for parsed webhook events.
 * Implemented by WebhookResult and RefundWebhookResult.
 *
 * The controller uses instanceof checks to route to the correct use case —
 * no string-based event-type routing leaks into application or infrastructure layers.
 */
interface WebhookEvent
{
}
