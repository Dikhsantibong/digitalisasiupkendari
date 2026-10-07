<?php

namespace App\Enums;

/**
 * Daily per-machine sheets of Pengusahaan Operasi that are not hours:
 * the highest load of the day (kW), the number of disturbances (kali) and
 * the tara kalor (kCal/kWh, typed in, not linked to other data).
 */
enum MesinHarianJenis: string
{
    case BebanTinggi = 'beban-tinggi';
    case KaliGangguan = 'kali-gangguan';
    case TaraKalor = 'tara-kalor';

    public function label(): string
    {
        return match ($this) {
            self::BebanTinggi => 'Beban Tertinggi',
            self::KaliGangguan => 'Jumlah Kali Gangguan',
            self::TaraKalor => 'kWh / kCal (Tara Kalor)',
        };
    }

    /** The heading of the printed sheet. */
    public function title(): string
    {
        return match ($this) {
            self::BebanTinggi => 'REKAP BEBAN HARIAN TERTINGGI',
            self::KaliGangguan => 'JUMLAH KALI GANGGUAN PEMBANGKIT',
            self::TaraKalor => 'TARA KALOR (KCAL/KWH)',
        };
    }

    public function satuan(): string
    {
        return match ($this) {
            self::BebanTinggi => 'kW',
            self::KaliGangguan => 'kali',
            self::TaraKalor => 'kCal/kWh',
        };
    }

    /**
     * How a machine's month, a day's machines and the unit are summed up:
     * the highest (beban), the sum (gangguan) or the average of the filled
     * cells (tara kalor).
     *
     * @return 'max'|'sum'|'average'
     */
    public function aggregation(): string
    {
        return match ($this) {
            self::BebanTinggi => 'max',
            self::KaliGangguan => 'sum',
            self::TaraKalor => 'average',
        };
    }

    /** The largest value a cell may hold. */
    public function maxValue(): int
    {
        return match ($this) {
            self::BebanTinggi => 100000,
            self::KaliGangguan => 100,
            self::TaraKalor => 100000,
        };
    }
}
