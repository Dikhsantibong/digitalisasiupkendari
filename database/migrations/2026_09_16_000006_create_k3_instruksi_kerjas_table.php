<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_instruksi_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('kegiatan');
            $table->string('waktu')->nullable();
            $table->string('pic')->nullable();
            $table->string('peserta')->nullable();
            $table->json('rencana')->nullable();   // list<int> hari rencana (RENC)
            $table->json('realisasi')->nullable(); // list<int> hari realisasi (REAL)
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_instruksi_kerjas');
    }
};
