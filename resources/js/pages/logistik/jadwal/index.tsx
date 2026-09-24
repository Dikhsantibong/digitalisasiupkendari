import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    CalendarClock,
    ClipboardList,
    FileText,
    PhoneCall,
    ShieldCheck,
    Sparkles,
    Users,
    Wrench,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import jadwal from '@/routes/logistik/jadwal';
import sheet from '@/routes/logistik/jadwal/sheet';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

type JadwalCard = {
    title: string;
    description: string;
    icon: typeof Activity;
    /** App\Support\LogistikJadwal sheet key; cards without one are still being built. */
    sheetKey?: string;
};

const JADWAL_LIST: JadwalCard[] = [
    {
        title: 'Jadwal Kegiatan Logistik & Gudang',
        description:
            'Kegiatan rutin harian, mingguan, bulanan dan non rutin logistik & gudang per tanggal, dengan target, rencana, realisasi dan kinerja.',
        icon: Activity,
        sheetKey: 'kegiatan',
    },
    {
        title: 'Jadwal Shift Operator Logistik & Gudang',
        description:
            'Jadwal shift harian personil logistik (Pagi / Off / Sakit / Izin / Cuti / Mankir) dengan rekap absensi dan persentase kehadiran.',
        icon: Users,
        sheetKey: 'shift',
    },
    {
        title: 'Jadwal Piket Patrol Check Logistik & Gudang (On Call)',
        description:
            'Rencana & realisasi piket patrol check / on call personil logistik & gudang per tanggal.',
        icon: PhoneCall,
        sheetKey: 'piket',
    },
    {
        title: 'Jadwal Pelaksanaan 5S5R Logistik & Gudang',
        description:
            'Rencana & realisasi pelaksanaan budaya kerja Ringkas, Rapi, Resik, Rawat, Rajin di area gudang dan penyimpanan.',
        icon: Sparkles,
        sheetKey: '5s5r',
    },
    {
        title: 'Jadwal Meeting Logistik & Gudang',
        description:
            'Rencana & realisasi rapat koordinasi logistik & gudang per tanggal.',
        icon: CalendarClock,
        sheetKey: 'meeting',
    },
    {
        title: 'Jadwal Inventarisasi Tools dan Material Bagian Lainnya',
        description:
            'Rencana & realisasi inventarisasi harian tools dan material bagian lainnya per tanggal.',
        icon: ClipboardList,
        sheetKey: 'inventarisasi',
    },
    {
        title: 'Jadwal Pembuatan Instruksi Kerja (IK) Logistik & Gudang',
        description:
            'Rencana & realisasi pembuatan Instruksi Kerja (IK) per bulan dalam setahun, dengan jumlah, total IK dan capaian.',
        icon: FileText,
        sheetKey: 'ik',
    },
    {
        title: 'Jadwal Pemeliharaan Logistik dan Gudang',
        description:
            'Rencana & realisasi pemeliharaan sarana, rak, dan fasilitas penyimpanan gudang per tanggal, dengan target, realisasi, dan kinerja.',
        icon: Wrench,
        sheetKey: 'pemeliharaan',
    },
    {
        title: 'Jadwal Piket Patrol Check Logistik & Gudang',
        description:
            'Rencana & realisasi piket patrol check stok material & tools gudang (Senin & Jumat), dengan target, realisasi, dan kinerja.',
        icon: ShieldCheck,
        sheetKey: 'piket-patrol-check',
    },
];

export default function LogistikJadwalIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            jadwal.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Jadwal Logistik & Gudang" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Logistik & Gudang"
                    description="Pilih unit & periode, lalu kelola jadwal kegiatan logistik dan pergudangan pembangkit."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {JADWAL_LIST.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4 transition-all hover:border-primary/30"
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        {item.sheetKey ? (
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
                                    {item.sheetKey ? (
                                        <>
                                            <Button asChild className="w-full justify-center gap-2">
                                                <Link href={sheet.index(item.sheetKey, { query: filters }).url}>
                                                    <Icon className="size-4" />
                                                    Input Jadwal
                                                </Link>
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Input jadwal, simpan, cetak PDF &amp; unduh Excel
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
                                                Input Jadwal
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

LogistikJadwalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Logistik & Gudang', href: jadwal.index() },
    ],
};
