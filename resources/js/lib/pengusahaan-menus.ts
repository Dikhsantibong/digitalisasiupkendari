import {
    Activity,
    AlertTriangle,
    Award,
    BatteryCharging,
    BellRing,
    BookUser,
    Boxes,
    BriefcaseMedical,
    Building2,
    CalendarClock,
    CalendarRange,
    Cctv,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Container,
    Cog,
    Disc,
    Droplet,
    Droplets,
    FileSignature,
    FileSpreadsheet,
    Flame,
    Fuel,
    Gauge,
    HardHat,
    LifeBuoy,
    NotebookPen,
    Plug,
    Ruler,
    ShieldAlert,
    ShieldCheck,
    Signpost,
    SquarePen,
    Timer,
    TimerReset,
    UserCheck,
    UserX,
    Waves,
    Wrench,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import harServiceRequest from '@/routes/har/input/service-request';
import harWorkOrder from '@/routes/har/input/work-order';
import harPengusahaan from '@/routes/har/pengusahaan';
import harPengusahaanAxialConrod from '@/routes/har/pengusahaan/axial-conrod';
import harPengusahaanBatteryVoltage from '@/routes/har/pengusahaan/battery-voltage';
import harPengusahaanClearanceValve from '@/routes/har/pengusahaan/clearance-valve';
import harPengusahaanCombustionPressure from '@/routes/har/pengusahaan/combustion-pressure';
import harPengusahaanCounterWeight from '@/routes/har/pengusahaan/counter-weight';
import harPengusahaanCrankshaftDeflection from '@/routes/har/pengusahaan/crankshaft-deflection';
import harPengusahaanHydrotest from '@/routes/har/pengusahaan/hydrotest';
import harPengusahaanInjectorPressure from '@/routes/har/pengusahaan/injector-pressure';
import harPengusahaanLaporanKegiatan from '@/routes/har/pengusahaan/laporan-kegiatan';
import harPengusahaanLubeQuality from '@/routes/har/pengusahaan/lube-quality';
import harPengusahaanMotorCurrent from '@/routes/har/pengusahaan/motor-current';
import harPengusahaanPrelubeTest from '@/routes/har/pengusahaan/prelube-test';
import harPengusahaanRencanaRealisasi from '@/routes/har/pengusahaan/rencana-realisasi';
import harPengusahaanTimingInjectionPump from '@/routes/har/pengusahaan/timing-injection-pump';
import harPengusahaanVibration from '@/routes/har/pengusahaan/vibration';
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
import operasiPengusahaanBaFisikPelumas from '@/routes/operasi/pengusahaan/ba-fisik-pelumas';
import operasiPengusahaanBebanTinggi from '@/routes/operasi/pengusahaan/beban-tinggi';
import operasiPengusahaanBeritaAcara from '@/routes/operasi/pengusahaan/berita-acara';
import operasiPengusahaanDailyReport from '@/routes/operasi/pengusahaan/daily-report';
import operasiPengusahaanEnergiDibangkit from '@/routes/operasi/pengusahaan/energi-dibangkit';
import operasiPengusahaanEnergiPemakaianSendiri from '@/routes/operasi/pengusahaan/energi-pemakaian-sendiri';
import operasiPengusahaanInventarisMesin from '@/routes/operasi/pengusahaan/inventaris-mesin';
import operasiPengusahaanJamGangguan from '@/routes/operasi/pengusahaan/jam-gangguan';
import operasiPengusahaanJamOperasi from '@/routes/operasi/pengusahaan/jam-operasi';
import operasiPengusahaanJamPemeliharaan from '@/routes/operasi/pengusahaan/jam-pemeliharaan';
import operasiPengusahaanJamSiapOps from '@/routes/operasi/pengusahaan/jam-siap-ops';
import operasiPengusahaanKaliGangguan from '@/routes/operasi/pengusahaan/kali-gangguan';
import operasiPengusahaanKinerja from '@/routes/operasi/pengusahaan/kinerja';
import operasiPengusahaanKinerjaTermal from '@/routes/operasi/pengusahaan/kinerja-termal';
import operasiPengusahaanKwhRekap from '@/routes/operasi/pengusahaan/kwh-rekap';
import operasiPengusahaanKwhTp from '@/routes/operasi/pengusahaan/kwh-tp';
import operasiPengusahaanPelumasTambahGanti from '@/routes/operasi/pengusahaan/pelumas-tambah-ganti';
import operasiPengusahaanPemakaianBbm from '@/routes/operasi/pengusahaan/pemakaian-bbm';
import operasiPengusahaanPemakaianPelumas from '@/routes/operasi/pengusahaan/pemakaian-pelumas';
import operasiPengusahaanPerincianBbm from '@/routes/operasi/pengusahaan/perincian-bbm';
import operasiPengusahaanPerincianPelumas from '@/routes/operasi/pengusahaan/perincian-pelumas';
import operasiPengusahaanPersediaanBbm from '@/routes/operasi/pengusahaan/persediaan-bbm';
import operasiPengusahaanPersediaanPelumas from '@/routes/operasi/pengusahaan/persediaan-pelumas';
import operasiPengusahaanRekapBbm from '@/routes/operasi/pengusahaan/rekap-bbm';
import operasiPengusahaanRekapPelumas from '@/routes/operasi/pengusahaan/rekap-pelumas';
import operasiPengusahaanSfc from '@/routes/operasi/pengusahaan/sfc';
import operasiPengusahaanSfcNetto from '@/routes/operasi/pengusahaan/sfc-netto';
import operasiPengusahaanStandKwh from '@/routes/operasi/pengusahaan/stand-kwh';
import operasiPengusahaanStandMeter from '@/routes/operasi/pengusahaan/stand-meter';
import operasiPengusahaanTaraKalor from '@/routes/operasi/pengusahaan/tara-kalor';
import operasiPengusahaanTugBbm from '@/routes/operasi/pengusahaan/tug-bbm';
import operasiPengusahaanTugPelumas from '@/routes/operasi/pengusahaan/tug-pelumas';

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
    {
        key: 'operasi',
        title: 'Operasi',
        permission: 'operasi.pengusahaan.view',
        hub: (section) => operasiPengusahaan.index(section).url,
    },
    {
        key: 'har',
        title: 'Pemeliharaan',
        permission: 'har.pengusahaan.view',
        hub: (section) => harPengusahaan.index(section).url,
    },
    {
        key: 'k3',
        title: 'K3 & Keamanan',
        permission: 'k3.pengusahaan.view',
        hub: (section) => k3Pengusahaan.index(section).url,
    },
];

