import { Head, router } from '@inertiajs/react';
import {
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Flame,
    Gauge,
    Image,
    ScrollText,
    ShieldCheck,
    Siren,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import k3Accident from '@/routes/k3/input/accident';
import k3AparCheck from '@/routes/k3/input/apar-check';
import k3Attachment from '@/routes/k3/input/attachment';
import k3Certificate from '@/routes/k3/input/certificate';
import k3Emergency from '@/routes/k3/input/emergency';
import k3Inspection from '@/routes/k3/input/inspection';
import k3Patrol from '@/routes/k3/input/patrol';
import k3TimeFrame from '@/routes/k3/input/time-frame';
import k3Monitoring from '@/routes/k3/monitoring';

type InputCard = {
    title: string;
    description: string;
    icon: typeof ClipboardList;
    url: string;
    buttonLabel: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Time Frame',
        description: 'Pencatatan target rencana dan realisasi program kerja K3 pada periode berjalan.',
        icon: CalendarRange,
        url: k3TimeFrame.index().url,
        buttonLabel: 'Buka Input Time Frame',
    },
    {
        title: 'Laporan Kecelakaan',
        description: 'Pencatatan laporan insiden kecelakaan kerja, penyakit akibat kerja (PAK/PAHK), dan investigasi.',
        icon: ClipboardCheck,
        url: k3Accident.index().url,
        buttonLabel: 'Buka Input Kecelakaan',
    },
    {
        title: 'Inspeksi Checklist',
        description: 'Pelaksanaan dan pencatatan checklist inspeksi keselamatan kerja berkala di seluruh area.',
        icon: ClipboardList,
        url: k3Inspection.index().url,
        buttonLabel: 'Buka Input Checklist',
    },
    {
        title: 'Inspeksi APAR/APAB',
        description: 'Pencatatan kondisi fisik, segel, tekanan tabung, dan masa berlaku APAR/APAB unit pembangkit.',
        icon: Flame,
        url: k3AparCheck.index().url,
        buttonLabel: 'Buka Input APAR/APAB',
    },
    {
        title: 'Fasilitas Darurat',
        description: 'Pemeriksaan kesiapan kotak P3K, shower darurat, eye wash, alarm evakuasi, dan assembly point.',
        icon: Siren,
        url: k3Emergency.index().url,
        buttonLabel: 'Buka Input Fasilitas Darurat',
    },
    {
        title: 'Patroli Keamanan',
        description: 'Pencatatan ronda keamanan terjadwal dan verifikasi log titik scan RFID pos pengamanan.',
        icon: ShieldCheck,
        url: k3Patrol.index().url,
        buttonLabel: 'Buka Input Patroli',
    },
    {
        title: 'Sertifikasi Peralatan',
        description: 'Pencatatan riwayat uji kelaikan, izin operasi, dan masa berlaku sertifikat peralatan penunjang.',
        icon: ScrollText,
        url: k3Certificate.index().url,
        buttonLabel: 'Buka Input Sertifikasi',
    },
    {
        title: 'Lampiran K3',
        description: 'Unggah dokumentasi foto kegiatan K3, sosialisasi/safety briefing, dan temuan lingkungan.',
        icon: Image,
        url: k3Attachment.index().url,
        buttonLabel: 'Buka Input Lampiran K3',
    },
    {
        title: 'Monitoring K3',
        description: 'Dashboard pemantauan masa berlaku sertifikasi peralatan dan tabung pemadam api unit.',
        icon: Gauge,
        url: k3Monitoring.index().url,
        buttonLabel: 'Buka Monitoring K3',
    },
];

export default function K3InputIndex() {
    return (
        <>
            <Head title="Input K3 & Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input K3 & Keamanan"
                    description="Pilih formulir kegiatan keselamatan kerja, lindungan lingkungan, dan keamanan untuk diinput & dikelola."
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

K3InputIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
    ],
};
