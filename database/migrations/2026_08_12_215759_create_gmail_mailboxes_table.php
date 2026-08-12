<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gmail_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('last_history_id')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('sync_status')->default('connected')->index();
            $table->text('last_error')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_mailboxes');
    }
};
