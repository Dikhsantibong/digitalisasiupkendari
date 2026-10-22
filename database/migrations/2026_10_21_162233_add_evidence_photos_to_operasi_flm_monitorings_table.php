<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eviden photos of a Monitoring FLM finding: the kondisi awal (before) and
     * the kondisi akhir (after), as paths on the public disk.
     */
    public function up(): void
    {
        Schema::table('operasi_flm_monitorings', function (Blueprint $table): void {
            $table->string('foto_awal')->nullable()->after('kondisi_akhir');
            $table->string('foto_akhir')->nullable()->after('foto_awal');
        });
    }

    public function down(): void
    {
        Schema::table('operasi_flm_monitorings', function (Blueprint $table): void {
            $table->dropColumn(['foto_awal', 'foto_akhir']);
        });
    }
};
