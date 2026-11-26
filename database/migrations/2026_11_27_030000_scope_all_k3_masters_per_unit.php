<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['k3_activity_types', 'apd_categories', 'equipment_categories'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'unit_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['code']);
                    $table->foreignId('unit_id')->nullable()->after('id')->constrained('units')->cascadeOnDelete();
                    $table->unique(['unit_id', 'code']);
                });
            }
        }

        $this->backfillPerUnitMasters();
    }

    public function down(): void
    {
        foreach (['k3_activity_types', 'apd_categories', 'equipment_categories'] as $tableName) {
            if (Schema::hasColumn($tableName, 'unit_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['unit_id', 'code']);
                    $table->dropConstrainedForeignId('unit_id');
                });
            }
        }
    }

    private function backfillPerUnitMasters(): void
    {
        $unitIds = DB::table('units')->orderBy('id')->pluck('id')->all();

        if ($unitIds === []) {
            return;
        }

        $now = now();

        // 1. k3_activity_types -> k3_activity_plans.k3_activity_type_id
        $globalActivities = DB::table('k3_activity_types')->whereNull('unit_id')->orderBy('id')->get();
        if ($globalActivities->isNotEmpty()) {
            foreach ($unitIds as $unitId) {
                foreach ($globalActivities as $activity) {
                    $existingId = DB::table('k3_activity_types')
                        ->where('unit_id', $unitId)
                        ->where('code', $activity->code)
                        ->value('id');

                    if (! $existingId) {
                        $existingId = DB::table('k3_activity_types')->insertGetId([
                            'unit_id' => $unitId,
                            'code' => $activity->code,
                            'name' => $activity->name,
                            'category' => $activity->category,
                            'default_pic' => $activity->default_pic,
                            'sort_order' => $activity->sort_order,
                            'is_active' => $activity->is_active,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('k3_activity_plans')
                        ->where('unit_id', $unitId)
                        ->where('k3_activity_type_id', $activity->id)
                        ->update(['k3_activity_type_id' => $existingId]);
                }
            }

            DB::table('k3_activity_types')->whereNull('unit_id')->delete();
        }

        // 2. apd_categories -> apd_items.apd_category_id
        $globalApdCategories = DB::table('apd_categories')->whereNull('unit_id')->orderBy('id')->get();
        if ($globalApdCategories->isNotEmpty()) {
            foreach ($unitIds as $unitId) {
                foreach ($globalApdCategories as $cat) {
                    $existingId = DB::table('apd_categories')
                        ->where('unit_id', $unitId)
                        ->where('code', $cat->code)
                        ->value('id');

                    if (! $existingId) {
                        $existingId = DB::table('apd_categories')->insertGetId([
                            'unit_id' => $unitId,
                            'code' => $cat->code,
                            'name' => $cat->name,
                            'sort_order' => $cat->sort_order,
                            'is_active' => $cat->is_active,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('apd_items')
                        ->where('unit_id', $unitId)
                        ->where('apd_category_id', $cat->id)
                        ->update(['apd_category_id' => $existingId]);
                }
            }

            DB::table('apd_categories')->whereNull('unit_id')->delete();
        }

        // 3. equipment_categories -> equipment_certificates & k3_pengusahaan_certificates
        $globalEquipCategories = DB::table('equipment_categories')->whereNull('unit_id')->orderBy('id')->get();
        if ($globalEquipCategories->isNotEmpty()) {
            foreach ($unitIds as $unitId) {
                foreach ($globalEquipCategories as $cat) {
                    $existingId = DB::table('equipment_categories')
                        ->where('unit_id', $unitId)
                        ->where('code', $cat->code)
                        ->value('id');

                    if (! $existingId) {
                        $existingId = DB::table('equipment_categories')->insertGetId([
                            'unit_id' => $unitId,
                            'code' => $cat->code,
                            'name' => $cat->name,
                            'sort_order' => $cat->sort_order,
                            'is_active' => $cat->is_active,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('equipment_certificates')
                        ->where('unit_id', $unitId)
                        ->where('equipment_category_id', $cat->id)
                        ->update(['equipment_category_id' => $existingId]);

                    if (Schema::hasTable('k3_pengusahaan_certificates')) {
                        DB::table('k3_pengusahaan_certificates')
                            ->where('unit_id', $unitId)
                            ->where('equipment_category_id', $cat->id)
                            ->update(['equipment_category_id' => $existingId]);
                    }
                }
            }

            DB::table('equipment_categories')->whereNull('unit_id')->delete();
        }
    }
};
