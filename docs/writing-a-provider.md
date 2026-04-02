# Writing a Payroad Provider

This guide walks you through building a payment provider for Payroad from scratch.

A provider is an adapter between Payroad's domain model and an external payment API.
It receives domain objects (identifiers, Money amounts), calls the external API,
and returns domain aggregates with the result.

---

## 1. Choose your payment flow

Payroad has four distinct payment flows. Your provider implements the interface
that matches how your payment API works:

| Flow | Interface | When to use |
|------|-----------|-------------|
| **Card** | `CardProviderInterface` | Credit/debit card processing |
| **Crypto** | `CryptoProviderInterface` | Blockchain payment processors |
| **P2P** | `P2PProviderInterface` | Bank transfers, mobile wallets |
| **Cash** | `CashProviderInterface` | Cash vouchers, deposit codes |

Pick one and move to the next step.

---

## 2. Choose your capabilities (Card flow only)

Card providers have optional capability interfaces on top of the base `CardProviderInterface`.
Implement only the ones your API supports:

| Interface | Methods | When to implement |
|-----------|---------|-------------------|
| `OneStepCardProviderInterface` | *(marker)* | Client-side SDK flow (Stripe.js, Adyen Web) — webhook finalizes the attempt |
| `TwoStepCardProviderInterface` | `chargeWithNonce()` | Server-side nonce submission (Braintree Drop-in) |
| `CapturableCardProviderInterface` | `captureAttempt()`, `voidAttempt()` | Authorize + capture / release |
| `TokenizingCardProviderInterface` | `initiateAttemptWithSavedMethod()`, `savePaymentMethod()` | Stored card / off-session charges |

You can implement multiple capabilities. Stripe, for example, implements all four.
Internal mock implements only `CapturableCardProviderInterface`.

---

## 3. Create the AttemptData class

Every flow has a typed data class that holds provider-specific fields on the attempt.
You must create one that implements the matching interface:

| Flow | Interface to implement |
|------|----------------------|
| Card | `CardAttemptData` |
| Crypto | `CryptoAttemptData` |
| P2P | `P2PAttemptData` |
| Cash | `CashAttemptData` |

The interface requires getters for all standard fields (last4, clientToken, wallet address, etc.)
and two serialization methods used by the persistence layer:

```php
// src/Data/AcmeCardAttemptData.php

namespace Payroad\Provider\Acme\Data;

use Payroad\Domain\Channel\Card\CardAttemptData;
use Payroad\Domain\Channel\Card\ThreeDSData;

final class AcmeCardAttemptData implements CardAttemptData
{
    public function __construct(
        // Fields your API returns that you want to store
        private readonly ?string $acmeTransactionId = null,
        private readonly ?string $clientToken       = null,
        private readonly ?string $last4             = null,
        private readonly ?string $cardBrand         = null,
    ) {}

    // ── CardAttemptData interface ─────────────────────────────────────────────

    public function getClientToken(): ?string      { return $this->clientToken; }
    public function getLast4(): ?string            { return $this->last4; }
    public function getBin(): ?string              { return null; }
    public function getExpiryMonth(): ?int         { return null; }
    public function getExpiryYear(): ?int          { return null; }
    public function getCardholderName(): ?string   { return null; }
    public function getCardBrand(): ?string        { return $this->cardBrand; }
    public function getFundingType(): ?string      { return null; }
    public function getIssuingCountry(): ?string   { return null; }
    public function requiresUserAction(): bool     { return $this->clientToken !== null; }
    public function getThreeDSData(): ?ThreeDSData { return null; }

    // ── Serialization — used by the persistence layer ─────────────────────────

    public function toArray(): array
    {
        return [
            'acme_transaction_id' => $this->acmeTransactionId,
            'client_token'        => $this->clientToken,
            'last4'               => $this->last4,
            'card_brand'          => $this->cardBrand,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            acmeTransactionId: $data['acme_transaction_id'] ?? null,
            clientToken:       $data['client_token'] ?? null,
            last4:             $data['last4'] ?? null,
            cardBrand:         $data['card_brand'] ?? null,
        );
    }
}
```

**Rules:**
- `toArray()` / `fromArray()` must be symmetric — whatever you put in must come back out
- All keys in `toArray()` must be present in `fromArray()` with a `?? null` default (older records may be missing new fields)
- Do not store sensitive data (full card numbers, CVV) — store only what your API returns and what you need

---

