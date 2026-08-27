<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen tblConfig_Password.Password from varchar(20) to varchar(100).
     *
     * The column previously held short plain-text passwords; widening leaves
     * headroom for longer values (e.g. future encrypted/derived passwords).
     * Current rows (srss123 for EXCEL/PDF) are unaffected.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblConfig_Password', function (Blueprint $table) {
            $table->string('Password', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblConfig_Password', function (Blueprint $table) {
            $table->string('Password', 20)->nullable()->change();
        });
    }
};
