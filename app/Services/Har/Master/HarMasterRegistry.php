<?php

namespace App\Services\Har\Master;

use App\Models\MaintenanceCycle;
use App\Models\MaintenanceType;
use App\Models\SrCategory;
use App\Models\WorkGroup;
use App\Models\WoStatus;
use App\Services\Master\MasterFields;
use App\Services\Master\MasterRegistry;

/**
 * The catalogue of pemeliharaan (HAR) master data — all global lookups —
 * managed through the shared generic CRUD screen. Adding a master means adding
 * an entry here; no new controller or page.
 */
class HarMasterRegistry implements MasterRegistry
{
    use MasterFields;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resources(): array
    {
        return [
            'maintenance-types' => [
                'label' => 'Jenis Pemeliharaan',
                'model' => MaintenanceType::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::text('category', 'Kategori'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'maintenance-cycles' => [
                'label' => 'Siklus Pemeliharaan',
                'model' => MaintenanceCycle::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::number('interval_days', 'Interval (hari)'),
                    self::text('description', 'Keterangan'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'wo-statuses' => [
                'label' => 'Status WO',
                'model' => WoStatus::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::bool('is_closed', 'Dihitung Selesai'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'work-groups' => [
                'label' => 'Work Group',
                'model' => WorkGroup::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'sr-categories' => [
                'label' => 'Kategori SR',
                'model' => SrCategory::class,
                'unit_scoped' => false,
                'order' => ['sort_order', 'code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('name', 'Nama', required: true),
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
