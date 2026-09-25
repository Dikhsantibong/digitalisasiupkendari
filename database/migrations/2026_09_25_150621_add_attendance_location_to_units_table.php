<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Office coordinates and check-in radius per unit, used to validate that an
 * absen masuk/pulang happens on site. Set by Super Admin (menu Lokasi Absensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('attendance_radius_m')->default(300)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'attendance_radius_m']);
        });
    }
};
