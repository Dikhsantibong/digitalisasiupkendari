<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal Program 5S 5R Pemeliharaan (Modul HAR): per unit + bulan + minggu,
     * satu baris per program (Ringkas, Rapi, Resik, Rawat, Rajin) dan foto
     * eviden per minggu. Hanya minggu yang benar-benar diisi yang disimpan.
     */
    public function up(): void
    {
        Schema::create('har_program_5s5r_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('minggu');
            $table->string('program', 20);
            $table->text('detail')->nullable();
            $table->string('pic', 150)->nullable();
            $table->string('kondisi_awal', 50)->nullable();
            $table->boolean('membersihkan')->default(false);
            $table->boolean('merapikan')->default(false);
            $table->boolean('membuang_sampah')->default(false);
            $table->boolean('mengecat')->default(false);
            $table->boolean('lainnya')->default(false);
            $table->string('progres', 10)->nullable();
            $table->string('kondisi_akhir', 50)->nullable();
            $table->unsignedSmallInteger('jumlah')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month', 'minggu', 'program'], 'har_5s5r_items_period_unique');
        });

        Schema::create('har_program_5s5r_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('minggu');
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'minggu'], 'har_5s5r_evidences_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_program_5s5r_evidences');
        Schema::dropIfExists('har_program_5s5r_items');
    }
};
