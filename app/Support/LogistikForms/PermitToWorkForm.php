<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Permit To Work Pembangkit: per izin kerja the uraian, tanggal and
 * its status Open or Close (one tick per row), with the TOTAL of Open and
 * Close under the table — 30 numbered rows like the official sheet.
 */
class PermitToWorkForm extends LogistikForm
{
    public const ROWS = 30;

    public function key(): string
    {
        return 'permit-to-work';
    }

    public function title(): string
    {
        return 'Laporan Permit To Work Pembangkit';
    }

    public function kop(): string
    {
        return 'LAPORAN PERMIT TO WORK PEMBANGKIT';
    }

    public function description(): string
    {
        return 'Pencatatan izin kerja (Permit To Work) di area pembangkit: uraian pekerjaan, tanggal, dan status Open/Close dengan total per status.';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function columns(): array
    {
        $status = fn (string $key, string $label): array => self::col($key, $label, 'check', ['group' => 'STATUS', 'exclusive' => 'status', 'align' => 'c', 'width' => 80]);

        return [
            self::col('uraian', 'URAIAN', 'text', ['width' => 230]),
            self::col('tanggal', 'TANGGAL', 'date', ['align' => 'c', 'width' => 110]),
            $status('open', 'OPEN'),
            $status('close', 'CLOSE'),
        ];
    }

    public function sections(): array
    {
        return [self::section('ptw', 'LAPORAN PTW PEMBANGKIT', [], self::ROWS, ['open', 'close'])];
    }

    public function notes(): array
    {
        return ['Status diisi salah satu: OPEN (izin kerja masih berjalan) atau CLOSE (pekerjaan selesai & izin ditutup).'];
    }

    public function summary(array $rows): ?array
    {
        $permits = collect($rows)->flatten(1)->filter(fn (array $row): bool => trim((string) ($row['uraian'] ?? '')) !== '' || ! empty($row['tanggal']));
        $open = $permits->filter(fn (array $row): bool => (int) ($row['open'] ?? 0) === 1)->count();
        $close = $permits->filter(fn (array $row): bool => (int) ($row['close'] ?? 0) === 1)->count();

        return [
            'title' => 'REKAP PERMIT TO WORK',
            'columns' => ['TOTAL PTW', 'OPEN', 'CLOSE', 'BELUM ADA STATUS'],
            'rows' => [[$permits->count(), $open, $close, $permits->count() - $open - $close]],
        ];
    }
}
