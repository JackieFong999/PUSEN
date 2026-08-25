<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log for MANUAL Email Management sends (ET-003 subject teachers / ET-004 students).
     * One row per (SEN case, recipient) email attempt. Jackie's schema 2026-08-25
     * (Recipient_Type widened to 40 to fit 'SUBJECT_TEACHER'; SEN_Id indexed).
     */
    public function up(): void
    {
        Schema::connection('pusen')->create('tblEmail_Log', function (Blueprint $table) {
            $table->integer('Id', true);
            $table->string('SEN_Id', 10)->index();
            $table->string('Student_Id', 12)->nullable();
            $table->string('Recipient_Type', 40);
            $table->string('Recipient_Name', 30)->nullable();
            $table->string('Recipient_Email', 100);
            $table->string('Email_Status', 10);
            $table->string('Remarks', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('created_by', 20)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblEmail_Log');
    }
};
