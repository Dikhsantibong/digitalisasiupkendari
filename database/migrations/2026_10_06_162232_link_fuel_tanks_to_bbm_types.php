<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tank now points at a Jenis BBM of the master, so each unit's BBM list
     * (its tanks) is data, not code. Existing tanks are linked to the Jenis BBM
     * whose code matches their old fuel group (hsd → HSD, mfo → MFO).
     * Ikhtisar Sentral keeps the BBM used per mesin per jenis in a JSON column.
     */
    public function up(): void
    {
        Schema::table('fuel_tanks', function (Blueprint $table): void {
            $table->foreignId('bbm_type_id')->nullable()->after('name')->constrained('bbm_types')->nullOnDelete();
        });

        foreach (DB::table('fuel_tanks')->whereNull('bbm_type_id')->get(['id', 'fuel_type']) as $tank) {
            $bbmTypeId = DB::table('bbm_types')->whereRaw('UPPER(code) = ?', [strtoupper((string) $tank->fuel_type)])->value('id');
            if ($bbmTypeId !== null) {
                DB::table('fuel_tanks')->where('id', $tank->id)->update(['bbm_type_id' => $bbmTypeId]);
            }
        }

        Schema::table('operasi_ikhtisar_sentral_mesins', function (Blueprint $table): void {
            $table->json('pemakaian_bbm')->nullable()->after('pemakaian_mfo');
        });
    }

    public function down(): void
    {
        Schema::table('operasi_ikhtisar_sentral_mesins', function (Blueprint $table): void {
            $table->dropColumn('pemakaian_bbm');
        });

        Schema::table('fuel_tanks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bbm_type_id');
        });
    }
};
