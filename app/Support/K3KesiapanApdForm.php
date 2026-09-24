<?php

namespace App\Support;

/**
 * Template defaults and options for Form Inspeksi K3 (Kesiapan APD).
 * Matches the official PLN NP UP Kendari Form Inspeksi K3 sheet.
 */
final class K3KesiapanApdForm
{
    public const GROUPS = [
        'I. Alat Pelindung Diri (APD)',
        'II. Peralatan Kerja',
        'III. Prosedur/ IK',
        'IV. P3K',
        'V. Area Kerja',
        'VI. Cara Kerja/ Ergonomi',
        'VII. Dan lain-lain',
    ];

    /**
     * Default items per group matching the official template.
     *
     * @var array<string, list<array{no: string, inspeksi: string, apd_jumlah?: string, kelayakan_apd?: string, peralatan_jumlah?: string, peralatan_kelayakan?: string, sop_pnp?: string, sop_vendor?: string, p3k_ada?: string, p3k_memenuhi?: string, cara_kerja?: string, keterangan?: string}>>
     */
    public const DEFAULTS = [
        'I. Alat Pelindung Diri (APD)' => [
            ['no' => '1', 'inspeksi' => 'Sarung tangan kain bintik', 'apd_jumlah' => '5 set', 'kelayakan_apd' => 'Layak'],
            ['no' => '2', 'inspeksi' => 'Sarung tangan kulit (las)', 'apd_jumlah' => '1 set', 'kelayakan_apd' => 'Layak'],
            ['no' => '3', 'inspeksi' => 'Helm', 'apd_jumlah' => '15 buah', 'kelayakan_apd' => 'Layak'],
            ['no' => '4', 'inspeksi' => 'Safety shoes', 'apd_jumlah' => '15 pasang', 'kelayakan_apd' => 'Layak'],
            ['no' => '5', 'inspeksi' => 'Sepatu karet / boot', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '6', 'inspeksi' => 'Kacamata bengkel', 'apd_jumlah' => '1 buah', 'kelayakan_apd' => 'Layak'],
            ['no' => '7', 'inspeksi' => 'Kacamata las listrik', 'apd_jumlah' => '1 buah', 'kelayakan_apd' => 'Layak'],
            ['no' => '8', 'inspeksi' => 'Pakaian kerja biasa', 'apd_jumlah' => '15 buah', 'kelayakan_apd' => 'Layak'],
            ['no' => '9', 'inspeksi' => 'Jas hujan', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '10', 'inspeksi' => 'Sabuk Pengaman/ body harness', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '11', 'inspeksi' => 'Ear muff/Ear protector', 'apd_jumlah' => '3 set', 'kelayakan_apd' => 'Layak'],
            ['no' => '12', 'inspeksi' => 'Ear plug', 'apd_jumlah' => '1 Box', 'kelayakan_apd' => 'Layak'],
            ['no' => '13', 'inspeksi' => 'Tongkat pentanahan', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '14', 'inspeksi' => 'Respirator', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '15', 'inspeksi' => 'Helm Pemadam', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '16', 'inspeksi' => 'Baju & Celana Pemadam', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '17', 'inspeksi' => 'Sarung tangan pemadam', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '18', 'inspeksi' => 'Sepatu pemadam', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '19', 'inspeksi' => 'SCBA (oksigen)', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '20', 'inspeksi' => 'Gloves / Sarung tangan 20 KV', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '21', 'inspeksi' => 'Safety shoes 20 KV', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
            ['no' => '22', 'inspeksi' => 'Baju rompi Security', 'apd_jumlah' => '-', 'kelayakan_apd' => '-'],
        ],
        'II. Peralatan Kerja' => [
            ['no' => '1', 'inspeksi' => 'APAR', 'peralatan_jumlah' => '7 buah', 'peralatan_kelayakan' => 'Layak', 'keterangan' => '7 bh apar expired Agustus 2026'],
            ['no' => '2', 'inspeksi' => 'APAB', 'peralatan_jumlah' => '1 buah', 'peralatan_kelayakan' => 'Tidak Layak', 'keterangan' => '1 bh apab expired Oktober 2018'],
            ['no' => '3', 'inspeksi' => 'APAT', 'peralatan_jumlah' => '-', 'peralatan_kelayakan' => '-'],
            ['no' => '4', 'inspeksi' => 'Pompa Hydrant', 'peralatan_jumlah' => '-', 'peralatan_kelayakan' => '-'],
            ['no' => '5', 'inspeksi' => 'Box Hydrant', 'peralatan_jumlah' => '-', 'peralatan_kelayakan' => '-'],
            ['no' => '6', 'inspeksi' => 'Tespen 6,3 KV - 20 KV', 'peralatan_jumlah' => '-', 'peralatan_kelayakan' => '-'],
        ],
        'III. Prosedur/ IK' => [
            ['no' => '1', 'inspeksi' => 'SOP Diesel Emergency', 'sop_pnp' => '-', 'sop_vendor' => '-'],
            ['no' => '2', 'inspeksi' => 'IK Operasi Hydrant', 'sop_pnp' => '-', 'sop_vendor' => '-'],
            ['no' => '3', 'inspeksi' => 'IK Emergency Diesel', 'sop_pnp' => '-', 'sop_vendor' => '-'],
        ],
        'IV. P3K' => [
            ['no' => '1', 'inspeksi' => 'Ruang Container', 'p3k_ada' => 'Tidak ada', 'p3k_memenuhi' => 'Tidak memenuhi'],
            ['no' => '2', 'inspeksi' => 'Ruang Workshop', 'p3k_ada' => 'Tidak ada', 'p3k_memenuhi' => 'Tidak memenuhi'],
            ['no' => '3', 'inspeksi' => 'Gedung TPS B3', 'p3k_ada' => 'Tidak ada', 'p3k_memenuhi' => 'Tidak memenuhi'],
            ['no' => '4', 'inspeksi' => '-', 'p3k_ada' => '-', 'p3k_memenuhi' => '-'],
            ['no' => '5', 'inspeksi' => '-', 'p3k_ada' => '-', 'p3k_memenuhi' => '-'],
        ],
        'V. Area Kerja' => [
            ['no' => '1', 'inspeksi' => 'Pos Keamanan', 'peralatan_kelayakan' => 'Tidak ada'],
            ['no' => '2', 'inspeksi' => 'Halaman Parkir', 'peralatan_kelayakan' => 'Tidak ada'],
            ['no' => '3', 'inspeksi' => 'Ruang Kantor', 'peralatan_kelayakan' => 'Aman'],
            ['no' => '4', 'inspeksi' => 'Ruang Pembangkit', 'peralatan_kelayakan' => 'Aman'],
        ],
        'VI. Cara Kerja/ Ergonomi' => [
            ['no' => '1', 'inspeksi' => 'Cara Menata ...', 'cara_kerja' => 'Ergonomi'],
            ['no' => '2', 'inspeksi' => 'Cara Duduk ....', 'cara_kerja' => 'Ergonomi'],
            ['no' => '3', 'inspeksi' => 'Cara Mengangkat', 'cara_kerja' => 'Ergonomi'],
        ],
        'VII. Dan lain-lain' => [
            ['no' => '1', 'inspeksi' => '(Item 1)', 'keterangan' => ''],
        ],
    ];

    /**
     * Dropdown selectable options for fields.
     */
    public const OPTIONS = [
        'kelayakan_apd' => ['Layak', 'Tidak Layak', '-'],
        'peralatan_kelayakan' => ['Layak', 'Tidak Layak', 'Aman', 'Tidak ada', '-'],
        'sop_pnp' => ['Memenuhi', 'Tidak memenuhi', '-'],
        'sop_vendor' => ['Memenuhi', 'Tidak memenuhi', '-'],
        'p3k_ada' => ['Ada', 'Tidak ada', '-'],
        'p3k_memenuhi' => ['Memenuhi', 'Tidak memenuhi', '-'],
        'cara_kerja' => ['Ergonomi', 'Tdk Ergonomi', '-'],
    ];

    /**
     * Builds default rows array for pre-filling.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        $rows = [];
        $sortOrder = 0;

        foreach (self::DEFAULTS as $group => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    'id' => null,
                    'kelompok' => $group,
                    'no_urut' => $item['no'] ?? null,
                    'inspeksi' => $item['inspeksi'],
                    'apd_jumlah' => $item['apd_jumlah'] ?? '-',
                    'kelayakan_apd' => $item['kelayakan_apd'] ?? '-',
                    'peralatan_jumlah' => $item['peralatan_jumlah'] ?? '-',
                    'peralatan_kelayakan' => $item['peralatan_kelayakan'] ?? '-',
                    'sop_pnp' => $item['sop_pnp'] ?? '-',
                    'sop_vendor' => $item['sop_vendor'] ?? '-',
                    'p3k_ada' => $item['p3k_ada'] ?? '-',
                    'p3k_memenuhi' => $item['p3k_memenuhi'] ?? '-',
                    'cara_kerja' => $item['cara_kerja'] ?? '-',
                    'keterangan' => $item['keterangan'] ?? '',
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $rows;
    }
}
