<?php

namespace App\Services\K3\Master;

use App\Models\ApdCategory;
use App\Models\ApdItem;
use App\Models\EmergencyEquipment;
use App\Models\EquipmentCategory;
use App\Models\FireExtinguisher;
use App\Models\InspectionChecklist;
use App\Models\K3ActivityType;
use App\Models\P3kBox;
use App\Models\PatrolLocation;
use App\Models\SecurityPost;
use App\Services\Master\MasterFields;
use App\Services\Master\MasterRegistry;

/**
 * The catalogue of K3 & security master data managed through the shared generic
 * CRUD screen. Global lookups (activity types, APD/equipment categories) carry
 * no unit; the rest are per-unit. Adding a master means adding an entry here.
 */
class K3MasterRegistry implements MasterRegistry
{
    use MasterFields;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resources(): array
    {
        return [
            'activity-types' => [
                'label' => 'Jenis Kegiatan K3',
                'model' => K3ActivityType::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::text('category', 'Kategori'),
                    self::text('default_pic', 'PIC Default'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'apd-categories' => [
                'label' => 'Kategori APD',
                'model' => ApdCategory::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'equipment-categories' => [
                'label' => 'Kategori Alat Sertifikasi',
                'model' => EquipmentCategory::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'patrol-locations' => [
                'label' => 'Lokasi Patroli',
                'model' => PatrolLocation::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'security-posts' => [
                'label' => 'Pos Keamanan',
                'model' => SecurityPost::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('name', 'Nama', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'emergency-equipments' => [
                'label' => 'Fasilitas Darurat',
                'model' => EmergencyEquipment::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('group_name', 'Kelompok'),
                    self::text('name', 'Nama', required: true),
                    self::text('location', 'Lokasi'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'apd-items' => [
                'label' => 'Item APD',
                'model' => ApdItem::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::relation('apd_category_id', 'Kategori APD', 'apd_categories', unitScoped: false),
                    self::text('name', 'Nama', required: true),
                    self::text('location', 'Lokasi'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'fire-extinguishers' => [
                'label' => 'APAR/APAB',
                'model' => FireExtinguisher::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'location'],
                'fields' => [
                    self::text('rfid', 'RFID'),
                    self::text('location', 'Lokasi'),
                    self::text('merk', 'Merk'),
                    self::text('jenis', 'Jenis'),
                    self::number('berat_kg', 'Berat (kg)', step: '0.01'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'p3k-boxes' => [
                'label' => 'Kotak P3K',
                'model' => P3kBox::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('location', 'Lokasi'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'inspection-checklists' => [
                'label' => 'Item Checklist Inspeksi',
                'model' => InspectionChecklist::class,
                'unit_scoped' => true,
                'order' => ['form_code', 'sort_order'],
                'fields' => [
                    self::text('form_code', 'Kode Form', required: true),
                    self::text('item_text', 'Item', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return $this->resources()[$slug] ?? null;
    }

    /**
     * @return list<array{slug: string, label: string, unit_scoped: bool}>
     */
    public function summaries(): array
    {
        return collect($this->resources())
            ->map(fn (array $resource, string $slug): array => [
                'slug' => $slug,
                'label' => $resource['label'],
                'unit_scoped' => $resource['unit_scoped'],
            ])
            ->values()
            ->all();
    }
}
