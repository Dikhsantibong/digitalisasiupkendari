<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_material_peralatans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kategori', 50)->default('MATERIAL'); // PERALATAN / MATERIAL
            $table->string('kode_material', 100)->nullable();
            $table->string('stok_code', 100)->nullable();
            $table->string('nama_item', 255);
            $table->decimal('stok_awal', 12, 2)->default(0);
            $table->decimal('masuk', 12, 2)->default(0);
            $table->decimal('keluar', 12, 2)->default(0);
            $table->decimal('stok_akhir', 12, 2)->default(0);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_satuan', 14, 2)->default(0);
            $table->decimal('pemakaian_rata_rata', 10, 2)->default(0);
            $table->decimal('safety_stock', 10, 2)->default(0);
            $table->decimal('ilt', 8, 2)->default(0);
            $table->decimal('rop', 8, 2)->default(0);
            $table->decimal('roq', 8, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_material_peralatans');
    }
};
