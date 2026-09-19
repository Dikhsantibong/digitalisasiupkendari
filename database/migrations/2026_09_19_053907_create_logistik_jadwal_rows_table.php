<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rows of the Logistik & Gudang sheets (jadwal & grid-shaped inputs) — see
 * App\Support\LogistikJadwal. `days` maps a column (day, month, slot or
 * level) to its code; `evidence` holds photo paths on the public disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistik_jadwal_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('jadwal', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('section', 30)->nullable();
            $table->string('nama')->nullable();
            $table->string('pic')->nullable();
            $table->json('days')->nullable();
            $table->unsignedSmallInteger('target')->nullable();
            $table->string('keterangan')->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'jadwal', 'year', 'month'], 'logistik_jadwal_rows_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistik_jadwal_rows');
    }
};
