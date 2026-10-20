<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->boolean('menu_enabled')->default(false);
        });

        Schema::create('chat_menu_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('messaging_channel_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 20);
            $table->string('screen', 20)->default('categories');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('page')->default(1);
            $table->json('choice_ids')->nullable();
            $table->boolean('opted_out')->default(false);
            $table->timestamps();
            $table->unique(['messaging_channel_id', 'customer_phone'], 'menu_channel_customer_unique');
            $table->index(['merchant_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_menu_sessions');
        Schema::table('messaging_channels', fn (Blueprint $table) => $table->dropColumn('menu_enabled'));
    }
};
