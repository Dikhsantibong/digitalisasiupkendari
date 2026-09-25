import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    FileWarning,
    Gauge,
    Image,
    NotebookPen,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Wallet,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import harAbnormalGangguan from '@/routes/har/input/abnormal-gangguan';
import harActivity from '@/routes/har/input/activity';
import harAttachment from '@/routes/har/input/attachment';
import harCost from '@/routes/har/input/cost';
import harLaporanGangguan from '@/routes/har/input/laporan-gangguan';
import harInputLembar from '@/routes/har/input/lembar';
import harPatrolCheckParameter from '@/routes/har/input/patrol-check-parameter';
import harProgram5s5r from '@/routes/har/input/program-5s5r';
import harSchedule from '@/routes/har/input/schedule';
import harServiceRequest from '@/routes/har/input/service-request';
import harUnsafeCondition from '@/routes/har/input/unsafe-condition';
import harWorkOrder from '@/routes/har/input/work-order';

type InputCard = {
    title: string;
    description: string;
    icon: typeof ClipboardList;
    url: string;
    buttonLabel: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Work Order',
        description:
            'Pencatatan, perencanaan, dan pelacakan status Work Order pemeliharaan unit pembangkit.',
        icon: ClipboardList,
        url: harWorkOrder.index().url,
        buttonLabel: 'Buka Input Work Order',
    },
    {
        title: 'Service Request',
        description:
            'Pencatatan permintaan perbaikan dan pemeliharaan mesin atau peralatan pembangkit.',
        icon: ClipboardCheck,
        url: harServiceRequest.index().url,
        buttonLabel: 'Buka Input Service Request',
    },
    {
        title: 'Log Kegiatan',
        description:
            'Pencatatan log aktivitas dan riwayat pelaksanaan pekerjaan pemeliharaan berkala maupun korektif.',
        icon: NotebookPen,
        url: harActivity.index().url,
        buttonLabel: 'Buka Input Log Kegiatan',
    },
    {
        title: 'Biaya',
        description:
            'Pencatatan realisasi biaya pemeliharaan, pembelian spare part, dan jasa pemeliharaan.',
        icon: Wallet,
        url: harCost.index().url,
        buttonLabel: 'Buka Input Biaya',
    },
    {
        title: 'Rencana vs Realisasi',
        description:
            'Pemantauan dan evaluasi perbandingan antara target rencana pemeliharaan dengan realisasinya.',
        icon: CalendarRange,
        url: harSchedule.index().url,
        buttonLabel: 'Buka Input Rencana vs Realisasi',
    },
    {
        title: 'Lampiran Foto',
        description:
            'Unggah dokumentasi foto sebelum (before), sedang berlangsung (in progress), dan sesudah (after) pemeliharaan.',
        icon: Image,
        url: harAttachment.index().url,
        buttonLabel: 'Buka Input Lampiran Foto',
    },
    {
        title: 'Unsafe Action & Unsafe Condition',
        description:
            'Pencatatan, pelaporan, dan evaluasi tindak lanjut temuan tindakan tidak aman (unsafe action) serta kondisi berbahaya (unsafe condition).',
        icon: ShieldAlert,
        url: harUnsafeCondition.index().url,
        buttonLabel: 'Buka Input Unsafe Action & Condition',
    },
    {
        title: 'Rekap Laporan Gangguan',
        description:
            'Rekap gangguan pembangkit per bulan: tindakan, material rusak, durasi pemeliharaan, komponen & sistem terganggu, kWh loss, tindak lanjut, pencegahan, dan status Open/Close.',
        icon: FileWarning,
        url: harLaporanGangguan.index().url,
        buttonLabel: 'Buka Rekap Laporan Gangguan',
    },
    {
        title: 'Laporan Kondisi Abnormal dan Gangguan Pembangkit',
        description:
            'Kondisi abnormal & gangguan per kejadian: uraian, jenis (mesin/electrical/sipil), tanggal, status abnormal/gangguan dengan durasi (jam), dan total.',
        icon: AlertTriangle,
        url: harAbnormalGangguan.index().url,
        buttonLabel: 'Buka Laporan Abnormal & Gangguan',
    },
    {
        title: 'Jadwal Program 5S 5R Pemeliharaan',
        description:
            'Pelaksanaan Ringkas, Rapi, Resik, Rawat, Rajin per minggu: kondisi awal/akhir, tindakan, progres, jumlah, keterangan, dan foto eviden.',
        icon: Sparkles,
        url: harProgram5s5r.index().url,
        buttonLabel: 'Buka Input Program 5S 5R',
    },
    {
        title: 'Laporan Patrol Check Pemeliharaan',
        description:
            'Patrol check harian per mesin: peralatan sistem pelumasan, bahan bakar, pendingin, udara & gas buang, dan kelistrikan — N normal / T tidak normal per tanggal.',
        icon: ShieldCheck,
        url: harInputLembar.index('patrol-check-pemeliharaan').url,
        buttonLabel: 'Buka Input Patrol Check',
    },
    {
        title: 'Patrol Check Parameter Mesin',
        description:
            'Pembacaan harian per mesin: PIC, jam, load, temperatur & tekanan engine, temperatur/arus/tegangan generator, cos phi, kVAR, trafo, dan tegangan baterai.',
        icon: Gauge,
        url: harPatrolCheckParameter.index().url,
        buttonLabel: 'Buka Input Parameter Mesin',
    },
];

export default function HarInputIndex() {
    return (
        <>
            <Head title="Input Pemeliharaan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input Pemeliharaan"
                    description="Pilih jenis formulir data kegiatan pemeliharaan pembangkit untuk diinput dan dikelola."
                />

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {INPUT_MENUS.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-5 transition-all hover:border-primary/50"
                            >
                                <div>
                                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>

                                <div className="pt-2">
                                    <Button
                                        className="w-full justify-center gap-2"
                                        onClick={() => router.get(item.url)}
                                    >
                                        <Icon className="size-4" />
                                        {item.buttonLabel}
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

HarInputIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
    ],
};
