<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the template for scheduled/import stakeholder emails on tblEmail_List
     * (ET-002 — SEN stakeholder-change emails after CSV import; consistent with tblEmail_Log).
     * Jackie 2026-08-25.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblEmail_List', function (Blueprint $table) {
            $table->string('Template_Name', 50)->nullable()->after('Stakeholder_Type');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblEmail_List', function (Blueprint $table) {
            $table->dropColumn('Template_Name');
        });
    }
};
