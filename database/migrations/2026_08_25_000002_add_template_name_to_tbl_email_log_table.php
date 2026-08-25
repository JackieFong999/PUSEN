<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the template used for manual Email Management sends (ET-003 subject teacher / ET-004 student).
     * Jackie 2026-08-25.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblEmail_Log', function (Blueprint $table) {
            $table->string('Template_Name', 50)->nullable()->after('Recipient_Type');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblEmail_Log', function (Blueprint $table) {
            $table->dropColumn('Template_Name');
        });
    }
};
