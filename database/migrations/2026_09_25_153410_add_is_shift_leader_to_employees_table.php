<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the Leader Shift of a regu (one per regu per unit, validated in
 * EmployeeRequest). The other members of the regu are regular operators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->boolean('is_shift_leader')->default(false)->after('regu');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('is_shift_leader');
        });
    }
};
