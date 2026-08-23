<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping for Student — audit log (backup) of deleted student records.
 * Spec: docs/pusen01-housekeeping-student-spec.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->create('tblHK_Student_Log', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';

            $table->integer('Id', true);                    // auto-increment PK
            $table->string('Student_Id', 12);
            $table->string('Student_Name_Eng', 30)->nullable();
            $table->string('Student_Name_Chn', 5)->nullable();
            $table->string('Student_Status', 15)->nullable();
            $table->dateTime('Student_created_at')->nullable();
            $table->dateTime('Student_updated_at')->nullable(); // the value that qualified the row
            $table->string('Remarks', 255)->nullable();
            $table->dateTime('Delete_At')->nullable();      // UTC
            $table->string('Delete_By', 20)->nullable();    // logged-in Staff_Id

            $table->index('Student_Id');
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblHK_Student_Log');
    }
};
