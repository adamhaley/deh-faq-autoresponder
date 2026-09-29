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
        Schema::table('email_templates', function (Blueprint $table): void {
            // Nullable = a personal template for that user; null = the
            // shared fallback used when a user has none of their own.
            // Postgres allows multiple NULLs under a unique index, so this
            // only enforces "at most one personal template per user".
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
