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
        Schema::create('k3_pengusahaan_alat_tanggap_darurats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-03.03');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 September 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_tanggap_darurat_unit_year_month_unique');
        });

        Schema::create('k3_pengusahaan_alat_tanggap_darurat_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_alat_tanggap_darurats')->cascadeOnDelete();
            $table->unsignedInteger('no_urut')->default(1);
            $table->string('jenis');
            $table->integer('siap_pakai')->nullable();
            $table->integer('kadaluarsa')->nullable();
            $table->integer('kosong')->nullable();
            $table->string('tgl_diisi_kembali')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_alat_tanggap_darurat_items');
        Schema::dropIfExists('k3_pengusahaan_alat_tanggap_darurats');
    }
};
