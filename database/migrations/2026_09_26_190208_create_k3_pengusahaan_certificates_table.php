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
        Schema::create('k3_pengusahaan_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('equipment_category_id')->nullable()->constrained('equipment_categories')->nullOnDelete();
            $table->string('jenis');
            $table->string('kapasitas')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('merk_manufacture')->nullable();
            $table->string('no_seri')->nullable();
            $table->string('regulasi')->nullable();
            $table->string('ijin_awal_nomor')->nullable();
            $table->date('ijin_awal_tanggal')->nullable();
            $table->string('uji_terakhir_nomor')->nullable();
            $table->date('uji_terakhir_tanggal')->nullable();
            $table->date('uji_ulang_tanggal')->nullable();
            $table->string('batasan_uji')->nullable();
            $table->unsignedSmallInteger('masa_berlaku_tahun')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_certificates');
    }
};
