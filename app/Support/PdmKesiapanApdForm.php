<?php

namespace App\Support;

/**
 * Layout of the PdM "Kesiapan APD Bagian PdM Pembangkit" form: the items the
 * form starts with and the allowed answer of each assessment column. Shared by
 * the input page, its PDF and its Excel export.
 */
final class PdmKesiapanApdForm
{
    public const DEFAULT_KELOMPOK = 'Alat Pelindung Diri (APD)';

    /** @var list<array{inspeksi: string, satuan: string}> */
    public const DEFAULT_ITEMS = [
        ['inspeksi' => 'Sarung tangan kain bintik', 'satuan' => 'Set'],
        ['inspeksi' => 'Sarung tangan kulit (las)', 'satuan' => 'Set'],
        ['inspeksi' => 'Helm', 'satuan' => 'Buah'],
        ['inspeksi' => 'Safety shoes', 'satuan' => 'Pasang'],
        ['inspeksi' => 'Sepatu karet / boot', 'satuan' => 'Pasang'],
        ['inspeksi' => 'Kacamata bengkel', 'satuan' => 'Buah'],
        ['inspeksi' => 'Kacamata las listrik', 'satuan' => 'Buah'],
        ['inspeksi' => 'Pakaian kerja biasa', 'satuan' => 'Buah'],
        ['inspeksi' => 'Jas hujan', 'satuan' => 'Buah'],
        ['inspeksi' => 'Sabuk Pengaman/ body harness', 'satuan' => 'Set'],
        ['inspeksi' => 'Ear muff/Ear protector', 'satuan' => 'Buah'],
        ['inspeksi' => 'Ear plug', 'satuan' => 'Buah'],
        ['inspeksi' => 'Tongkat pentanahan', 'satuan' => 'Buah'],
        ['inspeksi' => 'Respirator', 'satuan' => 'Buah'],
    ];

    /**
     * Assessment columns in form order => allowed answers (empty = "-").
     *
     * @var array<string, list<string>>
     */
    public const OPTIONS = [
        'kelayakan_apd' => ['Layak', 'Tidak Layak'],
        'peralatan_jumlah' => ['Memenuhi', 'Tidak Memenuhi'],
        'peralatan_kelayakan' => ['Layak', 'Tidak Layak'],
        'sop_pnp' => ['Memenuhi', 'Tidak Memenuhi'],
        'sop_vendor' => ['Memenuhi', 'Tidak Memenuhi'],
        'p3k_kotak' => ['Ada', 'Tidak Ada'],
        'p3k_isi' => ['Ada', 'Tidak Ada'],
        'cara_kerja' => ['Ergonomi', 'Tidak Ergonomi'],
    ];

    /**
     * The rows a period starts with before anything is saved.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        return array_map(fn (array $item, int $index): array => [
            'id' => null,
            'kelompok' => self::DEFAULT_KELOMPOK,
            'no_urut' => $index + 1,
            'inspeksi' => $item['inspeksi'],
            'jumlah' => null,
            'satuan' => $item['satuan'],
            ...array_fill_keys(array_keys(self::OPTIONS), null),
            'keterangan' => '',
        ], self::DEFAULT_ITEMS, array_keys(self::DEFAULT_ITEMS));
    }
}
