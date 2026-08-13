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
        DB::table('email_question_answer_drafts')
            ->whereIn('status', ['queued', 'generating'])
            ->where('generated_answer', '[Queued for generation]')
            ->update([
                'final_answer' => null,
                'generation_reason' => null,
                'generation_metadata' => null,
                'generation_error' => null,
                'generation_started_at' => null,
                'generated_at' => null,
                'generation_failed_at' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
