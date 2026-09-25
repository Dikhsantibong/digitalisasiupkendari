<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patrol Check Parameter Mesin (Input HAR): pembacaan parameter
     * mesin per tanggal — engine, generator, trafo, tegangan baterai — satu
     * baris per mesin per hari, nilai-nilainya JSON (App\Support\HarPatrolCheckParameter).
     */
    public function up(): void
    {
        Schema::create('har_patrol_check_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->json('values');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['machine_id', 'year', 'month', 'day']);
            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_patrol_check_readings');
    }
};
