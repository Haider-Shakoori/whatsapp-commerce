<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedInteger('interval_months')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedInteger('max_channels');
            $table->unsignedInteger('max_team_members');
            $table->unsignedInteger('max_products');
            $table->unsignedInteger('max_monthly_outbound');
            $table->boolean('allow_automation')->default(false);
            $table->boolean('allow_ai_drafts')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('merchant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->boolean('auto_renew')->default(false);
            $table->string('provider', 30)->nullable();
            $table->string('provider_subscription_id', 255)->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'period_end']);
        });

        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->char('usage_month', 7);
            $table->unsignedInteger('outbound_count')->default(0);
            $table->timestamps();
            $table->unique(['merchant_id', 'usage_month']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('external_reference')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'created_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'created_at']);
        });

        $now = now();
        DB::table('subscription_plans')->insert([
            [
                'code' => 'trial', 'name' => 'Evaluation', 'description' => '14-day evaluation with bounded resources',
                'currency' => 'USD', 'price_minor' => null, 'interval_months' => 1, 'trial_days' => 14,
                'max_channels' => 2, 'max_team_members' => 5, 'max_products' => 100,
                'max_monthly_outbound' => 250, 'allow_automation' => true, 'allow_ai_drafts' => true,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'starter', 'name' => 'Starter', 'description' => 'Single-channel starter tier; pricing pending approval',
                'currency' => 'USD', 'price_minor' => null, 'interval_months' => 1, 'trial_days' => 0,
                'max_channels' => 1, 'max_team_members' => 3, 'max_products' => 500,
                'max_monthly_outbound' => 1000, 'allow_automation' => false, 'allow_ai_drafts' => false,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'growth', 'name' => 'Growth', 'description' => 'Multi-channel tier; pricing pending approval',
                'currency' => 'USD', 'price_minor' => null, 'interval_months' => 1, 'trial_days' => 0,
                'max_channels' => 5, 'max_team_members' => 15, 'max_products' => 5000,
                'max_monthly_outbound' => 10000, 'allow_automation' => true, 'allow_ai_drafts' => true,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscription_usages');
        Schema::dropIfExists('merchant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
