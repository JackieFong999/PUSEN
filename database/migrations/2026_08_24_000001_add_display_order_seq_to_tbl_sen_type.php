<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEN Type drag-reorder support (2026-08-24):
 * add display_order_seq and backfill by alphabetical rank (SEN_Type),
 * so the first load after the change matches the previous A-Z order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->table('tblSEN_Type', function (Blueprint $table) {
            $table->integer('display_order_seq')->default(0)->after('SEN_Type');
        });

        // backfill: rank existing rows alphabetically (1-based)
        DB::connection('pusen')->statement(
            'UPDATE tblSEN_Type t
             JOIN (SELECT Id, ROW_NUMBER() OVER (ORDER BY SEN_Type) rn FROM tblSEN_Type) x
               ON x.Id = t.Id
             SET t.display_order_seq = x.rn'
        );
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblSEN_Type', function (Blueprint $table) {
            $table->dropColumn('display_order_seq');
        });
    }
};
