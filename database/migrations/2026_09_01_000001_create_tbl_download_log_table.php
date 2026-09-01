<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log for SEN document DOWNLOADS (UAT CC-13, Jackie 2026-09-01).
     * One row per download click on a SEN document (?dl=1).
     * SEN_Id is parsed from the storage filename; Doc_Filename_Original is the
     * true client-side filename shown to / downloaded by the user.
     */
    public function up(): void
    {
        Schema::connection('pusen')->create('tblDownload_Log', function (Blueprint $table) {
            $table->integer('Id', true);
            $table->string('SEN_Id', 10)->index();
            $table->string('Doc_Filename_Original', 150);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by', 20)->nullable();
            $table->string('updated_ip', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblDownload_Log');
    }
};
