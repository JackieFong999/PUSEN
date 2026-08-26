<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen tblStaff.Password so bcrypt hashes can be stored.
     *
     * The legacy column was varchar(10) — only enough for plain text.
     * bcrypt hashes are 60 chars; varchar(255) also leaves headroom for
     * future algorithm changes (Argon2id hashes are 96+ chars).
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->string('Password', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblStaff', function (Blueprint $table) {
            $table->string('Password', 10)->nullable()->change();
        });
    }
};
