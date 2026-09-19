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
        Schema::create('pdm_jadwal_patrol_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('kategori')->default('I. HARMES');
            $table->boolean('is_category_header')->default(false);
            $table->string('no_urut', 20)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('nama');
            $table->string('no_hp', 50)->nullable();
            $table->unsignedSmallInteger('target')->default(23);
            $table->json('jadwal')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        if (Schema::hasTable('pdm_jadwal_meta')) {
            Schema::table('pdm_jadwal_meta', function (Blueprint $table): void {
                if (! Schema::hasColumn('pdm_jadwal_meta', 'mengetahui_employee_id')) {
                    $table->foreignId('mengetahui_employee_id')->nullable()->after('page_number')->constrained('employees')->nullOnDelete();
                }
                if (! Schema::hasColumn('pdm_jadwal_meta', 'mengetahui_nama')) {
                    $table->string('mengetahui_nama')->nullable()->after('mengetahui_employee_id');
                }
                if (! Schema::hasColumn('pdm_jadwal_meta', 'mengetahui_jabatan')) {
                    $table->string('mengetahui_jabatan')->nullable()->after('mengetahui_nama');
                }
                if (! Schema::hasColumn('pdm_jadwal_meta', 'disetujui_employee_id')) {
                    $table->foreignId('disetujui_employee_id')->nullable()->after('mengetahui_jabatan')->constrained('employees')->nullOnDelete();
                }
                if (! Schema::hasColumn('pdm_jadwal_meta', 'dibuat_employee_id')) {
                    $table->foreignId('dibuat_employee_id')->nullable()->after('disetujui_jabatan')->constrained('employees')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdm_jadwal_patrol_checks');

        if (Schema::hasTable('pdm_jadwal_meta')) {
            Schema::table('pdm_jadwal_meta', function (Blueprint $table): void {
                $columnsToDrop = [
                    'mengetahui_employee_id',
                    'mengetahui_nama',
                    'mengetahui_jabatan',
                    'disetujui_employee_id',
                    'dibuat_employee_id',
                ];
                foreach ($columnsToDrop as $col) {
                    if (Schema::hasColumn('pdm_jadwal_meta', $col)) {
                        $table->dropConstrainedForeignId($col);
                    }
                }
            });
        }
    }
};
