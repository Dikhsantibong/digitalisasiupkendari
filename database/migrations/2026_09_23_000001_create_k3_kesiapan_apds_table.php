<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Form Inspeksi K3 / Kesiapan APD (Modul K3)
     */
    public function up(): void
    {
        Schema::create('k3_kesiapan_apds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kelompok')->default('I. Alat Pelindung Diri (APD)');
            $table->string('no_urut', 20)->nullable();
            $table->string('inspeksi');
            $table->string('apd_jumlah', 50)->nullable();
            $table->string('kelayakan_apd', 50)->nullable();
            $table->string('peralatan_jumlah', 50)->nullable();
            $table->string('peralatan_kelayakan', 50)->nullable();
            $table->string('sop_pnp', 50)->nullable();
            $table->string('sop_vendor', 50)->nullable();
            $table->string('p3k_ada', 50)->nullable();
            $table->string('p3k_memenuhi', 50)->nullable();
            $table->string('cara_kerja', 50)->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_kesiapan_apd_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_kesiapan_apd_meta');
        Schema::dropIfExists('k3_kesiapan_apds');
    }
};
