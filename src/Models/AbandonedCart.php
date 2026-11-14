<?php

namespace ItmoSoft\AbandonedCarts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AbandonedCart extends Model
{
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'last_activity_at' => 'immutable_datetime',
        'abandoned_at' => 'immutable_datetime',
        'recovered_at' => 'immutable_datetime',
        'converted_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('itmosoft-abandoned-carts.table_prefix', 'itmosoft_').'abandoned_carts';
    }

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            $cart->recovery_token ??= Str::random((int) config('itmosoft-abandoned-carts.recovery_token_length', 64));
        });
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeAbandoned($query)
    {
        return $query->whereNotNull('abandoned_at')
            ->whereNull('converted_at')
            ->whereNull('recovered_at');
    }
}
