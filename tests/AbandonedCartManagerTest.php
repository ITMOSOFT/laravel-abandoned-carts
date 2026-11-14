<?php

namespace ItmoSoft\AbandonedCarts\Tests;

use Carbon\Carbon;
use ItmoSoft\AbandonedCarts\AbandonedCartManager;
use ItmoSoft\AbandonedCarts\Models\AbandonedCart;

class AbandonedCartManagerTest extends TestCase
{
    public function test_capture_creates_cart_snapshot_and_token(): void
    {
        $cart = app(AbandonedCartManager::class)->capture(
            'session-abc',
            [['sku' => 'SKU-1', 'quantity' => 2]],
            125.50,
            null,
            'USD'
        );

        $this->assertInstanceOf(AbandonedCart::class, $cart);
        $this->assertNotEmpty($cart->recovery_token);
        $this->assertSame('session-abc', $cart->cart_key);
        $this->assertEquals(125.50, $cart->subtotal);
    }

    public function test_inactive_cart_can_be_marked_abandoned(): void
    {
        $manager = app(AbandonedCartManager::class);
        $cart = $manager->capture('old-cart', [], 0);
        $cart->forceFill(['last_activity_at' => Carbon::now()->subHours(3)])->save();

        $this->assertSame(1, $manager->markAbandoned(Carbon::now()->subHour()));
        $this->assertNotNull($cart->fresh()->abandoned_at);
    }

    public function test_recovery_and_conversion_are_recorded(): void
    {
        $manager = app(AbandonedCartManager::class);
        $cart = $manager->capture('recover-me', [], 10);
        $manager->markAbandoned(Carbon::now()->addMinute());
        $cart->refresh();

        $manager->markRecovered($cart);
        $this->assertNotNull($cart->fresh()->recovered_at);

        $manager->markConverted($cart->fresh(), 'ORDER-100');
        $this->assertSame('ORDER-100', $cart->fresh()->order_reference);
        $this->assertNotNull($cart->fresh()->converted_at);
    }

    public function test_abandoned_query_returns_only_abandoned_unconverted_carts(): void
    {
        $manager = app(AbandonedCartManager::class);
        $cart = $manager->capture('abandoned-one', [], 25);
        $manager->markAbandoned(Carbon::now()->addMinute());

        $this->assertSame(1, $manager->abandoned()->count());
    }
}
