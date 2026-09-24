<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulir Daily Meeting Pemeliharaan (Modul HAR): satu baris per meeting —
     * acara, tanggal, waktu, tempat, daftar hadir (JSON) dan foto eviden
     * (path di disk public, dicetak sebagai lembar kedua PDF).
     */
    public function up(): void
    {
        Schema::create('har_daily_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('tanggal');
            $table->string('acara', 255);
            $table->string('waktu', 50)->nullable();
            $table->string('tempat', 255)->nullable();
            $table->json('peserta');
            $table->json('eviden');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_daily_meetings');
    }
};
