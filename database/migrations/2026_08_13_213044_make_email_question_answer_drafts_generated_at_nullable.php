<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('alter table email_question_answer_drafts alter column generated_at drop not null');

        DB::table('email_question_answer_drafts')
            ->whereIn('status', ['queued', 'generating'])
            ->where('generated_answer', '[Queued for generation]')
            ->update(['generated_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_question_answer_drafts')
            ->whereNull('generated_at')
            ->update(['generated_at' => DB::raw('created_at')]);

        DB::statement('alter table email_question_answer_drafts alter column generated_at set not null');
    }
};
