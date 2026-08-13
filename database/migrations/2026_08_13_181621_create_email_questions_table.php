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
        Schema::create('email_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('question_order')->default(1);
            $table->text('question_text');
            $table->text('normalized_question')->nullable();
            $table->string('question_hash', 64);
            $table->string('classification')->nullable()->index();
            $table->unsignedTinyInteger('classification_confidence')->nullable();
            $table->text('classification_reason')->nullable();
            $table->string('review_status')->default('pending_review')->index();
            $table->string('parser_version')->default('n8n-chat-v1');
            $table->json('extraction_metadata')->nullable();
            $table->json('classification_metadata')->nullable();
            $table->timestampTz('classified_at')->nullable()->index();
            $table->timestampTz('reviewed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['gmail_message_id', 'question_hash']);
            $table->index(['review_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_questions');
    }
};
