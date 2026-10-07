<?php

namespace App\Services\Operasi;

use App\Models\Employee;
use App\Models\OperasiRekap;
use App\Models\Unit;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * Berita Acara Pemeriksaan Fisik Pelumas of a month: per jenis pelumas the
 * persediaan awal, penerimaan, stock, pemakaian sendiri, pengiriman and the
 * persediaan menurut administrasi — all from {@see PerincianPelumasSheet}, so
 * the three pelumas documents agree — next to the physical stock counted on
 * the inspection day (drum, cm, liter) and the selisih fisik − administrasi.
 *
 * Stored as an {@see OperasiRekap} (`ba-fisik-pelumas`): the header (nomor,
 * tanggal, pukul, signers) and the counted stock. The counted liters are the
 * default "sisa fisik" of Perincian and Rekap Pelumas.
 */
class BaFisikPelumasSheet
{
    public const JENIS = 'ba-fisik-pelumas';

    public function __construct(private readonly PerincianPelumasSheet $perincian) {}

    /**
     * The liters counted per jenis pelumas, for the other pelumas sheets.
     *
     * @return array<string, float>
     */
    public function counted(Unit $unit, int $month, int $year): array
    {
        $items = $this->record($unit, $month, $year)?->overrides['items'] ?? [];

        return array_map(fn (array $item): float => (float) $item['liter'], array_filter($items, fn (mixed $item): bool => is_array($item) && is_numeric($item['liter'] ?? null)));
    }

    public function record(Unit $unit, int $month, int $year): ?OperasiRekap
    {
        return OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * @return array{
     *     lubricants: list<array{key: string, name: string, code: string|null, unit_label: string, unit_of_measure: string}>,
     *     rows: array<string, array{awal: float, penerimaan: float, stock: float, pemakaian: float, pengiriman: float, administrasi: float}>,
     *     header: array{nomor: string, tanggal: string, pukul: string, mengetahui: string, mengetahui_jabatan: string, dibuat: string, dibuat_jabatan: string},
     *     items: array<string, array{drum: float|null, cm: float|null, liter: float|null}>,
     *     record: OperasiRekap|null
     * }
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $data = $this->perincian->build($unit, $month, $year);
        $totals = $this->perincian->totals($data);
        $keys = array_column($data['lubricants'], 'key');
        $record = $this->record($unit, $month, $year);
        $stored = $record?->overrides ?? [];

        $rows = [];
        foreach ($keys as $key) {
            $row = $totals[$key];
            $rows[$key] = [
                'awal' => $row['awal'],
                'penerimaan' => $row['jumlah_penerimaan'],
                'stock' => $row['total_persediaan'],
                'pemakaian' => round($row['jumlah_pemakaian'] + $row['non_mesin'], 2),
                'pengiriman' => $row['jumlah_pengiriman'],
                'administrasi' => $row['sisa'],
            ];
        }

        $tl = Employee::query()
            ->where('is_active', true)
            ->where('unit_id', $unit->id)
            ->where(fn ($query) => $query->where('position', 'like', '%team leader operasi%')->orWhere('position', 'like', '%tl operasi%'))
            ->orderBy('name')
            ->first();

        return [
            'lubricants' => $data['lubricants'],
            'rows' => $rows,
            'header' => [
                'nomor' => (string) ($stored['header']['nomor'] ?? ''),
                // The inspection is held on the first day after the month.
                'tanggal' => (string) ($stored['header']['tanggal'] ?? Carbon::create($year, $month, 1)->addMonth()->toDateString()),
                'pukul' => (string) ($stored['header']['pukul'] ?? '10.00'),
                'mengetahui' => (string) ($stored['header']['mengetahui'] ?? $unit->manager()?->name ?? ''),
                'mengetahui_jabatan' => (string) ($stored['header']['mengetahui_jabatan'] ?? 'Manajer'),
                'dibuat' => (string) ($stored['header']['dibuat'] ?? $tl?->name ?? ''),
                'dibuat_jabatan' => (string) ($stored['header']['dibuat_jabatan'] ?? 'Team Leader Operasi'),
            ],
            'items' => $this->items($stored['items'] ?? [], $keys),
            'record' => $record,
        ];
    }

    /**
     * Keep the counted stock of this unit's jenis pelumas (an empty count stays null).
     *
     * @param  array<array-key, mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, array{drum: float|null, cm: float|null, liter: float|null}>
     */
    public function items(array $input, array $keys): array
    {
        $number = fn (mixed $value): ?float => is_numeric($value) && (float) $value >= 0 ? round((float) $value, 2) : null;
        $items = [];

        foreach ($keys as $key) {
            $item = is_array($input[$key] ?? null) ? $input[$key] : [];
            $items[$key] = ['drum' => $number($item['drum'] ?? null), 'cm' => $number($item['cm'] ?? null), 'liter' => $number($item['liter'] ?? null)];
        }

        return $items;
    }

    /**
     * The opening sentence: "Pada hari ini Kamis Tanggal Satu Bulan Oktober
     * Tahun Dua Ribu Dua Puluh Enam (01-10-2026) pukul 10.00 Wita …".
     */
    public function opening(Unit $unit, string $tanggal, string $pukul): string
    {
        $date = Carbon::parse($tanggal);

        return sprintf(
            'Pada hari ini %s Tanggal %s Bulan %s Tahun %s (%s) pukul %s Wita, telah diadakan pemeriksaan Fisik Pelumas pada %s dengan hasil sebagai berikut :',
            Indonesian::dayName($date),
            ucwords(Indonesian::terbilang($date->day)),
            Indonesian::monthName((int) $date->month),
            ucwords(Indonesian::terbilang($date->year)),
            $date->format('d-m-Y'),
            $pukul,
            $unit->name,
        );
    }
}
