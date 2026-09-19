import { Head, router } from '@inertiajs/react';
import {
    Activity,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Cpu,
    FileText,
    PhoneCall,
    ShieldCheck,
    Sparkles,
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
import jadwal from '@/routes/pdm/jadwal';
import type { IdName } from '@/types';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
};

type JadwalCard = {
    title: string;
    description: string;
    icon: typeof Activity;
    target: string;
};

const JADWAL_LIST: JadwalCard[] = [
    {
        title: 'Jadwal Kegiatan PdM & MATLEV',
        description:
            'Penjadwalan aktivitas dan program kerja predictive maintenance (PdM) dan Maturity Level (harian, mingguan, dan bulanan).',
        icon: Activity,
        target: '/pdm/jadwal/harian',
    },
    {
        title: 'Jadwal Piket On Call',
        description:
            'Penetapan personil PdM siaga (On Call) untuk penanganan pengukuran dan analisa kondisi mesin.',
        icon: PhoneCall,
        target: '/pdm/jadwal/piket-on-call',
    },
    {
        title: 'Jadwal Piket Patrol Check PdM KIT',
        description:
            'Jadwal piket patroli pemeriksaan pemantauan parameter predictive (HARMES & HARLIS) pembangkit.',
        icon: ShieldCheck,
        target: '/pdm/jadwal/patrol-check',
    },
    {
        title: 'Jadwal Program 5S 5R',
        description:
            'Jadwal pelaksanaan budaya kerja Ringkas, Rapi, Resik, Rawat, Rajin di area kerja PdM.',
        icon: Sparkles,
        target: '/pdm/jadwal/program-5s-5r',
    },
    {
        title: 'Jadwal Meeting PdM KIT',
        description:
            'Jadwal rapat koordinasi berkala tim PdM KIT, evaluasi hasil pengukuran, dan tindak lanjut anomali.',
        icon: CalendarClock,
        target: '/pdm/jadwal/meeting',
    },
    {
        title: 'Jadwal Pembuatan IK',
        description:
            'Penyusunan, review, standardisasi, dan pemutakhiran Instruksi Kerja (IK) predictive maintenance.',
        icon: FileText,
        target: '/pdm/jadwal/pembuatan-ik',
    },
    {
        title: 'Jadwal Pemeriksaan Instalasi Blackstart',
        description:
            'Jadwal pengujian berkala dan inspeksi kesiapan teknis instalasi sistem darurat Blackstart Diesel.',
        icon: ZapOff,
        target: '/pdm/jadwal/blackstart',
    },
    {
        title: 'Jadwal Commissioning Test Mesin',
        description:
            'Jadwal dan checklist pengujian kesiapan, persiapan, dan paralel generator commissioning test mesin.',
        icon: CheckCircle2,
        target: '/pdm/jadwal/commissioning-test-mesin',
    },
    {
        title: 'Jadwal Commissioning Test Peralatan Non Mesin',
        description:
            'Jadwal pengujian commissioning peralatan non mesin: proteksi, transformator, motor bantu, dan panel.',
        icon: Cpu,
        target: '/pdm/jadwal/commissioning-test-non-mesin',
    },
    {
        title: 'Jadwal Rencana Operasi (ROT, ROB, ROM)',
        description:
            'Penyusunan Rencana Operasi Tahunan (ROT), Bulanan (ROB), dan Mingguan (ROM) unit pembangkit.',
        icon: ClipboardList,
        target: '/pdm/jadwal/rencana-operasi',
    },
];

export default function PdmJadwalIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            jadwal.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Jadwal PdM & Maturity Level" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal PdM & Maturity Level"
                    description="Pilih unit & periode, lalu kelola jadwal kegiatan predictive maintenance (PdM) pembangkit."
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
                        const isAvailable =
                            item.target === '/pdm/jadwal/harian' ||
                            item.target === '/pdm/jadwal/patrol-check' ||
                            item.target === '/pdm/jadwal/program-5s-5r' ||
                            item.target === '/pdm/jadwal/meeting';

                        return (
                            <div
                                key={item.title}
                                className={`flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4 transition-all shadow-xs ${
                                    isAvailable
                                        ? 'hover:border-primary/50'
                                        : 'hover:border-primary/30'
                                }`}
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        {isAvailable ? (
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

                                <div className="space-y-1.5 pt-2 border-t border-border/60">
                                    {isAvailable ? (
                                        <>
                                            <Button
                                                onClick={() =>
                                                    router.get(item.target, {
                                                        unit_id: filters.unit_id,
                                                        month: filters.month,
                                                        year: filters.year,
                                                    })
                                                }
                                                className="w-full justify-center gap-2 font-semibold"
                                            >
                                                <Icon className="size-4" />
                                                Input Jadwal
                                            </Button>
                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Kelola matriks dan checklist jadwal kegiatan
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
                                                * Tombol belum difungsikan (halaman sedang disusun)
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

PdmJadwalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal PdM & Maturity Level', href: jadwal.index() },
    ],
};
