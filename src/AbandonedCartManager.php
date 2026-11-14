<?php

namespace ItmoSoft\AbandonedCarts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ItmoSoft\AbandonedCarts\Models\AbandonedCart;

class AbandonedCartManager
{
    public function __construct(protected array $config)
    {
    }

    /**
     * Create or refresh a cart snapshot. Pass a stable cart_key from the host app.
     * Items should be serializable snapshots, not live Eloquent models.
     */
    public function capture(
        string $cartKey,
        array $items,
        int|float|string $subtotal,
        ?object $customer = null,
        ?string $currency = null,
        ?CarbonInterface $expiresAt = null,
        array $metadata = []
    ): AbandonedCart {
        if (trim($cartKey) === '') {
            throw new InvalidArgumentException('A non-empty cart key is required.');
        }
        if ((float) $subtotal < 0) {
            throw new InvalidArgumentException('Cart subtotal cannot be negative.');
        }

        $cart = AbandonedCart::query()->firstOrNew(['cart_key' => $cartKey]);
        $cart->items = $items;
        $cart->subtotal = $subtotal;
        $cart->currency = $currency;
        $cart->metadata = $metadata;
        $cart->last_activity_at = now();
        $cart->abandoned_at = null;
        $cart->recovered_at = null;
        $cart->converted_at = null;
        $cart->expires_at = $expiresAt;

        if ($customer !== null) {
            $cart->customer()->associate($customer);
        }

        $cart->save();

        return $cart->refresh();
    }

    /**
     * Mark inactive carts as abandoned. Schedule this method from the host app.
     */
    public function markAbandoned(?CarbonInterface $cutoff = null): int
    {
        $cutoff ??= now()->subMinutes((int) ($this->config['abandon_after_minutes'] ?? 60));

        return AbandonedCart::query()
            ->whereNull('abandoned_at')
            ->whereNull('converted_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update(['abandoned_at' => now(), 'updated_at' => now()]);
    }

    public function findByRecoveryToken(string $token): ?AbandonedCart
    {
        if ($token === '') {
            return null;
        }

        return AbandonedCart::query()
            ->where('recovery_token', hash_equals($token, $token) ? $token : '')
            ->whereNull('converted_at')
            ->first();
    }

    public function markRecovered(AbandonedCart $cart): AbandonedCart
    {
        if ($cart->converted_at !== null) {
            throw new InvalidArgumentException('A converted cart cannot be marked as recovered.');
        }

        $cart->forceFill([
            'recovered_at' => now(),
            'abandoned_at' => null,
        ])->save();

        return $cart->refresh();
    }

    public function markConverted(AbandonedCart $cart, ?string $orderReference = null): AbandonedCart
    {
        $cart->forceFill([
            'converted_at' => now(),
            'order_reference' => $orderReference,
            'recovered_at' => $cart->recovered_at ?? now(),
        ])->save();

        return $cart->refresh();
    }

    public function abandoned(?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        $query = AbandonedCart::query()->abandoned();

        if ($from !== null) {
            $query->where('abandoned_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('abandoned_at', '<=', $to);
        }

        return $query;
    }
}
