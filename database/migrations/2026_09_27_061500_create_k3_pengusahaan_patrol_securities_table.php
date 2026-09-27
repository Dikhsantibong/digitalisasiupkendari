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
        Schema::create('k3_pengusahaan_patrol_securities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->string('judul')->default('PATROL CHECK SECURITY');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month'], 'k3_peng_patrol_sec_unique');
        });

        Schema::create('k3_pengusahaan_patrol_security_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('k3_pengusahaan_patrol_securities')->cascadeOnDelete();
            $table->string('lokasi_kode');
            $table->string('lokasi_nama')->nullable();
            $table->json('scans')->nullable();
            $table->integer('total')->default(0);
            $table->decimal('persentase', 5, 2)->nullable()->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_patrol_security_items');
        Schema::dropIfExists('k3_pengusahaan_patrol_securities');
    }
};