/**
 * The menu sections of every module's Akses 2 (no Jadwal), in order (mirrors
 * PengusahaanController::SECTIONS). `modules` renames a section for one module:
 * Operasi's Formulir holds its Berita Acara (and TUG 9).
 */
export const PENGUSAHAAN_SECTIONS: {
    key: PengusahaanSectionKey;
    title: string;
    icon: LucideIcon;
    modules?: Partial<
        Record<PengusahaanModuleKey, { title: string; icon: LucideIcon }>
    >;
}[] = [
    { key: 'input', title: 'Input', icon: SquarePen },
    {
        key: 'formulir',
        title: 'Formulir',
        icon: ClipboardCheck,
        modules: { operasi: { title: 'Berita Acara', icon: FileSignature } },
    },
];

/** The title and icon of a section for one module. */
export function pengusahaanSection(
    key: PengusahaanSectionKey,
    module: PengusahaanModuleKey,
): { title: string; icon: LucideIcon } {
    const section = PENGUSAHAAN_SECTIONS.find((item) => item.key === key)!;

    return section.modules?.[module] ?? section;
}

/** The sub-headings of a hub, in display order. */
export const PENGUSAHAAN_MENU_GROUPS = [
    'Bahan Bakar',
    'Pelumas',
    'kWh',
    'Rekap',
] as const;

export type PengusahaanMenuGroup = (typeof PENGUSAHAAN_MENU_GROUPS)[number];

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
    /** A sub-heading inside the section's hub (Operasi Input); none = the section's main list. */
    group?: PengusahaanMenuGroup;
    /**
     * The Inertia page the menu opens; defaults to
     * `pengusahaan/{module}/{last URL segment}/index` (see .ai/rules/pengusahaan.md).
     */
    component?: string;
};

