<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The contract package a unit falls under (11 KIT or 6 KIT), used later to
     * split responsibilities. Null until it is set in Master Unit.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->string('contract', 10)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('contract');
        });
    }
};
