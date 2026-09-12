<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly maintenance cost accumulation per unit. By default costs are summed
 * from work_orders; these manual totals act as an override when `use_manual` is
 * set (the Excel fills them by hand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('service_cost', 15, 2)->nullable();
            $table->decimal('material_cost', 15, 2)->nullable();
            $table->boolean('use_manual')->default(false);
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_costs');
    }
};
