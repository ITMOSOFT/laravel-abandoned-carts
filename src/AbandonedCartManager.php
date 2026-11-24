<?php

namespace ItmoSoft\AbandonedCarts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ItmoSoft\AbandonedCarts\Models\AbandonedCart;
use LogicException;

class AbandonedCartManager
{
    public function __construct(protected array $config)
    {
    }

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
        if (! is_numeric($subtotal) || (float) $subtotal < 0) {
            throw new InvalidArgumentException('Cart subtotal must be a non-negative number.');
        }

        return DB::transaction(function () use ($cartKey, $items, $subtotal, $customer, $currency, $expiresAt, $metadata): AbandonedCart {
            $cart = AbandonedCart::query()->where('cart_key', $cartKey)->lockForUpdate()->first();
            if ($cart?->converted_at !== null) {
                throw new LogicException('A converted cart is terminal and cannot be captured again.');
            }
            $cart ??= new AbandonedCart(['cart_key' => $cartKey]);

            $cart->items = $items;
            $cart->subtotal = $subtotal;
            $cart->currency = $currency;
            $cart->metadata = $metadata;
            $cart->last_activity_at = now();
            $cart->abandoned_at = null;
            // A newly active cart can become abandoned again; conversion remains terminal.
            $cart->recovered_at = null;
            $cart->expires_at = $expiresAt;

            if ($customer !== null) {
                $cart->customer()->associate($customer);
            }

            $cart->save();
            return $cart->refresh();
        });
    }

    public function markAbandoned(?CarbonInterface $cutoff = null): int
    {
        $cutoff ??= now()->subMinutes((int) ($this->config['abandon_after_minutes'] ?? 60));

        return AbandonedCart::query()
            ->whereNull('abandoned_at')
            ->whereNull('recovered_at')
            ->whereNull('converted_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->where(function (Builder $query): void {
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
            ->where('recovery_token', $token)
            ->whereNull('converted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function markRecovered(AbandonedCart $cart): AbandonedCart
    {
        return DB::transaction(function () use ($cart): AbandonedCart {
            $locked = AbandonedCart::query()->lockForUpdate()->findOrFail($cart->getKey());
            if ($locked->converted_at !== null) {
                throw new LogicException('A converted cart cannot be marked as recovered.');
            }
            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new LogicException('An expired cart cannot be recovered.');
            }

            if ($locked->recovered_at === null) {
                $locked->forceFill(['recovered_at' => now(), 'abandoned_at' => null])->save();
            }

            return $locked->refresh();
        });
    }

    public function markConverted(AbandonedCart $cart, ?string $orderReference = null): AbandonedCart
    {
        return DB::transaction(function () use ($cart, $orderReference): AbandonedCart {
            $locked = AbandonedCart::query()->lockForUpdate()->findOrFail($cart->getKey());
            if ($locked->converted_at === null) {
                $locked->forceFill([
                    'converted_at' => now(),
                    'order_reference' => $orderReference,
                    'recovered_at' => $locked->recovered_at ?? now(),
                    'abandoned_at' => null,
                ])->save();
            } elseif ($orderReference !== null && $locked->order_reference !== $orderReference) {
                throw new LogicException('A converted cart cannot be linked to a different order.');
            }

            return $locked->refresh();
        });
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
