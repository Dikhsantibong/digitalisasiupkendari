<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('har_jadwal_pembuatan_iks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('instruksi_kerja');
            $table->string('pic_pembuat')->nullable();
            $table->json('rencana_bulan')->nullable();
            $table->json('realisasi_bulan')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('har_jadwal_pembuatan_iks');
    }
};
