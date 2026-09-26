<?php

namespace Database\Seeders;

use App\Models\MaintenanceCycle;
use App\Models\MaintenanceType;
use App\Models\SrCategory;
use App\Models\WorkGroup;
use App\Models\WoStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Placeholder maintenance master data. The full WPC lists (statuses, cycles,
 * categories) are not final — users complete and correct these via the CRUD.
 */
class HarMasterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $types = [
            ['code' => 'PM', 'name' => 'Preventive Maintenance'],
            ['code' => 'PdM', 'name' => 'Predictive Maintenance'],
            ['code' => 'CM', 'name' => 'Corrective Maintenance'],
            ['code' => 'FLM', 'name' => 'First Line Maintenance'],
            ['code' => 'ENJI', 'name' => 'Engineering'],
        ];
        foreach ($types as $i => $type) {
            MaintenanceType::query()->updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $cycles = [
            ['code' => 'P1', 'name' => 'Siklus 7 Hari', 'interval_days' => 7],
            ['code' => 'P2', 'name' => 'Siklus 14 Hari', 'interval_days' => 14],
            ['code' => 'P3', 'name' => 'Siklus 28 Hari', 'interval_days' => 28],
            ['code' => 'P4', 'name' => 'Siklus 84 Hari', 'interval_days' => 84],
        ];
        foreach ($cycles as $i => $cycle) {
            MaintenanceCycle::query()->updateOrCreate(
                ['code' => $cycle['code']],
                ['name' => $cycle['name'], 'interval_days' => $cycle['interval_days'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $statuses = [
            ['code' => 'WAPPR', 'name' => 'Waiting Approval', 'is_closed' => false],
            ['code' => 'APPR', 'name' => 'Approved', 'is_closed' => false],
            ['code' => 'INPRG', 'name' => 'In Progress', 'is_closed' => false],
            ['code' => 'WPTW', 'name' => 'Waiting Permit to Work', 'is_closed' => false],
            ['code' => 'CLOSE', 'name' => 'Closed', 'is_closed' => true],
            ['code' => 'WMATL', 'name' => 'Waiting Material', 'is_closed' => false],
            ['code' => 'WENG', 'name' => 'Waiting Engineering', 'is_closed' => false],
            ['code' => 'WSCH', 'name' => 'Waiting Schedule / Shutdown', 'is_closed' => false],
        ];
        foreach ($statuses as $i => $status) {
            WoStatus::query()->updateOrCreate(
                ['code' => $status['code']],
                ['name' => $status['name'], 'is_closed' => $status['is_closed'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $groups = [
            ['code' => 'MECHD', 'name' => 'Mekanik'],
            ['code' => 'ELECD', 'name' => 'Listrik'],
            ['code' => 'INSTD', 'name' => 'Kontrol & Instrumen'],
            ['code' => 'CIVD', 'name' => 'Sipil'],
        ];
        foreach ($groups as $i => $group) {
            WorkGroup::query()->updateOrCreate(
                ['code' => $group['code']],
                ['name' => $group['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $categories = [
            ['code' => 'CM', 'name' => 'Corrective Maintenance'],
            ['code' => 'FLM', 'name' => 'First Line Maintenance'],
            ['code' => 'PDM', 'name' => 'Predictive Maintenance'],
            ['code' => 'CANCEL', 'name' => 'Dibatalkan'],
        ];
        foreach ($categories as $i => $category) {
            SrCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }
    }
}
