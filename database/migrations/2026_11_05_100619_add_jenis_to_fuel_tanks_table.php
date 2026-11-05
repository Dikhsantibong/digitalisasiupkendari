<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Tangki BBM: the jenis tangki (storage, setlink, transfer, service)
     * the Opname BBM groups the tanks by. Existing tanks start as service when
     * they are a daily tank, otherwise storage; the master corrects them.
     */
    public function up(): void
    {
        Schema::table('fuel_tanks', function (Blueprint $table): void {
            $table->string('jenis', 20)->nullable()->after('name');
        });

        DB::table('fuel_tanks')->where('is_daily_tank', true)->update(['jenis' => 'service']);
        DB::table('fuel_tanks')->whereNull('jenis')->update(['jenis' => 'storage']);
    }

    public function down(): void
    {
        Schema::table('fuel_tanks', function (Blueprint $table): void {
            $table->dropColumn('jenis');
        });
    }
};
