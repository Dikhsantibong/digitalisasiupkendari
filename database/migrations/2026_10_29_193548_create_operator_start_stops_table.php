<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Start Stop Engine (modul Operator): one row per run of a machine — the
     * start, CBG on, CBG off and stop times of a tanggal, and whether the stop
     * was a gangguan (counted in Jumlah Kali Gangguan).
     */
    public function up(): void
    {
        Schema::create('operator_start_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('start_time', 5)->nullable();
            $table->string('cbg_on', 5)->nullable();
            $table->string('cbg_off', 5)->nullable();
            $table->string('stop_time', 5)->nullable();
            $table->string('penyebab_stop', 20)->default('normal');
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_start_stops');
    }
};
