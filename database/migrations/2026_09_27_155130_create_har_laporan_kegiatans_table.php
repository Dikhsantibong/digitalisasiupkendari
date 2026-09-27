<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akses 2 — Pengusahaan: Laporan Kegiatan Pemeliharaan Mesin / Listrik &
     * Kontrol Pembangkit (FMKD-314-10.3.3-A3). One row per numbered entry of a
     * report month; an entry holds one or more machine groups (title, jenis
     * HAR and activity lines) and the shared result, material & keterangan.
     */
    public function up(): void
    {
        Schema::create('har_laporan_kegiatans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('activity_date')->nullable();
            $table->json('groups');
            $table->string('hasil_pekerjaan', 100)->nullable();
            $table->text('material_nama')->nullable();
            $table->text('material_no_part')->nullable();
            $table->text('jumlah')->nullable();
            $table->text('data')->nullable();
            $table->text('no_lh05')->nullable();
            $table->text('no_sr_ba')->nullable();
            $table->text('no_tug9')->nullable();
            $table->text('no_wo_spki')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'category', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_laporan_kegiatans');
    }
};
