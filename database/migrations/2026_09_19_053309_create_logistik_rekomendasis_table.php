<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input Logistik "Rekomendasi Logistik & Gudang": numbered recommendations per
 * unit & month (uraian, kondisi existing, tindak lanjut, keterangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistik_rekomendasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut');
            $table->string('uraian')->nullable();
            $table->text('kondisi_existing')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistik_rekomendasis');
    }
};
