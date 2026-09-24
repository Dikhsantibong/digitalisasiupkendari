<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel input bebas Modul HAR yang didefinisikan di App\Support\HarTabel
     * (Rekap Laporan Gangguan, Laporan Kondisi Abnormal & Gangguan): satu baris
     * per kejadian per unit + bulan; `data` = nilai kolom sesuai definisinya.
     * Tabel lama har_laporan_gangguans (Form LH-05) sengaja tidak dihapus.
     */
    public function up(): void
    {
        Schema::create('har_tabel_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('tabel', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('data');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'tabel', 'year', 'month'], 'har_tabel_rows_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_tabel_rows');
    }
};
