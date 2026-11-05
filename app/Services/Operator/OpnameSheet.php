<?php

namespace App\Services\Operator;

use App\Enums\KwhMeterKategori;
use App\Enums\OpnameJenis;
use App\Enums\TankJenis;
use App\Models\FuelTank;
use App\Models\KwhMeter;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperatorOpname;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The daily Opname BBM, Opname Pelumas and Opname kWh of a unit (modul
 * Operator): which rows each sheet has (from the unit's own master — tank,
 * pelumas and kWh names differ per unit), the values with the stand awal
 * carried from the previous opname, and the computed figures. Only typed
 * values are stored; subtotals and the kWh summary are always computed here.
 *
 * A section has column groups and rows; a row fills the fields of its group:
 *  - Opname BBM: Opname Fisik (storage & setlink tanks: cm, liter) and Opname
 *    Administrasi (service tanks: cm, liter; flowmeter BBM per mesin: stand
 *    awal, stand akhir, pemakaian);
 *  - Opname Pelumas: per jenis pelumas stand awal, stand akhir, sub total;
 *  - Opname kWh: kWh produksi, kWh PS and susut trafo, each stand awal, stand
 *    akhir, sub total (kWh Meter master; produksi & PS fall back to the mesin).
 *
 * @phpstan-type Field array{key: string, label: string, computed?: bool}
 * @phpstan-type Section array{key: string, title: string, name_label: string, totals: list<string>, groups: list<array{key: string, label: string, fields: list<Field>}>, items: list<array{key: string, name: string, group: string, note: string|null}>}
 */
class OpnameSheet
{
    /** @var list<Field> */
    private const LEVEL = [['key' => 'cm', 'label' => 'cm'], ['key' => 'liter', 'label' => 'Liter']];

    /**
     * @return list<Field>
     */
    private static function stand(string $subtotal = 'Sub Total'): array
    {
        return [
            ['key' => 'awal', 'label' => 'Stand Awal'],
            ['key' => 'akhir', 'label' => 'Stand Akhir'],
            ['key' => 'subtotal', 'label' => $subtotal, 'computed' => true],
        ];
    }

