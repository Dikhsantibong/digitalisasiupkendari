<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logbook Mutasi Harian Tim Pemeliharaan (Formulir HAR): satu logbook per
     * unit per tanggal — absensi, kesiapan APD, job harian rutin & non rutin,
     * dan kondisi K3 (unsafe action & condition), masing-masing JSON.
     */
    public function up(): void
    {
        Schema::create('har_logbook_mutasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('tanggal');
            $table->json('absensi');
            $table->json('apd');
            $table->json('rutin');
            $table->json('non_rutin');
            $table->json('kondisi_k3');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'tanggal']);
            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_logbook_mutasis');
    }
};
