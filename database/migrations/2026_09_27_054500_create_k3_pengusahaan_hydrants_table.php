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
        Schema::create('k3_pengusahaan_hydrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_dokumen')->default('SMT-FM-AK3-12.06');
            $table->string('revisi')->default('00');
            $table->string('tanggal_dokumen')->default('23 SEPTEMBER 2019');
            $table->string('tanggal_periksa')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_hydrant_unit_year_month_unique');
        });

        Schema::create('k3_pengusahaan_hydrant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_hydrants')->cascadeOnDelete();
            $table->unsignedInteger('no_urut')->default(1);
            $table->string('lokasi');
            $table->string('tanggal_periksa')->nullable();
            $table->string('hose')->default('Normal');
            $table->string('nozzle')->default('Normal');
            $table->string('box_hydrant')->default('Baik');
            $table->string('kondisi_tekanan_air')->default('Baik');
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
        Schema::dropIfExists('k3_pengusahaan_hydrant_items');
        Schema::dropIfExists('k3_pengusahaan_hydrants');
    }
};
