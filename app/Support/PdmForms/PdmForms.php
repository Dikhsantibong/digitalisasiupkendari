<?php

namespace App\Support\PdmForms;

/**
 * Registry of the generic PdM input forms, in the order of the Input menu and
 * the Laporan PdM.
 */
final class PdmForms
{
    /** @var list<class-string<PdmForm>> */
    public const ALL = [
        Checklist5s5rForm::class,
        AirPendinginForm::class,
        PelumasForm::class,
        VibrasiForm::class,
        KontrolMaterialForm::class,
        PatrolCheckPdmForm::class,
        ChecklistPatrolCheckPdmForm::class,
    ];

    /**
     * @return list<PdmForm>
     */
    public static function all(): array
    {
        return array_map(fn (string $class): PdmForm => app($class), self::ALL);
    }

    public static function find(string $key): ?PdmForm
    {
        foreach (self::all() as $form) {
            if ($form->key() === $key) {
                return $form;
            }
        }

        return null;
    }
}
