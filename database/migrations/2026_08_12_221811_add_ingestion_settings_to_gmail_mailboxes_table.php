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
        Schema::table('gmail_mailboxes', function (Blueprint $table) {
            $table->json('label_ids')->nullable()->after('scopes');
            $table->string('import_query')->nullable()->after('label_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_mailboxes', function (Blueprint $table) {
            $table->dropColumn(['label_ids', 'import_query']);
        });
    }
};
