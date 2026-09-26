<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the divisi now derived by Employee::fieldDivision(): shift
 * operators (with a regu, Leader Shift included) belong to Operasi and the
 * Harmes / Harlist maintenance staff to Pemeliharaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')->whereNotNull('regu')->where('regu', '!=', '')
            ->update(['division' => 'operasi']);

        DB::table('employees')->whereNull('regu')->whereIn('position', ['Harmes', 'Harlist'])
            ->update(['division' => 'pemeliharaan']);
    }

    public function down(): void
    {
        DB::table('employees')->whereNotNull('regu')->where('division', 'operasi')
            ->update(['division' => 'operator']);
    }
};
