<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique index on tblStaff.SSO_Email (pusen MySQL).
     *
     * Guarantees one staff account per SSO identity so the SSO login
     * mapping is unambiguous. MySQL permits multiple NULLs, so accounts
     * without an SSO email are unaffected.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->unique('SSO_Email', 'uq_tblStaff_SSO_Email');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->dropUnique('uq_tblStaff_SSO_Email');
        });
    }
};