## 4. Create the RefundData class (if your API supports refunds)

Same pattern, implementing the matching refund data interface:

```php
// src/Data/AcmeCardRefundData.php

namespace Payroad\Provider\Acme\Data;

use Payroad\Domain\Channel\Card\CardRefundData;

final class AcmeCardRefundData implements CardRefundData
{
    public function __construct(
        private readonly ?string $acmeRefundId = null,
        private readonly ?string $reason       = null,
    ) {}

    public function getReason(): ?string                  { return $this->reason; }
    public function getAcquirerReferenceNumber(): ?string { return null; }

    public function toArray(): array
    {
        return [
            'acme_refund_id' => $this->acmeRefundId,
            'reason'         => $this->reason,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            acmeRefundId: $data['acme_refund_id'] ?? null,
            reason:       $data['reason'] ?? null,
        );
    }
}
```

---

## 5. Implement the provider

Now implement the provider class itself. Here is the full contract for a card provider:

```php
// src/AcmeCardProvider.php

namespace Payroad\Provider\Acme;

use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\Channel\Card\CardPaymentAttempt;
use Payroad\Domain\Channel\Card\CardRefund;
use Payroad\Domain\Refund\RefundId;
use Payroad\Domain\Refund\RefundStatus;
use Payroad\Port\Provider\Card\CapturableCardProviderInterface;
use Payroad\Port\Provider\Card\CardAttemptContext;
use Payroad\Port\Provider\Card\CardRefundContext;
use Payroad\Port\Provider\Card\CaptureResult;
use Payroad\Port\Provider\Card\OneStepCardProviderInterface;
use Payroad\Port\Provider\Card\VoidResult;
use Payroad\Port\Provider\WebhookEvent;
use Payroad\Port\Provider\WebhookResult;
use Payroad\Provider\Acme\Data\AcmeCardAttemptData;
use Payroad\Provider\Acme\Data\AcmeCardRefundData;

final class AcmeCardProvider implements OneStepCardProviderInterface, CapturableCardProviderInterface
{
    public function __construct(
        private readonly AcmeHttpClient $client,
        private readonly string         $webhookSecret,
    ) {}

    // ── Required by PaymentProviderInterface ──────────────────────────────────

    /**
     * Return true only for the provider name this instance is registered under.
     * The registry calls this to route requests to the right provider.
     */
    public function supports(string $providerName): bool
    {
        return $providerName === 'acme';
    }

    // ── Required by CardProviderInterface ─────────────────────────────────────

    /**
     * Create a payment attempt with your provider and return a CardPaymentAttempt.
     *
     * Contract:
     * - Always call CardPaymentAttempt::create() to build the aggregate
     * - Always call $attempt->setProviderReference() with the external ID
     * - Apply status transitions to reflect what the API returned
     * - Do NOT call $attempt->applyTransition(SUCCEEDED) unless the charge is
     *   confirmed synchronously — let the webhook do it for async flows
     */
    public function initiateCardAttempt(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CardAttemptContext $context,
    ): CardPaymentAttempt {
        $response = $this->client->createPaymentIntent([
            'amount'   => $amount->getMinorAmount(),
            'currency' => strtolower($amount->getCurrency()->code),
        ]);

        $data    = new AcmeCardAttemptData(
            acmeTransactionId: $response['id'],
            clientToken:       $response['client_token'],
        );
        $attempt = CardPaymentAttempt::create($id, $paymentId, $providerName, $amount, $data);

        // Always set the provider reference — used for capture, void, refund
        $attempt->setProviderReference($response['id']);

        // For OneStep flow (Stripe.js): the client confirms the charge via the SDK.
        // The attempt stays PENDING here — webhook will transition it to SUCCEEDED later.
        //
        // If your API starts processing immediately on the server side (charge is in-flight
        // but not yet settled), transition to PROCESSING instead:
        //   $attempt->applyTransition(AttemptStatus::PROCESSING, $response['status']);

        return $attempt;
    }

    /**
     * Initiate a refund and return a CardRefund aggregate.
     *
     * Contract:
     * - Always call CardRefund::create() to build the aggregate
     * - Always call $refund->setProviderReference() with the external refund ID
     * - Apply status transitions based on the API response
     * - For async refund APIs: leave the refund in PENDING — webhook will complete it
     */
    public function initiateRefund(
        RefundId          $id,
        PaymentId         $paymentId,
        PaymentAttemptId  $originalAttemptId,
        string            $providerName,
        Money             $amount,
        string            $originalProviderReference,
        CardRefundContext  $context,
    ): CardRefund {
        $response = $this->client->createRefund([
            'transaction_id' => $originalProviderReference,
            'amount'         => $amount->getMinorAmount(),
        ]);

        $data   = new AcmeCardRefundData(
            acmeRefundId: $response['refund_id'],
            reason:       $context->reason,
        );
        $refund = CardRefund::create($id, $paymentId, $originalAttemptId, $providerName, $amount, $data);
        $refund->setProviderReference($response['refund_id']);
        $refund->applyTransition(RefundStatus::SUCCEEDED, $response['status']);

        return $refund;
    }

    // ── CapturableCardProviderInterface ───────────────────────────────────────

    public function captureAttempt(string $providerReference, ?Money $amount = null): CaptureResult
    {
        $params   = ['transaction_id' => $providerReference];
        if ($amount !== null) {
            $params['amount'] = $amount->getMinorAmount();
        }

        $response = $this->client->captureTransaction($params);

        return new CaptureResult(
            newStatus:      $response['status'] === 'settled'
                                ? AttemptStatus::SUCCEEDED
                                : AttemptStatus::PROCESSING,
            providerStatus: $response['status'],
        );
    }

    public function voidAttempt(string $providerReference): VoidResult
    {
        $response = $this->client->voidTransaction(['transaction_id' => $providerReference]);

        return new VoidResult(
            newStatus:      AttemptStatus::CANCELED,
            providerStatus: $response['status'],
        );
    }

    // ── Webhook parsing ───────────────────────────────────────────────────────

    /**
     * Parse and verify an incoming webhook from your provider.
     *
     * Contract:
     * - Verify the signature first — throw \InvalidArgumentException on failure
     * - Return WebhookResult for attempt status changes
     * - Return RefundWebhookResult for refund status changes
     * - Return null for event types you intentionally ignore
     * - Never return null for signature failures — throw instead
     */
    public function parseIncomingWebhook(array $payload, array $headers): ?WebhookEvent
    {
        $this->verifySignature($payload, $headers);

        $eventType = $payload['event_type'] ?? '';

        return match (true) {
            $eventType === 'payment.succeeded' => new WebhookResult(
                providerReference: $payload['transaction_id'],
                newStatus:         AttemptStatus::SUCCEEDED,
                providerStatus:    $payload['status'],
            ),
            $eventType === 'payment.failed' => new WebhookResult(
                providerReference: $payload['transaction_id'],
                newStatus:         AttemptStatus::FAILED,
                providerStatus:    $payload['status'],
                reason:            $payload['failure_reason'] ?? '',
            ),
            default => null, // ignore other event types
        };
    }

    private function verifySignature(array $payload, array $headers): void
    {
        $signature = $headers['x-acme-signature'][0]
            ?? throw new \InvalidArgumentException('Missing webhook signature header.');

        $expected = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Webhook signature verification failed.');
        }
    }
}
```

