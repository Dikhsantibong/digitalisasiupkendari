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
        Schema::create('k3_pengusahaan_kecelakaan_masyarakats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('is_nihil')->default(true);
            $table->string('lampiran_teks', 255)->default('Lampiran 1 Keputusan Direksi PT PLN (Persero)');
            $table->string('nomor_keputusan', 100)->default('Nomor :0252.P/DIR/2016');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_pengusahaan_kec_masy_uniq');
        });

        Schema::create('k3_pengusahaan_kecelakaan_masyarakat_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_kecelakaan_masyarakats')->cascadeOnDelete();
            $table->unsignedSmallInteger('no_urut')->default(1);
            $table->string('tanggal_kejadian', 50)->nullable();
            $table->string('fungsi', 100)->nullable();
            $table->string('lokasi_kejadian', 255)->nullable();
            $table->unsignedInteger('luka_ringan')->default(0);
            $table->unsignedInteger('luka_berat')->default(0);
            $table->unsignedInteger('meninggal')->default(0);
            $table->decimal('kerugian_material', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_kecelakaan_masyarakat_items');
        Schema::dropIfExists('k3_pengusahaan_kecelakaan_masyarakats');
    }
};
