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
        Schema::create('k3_pengusahaan_time_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('no_urut')->nullable();
            $table->string('uraian_pelaporan');
            $table->string('pic')->nullable();
            $table->json('rencana')->nullable();
            $table->json('realisasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k3_pengusahaan_time_frames');
    }
};
