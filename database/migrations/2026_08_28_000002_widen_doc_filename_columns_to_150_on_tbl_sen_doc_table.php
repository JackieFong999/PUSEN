<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix SQLSTATE 22001 "Data too long for column 'Doc_Filename'" on SEN save:
 * stored doc filenames ({SEN_Id}_{seq}_{original}) could exceed varchar(60).
 * Widened to 150 (Jackie 2026-08-28) + client-side check blocks originals > 100 chars,
 * so max stored name ~= 12 + 100 = 112 chars, well within 150.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->table('tblSEN_Doc', function (Blueprint $table) {
            $table->string('Doc_Filename', 150)->nullable()->change();
            $table->string('Doc_Filename_Original', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblSEN_Doc', function (Blueprint $table) {
            $table->string('Doc_Filename', 60)->nullable()->change();
            $table->string('Doc_Filename_Original', 60)->nullable()->change();
        });
    }
};
