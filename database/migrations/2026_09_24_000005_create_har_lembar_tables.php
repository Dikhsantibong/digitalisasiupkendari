<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lembar matriks Modul HAR yang didefinisikan di App\Support\HarLembar
     * (Inventarisasi Tools & Material, Pemeriksaan Instalasi Blackstart, Patrol
     * Check Pemeliharaan): satu baris per item; `fields` = kolom teks, `cells`
     * = {baris (main|rencana|realisasi): {kolom: kode}}. Lembar tahunan
     * disimpan dengan month = 0; lembar per mesin memakai `subject` = id mesin.
     */
    public function up(): void
    {
        Schema::create('har_lembar_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('lembar', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('subject', 30)->default('');
            $table->string('section', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('fields');
            $table->json('cells');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'lembar', 'year', 'month', 'subject'], 'har_lembar_rows_period_index');
        });

        Schema::create('har_lembar_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('lembar', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('subject', 30)->default('');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'lembar', 'year', 'month', 'subject'], 'har_lembar_meta_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_lembar_meta');
        Schema::dropIfExists('har_lembar_rows');
    }
};
