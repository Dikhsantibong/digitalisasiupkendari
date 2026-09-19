<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input PdM "Realisasi Pemeliharaan Prediktif Bulanan": per unit & month,
     * one row per predictive activity & machine with its planned and realised
     * days. Header (sentral, no. dokumen, penanda tangan) lives in
     * pdm_jadwal_meta (type "realisasi-prediktif").
     */
    public function up(): void
    {
        Schema::create('pdm_realisasi_prediktifs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->default(1);
            $table->string('uraian');
            $table->string('mesin')->nullable();
            $table->json('rencana')->nullable();
            $table->json('realisasi')->nullable();
            $table->decimal('durasi', 8, 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_realisasi_prediktifs');
    }
};
