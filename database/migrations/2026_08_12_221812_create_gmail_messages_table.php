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
        Schema::create('gmail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_message_id');
            $table->string('thread_id')->nullable();
            $table->string('history_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->text('snippet')->nullable();
            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->json('label_ids')->nullable();
            $table->timestampTz('internal_date')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestampTz('imported_at')->index();
            $table->timestampsTz();

            $table->unique(['gmail_mailbox_id', 'gmail_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_messages');
    }
};
