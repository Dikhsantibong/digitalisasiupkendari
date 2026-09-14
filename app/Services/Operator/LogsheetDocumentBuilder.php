<?php

namespace App\Services\Operator;

use App\Enums\LogsheetStatus;
use App\Enums\PlantType;
use App\Models\LogsheetParameter;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use App\Services\Operasi\DocumentGridBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * Assembles the Operator logsheet report document: the report payload (header,
 * hourly parameter readings, bearing block, day stats), the initial rich-text
 * body, the spreadsheet grid (Excel), and the letterhead — mirroring the other
 * report-document builders so the shared editor works identically.
 */
class LogsheetDocumentBuilder
{
    /**
     * The available time slots — kept in sync with the logsheet input.
     *
     * @var list<string>
     */
    public const SLOTS = [
        '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00',
        '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00',
        '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
        '21:00', '21:30', '22:00', '23:00', '24:00',
    ];

    private const BEARING = 'Bearing Generator Temperatur';

    public function __construct(private readonly DocumentGridBuilder $grids) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, Machine $engine, string $date): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        $parameters = LogsheetParameter::query()
            ->where('plant_type', PlantType::All->value)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        $logsheet = OperatorLogsheet::query()->with('readings')
            ->where('engine_id', $engine->id)->whereDate('log_date', $date)->first();

        $stored = $logsheet?->readings->groupBy(fn ($r): string => substr((string) $r->time_slot, 0, 5)) ?? collect();

        $rows = collect(self::SLOTS)->map(function (string $slot) use ($stored, $parameters): array {
            $byParam = ($stored->get($slot) ?? collect())->keyBy('parameter_id');
            $values = [];
            foreach ($parameters as $parameter) {
                $values['p_'.$parameter->id] = $this->trimNumber($byParam->get($parameter->id)?->value);
            }

            return ['time_slot' => $slot, 'values' => $values];
        })->all();

        $bearing = $parameters->firstWhere('name', self::BEARING);
        $mainParameters = $parameters->reject(fn (LogsheetParameter $p): bool => $p->name === self::BEARING)->values();

        $dateLabel = Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y');

        return [
            'unit' => ['name' => $unit->name, 'service_unit' => $unit->serviceUnit?->name],
            'engine' => ['name' => $engine->name],
            'period' => ['label' => $dateLabel],
            'date' => $date,
            'date_label' => $dateLabel,
            'shift' => $logsheet?->shift,
            'status' => ($logsheet?->status ?? LogsheetStatus::Draft)->value,
            'parameters' => $mainParameters->map(fn (LogsheetParameter $p): array => [
                'id' => $p->id,
                'header' => $this->header($p),
            ])->all(),
            'groups' => $this->groupParameters($mainParameters),
            'bearing' => $bearing === null ? null : [
                'id' => $bearing->id,
                'unit_of_measure' => $bearing->unit_of_measure,
            ],
            'rows' => $rows,
            'stats' => $this->dayStats($parameters, $logsheet),
            'document_number' => $this->documentNumber($unit, $engine, $date),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('operator.laporan.document-body', ['report' => $data])->render();
    }

    public function contentStyles(): string
    {
        return View::make('operator.laporan.logsheet-styles')->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('operasi.laporan.partials.letterhead', [
            'report' => $data,
            'title' => 'Laporan Logsheet Operator',
        ])->render();
    }

    /**
     * The spreadsheet grid for Excel mode: Jam column + one column per parameter,
     * a row per time slot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function grid(array $data): array
    {
        $params = $data['parameters'];
        $cols = 1 + count($params);

        $header = [$this->c('JAM', true, 'c')];
        foreach ($params as $p) {
            $header[] = $this->c($p['header'], true, 'c');
        }

        $rows = [$header];
        foreach ($data['rows'] as $row) {
            $cells = [$this->c($row['time_slot'], true, 'c')];
            foreach ($params as $p) {
                $cells[] = $this->c((string) ($row['values']['p_'.$p['id']] ?? ''), false, 'c');
            }
            $rows[] = $cells;
        }

        return [
            'name' => 'Logsheet',
            'cols' => $cols,
            'col_widths' => array_merge([60], array_fill(0, count($params), 70)),
            'merges' => [],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    public function gridToHtml(array $grid): string
    {
        return $this->grids->gridToHtml($grid);
    }

    private function documentNumber(Unit $unit, Machine $engine, string $date): string
    {
        return sprintf('LOG-OPS/%s/%s/%s', $unit->code ?? $unit->id, $engine->id, Carbon::parse($date)->format('Ymd'));
    }

    /**
     * Group consecutive parameters that share a name into a parent header with
     * sub-columns (e.g. Flow Meter → IN/OUT), mirroring the input screen.
     *
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @return list<array{name: string, unit_of_measure: string|null, has_sub: bool, members: list<array{id: int, sub_channel: string|null}>}>
     */
    private function groupParameters($parameters): array
    {
        $groups = [];

        foreach ($parameters as $p) {
            $i = count($groups) - 1;
            if ($i >= 0 && $groups[$i]['name'] === $p->name) {
                $groups[$i]['members'][] = ['id' => $p->id, 'sub_channel' => $p->sub_channel];
            } else {
                $groups[] = [
                    'name' => $p->name,
                    'unit_of_measure' => $p->unit_of_measure,
                    'members' => [['id' => $p->id, 'sub_channel' => $p->sub_channel]],
                ];
            }
        }

        return array_map(function (array $g): array {
            $g['has_sub'] = collect($g['members'])->contains(fn (array $m): bool => $m['sub_channel'] !== null);

            return $g;
        }, $groups);
    }

    private function header(LogsheetParameter $p): string
    {
        $label = $p->name;
        if ($p->sub_channel) {
            $label .= ' '.$p->sub_channel;
        }
        if ($p->unit_of_measure) {
            $label .= ' ('.$p->unit_of_measure.')';
        }

        return $label;
    }

    /**
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @return list<array{label: string, value: string}>
     */
    private function dayStats($parameters, ?OperatorLogsheet $logsheet): array
    {
        $readings = $logsheet?->readings ?? collect();

        $loadId = $parameters->firstWhere('code', 'LOAD')?->id;
        $kwhId = $parameters->firstWhere('code', 'KWH_METER')?->id;

        $loadValues = $readings->where('parameter_id', $loadId)
            ->pluck('value')->filter(fn ($v): bool => $v !== null)->map(fn ($v): float => (float) $v);

        $kwhLast = $readings->where('parameter_id', $kwhId)
            ->sortByDesc(fn ($r): string => substr((string) $r->time_slot, 0, 5))->first()?->value;

        $filledSlots = $readings->groupBy(fn ($r): string => substr((string) $r->time_slot, 0, 5))->count();

        return [
            ['label' => 'Jam Terisi', 'value' => $filledSlots.' / '.count(self::SLOTS).' slot'],
            ['label' => 'Beban Puncak', 'value' => ($this->trimNumber($loadValues->max()) ?? '—').' kW'],
            ['label' => 'Rata-rata Beban', 'value' => ($loadValues->isNotEmpty() ? (string) $this->trimNumber(round((float) $loadValues->avg(), 1)) : '—').' kW'],
            ['label' => 'kWh Meter Akhir', 'value' => ($this->trimNumber($kwhLast) ?? '—').' kWh'],
        ];
    }

    private function trimNumber(int|float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = (string) $value;

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function c(string $text, bool $bold = false, string $align = 'l'): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }

        return $cell;
    }
}
