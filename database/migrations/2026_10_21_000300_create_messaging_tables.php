<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('display_name')->nullable();
            $table->string('external_account_id')->nullable();
            $table->text('credentials')->nullable(); // Encrypted Eloquent cast; no credentials in logs.
            $table->string('status', 20)->default('disconnected');
            $table->timestamps();
            $table->index(['merchant_id', 'provider']);
        });
        Schema::create('inbound_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('messaging_channel_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id');
            $table->longText('payload'); // Encrypted Eloquent cast.
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['messaging_channel_id', 'provider_event_id'], 'unique_channel_event');
        });
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('messaging_channel_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->text('body');
            $table->string('idempotency_key');
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('inbound_events');
        Schema::dropIfExists('messaging_channels');
    }
};
