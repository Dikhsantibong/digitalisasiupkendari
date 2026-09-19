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
        Schema::create('har_jadwal_data_tekniss', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->string('section')->default('PEMBUATAN DATA TEKNIS');
            $table->smallInteger('no_urut')->default(1);
            $table->string('data_teknis');
            $table->string('pic_pembuat')->nullable()->default('Koord Operasi');
            $table->json('bulan')->nullable(); // Array of month numbers (1-12) where scheduled
            $table->string('keterangan', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('har_jadwal_data_tekniss');
    }
};
