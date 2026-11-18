<?php

namespace App\Services\Reports;

use App\Enums\ReportModule;

/**
 * Kriteria SLA (Service Level Agreement) Pekerjaan Pemborongan Jasa Pendukung
 * Teknis 6 Site Pembangkit PLN NP UP Kendari 2026-2028 (KDD056.PJ/612/UPKD/2026),
 * per divisi, as in the user's SLA sheet (PRD.md §8.12).
 *
 * Each criterion: [kriteria, target kinerja, eviden, jenis SLA (1|2), nilai
 * target, sumber data, count?]. Bobot follows the jenis (jenis 1 = bobot 2,
 * jenis 2 = bobot 1) and the denda is bobot/10 × 9% × tagihan bulan berjalan.
 *
 * Sumber data (read by {@see SlaReport}):
 * - an InputCatalog key (`group:table[:where]`) — the input's kelengkapan %;
 * - `report:<ReportModule>` — 100% once that laporan of the month is submitted;
 * - `group:<InputCatalog group>` — the group's average kelengkapan (`group:*` = all).
 * With `count` the pencapaian is the number of records instead (e.g. 2 dokumen IK).
 * No source = not measured by the application yet (filled in manually later).
 */
final class SlaCatalog
{
    /**
     * divisi => [nomor, judul, ModuleResume modules whose laporan view opens it (all of them)].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public const DIVISI = [
        'operasi' => ['1.1.a', 'Pelaksanaan Pengoperasian Pembangkit', ['operasi']],
        'pemeliharaan' => ['1.1.b', 'Pelaksanaan Pemeliharaan Pembangkit', ['pemeliharaan']],
        'logistik' => ['1.1.c', 'Pelaksanaan Logistik dan Gudang Pembangkit', ['logistik']],
        'pdm' => ['1.1.d', 'Pelaksanaan PdM dan Maturity Level Pembangkit', ['pdm']],
        'k3' => ['1.2.a', 'Pelaksanaan K3L Pembangkit', ['k3']],
        'project-leader' => ['1.2.b', 'Pelaksanaan Project Leader Pembangkit', ['operasi', 'pemeliharaan', 'k3', 'logistik', 'pdm']],
    ];

    public const CONTRACT = 'PEKERJAAN PEMBORONGAN JASA PENDUKUNG TEKNIS 6 SITE PEMBANGKIT PLN NP UP KENDARI TAHUN 2026-2028';

    public const CONTRACT_NUMBER = 'KDD056.PJ/612/UPKD/2026';

    /** Denda rate of the tagihan bulan berjalan per 10 bobot. */
    public const PENALTY_RATE = 0.09;

    private const INPUT_APP = ['Melaksanakan input data rutin aplikasi on line pembangkit', 'Input data rutin aplikasi on line pembangkit tersedia tepat waktu dan up to date', 'Aplikasi PLNNP UP Kendari'];

