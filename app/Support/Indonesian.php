<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Indonesian formatting helpers reused across every document: day names, long
 * dates, and "terbilang" (a number spelled out in words). Kept in one place so
 * the Berita Acara and any future document read identically.
 */
class Indonesian
{
    private const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const UNITS = [
        'nol', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /** The day name, e.g. "Senin". */
    public static function dayName(Carbon $date): string
    {
        return self::DAYS[(int) $date->dayOfWeek];
    }

    /** The month name, e.g. "Agustus". */
    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? (string) $month;
    }

    /** A long date, e.g. "7 September 2026". */
    public static function longDate(Carbon $date): string
    {
        return $date->day.' '.self::monthName((int) $date->month).' '.$date->year;
    }

    /** A number spelled out in Indonesian, e.g. 2026 => "dua ribu dua puluh enam". */
    public static function terbilang(int $number): string
    {
        if ($number < 0) {
            return 'minus '.self::terbilang(-$number);
        }

        if ($number < 12) {
            return self::UNITS[$number];
        }

        if ($number < 20) {
            return self::terbilang($number - 10).' belas';
        }

        if ($number < 100) {
            return self::terbilang(intdiv($number, 10)).' puluh'.self::remainder($number % 10);
        }

        if ($number < 200) {
            return 'seratus'.self::remainder($number - 100);
        }

        if ($number < 1000) {
            return self::terbilang(intdiv($number, 100)).' ratus'.self::remainder($number % 100);
        }

        if ($number < 2000) {
            return 'seribu'.self::remainder($number - 1000);
        }

        if ($number < 1_000_000) {
            return self::terbilang(intdiv($number, 1000)).' ribu'.self::remainder($number % 1000);
        }

        if ($number < 1_000_000_000) {
            return self::terbilang(intdiv($number, 1_000_000)).' juta'.self::remainder($number % 1_000_000);
        }

        return self::terbilang(intdiv($number, 1_000_000_000)).' miliar'.self::remainder($number % 1_000_000_000);
    }

    private static function remainder(int $number): string
    {
        return $number === 0 ? '' : ' '.self::terbilang($number);
    }
}
