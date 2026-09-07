<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the operasi-specific attributes the source Excel never modelled to the
 * existing master machine record. Values are left null: the operator completes
 * fuel type and lubricants per machine through the machine edit form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            $table->string('fuel_type')->nullable()->after('type');
        });

        Schema::create('machine_lubricant_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lubricant_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['machine_id', 'lubricant_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_lubricant_type');

        Schema::table('machines', function (Blueprint $table): void {
            $table->dropColumn('fuel_type');
        });
    }
};