    private const PTW = ['Kegiatan PtW pembangkit terlaksana, sesuai realisasi', 'Laporan/ dok PtW pembangkit'];

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: int, 4: int|float, 5: list<string>, 6?: bool}>
     */
    public static function criteria(string $divisi): array
    {
        $report = fn (ReportModule ...$modules): array => array_map(fn (ReportModule $m): string => 'report:'.$m->value, $modules);

        return match ($divisi) {
            'operasi' => [
                ['Rencana program/kegiatan, jadwal shift dan jadwal operasi lainnya', 'Program/kegiatan operasi terlaksana 100%', 'Dok. jadwal Operasi', 2, 1, ['operator:work_schedules', 'operasi:operasi_flm_jadwals', 'operasi:operasi_5s5r_jadwals', 'operasi:operasi_meeting_shift_jadwals']],
                ['Membuat IK dan review IK rutin operasi', '2 dokumen IK/ Review IK setiap bulan', 'Dok. IK/ Review IK', 2, 2, ['operasi:operasi_instruksi_kerjas'], true],
                ['Membuat data teknis mesin dan peralatan pembangkit', '5 dok. data teknis mesin dan peralatan pembangkit setiap bulan', 'Dok. data teknis mesin dan peralatan pembangkit', 2, 5, ['operasi:operasi_data_tekniss'], true],
                ['Membuat laporan/checklist kesiapan sistem blackstart', 'Laporan/checklist kesiapan sistem blackstart setiap bulan', 'Laporan/checklist kesiapan sistem blackstart', 2, 1, ['operasi:operasi_blackstart_jadwals']],
                ['Membuat laporan comissioning, NDC, performance test mesin dan peralatan pembangkit', 'Laporan comissioning, NDC, performance test mesin dan peralatan pembangkit sesuai realisasi kegiatan', 'Laporan comissioning, NDC, performance test mesin dan peralatan pembangkit', 1, 1, ['operasi:operasi_commissioning_tests', 'operasi:operasi_comm_peralatans', 'operasi:operasi_performance_test_mesins']],
                ['Membuat laporan maturity level bagian operasi pembangkit', 'Laporan/ data awal maturity level operasi dan efisiensi tersedia setiap semester', 'Laporan/data awal maturity level operasi dan efisiensi tersedia setiap semester', 2, 1, []],
                ['Membuat laporan operasi pembangkit', 'Laporan operasi pembangkit setiap tanggal 1', 'Laporan operasi pembangkit', 1, 1, $report(ReportModule::Operasi, ReportModule::OperasiPengusahaan)],
                ['Membuat dokumen peralatan, material dan tools operasi', 'Dokumen peralatan, material dan tools operasi setiap bulan', 'Dokumen peralatan, material dan tools operasi', 2, 1, ['operasi:operasi_material_peralatans', 'operasi:operasi_inventaris_jadwals']],
                ['Mengisi Logbook mutasi, Absensi shift/meeting shift', 'Informasi shift dan kehadiran/ketersediaan TK diketahui', 'Logbook Mutasi shift, Absensi, dokumen meeting shift', 1, 1, ['operator:operator_mutasis', 'operator:employee_presences']],
                ['Melaksanakan pemeriksaan rutin kondisi mesin, peralatan, panel-panel, dan instalasi pembangkit', 'Kondisi mesin, peralatan, panel-panel, dan instalasi pembangkit diketahui rutin setiap bulan', 'Patrol check pembangkit', 1, 1, ['operasi:operasi_patrol_check_mesins']],
                ['Melaksanakan penerimaan, pemeriksaan dan pengisian resource pembangkit (Oli, BBM, Air pendingin, bahan kimia)', 'Kondisi resource pembangkit (Oli, BBM, Air pendingin, bahan kimia) terlaksana & diketahui rutin', 'Patrol check pembangkit, data transaksi dan stok resource pembangkit', 1, 1, ['operasi_pengusahaan:operasi_persediaan:bbm', 'operasi_pengusahaan:operasi_persediaan:pelumas', 'operasi_pengusahaan:operasi_pemakaian_bbm', 'operasi_pengusahaan:operasi_pemakaian_pelumas']],
                ['Melaksanakan dan memonitoring serta mengkoordinasikan pengoperasian mesin dan peralatan pembangkit', 'Mesin beroperasi aman, handal, efisien dan terkoordinasi dengan pengatur beban', 'Logbook, patrol check dan laporan operator lainnya', 1, 1, ['operator:operator_logsheets', 'operasi_pengusahaan:daily_engine_reports']],
                ['Melaporkan kondisi abnormal mesin, peralatan bantu, peralatan pembangkit lainnya dan instalasi pembangkit', 'Kondisi abnormal pembangkit diketahui segera dan dapat direspon secara tepat', 'Laporan Kondisi abnormal mesin dan peralatan pembangkit', 1, 1, ['operasi:kondisi_abnormals']],
                ['Melaksanakan FLM mesin dan peralatan pembangkit', '30 kegiatan FLM Pembangkit terlaksana rutin setiap bulan', 'Laporan FLM Pembangkit', 1, 1, ['operasi:operasi_flm_monitorings']],
                ['Melaksanakan 5S 5R operasi', '5 kegiatan 5S 5R operasi terlaksana rutin setiap bulan', 'Laporan 5S 5R operasi', 2, 1, ['operasi:operasi_program_5s5r_items']],
                ['Melaksanakan PtW operasi', 'Kegiatan PtW operasi terlaksana, sesuai realisasi', 'Laporan/dok PtW operasi', 1, 1, ['operasi:operasi_permit_to_works']],
                ['Respon pelaporan kondisi abnormal dan gangguan pembangkit', 'Pelaporan gangguan dilaksanakan selambat-lambatnya 20 menit', 'Laporan/data gangguan pembangkit', 1, 1, ['operasi:kondisi_abnormals', 'operasi_pengusahaan:operasi_jam_mesin:gangguan']],
                [...self::INPUT_APP, 1, 1, ['group:operasi', 'group:operasi_pengusahaan', 'group:operator']],
            ],
            'pemeliharaan' => [
                ['Rencana program/kegiatan, jadwal pemeliharaan lainnya', 'Program/kegiatan, jadwal pemeliharaan terlaksana 100% setiap bulan', 'Dok. jadwal Pemeliharaan', 2, 1, ['har:har_jadwal_harians', 'har:har_jadwal_p0_p5s', 'har:har_jadwal_piket_on_calls', 'har:har_jadwal_patrol_checks', 'har:har_jadwal_meeting_pemeliharaans']],
                ['Membuat IK dan review IK rutin pemeliharaan', '2 dokumen IK/ Review IK setiap bulan', 'Dok. IK/ Review IK', 2, 1, ['har:har_instruksi_kerjas'], true],
                ['Membuat laporan/checklist pemeliharaan Job Plan, Job card, SR PM & CM', 'Laporan/checklist pemeliharaan Job Plan, Job card, SR PM & CM tersedia setiap kegiatan', 'Laporan/checklist pemeliharaan Job Plan, Job card, SR PM & CM', 2, 1, ['har:work_orders', 'har:service_requests', 'har:maintenance_activities']],
                ['Membuat laporan maturity level bagian pemeliharaan pembangkit', 'Laporan/ data awal maturity level pemeliharaan dan tersedia setiap semester', 'Laporan / data awal maturity level pemeliharaan dan efisiensi tersedia setiap semester', 2, 1, []],
                ['Membuat laporan pemeliharaan pembangkit', 'Laporan pemeliharaan pembangkit setiap tanggal 1', 'Laporan pemeliharaan pembangkit', 1, 1, $report(ReportModule::Har, ReportModule::HarPengusahaan)],
                ['Membuat dokumen peralatan, material dan tools pemeliharaan', 'Dokumen peralatan, material dan tools pemeliharaan setiap bulan', 'Dokumen peralatan, material dan tools pemeliharaan', 1, 1, ['har:har_material_peralatans', 'har:har_lembar_rows:inventarisasi-tools']],
                ['Mengisi Logbook pemeliharaan, Absensi daily meeting', 'Informasi pemeliharaan dan kehadiran/ ketersediaan TK diketahui setiap bulan', 'Logbook pemeliharaan, Absensi, dokumen meeting', 2, 1, ['har:har_logbook_mutasis', 'har:har_daily_meetings']],
                ['Melaksanakan pemeliharaan rutin kondisi mesin, peralatan, panel-panel, dan instalasi pembangkit', 'Kondisi mesin, peralatan, panel-panel, dan instalasi pembangkit diketahui rutin', 'Patrol check pemeliharaan pembangkit', 1, 1, ['har:har_lembar_rows:patrol-check-pemeliharaan', 'har_pengusahaan:maintenance_schedules']],
                ['Melaporkan kondisi abnormal mesin, peralatan bantu, peralatan pembangkit lainnya dan instalasi pembangkit', 'Kondisi abnormal pembangkit diketahui segera dan dapat direspon secara tepat', 'Laporan Kondisi abnormal mesin dan peralatan pembangkit', 1, 1, ['har:har_tabel_rows:abnormal-gangguan', 'har:har_laporan_gangguans']],
                ['Melaksanakan 5S 5R pemeliharaan', '5 kegiatan 5S 5R pemeliharaan terlaksana rutin setiap bulan', 'Laporan 5S 5R pemeliharaan', 2, 1, ['har:har_program_5s5r_items']],
                ['Melaksanakan dan mendukung PtW pembangkit', ...self::PTW, 1, 1, ['operasi:operasi_permit_to_works']],
                [...self::INPUT_APP, 1, 1, ['group:har', 'group:har_pengusahaan']],
            ],
            'logistik' => [
                ['Rencana program/kegiatan, timeline, dan jadwal logistik dan gudang lainnya', 'Program/kegiatan, jadwal logistik dan gudang terlaksana 100% setiap bulan', 'Dok. jadwal logistik dan gudang', 2, 1, ['logistik:logistik_jadwal_rows:kegiatan', 'logistik:logistik_jadwal_rows:pemeliharaan', 'logistik:logistik_jadwal_rows:shift', 'logistik:logistik_jadwal_rows:piket', 'logistik:logistik_jadwal_rows:5s5r', 'logistik:logistik_jadwal_rows:meeting']],
                ['Mengusulkan IK dan review IK bagian logistik dan gudang', '2 dokumen IK/ Review IK setiap bulan', 'Dok. IK/ Review IK', 2, 1, ['logistik:logistik_jadwal_rows:ik']],
                ['Membuat laporan maturity level bagian logistik dan gudang pembangkit', 'Laporan/data awal maturity level pemeliharaan dan tersedia setiap semester', 'Laporan/data awal maturity level pemeliharaan tersedia setiap semester', 2, 1, ['logistik:logistik_jadwal_rows:maturity']],
                ['Membuat laporan logistik dan gudang pembangkit', 'Laporan pemeliharaan pembangkit setiap tanggal 1', 'Laporan logistik dan gudang pembangkit', 1, 1, $report(ReportModule::Logistik)],
                ['Membuat dan menginventarisasi peralatan, material dan tools bagian terkait lainnya', 'Dokumen peralatan, material dan tools pembangkit setiap bulan', 'Dokumen peralatan, material dan tools pembangkit', 1, 1, ['logistik:logistik_form_rows:peralatan', 'logistik:logistik_form_rows:inventaris-lainnya', 'logistik:logistik_jadwal_rows:inventarisasi']],
                ['Mengisi Logbook kegiatan, Absensi dan daily meeting harian logistik dan gudang', 'Informasi logistik dan gudang dan kehadiran/ ketersediaan TK diketahui setiap bulan', 'Logbook logistik dan gudang, Absensi, dokumen meeting', 1, 1, ['logistik:logistik_jadwal_rows:meeting', 'logistik:logistik_jadwal_rows:shift']],
                ['Melaksanakan pemeliharaan rutin dan prediktif logistik dan gudang pembangkit sesuai jadwal', 'Pembersihan gudang, Inventarisasi dan kegiatan lainnya terlaksana', 'Laporan logistik dan gudang pembangkit', 2, 1, ['logistik:logistik_jadwal_rows:pemeliharaan', 'logistik:logistik_jadwal_rows:patrol-check']],
                ['Mendukung ketersediaan logistik dan gudang untuk pemeliharaan korektif pembangkit', 'Ketersediaan, material, peralatan dan tools saat kondisi korektif tinggi, transaksi material terdata setiap bulan', 'Laporan logistik dan gudang pembangkit', 2, 1, ['logistik:logistik_form_rows:kondisi-stok']],
                ['Membuat dan mengusulkan kertas kerja logistik dan gudang', 'Membuat usulan data material ellips, data awal ROP, ROQ, IL T setiap bulan', 'Data analisa awal logistik', 1, 1, ['logistik:logistik_rekomendasis', 'logistik:logistik_form_rows:pendukung']],
                ['Melaksanakan 5S 5R pembangkit', '5 kegiatan 5S 5R logistik dan gudang terlaksana rutin setiap bulan', 'Laporan 5S 5R logistik dan gudang', 2, 1, ['logistik:logistik_jadwal_rows:5s5r', 'logistik:logistik_jadwal_rows:inspeksi-5s5r']],
                ['Mendukung pelaksanaan PtW pembangkit', ...self::PTW, 1, 1, ['logistik:logistik_form_rows:permit-to-work']],
                [...self::INPUT_APP, 1, 1, ['logistik:logistik_jadwal_rows:input-aplikasi', 'group:logistik']],
            ],
            'pdm' => [
                ['Rencana program/kegiatan, timeline, dan jadwal PdM & Matlev. lainnya', 'Program/kegiatan, jadwal PdM & Matlev. terlaksana 100% setiap bulan', 'Dok. jadwal PdM & Matlev.', 1, 1, ['pdm:pdm_jadwal_harians', 'pdm:pdm_jadwal_patrol_checks', 'pdm:pdm_jadwal_5s5rs', 'pdm:pdm_jadwal_meetings']],
                ['Mengusulkan IK dan review IK bagian PdM & Matlev.', '2 dokumen IK/ Review IK setiap bulan', 'Dok. IK/Review IK', 1, 1, []],
                ['Mendukung kegiatan teknis dan administrasi semua bagian unit maturity level pembangkit', 'Laporan dan data maturity level semua bagian terkumpul setiap bulan dan dilaporkan setiap semester', 'Laporan kolektif Maturity level Pembangkit', 1, 1, ['logistik:logistik_jadwal_rows:maturity']],
                ['Membuat laporan PdM & Matlev. pembangkit', 'Kegiatan PdM terlaksana sesuai jadwal setiap bulan', 'Laporan PdM & Maturity level', 1, 1, $report(ReportModule::Pdm)],
                ['Membuat dan menginventarisasi peralatan, material dan tools PdM & Matlev.', 'Dokumen peralatan, material dan tools PdM & Matlev. setiap bulan', 'Dokumen peralatan, material dan tools pembangkit', 1, 1, ['pdm:pdm_form_documents:kontrol-material']],
                ['Mengisi Logbook kegiatan, Absensi dan daily meeting harian PdM & Matlev.', 'Informasi PdM & Matlev. dan kehadiran/ ketersediaan TK diketahui setiap bulan', 'Logbook PdM & Matlev., Absensi, dokumen meeting', 1, 1, ['pdm:pdm_jadwal_meetings', 'pdm:pdm_kesiapan_apds']],
                ['Melaksanakan kegiatan rutin dan prediktif PdM & Matlev. pembangkit sesuai jadwal', 'Kegiatan rutin dan prediktif PdM & Matlev. Pembangkit terlaksana sesuai jadwal', 'Laporan PdM & Maturity level', 2, 1, ['pdm:pdm_realisasi_prediktifs', 'pdm:pdm_form_documents:vibrasi', 'pdm:pdm_form_documents:pelumas', 'pdm:pdm_form_documents:air-pendingin']],
                ['Mengkoordinasikan data dan sampel resource PdM ke bagian dan pihak terkait', 'Sampel resource tersedia, data rutin PdM tersedia setiap bulan', 'Laporan PdM & Maturity level pembangkit', 1, 1, ['pdm:pdm_sample_monitorings']],
                ['Melaksanakan 5S 5R pembangkit', '5 kegiatan 5S 5R PdM & Matlev. terlaksana rutin setiap bulan', 'Laporan 5S 5R PdM & Matlev.', 2, 1, ['pdm:pdm_jadwal_5s5rs', 'pdm:pdm_form_documents:checklist-5s5r']],
                ['Mendukung pelaksanaan PtW pembangkit', ...self::PTW, 1, 1, ['pdm:pdm_permit_to_works']],
                [...self::INPUT_APP, 1, 1, ['group:pdm']],
            ],
            'k3' => [
                ['Rencana program/kegiatan, jadwal K3L lainnya', 'Program/ kegiatan, jadwal K3L terlaksana 100%', 'Dok. jadwal K3L', 2, 1, ['k3:k3_kegiatan_rutins', 'k3:k3_jadwal_on_calls', 'k3:k3_patrol_check_jadwals', 'k3:k3_pekerjaan_rutins', 'k3:k3_activity_plans']],
                ['Membuat IK dan review IK rutin K3L', '2 dokumen IK/Review IK setiap bulan', 'Dok. IK/ Review IK', 2, 2, ['k3:k3_dokumen_iks'], true],
                ['Mengumpulkan IK dan review IK semua bagian pembangkit', 'Rekapitulasi dan data IK & review IK terkumpul dan termonitoring setiap bulan', 'Laporan kolektif IK dan Review IK Pembangkit', 1, 1, ['k3:k3_instruksi_kerjas', 'k3:k3_jadwal_pembuatan_iks']],
                ['Melaksanakan dan mengawasi penggunaan safety tenaga kerja', 'Semua tenaga kerja disiplin dalam penggunaan safety APD dan safety pembangkit lainnya', 'Laporan pelanggaran penggunaan safety pembangkit', 1, 1, ['k3:k3_kesiapan_apds', 'k3:k3_kondisi_k3s']],
                ['Memastikan kesiapan sistem fire fighting pembangkit', 'Sistem fire fighting standby operasi secara up to date (min. Laporan setiap bulan)', 'Laporan, patrol check sistem fire fighting', 1, 1, ['k3:fire_extinguisher_checks', 'k3:k3_hydrant_inspections', 'k3:k3_fire_alarm_inspections']],
                ['Membuat dan menginventarisasi peralatan, material dan tools K3L', 'Dokumen peralatan, material dan tools pemeliharaan setiap bulan', 'Dokumen peralatan, material dan tools pemeliharaan', 2, 1, ['k3:k3_apd_inventories', 'k3:k3_formulir_records:sarana-prasarana']],
                ['Membuat dan memeriksa unsafe action dan unsafe condition', 'Laporan unsafe action dan unsafe condition setiap bulan', 'Laporan unsafe action dan unsafe condition setiap bulan', 2, 1, ['k3:inspections', 'har:har_unsafe_conditions']],
                ['Mengisi Logbook kegiatan. absensi dan daily meeting K3L', 'Informasi pemeliharaan dan kehadiran/ ketersediaan TK diketahui', 'Logbook pemeliharaan, Absensi, dokumen meeting', 2, 1, ['k3:security_patrols', 'k3:k3_formulir_records:kontrol-mingguan']],
                ['Melaksanakan pemeliharaan rutin dan prediktif K3L pembangkit sesuai jadwal', 'Area Lingkungan terpelihara (gudang limbah, oil trap, etc) terlaksana sesuai jadwal', 'Laporan K3L Pembangkit', 1, 1, ['k3:k3_air_limbahs', 'k3:k3_formulir_records:pemeliharaan-tps-lb3', 'k3:k3_formulir_records:pemeliharaan-oil-trap']],
                ['Melaksanakan monitoring perizinan pembangkit', 'Data kondisi dan perizinan pembangkit tersedia setiap bulan', 'Data perizinan pembangkit', 2, 1, []],
                ['Membuat usulan rambu- rambu (kesehatan dan keselamatan) pembangkit', 'Data dan kondisi rambu- rambu tersedia setiap bulan', 'Data rambu-rambu pembangkit', 2, 1, ['k3:k3_rambu_inspections']],
                ['Melaksanakan 5S 5R pemeliharaan', '5 kegiatan 5S 5R pemeliharaan terlaksana rutin setiap bulan', 'Laporan 5S 5R pemeliharaan', 2, 1, ['har:har_program_5s5r_items']],
                ['Melaksanakan dan mendukung PtW pembangkit', ...self::PTW, 1, 1, ['operasi:operasi_permit_to_works']],
                [...self::INPUT_APP, 1, 1, ['group:k3', 'group:k3_pengusahaan']],
            ],
            'project-leader' => [
                ['Melaksanakan konsolidasi ketenagakerjaan', 'Data absensi dinas, absensi lembur, konsolidasi tenaga kerja lainnya tersedia dan terlaksana setiap bulan', 'Data tenaga kerja, absensi dan respon kegiatan pembangkit.', 2, 1, ['operator:employee_presences']],
                ['Membuat, mengusulkan, mengkonsolidasikan jadwal - jadwal pembangkit', 'Jadwal, rencana realisasi kegiatan fungsi pelaksana pekerjaan tersedia setiap bulan', 'Jadwal, timeline', 2, 1, ['operator:work_schedules', 'operasi:operasi_flm_jadwals', 'har:har_jadwal_harians', 'k3:k3_kegiatan_rutins', 'logistik:logistik_jadwal_rows:kegiatan', 'pdm:pdm_jadwal_harians']],
                ['Membuat, mengusulkan, mengkonsolidasikan program kerja pembangkit', 'Program kerja fungsi pelaksana pekerjaan tersedia setiap bulan', 'Laporan-laporan pembangkit', 2, 1, ['k3:k3_activity_plans', 'har_pengusahaan:maintenance_schedules']],
                ['Membuat, mengusulkan, mengkonsolidasikan Laporan fungsi pelaksana pembangkit', 'Laporan fungsi pelaksana pekerjaan tersedia setiap bulan', 'Laporan-laporan pembangkit', 1, 1, $report(ReportModule::Operasi, ReportModule::Har, ReportModule::K3, ReportModule::Logistik, ReportModule::Pdm)],
                ['Membuat, mengusulkan, mengkonsolidasikan Data tagihan project pembangkit', 'Data tagihan sesuai SLA dan syarat bayar, syarat serah terima pekerjaan tersedia setiap bulan', 'Dokumen tagihan pembangkit', 1, 1, []],
                ['Membuat, mengusulkan, mengkonsolidasikan Kegiatan pembangkit', 'Kegiatan pembangkit terlaksana dan diikuti TK', 'Absensi, dokumentasi', 2, 1, ['har:har_daily_meetings', 'operasi:operasi_meeting_shift_jadwals']],
                ['Mendukung dan melaksanakan pengawasan penerapan safety personil', 'Semua TK Wajib mematuhi dan melaksanakan penerapan safety personil', 'Laporan Pelanggaran Safety pembangkit', 1, 1, ['k3:k3_kesiapan_apds']],
                ['Mendukung dan melaksanakan input data rutin aplikasi on line pembangkit', 'Input data rutin aplikasi on line pembangkit tersedia tepat waktu dan up to date', 'Aplikasi PLNNP UP Kendari', 1, 1, ['group:*']],
            ],
            default => [],
        };
    }

    /** Bobot of a jenis SLA: jenis 1 weighs 2, jenis 2 weighs 1. */
    public static function bobot(int $jenis): int
    {
        return $jenis === 1 ? 2 : 1;
    }
}
