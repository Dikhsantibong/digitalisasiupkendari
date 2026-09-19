<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_unsafe_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('periode', 50)->default('MINGGU KE - 1');
            $table->string('kategori', 50)->default('UNSAFE CONDITION');
            $table->text('temuan');
            $table->string('kondisi', 255)->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->string('keterangan', 50)->default('close');
            $table->string('foto_sebelum')->nullable();
            $table->string('foto_sesudah')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_unsafe_conditions');
    }
};
