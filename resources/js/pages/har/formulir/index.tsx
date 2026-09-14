import { Head, router } from '@inertiajs/react';
import {
    Activity,
    BatteryCharging,
    Cog,
    Disc,
    Droplet,
    Flame,
    Fuel,
    Ruler,
    Sliders,
    Timer,
    Waves,
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
import formulir from '@/routes/har/formulir';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

type FormulirCard = {
    title: string;
    description: string;
    icon: typeof Wrench;
    target: string;
};

const FORMULIR_LIST: FormulirCard[] = [
    {
        title: 'Formulir Checklist Prelube Test',
        description: 'Checklist pemeriksaan sistem pelumasan awal (prelube pump, tekanan oli, dan kesiapan pelumasan mesin).',
        icon: Droplet,
        target: '/har/formulir/prelube-test',
    },
    {
        title: 'Formulir Checklist Hydrotest',
        description: 'Checklist pengujian tekanan hidrolik (hydrotest) pipa, bejana tekan, cooler, dan sistem pendingin.',
        icon: Waves,
        target: '/har/formulir/hydrotest',
    },
    {
        title: 'Formulir Checklist Timing Injection Pump',
        description: 'Checklist dan verifikasi sudut penyemprotan bahan bakar (timing injection) pompa injeksi mesin.',
        icon: Timer,
        target: '/har/formulir/timing-injection-pump',
    },
    {
        title: 'Formulir Pengukuran Defleksi Crankshaft',
        description: 'Pencatatan pengukuran kelurusan (alignment) dan defleksi poros engkol (crankshaft) tiap silinder mesin.',
        icon: Sliders,
        target: '/har/formulir/defleksi-crankshaft',
    },
    {
        title: 'Formulir Pemeriksaan Kondisi Kekencangan Baut Counter Weight',
        description: 'Pemeriksaan torsi kekencangan baut counter weight dan penguncian poros engkol.',
        icon: Wrench,
        target: '/har/formulir/baut-counter-weight',
    },
    {
        title: 'Formulir Pemeriksaan Axial Conrod & Baut Conrod',
        description: 'Pemeriksaan clearance axial connecting rod, kondisi bearing, dan torsi pengencangan baut conrod.',
        icon: Disc,
        target: '/har/formulir/axial-conrod',
    },
    {
        title: 'Formulir Pengukuran Backlash Gear Camshaft',
        description: 'Pengukuran kerenggangan (backlash) roda gigi timing gear dan camshaft mesin pembangkit.',
        icon: Cog,
        target: '/har/formulir/backlash-gear-camshaft',
    },
    {
        title: 'Formulir Pengukuran Clearance Valve',
        description: 'Pencatatan celah katup hisap (inlet valve) dan katup buang (exhaust valve) pada silinder head.',
        icon: Ruler,
        target: '/har/formulir/clearance-valve',
    },
    {
        title: 'Formulir Pengukuran Tekanan Pembakaran',
        description: 'Pencatatan tekanan kompresi (Pcomp) dan tekanan pembakaran maksimum (Pmax) tiap silinder mesin.',
        icon: Flame,
        target: '/har/formulir/tekanan-pembakaran',
    },
    {
        title: 'Formulir Pengukuran Tekanan Vibrasi',
        description: 'Pengukuran tingkat getaran (vibration level) pada bearing mesin, generator, turbocharger, dan auxiliary.',
        icon: Activity,
        target: '/har/formulir/tekanan-vibrasi',
    },
    {
        title: 'Formulir Pengukuran Kualitas Pelumas',
        description: 'Pencatatan hasil uji laboratorium parameter oli pelumas (viskositas, TBN, water content, kontaminasi).',
        icon: Fuel,
        target: '/har/formulir/kualitas-pelumas',
    },
    {
        title: 'Formulir Pengukuran Tegangan Baterai',
        description: 'Pemeriksaan tegangan sel, berat jenis elektrolit, dan kesiapan baterai starting dan kontrol DC.',
        icon: BatteryCharging,
        target: '/har/formulir/tegangan-baterai',
    },
];

export default function HarFormulirIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            formulir.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Formulir Pemeliharaan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir Pemeliharaan"
                    description="Pilih unit & periode, lalu kelola formulir teknis checklist dan pengukuran pemeliharaan mesin."
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
                    {FORMULIR_LIST.map((item) => {
                        const Icon = item.icon;
                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4 transition-all hover:border-primary/50"
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
                                            Sementara Disusun
                                        </Badge>
                                    </div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>

                                <div className="space-y-1.5 pt-2">
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
                                        * Tombol belum difungsikan (formulir sedang disusun)
                                    </p>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

HarFormulirIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: formulir.index() },
    ],
};
