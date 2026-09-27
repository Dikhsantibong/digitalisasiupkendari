<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanEmergencyFacilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanEmergencyFacility extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanEmergencyFacilityFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_emergency_facilities';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'periode',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'halaman',
        'grup',
        'no_urut',
        'nama_peralatan',
        'jml_total',
        'jml_ready',
        'jml_not_ready',
        'persen_kesiapan',
        'lokasi',
        'kendala',
        'tindak_lanjut',
        'sort_order',
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * Standard 8 items checklist for PT PLN Nusantara Power Emergency Facility Inspection
     * based on official SMT-FM-AK3-12.01 documents.
     *
     * @var array<string, list<array{no: int, nama: string, lokasi: string}>>
     */
    public const DEFAULT_GROUPS_AND_ITEMS = [
        'Fire Pump' => [
            ['no' => 1, 'nama' => 'Fire Protection Jockey Pump', 'lokasi' => 'Fire Pump House'],
            ['no' => 2, 'nama' => 'Fire Protection Electric Pump', 'lokasi' => 'Fire Pump House'],
            ['no' => 3, 'nama' => 'Fire Protection Diesel Pump', 'lokasi' => 'Fire Pump House'],
            ['no' => 4, 'nama' => 'Sea Water Fire Protection Pump', 'lokasi' => '-'],
            ['no' => 5, 'nama' => 'Fire Water Level', 'lokasi' => 'Fire Water Tank'],
        ],
        'EMERGENCY PREPAREDNESS' => [
            ['no' => 6, 'nama' => 'PMK Mobile / Fire Truck', 'lokasi' => 'Fire Station Garage'],
            ['no' => 7, 'nama' => 'Emergency Response Team', 'lokasi' => 'Admin, Main & Common Building'],
        ],
        'EMERGENCY MEDICAL SERVICE' => [
            ['no' => 8, 'nama' => 'Ambulance Mobile', 'lokasi' => '-'],
        ],
    ];

    /**
     * Build standard rows when seeding a new inspection period.
     *
     * @return list<array<string, mixed>>
     */
    public static function buildDefaultRows(): array
    {
        $rows = [];
        $sort = 0;

        foreach (self::DEFAULT_GROUPS_AND_ITEMS as $groupName => $items) {
            foreach ($items as $item) {
                $sort++;
                $rows[] = [
                    'id' => null,
                    'grup' => $groupName,
                    'no_urut' => $item['no'],
                    'nama_peralatan' => $item['nama'],
                    'jml_total' => '0',
                    'jml_ready' => '0',
                    'jml_not_ready' => '0',
                    'persen_kesiapan' => '-',
                    'lokasi' => $item['lokasi'],
                    'kendala' => '',
                    'tindak_lanjut' => '',
                    'sort_order' => $sort,
                ];
            }
        }

        return $rows;
    }

    /**
     * Build sample rows for a given period (M1-M4/BULANAN) matching the scanned documents.
     * Each period has specific values to demonstrate independent weekly inspection data.
     *
     * @return list<array<string, mixed>>
     */
    public static function buildSampleRows(string $periode = 'M1'): array
    {
        $baseData = [
            1 => [
                'jml_total' => '1',
                'jml_ready' => '1',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '100%',
                'lokasi' => 'Fire Pump House',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            2 => [
                'jml_total' => '1',
                'jml_ready' => '1',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '100%',
                'lokasi' => 'Fire Pump House',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            3 => [
                'jml_total' => '1',
                'jml_ready' => '1',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '100%',
                'lokasi' => 'Fire Pump House',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            4 => [
                'jml_total' => '0',
                'jml_ready' => '0',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '-',
                'lokasi' => '-',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            5 => [
                'jml_total' => '',
                'jml_ready' => '196.000 Liter',
                'jml_not_ready' => '',
                'persen_kesiapan' => '87.5%',
                'lokasi' => 'Fire Water Tank',
                'kendala' => '',
                'tindak_lanjut' => 'Telah disediakan jalur pengisian bertahap',
            ],
            6 => [
                'jml_total' => '0',
                'jml_ready' => '0',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '-',
                'lokasi' => 'Fire Station Garage',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            7 => [
                'jml_total' => '7 Petugas Pemadam Kebakaran + 6 First Aid Kit',
                'jml_ready' => '',
                'jml_not_ready' => '',
                'persen_kesiapan' => 'Ready',
                'lokasi' => 'Admin, Main & Common Building',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
            8 => [
                'jml_total' => '0',
                'jml_ready' => '0',
                'jml_not_ready' => '0',
                'persen_kesiapan' => '-',
                'lokasi' => '-',
                'kendala' => '',
                'tindak_lanjut' => '',
            ],
        ];

        // Specific weekly differences so M1, M2, M3, M4 have distinctive inspection data
        $periodeVariations = [
            'M1' => [
                5 => [
                    'jml_ready' => '196.000 Liter',
                    'persen_kesiapan' => '87.5%',
                    'tindak_lanjut' => 'Telah disediakan jalur pengisian bertahap',
                ],
            ],
            'M2' => [
                5 => [
                    'jml_ready' => '198.000 Liter',
                    'persen_kesiapan' => '88.4%',
                    'tindak_lanjut' => 'Pengisian air bertahap sedang berlangsung',
                ],
            ],
            'M3' => [
                5 => [
                    'jml_ready' => '205.000 Liter',
                    'persen_kesiapan' => '91.5%',
                    'tindak_lanjut' => 'Level air bertambah, pengisian dilanjutkan sesuai jadwal',
                ],
            ],
            'M4' => [
                5 => [
                    'jml_ready' => '224.000 Liter',
                    'persen_kesiapan' => '100%',
                    'tindak_lanjut' => 'Kapasitas air tangki penuh (optimal) dan siap operasi',
                ],
            ],
            'BULANAN' => [
                5 => [
                    'jml_ready' => '224.000 Liter',
                    'persen_kesiapan' => '100%',
                    'tindak_lanjut' => 'Rekapitulasi bulanan: seluruh fasilitas darurat dalam kondisi prima',
                ],
            ],
        ];

        $overrides = $periodeVariations[$periode] ?? [];

        $rows = [];
        $sort = 0;

        foreach (self::DEFAULT_GROUPS_AND_ITEMS as $groupName => $items) {
            foreach ($items as $item) {
                $sort++;
                $no = $item['no'];
                $base = $baseData[$no] ?? [
                    'jml_total' => '0',
                    'jml_ready' => '0',
                    'jml_not_ready' => '0',
                    'persen_kesiapan' => '-',
                    'lokasi' => $item['lokasi'],
                    'kendala' => '',
                    'tindak_lanjut' => '',
                ];
                $override = $overrides[$no] ?? [];
                $merged = array_merge($base, $override);

                $rows[] = [
                    'id' => null,
                    'grup' => $groupName,
                    'no_urut' => $no,
                    'nama_peralatan' => $item['nama'],
                    'jml_total' => $merged['jml_total'],
                    'jml_ready' => $merged['jml_ready'],
                    'jml_not_ready' => $merged['jml_not_ready'],
                    'persen_kesiapan' => $merged['persen_kesiapan'],
                    'lokasi' => $item['lokasi'],
                    'kendala' => $merged['kendala'],
                    'tindak_lanjut' => $merged['tindak_lanjut'],
                    'sort_order' => $sort,
                ];
            }
        }

        return $rows;
    }
}
