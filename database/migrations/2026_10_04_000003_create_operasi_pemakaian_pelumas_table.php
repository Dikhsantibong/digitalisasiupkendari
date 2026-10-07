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
        Schema::create('operasi_pemakaian_pelumas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('grand_total_liter', 14, 2)->default(0);
            $table->json('raw_readings')->nullable();
            $table->json('totals_by_lubricant')->nullable();
            $table->json('totals_by_machine')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'month', 'year']);
        });

        Schema::create('operasi_pemakaian_pelumas_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pemakaian_pelumas_id')->constrained('operasi_pemakaian_pelumas')->cascadeOnDelete();
            $table->foreignId('lubricant_type_id')->nullable()->constrained('lubricant_types')->nullOnDelete();
            $table->string('lubricant_name');
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('machine_name');
            $table->json('daily_readings')->nullable();
            $table->decimal('subtotal_p1', 12, 2)->default(0);
            $table->decimal('subtotal_p2', 12, 2)->default(0);
            $table->decimal('subtotal_p3', 12, 2)->default(0);
            $table->decimal('total_liter', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['pemakaian_pelumas_id', 'lubricant_type_id'], 'idx_opp_item_lubricant');
            $table->index(['pemakaian_pelumas_id', 'machine_id'], 'idx_opp_item_machine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operasi_pemakaian_pelumas_items');
        Schema::dropIfExists('operasi_pemakaian_pelumas');
    }
};
