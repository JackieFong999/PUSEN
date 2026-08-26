<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the student name columns so AES-256-CBC ciphertext fits.
     *
     * Student names are encrypted at rest (STUDENT_NAME_KEY, since
     * 2026-08-26): a 30-char name produces ~200 chars of ciphertext, so
     * varchar(30)/varchar(5) are no longer enough. varchar(255) is the
     * ceiling for a utf8mb4 column under an index-free legacy table.
     */
    public function up(): void
    {
        Schema::connection('pusen')->table('tblStudent', function (Blueprint $table) {
            $table->string('Student_Name_Eng', 255)->nullable()->change();
            $table->string('Student_Name_Chn', 255)->nullable()->change();
        });

        Schema::connection('pusen')->table('tblHK_Student_Log', function (Blueprint $table) {
            $table->string('Student_Name_Eng', 255)->nullable()->change();
            $table->string('Student_Name_Chn', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Only safe after student:decrypt-names has restored plaintext.
        Schema::connection('pusen')->table('tblStudent', function (Blueprint $table) {
            $table->string('Student_Name_Eng', 30)->nullable()->change();
            $table->string('Student_Name_Chn', 5)->nullable()->change();
        });

        Schema::connection('pusen')->table('tblHK_Student_Log', function (Blueprint $table) {
            $table->string('Student_Name_Eng', 30)->nullable()->change();
            $table->string('Student_Name_Chn', 5)->nullable()->change();
        });
    }
};
