<?php

namespace App\Services\Keuangan;

use App\Enums\EmployeePosition;
use App\Models\CashCardEntry;
use App\Models\CashCardReport;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Supplier;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The printed documents of the Laporan Realisasi Cash Card of a UL (user,
 * 2026-11-20):
 * - the laporan: every row with its running saldo, the totals, saldo awal /
 *   penerimaan / pengeluaran / saldo akhir, Disetujui Manager UL and Dibuat
 *   Oleh JOF Administrasi Umum;
 * - per row the Bukti Penerimaan Kas Kecil (nomor bukti, terima dari, the
 *   amount spelled out, uraian, kode perkiraan, tanggal, kode SAP, cost
 *   centre) and its Lampiran: the row's photos (nota, barang) plus the
 *   supplier's KTP, e-mail and NPWP from the Master Supplier.
 */
class CashCardDocument
{
    /**
     * Rows with their running saldo and the report totals.
     *
     * @return array{rows: list<array<string, mixed>>, total_penerimaan: float, total_pengeluaran: float, saldo_awal: float, saldo_akhir: float}
     */
    public function ledger(CashCardReport $report): array
    {
        $saldoAwal = (float) $report->saldo_awal;
        $saldo = $saldoAwal;
        $in = 0.0;
        $out = 0.0;
        $rows = [];

        foreach ($report->entries()->with('supplier')->get() as $index => $entry) {
            $penerimaan = (float) $entry->penerimaan;
            $pengeluaran = (float) $entry->pengeluaran;
            $saldo += $penerimaan - $pengeluaran;
            $in += $penerimaan;
            $out += $pengeluaran;
            $rows[] = [
                'no' => $index + 1,
                'entry' => $entry,
                'tanggal' => $entry->tanggal?->format('d/m/Y') ?? '',
                'uraian' => self::uraian($entry),
                'penerimaan' => $penerimaan,
                'pengeluaran' => $pengeluaran,
                'saldo' => $saldo,
            ];
        }

        return ['rows' => $rows, 'total_penerimaan' => $in, 'total_pengeluaran' => $out, 'saldo_awal' => $saldoAwal, 'saldo_akhir' => $saldo];
    }

    /**
     * The uraian as printed: the text, then " / SUPPLIER" when a supplier is
     * chosen and the text does not name it yet.
     */
    public static function uraian(CashCardEntry $entry): string
    {
        $text = trim((string) $entry->uraian);
        $supplier = $entry->supplier?->name;

        if ($supplier === null || Str::contains(Str::lower($text), Str::lower($supplier))) {
            return $text;
        }

        return $text === '' ? $supplier : "{$text} / {$supplier}";
    }

    /**
     * Data of the laporan PDF.
     *
     * @return array<string, mixed>
     */
    public function laporan(CashCardReport $report): array
    {
        $serviceUnit = $report->serviceUnit;
        $lastDay = Carbon::create($report->year, $report->month, 1)->endOfMonth();

        return [
            'ul' => $serviceUnit,
            'ulLabel' => self::ulLabel($serviceUnit),
            'periodLabel' => 'PERIODE 01 S/D '.$lastDay->day.' '.mb_strtoupper(Indonesian::monthName($report->month)).' '.$report->year,
            'ledger' => $this->ledger($report),
            'signers' => $this->signers($serviceUnit),
            'tempatTanggal' => 'Kendari, '.$lastDay->day.' '.Indonesian::monthName($report->month).' '.$report->year,
            'logo' => self::image(public_path('logo/sidebar-logo.png')),
        ];
    }

    /**
     * Data of the bukti & lampiran PDF of some rows.
     *
     * @param  list<array<string, mixed>>  $ledgerRows  rows of {@see self::ledger()}
     * @param  bool  $urls  photos as their public URL (the document editor) instead of data URIs (the PDF)
     * @return array<string, mixed>
     */
    public function bukti(CashCardReport $report, array $ledgerRows, bool $urls = false): array
    {
        $image = fn (string $path): ?string => $urls ? self::url($path) : self::stored($path);

        $serviceUnit = $report->serviceUnit;

        return [
            'ulLabel' => self::ulLabel($serviceUnit),
            'signers' => $this->signers($serviceUnit),
            'logo' => self::image(public_path('logo/sidebar-logo.png')),
            'items' => array_map(function (array $row) use ($image): array {
                /** @var CashCardEntry $entry */
                $entry = $row['entry'];
                $amount = $row['pengeluaran'] > 0 ? $row['pengeluaran'] : $row['penerimaan'];
                $supplier = $entry->supplier;

                return [
                    'nomor' => $entry->no_entry ?: $row['no'],
                    'terima_dari' => $supplier?->name ?? '',
                    'terbilang' => self::terbilang($amount),
                    'jumlah' => $amount,
                    'uraian' => $row['uraian'],
                    'tanggal' => $row['tanggal'],
                    'kode_perkiraan' => $entry->kode_perkiraan,
                    'kode_sap' => $entry->kode_sap,
                    'cost_centre' => $entry->cost_centre,
                    'photos' => array_values(array_filter(array_map($image, $entry->photos ?? []))),
                    'supplier' => $supplier === null ? null : [
                        'name' => $supplier->name,
                        'email' => $supplier->email,
                        'npwp' => $supplier->npwp,
                        'ktp' => $supplier->ktp_path !== null ? $image($supplier->ktp_path) : null,
                    ],
                ];
            }, $ledgerRows),
        ];
    }

    /**
     * "Sembilan Ratus Dua Puluh Lima Ribu Lima Ratus Rupiah".
     */
    public static function terbilang(float $amount): string
    {
        $rupiah = (int) round($amount);

        return $rupiah === 0 ? 'Nol Rupiah' : Str::title(Indonesian::terbilang($rupiah)).' Rupiah';
    }

    /**
     * Manager UL (Disetujui / Mengetahui) and JOF Administrasi Umum (Dibuat Oleh / Diperiksa Oleh) of the UL.
     *
     * @return array{manager: string, jof: string}
     */
    public function signers(ServiceUnit $serviceUnit): array
    {
        $holder = fn (EmployeePosition $position): ?Employee => Employee::query()
            ->where('singleton_key', $position->singletonKey(null, $serviceUnit->id))
            ->first();

        return [
            'manager' => (string) ($holder(EmployeePosition::ManagerUl)?->name ?? $serviceUnit->manager()?->name ?? ''),
            'jof' => (string) ($holder(EmployeePosition::JofAdministrasiUmum)?->name ?? ''),
        ];
    }

    /**
     * "ULPLTD POASIA" from "UL PLTD Poasia".
     */
    public static function ulLabel(ServiceUnit $serviceUnit): string
    {
        return mb_strtoupper((string) preg_replace('/^UL\s+/i', 'UL', (string) $serviceUnit->name));
    }

    /**
     * A stored (public disk) image as a data URI for dompdf, or null when missing.
     */
    public static function stored(string $path): ?string
    {
        $disk = Storage::disk('public');

        return $disk->exists($path)
            ? 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path))
            : null;
    }

    private static function image(string $path): ?string
    {
        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * The public URL of a stored photo, for the page.
     */
    public static function url(string $path): ?string
    {
        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whether the supplier belongs to the UL of the report.
     */
    public static function supplierOf(ServiceUnit $serviceUnit, ?int $supplierId): ?Supplier
    {
        return $supplierId === null ? null : Supplier::query()->where('service_unit_id', $serviceUnit->id)->find($supplierId);
    }
}
