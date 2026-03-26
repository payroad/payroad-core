# payroad-core

<p>
  <a href="https://github.com/payroad/payroad-core/actions"><img src="https://github.com/payroad/payroad-core/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/payroad/payroad-core"><img src="https://img.shields.io/packagist/v/payroad/payroad-core" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/payroad/payroad-core"><img src="https://img.shields.io/packagist/php-v/payroad/payroad-core" alt="PHP Version"></a>
  <a href="https://github.com/payroad/payroad-core/blob/main/LICENSE"><img src="https://img.shields.io/github/license/payroad/payroad-core" alt="License"></a>
</p>

Framework-agnostic payment domain for the Payroad ecosystem — aggregates, use cases, and provider ports with no framework or database dependencies.

---

## Installation

```bash
composer require payroad/payroad-core
```

Requires PHP 8.2+.

---

## Overview

`payroad-core` is the shared kernel of the Payroad payment platform. It defines the domain model and the contracts (ports) that infrastructure must implement.

```
┌──────────────────────────────────┐
│         Your Application         │
│   (Symfony / Laravel / custom)   │
└────────────────┬─────────────────┘
                 │ uses
┌────────────────▼─────────────────┐
│           payroad-core           │
│  Domain · Use Cases · Ports      │
└───────┬──────────────┬───────────┘
        │ implements   │ implements
┌───────▼──────┐ ┌─────▼────────────┐
│   Provider   │ │  Infrastructure  │
│ stripe, etc. │ │  DB, Events, …   │
└──────────────┘ └──────────────────┘
```

This package contains **no** HTTP, ORM, or provider-specific code.

---

## Payment flows

Four payment methods are supported, each with its own aggregate, state machine, and refund flow:

| Flow | Attempt class | Key steps |
|------|--------------|-----------|
| **Card** | `CardPaymentAttempt` | Authorize → (3DS) → Capture / Void |
| **Crypto** | `CryptoPaymentAttempt` | Address issued → Confirmations → Settled |
| **P2P** | `P2PPaymentAttempt` | QR / redirect → User confirms → Settled |
| **Cash** | `CashPaymentAttempt` | Voucher issued → Cash collected → Settled |

---

## Usage

### Create a payment

```php
use Payroad\Application\UseCase\Payment\CreatePaymentCommand;
use Payroad\Domain\Money\Currency;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\CustomerId;
use Payroad\Domain\Payment\PaymentMetadata;

$command = new CreatePaymentCommand(
    amount:    Money::ofDecimal('49.99', new Currency('USD', 2)),
    customerId: CustomerId::of('customer-456'),
    metadata:  PaymentMetadata::fromArray(['orderId' => '789']),
    expiresAt: new DateTimeImmutable('+30 minutes'),
);

$useCase->execute($command);
```

Supply your own ID when needed (e.g. client-generated UUID):

```php
use Payroad\Domain\Payment\PaymentId;

$command = new CreatePaymentCommand(
    // ...
    id: PaymentId::fromUuid('018e4c3d-1a2b-7000-...'),
);
```

### Initiate a card attempt

```php
use Payroad\Application\UseCase\Card\InitiateCardAttemptCommand;
use Payroad\Port\Provider\Card\CardAttemptContext;

$command = new InitiateCardAttemptCommand(
    paymentId:    $payment->getId(),
    providerName: 'stripe',
    context:      new CardAttemptContext(ip: '1.2.3.4', userAgent: '...'),
);

$useCase->execute($command);
```

### Capture / void an authorized card

```php
use Payroad\Application\UseCase\Card\CaptureCardAttemptCommand;
use Payroad\Application\UseCase\Card\VoidCardAttemptCommand;

// Full capture
$captureUseCase->execute(new CaptureCardAttemptCommand($attempt->getId()));

// Partial capture
$captureUseCase->execute(new CaptureCardAttemptCommand(
    $attempt->getId(),
    Money::ofDecimal('25.00', new Currency('USD', 2)),
));

// Void (release the authorization)
$voidUseCase->execute(new VoidCardAttemptCommand($attempt->getId()));
```

### Handle an incoming webhook

```php
use Payroad\Application\UseCase\Webhook\HandleWebhookCommand;

$useCase->execute(new HandleWebhookCommand(
    providerName: 'stripe',
    payload:      $request->toArray(),
    headers:      $request->headers->all(),
));
```

The use case is idempotent — duplicate webhooks from at-least-once providers are safely ignored.

### Cancel or expire a payment

```php
use Payroad\Application\UseCase\Payment\CancelPaymentCommand;
use Payroad\Application\UseCase\Payment\ExpirePaymentCommand;

$cancelUseCase->execute(new CancelPaymentCommand($payment->getId()));
$expireUseCase->execute(new ExpirePaymentCommand($payment->getId()));
```

---

## Key design decisions

### Payment and PaymentAttempt are separate aggregates

`Payment` is a thin business document — amount, customer, status. It never holds attempt objects, only the ID of the winning attempt once resolved.

`PaymentAttempt` is the operational aggregate that drives actual money movement through a provider. Multiple attempts may exist per payment (retries).

### Money carries precision explicitly

