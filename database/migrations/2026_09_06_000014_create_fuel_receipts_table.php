<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register of fuel deliveries from suppliers (manual input).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('fuel_type');
            $table->string('supplier')->nullable();
            $table->string('do_number')->nullable();
            $table->date('unloading_date')->nullable();
            $table->decimal('volume_liter', 18, 2);
            $table->decimal('calorie_value', 12, 2)->nullable();
            $table->decimal('price_per_liter', 14, 2)->nullable();
            $table->decimal('transport_cost', 16, 2)->nullable();
            $table->decimal('surveyor_cost', 16, 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'fuel_type', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_receipts');
    }
};
