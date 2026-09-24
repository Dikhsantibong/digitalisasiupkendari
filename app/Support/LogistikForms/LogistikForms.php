<?php

namespace App\Support\LogistikForms;

/**
 * Registry of the Logistik & Gudang table forms, in menu order.
 */
class LogistikForms
{
    /** @var list<class-string<LogistikForm>> */
    public const ALL = [
        PendukungForm::class,
        InventarisLainnyaForm::class,
        PeralatanForm::class,
        KondisiStokForm::class,
        UnsafeForm::class,
        PermitToWorkForm::class,
    ];

    /**
     * @return list<LogistikForm>
     */
    public static function all(): array
    {
        return array_map(fn (string $class): LogistikForm => new $class, self::ALL);
    }

    public static function find(string $key): ?LogistikForm
    {
        foreach (self::all() as $form) {
            if ($form->key() === $key) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (LogistikForm $form): string => $form->key(), self::all());
    }
}
