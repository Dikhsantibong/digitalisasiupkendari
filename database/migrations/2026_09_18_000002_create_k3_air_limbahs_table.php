<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_air_limbahs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('area_penyiraman');
            $table->date('tanggal')->nullable();
            $table->string('waktu_penyiraman')->nullable(); // e.g. "16.00 WITA"
            $table->string('metode_pemanfaatan')->nullable()->default('Penyiraman Tanaman');
            $table->decimal('debit_awal', 10, 2)->default(0);
            $table->decimal('debit_akhir', 10, 2)->default(0);
            $table->decimal('debit_jumlah', 10, 2)->default(0);
            $table->string('frekuensi')->nullable()->default('1');
            $table->string('pic')->nullable()->default('K3L');
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_air_limbahs');
    }
};
