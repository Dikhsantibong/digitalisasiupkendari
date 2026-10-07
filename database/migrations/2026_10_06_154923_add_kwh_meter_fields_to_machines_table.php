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
        Schema::table('machines', function (Blueprint $table): void {
            $table->string('merk', 60)->nullable()->after('name');
            $table->decimal('kwh_faktor_kali_produksi', 14, 4)->default(1)->after('capacity_kw');
            $table->decimal('kwh_faktor_kali_ps', 14, 4)->default(1)->after('kwh_faktor_kali_produksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            $table->dropColumn(['merk', 'kwh_faktor_kali_produksi', 'kwh_faktor_kali_ps']);
        });
    }
};
