<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('messaging_channel_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 20);
            $table->char('currency', 3);
            $table->timestamps();
            $table->unique(['messaging_channel_id', 'customer_phone'], 'cart_channel_customer_unique');
            $table->index(['merchant_id', 'updated_at']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopping_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->char('selection_key', 64);
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->json('selected_options')->nullable();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->timestamps();
            $table->unique(['shopping_cart_id', 'selection_key'], 'cart_items_selection_unique');
            $table->index(['merchant_id', 'product_id']);
        });

        Schema::table('chat_menu_sessions', function (Blueprint $table) {
            $table->foreignId('selected_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('pending_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedTinyInteger('pending_quantity')->nullable();
            $table->json('pending_options')->nullable();
            $table->unsignedTinyInteger('pending_group_index')->default(0);
            $table->unsignedTinyInteger('pending_variant_page')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('chat_menu_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_product_id');
            $table->dropConstrainedForeignId('pending_variant_id');
            $table->dropColumn(['pending_quantity', 'pending_options', 'pending_group_index', 'pending_variant_page']);
        });

        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('shopping_carts');
    }
};
