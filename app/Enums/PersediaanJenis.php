<?php

namespace App\Enums;

/**
 * The stock sheets of Pengusahaan Operasi: Persediaan Bahan Bakar (one block
 * per jenis BBM of the unit) and Persediaan Pelumas (one block per jenis
 * pelumas of the unit).
 */
enum PersediaanJenis: string
{
    case Bbm = 'bbm';
    case Pelumas = 'pelumas';

    public function label(): string
    {
        return match ($this) {
            self::Bbm => 'Persediaan Bahan Bakar',
            self::Pelumas => 'Persediaan Pelumas',
        };
    }

    /** The heading of the printed sheet. */
    public function title(): string
    {
        return match ($this) {
            self::Bbm => 'PERSEDIAAN BAHAN BAKAR MINYAK',
            self::Pelumas => 'PERSEDIAAN PELUMAS',
        };
    }

    /** The menu the PEMAKAIAN column is taken from. */
    public function pemakaianSource(): string
    {
        return match ($this) {
            self::Bbm => 'Pemakaian Bahan Bakar',
            self::Pelumas => 'Pemakaian Pelumas',
        };
    }
}
