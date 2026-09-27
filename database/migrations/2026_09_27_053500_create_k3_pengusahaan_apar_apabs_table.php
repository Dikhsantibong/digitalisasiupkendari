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
        Schema::create('k3_pengusahaan_apar_apabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-12.03');
            $table->string('revisi')->default('01');
            $table->string('tanggal_dokumen')->default('23 SEPTEMBER 2019');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_apar_apab_unit_year_month_unique');
        });

        Schema::create('k3_pengusahaan_apar_apab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_apar_apabs')->cascadeOnDelete();
            $table->unsignedInteger('no_urut')->default(1);
            $table->string('no_rfid')->nullable();
            $table->string('lokasi');
            $table->string('tgl_periksa')->nullable();
            $table->string('merk_apar')->nullable();
            $table->string('jenis_apar')->nullable();
            $table->decimal('berat_kg', 8, 2)->nullable();
            $table->string('kondisi_tabung')->nullable()->default('baik');
            $table->string('kondisi_nozzle_selang')->nullable()->default('baik');
            $table->string('indikator_tekanan')->nullable()->default('ok');
            $table->string('kondisi_pin_segel')->nullable()->default('baik');
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
        Schema::dropIfExists('k3_pengusahaan_apar_apab_items');
        Schema::dropIfExists('k3_pengusahaan_apar_apabs');
    }
};
