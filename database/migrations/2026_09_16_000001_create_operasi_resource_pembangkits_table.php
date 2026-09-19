<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_resource_pembangkits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('tanggal');
            $table->decimal('stok_awal', 14, 2)->default(0);
            $table->decimal('pemakaian', 14, 2)->default(0);
            $table->decimal('pengiriman', 14, 2)->default(0);
            $table->decimal('stok_akhir', 14, 2)->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month', 'tanggal']);
            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_resource_pembangkits');
    }
};
