<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_jadwal_on_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('nama');
            $table->string('kode_prk')->nullable();
            $table->string('jabatan')->nullable();
            $table->json('schedule')->nullable(); // map of "YYYY-MM-DD" => shift code
            $table->unsignedInteger('total')->default(0);
            $table->decimal('nilai', 12, 2)->default(100000);
            $table->decimal('rupiah', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_jadwal_on_calls');
    }
};
