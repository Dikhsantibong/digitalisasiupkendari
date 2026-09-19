<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional OPERASI schedule registers: 5S5R, Meeting Shift, Inventarisasi
 * Tools & Material (daily matrices), plus Pembuatan IK & Data Teknis (yearly
 * 12-month matrices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_5s5r_jadwals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('pelaksana');
            $table->json('rencana')->nullable();
            $table->json('realisasi')->nullable();
            $table->unsignedInteger('target')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('operasi_meeting_shift_jadwals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('label');
            $table->string('row_type', 20)->default('mark'); // mark | shift
            $table->json('days')->nullable();
            $table->unsignedInteger('target')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('operasi_inventaris_jadwals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('uraian');
            $table->string('shift')->nullable();
            $table->json('rencana')->nullable();
            $table->json('realisasi')->nullable();
            $table->unsignedInteger('target')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('operasi_pembuatan_iks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('section')->default('PEMBUATAN INSTRUKSI KERJA');
            $table->string('nama')->nullable();
            $table->string('pic')->nullable();
            $table->json('months')->nullable(); // { "1".."12": "1" }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year']);
        });

        Schema::create('operasi_data_tekniss', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('section')->default('PEMBUATAN DATA TEKNIS');
            $table->string('nama')->nullable();
            $table->string('pic')->nullable();
            $table->json('months')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_data_tekniss');
        Schema::dropIfExists('operasi_pembuatan_iks');
        Schema::dropIfExists('operasi_inventaris_jadwals');
        Schema::dropIfExists('operasi_meeting_shift_jadwals');
        Schema::dropIfExists('operasi_5s5r_jadwals');
    }
};
