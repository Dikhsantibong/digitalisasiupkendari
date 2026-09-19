<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header fields the PdM input forms print above the signatures
     * (e.g. "Kolaka, 02 Agustus 2026") and their free "Catatan".
     */
    public function up(): void
    {
        Schema::table('pdm_jadwal_meta', function (Blueprint $table): void {
            $table->string('tempat_tanggal')->nullable()->after('page_number');
            $table->text('catatan')->nullable()->after('tempat_tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('pdm_jadwal_meta', function (Blueprint $table): void {
            $table->dropColumn(['tempat_tanggal', 'catatan']);
        });
    }
};
