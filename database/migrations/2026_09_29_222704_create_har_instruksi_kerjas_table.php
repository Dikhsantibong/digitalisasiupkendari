<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instruksi Kerja (IK) Pemeliharaan: the unit's library of IK documents
     * (e.g. PM 1500 jam mesin Cummins KTA 50). `sections` holds the numbered
     * parts and sub-headings with their points as JSON.
     */
    public function up(): void
    {
        Schema::create('har_instruksi_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('kop')->default('JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI');
            $table->text('judul');
            $table->string('mesin', 100)->nullable();
            $table->string('no_dokumen', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('revisi', 20)->nullable();
            $table->json('sections');
            $table->string('dibuat_jabatan', 100)->nullable();
            $table->string('dibuat_nama', 150)->nullable();
            $table->string('disetujui_jabatan', 100)->nullable();
            $table->string('disetujui_nama', 150)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_instruksi_kerjas');
    }
};
