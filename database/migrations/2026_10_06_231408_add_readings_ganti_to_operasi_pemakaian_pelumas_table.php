<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pelumas ganti (oil change) part of Pemakaian Pelumas; raw_readings
     * stays the total (tambah + ganti), so tambah = total − ganti.
     */
    public function up(): void
    {
        Schema::table('operasi_pemakaian_pelumas', function (Blueprint $table): void {
            $table->json('readings_ganti')->nullable()->after('raw_readings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operasi_pemakaian_pelumas', function (Blueprint $table): void {
            $table->dropColumn('readings_ganti');
        });
    }
};
