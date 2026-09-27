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
        Schema::create('k3_pengusahaan_jam_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Metadata Dokumen
            $table->string('no_dokumen', 100)->nullable()->default('FMZ-08.4.4.10');
            $table->string('tgl_berlaku', 100)->nullable()->default('15 Okt 2014');
            $table->string('revisi', 50)->nullable()->default('0.0');
            $table->string('halaman', 50)->nullable()->default('1 dari 1');

            // A. Karyawan
            $table->unsignedInteger('karyawan_tetap')->default(0);
            $table->unsignedInteger('karyawan_tetap_shift')->default(0);
            $table->unsignedInteger('karyawan_tidak_tetap')->default(0);
            $table->unsignedInteger('karyawan_tidak_tetap_shift')->default(0);

            // B. Hari Kerja dan Jam Kerja / Orang / Bulan
            $table->unsignedInteger('hari_tetap')->default(0);
            $table->decimal('jam_tetap', 5, 2)->default(8);
            $table->decimal('lembur_tetap', 8, 2)->default(0);

            $table->unsignedInteger('hari_tetap_shift')->default(0);
            $table->decimal('jam_tetap_shift', 5, 2)->default(8);
            $table->decimal('lembur_tetap_shift', 8, 2)->default(0);

            $table->unsignedInteger('hari_tidak_tetap')->default(0);
            $table->decimal('jam_tidak_tetap', 5, 2)->default(8);
            $table->decimal('lembur_tidak_tetap', 8, 2)->default(0);

            $table->unsignedInteger('hari_tidak_tetap_shift')->default(0);
            $table->decimal('jam_tidak_tetap_shift', 5, 2)->default(8);
            $table->decimal('lembur_tidak_tetap_shift', 8, 2)->default(0);

            // E. Absensi Karyawan
            $table->unsignedInteger('cuti_orang')->default(0);
            $table->unsignedInteger('cuti_hari')->default(0);
            $table->decimal('cuti_jam', 8, 2)->default(0);

            $table->unsignedInteger('ijin_orang')->default(0);
            $table->unsignedInteger('ijin_hari')->default(0);
            $table->decimal('ijin_jam', 8, 2)->default(0);

            $table->unsignedInteger('sakit_orang')->default(0);
            $table->unsignedInteger('sakit_hari')->default(0);
            $table->decimal('sakit_jam', 8, 2)->default(0);

            // C, D, F Kalkulasi Tersimpan
            $table->decimal('total_jam_kerja_orang', 12, 2)->default(0);
            $table->decimal('total_lembur', 10, 2)->default(0);
            $table->decimal('total_absensi_jam', 10, 2)->default(0);
            $table->decimal('total_jam_kerja_seluruh', 12, 2)->default(0);

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
        Schema::dropIfExists('k3_pengusahaan_jam_kerjas');
    }
};
