<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit master of fuel storage tanks used for physical stock takes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_tanks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('fuel_type');
            $table->decimal('capacity_liter', 18, 2)->nullable();
            $table->boolean('is_daily_tank')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'fuel_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_tanks');
    }
};
