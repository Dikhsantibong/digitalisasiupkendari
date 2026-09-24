import { Head, router } from '@inertiajs/react';
import {
    Activity,
    BatteryCharging,
    Boxes,
    CalendarClock,
    FileText,
    PhoneCall,
    ShieldCheck,
    Sliders,
    Sparkles,
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
import jadwal from '@/routes/har/jadwal';
import lembar from '@/routes/har/jadwal/lembar';
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
    /** Lembar matriks HAR (App\Support\HarLembar) served by /har/jadwal/lembar/{key}. */
    lembarKey?: string;
};

const JADWAL_LIST: JadwalCard[] = [
    {
        title: 'Jadwal Kegiatan Harian Pemeliharaan',
        description: 'Penjadwalan aktivitas dan program kerja harian pemeliharaan mesin pembangkit.',
        icon: Activity,
        target: '/har/jadwal/harian',
    },
    {
        title: 'Jadwal Kegiatan Pemeliharaan P0 - P5',
        description: 'Perencanaan jadwal pemeliharaan berkala periodik P0 hingga P5 sesuai jam operasi (EOH).',
        icon: Wrench,
        target: '/har/jadwal/p0-p5',
    },
    {
        title: 'Jadwal Kegiatan Piket Pemeliharaan (ON CALL)',
        description: 'Penetapan regu dan personil siaga darurat (On Call) untuk penanganan gangguan unit 24 jam.',
        icon: PhoneCall,
        target: '/har/jadwal/piket-on-call',
    },
    {
        title: 'Jadwal Piket Patrol Check Harian Pemeliharaan',
        description: 'Jadwal inspeksi patroli harian pemantauan vibrasi, temperatur, kebocoran, dan kondisi mesin.',
        icon: ShieldCheck,
        target: '/har/jadwal/patrol-check',
    },
    {
        title: 'Jadwal Meeting Pemeliharaan',
        description: 'Jadwal rapat koordinasi berkala, evaluasi pekerjaan pemeliharaan, dan tindak lanjut kendala teknis.',
        icon: CalendarClock,
        target: '/har/jadwal/meeting-pemeliharaan',
    },
    {
        title: 'Jadwal Pembuatan IK Pemeliharaan',
        description: 'Penyusunan, review, standardisasi, dan pemutakhiran Instruksi Kerja (IK) teknis pemeliharaan.',
        icon: FileText,
        target: '/har/jadwal/pembuatan-ik',
    },
    {
        title: 'Jadwal Individual Test Peralatan Non Mesin dan Instalasi',
        description: 'Pengujian mandiri peralatan proteksi, transformator, motor bantu listrik, dan panel instalasi.',
        icon: Sliders,
        target: '/har/jadwal/individual-test',
    },
    {
        title: 'Jadwal Inventarisasi Tools & Material',
        description: 'Inventarisasi tools & material per minggu (1 baik, 2 tidak baik, 3 rusak) dengan merek, penerimaan, satuan, jumlah, dan keterangan.',
        icon: Boxes,
        target: lembar.index('inventarisasi-tools').url,
        lembarKey: 'inventarisasi-tools',
    },
    {
        title: 'Jadwal Pemeriksaan Instalasi Blackstart',
        description: 'Rencana & realisasi pemeriksaan instalasi blackstart per minggu Januari–Desember, PIC pembuat, jumlah, dan kinerja bulanan.',
        icon: BatteryCharging,
        target: lembar.index('pemeriksaan-blackstart').url,
        lembarKey: 'pemeriksaan-blackstart',
    },
];

export default function HarJadwalIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            jadwal.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Jadwal Pemeliharaan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Pemeliharaan"
                    description="Pilih unit & periode, lalu kelola jadwal kegiatan pemeliharaan pembangkit."
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
                        const isAvailable =
                            item.lembarKey !== undefined ||
                            item.target === '/har/jadwal/harian' ||
                            item.target === '/har/jadwal/p0-p5' ||
                            item.target === '/har/jadwal/patrol-check' ||
                            item.target === '/har/jadwal/meeting-pemeliharaan' ||
                            item.target === '/har/jadwal/piket-on-call' ||
                            item.target === '/har/jadwal/pembuatan-ik';
                        const buttonLabel =
                            item.target === '/har/jadwal/harian'
                                ? 'Buka Jadwal Harian'
                                : item.target === '/har/jadwal/p0-p5'
                                  ? 'Buka Jadwal P0 - P5'
                                  : item.target === '/har/jadwal/patrol-check'
                                    ? 'Buka Jadwal Patrol Check'
                                    : item.target === '/har/jadwal/meeting-pemeliharaan'
                                      ? 'Buka Jadwal Meeting'
                                      : item.target === '/har/jadwal/piket-on-call'
                                        ? 'Buka Jadwal Piket On Call'
                                        : item.target === '/har/jadwal/pembuatan-ik'
                                          ? 'Buka Jadwal Pembuatan IK'
                                          : item.lembarKey !== undefined
                                            ? 'Buka Jadwal'
                                            : item.title;

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
                                            {buttonLabel}
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

HarJadwalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Pemeliharaan', href: jadwal.index() },
    ],
};
