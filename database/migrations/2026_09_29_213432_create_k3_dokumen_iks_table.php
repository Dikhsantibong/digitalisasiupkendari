<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen Instruksi Kerja (IK) K3 Lingkungan Pembangkit: one document per
     * row, grouped by unit and report period (month/year). `sections` holds the
     * numbered parts (ALAT, BAHAN, REFERENSI, LANGKAH PELAKSANAAN …) as JSON.
     */
    public function up(): void
    {
        Schema::create('k3_dokumen_iks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('sistem', 60)->default('SMK3 LEVEL 3');
            $table->string('judul');
            $table->string('no_dokumen', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('revisi', 20)->nullable();
            $table->string('halaman', 20)->nullable();
            $table->json('sections');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_dokumen_iks');
    }
};
