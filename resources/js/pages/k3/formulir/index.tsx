import { Head, router } from '@inertiajs/react';
import {
    Boxes,
    CalendarCheck,
    ChevronRight,
    Droplets,
    FileCheck2,
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
import formulir from '@/routes/k3/formulir';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

type FormulirCard = {
    title: string;
    description: string;
    icon: typeof ShieldCheck;
    target: string;
    active?: boolean;
};

const FORMULIR_LIST: FormulirCard[] = [
    {
        title: 'Formulir Atribut, Peralatan, Administrasi, dan Sarana Prasarana',
        description:
            'Pemeriksaan kelengkapan atribut K3, ketersediaan APD, peralatan darurat, kelengkapan administrasi, dan kondisi sarana prasarana keselamatan.',
        icon: ShieldCheck,
        target: '/k3/formulir/sarana-prasarana',
        active: true,
    },
    {
        title: 'Form Kontrol K3 Mingguan',
        description:
            'Monitoring dan pengawasan kepatuhan K3 mingguan terhadap implementasi safety briefing, unsafe action/condition, dan kebersihan area kerja.',
        icon: CalendarCheck,
        target: '/k3/formulir/kontrol-mingguan',
        active: true,
    },
    {
        title: 'Formulir Metode Pengujian Peralatan',
        description:
            'Pencatatan standar operasional dan metode pengujian peralatan K3 & keselamatan kerja sebelum dioperasikan di unit pembangkit.',
        icon: FileCheck2,
        target: '/k3/formulir/metode-pengujian',
        active: true,
    },
    {
        title: 'Formulir Pemeliharaan TPS LB3',
        description:
            'Pemeriksaan dan pencatatan kondisi Tempat Penyimpanan Sementara Limbah Bahan Berbahaya dan Beracun (TPS LB3), penataan drum, dan simbol limbah.',
        icon: Boxes,
        target: '/k3/formulir/pemeliharaan-tps-lb3',
        active: true,
    },
    {
        title: 'Formulir Pemeliharaan Oil Trap',
        description:
            'Inspeksi berkala dan pemeliharaan bak penangkap ceceran minyak (oil trap/separator) untuk mencegah pencemaran lingkungan air limbah.',
        icon: Droplets,
        target: '/k3/formulir/pemeliharaan-oil-trap',
        active: true,
    },
];

export default function K3FormulirIndex({ filters, options }: Props) {
    const compact = useCompactLayout();
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            formulir.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    if (compact) {
        return (
            <>
                <Head title="Formulir K3 & Keamanan" />
                <div className="flex flex-1 flex-col gap-3 p-3">
                    <K3MobileHeader title="Formulir K3 & Keamanan" />

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
                        {FORMULIR_LIST.map((item) => {
                            const Icon = item.icon;

                            return (
                                <button
                                    key={item.title}
                                    type="button"
                                    disabled={!item.active}
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
            <Head title="Formulir K3 & Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir K3 & Keamanan"
                    description="Pilih unit & periode, lalu kelola formulir kepatuhan, inspeksi, dan pengujian K3 lingkungan pembangkit."
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
                    {FORMULIR_LIST.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4 transition-all hover:border-primary/50"
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div
                                            className={`flex size-9 items-center justify-center rounded-lg ${item.active ? 'bg-primary text-primary-foreground' : 'bg-primary/10 text-primary'}`}
                                        >
                                            <Icon className="size-5" />
                                        </div>
                                        {item.active ? (
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
                                    {item.active ? (
                                        <>
                                            <Button
                                                onClick={() =>
                                                    router.get(item.target, {
                                                        unit_id:
                                                            filters.unit_id,
                                                        month: filters.month,
                                                        year: filters.year,
                                                    })
                                                }
                                                className="w-full justify-center gap-2"
                                            >
                                                <Icon className="size-4" />
                                                Buka Formulir
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Input data, atur layout, &amp;
                                                cetak PDF
                                            </p>
                                        </>
                                    ) : (
                                        <>
                                            <Button
                                                disabled
                                                variant="outline"
                                                className="w-full cursor-not-allowed justify-center gap-2 opacity-75"
                                                title="Tombol sementara dinonaktifkan (formulir sedang disusun)"
                                            >
                                                <Icon className="size-4" />
                                                Buka Formulir
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground italic">
                                                * Tombol belum difungsikan
                                                (formulir sedang disusun)
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

K3FormulirIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir K3 & Keamanan', href: formulir.index() },
    ],
};
