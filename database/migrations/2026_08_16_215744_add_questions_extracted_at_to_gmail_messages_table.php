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
        Schema::table('gmail_messages', function (Blueprint $table) {
            $table->timestamp('questions_extracted_at')->nullable()->after('imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_messages', function (Blueprint $table) {
            $table->dropColumn('questions_extracted_at');
        });
    }
};