/** The Inertia page component a Pengusahaan menu opens (used by the phone shell). */
export function pengusahaanComponent(menu: PengusahaanMenu): string {
    return (
        menu.component ??
        `pengusahaan/${menu.module}/${(menu.href ?? '').split('?')[0].split('/').filter(Boolean).pop()}/index`
    );
}

export const PENGUSAHAAN_MENUS: PengusahaanMenu[] = [
    {
        module: 'operasi',
        section: 'input',
        group: 'Bahan Bakar',
        title: 'Stand Flow Meter BBM',
        description:
            'Pencatatan harian stand meter BBM generator, perhitungan pemakaian terkalibrasi, administrasi, dan selisih terhadap realisasi sounding.',
        icon: Gauge,
        href: operasiPengusahaanStandMeter.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/stand-meter/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Pelumas',
        title: 'Pemakaian Pelumas',
        description:
            'Pencatatan pemakaian pelumas bulanan per jenis pelumas dan mesin pembangkit.',
        icon: Droplets,
        href: operasiPengusahaanPemakaianPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/pemakaian-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Pelumas',
        title: 'Pelumas Tambah & Ganti',
        description:
            'Rincian pelumas tambah dan pelumas ganti per mesin per hari — dari Pemakaian Pelumas.',
        icon: Droplet,
        href: operasiPengusahaanPelumasTambahGanti.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/pelumas-tambah-ganti/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Pelumas',
        title: 'Persediaan Pelumas',
        description:
            'Persediaan awal, penerimaan, pemakaian, TUG 8/10, over flow dan saldo akhir pelumas per hari, dengan rekap Periode I–III.',
        icon: Container,
        href: operasiPengusahaanPersediaanPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/persediaan-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Pelumas',
        title: 'Perincian Minyak Pelumas',
        description:
            'Persediaan awal, penerimaan, pemakaian mesin, pengiriman ke unit dan sisa pelumas per jenis dalam satu bulan.',
        icon: ClipboardList,
        href: operasiPengusahaanPerincianPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/perincian-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Pelumas',
        title: 'Rekap Pelumas',
        description:
            'Pemakaian pelumas & grease per mesin dan alat bantu, sisa persediaan kartu dan fisik.',
        icon: FileSpreadsheet,
        href: operasiPengusahaanRekapPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/rekap-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Bahan Bakar',
        title: 'Pemakaian Bahan Bakar',
        description:
            'Pemakaian BBM harian (liter) per jenis BBM dan mesin, bisa diambil dari Stand Flow Meter.',
        icon: Fuel,
        href: operasiPengusahaanPemakaianBbm.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/pemakaian-bbm/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Bahan Bakar',
        title: 'Persediaan Bahan Bakar',
        description:
            'Persediaan awal, penerimaan, pemakaian, pengiriman dan saldo akhir BBM per hari, dengan rekap Periode I–IV.',
        icon: Container,
        href: operasiPengusahaanPersediaanBbm.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/persediaan-bbm/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Bahan Bakar',
        title: 'Perincian Bahan Bakar',
        description:
            'Kapasitas tangki, persediaan, penerimaan per periode, pemakaian, pengiriman dan sisa per jenis BBM dalam satu bulan.',
        icon: ClipboardList,
        href: operasiPengusahaanPerincianBbm.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/perincian-bbm/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Bahan Bakar',
        title: 'Rekap Bahan Bakar',
        description:
            'Pemakaian bahan bakar per mesin: pemakaian mesin, on operasi / test, cuci HAR dan bocor per jenis BBM.',
        icon: FileSpreadsheet,
        href: operasiPengusahaanRekapBbm.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/rekap-bbm/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Jam Operasi',
        description:
            'Jam operasi per mesin per hari (maks. 24 jam), bisa diambil dari Star-Stop.',
        icon: Timer,
        href: operasiPengusahaanJamOperasi.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/jam-operasi/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Jam Pemeliharaan',
        description:
            'Jam pemeliharaan (PO) per mesin per hari, bisa diambil dari Star-Stop.',
        icon: Wrench,
        href: operasiPengusahaanJamPemeliharaan.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/jam-pemeliharaan/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Jam Gangguan',
        description:
            'Jam gangguan per mesin per hari, bisa diambil dari Star-Stop.',
        icon: ShieldAlert,
        href: operasiPengusahaanJamGangguan.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/jam-gangguan/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Jam Siap Operasi',
        description:
            'Dihitung otomatis: 24 jam dikurangi jam operasi, pemeliharaan, dan gangguan.',
        icon: TimerReset,
        href: operasiPengusahaanJamSiapOps.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/jam-siap-ops/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Beban Tertinggi',
        description:
            'Rekap beban harian tertinggi (kW) per mesin, dengan daya mampu tiap mesin.',
        icon: Activity,
        href: operasiPengusahaanBebanTinggi.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/beban-tinggi/index',
    },
    {
        module: 'operasi',
        section: 'input',
        title: 'Jumlah Kali Gangguan',
        description:
            'Jumlah kejadian gangguan per mesin per hari, bisa diambil dari Star-Stop.',
        icon: AlertTriangle,
        href: operasiPengusahaanKaliGangguan.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/kali-gangguan/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'Stand kWh Harian',
        description:
            'Stand kWh meter produksi & pemakaian sendiri per mesin per hari — sumber semua menu kWh.',
        icon: Gauge,
        href: operasiPengusahaanStandKwh.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/stand-kwh/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'Stand kWh Meter untuk TP',
        description:
            'Stand awal & akhir kWh meter mesin dan pemakaian sendiri untuk perhitungan transfer pricing.',
        icon: FileSpreadsheet,
        href: operasiPengusahaanKwhTp.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/kwh-tp/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'kWh Rekap',
        description:
            'kWh produksi, kWh pemakaian sendiri dan kWh netto per mesin per hari — dari Stand kWh Harian.',
        icon: ClipboardList,
        href: operasiPengusahaanKwhRekap.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/kwh-rekap/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'Energi Dibangkit',
        description:
            'kWh nett per mesin per hari, dijumlah per merk dan total unit.',
        icon: Zap,
        href: operasiPengusahaanEnergiDibangkit.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/energi-dibangkit/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'Energi Pemakaian Sendiri',
        description:
            'kWh pemakaian sendiri per mesin per hari, dijumlah per merk dan total unit.',
        icon: Plug,
        href: operasiPengusahaanEnergiPemakaianSendiri.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/energi-pemakaian-sendiri/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'kWh',
        title: 'kWh / kCal',
        description:
            'Tara kalor (kCal/kWh) per mesin per hari — diisi manual, dengan rata-rata harian dan bulanan.',
        icon: Flame,
        href: operasiPengusahaanTaraKalor.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/tara-kalor/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'Ikhtisar Sentral',
        description:
            'Ikhtisar sentral produksi kWh, beban puncak, pemakaian BBM/pelumas per mesin, serta persediaan BBM dan pelumas.',
        icon: Gauge,
        href: operasiPengusahaanDailyReport.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/daily-report/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'SFC',
        description:
            'Spesifik fuel consumption (liter/kWh) per mesin per hari: pemakaian BBM ÷ kWh produksi.',
        icon: Gauge,
        href: operasiPengusahaanSfc.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/sfc/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'SFC Netto',
        description:
            'Spesifik fuel consumption netto (liter/kWh) per mesin per hari: pemakaian BBM ÷ kWh netto.',
        icon: Gauge,
        href: operasiPengusahaanSfcNetto.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/sfc-netto/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'Kinerja Unit Mesin',
        description:
            'Daya terpasang & mampu, kondisi, jam operasi/HAR/gangguan dan ratio daya — terisi otomatis, bisa dikoreksi.',
        icon: Gauge,
        href: operasiPengusahaanKinerja.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/kinerja/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'Data Kinerja Pembangkit Termal',
        description:
            'CF, OF, SF, EAF, POF, FOF, FOR, SOF, EFOR, SdOF & EFF per mesin — dihitung otomatis, bisa dikoreksi.',
        icon: Activity,
        href: operasiPengusahaanKinerjaTermal.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/kinerja-termal/index',
    },
    {
        module: 'operasi',
        section: 'input',
        group: 'Rekap',
        title: 'Daftar Inventarisasi Mesin',
        description:
            'Data penggerak & generator dari Master Mesin dengan daya mampu dan beban tertinggi bulan ini.',
        icon: Cog,
        href: operasiPengusahaanInventarisMesin.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/inventaris-mesin/index',
    },
    {
        module: 'operasi',
        section: 'formulir',
        title: 'Berita Acara',
        description:
            'Berita acara pemakaian BBM & pelumas bulanan — buat, edit, pratinjau, dan cetak PDF.',
        icon: FileSignature,
        href: operasiPengusahaanBeritaAcara.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/berita-acara/index',
    },
    {
        module: 'operasi',
        section: 'formulir',
        title: 'BA Pemeriksaan Fisik Pelumas',
        description:
            'Berita acara pemeriksaan fisik pelumas: stock administrasi dari Perincian Pelumas dan stock fisik (drum, cm, liter) hasil pemeriksaan.',
        icon: ClipboardCheck,
        href: operasiPengusahaanBaFisikPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/ba-fisik-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'formulir',
        title: 'TUG 9 Pelumas',
        description:
            'Rekap Bon Pemakaian Energi Primer (Pelumas) per mesin — data dari Pemakaian Pelumas, siap dicetak.',
        icon: FileSpreadsheet,
        href: operasiPengusahaanTugPelumas.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/tug-pelumas/index',
    },
    {
        module: 'operasi',
        section: 'formulir',
        title: 'TUG 9 BBM',
        description:
            'Rekap Bon Pemakaian Energi Primer (HSD/MFO) per mesin — data dari Pemakaian Bahan Bakar, siap dicetak.',
        icon: FileSpreadsheet,
        href: operasiPengusahaanTugBbm.index().url,
        permission: 'operasi.pengusahaan.view',
        component: 'pengusahaan/operasi/tug-bbm/index',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Time Frame Pengusahaan',
        description:
            'Matriks rencana dan realisasi time frame kinerja K3 dan keamanan bulanan.',
        icon: CalendarClock,
        href: k3PengusahaanTimeFrame.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Daftar Sertifikasi Peralatan',
        description:
            'Monitoring sertifikasi dan perizinan peralatan K3 unit pembangkit.',
        icon: Award,
        href: k3PengusahaanCertificate.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Hari & Jam Kerja Karyawan',
        description:
            'Laporan dan perhitungan hari kerja, jam kerja reguler, lembur, dan absensi seluruh karyawan unit.',
        icon: Clock,
        href: k3PengusahaanJamKerja.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Bulanan Jam Kerja Karyawan',
        description:
            'Ringkasan 14 uraian jam kerja standar, lembur, absensi, realisasi, dan kumulatif bulanan (FMZ-08.4.4.11).',
        icon: CalendarRange,
        href: k3PengusahaanJamKerjaBulanan.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Emergency Facility',
        description:
            'Pemeriksaan kesiapan fasilitas darurat, proteksi kebakaran, dan tanggap darurat per periode (M1-M4 & Bulanan).',
        icon: ShieldAlert,
        href: k3PengusahaanEmergencyFacility.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kecelakaan Instalasi',
        description:
            'Laporan bulanan kejadian kecelakaan instalasi dan kerugian material (Kepdir 0251.P/DIR/2016).',
        icon: AlertTriangle,
        href: k3PengusahaanKecelakaanInstalasi.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kecelakaan Masyarakat Umum',
        description:
            'Laporan bulanan kejadian kecelakaan masyarakat umum dan kerugian material (Kepdir 0252.P/DIR/2016).',
        icon: UserX,
        href: k3PengusahaanKecelakaanMasyarakat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Daftar Alat Tanggap Darurat',
        description:
            'Daftar dan pemantauan kondisi kesiapan alat tanggap darurat (SMT-FM-AK3-03.03).',
        icon: LifeBuoy,
        href: k3PengusahaanAlatTanggapDarurat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Kondisi APAR dan APAB',
        description:
            'Pemeriksaan dan monitoring kondisi fisik, tekanan, pin segel, dan masa kadaluarsa APAR & APAB (SMT-FM-AK3-12.03).',
        icon: Flame,
        href: k3PengusahaanAparApab.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Alat Pemadam Api Tradisional',
        description:
            'Pemeriksaan dan monitoring kondisi fisik serta kesiapan alat pemadam api tradisional / APAT (SMT-FM-AK3-12.04).',
        icon: Boxes,
        href: k3PengusahaanApat.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Hydrant',
        description:
            'Pemeriksaan berkala kondisi hose, nozzle, box hydrant, dan tekanan air (SMT-FM-AK3-12.06).',
        icon: Droplets,
        href: k3PengusahaanHydrant.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Inventaris Alat Pelindung Diri',
        description:
            'Inventaris dan pemantauan kondisi kesiapan alat pelindung diri utama dan pelengkap (SMT-FM-AK3-01.01).',
        icon: HardHat,
        href: k3PengusahaanInventarisApd.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Peralatan P3K',
        description:
            'Pemeriksaan dan monitoring kelengkapan isi kotak P3K di seluruh lokasi unit (SMT-FM-AK3-10.01).',
        icon: BriefcaseMedical,
        href: k3PengusahaanPemeriksaanP3k.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Pemeriksaan Fire Alarm',
        description:
            'Pemeriksaan berkala kondisi fisik panel dan detektor fire alarm unit pembangkit (SMT-FM-AK3-12.06).',
        icon: BellRing,
        href: k3PengusahaanFireAlarm.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Apel Satuan Keamanan',
        description:
            'Laporan apel harian satuan pengamanan per shift dan pemantauan kelengkapan atribut (SMT-FM-AK3-13.13).',
        icon: UserCheck,
        href: k3PengusahaanApelKeamanan.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Inspeksi Rambu Rambu K3',
        description:
            'Pemeriksaan rutin kondisi fisik, lokasi, dan tingkat pelanggaran rambu-rambu K3 (SMT-FM-AK3-07.01).',
        icon: Signpost,
        href: k3PengusahaanInspeksiRambu.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Patrol Check Security',
        description:
            'Rekapitulasi dan pemantauan matriks scan patroli harian satuan pengamanan per checkpoint.',
        icon: ShieldCheck,
        href: k3PengusahaanPatrolSecurity.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Kondisi CCTV',
        description:
            'Laporan kondisi fisik, visual, dan pemantauan kamera pengawas CCTV di area unit pembangkit (SMT-FM-AK3-14.01).',
        icon: Cctv,
        href: k3PengusahaanLaporanCctv.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'input',
        title: 'Laporan Mutasi Buku Tamu',
        description:
            'Rekapitulasi mutasi dan kehadiran tamu berkunjung ke unit pembangkit (SMT-FM-AK3-06.04).',
        icon: BookUser,
        href: k3PengusahaanBukuTamu.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Metode Pengujian Peralatan',
        description:
            'Formulir metode pemeriksaan, pengujian fisik, dan riwayat sertifikasi peralatan.',
        icon: ClipboardCheck,
        href: k3PengusahaanMetodePengujian.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Evaluasi Hasil Pengujian Peralatan',
        description:
            'Formulir evaluasi temuan dalam sertifikat pengujian peralatan dan pemantauan progres tindak lanjut tahunan.',
        icon: FileSpreadsheet,
        href: k3PengusahaanEvaluasiPengujian.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'k3',
        section: 'formulir',
        title: 'Inspeksi Tempat Kerja dan Fasilitas',
        description:
            'Formulir checklist inspeksi berkala kondisi tempat kerja, fasilitas kantor, keselamatan instalasi, dan lingkungan kerja (SMT-FM-AK3-12-01).',
        icon: Building2,
        href: k3PengusahaanInspeksiTempatKerja.index().url,
        permission: 'k3.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'input',
        title: 'Work Order',
        description:
            'Work order pemeliharaan (PM, CM, rekomendasi engineering, waiting shutdown & material) — data yang sama dengan Koordinator Pemeliharaan.',
        icon: Wrench,
        href: harWorkOrder.index().url,
        permission: 'har.pengusahaan.view',
        component: 'har/input/work-order/index',
    },
    {
        module: 'har',
        section: 'input',
        title: 'Service Request',
        description:
            'Permintaan perbaikan (service request) dari operasi — data yang sama dengan Koordinator Pemeliharaan.',
        icon: ClipboardList,
        href: harServiceRequest.index().url,
        permission: 'har.pengusahaan.view',
        component: 'har/input/service-request/index',
    },
    {
        module: 'har',
        section: 'input',
        title: 'Rencana & Realisasi',
        description:
            'Rencana & realisasi pemeliharaan rutin (P0–P5), pengukuran air dan monitoring pelumas per mesin.',
        icon: CalendarRange,
        href: harPengusahaanRencanaRealisasi.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'input',
        title: 'Laporan Kegiatan Pemeliharaan',
        description:
            'Kegiatan pemeliharaan mesin serta listrik & kontrol pembangkit per bulan: uraian per mesin, jenis HAR, material dan keterangan.',
        icon: NotebookPen,
        href: harPengusahaanLaporanKegiatan.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Checklist Prelube Test',
        description:
            'Checklist pelumasan awal per silinder: camshaft, conrod, piston & rocker arm.',
        icon: Droplet,
        href: harPengusahaanPrelubeTest.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Checklist Hydrotest',
        description: 'Checklist kebocoran O-ring liner & liner per silinder.',
        icon: Waves,
        href: harPengusahaanHydrotest.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Timing Injection Pump',
        description:
            'Checklist timing pembakaran injection pump sebelum & sesudah penyetelan.',
        icon: Timer,
        href: harPengusahaanTimingInjectionPump.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Defleksi Crankshaft',
        description:
            'Pengukuran defleksi crankshaft per silinder (posisi A–E).',
        icon: Ruler,
        href: harPengusahaanCrankshaftDeflection.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Baut Counter Weight',
        description:
            'Pemeriksaan kekencangan baut counter weight per silinder.',
        icon: Cog,
        href: harPengusahaanCounterWeight.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Axial Conrod & Baut Conrod',
        description:
            'Pemeriksaan axial connecting rod & pengencangan baut conrod.',
        icon: Disc,
        href: harPengusahaanAxialConrod.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Clearance Valve',
        description:
            'Pengukuran clearance katup exhaust & inlet sebelum dan sesudah penyetelan.',
        icon: Ruler,
        href: harPengusahaanClearanceValve.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Tekanan Pembakaran',
        description:
            'Tekanan pembakaran, temperatur gas buang & rack injection pump per silinder.',
        icon: Flame,
        href: harPengusahaanCombustionPressure.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Tekanan Pengabutan Injektor',
        description: 'Tekanan pengabutan injektor sebelum & sesudah kalibrasi.',
        icon: Fuel,
        href: harPengusahaanInjectorPressure.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Arus Kerja Elektro Motor',
        description: 'Arus kerja elektro motor auxiliary per fasa (R, S, T).',
        icon: Zap,
        href: harPengusahaanMotorCurrent.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Tekanan Vibrasi',
        description: 'Vibrasi vertikal & horizontal per titik pengukuran.',
        icon: Activity,
        href: harPengusahaanVibration.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Kualitas Pelumas',
        description:
            'Hasil uji laboratorium oli pelumas, analisa & rekomendasi.',
        icon: Droplets,
        href: harPengusahaanLubeQuality.index().url,
        permission: 'har.pengusahaan.view',
    },
    {
        module: 'har',
        section: 'formulir',
        title: 'Tegangan Baterai',
        description: 'Tegangan sel baterai 24 V & 110 V dan kondisi charging.',
        icon: BatteryCharging,
        href: harPengusahaanBatteryVoltage.index().url,
        permission: 'har.pengusahaan.view',
    },
];
