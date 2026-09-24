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
        Schema::create('k3_kondisi_k3s', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('no_urut', 50)->nullable();
            $table->string('periode', 100)->nullable();
            $table->string('kategori', 100)->default('Unsafe Action');
            $table->text('temuan');
            $table->text('kondisi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('eviden_sebelum', 1000)->nullable();
            $table->string('eviden_sesudah', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_kondisi_k3_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_kondisi_k3_meta');
        Schema::dropIfExists('k3_kondisi_k3s');
    }
};
