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
        Schema::create('k3_pengusahaan_inventaris_apds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-01.01');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 September 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_inventaris_apd_unit_year_month_unique');
        });

        Schema::create('k3_pengusahaan_inventaris_apd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_inventaris_apds')->cascadeOnDelete();
            $table->string('kategori')->default('I');
            $table->unsignedInteger('no_grup')->default(1);
            $table->string('nama_grup')->nullable();
            $table->string('nama_alat');
            $table->integer('jumlah')->nullable();
            $table->string('lokasi')->nullable();
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
        Schema::dropIfExists('k3_pengusahaan_inventaris_apd_items');
        Schema::dropIfExists('k3_pengusahaan_inventaris_apds');
    }
};
