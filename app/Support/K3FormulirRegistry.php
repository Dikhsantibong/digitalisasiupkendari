<?php

namespace App\Support;

use App\Enums\EmployeePosition;
use Illuminate\Support\Carbon;

/**
 * Definisi Formulir K3 berbasis lembar (kolom, baris default, penandatangan,
 * periode) yang dilayani K3\FormulirRecordController dan disimpan di
 * k3_formulir_records. Menambah formulir baru = menambah entri di sini
 * + halaman tipis `resources/js/pages/k3/formulir/{key}/index.tsx`.
 *
 * @phpstan-type Column array{key: string, label: string, type: string, options?: list<string>, group?: string, width?: string, align?: string, item?: bool}
 * @phpstan-type Section array{key: string, label: string|null, letter: string|null, columns: list<Column>, rows: list<array<string, string>>, addable: bool}
 * @phpstan-type Signer array{key: string, label: string, title: string, positions: list<string>}
 */
final class K3FormulirRegistry
{
    private const KONDISI_BAIK_RUSAK = ['-', 'Baik', 'Rusak'];

    private const KONDISI = ['-', 'Baik', 'Kurang Baik', 'Rusak', 'Tidak Ada'];

    private const STATUS_SESUAI = ['-', 'Sesuai', 'Tidak Sesuai'];

    private const STATUS_TEMUAN = ['-', 'Open', 'On Progress', 'Close'];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * Definisi lengkap satu formulir.
     *
     * @return array{key: string, title: string, description: string, orientation: string, period: string, title_with_period: bool, single_table: bool, header_fields: list<array{key: string, label: string}>, notes_label: string|null, sign_place: bool, signers: list<Signer>, sections: list<Section>, body_view: string}
     */
    public static function get(string $key): array
    {
        $definition = self::definitions()[$key] ?? abort(404);

        return [
            'key' => $key,
            'orientation' => 'portrait',
            'period' => 'monthly',
            'title_with_period' => false,
            'single_table' => false,
            'header_fields' => [],
            'notes_label' => 'Catatan / Rekomendasi',
            'sign_place' => false,
            'signers' => self::defaultSigners(),
            'body_view' => 'k3.formulir.record.body',
            ...$definition,
        ];
    }

    /**
     * Baris default tiap section, nilai kosong diisi '' agar semua kolom ada.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, list<array<string, string>>>
     */
    public static function defaultSections(array $form): array
    {
        $sections = [];
        foreach ($form['sections'] as $section) {
            $sections[$section['key']] = array_map(
                fn (array $row): array => self::normalizeRow($section, $row),
                $section['rows'],
            );
        }

        return $sections;
    }

    /**
     * Rapikan isi sections kiriman klien: hanya section & kolom terdaftar, nilai string.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $sections
     * @return array<string, list<array<string, string>>>
     */
    public static function sanitizeSections(array $form, array $sections): array
    {
        $clean = [];
        foreach ($form['sections'] as $section) {
            $rows = is_array($sections[$section['key']] ?? null) ? $sections[$section['key']] : [];
            $clean[$section['key']] = array_values(array_map(
                fn (mixed $row): array => self::normalizeRow($section, is_array($row) ? $row : []),
                $rows,
            ));
        }

        return $clean;
    }

    /**
     * Nilai sel untuk dokumen cetak (tanggal ISO => "31 Agustus 2026").
     *
     * @param  array{type: string}  $column
     */
    public static function displayValue(array $column, ?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return in_array($column['type'], ['date', 'select'], true) ? '-' : '';
        }

        if ($column['type'] === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return Indonesian::longDate(Carbon::parse($value));
        }

