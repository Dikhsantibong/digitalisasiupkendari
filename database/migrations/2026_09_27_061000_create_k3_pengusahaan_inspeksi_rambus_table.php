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
        Schema::create('k3_pengusahaan_inspeksi_rambus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-07.01');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 September 2019');
            $table->string('tanggal_inspeksi')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_peng_inspeksi_rambu_unique');
        });

        Schema::create('k3_pengusahaan_inspeksi_rambu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_inspeksi_rambus')->cascadeOnDelete();
            $table->integer('no_id')->default(1);
            $table->string('rambu_k3');
            $table->string('lokasi');
            $table->string('tingkat_pelanggaran')->default('Nihil');
            $table->string('keterangan')->nullable()->default('Perhatian');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_inspeksi_rambu_items');
        Schema::dropIfExists('k3_pengusahaan_inspeksi_rambus');
    }
};
