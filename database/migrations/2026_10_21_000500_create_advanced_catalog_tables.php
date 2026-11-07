<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['merchant_id', 'sku'], 'products_merchant_sku_unique');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['merchant_id', 'sku'], 'variants_merchant_sku_unique');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 24)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'product_id']);
        });

        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('min_choices')->default(0);
            $table->unsignedTinyInteger('max_choices')->default(1);
            $table->timestamps();
            $table->index(['merchant_id', 'product_id']);
        });

        Schema::create('product_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_modifier_group_id', 'modifier_group_id')->constrained('product_modifier_groups')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'product_modifier_group_id'], 'modifier_option_tenant_group_idx');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('before_quantity');
            $table->unsignedInteger('after_quantity');
            $table->string('reason', 255);
            $table->timestamps();
            $table->index(['merchant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('product_modifier_options');
        Schema::dropIfExists('product_modifier_groups');
        Schema::dropIfExists('product_images');

        Schema::table('product_variants', fn (Blueprint $table) => $table->dropUnique('variants_merchant_sku_unique'));
        Schema::table('products', fn (Blueprint $table) => $table->dropUnique('products_merchant_sku_unique'));
    }
};
