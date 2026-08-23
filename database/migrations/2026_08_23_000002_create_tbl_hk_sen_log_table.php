<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping for Student — audit log of deleted SEN cases (1 row per case).
 * HK_Run_Id → tblHK_Student_Log.Id (logical link, no FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->create('tblHK_SEN_Log', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->integer('Id', true);                    // auto-increment PK
            $table->integer('HK_Run_Id')->nullable();       // → tblHK_Student_Log.Id
            $table->string('SEN_Id', 10)->nullable();
            $table->string('Student_Id', 12)->nullable();
            $table->string('Remarks', 255)->nullable();
            $table->dateTime('Delete_At')->nullable();      // UTC
            $table->string('Delete_By', 20)->nullable();

            $table->index('HK_Run_Id');
            $table->index('Student_Id');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblHK_SEN_Log');
    }
};
