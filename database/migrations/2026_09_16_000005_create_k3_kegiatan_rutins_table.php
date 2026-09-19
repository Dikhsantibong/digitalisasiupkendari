<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_kegiatan_rutins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('grup', 20)->default('harian'); // harian | mingguan | bulanan
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('kegiatan');
            $table->unsignedSmallInteger('target')->default(22);
            $table->json('jadwal')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'grup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_kegiatan_rutins');
    }
};
