import { Head, router } from '@inertiajs/react';
import { AlertOctagon, BookOpen, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    icon: LucideIcon;
    target: string;
    active?: boolean;
};

const FORMULIR_LIST: FormulirCard[] = [
    {
        title: 'Formulir Laporan Gangguan (LH-05)',
        description: 'Laporan kerusakan unit pembangkit: data mesin, tanggal & peralatan rusak, gejala, urutan kejadian, analisa penyebab, akibat, tindak lanjut, dan eviden.',
        icon: AlertOctagon,
        target: formulir.laporanGangguan.index().url,
        active: true,
    },
    {
        title: 'Formulir Daily Meeting',
        description: 'Buat meeting (acara, hari/tanggal, waktu, tempat), tampilkan QR code — peserta scan & isi absensi dengan tanda tangan di HP. Export PDF daftar hadir + foto eviden.',
        icon: Users,
        target: formulir.dailyMeeting.index().url,
        active: true,
    },
    {
        title: 'Form Logbook Mutasi Harian',
        description: 'Logbook harian tim pemeliharaan: absensi, kesiapan APD, job harian rutin & non rutin, serta kondisi K3 (unsafe action & condition).',
        icon: BookOpen,
        target: formulir.logbookMutasi.index().url,
        active: true,
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

HarFormulirIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: formulir.index() },
    ],
};
