<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A logsheet form per jenis mesin (user, 2026-11-21): its parameters, its time
 * slots and, optionally, the Patrol Check Mesin items. Machines point at one
 * through machines.logsheet_template_id; without one they use the default
 * template ({@see LogsheetTemplate::default()}).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property list<string>|null $time_slots
 * @property list<array{key: string, name: string, interval?: string|null, slots: list<string>, summary?: bool}>|null $sheets
 * @property list<array{system: string, peralatan: string}>|null $patrol_items
 * @property bool $is_default
 */
#[Fillable(['code', 'name', 'description', 'time_slots', 'sheets', 'patrol_items', 'is_default'])]
class LogsheetTemplate extends Model
{
    /**
     * The time slots when a template sets none: every hour plus the evening
     * peak half-hours (the Cummins logsheet).
     *
     * @var list<string>
     */
    public const DEFAULT_SLOTS = [
        '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00',
        '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00',
        '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
        '21:00', '21:30', '22:00', '23:00', '24:00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_slots' => 'array',
            'sheets' => 'array',
            'patrol_items' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LogsheetParameter, $this>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(LogsheetParameter::class, 'template_id');
    }

    /**
     * @return HasMany<Machine, $this>
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'logsheet_template_id');
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->orderBy('id')->first();
    }

    /**
     * The template a machine reads: its own, else the default one.
     */
    public static function forMachine(?Machine $machine): ?self
    {
        if ($machine?->logsheet_template_id !== null) {
            $template = static::query()->find($machine->logsheet_template_id);
            if ($template !== null) {
                return $template;
            }
        }

        return static::default();
    }

    /**
     * @return list<string>
     */
    public function slots(): array
    {
        return $this->time_slots !== null && $this->time_slots !== [] ? array_values($this->time_slots) : self::DEFAULT_SLOTS;
    }

    /**
     * The sheets (lembar) of the form, each with its own interval and slots
     * (user, 2026-11-22: PLTD Poasia Lokal 02 hourly, Lokal 03 & Cylinder Head
     * every 2 hours). A template without sheets has one sheet: its time slots.
     *
     * @return list<array{key: string, name: string, interval: string|null, slots: list<string>, summary: bool}>
     */
    public function sheetList(): array
    {
        if (empty($this->sheets)) {
            return [['key' => self::SINGLE_SHEET, 'name' => $this->name, 'interval' => null, 'slots' => $this->slots(), 'summary' => false]];
        }

        return array_values(array_map(fn (array $sheet): array => [
            'key' => (string) $sheet['key'],
            'name' => (string) $sheet['name'],
            'interval' => $sheet['interval'] ?? null,
            'slots' => array_values($sheet['slots'] ?? []) ?: self::DEFAULT_SLOTS,
            'summary' => (bool) ($sheet['summary'] ?? false),
        ], $this->sheets));
    }

    /**
     * One sheet by key, else the first one.
     *
     * @return array{key: string, name: string, interval: string|null, slots: list<string>, summary: bool}
     */
    public function sheet(?string $key): array
    {
        $sheets = $this->sheetList();

        return collect($sheets)->firstWhere('key', $key) ?? $sheets[0];
    }

    /** The key of the only sheet of a template without sheets. */
    public const SINGLE_SHEET = 'utama';
}
