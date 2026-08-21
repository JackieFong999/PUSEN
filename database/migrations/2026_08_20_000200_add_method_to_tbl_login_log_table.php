<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Method to tblLogin_Log (pusen MySQL).
     *
     * Records how the login happened: LOCAL (staff ID + password) or SSO.
     * Nullable for backward compatibility with existing rows.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblLogin_Log', function (Blueprint $table) {
            $table->string('Method', 10)->nullable()->after('Browser');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblLogin_Log', function (Blueprint $table) {
            $table->dropColumn('Method');
        });
    }
};
