<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan Kondisi Abnormal & Gangguan: per finding the related parameter,
     * the short-term follow-up (SR, FLM, lainnya + keterangan), the long-term
     * follow-up and the impact on the system and on the plant.
     */
    public function up(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->text('parameter_terkait')->nullable()->after('durasi_gangguan');
            $table->boolean('tl_pendek_sr')->default(false)->after('parameter_terkait');
            $table->boolean('tl_pendek_flm')->default(false)->after('tl_pendek_sr');
            $table->boolean('tl_pendek_lainnya')->default(false)->after('tl_pendek_flm');
            $table->text('tl_pendek_keterangan')->nullable()->after('tl_pendek_lainnya');
            $table->text('tl_panjang')->nullable()->after('tl_pendek_keterangan');
            $table->text('dampak_sistem')->nullable()->after('tl_panjang');
            $table->text('dampak_pembangkit')->nullable()->after('dampak_sistem');
        });
    }

    public function down(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->dropColumn([
                'parameter_terkait', 'tl_pendek_sr', 'tl_pendek_flm', 'tl_pendek_lainnya',
                'tl_pendek_keterangan', 'tl_panjang', 'dampak_sistem', 'dampak_pembangkit',
            ]);
        });
    }
};
