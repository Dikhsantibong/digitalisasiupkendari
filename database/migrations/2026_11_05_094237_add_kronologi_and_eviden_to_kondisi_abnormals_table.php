<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan Kondisi Abnormal & Gangguan: the kronologi of a gangguan and one
     * eviden photo (public disk path) per report, abnormal or gangguan.
     */
    public function up(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->text('kronologi')->nullable()->after('parameter_terkait');
            $table->string('eviden')->nullable()->after('dampak_pembangkit');
        });
    }

    public function down(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->dropColumn(['kronologi', 'eviden']);
        });
    }
};
