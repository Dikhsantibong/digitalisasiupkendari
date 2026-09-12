<?php

namespace Database\Seeders;

use App\Models\ApdCategory;
use App\Models\ApdItem;
use App\Models\EmergencyEquipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\InspectionChecklist;
use App\Models\K3ActivityType;
use App\Models\P3kBox;
use App\Models\PatrolLocation;
use App\Models\SecurityPost;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Real K3 master data (PLTD Poasia). Global lookups (activity types, APD &
 * equipment categories) apply everywhere; the rest (emergency facilities, APD
 * items, APAR/APAB, P3K boxes, inspection checklists) are seeded on the Poasia
 * unit — the source of this catalogue. Idempotent (keyed by code / name / order).
 */
class K3MasterDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const UNIT_CODE = 'PLTD-POASIA';

    /**
     * Patrol-checkpoint code prefix per unit, derived from the unit name
     * (Poasia→POA, Bau-Bau→BAUS, Kolaka→KOLA, …). Units not listed fall back to
     * the first letters of their name.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'PLTD-POASIA' => 'POA',
        'PLTD-POASIA-CONT' => 'POAC',
        'PLTD-BAUBAU' => 'BAUS',
        'PLTD-WANGIWANGI' => 'WANG',
        'PLTD-RAHA' => 'RAHA',
        'PLTD-EREKE' => 'EREK',
        'PLTM-RONGI' => 'RONG',
        'PLTM-WINNING' => 'WINN',
        'PLTD-KOLAKA' => 'KOLA',
        'PLTG-KOLAKA' => 'KOLG',
        'PLTM-SABILAMBO' => 'SABI',
        'PLTM-MIKUASI' => 'MIKU',
        'PLTD-LANIPANIPA' => 'LANI',
        'PLTD-WUAWUA' => 'WUAS',
        'PLTD-LANGARA' => 'LANG',
        'PLTD-PASARWAJO' => 'PASA',
    ];

    /** @var list<array{name: string, pic: string}> */
    private const ACTIVITY_TYPES = [
        ['name' => 'Inspeksi P3K', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi Potensi Bahaya Kebakaran', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Laporan Identifikasi Potensi Bahaya Kebakaran', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi Tempat Kerja dan Fasilitas', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi APAR & APAT', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Pengetesan Hydrant', 'pic' => 'K3 & Keamanan, Operator'],
        ['name' => 'Inspeksi Rambu - Rambu K3', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Penyemprotan Disinfectan', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi APD', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Membuat Laporan P2K3', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Rapat Evaluasi SLA Security', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi Hydrant', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Inspeksi Fire Alarm', 'pic' => 'K3 & Keamanan'],
        ['name' => 'Membuat Laporan Bulanan Kinerja K3 dan Lingkungan', 'pic' => 'K3 & Keamanan'],
    ];

    /** Patrol checkpoints POA1..POA14 (name defaults to code — fill real names later). */
    private const PATROL_COUNT = 14;

    /** Security teams (regu) from the Apel Keamanan form; no named physical posts in the Excel. */
    private const SECURITY_POSTS = ['Regu A', 'Regu B', 'Regu C'];

    /** @var list<string> */
    private const EMERGENCY = [
        'Alat Pemadam Api Ringan (APAR)',
        'Alat Pemadam Api Berat (APAB)',
        'Alat Pemadam Api Tradisional (APAT)',
        'Pompa Hydrant (Electrik)',
        'Pompa Hydrant (Diesel)',
        'Pompa Hydrant (Jocky)',
        'Outdoor Hydrant + Busa (Tabung Stainless)',
        'Indoor Hydrant + Busa (Tabung Stainless)',
        'Out Door Hydrant',
        'Kotak P3K',
    ];

    /** @var list<array{group: string, name: string}> */
    private const APD_CATEGORIES = [
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Sarung Tangan'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pelindung Lengan'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Helm'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Helm Tamu'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Sepatu Tamu'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pelindung Muka dan Mata'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pakaian Kerja'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pelindung Dada Las'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pelindung Telinga'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Pelindung Pernafasan'],
        ['group' => 'Peralatan Keselamatan Kerja Utama', 'name' => 'Baju Pelampung'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Tangga'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Lampu Penerangan'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Sirkulator Udara/Kipas'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'TOA'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Tongkat Rambu-Rambu'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Rompi Nyala'],
        ['group' => 'Peralatan Keselamatan Kerja Pelengkap', 'name' => 'Lampu Sorot'],
    ];

    /** @var list<array{category: string, item: string, location: ?string}> */
    private const APD_ITEMS = [
        ['category' => 'Sarung Tangan', 'item' => 'Sarung tangan kain bintik', 'location' => 'Lemari K3'],
        ['category' => 'Sarung Tangan', 'item' => 'Sarung tangan karet', 'location' => 'Lemari K3'],
        ['category' => 'Sarung Tangan', 'item' => 'Sarung tangan kulit (las)', 'location' => 'K2LH'],
        ['category' => 'Sarung Tangan', 'item' => 'Sarung tangan anti panas', 'location' => 'Lemari K3'],
        ['category' => 'Sepatu Tamu', 'item' => 'Safety shoes', 'location' => 'ULPLTD Poasia'],
        ['category' => 'Sepatu Tamu', 'item' => 'Sepatu 20 kV', 'location' => 'Lemari K3'],
        ['category' => 'Sepatu Tamu', 'item' => 'Sepatu tahan panas', 'location' => 'Lemari K3'],
        ['category' => 'Pelindung Muka dan Mata', 'item' => 'Kacamata bengkel', 'location' => 'Lemari K3 & Workshop'],
        ['category' => 'Pelindung Muka dan Mata', 'item' => 'Kacamata las listrik', 'location' => 'Workshop'],
        ['category' => 'Pelindung Muka dan Mata', 'item' => 'Pelindung muka bengkel', 'location' => 'Lemari K3'],
        ['category' => 'Pelindung Muka dan Mata', 'item' => 'Pelindung muka las', 'location' => 'Lemari K3'],
        ['category' => 'Pakaian Kerja', 'item' => 'Pakaian kerja biasa', 'location' => null],
        ['category' => 'Pakaian Kerja', 'item' => 'Pakaian anti panas', 'location' => 'Lemari K3'],
        ['category' => 'Pakaian Kerja', 'item' => 'Jas hujan', 'location' => 'ULPLTD Poasia'],
        ['category' => 'Pelindung Telinga', 'item' => 'Ear muff/Ear protector', 'location' => 'K2LH & Security'],
        ['category' => 'Pelindung Telinga', 'item' => 'Ear plug', 'location' => 'ULPLTD Poasia'],
        ['category' => 'Pelindung Pernafasan', 'item' => 'Masker kain', 'location' => 'K2LH'],
        ['category' => 'Pelindung Pernafasan', 'item' => 'Masker kimia', 'location' => 'K2LH & Lemari K3'],
        ['category' => 'Tangga', 'item' => 'Tangga biasa', 'location' => 'Logistik'],
        ['category' => 'Tangga', 'item' => 'Tangga berkait', 'location' => 'Pemeliharaan'],
        ['category' => 'Tangga', 'item' => 'Tangga sambungan', 'location' => null],
        ['category' => 'Tangga', 'item' => 'Tangga berdiri', 'location' => 'Logistik'],
        ['category' => 'Lampu Penerangan', 'item' => 'Senter', 'location' => 'ULPLTD Poasia'],
        ['category' => 'Lampu Penerangan', 'item' => 'Lampu darurat/emergency', 'location' => 'ULPLTD Poasia'],
        ['category' => 'Lampu Penerangan', 'item' => 'Lampu TR (penerangan tempat kerja)', 'location' => 'Pemeliharaan'],
    ];

    /** @var list<array{category: string, jenis: string, kapasitas: string, lokasi: string}> */
    private const CERTIFICATES = [
        ['category' => 'Crane', 'jenis' => 'Overhead Traveling Crane', 'kapasitas' => '5 Ton', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit Containerized', 'kapasitas' => '25.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 1 HSD', 'kapasitas' => '100.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 2 HSD', 'kapasitas' => '1.500.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 1 MFO', 'kapasitas' => '1.500.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 2 MFO', 'kapasitas' => '1.500.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 3 MFO', 'kapasitas' => '1.500.000 Liter', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Instalasi Penyalur Petir', 'jenis' => 'Power House', 'kapasitas' => '-', 'lokasi' => 'PLTD Poasia'],
        ['category' => 'Instalasi Penyalur Petir', 'jenis' => 'Tangki HSD 02', 'kapasitas' => '-', 'lokasi' => 'PLTD Poasia'],
    ];

    /** @var list<array{rfid: ?string, lokasi: ?string, merk: ?string, jenis: ?string, berat: ?float, ket: ?string}> */
    private const FIRE_EXT = [
        ['rfid' => '051', 'lokasi' => 'CCR', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '056', 'lokasi' => 'CCR', 'merk' => 'Alpinas', 'jenis' => 'Powder', 'berat' => 6, 'ket' => 'Ex. 04/08/2021'],
        ['rfid' => '052', 'lokasi' => 'CCR', 'merk' => 'Fire Venom', 'jenis' => 'CO2', 'berat' => 5, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => '057', 'lokasi' => 'CCR', 'merk' => 'Alpinas', 'jenis' => 'Gas Cair', 'berat' => 5, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '055', 'lokasi' => 'CCR', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 27/02/2027'],
        ['rfid' => null, 'lokasi' => 'Lorong/Lobby', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 8, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '069', 'lokasi' => 'Workshop', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '043', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 5, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '050', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 9, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '054', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 27/02/2027'],
        ['rfid' => '058', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 27/02/2027'],
        ['rfid' => '063', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 9, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '064', 'lokasi' => 'Ruang Pembangkit', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 5, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '046', 'lokasi' => 'Switch Gear', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '047', 'lokasi' => 'Switch Gear', 'merk' => 'Garra Fire', 'jenis' => 'CO2', 'berat' => 7, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '059', 'lokasi' => 'Switch Gear', 'merk' => 'Fire Venom', 'jenis' => 'CO2', 'berat' => 5, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => '060', 'lokasi' => 'Switch Gear', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 27/02/2027'],
        ['rfid' => '061', 'lokasi' => 'Switch Gear', 'merk' => 'Garra Fire', 'jenis' => 'CO2', 'berat' => 7, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '062', 'lokasi' => 'Switch Gear', 'merk' => 'Fire Venom', 'jenis' => 'CO2', 'berat' => 5, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => '044', 'lokasi' => 'Pompa Hydrant', 'merk' => 'Alpinas', 'jenis' => 'Gas Cair', 'berat' => 5, 'ket' => 'Ex. 10/11/2027'],
        ['rfid' => '049', 'lokasi' => 'Penerimaan HSD 1', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '071', 'lokasi' => 'Oil Catcher 1', 'merk' => 'Safey Fire', 'jenis' => 'Dry Chemical', 'berat' => 6, 'ket' => 'Ex. 10/11/2026'],
        ['rfid' => '041', 'lokasi' => 'Kantor Unit', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => '039', 'lokasi' => 'Gudang Limbah B3', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 20/04/2026'],
        ['rfid' => null, 'lokasi' => 'Pos BBM', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 8, 'ket' => 'Ex. 10/11/2026'],
        ['rfid' => null, 'lokasi' => 'Gudang Bahan Kimia', 'merk' => 'Safey Fire', 'jenis' => 'Dry Chemical', 'berat' => 6, 'ket' => 'Ex. 10/11/2026'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 6, 'ket' => 'Ex. 10/11/2026'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 24/08/2026'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Gas Cair', 'berat' => 9, 'ket' => 'Ex. 27/02/2027'],
        ['rfid' => null, 'lokasi' => 'Containerized', 'merk' => 'Fire Venom', 'jenis' => 'Foam', 'berat' => 6, 'ket' => 'Ex. 24/08/2026'],
    ];

    /** @var list<string> */
    private const P3K_BOXES = [
        'Ruangan CCR', 'Ruang Lobby', 'Pos Security', 'Ruang Pembangkit', 'Ruangan Kantor Unit', 'WorkShop', 'TPS LB3',
    ];

    /** @var list<string> */
    private const P3K_ITEMS = [
        'Kasa Steril terbungkus', 'Perban lebar 5 cm', 'Perban lebar 10 cm', 'Plester lebar 1.25 cm', 'Plester cepat',
        'Kapas 25gr', 'Kain Segitiga (Mitela)', 'Gunting', 'Peniti', 'Sarung Tangan sekali pakai', 'Masker', 'Pinset',
        'Lampu senter', 'Gelas utk cuci mata', 'Kantong plastik bersih', 'Aquades (100ml lar Saline)',
        'Povidon Lodin (60 ml)', 'Alkohol 70%', 'Buku Panduan P3K di tmpt kerja', 'Buku Catatan Daftar Isi Kotak P3K',
        'Povidon Lodin (30 ml)',
    ];

    /** @var list<array{seksi: string, item: string}> */
    private const CHECKLIST = [
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Furnitur, peralatan dan alat elektronik telah diatur untuk memperoleh keamanan dan penggunaan fasilitas'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Meja, lemari file dll telah diatur sehingga laci tidak terbuka ke arah lorong jalan'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Beban telah didistribusikan di lemari file sehingga tidak menciptakan beban tertinggi'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Laci, tempat buku dan lemari telah aman di pijakan lantai untuk mencegah terjatuh'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Meja, kursi atau peralatan kantor yang rusak telah diperbaiki atau ditarik untuk perbaikan'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Pisau potong kertas berada dalam posisi terkunci/aman pada saat tidak digunakan'],
        ['seksi' => 'A. FURNITURE DAN PERALATAN KANTOR', 'item' => 'Pisau potong memiliki pengaman pada saat tidak digunakan'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Jarak lorong mencukupi untuk lalu lintas dua arah dan tidak terdapat penghalang jalan masuk'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Pengaturan kantor mengijinkan "kemudahan jalur" dalam kondisi gawat darurat'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Tempat sampah, tas atau obyek lainnya tidak menimbulkan bahaya potensial tersandung'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Lantai bebas dari pensil, botol dan obyek-obyek lepas'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Bahaya potensial tersandung dari kabel dilindungi dengan pengaturan furniture atau cara lainnya'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Lantai bebas dari ubin dan projections yang menimbulkan bahaya potensial tersandung'],
        ['seksi' => 'B. LORONG DAN LANTAI', 'item' => 'Karpet dalam kondisi baik dan tidak dalam kondisi rusak atau koyak'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'House keeping dipelihara untuk meminimumkan kecelakaan'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Material dan kertas disimpan dengan baik'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Bahan mudah terbakar tidak disimpan dibawah meja, laci atau lemari'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Tangga disediakan untuk mengambil material dan disimpan dalam kondisi aman'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Cairan pembersih digunakan dalam kuantitas minimum dan disimpan di container tertutup'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah lantai licin, atau terdapat ceceran oli dan tumpahan zat kimia'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah tempat kerja memiliki ventilasi yang cukup'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah tersedia kotak P3K dan isinya lengkap'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah APAR tersedia pada tempat yang mudah dijangkau'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah APAR yang terpasang telah diperiksa'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah tersedia rambu-rambu peringatan dengan jelas'],
        ['seksi' => 'C. HOUSE KEEPING', 'item' => 'Apakah mereka bekerja dengan penerangan yang cukup'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Kipas angin telah dilindungi dengan pelindung untuk mencegah jari tangan masuk'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Kabel dan konektor/plug dalam kondisi yang baik'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Kabel elektrik dipasang melalui pintu, dinding atau langit-langit dengan aman'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Multi-outlet plugs tidak dipasang dengan multi-outlet plugs lainnya'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Kabel ekstensi tidak dipasang pada kabel ekstensi lainnya'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Kabel ekstensi tidak diletakkan melalui radiator, pipa uap, atau dibawah rugs'],
        ['seksi' => 'D. PERALATAN LISTRIK', 'item' => 'Peralatan listrik tidak menunjukkan tanda-tanda panas yang berlebihan'],
        ['seksi' => 'E. EMERGENCY PREPAREDNESS', 'item' => 'Staf memahami prosedur gawat darurat serta penggunaan peralatan tanggap darurat'],
        ['seksi' => 'E. EMERGENCY PREPAREDNESS', 'item' => 'Nomor darurat secara jelas di pasang'],
        ['seksi' => 'F. PARKIR', 'item' => 'Kendaraan diparkir pada lokasi yang ditentukan'],
        ['seksi' => 'F. PARKIR', 'item' => 'Tanda Parkir jelas'],
        ['seksi' => 'F. PARKIR', 'item' => 'Apakah ada kendaraan pribadi berada diluar area yang ditentukan'],
        ['seksi' => 'G. TANDA K3', 'item' => 'Tanda K3 mudah dibaca dan berada pada lokasi yang ditentukan'],
        ['seksi' => 'G. TANDA K3', 'item' => 'Tanda K3 dalam kondisi baik'],
        ['seksi' => 'H. PERALATAN KERJA', 'item' => 'Peralatan kerja berada pada lokasi yang ditentukan'],
        ['seksi' => 'H. PERALATAN KERJA', 'item' => 'Penandaan lokasi telah baik'],
        ['seksi' => 'I. MEROKOK', 'item' => 'Lokasi yang dilarang merokok telah diberi tanda'],
        ['seksi' => 'I. MEROKOK', 'item' => 'Lokasi merokok telah diberi tanda'],
        ['seksi' => 'I. MEROKOK', 'item' => 'Asbak rokok disediakan'],
        ['seksi' => 'I. MEROKOK', 'item' => 'Orang merokok di area khusus merokok'],
        ['seksi' => 'J. PANEL LISTRIK', 'item' => 'Panel listrik dalam kondisi terkunci'],
        ['seksi' => 'K. PINTU DARURAT', 'item' => 'Pintu darurat dalam kondisi baik dan tidak terhalang'],
        ['seksi' => 'K. PINTU DARURAT', 'item' => 'Tanda Pintu darurat terpelihara'],
        ['seksi' => 'K. PINTU DARURAT', 'item' => 'Lampu pintu darurat menyala dan kondisi baik'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah memakai APD yang memadai'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah mereka bekerja sendirian di ruang tertutup'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah mereka bekerja dengan tekun dan hati-hati'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah mereka telah mendapat pelatihan/pengarahan sesuai dengan tugasnya'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah ada pembatasan ijin masuk daerah berbahaya/resiko tinggi'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah mereka bekerja sesuai dengan wewenang dan tugasnya'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah posisi tubuh benar saat mengangkat'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah pengamanan dan tanda peringatan yang dibutuhkan saat bekerja telah dipasang'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah pengaman peralatan yang akan dikerjakan sudah dilakukan'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Apakah mereka menggunakan tools yang sesuai dan dalam kondisi bagus'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Petugas/pekerja mengenakan identitas diri'],
        ['seksi' => 'L. PEKERJA/PETUGAS', 'item' => 'Petugas/pekerja dalam kondisi sehat'],
        ['seksi' => 'M. LINGKUNGAN KERJA', 'item' => 'Apakah kebisingan area kerja dibawah Baku Mutu'],
        ['seksi' => 'M. LINGKUNGAN KERJA', 'item' => 'Apakah getaran di area kerja dibawah Baku Mutu'],
        ['seksi' => 'M. LINGKUNGAN KERJA', 'item' => 'Apakah temperature dibawah Indeks Suhu Bola Basah'],
        ['seksi' => 'M. LINGKUNGAN KERJA', 'item' => 'Pencahayaan yang cukup dan efektif disediakan di seluruh area kerja'],
        ['seksi' => 'N. SUMBER DAYA ALAM', 'item' => 'Apakah ada pemakaian air yang tidak terkontrol'],
        ['seksi' => 'N. SUMBER DAYA ALAM', 'item' => 'Pemakaian listrik yang tidak digunakan telah dimatikan'],
    ];

    public function run(): void
    {
        foreach (self::ACTIVITY_TYPES as $i => $a) {
            K3ActivityType::query()->updateOrCreate(
                ['code' => 'K3A-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                ['name' => $a['name'], 'category' => 'Inspeksi & Keamanan', 'default_pic' => $a['pic'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $apdCatIds = [];
        foreach (self::APD_CATEGORIES as $i => $c) {
            $model = ApdCategory::query()->updateOrCreate(
                ['code' => Str::slug($c['name'])],
                ['name' => $c['name'], 'sort_order' => $i, 'is_active' => true],
            );
            $apdCatIds[$c['name']] = $model->id;
        }

        $equipmentCatIds = [];
        $equipmentCategories = collect(self::CERTIFICATES)->pluck('category')->unique()->values();
        foreach ($equipmentCategories as $i => $name) {
            $model = EquipmentCategory::query()->updateOrCreate(
                ['code' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'is_active' => true],
            );
            $equipmentCatIds[$name] = $model->id;
        }

        // The generic K3 catalogue (patrol points, posts, emergency facilities,
        // APD items, P3K boxes, checklists) applies to every unit.
        $poasia = null;
        foreach (Unit::query()->get() as $unit) {
            $this->seedUnitMasters($unit, $apdCatIds);
            if ($unit->code === self::UNIT_CODE) {
                $poasia = $unit;
            }
        }

        // Equipment certificates are Poasia's own physical assets (different per
        // unit), so they stay on Poasia; other units register their own.
        if ($poasia !== null) {
            foreach (self::CERTIFICATES as $cert) {
                EquipmentCertificate::query()->updateOrCreate(
                    ['unit_id' => $poasia->id, 'jenis' => $cert['jenis']],
                    [
                        'equipment_category_id' => $equipmentCatIds[$cert['category']] ?? null,
                        'kapasitas' => $cert['kapasitas'],
                        'lokasi' => $cert['lokasi'],
                    ],
                );
            }
        }
    }

    /**
     * Seed the shared K3 catalogue for one unit (patrol codes use the unit's prefix).
     *
     * @param  array<string, int>  $apdCatIds
     */
    private function seedUnitMasters(Unit $unit, array $apdCatIds): void
    {
        $prefix = $this->patrolPrefix($unit);
        for ($i = 1; $i <= self::PATROL_COUNT; $i++) {
            PatrolLocation::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'code' => $prefix.$i],
                ['name' => $prefix.$i, 'sort_order' => $i, 'is_active' => true],
            );
        }

        foreach (self::SECURITY_POSTS as $i => $name) {
            SecurityPost::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $name],
                ['sort_order' => $i, 'is_active' => true],
            );
        }

        foreach (self::EMERGENCY as $i => $name) {
            EmergencyEquipment::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $name],
                ['group_name' => 'Fasilitas Darurat', 'sort_order' => $i, 'is_active' => true],
            );
        }

        foreach (self::APD_ITEMS as $i => $item) {
            ApdItem::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $item['item']],
                ['apd_category_id' => $apdCatIds[$item['category']] ?? null, 'location' => $item['location'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        foreach (self::P3K_BOXES as $i => $lokasi) {
            P3kBox::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'code' => 'P3K-'.($i + 1)],
                ['location' => $lokasi, 'sort_order' => $i, 'is_active' => true],
            );
        }

        foreach (self::FIRE_EXT as $i => $ext) {
            FireExtinguisher::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'sort_order' => $i],
                [
                    'rfid' => $ext['rfid'],
                    'location' => $ext['lokasi'],
                    'merk' => $ext['merk'],
                    'jenis' => $ext['jenis'],
                    'berat_kg' => $ext['berat'],
                    'is_active' => true,
                ],
            );
        }

        foreach (self::CHECKLIST as $i => $row) {
            InspectionChecklist::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'form_code' => 'tempat-kerja', 'sort_order' => $i],
                ['item_text' => $row['seksi'].' — '.$row['item'], 'is_active' => true],
            );
        }

        foreach (self::P3K_ITEMS as $i => $item) {
            InspectionChecklist::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'form_code' => 'isi-p3k', 'sort_order' => $i],
                ['item_text' => $item, 'is_active' => true],
            );
        }
    }

    /** Patrol code prefix for a unit — mapped where known, else derived from the name. */
    private function patrolPrefix(Unit $unit): string
    {
        if (isset(self::PREFIXES[$unit->code])) {
            return self::PREFIXES[$unit->code];
        }

        $name = preg_replace('/^PLT[DGMU]\s*/i', '', (string) ($unit->name ?? $unit->code));
        $alnum = strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $name));

        return substr($alnum, 0, 4) ?: 'UNIT';
    }
}