    /**
     * @return list<Section>
     */
    public function sections(Unit $unit, OpnameJenis $jenis): array
    {
        return match ($jenis) {
            OpnameJenis::Bbm => $this->bbmSections($unit),
            OpnameJenis::Pelumas => [[
                'key' => 'pelumas',
                'title' => 'Opname Pelumas',
                'name_label' => 'Nama Pelumas',
                // Drum, liter and kg do not add up.
                'totals' => [],
                'groups' => [['key' => 'stand', 'label' => '', 'fields' => self::stand()]],
                'items' => LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)
                    ->orderBy('sort_order')->orderBy('name')->get()
                    ->map(fn (LubricantType $type): array => ['key' => "l{$type->id}", 'name' => $type->name, 'group' => 'stand', 'note' => $type->unit_of_measure?->label()])
                    ->values()->all(),
            ]],
            OpnameJenis::Kwh => array_map(fn (KwhMeterKategori $kategori): array => [
                'key' => $kategori->value,
                'title' => $kategori->label(),
                'name_label' => $kategori === KwhMeterKategori::SusutTrafo ? 'Nama' : 'Nama kWh',
                'totals' => ['subtotal'],
                'groups' => [['key' => 'stand', 'label' => '', 'fields' => self::stand()]],
                'items' => $this->kwhItems($unit, $kategori),
            ], KwhMeterKategori::cases()),
        };
    }

    /**
     * The values of one sheet: what was saved, and for an empty stand awal the
     * stand akhir of the same row in the unit's previous opname.
     *
     * @param  list<Section>  $sections
     * @return array<string, array<string, array<string, float>>>
     */
    public function values(Unit $unit, string $tanggal, OpnameJenis $jenis, array $sections): array
    {
        $saved = $this->record($unit, $tanggal, $jenis)?->data ?? [];
        $previous = OperatorOpname::query()
            ->where('unit_id', $unit->id)->where('jenis', $jenis)->whereDate('tanggal', '<', $tanggal)
            ->orderByDesc('tanggal')->first()?->data ?? [];

        $values = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $row = (array) ($saved[$section['key']][$item['key']] ?? []);
                $carried = $previous[$section['key']][$item['key']]['akhir'] ?? null;
                if (! isset($row['awal']) && is_numeric($carried) && $this->hasField($section, $item['group'], 'awal')) {
                    $row['awal'] = (float) $carried;
                }
                if ($row !== []) {
                    $values[$section['key']][$item['key']] = array_map(fn ($value): float => (float) $value, $row);
                }
            }
        }

        return $values;
    }

    /**
     * Only the typed fields of the sheet's rows, as numbers; empty rows dropped.
     *
     * @param  list<Section>  $sections
     * @param  array<mixed>  $input
     * @return array<string, array<string, array<string, float>>>
     */
    public function clean(array $sections, array $input): array
    {
        $data = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                foreach ($this->fieldsOf($section, $item['group']) as $field) {
                    $value = $input[$section['key']][$item['key']][$field['key']] ?? null;
                    if (empty($field['computed']) && is_numeric($value)) {
                        $data[$section['key']][$item['key']][$field['key']] = (float) $value;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * kWh netto = kWh produksi − kWh PSST, kWh PSST = kWh PS + susut trafo,
     * SFC bruto = kWh produksi ÷ pemakaian BBM, SFC netto = kWh netto ÷
     * pemakaian BBM (the flowmeter pemakaian of the same day's Opname BBM).
     *
     * @param  array<string, array<string, array<string, float>>>  $values
     * @return array{produksi: float, ps: float, susut_trafo: float, psst: float, netto: float, bbm: float|null, sfc_bruto: float|null, sfc_netto: float|null}
     */
    public function kwhSummary(Unit $unit, string $tanggal, array $values): array
    {
        $produksi = $this->subtotalOf($values['produksi'] ?? []);
        $ps = $this->subtotalOf($values['ps'] ?? []);
        $susut = $this->subtotalOf($values['susut_trafo'] ?? []);
        $psst = $ps + $susut;
        $netto = $produksi - $psst;
        $bbm = $this->bbmPemakaian($unit, $tanggal);

        return [
            'produksi' => $produksi,
            'ps' => $ps,
            'susut_trafo' => $susut,
            'psst' => $psst,
            'netto' => $netto,
            'bbm' => $bbm,
            'sfc_bruto' => $bbm !== null && $bbm > 0 ? $produksi / $bbm : null,
            'sfc_netto' => $bbm !== null && $bbm > 0 ? $netto / $bbm : null,
        ];
    }

    /**
     * The day's BBM pemakaian: the flowmeter rows of its Opname BBM; null
     * when that opname has none.
     */
    public function bbmPemakaian(Unit $unit, string $tanggal): ?float
    {
        $flowmeter = $this->record($unit, $tanggal, OpnameJenis::Bbm)?->data['administrasi'] ?? [];
        $rows = array_filter($flowmeter, fn (array $row): bool => isset($row['awal'], $row['akhir']));

        return $rows === [] ? null : $this->subtotalOf($rows);
    }

    public function record(Unit $unit, string $tanggal, OpnameJenis $jenis): ?OperatorOpname
    {
        return OperatorOpname::query()
            ->where('unit_id', $unit->id)->where('jenis', $jenis)->whereDate('tanggal', $tanggal)
            ->first();
    }

    /**
     * Sum of stand akhir − stand awal of the rows with both.
     *
     * @param  array<string, array<string, float>>  $rows
     */
    private function subtotalOf(array $rows): float
    {
        return (float) collect($rows)
            ->filter(fn (array $row): bool => isset($row['awal'], $row['akhir']))
            ->sum(fn (array $row): float => (float) $row['akhir'] - (float) $row['awal']);
    }

    /**
     * @return list<Section>
     */
    private function bbmSections(Unit $unit): array
    {
        /** @var Collection<int, FuelTank> $tanks */
        $tanks = FuelTank::query()->with('bbmType:id,code')->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $tank = fn (FuelTank $tank, string $group): array => [
            'key' => "t{$tank->id}",
            'name' => $tank->name,
            'group' => $group,
            'note' => $tank->bbmType?->code ?? $tank->fuel_type?->label(),
        ];
        // A tank without a jenis counts as storage.
        $jenisOf = fn (FuelTank $tank): TankJenis => $tank->jenis ?? TankJenis::Storage;

        return [
            [
                'key' => 'fisik',
                'title' => 'Opname Fisik',
                'name_label' => 'Nama Tangki',
                'totals' => ['liter'],
                'groups' => [
                    ['key' => TankJenis::Storage->value, 'label' => TankJenis::Storage->label(), 'fields' => self::LEVEL],
                    ['key' => TankJenis::Setlink->value, 'label' => TankJenis::Setlink->label(), 'fields' => self::LEVEL],
                ],
                'items' => $tanks->filter(fn (FuelTank $t): bool => in_array($jenisOf($t), [TankJenis::Storage, TankJenis::Setlink], true))
                    ->sortBy(fn (FuelTank $t): int => $jenisOf($t) === TankJenis::Storage ? 0 : 1)
                    ->map(fn (FuelTank $t): array => $tank($t, $jenisOf($t)->value))
                    ->values()->all(),
            ],
            [
                'key' => 'administrasi',
                'title' => 'Opname Administrasi',
                'name_label' => 'Nama',
                'totals' => ['liter', 'subtotal'],
                'groups' => [
                    ['key' => TankJenis::Service->value, 'label' => TankJenis::Service->label(), 'fields' => self::LEVEL],
                    ['key' => 'flowmeter', 'label' => 'Flowmeter BBM', 'fields' => self::stand('Pemakaian')],
                ],
                'items' => [
                    ...$tanks->filter(fn (FuelTank $t): bool => $jenisOf($t) === TankJenis::Service)
                        ->map(fn (FuelTank $t): array => $tank($t, TankJenis::Service->value))->values()->all(),
                    ...$this->machines($unit)->map(fn (Machine $m): array => ['key' => "m{$m->id}", 'name' => "Flowmeter {$m->name}", 'group' => 'flowmeter', 'note' => null])->all(),
                ],
            ],
        ];
    }

    /**
     * The unit's kWh meters of one kategori; kWh produksi & PS use the mesin
     * while the unit has none in the master.
     *
     * @return list<array{key: string, name: string, group: string, note: string|null}>
     */
    private function kwhItems(Unit $unit, KwhMeterKategori $kategori): array
    {
        $meters = KwhMeter::query()->with('machine:id,name')->where('unit_id', $unit->id)->where('kategori', $kategori)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        if ($meters->isEmpty() && $kategori !== KwhMeterKategori::SusutTrafo) {
            return $this->machines($unit)->map(fn (Machine $m): array => ['key' => "m{$m->id}", 'name' => $m->name, 'group' => 'stand', 'note' => null])->all();
        }

        return $meters->map(fn (KwhMeter $meter): array => ['key' => "k{$meter->id}", 'name' => $meter->name, 'group' => 'stand', 'note' => $meter->machine?->name])
            ->values()->all();
    }

    /**
     * @return Collection<int, Machine>
     */
    private function machines(Unit $unit): Collection
    {
        return Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])->values();
    }

    /**
     * @param  Section  $section
     * @return list<Field>
     */
    private function fieldsOf(array $section, string $group): array
    {
        return collect($section['groups'])->firstWhere('key', $group)['fields'] ?? [];
    }

    /**
     * @param  Section  $section
     */
    private function hasField(array $section, string $group, string $field): bool
    {
        return collect($this->fieldsOf($section, $group))->contains('key', $field);
    }

    /** Today in WITA, the operators' day. */
    public static function today(): string
    {
        return Carbon::now('Asia/Makassar')->toDateString();
    }
}
