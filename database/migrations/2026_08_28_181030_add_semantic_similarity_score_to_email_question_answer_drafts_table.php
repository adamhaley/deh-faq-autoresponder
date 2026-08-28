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
        Schema::table('email_question_answer_drafts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('semantic_similarity_score')->nullable()->after('final_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_question_answer_drafts', function (Blueprint $table): void {
            $table->dropColumn('semantic_similarity_score');
        });
    }
};