---

## 6. Create the factory

The factory is how the Symfony bridge instantiates your provider from config:

```php
// src/AcmeProviderFactory.php

namespace Payroad\Provider\Acme;

use Payroad\Port\Provider\ProviderFactoryInterface;

final class AcmeProviderFactory implements ProviderFactoryInterface
{
    public function create(array $config): AcmeCardProvider
    {
        return new AcmeCardProvider(
            new AcmeHttpClient($config['api_key']),
            $config['webhook_secret'],
        );
    }
}
```

---

## 7. Register with Symfony

Add your provider to `config/packages/payroad.yaml`:

```yaml
payroad:
    providers:
        acme:
            factory: Payroad\Provider\Acme\AcmeProviderFactory
            config:
                api_key:        '%env(ACME_API_KEY)%'
                webhook_secret: '%env(ACME_WEBHOOK_SECRET)%'
```

That's it. The Symfony bridge will instantiate your provider via the factory and
register it in the `ProviderRegistry`. Your provider will be available as:

```php
$registry->forCard('acme'); // returns AcmeCardProvider
```

---

## 8. Key rules

**Always follow the attempt contract:**

```php
// 1. Create via the static factory — never call constructor directly
$attempt = CardPaymentAttempt::create($id, $paymentId, $providerName, $amount, $data);

// 2. Always set provider reference — use the external ID returned by the API
$attempt->setProviderReference($response['id']); // must not be empty string

// 3. Apply transitions that reflect what actually happened synchronously
// For async (client confirms later via webhook) — no transition, leave as PENDING
// For sync charge — transition to PROCESSING then SUCCEEDED
$attempt->applyTransition(AttemptStatus::PROCESSING, 'processing');
$attempt->applyTransition(AttemptStatus::SUCCEEDED,  'settled');
```

