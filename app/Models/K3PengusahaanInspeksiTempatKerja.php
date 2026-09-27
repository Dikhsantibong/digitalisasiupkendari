<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanInspeksiTempatKerja extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inspeksi_tempat_kerjas';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'tanggal_inspeksi',
        'departemen',
        'lokasi',
        'tim_inspektur',
        'ketua_tim',
        'inspektur',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * The official 68-item checklist from SMT-FM-AK3-12-01 grouped by 14 categories.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_CHECKLIST = [
        'A. FURNITURE DAN PERALATAN KANTOR' => [
            'Furnitur, peralatan dan alat elektronik telah diatur untuk memperoleh keamanan dan penggunaan fasilitas seperti lampu, dinding pembatas, telepon dan service lainnya telah maksimum',
            'Meja, lemari file dll telah diatur sehingga laci tidak terbuka ke arah lorong jalan. Laci meja dan lemari dalam kondisi tertutup setelah digunakan',
            'Beban telah didistribusikan di lemari file sehingga isi laci yang lebih tinggi tidak menciptakan kondisi dengan beban tertinggi',
            'Laci, tempat buku dan lemari telah aman di pijakan lantai untuk mencegah terjatuh',
            'Meja, kursi atau peralatan kantor yang rusak telah diperbaiki atau ditarik untuk perbaikan',
            'Pisau potong kertas berada dalam posisi terkunci/aman pada saat tidak digunakan',
            'Pisau potong memiliki pengaman pada saat tidak digunakan',
        ],
        'B. LORONG DAN LANTAI' => [
            'Jarak lorong mencukupi untuk lalu lintas dua arah dan tidak terdapat penghalang jalan masuk di seluruh bagian dari gedung atau kantor',
            'Pengaturan kantor mengijinkan "kemudahan jalur" dalam kondisi gawat darurat',
            'Tempat sampah, tas atau obyek lainnya diletakkan pada lokasi dimana tidak menimbulkan bahaya potensial tersandung',
            'Lantai bebas dari pensil, botol dan obyek obyek lepas',
            'Bahaya potensial tersandung dari kabel elektrik, kabel telepon atau "protrusions" pada lantai dilindungi dengan pengaturan furniture atau cara lainnya',
            'Lantai bebas dari ubin dan "projections" yang menimbulkan bahaya potensial tersandung',
            'Karpet dalam kondisi baik dan tidak dalam kondisi rusak atau koyak',
        ],
        'C. HOUSE KEEPING' => [
            'House keeping dipelihara untuk meminimumkan kecelakaan',
            'Material dan kertas disimpan dengan baik',
            'Bahan mudah terbakar tidak disimpan dibawah meja, laci atau lemari',
            'Tangga disediakan untuk mengambil material pada laci dan disimpan dalam kondisi yang aman dan terpelihara',
            'Cairan pembersih digunakan hanya dalam kuantitas yang minimum dan disimpan di dalam container tertutup serta disimpan pada lokasi dengan ventilasi yang baik. Material tersebut tidak digunakan dekat api atau elemen panas terbuka',
            'Apakah lantai licin, atau terdapat ceceran oli dan tumpahan zat kimia',
            'Apakah tempat kerja memiliki ventilasi yang cukup',
            'Apakah tersedia kotak P3K dan isinya lengkap',
            'Apakah APAR tersedia pada tempat yang mudah dijangkau',
            'Apakah APAR yang terpasang telah diperiksa',
            'Apakah tersedia rambu-rambu peringatan dengan jelas',
            'Apakah mereka bekerja dengan penerangan yang cukup',
        ],
        'D. PERALATAN LISTRIK' => [
            'Kipas angin telah dilindungi dengan pelindung untuk mencegah jari tangan masuk ke dalam kipas',
            'Kabel dan "konektor/plug" dalam kondisi yang baik',
            'Kabel elektrik dipasang melalui pintu, dinding atau langit-langit atau karpet lainnya',
            'Multi-outlet plugs tidak dipasang dengan multi-outlet plugs lainnya',
            'Kabel ekstensi tidak dipasang pada kabel ekstensi lainnya',
            'Kabel ekstensi diatur sehingga tidak diletakkan melalui radiator, pipa uap, melalui pintu atau dibawah "rugs"',
            'Peralatan listrik tidak menunjukkan tanda-tanda panas yang berlebihan',
        ],
        'E. EMERGENCY PREPAREDNESS' => [
            'Staf memahami prosedur dan tanda kondisi gawat darurat serta penggunaan peralatan tanggap darurat (seperti Alat Pemadam Api Ringan, Hose reel, dll)',
            'Nomor darurat secara jelas di pasang',
        ],
        'F. PARKIR' => [
            'Kendaraan diparkir pada lokasi yang ditentukan',
            'Tanda Parkir jelas',
            'Apakah ada kendaraan pribadi berada diluar area',
        ],
        'G. TANDA K3' => [
            'Tanda K3 mudah dibaca dan berada pada lokasi yang ditentukan',
            'Tanda K3 dalam kondisi baik',
        ],
        'H. PERALATAN KERJA' => [
            'Peralatan kerja berada pada lokasi yang ditentukan',
            'Penandaan lokasi telah baik',
        ],
        'I. MEROKOK' => [
            'Lokasi yang dilarang merokok telah diberi tanda',
            'Lokasi merokok telah diberi tanda',
            'Asbak rokok disediakan',
            'Orang merokok di area khusus merokok',
        ],
        'J. PANEL LISTRIK' => [
            'Panel listrik dalam kondisi terkunci',
        ],
        'K. PINTU DARURAT' => [
            'Pintu darurat dalam kondisi baik dan tidak terhalang',
            'Tanda Pintu darurat terpelihara',
            'Lampu pintu darurat menyala dan kondisi baik',
        ],
        'L. PEKERJA/PETUGAS' => [
            'Apakah memakai APD yang memadai',
            'Apakah mereka bekerja sendirian di ruang tertutup',
            'Apakah mereka bekerja dengan tekun dan hati-hati',
            'Apakah mereka telah mendapat pelatihan/pengarahan sesuai dengan tugasnya',
            'Apakah ada pembatasan ijin masuk daerah berbahaya/resiko tinggi',
            'Apakah mereka bekerja sesuai dengan wewenang dan tugasnya',
            'Apakah posisi tubuh benar saat mengangkat',
            'Apakah pengamanan dan tanda peringatan yang dibutuhkan saat bekerja telah dipasang',
            'Apakah pengamanan peralatan yang akan dikerjakan sudah dilakukan',
            'Apakah mereka bekerja menggunakan tools yang sesuai dengan pekerjaannya dan dalam kondisi bagus',
            'Petugas/pekerja mengenakan identitas diri',
            'Petugas/pekerja dalam kondisi sehat',
        ],
        'M. LINGKUNGAN KERJA' => [
            'Apakah kebisingan area kerja dibawah Baku Mutu',
            'Apakah getaran di area kerja dibawah Baku Mutu',
            'Apakah temperature dibawah Indeks Suhu Bola Basah',
            'Pencahayaan yang cukup dan efektif disediakan di seluruh area kerja',
        ],
        'N. SUMBER DAYA ALAM' => [
            'Apakah ada pemakaian air yang tidak terkontrol',
            'Pemakaian listrik yang tidak digunakan telah dimatikan',
        ],
    ];

    /**
     * Build default checklist rows (all 68 items, status null).
     *
     * @return list<array{id: null, category: string, no_urut: int, item: string, status: string|null, comment: string, sort_order: int}>
     */
    public static function buildDefaultRows(): array
    {
        $rows = [];
        $sortOrder = 0;

        foreach (self::DEFAULT_CHECKLIST as $category => $items) {
            foreach ($items as $idx => $item) {
                $rows[] = [
                    'id' => null,
                    'category' => $category,
                    'no_urut' => $idx + 1,
                    'item' => $item,
                    'status' => null,
                    'comment' => '',
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $rows;
    }

    /**
     * Build sample rows matching the scanned document (SMT-FM-AK3-12-01).
     *
     * @return list<array{id: null, category: string, no_urut: int, item: string, status: string, comment: string, sort_order: int}>
     */
    public static function buildSampleRows(): array
    {
        $sampleData = [
            'A. FURNITURE DAN PERALATAN KANTOR' => [
                ['status' => 'Y', 'comment' => 'Agar ditata sesuai dengan aturan 5R'],
                ['status' => 'Y', 'comment' => 'Meja, lemari, file dll dalam kondisi tertutup'],
                ['status' => 'N', 'comment' => 'Lemari file masih ada yang over kapasitas'],
                ['status' => 'Y', 'comment' => 'Laci, tempat buku dan lemari aman'],
                ['status' => 'Y', 'comment' => 'Semua telah dirapikan sesuai dengan tempatnya diperbaiki'],
                ['status' => 'Y', 'comment' => 'Pisau potong kertas tersimpan dengan aman'],
                ['status' => 'Y', 'comment' => 'Pisau potong dilengkapi pengaman'],
            ],
            'B. LORONG DAN LANTAI' => [
                ['status' => 'N', 'comment' => 'Masih perlu ditata sesuai dengan aturan 5R dan SMK3'],
                ['status' => 'Y', 'comment' => 'Jalur evakuasi dan titik kumpul telah disediakan'],
                ['status' => 'Y', 'comment' => 'Tempat sampah, tas atau obyek lainnya berada di tempat yang seharusnya'],
                ['status' => 'Y', 'comment' => 'Pensil, botol dan obyek - obyek lepas telah berada ditempatnya'],
                ['status' => 'Y', 'comment' => 'Tidak ada kabel yang terletak dilantai dan telah tersusun dengan rapi'],
                ['status' => 'N', 'comment' => 'Lantai belum bebas dari potensi tersandung'],
                ['status' => 'Y', 'comment' => 'Karpet dalam kondisi baik'],
            ],
            'C. HOUSE KEEPING' => [
                ['status' => 'Y', 'comment' => 'House Keeping menjadi budaya pada unit'],
                ['status' => 'Y', 'comment' => 'Setiap ruangan memiliki penyimpanan material'],
                ['status' => 'Y', 'comment' => 'Bahan mudah terbakar disimpan pada tempatnya'],
                ['status' => 'Y', 'comment' => 'Tersedia tangga untuk mengambil material yang berada di ketinggian'],
                ['status' => 'Y', 'comment' => 'Cairan pembersih disimpan ditempat yang telah disediakan terpisah dari bahan - bahan lain'],
                ['status' => 'Y', 'comment' => 'Setiap selesai pekerjaan lantai selalu dibersihkan'],
                ['status' => 'N', 'comment' => 'Masih ada ruangan yang belum memiliki ventilasi yang cukup'],
                ['status' => 'Y', 'comment' => 'Tersedia kotak P3K dan isinya cukup'],
                ['status' => 'Y', 'comment' => 'Tersedia APAR pada tempat yang mudah dijangkau'],
                ['status' => 'Y', 'comment' => 'APAR diperiksa tiap bulan'],
                ['status' => 'Y', 'comment' => 'Rambu - rambu terpasang dengan jelas'],
                ['status' => 'Y', 'comment' => 'Lampu penerangan selalu diperbaiki apabila ada yang rusak'],
            ],
            'D. PERALATAN LISTRIK' => [
                ['status' => 'Y', 'comment' => 'Semua kipas angin telah dilindungi'],
                ['status' => 'Y', 'comment' => 'Semua dalam kondisi baik'],
                ['status' => 'N', 'comment' => 'Telah terpasang melalui pintu, dinding atau langit- langit'],
                ['status' => 'Y', 'comment' => 'Semua telah terpasang dengan baik'],
                ['status' => 'Y', 'comment' => 'Semua telah terpasang dengan baik'],
                ['status' => 'N', 'comment' => 'Semua telah terpasang dengan baik'],
                ['status' => 'Y', 'comment' => 'Peralatan listrik digunakan dengan aman'],
            ],
            'E. EMERGENCY PREPAREDNESS' => [
                ['status' => 'N', 'comment' => 'Masih ada sebagian staff yang belum memahami prosedur dan tanda kondisi gawat darurat'],
                ['status' => 'Y', 'comment' => 'Masih ada ruangan yang belum terpasang nomor darurat'],
            ],
            'F. PARKIR' => [
                ['status' => 'Y', 'comment' => 'Disediakan tempat parkir khusus'],
                ['status' => 'Y', 'comment' => 'Tanda parkir jelas'],
                ['status' => 'Y', 'comment' => 'Masih ada'],
            ],
            'G. TANDA K3' => [
                ['status' => 'Y', 'comment' => 'Tanda K3 sudah terpasang dan mudah terbaca'],
                ['status' => 'N', 'comment' => 'Sudah ada tanda K3 yang rusak'],
            ],
            'H. PERALATAN KERJA' => [
                ['status' => 'Y', 'comment' => 'Peralatan kerja disimpan pada tempatnya'],
                ['status' => 'Y', 'comment' => 'Setiap lokasi sudah ditandai'],
            ],
            'I. MEROKOK' => [
                ['status' => 'Y', 'comment' => 'Lokasi yang dilarang merokok telah diberi tanda larangan merokok'],
                ['status' => 'Y', 'comment' => 'Lokasi merokok telah diberi tanda'],
                ['status' => 'Y', 'comment' => 'Asbak rokok disediakan'],
                ['status' => 'Y', 'comment' => 'Karyawan yang merokok pada Smoking Area'],
            ],
            'J. PANEL LISTRIK' => [
                ['status' => 'N', 'comment' => 'Masih ada panel yang tidak terkunci'],
            ],
            'K. PINTU DARURAT' => [
                ['status' => 'Y', 'comment' => ''],
                ['status' => 'Y', 'comment' => ''],
                ['status' => 'Y', 'comment' => ''],
            ],
            'L. PEKERJA/PETUGAS' => [
                ['status' => 'Y', 'comment' => 'APD sudah sesuai standar'],
                ['status' => 'Y', 'comment' => 'Selalu bekerja bersama - sama'],
                ['status' => 'Y', 'comment' => 'Karyawan bekerja dengan hati - hati'],
                ['status' => 'Y', 'comment' => 'Sebelum melakukan pekerjaan diberi instruksi kerja masing - masing'],
                ['status' => 'Y', 'comment' => 'Telah diberi tanda dilarang masuk'],
                ['status' => 'Y', 'comment' => 'Setiap karyawan bekerja sesuai wewenang dan tugasnya'],
                ['status' => 'Y', 'comment' => 'Setiap karyawan bekerja sesuai instruksi kerja'],
                ['status' => 'Y', 'comment' => 'Safety line selalu disipkan'],
                ['status' => 'Y', 'comment' => 'Setiap peralatan telah dilengkapi tagging'],
                ['status' => 'Y', 'comment' => 'Setiap karyawan menggunakan tools sesuai pekerjaanya'],
                ['status' => 'N', 'comment' => 'Masih ada pekerja yang belum menggunakan kartu identitas'],
                ['status' => 'Y', 'comment' => 'Semua pekerja dalam kondisi sehat'],
            ],
            'M. LINGKUNGAN KERJA' => [
                ['status' => 'Y', 'comment' => 'Kebisingan diruang pembangkit melewati batas standart baku mutu'],
                ['status' => 'Y', 'comment' => 'Getaran masih memenuhi standart baku mutu'],
                ['status' => 'Y', 'comment' => 'Ruangan lokal masih memenuhi batas indeks suhu ruangan bola basah'],
                ['status' => 'Y', 'comment' => 'Setiap ruangan dilengkapi pencahayaan yang cukup'],
            ],
            'N. SUMBER DAYA ALAM' => [
                ['status' => 'N', 'comment' => 'Tidak terdapat kebocoran pada saluran air'],
                ['status' => 'N', 'comment' => 'Masih ada komputer belum dioffkan saat pulang kerja'],
            ],
        ];

        $rows = [];
        $sortOrder = 0;

        foreach (self::DEFAULT_CHECKLIST as $category => $items) {
            foreach ($items as $idx => $item) {
                $sample = $sampleData[$category][$idx] ?? ['status' => null, 'comment' => ''];

                $rows[] = [
                    'id' => null,
                    'category' => $category,
                    'no_urut' => $idx + 1,
                    'item' => $item,
                    'status' => $sample['status'],
                    'comment' => $sample['comment'],
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $rows;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanInspeksiTempatKerjaItem::class, 'laporan_id')->orderBy('sort_order')->orderBy('no_urut');
    }
}
