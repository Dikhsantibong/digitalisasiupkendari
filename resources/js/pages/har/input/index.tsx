import { Head, router } from '@inertiajs/react';
import {
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    FileWarning,
    Image,
    NotebookPen,
    ShieldAlert,
    Wallet,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import harActivity from '@/routes/har/input/activity';
import harAttachment from '@/routes/har/input/attachment';
import harCost from '@/routes/har/input/cost';
import harLaporanGangguan from '@/routes/har/input/laporan-gangguan';
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
        title: 'Laporan Gangguan',
        description:
            'Pencatatan laporan kerusakan / gangguan unit pembangkit (Form LH-05): kronologi kejadian, analisa penyebab, dampak, dan tindak lanjut perbaikan.',
        icon: FileWarning,
        url: harLaporanGangguan.index().url,
        buttonLabel: 'Buka Input Laporan Gangguan',
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
