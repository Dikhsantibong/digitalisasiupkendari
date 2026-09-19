import { Head, router } from '@inertiajs/react';
import {
    Activity,
    CalendarClock,
    CalendarDays,
    FileText,
    PackageCheck,
    Sliders,
    Sparkles,
    Users,
    Wrench,
    ZapOff,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import jadwal from '@/routes/operasi/jadwal';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

type JadwalCard = {
    title: string;
    description: string;
    icon: typeof Users;
    target: string;
};

// Targets whose page is built; the rest render as "Sementara Disusun".
const AVAILABLE_TARGETS: string[] = [
    '/operasi/jadwal/flm',
    '/operasi/jadwal/program-5s-5r',
    '/operasi/jadwal/meeting-shift',
    '/operasi/jadwal/inventarisasi-tools',
    '/operasi/jadwal/pembuatan-ik',
    '/operasi/jadwal/pembuatan-data-teknis',
    '/operasi/jadwal/blackstart',
    '/operasi/jadwal/commissioning-test',
    '/operasi/jadwal/commissioning-test-peralatan',
];

const JADWAL_LIST: JadwalCard[] = [
    {
        title: 'Jadwal Shift Operator',
        description: 'Penjadwalan gilir kerja (shift) regu operator unit pembangkit harian dan bulanan.',
        icon: Users,
        target: '/operasi/jadwal/shift-operator',
    },
    {
        title: 'Jadwal FLM',
        description: 'Jadwal pemeliharaan tingkat pertama (First Line Maintenance) oleh tim operasi pembangkit.',
        icon: Wrench,
        target: '/operasi/jadwal/flm',
    },
    {
        title: 'Jadwal Program 5S 5R',
        description: 'Jadwal pelaksanaan program Ringkas, Rapi, Resik, Rawat, Rajin di area kerja pembangkit.',
        icon: Sparkles,
        target: '/operasi/jadwal/program-5s-5r',
    },
    {
        title: 'Jadwal Meeting Shift',
        description: 'Jadwal briefing serah terima (handover), koordinasi, dan evaluasi operasional antar shift.',
        icon: CalendarClock,
        target: '/operasi/jadwal/meeting-shift',
    },
    {
        title: 'Jadwal Inventarisasi Tools & Material Operasi',
        description: 'Pemeriksaan dan pendataan rutin ketersediaan tools kerja, APD, dan material operasional.',
        icon: PackageCheck,
        target: '/operasi/jadwal/inventarisasi-tools',
    },
    {
        title: 'Jadwal Pembuatan IK',
        description: 'Penyusunan dan pemutakhiran Instruksi Kerja (IK) operasi — matriks tahunan per bulan.',
        icon: FileText,
        target: '/operasi/jadwal/pembuatan-ik',
    },
    {
        title: 'Jadwal Pembuatan Data Teknis Pembangkit',
        description: 'Penyusunan dokumen data teknis pembangkit (jadwal, laporan, checklist) — matriks tahunan per bulan.',
        icon: FileText,
        target: '/operasi/jadwal/pembuatan-data-teknis',
    },
    {
        title: 'Jadwal Pemeriksaan Instalasi Blackstart',
        description: 'Jadwal pengujian dan inspeksi kesiapan fasilitas suplai darurat Blackstart Diesel Generator.',
        icon: ZapOff,
        target: '/operasi/jadwal/blackstart',
    },
    {
        title: 'Jadwal Commissioning Test Mesin',
        description: 'Jadwal uji unjuk kerja (performance test) dan komisioning mesin pembangkit pasca pemeliharaan.',
        icon: Activity,
        target: '/operasi/jadwal/commissioning-test',
    },
    {
        title: 'Jadwal Commissioning Test Peralatan Non Mesin',
        description: 'Jadwal uji kelayakan peralatan bantu (auxiliary), proteksi, dan instrumentasi kelistrikan.',
        icon: Sliders,
        target: '/operasi/jadwal/commissioning-test-peralatan',
    },
    {
        title: 'Jadwal Rencana Operasi (ROT, ROB, ROM)',
        description: 'Perencanaan pola operasi pembangkit harian (ROT), bulanan (ROB), dan tahunan (ROM).',
        icon: CalendarDays,
        target: '/operasi/jadwal/rencana-operasi',
    },
];

export default function OperasiJadwalIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            jadwal.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Jadwal Operasi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Operasi"
                    description="Pilih unit & periode, lalu kelola jadwal kegiatan operasional pembangkit."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {JADWAL_LIST.map((item) => {
                        const Icon = item.icon;
                        const isAvailable = AVAILABLE_TARGETS.includes(item.target);
                        return (
                            <div
                                key={item.title}
                                className={`flex flex-col justify-between gap-4 rounded-md border bg-card p-4 transition-all ${
                                    isAvailable
                                        ? 'border-primary/40 shadow-xs hover:border-primary'
                                        : 'border-border hover:border-primary/30'
                                }`}
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div
                                            className={`flex size-9 items-center justify-center rounded-lg ${
                                                isAvailable
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-primary/10 text-primary'
                                            }`}
                                        >
                                            <Icon className="size-5" />
                                        </div>
                                        {isAvailable ? (
                                            <Badge variant="outline" className="border-emerald-500/30 bg-emerald-500/10 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                                                Tersedia
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
                                                Sementara Disusun
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
                                    {isAvailable ? (
                                        <Button
                                            className="w-full justify-center gap-2"
                                            onClick={() => router.get(item.target, { unit_id: filters.unit_id, month: filters.month, year: filters.year })}
                                        >
                                            <Icon className="size-4" />
                                            Buka Jadwal
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                disabled
                                                variant="outline"
                                                className="w-full cursor-not-allowed justify-center gap-2 opacity-75"
                                                title="Tombol sementara dinonaktifkan (tabel sedang disusun)"
                                            >
                                                <Icon className="size-4" />
                                                Input Jadwal
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground italic">
                                                * Tombol belum difungsikan (tabel sedang disusun)
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

OperasiJadwalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Operasi', href: jadwal.index() },
    ],
};