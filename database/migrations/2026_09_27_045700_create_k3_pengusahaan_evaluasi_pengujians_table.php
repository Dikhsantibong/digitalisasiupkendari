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
        Schema::create('k3_pengusahaan_evaluasi_pengujians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('no_urut', 50)->nullable();
            $table->string('nama_kategori_alat');
            $table->string('jenis');
            $table->string('kapasitas')->nullable();
            $table->text('temuan_sertifikat')->nullable();
            $table->json('progres_bulan')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year']);
        });

        Schema::create('k3_pengusahaan_evaluasi_pengujian_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('nomor_dokumen', 100)->nullable()->default('FMG-08-2.3.60');
            $table->string('tanggal_terbit', 100)->nullable()->default('21 Mei 2018');
            $table->string('revisi', 50)->nullable();
            $table->string('halaman', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_evaluasi_pengujian_meta');
        Schema::dropIfExists('k3_pengusahaan_evaluasi_pengujians');
    }
};
