<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input PdM "Kesiapan APD Bagian PdM Pembangkit": one row per inspected item
     * per unit & period. Header (catatan, tempat/tanggal, penanda tangan) lives
     * in pdm_jadwal_meta (type "kesiapan-apd").
     */
    public function up(): void
    {
        Schema::create('pdm_kesiapan_apds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kelompok')->default('Alat Pelindung Diri (APD)');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('inspeksi');
            $table->unsignedInteger('jumlah')->nullable();
            $table->string('satuan', 30)->nullable();
            $table->string('kelayakan_apd', 20)->nullable();
            $table->string('peralatan_jumlah', 20)->nullable();
            $table->string('peralatan_kelayakan', 20)->nullable();
            $table->string('sop_pnp', 20)->nullable();
            $table->string('sop_vendor', 20)->nullable();
            $table->string('p3k_kotak', 20)->nullable();
            $table->string('p3k_isi', 20)->nullable();
            $table->string('cara_kerja', 20)->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_kesiapan_apds');
    }
};
