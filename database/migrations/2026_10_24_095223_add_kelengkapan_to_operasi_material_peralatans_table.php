<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The operator's check of each material & peralatan item the Koordinator /
     * Project Leader listed: lengkap, tidak lengkap or tidak ada, and the uraian
     * of the abnormal condition when it is not lengkap.
     */
    public function up(): void
    {
        Schema::table('operasi_material_peralatans', function (Blueprint $table): void {
            $table->string('kelengkapan', 20)->nullable()->after('roq');
            $table->text('uraian_kondisi')->nullable()->after('kelengkapan');
            $table->foreignId('checked_by')->nullable()->after('uraian_kondisi')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('operasi_material_peralatans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn(['kelengkapan', 'uraian_kondisi', 'checked_at']);
        });
    }
};