```php
// Fiat — precision from ISO 4217, provided by infrastructure KnownCurrencies
new Currency('USD', 2)   // 1 USD = 100 cents
new Currency('JPY', 0)   // 1 JPY, no subunits
new Currency('KWD', 3)   // 1 KWD = 1000 fils

// Crypto — precision always explicit
new Currency('BTC',  8)  // 1 BTC = 100_000_000 satoshis
new Currency('USDT', 6)  // 1 USDT = 1_000_000 micro-USDT
new Currency('ETH',  18) // ⚠ int overflow above ~9.2 ETH — use ofDecimal()

Money::ofMinor(4999, new Currency('USD', 2))           // $49.99
Money::ofDecimal('0.00100000', new Currency('BTC', 8)) // 100 000 satoshis
```

`Currency` carries no registry — precision is resolved by the infrastructure layer before entering the domain.

### State machines are embedded per flow

Each attempt subclass validates transitions before applying them:

```
Card:    PENDING → AWAITING_CONFIRMATION → AUTHORIZED → PROCESSING → SUCCEEDED
                 └──────────────────────→             └→ FAILED
                                                       └→ EXPIRED

Crypto:  PENDING → PROCESSING → SUCCEEDED | FAILED | EXPIRED

P2P:     PENDING → AWAITING_CONFIRMATION → PROCESSING → SUCCEEDED | FAILED | EXPIRED

Cash:    PENDING → AWAITING_CONFIRMATION → SUCCEEDED | FAILED | EXPIRED
```

An invalid transition throws `\DomainException` before any state changes.

### Domain events

Every state change produces typed events consumed by your application layer:

| Event | Trigger |
|-------|---------|
| `PaymentCreated` | Payment created |
| `PaymentProcessingStarted` | First attempt initiated |
| `PaymentSucceeded` | Attempt settled |
| `PaymentRetryAvailable` | Attempt failed, payment re-queued for retry |
| `PaymentCanceled` / `PaymentExpired` / `PaymentFailed` | Terminal transitions |
| `AttemptInitiated` | Attempt created |
| `AttemptAuthorized` | Card authorized, ready to capture |
| `AttemptRequiresUserAction` | 3DS / P2P redirect needed |
| `AttemptSucceeded` / `AttemptFailed` / `AttemptCanceled` / `AttemptExpired` | Terminal attempt states |

---

## Implementing a provider

Create a Composer package and implement the port interface for your payment method:

```php
use Payroad\Domain\Attempt\AttemptStatus;
use Payroad\Domain\Attempt\PaymentAttemptId;
use Payroad\Domain\Money\Money;
use Payroad\Domain\Payment\PaymentId;
use Payroad\Domain\PaymentFlow\Card\CardPaymentAttempt;
use Payroad\Port\Provider\Card\CardAttemptContext;
use Payroad\Port\Provider\Card\CardProviderInterface;
use Payroad\Port\Provider\Card\CaptureResult;
use Payroad\Port\Provider\WebhookResult;

final class StripeCardProvider implements CardProviderInterface
{
    public function name(): string
    {
        return 'stripe';
    }

    public function initiateCardAttempt(
        PaymentAttemptId   $id,
        PaymentId          $paymentId,
        string             $providerName,
        Money              $amount,
        CardAttemptContext $context,
    ): CardPaymentAttempt {
        $intent = $this->stripe->paymentIntents->create([
            'amount'   => $amount->getMinorAmount(),
            'currency' => strtolower((string) $amount->getCurrency()),
        ]);

        $attempt = CardPaymentAttempt::create(
            $id, $paymentId, $providerName, $amount,
            new StripeCardData(clientSecret: $intent->client_secret),
        );
        $attempt->setProviderReference($intent->id);

        return $attempt;
    }

    public function captureAttempt(string $providerReference, ?Money $amount = null): CaptureResult
    {
        $this->stripe->paymentIntents->capture($providerReference);

        return new CaptureResult(AttemptStatus::SUCCEEDED, 'succeeded');
    }

    public function parseWebhook(array $payload, array $headers): WebhookResult
    {
        // verify signature, parse event …

        return new WebhookResult(
            providerReference: $payload['data']['object']['id'],
            providerStatus:    $payload['data']['object']['status'],
            newStatus:         AttemptStatus::SUCCEEDED,
            statusChanged:     true,
        );
    }

    // … voidAttempt, initiateRefund, savePaymentMethod
}
```

Register the provider in your `ProviderRegistryInterface` implementation. No changes to this package are needed.

---

## Testing

With Docker (no local PHP required):

```bash
make test                                    # run all tests
make filter FILTER=testPaymentMarkedSucceeded # run a single test
make shell                                   # open a shell inside the container
```

Without Docker:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpunit --filter testPaymentMarkedSucceededOnSyncCapture
```

---

## Ecosystem

| Package | Description |
|---------|-------------|
| `payroad/payroad-core` | **This package** |
| `payroad/stripe-provider` | Card payments via Stripe |
| `payroad/braintree-provider` | Card payments via Braintree |
| `payroad/nowpayments-provider` | Crypto payments via NOWPayments |
| `payroad/coingate-provider` | Crypto payments via CoinGate |
| `payroad/quickstart` | 5-minute quickstart — mock card + Stripe |
| `payroad/payroad-symfony-demo` | Full reference application (all flows) |

---

## License

MIT — see [LICENSE](LICENSE).