**AttemptStatus transition cheat sheet:**

| Scenario | Transitions to apply in provider |
|----------|----------------------------------|
| Async (client-side SDK, webhook finalizes) | *(none — stay PENDING)* |
| Sync charge | `PROCESSING → SUCCEEDED` |
| Authorize only | `AUTHORIZED` |
| Requires user action (redirect, 3DS) | `AWAITING_CONFIRMATION` |
| Immediate failure | `FAILED` |

**Never:**
- Modify the `Payment` aggregate — that's the use case's responsibility
- Access repositories — providers receive everything they need as arguments
- Throw provider-specific exceptions — use standard PHP exceptions
  (`\InvalidArgumentException`, `\RuntimeException`)
- Store sensitive card data (PANs, CVVs) in AttemptData

---

## 9. Write tests

Test your provider in isolation — mock the HTTP client, verify the domain objects come back correctly:

```php
final class AcmeCardProviderTest extends TestCase
{
    public function testInitiateCardAttemptSetsProviderReference(): void
    {
        $client = $this->createMock(AcmeHttpClient::class);
        $client->method('createPaymentIntent')->willReturn([
            'id'           => 'acme-txn-123',
            'client_token' => 'tok_abc',
        ]);

        $provider = new AcmeCardProvider($client, 'webhook-secret');
        $id       = PaymentAttemptId::generate();
        $attempt  = $provider->initiateCardAttempt(
            $id,
            PaymentId::generate(),
            'acme',
            Money::ofMinor(1000, new Currency('USD', 2)),
            new CardAttemptContext('1.2.3.4', 'Mozilla/5.0'),
        );

        $this->assertSame('acme-txn-123', $attempt->getProviderReference());
        $this->assertSame(AttemptStatus::PENDING, $attempt->getStatus());
    }

    public function testParseWebhookReturnsSucceededResult(): void
    {
        // ...
    }

    public function testParseWebhookThrowsOnInvalidSignature(): void
    {
        // ...
    }
}
```

---

## Reference: Crypto provider

Crypto providers follow the same pattern but use `CryptoPaymentAttempt` and `CryptoAttemptData`:

```php
final class MyCryptoProvider implements CryptoProviderInterface
{
    public function supports(string $providerName): bool
    {
        return $providerName === 'my_crypto';
    }

    public function initiateCryptoAttempt(
        PaymentAttemptId    $id,
        PaymentId           $paymentId,
        string              $providerName,
        Money               $amount,
        CryptoAttemptContext $context,
    ): CryptoPaymentAttempt {
        $response = $this->client->createInvoice([...]);

        $data    = new MyCryptoAttemptData(
            depositAddress:    $response['address'],
            confirmationsLeft: 3,
        );
        $attempt = CryptoPaymentAttempt::create($id, $paymentId, $providerName, $amount, $data);
        $attempt->setProviderReference($response['invoice_id']);
        $attempt->applyTransition(AttemptStatus::AWAITING_CONFIRMATION, 'waiting_deposit');

        return $attempt;
    }

    public function parseIncomingWebhook(array $payload, array $headers): ?WebhookEvent
    {
        // verify signature, map status, return WebhookResult
    }
}
```

For crypto providers that support on-chain refunds, additionally implement
`RefundableCryptoProviderInterface`. If your provider does not support
programmatic refunds, do not implement the interface — the use case layer
will throw `ProviderNotFoundException` with a clear message.

---

## Real examples

- **Card (OneStep + Capturable + Tokenizing):** [`payroad/stripe-provider`](https://github.com/payroad/stripe-provider)
- **Card (TwoStep + Capturable + Tokenizing):** [`payroad/braintree-provider`](https://github.com/payroad/braintree-provider)
- **Card (mock, all modes):** [`payroad/internal-card-provider`](https://github.com/payroad/internal-card-provider)
- **Crypto (with refunds):** [`payroad/nowpayments-provider`](https://github.com/payroad/nowpayments-provider)
- **Crypto (no refunds):** [`payroad/coingate-provider`](https://github.com/payroad/coingate-provider)
