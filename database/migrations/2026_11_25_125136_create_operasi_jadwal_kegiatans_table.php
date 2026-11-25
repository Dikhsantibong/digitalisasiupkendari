<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal Kegiatan Operasi (user, 2026-11-25): the Operasi divisi's monthly
 * activities, grouped harian / mingguan / bulanan / non_rutin like the other
 * divisi (same columns as k3_kegiatan_rutins).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_jadwal_kegiatans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('grup', 20)->default('harian'); // harian | mingguan | bulanan | non_rutin
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('kegiatan');
            $table->unsignedSmallInteger('target')->default(0);
            $table->json('jadwal')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'grup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_jadwal_kegiatans');
    }
};
