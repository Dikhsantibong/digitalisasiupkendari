<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shift group (regu A/B/C) an employee rotates in. Nullable: non-shift
 * employees have none, and existing rows keep working untouched. Used by the
 * Absensi layer to group the schedule grid and drive shift-pattern generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('regu')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('regu');
        });
    }
};
