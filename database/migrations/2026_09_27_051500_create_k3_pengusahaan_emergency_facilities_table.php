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
        Schema::create('k3_pengusahaan_emergency_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('periode', 20)->default('M1'); // M1, M2, M3, M4, BULANAN
            $table->string('no_dokumen', 100)->nullable();
            $table->string('revisi', 50)->nullable();
            $table->string('tanggal_dokumen', 100)->nullable();
            $table->string('halaman', 50)->nullable();
            $table->string('grup', 100);
            $table->unsignedSmallInteger('no_urut');
            $table->string('nama_peralatan', 255);
            $table->string('jml_total', 100)->nullable();
            $table->string('jml_ready', 100)->nullable();
            $table->string('jml_not_ready', 100)->nullable();
            $table->string('persen_kesiapan', 50)->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'periode'], 'k3_pengusahaan_ef_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_emergency_facilities');
    }
};
