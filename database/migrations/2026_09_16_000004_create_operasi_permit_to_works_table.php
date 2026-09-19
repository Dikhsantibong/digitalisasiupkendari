<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_permit_to_works', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('no_urut')->default(1);
            $table->text('uraian')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('status', 20)->default('open'); // 'open' or 'close'
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_permit_to_works');
    }
};
