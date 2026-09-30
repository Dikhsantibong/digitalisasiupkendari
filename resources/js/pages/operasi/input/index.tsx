import { Head, router } from '@inertiajs/react';
import { AlertOctagon, ClipboardCheck, ClipboardList, FileCheck, FileText, Package, ShieldAlert, Sparkles, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import checklistCommissioningMesin from '@/routes/operasi/input/checklist-commissioning-mesin';
import flmMonitoring from '@/routes/operasi/input/flm-monitoring';
import instruksiKerja from '@/routes/operasi/input/instruksi-kerja';
import kondisiAbnormal from '@/routes/operasi/input/kondisi-abnormal';
import materialPeralatan from '@/routes/operasi/input/material-peralatan';
import patrolCheckMesin from '@/routes/operasi/input/patrol-check-mesin';
import permitToWork from '@/routes/operasi/input/permit-to-work';
import program5s5r from '@/routes/operasi/input/program-5s5r';
import unsafeCondition from '@/routes/operasi/input/unsafe-condition';

type InputCard = {
    title: string;
    description: string;
    icon: LucideIcon;
    url: string;
    buttonLabel: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Kondisi Abnormal & Gangguan',
        description: 'Pencatatan dan pemantauan kejadian kondisi abnormal serta gangguan mesin pembangkit beserta durasi kejadian.',
        icon: AlertOctagon,
        url: kondisiAbnormal.index().url,
        buttonLabel: 'Buka Input Kondisi Abnormal',
    },
    {
        title: 'Instruksi Kerja (IK)',
        description: 'Buat IK operasi dari template (mis. start / stop mesin PLTD): alat, pelaksana, langkah pelaksanaan dan tanda tangan — bebas disesuaikan, cetak PDF format MKP.',
        icon: FileText,
        url: instruksiKerja.index().url,
        buttonLabel: 'Buka Instruksi Kerja',
    },
    {
        title: 'Material & Peralatan',
        description: 'Pencatatan inventaris, monitoring stok awal, barang masuk, barang keluar, safety stock, dan reorder point.',
        icon: Package,
        url: materialPeralatan.index().url,
        buttonLabel: 'Buka Input Material & Peralatan',
    },
    {
        title: 'Permit to Work (PTW)',
        description: 'Pencatatan dan pemantauan status izin kerja (Permit to Work) open dan close untuk pekerjaan pembangkit.',
        icon: FileCheck,
        url: permitToWork.index().url,
        buttonLabel: 'Buka Input Permit to Work',
    },
    {
        title: 'Monitoring FLM',
        description: 'Pencatatan temuan first line maintenance mesin & peralatan, tindakan awal (bersihkan, lumasi, kencangkan, perbaikan koneksi), kondisi akhir, dan status.',
        icon: Wrench,
        url: flmMonitoring.index().url,
        buttonLabel: 'Buka Input Monitoring FLM',
    },
    {
        title: 'Patrol Check Mesin',
        description: 'Pencatatan pemeriksaan harian mesin pembangkit (Lubricating, Fuel, Cooling, Air Intake/Exhaust, Electrical System) per shift.',
        icon: ClipboardCheck,
        url: patrolCheckMesin.index().url,
        buttonLabel: 'Buka Input Patrol Check Mesin',
    },
    {
        title: 'Checklist Commissioning Test Mesin',
        description: 'Pemeriksaan dan verifikasi status kesiapan peralatan (Persiapan & Paralel Generator) saat commissioning test mesin pembangkit.',
        icon: ClipboardList,
        url: checklistCommissioningMesin.index().url,
        buttonLabel: 'Buka Checklist Commissioning Mesin',
    },
    {
        title: 'Unsafe Action & Unsafe Condition',
        description: 'Pencatatan, pelaporan, dan evaluasi tindak lanjut temuan tindakan tidak aman (unsafe action) serta kondisi berbahaya (unsafe condition).',
        icon: ShieldAlert,
        url: unsafeCondition.index().url,
        buttonLabel: 'Buka Unsafe Action & Condition',
    },
    {
        title: 'Program 5S 5R Pengoperasian KIT',
        description: 'Pencatatan mingguan program Ringkas (Seiri), Rapi (Seiton), Resik (Seiso), Rawat (Seiketsu), Rajin (Shitsuke), progres, kondisi, dan foto eviden.',
        icon: Sparkles,
        url: program5s5r.index().url,
        buttonLabel: 'Buka Program 5S 5R',
    },
];

export default function OperasiInputIndex() {
    return (
        <>
            <Head title="Input Operasi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input Operasi"
                    description="Pilih jenis formulir data kegiatan operasional pembangkit untuk diinput dan dikelola."
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

OperasiInputIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
    ],
};