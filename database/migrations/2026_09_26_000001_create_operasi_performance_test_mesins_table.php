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
        Schema::create('operasi_performance_test_mesins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('no_urut')->nullable();
            $table->string('nama_mesin', 255);
            $table->string('section', 255)->default('A. PEMBUATAN DATA TEKNIKS');
            $table->json('beban_50')->nullable();
            $table->json('beban_75')->nullable();
            $table->json('beban_100')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
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
        Schema::dropIfExists('operasi_performance_test_mesins');
    }
};
