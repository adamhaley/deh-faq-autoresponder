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
        Schema::create('email_thread_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('thread_id')->unique();
            $table->string('gmail_draft_id')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('status');
            $table->text('last_error')->nullable();
            $table->timestampTz('composed_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_thread_drafts');
    }
};
