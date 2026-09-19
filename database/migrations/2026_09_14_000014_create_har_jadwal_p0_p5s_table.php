<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_jadwal_p0_p5s', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->json('rencana')->nullable();
            $table->json('realisasi')->nullable();
            $table->json('durasi')->nullable();
            $table->text('operating_hours')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'machine_id', 'year', 'month'], 'har_jadwal_p0_p5_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_jadwal_p0_p5s');
    }
};
