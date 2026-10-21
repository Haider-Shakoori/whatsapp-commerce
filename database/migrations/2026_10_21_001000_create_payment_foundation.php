<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('cod_enabled')->default(false);
            $table->boolean('bank_transfer_enabled')->default(false);
            $table->text('bank_instructions')->nullable();
            $table->boolean('stripe_test_enabled')->default(false);
            $table->text('stripe_test_secret')->nullable();
            $table->text('stripe_test_webhook_secret')->nullable();
            $table->uuid('webhook_token')->unique();
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 24);
            $table->string('status', 28)->default('pending');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable();
            $table->text('payment_url')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'status']);
            $table->index(['order_id', 'method']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_event_id', 255);
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 32)->default('received');
            $table->timestamps();
            $table->unique(['merchant_id', 'provider', 'provider_event_id'], 'payment_event_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('merchant_payment_settings');
    }
};
