import { AlertTriangle, Award, BellRing, BookUser, Boxes, BriefcaseMedical, Building2, CalendarClock, CalendarRange, Cctv, ClipboardCheck, Clock, Droplets, FileSpreadsheet, Flame, HardHat, LifeBuoy, ShieldAlert, ShieldCheck, Signpost, SquarePen, UserCheck, UserX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import harPengusahaan from '@/routes/har/pengusahaan';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanAlatTanggapDarurat from '@/routes/k3/pengusahaan/alat-tanggap-darurat';
import k3PengusahaanAparApab from '@/routes/k3/pengusahaan/apar-apab';
import k3PengusahaanApat from '@/routes/k3/pengusahaan/apat';
import k3PengusahaanApelKeamanan from '@/routes/k3/pengusahaan/apel-keamanan';
import k3PengusahaanBukuTamu from '@/routes/k3/pengusahaan/buku-tamu';
import k3PengusahaanCertificate from '@/routes/k3/pengusahaan/certificate';
import k3PengusahaanEmergencyFacility from '@/routes/k3/pengusahaan/emergency-facility';
import k3PengusahaanEvaluasiPengujian from '@/routes/k3/pengusahaan/evaluasi-pengujian';
import k3PengusahaanFireAlarm from '@/routes/k3/pengusahaan/fire-alarm';
import k3PengusahaanHydrant from '@/routes/k3/pengusahaan/hydrant';
import k3PengusahaanInspeksiRambu from '@/routes/k3/pengusahaan/inspeksi-rambu';
import k3PengusahaanInspeksiTempatKerja from '@/routes/k3/pengusahaan/inspeksi-tempat-kerja';
import k3PengusahaanInventarisApd from '@/routes/k3/pengusahaan/inventaris-apd';
import k3PengusahaanJamKerja from '@/routes/k3/pengusahaan/jam-kerja';
import k3PengusahaanJamKerjaBulanan from '@/routes/k3/pengusahaan/jam-kerja-bulanan';
import k3PengusahaanKecelakaanInstalasi from '@/routes/k3/pengusahaan/kecelakaan-instalasi';
import k3PengusahaanKecelakaanMasyarakat from '@/routes/k3/pengusahaan/kecelakaan-masyarakat';
import k3PengusahaanLaporanCctv from '@/routes/k3/pengusahaan/laporan-cctv';
import k3PengusahaanMetodePengujian from '@/routes/k3/pengusahaan/metode-pengujian';
import k3PengusahaanPatrolSecurity from '@/routes/k3/pengusahaan/patrol-security';
import k3PengusahaanPemeriksaanP3k from '@/routes/k3/pengusahaan/pemeriksaan-p3k';
import k3PengusahaanTimeFrame from '@/routes/k3/pengusahaan/time-frame';
import operasiPengusahaan from '@/routes/operasi/pengusahaan';

/**
 * Akses 2 — Pengusahaan (Team Leader & Staf) of Operasi, Pemeliharaan & K3.
 *
 * Akses 1 — Laporan Project (Project Leader & Koordinator) keeps the existing
 * Jadwal / Input / Formulir menus. Akses 2 has its own menu per module (the
 * sections below, each a hub page), shown to roles holding the module's
 * `*.pengusahaan.view` permission — set per role in Role & Akses. Both
 * reports (Laporan Pembangkit & Laporan Pengusahaan) share one door: the
 * module's Laporan page, each card behind its own permission.
 *
 * To add a TL/Staf page: build the page (its controller authorises with the
 * module's pengusahaan permission, or a new permission of its own), then
 * append it to PENGUSAHAAN_MENUS with its module & section. Nothing else
 * needs to change: the hub page and the sidebar read this list.
 */
export type PengusahaanModuleKey = 'operasi' | 'har' | 'k3';

export type PengusahaanSectionKey = 'input' | 'formulir';

export const PENGUSAHAAN_MODULES: {
    key: PengusahaanModuleKey;
    title: string;
    permission: string;
    hub: (section: PengusahaanSectionKey) => string;
}[] = [
    { key: 'operasi', title: 'Operasi', permission: 'operasi.pengusahaan.view', hub: (section) => operasiPengusahaan.index(section).url },
    { key: 'har', title: 'Pemeliharaan', permission: 'har.pengusahaan.view', hub: (section) => harPengusahaan.index(section).url },
    { key: 'k3', title: 'K3 & Keamanan', permission: 'k3.pengusahaan.view', hub: (section) => k3Pengusahaan.index(section).url },
];

/** The menu sections of every module's Akses 2 (no Jadwal), in order (mirrors PengusahaanController::SECTIONS). */
export const PENGUSAHAAN_SECTIONS: { key: PengusahaanSectionKey; title: string; icon: LucideIcon }[] = [
    { key: 'input', title: 'Input', icon: SquarePen },
    { key: 'formulir', title: 'Formulir', icon: ClipboardCheck },
];

export type PengusahaanMenu = {
    module: PengusahaanModuleKey;
    section: PengusahaanSectionKey;
    title: string;
    description: string;
    icon: LucideIcon;
    /** Page URL; null while the page is still being built (shown disabled). */
    href: string | null;
    /** Shown only to users holding this permission (Role & Akses). */
    permission: string;
};

export const PENGUSAHAAN_MENUS: PengusahaanMenu[] = [
    {
        module: 'k3',
        section: 'input',
        title: 'Time Frame Pengusahaan',
        description: 'Matriks rencana dan realisasi time frame kinerja K3 dan keamanan bulanan.',
        icon: CalendarClock,
        href: k3PengusahaanTimeFrame.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Daftar Sertifikasi Peralatan',
        description: 'Monitoring sertifikasi dan perizinan peralatan K3 unit pembangkit.',
        icon: Award,
        href: k3PengusahaanCertificate.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Hari & Jam Kerja Karyawan',
        description: 'Laporan dan perhitungan hari kerja, jam kerja reguler, lembur, dan absensi seluruh karyawan unit.',
        icon: Clock,
        href: k3PengusahaanJamKerja.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Bulanan Jam Kerja Karyawan',
        description: 'Ringkasan 14 uraian jam kerja standar, lembur, absensi, realisasi, dan kumulatif bulanan (FMZ-08.4.4.11).',
        icon: CalendarRange,
        href: k3PengusahaanJamKerjaBulanan.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Emergency Facility',
        description: 'Pemeriksaan kesiapan fasilitas darurat, proteksi kebakaran, dan tanggap darurat per periode (M1-M4 & Bulanan).',
        icon: ShieldAlert,
        href: k3PengusahaanEmergencyFacility.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kecelakaan Instalasi',
        description: 'Laporan bulanan kejadian kecelakaan instalasi dan kerugian material (Kepdir 0251.P/DIR/2016).',
        icon: AlertTriangle,
        href: k3PengusahaanKecelakaanInstalasi.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kecelakaan Masyarakat Umum',
        description: 'Laporan bulanan kejadian kecelakaan masyarakat umum dan kerugian material (Kepdir 0252.P/DIR/2016).',
        icon: UserX,
        href: k3PengusahaanKecelakaanMasyarakat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Daftar Alat Tanggap Darurat',
        description: 'Daftar dan pemantauan kondisi kesiapan alat tanggap darurat (SMT-FM-AK3-03.03).',
        icon: LifeBuoy,
        href: k3PengusahaanAlatTanggapDarurat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Kondisi APAR dan APAB',
        description: 'Pemeriksaan dan monitoring kondisi fisik, tekanan, pin segel, dan masa kadaluarsa APAR & APAB (SMT-FM-AK3-12.03).',
        icon: Flame,
        href: k3PengusahaanAparApab.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Alat Pemadam Api Tradisional',
        description: 'Pemeriksaan dan monitoring kondisi fisik serta kesiapan alat pemadam api tradisional / APAT (SMT-FM-AK3-12.04).',
        icon: Boxes,
        href: k3PengusahaanApat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Hydrant',
        description: 'Pemeriksaan berkala kondisi hose, nozzle, box hydrant, dan tekanan air (SMT-FM-AK3-12.06).',
        icon: Droplets,
        href: k3PengusahaanHydrant.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Inventaris Alat Pelindung Diri',
        description: 'Inventaris dan pemantauan kondisi kesiapan alat pelindung diri utama dan pelengkap (SMT-FM-AK3-01.01).',
        icon: HardHat,
        href: k3PengusahaanInventarisApd.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Peralatan P3K',
        description: 'Pemeriksaan dan monitoring kelengkapan isi kotak P3K di seluruh lokasi unit (SMT-FM-AK3-10.01).',
        icon: BriefcaseMedical,
        href: k3PengusahaanPemeriksaanP3k.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Fire Alarm',
        description: 'Pemeriksaan berkala kondisi fisik panel dan detektor fire alarm unit pembangkit (SMT-FM-AK3-12.06).',
        icon: BellRing,
        href: k3PengusahaanFireAlarm.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Apel Satuan Keamanan',
        description: 'Laporan apel harian satuan pengamanan per shift dan pemantauan kelengkapan atribut (SMT-FM-AK3-13.13).',
        icon: UserCheck,
        href: k3PengusahaanApelKeamanan.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Inspeksi Rambu Rambu K3',
        description: 'Pemeriksaan rutin kondisi fisik, lokasi, dan tingkat pelanggaran rambu-rambu K3 (SMT-FM-AK3-07.01).',
        icon: Signpost,
        href: k3PengusahaanInspeksiRambu.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Patrol Check Security',
        description: 'Rekapitulasi dan pemantauan matriks scan patroli harian satuan pengamanan per checkpoint.',
        icon: ShieldCheck,
        href: k3PengusahaanPatrolSecurity.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kondisi CCTV',
        description: 'Laporan kondisi fisik, visual, dan pemantauan kamera pengawas CCTV di area unit pembangkit (SMT-FM-AK3-14.01).',
        icon: Cctv,
        href: k3PengusahaanLaporanCctv.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Mutasi Buku Tamu',
        description: 'Rekapitulasi mutasi dan kehadiran tamu berkunjung ke unit pembangkit (SMT-FM-AK3-06.04).',
        icon: BookUser,
        href: k3PengusahaanBukuTamu.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Metode Pengujian Peralatan',
        description: 'Formulir metode pemeriksaan, pengujian fisik, dan riwayat sertifikasi peralatan.',
        icon: ClipboardCheck,
        href: k3PengusahaanMetodePengujian.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Evaluasi Hasil Pengujian Peralatan',
        description: 'Formulir evaluasi temuan dalam sertifikat pengujian peralatan dan pemantauan progres tindak lanjut tahunan.',
        icon: FileSpreadsheet,
        href: k3PengusahaanEvaluasiPengujian.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Inspeksi Tempat Kerja dan Fasilitas',
        description: 'Formulir checklist inspeksi berkala kondisi tempat kerja, fasilitas kantor, keselamatan instalasi, dan lingkungan kerja (SMT-FM-AK3-12-01).',
        icon: Building2,
        href: k3PengusahaanInspeksiTempatKerja.index().url,
        permission: 'k3.pengusahaan.view',
    },
];
