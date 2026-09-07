<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * End-of-period physical stock opname for fuel tanks and lubricant drums, the
 * manual counterpart to the administrative stock in the Berita Acara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_stock_takes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->constrained()->cascadeOnDelete();
            $table->string('item_type');
            $table->foreignId('tank_id')->nullable()->constrained('fuel_tanks')->cascadeOnDelete();
            $table->foreignId('lubricant_type_id')->nullable()->constrained('lubricant_types')->cascadeOnDelete();
            $table->decimal('physical_qty_liter', 18, 2)->nullable();
            $table->decimal('physical_drum', 12, 2)->nullable();
            $table->decimal('physical_cm', 12, 2)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'report_period_id', 'item_type'], 'physical_stock_takes_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_stock_takes');
    }
};
