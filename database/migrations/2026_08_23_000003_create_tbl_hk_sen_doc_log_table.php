<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping for Student — audit log of deleted SEN documents (1 row per doc).
 * Doc_Path = resolved server path at delete time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->create('tblHK_SEN_Doc_Log', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->integer('Id', true);                    // auto-increment PK
            $table->integer('HK_Run_Id')->nullable();       // → tblHK_Student_Log.Id
            $table->string('SEN_Id', 10)->nullable();
            $table->integer('Doc_Seq')->nullable();
            $table->string('Doc_Path', 255)->nullable();            // resolved server path at delete time
            $table->string('Doc_Filename', 255)->nullable();
            $table->string('Doc_Filename_Original', 255)->nullable();
            $table->string('Remarks', 255)->nullable();     // pending / deleted / file not found / FILE DELETE FAILED / no file
            $table->dateTime('Delete_At')->nullable();      // UTC
            $table->string('Delete_By', 20)->nullable();

            $table->index('HK_Run_Id');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblHK_SEN_Doc_Log');
    }
};
