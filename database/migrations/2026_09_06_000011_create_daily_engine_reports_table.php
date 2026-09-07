<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per machine per date: the daily meter readings that are actually
 * typed in. Opening stands and hours are NOT stored — opening stands carry over
 * automatically and hours come from the Star-Stop log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_engine_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('report_date');

            $table->decimal('kwh_produksi_stand_akhir', 18, 4)->nullable();
            $table->decimal('kwh_pakai_sendiri_stand_akhir', 18, 4)->nullable();
            $table->decimal('beban_puncak_pagi_kw', 14, 2)->nullable();
            $table->decimal('beban_puncak_malam_kw', 14, 2)->nullable();
            $table->decimal('pemakaian_pelumas_liter', 14, 2)->nullable();

            $table->decimal('flowmeter_hsd_stand_akhir', 18, 4)->nullable();
            $table->decimal('flowmeter_hsd_tambah_liter', 18, 4)->nullable();
            $table->decimal('flowmeter_mfo_stand_akhir', 18, 4)->nullable();
            $table->decimal('flowmeter_mfo_tambah_liter', 18, 4)->nullable();

            $table->decimal('air_pps_stand_akhir', 18, 4)->nullable();
            $table->decimal('air_softener_stand_akhir', 18, 4)->nullable();

            $table->string('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['engine_id', 'report_date']);
            $table->index(['unit_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_engine_reports');
    }
};
