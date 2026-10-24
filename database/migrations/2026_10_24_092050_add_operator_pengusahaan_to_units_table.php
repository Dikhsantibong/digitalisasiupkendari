<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the operators of a unit fill the Pengusahaan Operasi inputs
     * (operasi.pengusahaan.input). Set per unit in Master Unit; the
     * containerized units start without it.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->boolean('operator_pengusahaan')->default(true)->after('contract');
        });

        DB::table('units')
            ->where(fn ($query) => $query->where('code', 'like', '%-CONT%')->orWhere('name', 'like', '%containerized%'))
            ->update(['operator_pengusahaan' => false]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('operator_pengusahaan');
        });
    }
};
