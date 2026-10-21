<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('automation_opted_out')->default(false);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_automation_at')->nullable();
        });

        Schema::create('automation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('after_hours_enabled')->default(false);
            $table->boolean('keyword_enabled')->default(false);
            $table->boolean('followups_enabled')->default(false);
            $table->boolean('ai_drafts_enabled')->default(false);
            $table->json('weekly_hours')->nullable();
            $table->text('after_hours_message')->nullable();
            $table->text('ai_guidance')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 80);
            $table->string('reply_text', 1000)->nullable();
            $table->foreignId('assign_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['merchant_id', 'enabled', 'priority']);
        });

        Schema::create('automation_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('source_message_id', 100);
            $table->string('rule_type', 30);
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'source_message_id'], 'automation_inbound_dedupe');
        });

        Schema::create('automation_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('latest_inbound_id');
            $table->longText('body');
            $table->timestamp('due_at');
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_at']);
            $table->index(['merchant_id', 'conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_followups');
        Schema::dropIfExists('automation_replies');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('automation_settings');
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('last_automation_at'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('automation_opted_out'));
    }
};
