<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal First Line Maintenance (FLM) Operator — matriks rencana/realisasi FLM
 * per hari, dikelompokkan RUTIN / NON RUTIN dengan baris per shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_flm_jadwals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('section', 20)->default('rutin'); // rutin | non_rutin
            $table->string('shift', 30)->nullable();          // JADWAL | A | B | C | D
            $table->string('label')->default('REALISASI');    // REALISASI | Waktu tentatif | 08:00 sd 15:00
            $table->string('row_type', 20)->default('mark');  // mark | minutes | shift
            $table->json('days')->nullable();                 // { "1": "1"|"60"|"A", ... }
            $table->unsignedInteger('target')->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_flm_jadwals');
    }
};
