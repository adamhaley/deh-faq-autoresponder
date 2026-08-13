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
        Schema::table('email_questions', function (Blueprint $table) {
            $table->string('faq_retrieval_status')->default('not_started')->after('review_status');
            $table->text('faq_retrieval_error')->nullable()->after('faq_retrieval_status');
            $table->timestampTz('faq_retrieval_started_at')->nullable()->after('faq_retrieval_error');
            $table->timestampTz('faq_retrieval_completed_at')->nullable()->after('faq_retrieval_started_at');
            $table->timestampTz('faq_retrieval_failed_at')->nullable()->after('faq_retrieval_completed_at');

            $table->index(['faq_retrieval_status', 'review_status']);
        });

        Schema::table('email_question_answer_drafts', function (Blueprint $table) {
            $table->text('generation_error')->nullable()->after('generation_metadata');
            $table->timestampTz('generation_started_at')->nullable()->after('generation_error');
            $table->timestampTz('generation_failed_at')->nullable()->after('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_question_answer_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'generation_error',
                'generation_started_at',
                'generation_failed_at',
            ]);
        });

        Schema::table('email_questions', function (Blueprint $table) {
            $table->dropIndex(['faq_retrieval_status', 'review_status']);
            $table->dropColumn([
                'faq_retrieval_status',
                'faq_retrieval_error',
                'faq_retrieval_started_at',
                'faq_retrieval_completed_at',
                'faq_retrieval_failed_at',
            ]);
        });
    }
};
