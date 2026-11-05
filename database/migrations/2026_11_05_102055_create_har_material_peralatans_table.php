<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input Pemeliharaan "Laporan Material dan Peralatan" — the same columns as
     * operasi_material_peralatans: the Koordinator Pemeliharaan lists the items
     * and their stock, Harmes / Harlist check each item's kelengkapan.
     *
     * The foreign keys are named explicitly: operasi_material_peralatans was
     * once har_material_peralatans and still owns the default constraint names.
     */
    public function up(): void
    {
        Schema::create('har_material_peralatans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained(indexName: 'har_mp_unit_id_foreign')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kategori', 50)->default('MATERIAL');
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
            $table->string('kelengkapan', 20)->nullable();
            $table->text('uraian_kondisi')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users', indexName: 'har_mp_checked_by_foreign')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users', indexName: 'har_mp_input_by_foreign')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'kategori'], 'har_mp_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_material_peralatans');
    }
};
