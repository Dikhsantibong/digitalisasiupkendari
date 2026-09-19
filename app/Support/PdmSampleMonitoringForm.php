<?php

namespace App\Support;

/**
 * Layout of the PdM "Form Monitoring Pemeriksaan & Pengiriman Sample": the
 * columns of sections A (pengiriman), B (hasil) and D (temuan), and section C
 * (rekap) computed from A & B. Shared by the input page, its PDF and Excel.
 *
 * @phpstan-type Column array{key: string, label: string, type?: string, options?: list<string>}
 */
final class PdmSampleMonitoringForm
{
    /** @var list<string> */
    public const JENIS_SAMPLE = ['Sample Oli Trafo', 'Sample Oli'];

    public const STATUS_TERKIRIM = ['Dikirim', 'Diterima Lab'];

    /**
     * @var array<string, array{title: string, default_rows: int, columns: list<Column>}>
     */
    public const SECTIONS = [
        'pengiriman' => [
            'title' => 'A. MONITORING PENGIRIMAN SAMPLE',
            'default_rows' => 12,
            'columns' => [
                ['key' => 'tanggal_pengambilan', 'label' => 'Tanggal Pengambilan', 'type' => 'date'],
                ['key' => 'tanggal_pengiriman', 'label' => 'Tanggal Pengiriman', 'type' => 'date'],
                ['key' => 'jenis_sample', 'label' => 'Jenis Sample', 'type' => 'jenis'],
                ['key' => 'unit_peralatan', 'label' => 'Unit / Peralatan'],
                ['key' => 'id_sn', 'label' => 'ID / SN Peralatan'],
                ['key' => 'volume', 'label' => 'Volume'],
                ['key' => 'laboratorium', 'label' => 'Tujuan / Laboratorium'],
                ['key' => 'no_resi', 'label' => 'No. Resi / Dokumen'],
                ['key' => 'status_pengiriman', 'label' => 'Status Pengiriman', 'type' => 'select', 'options' => ['Belum Dikirim', 'Dikirim', 'Diterima Lab']],
                ['key' => 'tanggal_diterima', 'label' => 'Tanggal Diterima', 'type' => 'date'],
                ['key' => 'keterangan', 'label' => 'Keterangan'],
            ],
        ],
        'hasil' => [
            'title' => 'B. MONITORING HASIL ANALISIS SAMPLE',
            'default_rows' => 8,
            'columns' => [
                ['key' => 'jenis_sample', 'label' => 'Jenis Sample', 'type' => 'jenis'],
                ['key' => 'unit_peralatan', 'label' => 'Unit / Peralatan'],
                ['key' => 'id_sn', 'label' => 'ID / SN'],
                ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'tanggal_hasil', 'label' => 'Tanggal Hasil', 'type' => 'date'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Menunggu Hasil', 'Hasil Diterima']],
                ['key' => 'hasil', 'label' => 'Hasil / Kesimpulan'],
                ['key' => 'rekomendasi', 'label' => 'Rekomendasi'],
                ['key' => 'tindak_lanjut', 'label' => 'Tindak Lanjut'],
                ['key' => 'status_tindak_lanjut', 'label' => 'Status Tindak Lanjut', 'type' => 'select', 'options' => ['Open', 'Progress', 'Close']],
                ['key' => 'keterangan', 'label' => 'Keterangan'],
            ],
        ],
        'temuan' => [
            'title' => 'D. TEMUAN DAN TINDAK LANJUT',
            'default_rows' => 6,
            'columns' => [
                ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'temuan', 'label' => 'Temuan / Ketidaksesuaian'],
                ['key' => 'dampak', 'label' => 'Dampak / Risiko'],
                ['key' => 'tindakan', 'label' => 'Tindakan yang Diperlukan'],
                ['key' => 'pic', 'label' => 'PIC'],
                ['key' => 'target_penyelesaian', 'label' => 'Target Penyelesaian', 'type' => 'date'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Open', 'Progress', 'Close']],
                ['key' => 'verifikasi', 'label' => 'Verifikasi'],
                ['key' => 'no_dokumen', 'label' => 'No. Dokumen'],
                ['key' => 'keterangan', 'label' => 'Keterangan'],
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(string $section): array
    {
        return array_column(self::SECTIONS[$section]['columns'], 'key');
    }

    /**
     * Keep only the section's columns as trimmed strings (empty → null).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    public static function clean(string $section, array $data): array
    {
        $clean = [];
        foreach (self::keys($section) as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            $clean[$key] = $value === '' ? null : $value;
        }

        return $clean;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public static function isBlank(array $data): bool
    {
        return array_filter($data, fn (?string $value): bool => $value !== null && $value !== '') === [];
    }

    /**
     * C. Rekap monitoring per jenis sample (+ TOTAL), computed from sections A & B.
     *
     * @param  list<array<string, string|null>>  $pengiriman
     * @param  list<array<string, string|null>>  $hasil
     * @param  array<string, array{target?: int|string|null, keterangan?: string|null}>  $targets
     * @return list<array{jenis: string, target: int|null, terkirim: int, belum_terkirim: int, hasil_diterima: int, menunggu: int, perlu_tindak_lanjut: int, status: string, keterangan: string}>
     */
    public static function rekap(array $pengiriman, array $hasil, array $targets): array
    {
        $is = fn (?string $value, string $jenis): bool => mb_strtolower(trim((string) $value)) === mb_strtolower($jenis);

        $rows = [];
        foreach (self::JENIS_SAMPLE as $jenis) {
            $sent = array_filter($pengiriman, fn (array $r): bool => $is($r['jenis_sample'] ?? null, $jenis));
            $terkirim = count(array_filter($sent, fn (array $r): bool => ! empty($r['tanggal_pengiriman']) || in_array($r['status_pengiriman'] ?? '', self::STATUS_TERKIRIM, true)));
            $results = array_filter($hasil, fn (array $r): bool => $is($r['jenis_sample'] ?? null, $jenis));
            $diterima = count(array_filter($results, fn (array $r): bool => ! empty($r['tanggal_hasil']) || ($r['status'] ?? '') === 'Hasil Diterima'));
            $perluTl = count(array_filter($results, fn (array $r): bool => ! empty($r['tindak_lanjut']) && ($r['status_tindak_lanjut'] ?? '') !== 'Close'));
            $target = isset($targets[$jenis]['target']) && $targets[$jenis]['target'] !== '' && $targets[$jenis]['target'] !== null
                ? (int) $targets[$jenis]['target'] : null;

            $rows[] = [
                'jenis' => $jenis,
                'target' => $target,
                'terkirim' => $terkirim,
                'belum_terkirim' => $target !== null ? max(0, $target - $terkirim) : count($sent) - $terkirim,
                'hasil_diterima' => $diterima,
                'menunggu' => max(0, $terkirim - $diterima),
                'perlu_tindak_lanjut' => $perluTl,
                'status' => self::status($target, $terkirim),
                'keterangan' => (string) ($targets[$jenis]['keterangan'] ?? ''),
            ];
        }

        $sum = fn (string $key): int => array_sum(array_column($rows, $key));
        $targetTotal = array_filter(array_column($rows, 'target'), fn (?int $t): bool => $t !== null) === [] ? null : $sum('target');
        $rows[] = [
            'jenis' => 'TOTAL',
            'target' => $targetTotal,
            'terkirim' => $sum('terkirim'),
            'belum_terkirim' => $sum('belum_terkirim'),
            'hasil_diterima' => $sum('hasil_diterima'),
            'menunggu' => $sum('menunggu'),
            'perlu_tindak_lanjut' => $sum('perlu_tindak_lanjut'),
            'status' => self::status($targetTotal, $sum('terkirim')),
            'keterangan' => '',
        ];

        return $rows;
    }

    private static function status(?int $target, int $terkirim): string
    {
        if ($target === null || $target === 0) {
            return '-';
        }

        return $terkirim >= $target ? 'Sesuai Target' : 'Belum Sesuai Target';
    }
}
