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
        Schema::create('k3_pengusahaan_inspeksi_tempat_kerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-12-01');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 September 2019');
            $table->string('tanggal_inspeksi')->nullable();
            $table->string('departemen')->default('PLN NUSANTARA POWER');
            $table->string('lokasi')->nullable();
            $table->string('tim_inspektur')->nullable();
            $table->string('ketua_tim')->nullable();
            $table->string('inspektur')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_peng_inspeksi_tk_unique');
        });

        Schema::create('k3_pengusahaan_inspeksi_tempat_kerja_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_inspeksi_tempat_kerjas')->cascadeOnDelete();
            $table->string('category');
            $table->integer('no_urut')->default(1);
            $table->text('item');
            $table->string('status')->nullable();
            $table->text('comment')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_inspeksi_tempat_kerja_items');
        Schema::dropIfExists('k3_pengusahaan_inspeksi_tempat_kerjas');
    }
};
