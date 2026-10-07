<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The engine (penggerak) and generator data of the Daftar Inventarisasi Mesin.
     */
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            $table->string('engine_hp', 20)->nullable()->after('serial_number');
            $table->unsignedInteger('engine_rpm')->nullable()->after('engine_hp');
            $table->unsignedSmallInteger('tahun_pembuatan')->nullable()->after('engine_rpm');
            $table->string('generator_merk', 80)->nullable()->after('tahun_pembuatan');
            $table->string('generator_type', 80)->nullable()->after('generator_merk');
            $table->string('generator_serial_number', 80)->nullable()->after('generator_type');
            $table->unsignedInteger('generator_volt')->nullable()->after('generator_serial_number');
            $table->decimal('generator_kva', 12, 2)->nullable()->after('generator_volt');
            $table->decimal('generator_cos_phi', 4, 2)->nullable()->after('generator_kva');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            $table->dropColumn(['engine_hp', 'engine_rpm', 'tahun_pembuatan', 'generator_merk', 'generator_type', 'generator_serial_number', 'generator_volt', 'generator_kva', 'generator_cos_phi']);
        });
    }
};
