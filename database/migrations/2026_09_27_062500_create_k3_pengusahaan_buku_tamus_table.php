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
        Schema::create('k3_pengusahaan_buku_tamus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-06.04');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 SEPTEMBER 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_peng_buku_tamu_unique');
        });

        Schema::create('k3_pengusahaan_buku_tamu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_buku_tamus')->cascadeOnDelete();
            $table->integer('no_urut')->default(1);
            $table->string('tanggal');
            $table->integer('jumlah_kehadiran_tamu')->default(0);
            $table->integer('tamu_pln')->default(0);
            $table->integer('instansi')->default(0);
            $table->integer('kontraktor')->default(0);
            $table->integer('lainnya')->default(0);
            $table->text('keterangan')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_buku_tamu_items');
        Schema::dropIfExists('k3_pengusahaan_buku_tamus');
    }
};
