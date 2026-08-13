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
        Schema::create('email_question_answer_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_question_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('generated_answer');
            $table->text('final_answer')->nullable();
            $table->string('status');
            $table->text('generation_reason')->nullable();
            $table->json('generation_metadata')->nullable();
            $table->timestampTz('generated_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_question_answer_drafts');
    }
};
