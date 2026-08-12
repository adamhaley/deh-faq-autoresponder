<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('faq_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('question');
            $table->text('answer');
            $table->text('embedding')->nullable();
            $table->timestampsTz();
        });

        if ($isPostgres) {
            DB::statement('ALTER TABLE faq_entries ALTER COLUMN embedding TYPE vector(1536) USING embedding::vector');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
    }
};
