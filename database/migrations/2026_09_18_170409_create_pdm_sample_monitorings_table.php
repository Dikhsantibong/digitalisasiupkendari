<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input PdM "Form Monitoring Pemeriksaan & Pengiriman Sample": one header
     * per unit & period (lokasi, PIC, target rekap per jenis sample, catatan).
     */
    public function up(): void
    {
        Schema::create('pdm_sample_monitorings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('lokasi')->nullable();
            $table->string('pic_monitoring')->nullable();
            $table->json('rekap_targets')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_sample_monitorings');
    }
};
