<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_apd_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('grup')->default('Peralatan Keselamatan Kerja Utama');
            $table->string('subkategori')->nullable();
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('nama');
            $table->unsignedInteger('jumlah')->default(0);
            $table->string('satuan')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('foto')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'grup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_apd_inventories');
    }
};
