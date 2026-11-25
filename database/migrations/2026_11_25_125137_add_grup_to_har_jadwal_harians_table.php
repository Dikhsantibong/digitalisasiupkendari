<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal Kegiatan Pemeliharaan grouped harian / mingguan / bulanan / non_rutin
 * (user, 2026-11-25). Every existing row is a daily activity, so it becomes
 * "harian"; nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('har_jadwal_harians', function (Blueprint $table): void {
            $table->string('grup', 20)->default('harian')->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('har_jadwal_harians', function (Blueprint $table): void {
            $table->dropColumn('grup');
        });
    }
};
