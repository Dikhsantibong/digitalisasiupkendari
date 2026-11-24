<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patrol Check Mesin template per jenis mesin / unit format (Operasi Master Data):
 * - "monthly": 31-day N/T matrix + shift crew initials (default for Cummins / Containerized).
 * - "checklist": per-shift check form (e.g. PLTD Poasia POA-OPR-CHK-01, 25 rows with
 *   parameter applicability flags and N/TN + early-action notes).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $format
 * @property array{title?: string, form_number?: string, revision?: string, instruction?: string}|null $meta
 * @property list<array{system: string, peralatan: string, params?: array<string, bool>}>|null $items
 * @property bool $is_default
 */
#[Fillable(['code', 'name', 'description', 'format', 'meta', 'items', 'is_default'])]
class OperasiPatrolCheckTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'items' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Machine, $this>
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'patrol_check_template_id');
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->orderBy('id')->first();
    }

    /**
     * The Patrol Check template a machine uses: its explicit assignment, else default.
     */
    public static function forMachine(?Machine $machine): ?self
    {
        if ($machine?->patrol_check_template_id !== null) {
            $template = static::query()->find($machine->patrol_check_template_id);
            if ($template !== null) {
                return $template;
            }
        }

        return static::default();
    }

    public function resolvedFormat(): string
    {
        return $this->format === OperasiPatrolCheckMesin::FORMAT_CHECKLIST
            ? OperasiPatrolCheckMesin::FORMAT_CHECKLIST
            : OperasiPatrolCheckMesin::FORMAT_MONTHLY;
    }

    public function isChecklist(): bool
    {
        return $this->resolvedFormat() === OperasiPatrolCheckMesin::FORMAT_CHECKLIST;
    }

    /**
     * Form metadata used for checklist-format headers & instructions.
     *
     * @return array{title: string, form_number: string, revision: string, instruction: string}
     */
    public function resolvedMeta(): array
    {
        $saved = is_array($this->meta) ? $this->meta : [];

        return [
            'title' => (string) ($saved['title'] ?? OperasiPatrolCheckMesin::POASIA_META['title']),
            'form_number' => (string) ($saved['form_number'] ?? OperasiPatrolCheckMesin::POASIA_META['form_number']),
            'revision' => (string) ($saved['revision'] ?? OperasiPatrolCheckMesin::POASIA_META['revision']),
            'instruction' => (string) ($saved['instruction'] ?? OperasiPatrolCheckMesin::POASIA_META['instruction']),
        ];
    }

    /**
     * Configured rows (falls back to POASIA_ITEMS or DEFAULT_ITEMS when empty).
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedItems(): array
    {
        if (is_array($this->items) && $this->items !== []) {
            return array_values($this->items);
        }

        return $this->isChecklist()
            ? OperasiPatrolCheckMesin::POASIA_ITEMS
            : OperasiPatrolCheckMesin::DEFAULT_ITEMS;
    }
}