        return $value;
    }

    /**
     * Label periode dokumen, mis. "Agustus 2026" atau "Minggu ke-2 Agustus 2026".
     */
    public static function periodLabel(int $month, int $year, int $week = 0): string
    {
        $label = Indonesian::monthName($month).' '.$year;

        return $week > 0 ? "Minggu ke-{$week} {$label}" : $label;
    }

    /**
     * Default "Kendari, 1 {bulan berikutnya}" seperti lembar resmi.
     */
    public static function defaultSignPlaceDate(int $month, int $year): string
    {
        return 'Kendari, '.Indonesian::longDate(Carbon::create($year, $month, 1)->addMonthNoOverflow());
    }

    /**
     * @param  array{columns: list<array{key: string}>}  $section
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function normalizeRow(array $section, array $row): array
    {
        $normalized = [];
        foreach ($section['columns'] as $column) {
            $value = $row[$column['key']] ?? '';
            $normalized[$column['key']] = is_scalar($value) ? mb_substr(trim((string) $value), 0, 1000) : '';
        }

        return $normalized;
    }

    /**
     * @return list<Signer>
     */
    public static function defaultSigners(): array
    {
        return [
            ['key' => 'manager_ul', 'label' => 'Mengetahui,', 'title' => '', 'positions' => []],
            ['key' => 'tl_k3', 'label' => 'Diperiksa,', 'title' => 'Team Leader K3 & Keamanan', 'positions' => [EmployeePosition::TeamLeaderK3->value]],
            ['key' => 'staff_k3', 'label' => 'Dibuat,', 'title' => 'Staff K3', 'positions' => [EmployeePosition::OfficeK3->value, EmployeePosition::KoordinatorK3->value]],
        ];
    }

    /**
     * Penandatangan lembar Laporan Project: Koordinator Project & Officer K3L.
     *
     * @return list<Signer>
     */
    private static function projectSigners(): array
    {
        return [
            ['key' => 'manager_ul', 'label' => 'Mengetahui,', 'title' => 'Koordinator Project', 'positions' => [EmployeePosition::ProjectLeader->value]],
            ['key' => 'staff_k3', 'label' => 'Dibuat oleh,', 'title' => 'Officer K3L', 'positions' => [EmployeePosition::OfficeK3->value, EmployeePosition::KoordinatorK3->value]],
        ];
    }

    /**
     * @param  list<string>  $options
     * @return array{key: string, label: string, type: string, options: list<string>, align: string}
     */
    private static function select(string $key, string $label, array $options): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options, 'align' => 'center'];
    }

    /**
     * @return array{key: string, label: string, type: string, align?: string, width?: string}
     */
    private static function text(string $key, string $label, ?string $align = null, ?string $width = null): array
    {
        return array_filter(['key' => $key, 'label' => $label, 'type' => 'text', 'align' => $align, 'width' => $width]);
    }

    /**
     * @return array{key: string, label: string, type: string, item: bool, width: string}
     */
    private static function item(string $label, string $width = '18%'): array
    {
        return ['key' => 'item', 'label' => $label, 'type' => 'text', 'item' => true, 'width' => $width];
    }

    /**
     * @param  list<string>  $items
     * @return list<array<string, string>>
     */
    private static function itemRows(array $items, array $defaults = []): array
    {
        return array_map(fn (string $item): array => ['item' => $item, ...$defaults], $items);
    }

    /**
     * Section "Sarana/Prasarana" untuk lembar pemeliharaan (TPS LB3, Oil Trap).
     *
     * @param  list<array<string, string>>  $rows
     * @return list<Section>
     */
    private static function maintenanceSections(array $rows): array
    {
        return [[
            'key' => 'sarana',
            'label' => null,
            'letter' => null,
            'addable' => true,
            'columns' => [
                self::item('Sarana/Prasarana', '22%'),
                ['key' => 'tanggal_inspeksi', 'label' => 'Tanggal Inspeksi', 'type' => 'date', 'align' => 'center', 'width' => '14%'],
                self::select('kondisi', 'Kondisi (Baik/Rusak)', self::KONDISI_BAIK_RUSAK),
                self::text('rencana_perbaikan', 'Rencana Perbaikan (Bila Rusak)', null, '22%'),
                self::text('keterangan', 'Keterangan', 'center', '26%'),
            ],
            'rows' => $rows,
        ]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        $kondisiMasaBerlaku = fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'type' => 'text', 'align' => 'center', 'group' => 'Kondisi / Masa Berlaku', 'width' => '7%'];
        $saranaColumns = [
            self::item('Uraian', '25%'),
            self::text('status_kepemilikan', 'Status Kepemilikan', 'center', '10%'),
            self::text('satuan', 'Satuan', 'center', '8%'),
            self::text('standar_kontrak', 'Standar Kontrak', 'center', '8%'),
            $kondisiMasaBerlaku('ada', 'Ada'),
            $kondisiMasaBerlaku('tidak', 'Tidak'),
            $kondisiMasaBerlaku('operasi_berlaku', 'Operasi / Berlaku'),
            $kondisiMasaBerlaku('rusak_kadaluarsa', 'Rusak / Kadaluarsa'),
            self::text('keterangan', 'Keterangan'),
        ];
        $sarana = fn (string $item, string $status, string $satuan, string $ada = '-', string $operasi = '-'): array => [
            'item' => $item, 'status_kepemilikan' => $status, 'satuan' => $satuan,
            'ada' => $ada, 'tidak' => '-', 'operasi_berlaku' => $operasi, 'rusak_kadaluarsa' => '-',
        ];
        $tpsDefault = ['tanggal_inspeksi' => '-', 'kondisi' => '-'];
        $oilTrapDefault = ['tanggal_inspeksi' => '-', 'kondisi' => '-', 'keterangan' => 'Fasilitas penunjang melengket pada ULPLTD Poasia'];

        return [
            'sarana-prasarana' => [
                'title' => 'Formulir Atribut, Peralatan, Administrasi dan Sarana Prasarana',
                'description' => 'Kelengkapan atribut & peralatan, administrasi, serta sarana prasarana keamanan.',
                'single_table' => true,
                'sections' => [
                    [
                        'key' => 'atribut_peralatan', 'label' => 'Atribut & Peralatan', 'letter' => 'A', 'addable' => true,
                        'columns' => $saranaColumns,
                        'rows' => [
                            $sarana('Tongkat T Karet', '', 'Unit'),
                            $sarana('Borgol', '', 'Unit'),
                            $sarana('Metal Detector Inspection', '', 'Unit'),
                            $sarana('Mirror Detector Inspection', '', 'Unit'),
                            $sarana('Patrol Sistem', '', 'Unit'),
                            $sarana('Handy Talky / HT', 'PLNNP', 'Unit', '1', '1'),
                            $sarana('Handy Talky / HT', '', 'Unit'),
                            $sarana('Radio Rig / Base Station', '', 'Unit'),
                            $sarana('Repeater', '', 'Unit'),
                            $sarana('Stick Lalu Lintas', '', 'Unit'),
                            $sarana('Lampu Senter', 'PLNNP', 'Unit', '2', '2'),
                            $sarana('Emergency Lamp', '', 'Unit'),
                            $sarana('HP', '', 'Unit'),
                            $sarana('Kamera', '', 'Unit'),
                            $sarana('Sepatu Boots', '', 'Unit'),
                            $sarana('Payung', '', 'Titik', '4', '4'),
                            $sarana('Jas Hujan', '', 'Unit'),
                            $sarana('Rompi Patroli', '', 'Unit', '3'),
                            $sarana('Lain-lain', '', 'Unit'),
                        ],
                    ],
                    [
                        'key' => 'administrasi', 'label' => 'Administrasi', 'letter' => 'B', 'addable' => true,
                        'columns' => $saranaColumns,
                        'rows' => [
                            $sarana('Kartu Tanda Anggota / KTA', '', 'Lembar'),
                            $sarana('Surat Ijin Operasional BUJP', '', 'Lembar'),
                            $sarana('Ijin Frekuensi (ISR)', '', 'Jumlah'),
                        ],
                    ],
                    [
                        'key' => 'sarana_prasarana', 'label' => 'Sarana & Prasarana', 'letter' => 'C', 'addable' => true,
                        'columns' => $saranaColumns,
                        'rows' => [
                            $sarana('Close Circuit Television / CCTV', 'PLNNP', 'Titik'),
                            $sarana('Barrier Gate / Portal', '', 'Unit'),
                            $sarana('Motor Patroli', '', 'Unit'),
                            $sarana('Mobil Patroli', '', 'Unit'),
                            $sarana('Sepeda Kayuh', '', 'Unit'),
                            $sarana('Perimeter', '', 'Meter'),
                            $sarana('Gerbang Utama', 'PLNNP', 'Unit'),
                            $sarana('Pos', 'PLNNP', 'Titik', '1'),
                            $sarana('Turnstile', '', 'Unit'),
                            $sarana('Lain-lain', '', 'Unit'),
                        ],
                    ],
                ],
            ],

            'kontrol-mingguan' => [
                'title' => 'Form Kontrol K3 Mingguan',
                'description' => 'Kontrol mingguan APD, peralatan emergency, housekeeping, pekerjaan berisiko, dan temuan.',
                'orientation' => 'landscape',
                'period' => 'weekly',
                'header_fields' => [
                    ['key' => 'periode_pemeriksaan', 'label' => 'Periode Pemeriksaan'],
                    ['key' => 'nama_petugas', 'label' => 'Nama Petugas Pemeriksa'],
                ],
                'notes_label' => 'F. Catatan Pemeriksaan Mingguan',
                'sections' => [
                    [
                        'key' => 'kontrol_apd', 'label' => 'Kontrol APD', 'letter' => 'A', 'addable' => true,
                        'columns' => [
                            self::item('Item APD'),
                            self::text('spesifikasi', 'Spesifikasi / Standar'),
                            self::text('jumlah_standar', 'Jumlah Standar', 'center'),
                            self::text('jumlah_aktual', 'Jumlah Aktual', 'center'),
                            self::text('satuan', 'Satuan', 'center'),
                            self::select('kondisi', 'Kondisi', self::KONDISI),
                            self::select('status', 'Status', self::STATUS_SESUAI),
                            self::text('lokasi', 'Lokasi'),
                            self::text('pic', 'PIC', 'center'),
                            self::text('keterangan', 'Keterangan'),
                        ],
                        'rows' => self::itemRows(['Helm Keselamatan', 'Safety Shoes', 'Sarung Tangan', 'Safety Glasses / Goggles', 'Ear Plug / Ear Muff', 'Masker / Respirator', 'APD Khusus Pekerjaan', 'APD Lainnya']),
                    ],
                    [
                        'key' => 'peralatan_emergency', 'label' => 'Kontrol Peralatan K3 dan Emergency', 'letter' => 'B', 'addable' => true,
                        'columns' => [
                            self::item('Peralatan / Fasilitas K3'),
                            self::text('kriteria', 'Kriteria / Standar'),
                            self::select('kondisi', 'Kondisi', self::KONDISI),
                            self::select('status', 'Status', self::STATUS_SESUAI),
                            self::text('lokasi', 'Lokasi'),
                            ['key' => 'tanggal_pemeriksaan', 'label' => 'Tanggal Pemeriksaan', 'type' => 'date', 'align' => 'center'],
                            self::text('masa_berlaku', 'Masa Berlaku / Expired', 'center'),
                            self::text('pic', 'PIC', 'center'),
                            self::text('tindak_lanjut', 'Tindak Lanjut'),
                            self::text('keterangan', 'Keterangan'),
                        ],
                        'rows' => self::itemRows(['APAR', 'Kotak P3K', 'Emergency Shower / Eyewash', 'Rambu Keselamatan', 'Jalur Evakuasi', 'Lampu Emergency', 'Spill Kit', 'Peralatan Emergency Lainnya']),
                    ],
                    [
                        'key' => 'housekeeping', 'label' => 'Housekeeping dan Kondisi Area Kerja', 'letter' => 'C', 'addable' => true,
                        'columns' => [
                            self::item('Item Pemeriksaan'),
                            self::text('kriteria', 'Kriteria / Standar'),
                            self::select('kondisi', 'Kondisi', self::KONDISI),
                            self::select('status', 'Status', self::STATUS_SESUAI),
                            self::text('temuan', 'Temuan'),
                            self::text('tindakan', 'Tindakan'),
                            self::text('pic', 'PIC', 'center'),
                            self::text('target', 'Target', 'center'),
                            self::text('verifikasi', 'Verifikasi', 'center'),
                            self::text('keterangan', 'Keterangan'),
                        ],
                        'rows' => self::itemRows(['Kebersihan area kerja', 'Potensi bahaya tersandung / jatuh', 'Tumpahan oli / BBM / bahan kimia', 'Kondisi lantai dan akses kerja', 'Jalur evakuasi tidak terhalang', 'Penataan material dan peralatan', 'Pencahayaan dan ventilasi', 'Kondisi area kerja lainnya']),
                    ],
                    [
                        'key' => 'pekerjaan_berisiko', 'label' => 'Kontrol Pekerjaan Berisiko', 'letter' => 'D', 'addable' => true,
                        'columns' => [
                            self::item('Jenis Pekerjaan / Aktivitas'),
                            self::text('kontrol_k3', 'Kontrol K3'),
                            self::select('kondisi', 'Kondisi', self::KONDISI),
                            self::select('status', 'Status', self::STATUS_SESUAI),
                            self::text('potensi_bahaya', 'Potensi Bahaya'),
                            self::text('tindakan_pengendalian', 'Tindakan Pengendalian'),
                            self::text('pic', 'PIC', 'center'),
                            self::text('target', 'Target', 'center'),
                            self::text('verifikasi', 'Verifikasi', 'center'),
                            self::text('keterangan', 'Keterangan'),
                        ],
                        'rows' => self::itemRows(['Permit to Work', 'LOTO / Isolasi Energi', 'Pekerjaan Panas / Hot Work', 'Bekerja di Ketinggian', 'Pekerjaan di Ruang Terbatas', 'Penggunaan Alat Kerja', 'Pekerjaan Berisiko Lainnya']),
                    ],
                    [
                        'key' => 'temuan', 'label' => 'Temuan dan Tindak Lanjut', 'letter' => 'E', 'addable' => true,
                        'columns' => [
                            ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date', 'align' => 'center'],
                            self::text('temuan', 'Temuan / Ketidaksesuaian'),
                            self::text('potensi_bahaya', 'Potensi Bahaya'),
                            self::text('risiko', 'Risiko'),
                            self::text('tindakan_perbaikan', 'Tindakan Perbaikan'),
                            self::text('pic', 'PIC', 'center'),
                            ['key' => 'target_penyelesaian', 'label' => 'Target Penyelesaian', 'type' => 'date', 'align' => 'center'],
                            self::select('status', 'Status', self::STATUS_TEMUAN),
                            self::text('verifikasi', 'Verifikasi', 'center'),
                            self::text('keterangan', 'Keterangan'),
                        ],
                        'rows' => array_fill(0, 8, []),
                    ],
                ],
            ],

            'pemeliharaan-tps-lb3' => [
                'title' => 'Formulir Pemeliharaan TPS LB3',
                'description' => 'Inspeksi kondisi sarana/prasarana Tempat Penyimpanan Sementara Limbah B3.',
                'title_with_period' => true,
                'notes_label' => null,
                'sign_place' => true,
                'signers' => self::projectSigners(),
                'sections' => self::maintenanceSections(self::itemRows([
                    'Tempat Penampungan LB3 Cair',
                    'Tempat Penampungan LB3 Padat',
                    'Lantai Gedung TPS',
                    'Kotak P3K dan Kelengkapannya',
                    'Saluran Tumpahan LB3 Cair',
                    'Bak Penampungan Tumpahan',
                    'APAR',
                    'APAT',
                    'Atap Gedung TPS',
                    'Tembok Gedung TPS',
                ], $tpsDefault)),
            ],

            'pemeliharaan-oil-trap' => [
                'title' => 'Formulir Pemeliharaan Oil Trap',
                'description' => 'Inspeksi kondisi kolam, pompa, pipa, dan kelengkapan oil trap.',
                'title_with_period' => true,
                'notes_label' => null,
                'sign_place' => true,
                'signers' => self::projectSigners(),
                'sections' => self::maintenanceSections(self::itemRows([
                    'Kolam 1',
                    'Kolam 2',
                    'Kondisi Pompa',
                    'Oil trap portable',
                    'Kondisi Pipa',
                    'Kondisi atap',
                    'Bak kontrol',
                    'Flow Meter',
                ], $oilTrapDefault)),
            ],
        ];
    }
}
