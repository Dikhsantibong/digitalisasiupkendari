<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A gangguan report also records when it started and ended (its durasi is
     * the hours between them) and which mesin & peralatan failed.
     */
    public function up(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->string('jam_mulai', 5)->nullable()->after('durasi_gangguan');
            $table->string('jam_selesai', 5)->nullable()->after('jam_mulai');
            $table->foreignId('machine_id')->nullable()->after('jam_selesai')->constrained('machines')->nullOnDelete();
            $table->string('peralatan')->nullable()->after('machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('kondisi_abnormals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('machine_id');
            $table->dropColumn(['jam_mulai', 'jam_selesai', 'peralatan']);
        });
    }
};
