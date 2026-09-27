<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Rencana / Realisasi sheets also carry, per machine: the duration of
     * each realised maintenance day, the operating hours, a remark and a
     * condition note that replaces the day grid (e.g. "Gangguan crankshaft").
     */
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->json('durasi_data')->nullable()->after('schedule_data');
            $table->string('jam_operasi', 50)->nullable()->after('durasi_data');
            $table->text('keterangan')->nullable()->after('jam_operasi');
            $table->string('status_note')->nullable()->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn(['durasi_data', 'jam_operasi', 'keterangan', 'status_note']);
        });
    }
};
