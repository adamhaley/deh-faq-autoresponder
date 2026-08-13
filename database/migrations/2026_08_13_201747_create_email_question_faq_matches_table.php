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
        Schema::create('email_question_faq_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_question_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('faq_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->float('similarity');
            $table->float('distance');
            $table->json('retrieval_metadata')->nullable();
            $table->timestampTz('retrieved_at');
            $table->timestampsTz();

            $table->unique(['email_question_id', 'faq_entry_id']);
            $table->index(['email_question_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_question_faq_matches');
    }
};
