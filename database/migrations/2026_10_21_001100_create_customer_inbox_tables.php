<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('name', 120)->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'phone']);
            $table->index(['merchant_id', 'updated_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('messaging_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['messaging_channel_id', 'customer_id']);
            $table->index(['merchant_id', 'status', 'last_message_at']);
            $table->index(['merchant_id', 'assigned_to', 'status']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->unique()->constrained('outbound_messages')->nullOnDelete();
            $table->string('provider_message_id', 150)->nullable();
            $table->string('direction', 12);
            $table->string('message_type', 30)->default('text');
            $table->longText('body')->nullable();
            $table->timestamp('message_at');
            $table->timestamps();
            $table->unique(['conversation_id', 'provider_message_id'], 'conversation_provider_msg_unique');
            $table->index(['merchant_id', 'conversation_id', 'message_at'], 'conv_messages_tenant_thread_time_idx');
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['merchant_id', 'customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('customers');
    }
};
