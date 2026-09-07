<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register of lubricant deliveries (manual input).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lubricant_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('lubricant_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('volume', 14, 2);
            $table->string('do_number')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'lubricant_type_id', 'report_date'], 'lubricant_receipts_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lubricant_receipts');
    }
};
