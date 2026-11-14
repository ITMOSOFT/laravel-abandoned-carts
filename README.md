# ITMOSOFT Laravel Abandoned Carts

Abandoned cart capture, inactivity detection, recovery tracking and conversion recording for Laravel 12 and 13.

## Features

- Store cart snapshots using a stable host-application `cart_key`.
- Optional polymorphic customer association.
- Configurable inactivity threshold.
- Mark stale carts as abandoned.
- Unique recovery tokens and token lookup.
- Record recovery and order conversion.
- Query abandoned carts by date range.
- Configurable table prefix.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-abandoned-carts
php artisan vendor:publish --tag=itmosoft-abandoned-carts-config
php artisan migrate
```

The migration creates `itmosoft_abandoned_carts` by default. Configure `table_prefix` before running migrations if you need a different table name.

## Capture or refresh a cart

```php
use ItmoSoft\AbandonedCarts\AbandonedCartManager;

$manager = app(AbandonedCartManager::class);

$record = $manager->capture(
    cartKey: (string) $cart->id,
    items: [
        ['product_id' => 10, 'sku' => 'SKU-10', 'name' => 'Example', 'quantity' => 2, 'unit_price' => '25.00'],
    ],
    subtotal: '50.00',
    customer: auth()->user(),
    currency: 'USD',
    metadata: ['source' => 'web'],
);
```

Pass a stable unique key for the cart from your application. The `items` argument should be a serializable snapshot. The package does not depend on or modify a particular cart package.

## Detect abandoned carts

Schedule the manager method in your application's scheduler. For example, call it hourly:

```php
use Illuminate\Support\Facades\Schedule;
use ItmoSoft\AbandonedCarts\AbandonedCartManager;

Schedule::call(fn () => app(AbandonedCartManager::class)->markAbandoned())
    ->hourly();
```

The default threshold is 60 minutes since `last_activity_at`. Configure `abandon_after_minutes` in `config/itmosoft-abandoned-carts.php`.

## Recovery and conversion

```php
$record = $manager->findByRecoveryToken($token);

if ($record) {
    $manager->markRecovered($record);
}

// After the order is successfully created:
$manager->markConverted($record, (string) $order->getKey());
```

Use `recovery_token` to build a signed or otherwise protected recovery URL in your application. Do not expose cart snapshots or customer information solely because a token is present without applying your application's authorization and expiry rules.

## Query abandoned carts

```php
$abandoned = $manager->abandoned(
    now()->subDays(7),
    now(),
)->latest('abandoned_at')->get();
```

## Important scope and production notes

- This package stores snapshots; it does not integrate automatically with `itmosoft/laravel-cart` or any other cart implementation.
- The host application must call `capture()` whenever cart contents or activity change, and must schedule `markAbandoned()`.
- It does not send email/SMS/push reminders, generate signed URLs, restore cart contents, or create orders.
- The package does not implement campaign frequency caps, consent management, GDPR/data-retention workflows, multi-tenant scopes, or per-customer deduplication. Add these policies in the host application.
- `cart_key` is unique and must be stable. Use a suitable opaque identifier; do not use raw session secrets.
- The subtotal is stored as a decimal string/decimal database value; the package does not calculate tax, shipping, discounts, or currency conversion.
- Recovery tokens are random, stored as plaintext and intended as opaque lookup tokens. Protect recovery endpoints with HTTPS, rate limiting, expiry/authorization checks and consider hashing tokens at rest for higher-security deployments.
- Concurrent capture calls for the same new `cart_key` can race at the unique constraint. Handle duplicate-key retries in the host app if concurrent writes are possible.
- `markAbandoned()` uses a bulk update; it does not dispatch per-record Eloquent model events.

## Testing

```bash
composer install
composer validate --strict
composer test
```

## License

MIT. See [LICENSE](LICENSE).
