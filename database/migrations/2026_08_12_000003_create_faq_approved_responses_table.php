<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_approved_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('faq_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('approved_response');
            $table->float('match_similarity')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_approved_responses');
    }
};
