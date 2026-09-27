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
        Schema::create('k3_pengusahaan_jam_kerja_bulanans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Metadata Dokumen
            $table->string('no_dokumen', 100)->nullable()->default('FMZ-08.4.4.11');
            $table->string('tgl_berlaku', 100)->nullable()->default('15 Okt 2014');
            $table->string('revisi', 50)->nullable()->default('0.0');
            $table->string('halaman', 50)->nullable()->default('1 dari 1');

            // 14 Uraian Nilai
            $table->decimal('jam_kerja_komulatif_bulan_lalu', 14, 2)->default(0);
            $table->unsignedInteger('karyawan_tetap')->default(0);
            $table->unsignedInteger('karyawan_tetap_shift')->default(0);
            $table->unsignedInteger('karyawan_tidak_tetap')->default(0);
            $table->unsignedInteger('karyawan_tidak_tetap_shift')->default(0);
            $table->unsignedInteger('jumlah_karyawan')->default(0);
            $table->unsignedInteger('hari_kerja')->default(0);
            $table->decimal('jam_kerja_standart', 8, 2)->default(0);
            $table->decimal('jam_kerja_standart_karyawan', 12, 2)->default(0);
            $table->decimal('jam_kerja_lembur_karyawan', 10, 2)->default(0);
            $table->decimal('jam_kerja_seluruh_karyawan', 12, 2)->default(0);
            $table->decimal('jam_absensi_karyawan', 10, 2)->default(0);
            $table->decimal('jam_kerja_realisasi_karyawan', 12, 2)->default(0);
            $table->decimal('jam_kerja_komulatif_bulan_ini', 14, 2)->default(0);

            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_jam_kerja_bulanans');
    }
};
