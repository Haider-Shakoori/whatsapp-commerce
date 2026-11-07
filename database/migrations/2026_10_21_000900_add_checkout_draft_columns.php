<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_menu_sessions', function (Blueprint $table) {
            $table->string('checkout_step', 24)->nullable();
            $table->uuid('checkout_token')->nullable();
            $table->string('checkout_customer_name', 120)->nullable();
            $table->string('checkout_method', 20)->nullable();
            $table->text('checkout_address')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
        Schema::table('chat_menu_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_step', 'checkout_token', 'checkout_customer_name',
                'checkout_method', 'checkout_address',
            ]);
        });
    }
};
