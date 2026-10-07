<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_stand_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('fuel_name', 50)->default('HSD');
            $table->unsignedTinyInteger('month');
            $table->smallInteger('year');
            $table->json('machine_parameters')->nullable();
            $table->json('readings')->nullable();
            $table->json('totals')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'fuel_name', 'month', 'year'], 'ops_stand_meter_uniq');
            $table->index(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_stand_meters');
    }
};
