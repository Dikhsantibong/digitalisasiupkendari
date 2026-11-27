<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('operasi_commissioning_tests');

        Schema::create('operasi_commissioning_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('nama_mesin');
            $table->string('section')->default('A. PEMBUATAN DATA TEKNIKS');
            $table->json('beban_50')->nullable();
            $table->json('beban_75')->nullable();
            $table->json('beban_100')->nullable();
            $table->json('realisasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_commissioning_tests');

        Schema::create('operasi_commissioning_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('section')->default('PERSIAPAN');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('kegiatan');
            $table->string('status')->nullable();
            $table->string('pic')->nullable();
            $table->string('paraf')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }
};
