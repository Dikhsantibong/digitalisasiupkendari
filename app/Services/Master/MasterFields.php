<?php

namespace App\Services\Master;

use BackedEnum;

/**
 * Field-schema builders shared by every master registry. A field's `type`
 * drives both the validation rule and the generated form control.
 */
trait MasterFields
{
    /**
     * @return array{key: string, label: string, type: string, required: bool}
     */
    protected static function text(string $key, string $label, bool $required = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => $required];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function number(string $key, string $label, bool $required = false, string $step = '1'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'number', 'required' => $required, 'step' => $step];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function bool(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'bool', 'required' => false, 'default' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function date(string $key, string $label, bool $required = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'date', 'required' => $required];
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return array<string, mixed>
     */
    protected static function enumSelect(string $key, string $label, array $cases, bool $required = false): array
    {
        $options = array_map(
            fn (BackedEnum $case): array => [
                'value' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases,
        );

        return ['key' => $key, 'label' => $label, 'type' => 'select', 'required' => $required, 'options' => $options];
    }

    /**
     * A foreign-key field rendered as a select. `$source` is the referenced
     * table; `$unitScoped` filters its options (and validates them) to the
     * current unit — the default, matching per-unit masters like `machines`.
     * Set it false for a global lookup (e.g. `apd_categories`).
     *
     * @return array<string, mixed>
     */
    protected static function relation(
        string $key,
        string $label,
        string $source,
        string $labelColumn = 'name',
        bool $unitScoped = true,
        bool $required = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'relation',
            'required' => $required,
            'source' => $source,
            'label_column' => $labelColumn,
            'relation_unit_scoped' => $unitScoped,
        ];
    }
}
