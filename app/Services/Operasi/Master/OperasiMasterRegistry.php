<?php

namespace App\Services\Operasi\Master;

use App\Enums\CalibrationFactorType;
use App\Enums\LubricantUnit;
use App\Enums\StatusCodeCategory;
use App\Enums\TankFuelType;
use App\Models\AuxiliarySource;
use App\Models\CalibrationFactor;
use App\Models\Feeder;
use App\Models\FuelTank;
use App\Models\LubricantType;
use App\Models\UnitStatusCode;

/**
 * The catalogue of operasi master data managed through one generic CRUD screen.
 * Each resource is a field schema, not a bespoke controller: adding a master
 * means adding an entry here. Field types drive both the validation rules and
 * the form the frontend renders.
 */
class OperasiMasterRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function resources(): array
    {
        return [
            'feeders' => [
                'label' => 'Feeder',
                'model' => Feeder::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('name', 'Nama', required: true),
                    self::text('feeder_type', 'Jenis'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'auxiliary-sources' => [
                'label' => 'Pasokan Cadangan',
                'model' => AuxiliarySource::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('name', 'Nama', required: true),
                    self::text('description', 'Keterangan'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'fuel-tanks' => [
                'label' => 'Tangki BBM',
                'model' => FuelTank::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('code', 'Kode'),
                    self::text('name', 'Nama', required: true),
                    self::enumSelect('fuel_type', 'Jenis BBM', TankFuelType::cases(), required: true),
                    self::number('capacity_liter', 'Kapasitas (L)'),
                    self::bool('is_daily_tank', 'Tangki Harian'),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'lubricant-types' => [
                'label' => 'Jenis Pelumas',
                'model' => LubricantType::class,
                'unit_scoped' => true,
                'order' => ['sort_order', 'name'],
                'fields' => [
                    self::text('code', 'Kode'),
                    self::text('name', 'Nama', required: true),
                    self::enumSelect('unit_of_measure', 'Satuan', LubricantUnit::cases(), required: true),
                    self::number('sort_order', 'Urutan'),
                    self::bool('is_active', 'Aktif'),
                ],
            ],
            'calibration-factors' => [
                'label' => 'Faktor Kalibrasi',
                'model' => CalibrationFactor::class,
                'unit_scoped' => true,
                'order' => ['effective_date'],
                'fields' => [
                    self::relation('engine_id', 'Mesin', 'machines'),
                    self::enumSelect('factor_type', 'Jenis Faktor', CalibrationFactorType::cases(), required: true),
                    self::number('value', 'Nilai', required: true, step: '0.0000001'),
                    self::date('effective_date', 'Berlaku Sejak', required: true),
                    self::text('notes', 'Catatan'),
                ],
            ],
            'status-codes' => [
                'label' => 'Kode Status (Global)',
                'model' => UnitStatusCode::class,
                'unit_scoped' => false,
                'order' => ['code'],
                'fields' => [
                    self::text('code', 'Kode', required: true),
                    self::text('label', 'Keterangan', required: true),
                    self::enumSelect('category', 'Kategori', StatusCodeCategory::cases(), required: true),
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
     * A lightweight description of every resource for the tab bar.
     *
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

    /**
     * @return array{key: string, label: string, type: string, required: bool}
     */
    private static function text(string $key, string $label, bool $required = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => $required];
    }

    /**
     * @return array<string, mixed>
     */
    private static function number(string $key, string $label, bool $required = false, string $step = '1'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'number', 'required' => $required, 'step' => $step];
    }

    /**
     * @return array<string, mixed>
     */
    private static function bool(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'bool', 'required' => false, 'default' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private static function date(string $key, string $label, bool $required = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'date', 'required' => $required];
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return array<string, mixed>
     */
    private static function enumSelect(string $key, string $label, array $cases, bool $required = false): array
    {
        $options = array_map(
            fn (\BackedEnum $case): array => [
                'value' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases,
        );

        return ['key' => $key, 'label' => $label, 'type' => 'select', 'required' => $required, 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    private static function relation(string $key, string $label, string $source): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'relation', 'required' => false, 'source' => $source];
    }
}
