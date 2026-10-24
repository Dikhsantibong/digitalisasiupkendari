<?php

namespace App\Enums;

/**
 * The operator's check of a Material & Peralatan item: lengkap means the
 * condition is normal; tidak lengkap / tidak ada needs an uraian of the
 * abnormal condition.
 */
enum MaterialKelengkapan: string
{
    case Lengkap = 'lengkap';
    case TidakLengkap = 'tidak_lengkap';
    case TidakAda = 'tidak_ada';

    public function label(): string
    {
        return match ($this) {
            self::Lengkap => 'Lengkap',
            self::TidakLengkap => 'Tidak Lengkap',
            self::TidakAda => 'Tidak Ada',
        };
    }

    public function isNormal(): bool
    {
        return $this === self::Lengkap;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
