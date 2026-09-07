<?php

namespace App\Enums;

/**
 * The three official Berita Acara documents produced by the operasi module.
 * The two fuel documents share a layout; the lubricant document uses its own
 * per-type table.
 */
enum BeritaAcaraType: string
{
    case Hsd = 'hsd';
    case Mfo = 'mfo';
    case Pelumas = 'pelumas';

    public function label(): string
    {
        return match ($this) {
            self::Hsd => 'BA BBM HSD',
            self::Mfo => 'BA BBM MFO',
            self::Pelumas => 'BA Opname Pelumas',
        };
    }

    /** The document title printed on the letterhead. */
    public function documentTitle(): string
    {
        return match ($this) {
            self::Hsd => 'BERITA ACARA PEMERIKSAAN BAHAN BAKAR MINYAK : HSD (B35)',
            self::Mfo => 'BERITA ACARA PEMERIKSAAN BAHAN BAKAR MINYAK : MFO',
            self::Pelumas => 'BERITA ACARA INVENTARISASI PEMERIKSAAN FISIK PELUMAS',
        };
    }

    public function isFuel(): bool
    {
        return $this !== self::Pelumas;
    }

    /** The tank fuel type for a fuel document, or null for the lubricant one. */
    public function tankFuelType(): ?TankFuelType
    {
        return match ($this) {
            self::Hsd => TankFuelType::Hsd,
            self::Mfo => TankFuelType::Mfo,
            self::Pelumas => null,
        };
    }

    /** The default example letter number (editable per template + per unit). */
    public function defaultDocumentNumber(): string
    {
        return match ($this) {
            self::Hsd => '021/OPS/BA-HSD',
            self::Mfo => '022/OPS/BA-MFO',
            self::Pelumas => '023/OPS/BA-PELUMAS',
        };
    }
}
