<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('proxy_check_items');
            Schema::dropIfExists('proxy_check_batches');
            Schema::dropIfExists('proxy_product_price_tiers');
            Schema::dropIfExists('proxy_product_durations');
            Schema::dropIfExists('proxy_orders');
            Schema::dropIfExists('user_proxies');
            Schema::dropIfExists('proxy_products');
            Schema::dropIfExists('proxy_services');
            Schema::dropIfExists('proxy_categories');
            Schema::dropIfExists('proxy_providers');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * The legacy proxy schema is intentionally not restorable.
     */
    public function down(): void {}
};
