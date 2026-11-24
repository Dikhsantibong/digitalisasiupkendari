<?php

use App\Models\OperasiPatrolCheckMesin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated Master Operasi configuration & entries for Patrol Check Mesin
 * (user, 2026-11-23):
 * - Creates operasi_patrol_check_templates ("Template Patrol Check" in Master
 *   Data), independent from Operator LogsheetTemplate.
 * - Links machines.patrol_check_template_id (null = default Cummins Containerized
 *   monthly format, completely untouched).
 * - Creates operasi_patrol_check_entries for per-shift checklist inspections
 *   (PLTD Poasia form POA-OPR-CHK-01, 25 rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_patrol_check_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('format', 24)->default(OperasiPatrolCheckMesin::FORMAT_MONTHLY);
            $table->json('meta')->nullable();
            $table->json('items')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('machines', function (Blueprint $table): void {
            $table->foreignId('patrol_check_template_id')
                ->nullable()
                ->after('logsheet_template_id')
                ->constrained('operasi_patrol_check_templates')
                ->nullOnDelete();
        });

        Schema::table('operasi_patrol_check_mesins', function (Blueprint $table): void {
            $table->string('format', 24)->default(OperasiPatrolCheckMesin::FORMAT_MONTHLY)->after('month');
        });

        Schema::create('operasi_patrol_check_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patrol_check_mesin_id')
                ->constrained('operasi_patrol_check_mesins')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('shift', 20);
            $table->string('jam', 10);
            $table->string('operator_name', 120);
            $table->json('items');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patrol_check_mesin_id', 'tanggal', 'jam'], 'patrol_entries_parent_date_time_idx');
        });

        $now = now();
        DB::table('operasi_patrol_check_templates')->insert([
            'code' => 'cummins-containerized',
            'name' => 'Cummins (Containerized)',
            'description' => 'Format bulanan N/T 1–31 — PLTD Poasia Containerized & unit standar.',
            'format' => OperasiPatrolCheckMesin::FORMAT_MONTHLY,
            'meta' => null,
            'items' => null,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $poasiaTemplateId = DB::table('operasi_patrol_check_templates')->insertGetId([
            'code' => 'poasia-checklist',
            'name' => 'PLTD Poasia (Mesin & Alat Bantu)',
            'description' => 'Format pemeriksaan per shift POA-OPR-CHK-01 (Getar, Bunyi, Suhu, Tek/Level, Bocor/Visual, N/TN).',
            'format' => OperasiPatrolCheckMesin::FORMAT_CHECKLIST,
            'meta' => json_encode(OperasiPatrolCheckMesin::POASIA_META),
            'items' => json_encode(OperasiPatrolCheckMesin::POASIA_ITEMS),
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $poasiaUnitId = DB::table('units')->where('code', 'PLTD-POASIA')->value('id');
        if ($poasiaUnitId !== null) {
            DB::table('machines')
                ->where('unit_id', $poasiaUnitId)
                ->update(['patrol_check_template_id' => $poasiaTemplateId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_patrol_check_entries');

        Schema::table('operasi_patrol_check_mesins', function (Blueprint $table): void {
            $table->dropColumn('format');
        });

        Schema::table('machines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('patrol_check_template_id');
        });

        Schema::dropIfExists('operasi_patrol_check_templates');
    }
};
