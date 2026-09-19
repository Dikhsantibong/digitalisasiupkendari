<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kondisi_abnormals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('no_urut')->default(1);
            $table->text('uraian_kondisi')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedTinyInteger('is_abnormal')->default(0);
            $table->decimal('durasi_abnormal', 8, 2)->default(0);
            $table->unsignedTinyInteger('is_gangguan')->default(0);
            $table->decimal('durasi_gangguan', 8, 2)->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kondisi_abnormals');
    }
};
