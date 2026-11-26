<?php

namespace Database\Seeders;

use App\Models\ApdCategory;
use App\Models\EquipmentCategory;
use App\Models\K3ActivityType;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Per-unit K3 master data (activity types, APD & equipment categories).
 * Every unit receives its own scoped catalogue so users can customize each unit
 * independently via the Master K3 CRUD.
 */
class K3MasterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $activities = [
            ['code' => 'INSP-P3K', 'name' => 'Inspeksi Kotak P3K', 'category' => 'Inspeksi'],
            ['code' => 'INSP-APAR', 'name' => 'Inspeksi APAR/APAB', 'category' => 'Inspeksi'],
            ['code' => 'INSP-KEBAKARAN', 'name' => 'Inspeksi Potensi Bahaya Kebakaran', 'category' => 'Inspeksi'],
            ['code' => 'INSP-TEMPAT-KERJA', 'name' => 'Inspeksi Tempat Kerja', 'category' => 'Inspeksi'],
            ['code' => 'PATROLI', 'name' => 'Patroli Keamanan', 'category' => 'Keamanan'],
            ['code' => 'APEL', 'name' => 'Apel Keamanan', 'category' => 'Keamanan'],
        ];

        $apdCategories = [
            ['code' => 'HEAD', 'name' => 'Pelindung Kepala'],
            ['code' => 'EYE', 'name' => 'Pelindung Mata'],
            ['code' => 'HAND', 'name' => 'Pelindung Tangan'],
            ['code' => 'FOOT', 'name' => 'Pelindung Kaki'],
            ['code' => 'BODY', 'name' => 'Pelindung Badan'],
        ];

        $equipmentCategories = [
            ['code' => 'CRANE', 'name' => 'Crane'],
            ['code' => 'TANGKI', 'name' => 'Tangki Timbun'],
            ['code' => 'BEJANA', 'name' => 'Bejana Tekan'],
        ];

        foreach (Unit::query()->orderBy('id')->get() as $unit) {
            foreach ($activities as $i => $activity) {
                K3ActivityType::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'code' => $activity['code']],
                    ['name' => $activity['name'], 'category' => $activity['category'], 'sort_order' => $i, 'is_active' => true],
                );
            }

            foreach ($apdCategories as $i => $category) {
                ApdCategory::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'code' => $category['code']],
                    ['name' => $category['name'], 'sort_order' => $i, 'is_active' => true],
                );
            }

            foreach ($equipmentCategories as $i => $category) {
                EquipmentCategory::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'code' => $category['code']],
                    ['name' => $category['name'], 'sort_order' => $i, 'is_active' => true],
                );
            }
        }
    }
}
