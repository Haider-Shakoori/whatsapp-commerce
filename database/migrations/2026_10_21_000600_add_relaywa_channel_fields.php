<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique();
            $table->timestamp('status_checked_at')->nullable();
            $table->unique(['provider', 'external_account_id'], 'channels_provider_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->dropUnique('channels_provider_external_unique');
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'status_checked_at']);
        });
    }
};
