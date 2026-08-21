<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add SSO_Email to the legacy tblStaff table (pusen MySQL).
     *
     * Purpose: map IdP identities (email / UPN) to staff accounts so the
     * future SSO login path can find the Staff row behind an SSO user.
     * Nullable on purpose: most accounts have no SSO email yet.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->string('SSO_Email', 100)->nullable()->after('Password');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->dropColumn('SSO_Email');
        });
    }
};
