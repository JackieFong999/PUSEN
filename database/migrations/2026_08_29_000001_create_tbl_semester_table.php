<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tblSemester: month/day ranges that map a calendar date to a semester.
     *
     * Used to resolve the current (Academic_Year, Semester) for the subject
     * teacher lookup in SEN Case (Create/Edit/View) and Email Management —
     * the same Subject_Code can have a different Teacher_Staff_Id per
     * (Academic_Year, Semester) in tblSubject.
     *
     * Ranges agreed with Jackie (2026-08-29):
     *   Sem 1: 01/08 - 15/12
     *   Sem 2: 16/12 - 10/04   (crosses the calendar year boundary)
     *   Sem 3: 11/04 - 31/07   (extended to cover the summer gap -> full year)
     */
    public function up(): void
    {
        Schema::connection('pusen')->create('tblSemester', function (Blueprint $table) {
            $table->unsignedTinyInteger('Semester')->primary();
            $table->unsignedTinyInteger('Start_Month');
            $table->unsignedTinyInteger('Start_Day');
            $table->unsignedTinyInteger('End_Month');
            $table->unsignedTinyInteger('End_Day');
            $table->timestamps();
        });

        DB::connection('pusen')->table('tblSemester')->insert([
            ['Semester' => 1, 'Start_Month' => 8,  'Start_Day' => 1,  'End_Month' => 12, 'End_Day' => 15],
            ['Semester' => 2, 'Start_Month' => 12, 'Start_Day' => 16, 'End_Month' => 4,  'End_Day' => 10],
            ['Semester' => 3, 'Start_Month' => 4,  'Start_Day' => 11, 'End_Month' => 7,  'End_Day' => 31],
        ]);
    }

    public function down(): void
    {
        Schema::connection('pusen')->dropIfExists('tblSemester');
    }
};
