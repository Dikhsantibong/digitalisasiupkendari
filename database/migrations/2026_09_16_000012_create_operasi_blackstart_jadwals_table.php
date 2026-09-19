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
        Schema::create('operasi_blackstart_jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('uraian');
            $table->string('pic')->nullable();
            $table->json('rencana')->nullable(); // Array of week keys e.g. ["1-1", "3-2"]
            $table->json('realisasi')->nullable(); // Array of week keys e.g. ["1-1", "3-2"]
            $table->string('keterangan', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
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
        Schema::dropIfExists('operasi_blackstart_jadwals');
    }
};
