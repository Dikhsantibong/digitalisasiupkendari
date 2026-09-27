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
        Schema::create('k3_pengusahaan_apel_keamanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-13.13');
            $table->string('revisi')->default('00');
            $table->string('tanggal_dokumen')->default('23 SEPTEMBER 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_apel_keamanan_unit_year_month_unique');
        });

        Schema::create('k3_pengusahaan_apel_keamanan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_apel_keamanans')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedTinyInteger('hari_ke');
            $table->string('tim_regu')->default('A');
            $table->string('shift')->default('Pagi');
            $table->string('waktu_apel')->default('08:00');
            $table->unsignedInteger('jumlah_personil')->default(2);
            $table->string('kelengkapan_atribut')->default('Lengkap');
            $table->string('paraf_komandan_regu')->nullable();
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
        Schema::dropIfExists('k3_pengusahaan_apel_keamanan_items');
        Schema::dropIfExists('k3_pengusahaan_apel_keamanans');
    }
};
