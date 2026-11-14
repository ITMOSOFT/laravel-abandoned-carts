<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('itmosoft-abandoned-carts.table_prefix', 'itmosoft_');

        Schema::create($prefix.'abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('cart_key')->unique();
            $table->nullableMorphs('customer');
            $table->json('items');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->char('currency', 3)->nullable();
            $table->string('recovery_token', 100)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('abandoned_at')->nullable()->index();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('converted_at')->nullable()->index();
            $table->string('order_reference')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('itmosoft-abandoned-carts.table_prefix', 'itmosoft_');
        Schema::dropIfExists($prefix.'abandoned_carts');
    }
};
