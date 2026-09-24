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
        Schema::create('k3_metode_pengujian_peralatans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_urut', 50)->nullable();
            $table->string('nama_peralatan', 255);
            $table->string('no_pengesahan', 255)->nullable();
            $table->string('nama_kategori_alat', 255)->nullable();
            $table->string('uji_visual', 100)->nullable();
            $table->string('uji_fungsi', 100)->nullable();
            $table->string('uji_beban', 100)->nullable();
            $table->string('uji_hydro', 100)->nullable();
            $table->string('ndt', 100)->nullable();
            $table->string('uji_ultrasonic_thickness', 100)->nullable();
            $table->string('uji_ketahanan', 100)->nullable();
            $table->string('sertifikasi_terakhir', 100)->nullable();
            $table->string('sertifikasi_ulang', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_metode_pengujian_peralatan_meta', function (Blueprint $table): void {
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
        Schema::dropIfExists('k3_metode_pengujian_peralatan_meta');
        Schema::dropIfExists('k3_metode_pengujian_peralatans');
    }
};
