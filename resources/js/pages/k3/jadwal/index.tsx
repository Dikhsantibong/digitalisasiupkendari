import { Head, router } from '@inertiajs/react';
import type { Users } from 'lucide-react';
import {
    CalendarDays,
    CalendarRange,
    ChevronRight,
    FilePen,
    FileText,
    Leaf,
    PhoneCall,
    ShieldCheck,
} from 'lucide-react';
import {
    K3MobileFilterCard,
    K3MobileHeader,
} from '@/components/k3/mobile-page-shell';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import jadwal from '@/routes/k3/jadwal';
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
    '/k3/input/time-frame',
    '/k3/jadwal/kegiatan-rutin',
    '/k3/jadwal/instruksi-kerja',
    '/k3/jadwal/patrol-check',
    '/k3/jadwal/pekerjaan-rutin',
    '/k3/jadwal/on-call',
    '/k3/jadwal/pembuatan-ik',
];

const JADWAL_LIST: JadwalCard[] = [
    {
        // Moved from Input K3 (user, 2026-11-27): a plan (rencana) & realisasi time line, for the Koordinator K3.
        title: 'Time Frame Kinerja K3 & Lingkungan',
        description:
            'Rencana (RENC) dan realisasi (REAL) program kerja K3 & Lingkungan per hari dalam sebulan, beserta target dan kinerja.',
        icon: CalendarRange,
        target: '/k3/input/time-frame',
    },
    {
        title: 'Jadwal On Call K3L',
        description:
            'Jadwal on call dan kesiapan personil K3L periode cut-off 16 s/d 15 beserta perhitungan nilai dan rupiah insentif.',
        icon: PhoneCall,
        target: '/k3/jadwal/on-call',
    },
    {
        title: 'Jadwal Kegiatan Harian, Mingguan, Bulanan & Non Rutin K3L KIT',
        description:
            'Agenda berkala pelaksanaan program keselamatan kerja dan lindungan lingkungan di unit pembangkit.',
        icon: CalendarDays,
        target: '/k3/jadwal/kegiatan-rutin',
    },
    {
        title: 'Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT',
        description:
            'Program kerja rutin pengelolaan keselamatan kerja dan kelestarian lingkungan di 11 KIT.',
        icon: Leaf,
        target: '/k3/jadwal/pekerjaan-rutin',
    },
    {
        title: 'Jadwal Patrol Check Harian K3L & Lingkungan',
        description:
            'Inspeksi patroli harian keliling area pembangkit untuk deteksi dini bahaya dan pemantauan lingkungan.',
        icon: ShieldCheck,
        target: '/k3/jadwal/patrol-check',
    },
    {
        title: 'Jadwal Instruksi Kerja K3L',
        description:
            'Penyusunan, sosialisasi, standardisasi, dan peninjauan Instruksi Kerja (IK) prosedur K3L.',
        icon: FileText,
        target: '/k3/jadwal/instruksi-kerja',
    },
    {
        title: 'Jadwal Pembuatan IK K3',
        description:
            'Rencana (R) dan realisasi (✓) penyusunan dokumen Instruksi Kerja K3 & Lingkungan per bulan dalam satu tahun, lengkap dengan PIC pembuat.',
        icon: FilePen,
        target: '/k3/jadwal/pembuatan-ik',
    },
];

export default function K3JadwalIndex({ filters, options }: Props) {
    const compact = useCompactLayout();
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            jadwal.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    if (compact) {
        return (
            <>
                <Head title="Jadwal K3 & Lingkungan" />
                <div className="flex flex-1 flex-col gap-3 p-3">
                    <K3MobileHeader title="Jadwal K3 & Lingkungan" />

                    <K3MobileFilterCard
                        unitId={filters.unit_id}
                        units={options.units}
                        onUnitChange={(v) => visit({ unit_id: v })}
                        month={filters.month}
                        onMonthChange={(v) => visit({ month: v })}
                        year={filters.year}
                        years={options.years}
                        onYearChange={(v) => visit({ year: v })}
                    />

                    <div className="flex flex-col gap-2">
                        {JADWAL_LIST.map((item) => {
                            const Icon = item.icon;
                            const isAvailable = AVAILABLE_TARGETS.includes(
                                item.target,
                            );

                            return (
                                <button
                                    key={item.title}
                                    type="button"
                                    disabled={!isAvailable}
                                    onClick={() =>
                                        router.get(item.target, {
                                            unit_id: filters.unit_id,
                                            month: filters.month,
                                            year: filters.year,
                                        })
                                    }
                                    className="flex items-center gap-3 rounded-xl border border-border bg-card p-3 text-left transition active:scale-[0.99] disabled:opacity-60"
                                >
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </div>
                                    <span className="min-w-0 flex-1 text-[13.5px] leading-snug font-semibold text-foreground">
                                        {item.title}
                                    </span>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </button>
                            );
                        })}
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Jadwal K3 & Lingkungan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal K3 & Lingkungan"
                    description="Pilih unit & periode, lalu kelola jadwal kegiatan K3 dan lindungan lingkungan pembangkit."
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
                        const isAvailable = AVAILABLE_TARGETS.includes(
                            item.target,
                        );

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
                                            <Badge
                                                variant="outline"
                                                className="border-emerald-500/30 bg-emerald-500/10 text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                                            >
                                                Tersedia
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="text-[11px] font-normal text-muted-foreground"
                                            >
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
                                            onClick={() =>
                                                router.get(item.target, {
                                                    unit_id: filters.unit_id,
                                                    month: filters.month,
                                                    year: filters.year,
                                                })
                                            }
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
                                                * Tombol belum difungsikan
                                                (tabel sedang disusun)
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

K3JadwalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3 & Lingkungan', href: jadwal.index() },
    ],
};
