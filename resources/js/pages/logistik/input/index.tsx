import { Head, Link } from '@inertiajs/react';
import type {
    ClipboardList} from 'lucide-react';
import {
    Boxes,
    ClipboardCheck,
    FileSignature,
    FolderOpen,
    TriangleAlert,
    Gauge,
    MonitorSmartphone,
    Sparkles,
    Lightbulb,
    Package,
    Warehouse,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import logistikInput from '@/routes/logistik/input';
import formRoutes from '@/routes/logistik/input/form';
import rekomendasi from '@/routes/logistik/input/rekomendasi';
import inputSheet from '@/routes/logistik/input/sheet';

type InputCard = {
    title: string;
    description: string;
    icon: typeof ClipboardList;
    /** The form's page; cards without one are still being built. */
    href?: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Rekomendasi Logistik & Gudang',
        description:
            'Rekomendasi bulanan: kondisi existing stok material, spare part kritis, consumable, tools, dan sarana kerja beserta tindak lanjutnya.',
        icon: Lightbulb,
        href: rekomendasi.index().url,
    },
    {
        title: 'Laporan Kegiatan Patrol Check Logistik & Gudang',
        description:
            'Pencatatan hasil patroli pengecekan kondisi gudang, area penyimpanan, dan kelengkapan material serta tools.',
        icon: ClipboardCheck,
        href: inputSheet.index('patrol-check').url,
    },
    {
        title: 'Laporan Inspeksi Checklist 5S5R Logistik & Gudang',
        description:
            'Penilaian Ringkas, Rapi, Resik, Rawat, Rajin per item pada 15 kali pelaksanaan, dengan eviden foto dan akumulatif.',
        icon: Sparkles,
        href: inputSheet.index('inspeksi-5s5r').url,
    },
    {
        title: 'Laporan Input Data Aplikasi Pembangkit',
        description:
            'Realisasi input data harian ke aplikasi pembangkit (ELIPS, dll.) terhadap target setiap hari.',
        icon: MonitorSmartphone,
        href: inputSheet.index('input-aplikasi').url,
    },
    {
        title: 'Maturity Level Logistik & Gudang',
        description:
            'Penilaian maturity level (0–5) manajemen persediaan (inventory) dan manajemen gudang (warehouse).',
        icon: Gauge,
        href: inputSheet.index('maturity').url,
    },
    {
        title: 'Laporan Inventaris Lainnya Logistik & Gudang',
        description:
            'Pemeriksaan harian inventaris lainnya (meja, kursi, komputer, HT, lemari, rak): N/T per tanggal, target, realisasi, dan kinerja.',
        icon: Boxes,
        href: formRoutes.index('inventaris-lainnya').url,
    },
    {
        title: 'Laporan Peralatan, Material dan Tools Logistik & Gudang',
        description:
            'Pencatatan daftar peralatan, material consumable, dan tools yang dikelola gudang beserta parameternya.',
        icon: Package,
        href: formRoutes.index('peralatan').url,
    },
    {
        title: 'Laporan Kondisi Stok Tools dan Material',
        description:
            'Pemantauan kondisi dan level stok tools serta material: stok awal, masuk, keluar, dan stok akhir.',
        icon: Warehouse,
        href: formRoutes.index('kondisi-stok').url,
    },
    {
        title: 'Laporan Unsafe Action dan Unsafe Condition',
        description:
            'Temuan unsafe action & unsafe condition per minggu dengan kondisi, tindak lanjut, rekomendasi, lokasi, dan eviden foto sebelum/sesudah.',
        icon: TriangleAlert,
        href: formRoutes.index('unsafe').url,
    },
    {
        title: 'Laporan Pendukung Logistik & Gudang',
        description:
            'Daftar dokumen pendukung laporan logistik & gudang (resume, stok opname, penerimaan, pemakaian, return, pengiriman, IK) beserta folder & detailnya.',
        icon: FolderOpen,
        href: formRoutes.index('pendukung').url,
    },
    {
        title: 'Laporan Permit To Work Pembangkit',
        description:
            'Pencatatan izin kerja (Permit To Work) di area pembangkit: uraian, tanggal, status Open/Close, dan total per status.',
        icon: FileSignature,
        href: formRoutes.index('permit-to-work').url,
    },
];

export default function LogistikInputIndex() {
    return (
        <>
            <Head title="Input Logistik & Gudang" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input Logistik & Gudang"
                    description="Pilih jenis formulir data kegiatan logistik dan pergudangan pembangkit untuk diinput dan dikelola."
                />

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {INPUT_MENUS.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-5 transition-all hover:border-primary/30"
                            >
                                <div>
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        {item.href ? (
                                            <Badge
                                                variant="outline"
                                                className="border-emerald-600/30 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                            >
                                                Tersedia
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="text-[11px] font-normal text-muted-foreground"
                                            >
                                                Segera Hadir
                                            </Badge>
                                        )}
                                    </div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>

                                <div className="space-y-1.5 pt-2">
                                    {item.href ? (
                                        <>
                                            <Button asChild className="w-full justify-center gap-2">
                                                <Link href={item.href}>
                                                    <Icon className="size-4" />
                                                    Buka Formulir
                                                </Link>
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Input data, simpan, cetak PDF &amp; unduh Excel
                                            </p>
                                        </>
                                    ) : (
                                        <>
                                            <Button
                                                disabled
                                                variant="outline"
                                                className="w-full cursor-not-allowed justify-center gap-2 opacity-75"
                                                title="Tombol sementara dinonaktifkan (halaman sedang disusun)"
                                            >
                                                <Icon className="size-4" />
                                                Buka Formulir
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground italic">
                                                * Tombol belum difungsikan (halaman
                                                sedang disusun)
                                            </p>
                                        </>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

LogistikInputIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Logistik & Gudang', href: logistikInput.index() },
    ],
};
