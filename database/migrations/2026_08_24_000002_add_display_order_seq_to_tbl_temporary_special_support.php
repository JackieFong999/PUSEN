<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary Special Support drag-reorder support (2026-08-24):
 * add display_order_seq and backfill by alphabetical rank,
 * so the first load after the change matches the previous A-Z order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pusen')->table('tblTemporary_Special_Support', function (Blueprint $table) {
            $table->integer('display_order_seq')->default(0)->after('Temporary_Special_Support');
        });

        // backfill: rank existing rows alphabetically (1-based)
        DB::connection('pusen')->statement(
            'UPDATE tblTemporary_Special_Support t
             JOIN (SELECT Id, ROW_NUMBER() OVER (ORDER BY Temporary_Special_Support) rn
                   FROM tblTemporary_Special_Support) x
               ON x.Id = t.Id
             SET t.display_order_seq = x.rn'
        );
    }

    public function down(): void
    {
        Schema::connection('pusen')->table('tblTemporary_Special_Support', function (Blueprint $table) {
            $table->dropColumn('display_order_seq');
        });
    }
};
