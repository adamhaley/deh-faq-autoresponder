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
            $table->string('participant_name')->nullable()->after('from_name');
            $table->string('reply_to_email')->nullable()->after('participant_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_messages', function (Blueprint $table) {
            $table->dropColumn(['participant_name', 'reply_to_email']);
        });
    }
};
