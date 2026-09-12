<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global catalogue of attendance / shift codes (P, S, M, OFF, C, SKT, I, A).
 * `type` separates worked shifts from absences so the Excel ambiguity between
 * "S" (Sore, a shift) and "SAKIT" (an absence, code SKT) never recurs.
 * `hitung_hadir` marks the codes that count as present for the attendance %.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('type')->default('shift');
            $table->string('jam_mulai')->nullable();
            $table->string('jam_selesai')->nullable();
            $table->boolean('hitung_hadir')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_codes');
    }
};
