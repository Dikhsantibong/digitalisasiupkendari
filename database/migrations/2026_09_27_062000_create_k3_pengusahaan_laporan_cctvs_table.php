<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('k3_pengusahaan_laporan_cctvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-14.01');
            $table->string('revisi')->default('00');
            $table->string('tanggal_dokumen')->default('23 SEPTEMBER 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_peng_laporan_cctv_unique');
        });

        Schema::create('k3_pengusahaan_laporan_cctv_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_laporan_cctvs')->cascadeOnDelete();
            $table->integer('no_urut')->default(1);
            $table->string('tanggal');
            $table->text('lokasi_cctv');
            $table->string('waktu_pantau')->default('Setiap Saat');
            $table->string('kondisi_pantau')->default('Aman');
            $table->string('keterangan')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_laporan_cctv_items');
        Schema::dropIfExists('k3_pengusahaan_laporan_cctvs');
    }
};
