import { Head, router } from '@inertiajs/react';
import {
    Boxes,
    CalendarCheck,
    Droplets,
    FileCheck2,
    ShieldCheck,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
        description: 'Pemeriksaan kelengkapan atribut K3, ketersediaan APD, peralatan darurat, kelengkapan administrasi, dan kondisi sarana prasarana keselamatan.',
        icon: ShieldCheck,
        target: '/k3/formulir/sarana-prasarana',
        active: false,
    },
    {
        title: 'Form Kontrol K3 Mingguan',
        description: 'Monitoring dan pengawasan kepatuhan K3 mingguan terhadap implementasi safety briefing, unsafe action/condition, dan kebersihan area kerja.',
        icon: CalendarCheck,
        target: '/k3/formulir/kontrol-mingguan',
        active: false,
    },
    {
        title: 'Formulir Metode Pengujian Peralatan',
        description: 'Pencatatan standar operasional dan metode pengujian peralatan K3 & keselamatan kerja sebelum dioperasikan di unit pembangkit.',
        icon: FileCheck2,
        target: '/k3/formulir/metode-pengujian',
        active: false,
    },
    {
        title: 'Formulir Pemeliharaan TPS LB3',
        description: 'Pemeriksaan dan pencatatan kondisi Tempat Penimpanan Sementara Limbah Bahan Berbahaya dan Beracun (TPS LB3), penataan drum, dan simbol limbah.',
        icon: Boxes,
        target: '/k3/formulir/pemeliharaan-tps-lb3',
        active: false,
    },
    {
        title: 'Formulir Pemeliharaan Oil Trap',
        description: 'Inspeksi berkala dan pemeliharaan bak penangkap ceceran minyak (oil trap/separator) untuk mencegah pencemaran lingkungan air limbah.',
        icon: Droplets,
        target: '/k3/formulir/pemeliharaan-oil-trap',
        active: false,
    },
];

export default function K3FormulirIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            formulir.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

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
                                        <div className={`flex size-9 items-center justify-center rounded-lg ${item.active ? 'bg-primary text-primary-foreground' : 'bg-primary/10 text-primary'}`}>
                                            <Icon className="size-5" />
                                        </div>
                                        {item.active ? (
                                            <Badge variant="outline" className="border-emerald-600/30 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
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
                                    {item.active ? (
                                        <>
                                            <Button
                                                onClick={() => router.get(item.target, { unit_id: filters.unit_id })}
                                                className="w-full justify-center gap-2"
                                            >
                                                <Icon className="size-4" />
                                                Buka Formulir
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Input data, atur layout, &amp; cetak PDF
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
                                                * Tombol belum difungsikan (formulir sedang disusun)
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
