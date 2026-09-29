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
        Schema::table('email_thread_drafts', function (Blueprint $table): void {
            // Whichever user's approval most recently triggered this
            // thread's compose -- records which template was used, and
            // lets the "Edit template" quick action edit the right one.
            $table->foreignId('user_id')->nullable()->after('thread_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_thread_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
