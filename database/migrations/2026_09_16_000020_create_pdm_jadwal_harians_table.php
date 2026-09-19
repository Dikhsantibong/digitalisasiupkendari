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
        Schema::create('pdm_jadwal_harians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kategori')->default('I. MESIN');
            $table->boolean('is_category_header')->default(false);
            $table->string('no_urut', 20)->nullable();
            $table->string('kegiatan');
            $table->unsignedSmallInteger('target')->default(23);
            $table->unsignedSmallInteger('rencana_count')->default(23);
            $table->unsignedSmallInteger('realisasi_count')->default(0);
            $table->json('jadwal')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('pdm_jadwal_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('type', 50)->default('harian');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('doc_number')->nullable();
            $table->string('revision')->default('00');
            $table->string('effective_date')->nullable();
            $table->string('page_number')->default('1 dari 1');
            $table->string('disetujui_nama')->nullable();
            $table->string('disetujui_jabatan')->nullable();
            $table->string('dibuat_nama')->nullable();
            $table->string('dibuat_jabatan')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'type', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdm_jadwal_meta');
        Schema::dropIfExists('pdm_jadwal_harians');
    }
};
