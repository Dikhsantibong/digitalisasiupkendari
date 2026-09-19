<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input PdM "Laporan Permit to Work (PTW) Pembangkit": one numbered row per
     * permit per unit & period with its open/close status.
     */
    public function up(): void
    {
        Schema::create('pdm_permit_to_works', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut');
            $table->string('uraian')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('status', 10)->default('open');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month', 'no_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_permit_to_works');
    }
};
