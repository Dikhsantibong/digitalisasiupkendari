<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_laporan_gangguans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            // Kop laporan
            $table->string('nomor')->nullable();               // 003/ULPLTD POASIA/LH05/XI/2023
            $table->date('tanggal_laporan')->nullable();       // Tanggal laporan
            $table->string('hal')->nullable();                 // SHAFT & BEARING GENERATOR
            $table->string('form_code')->default('LH - 05');
            $table->string('unit_kesatuan')->nullable();       // CONTAINERIZED SITE POASIA

            // Identitas mesin (bisa diprefill dari master mesin, disimpan agar dokumen mandiri)
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('merek')->nullable();               // Cummins Unit #6
            $table->string('type')->nullable();                // KTA-5-G8
            $table->string('no_seri')->nullable();
            $table->string('rh')->nullable();                  // 9003.1 HRS
            $table->string('jsb')->nullable();
            $table->string('jsmo')->nullable();
            $table->string('jsi_terakhir')->nullable();        // JSI Terakhir (MO)
            $table->string('fungsi_pembangkit')->nullable();   // PLTD POASIA
            $table->string('daya_terpasang')->nullable();      // 11200 kw
            $table->string('daya_mampu')->nullable();          // 850 kw

            // Isi laporan (1-9)
            $table->string('tanggal_jam_kerusakan')->nullable();
            $table->text('peralatan_rusak')->nullable();
            $table->text('gejala')->nullable();
            $table->text('urutan_kejadian')->nullable();
            $table->text('parameter_terkait')->nullable();
            $table->text('analisa_penyebab')->nullable();
            $table->text('akibat')->nullable();
            $table->text('tindak_lanjut_pendek')->nullable();
            $table->text('tindak_lanjut_panjang')->nullable();
            $table->text('eviden')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_laporan_gangguans');
    }
};
