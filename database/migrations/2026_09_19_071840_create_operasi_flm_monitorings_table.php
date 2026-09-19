<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input Operasi "Monitoring FLM": per unit & month the numbered findings of
 * the first line maintenance — mesin/peralatan, tanggal, masalah awal, the
 * kondisi awal actions taken, kondisi akhir, catatan and status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_flm_monitorings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut');
            $table->string('mesin')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('masalah')->nullable();
            $table->json('kondisi_awal')->nullable();
            $table->string('kondisi_akhir')->nullable();
            $table->string('catatan')->nullable();
            $table->string('status', 10)->default('open');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_flm_monitorings');
    }
};
