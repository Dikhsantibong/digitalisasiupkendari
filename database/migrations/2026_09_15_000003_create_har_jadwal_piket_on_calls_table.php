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
        Schema::create('har_jadwal_piket_on_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('nama');
            $table->string('no_hp')->nullable();
            $table->string('kategori')->default('HARMES'); // HARMES, HARLIS, etc.
            $table->unsignedInteger('target')->default(15);
            $table->json('piket')->nullable();
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
        Schema::dropIfExists('har_jadwal_piket_on_calls');
    }
};
