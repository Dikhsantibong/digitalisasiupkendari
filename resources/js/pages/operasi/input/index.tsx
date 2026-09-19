import { Head, router } from '@inertiajs/react';
import { AlertOctagon, Droplet, FileCheck, Fuel, Gauge, Package, Plug, TimerReset, Wrench, Zap } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import auxiliary from '@/routes/operasi/input/auxiliary';
import dailyReport from '@/routes/operasi/input/daily-report';
import feeder from '@/routes/operasi/input/feeder';
import flmMonitoring from '@/routes/operasi/input/flm-monitoring';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
import kondisiAbnormal from '@/routes/operasi/input/kondisi-abnormal';
import materialPeralatan from '@/routes/operasi/input/material-peralatan';
import permitToWork from '@/routes/operasi/input/permit-to-work';
import resourcePembangkit from '@/routes/operasi/input/resource-pembangkit';
import starStop from '@/routes/operasi/input/star-stop';

type InputCard = {
    title: string;
    description: string;
    icon: typeof Gauge;
    url: string;
    buttonLabel: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Input Harian',
        description: 'Pencatatan data produksi kWh harian, pemakaian sendiri, beban puncak, dan konsumsi BBM.',
        icon: Gauge,
        url: dailyReport.index().url,
        buttonLabel: 'Buka Input Harian',
    },
    {
        title: 'Star-Stop Mesin',
        description: 'Pencatatan riwayat start, stop, gangguan, dan status operasional mesin pembangkit.',
        icon: TimerReset,
        url: starStop.index().url,
        buttonLabel: 'Buka Input Star-Stop',
    },
    {
        title: 'Feeder',
        description: 'Pencatatan pembacaan beban, tegangan, arus, dan penyaluran energi pada masing-masing feeder.',
        icon: Zap,
        url: feeder.index().url,
        buttonLabel: 'Buka Input Feeder',
    },
    {
        title: 'Pasokan Cadangan',
        description: 'Pencatatan kWh pasokan cadangan (auxiliary power supply) dan pemakaian listrik internal.',
        icon: Plug,
        url: auxiliary.index().url,
        buttonLabel: 'Buka Input Pasokan Cadangan',
    },
    {
        title: 'Penerimaan BBM',
        description: 'Pencatatan transaksi penerimaan bahan bakar (BBM/Pelumas), sounding tangki, dan volume supply.',
        icon: Fuel,
        url: fuelReceipt.index().url,
        buttonLabel: 'Buka Input Penerimaan BBM',
    },
    {
        title: 'Kondisi Abnormal & Gangguan',
        description: 'Pencatatan dan pemantauan kejadian kondisi abnormal serta gangguan mesin pembangkit beserta durasi kejadian.',
        icon: AlertOctagon,
        url: kondisiAbnormal.index().url,
        buttonLabel: 'Buka Input Kondisi Abnormal',
    },
    {
        title: 'Resource Pembangkit',
        description: 'Pencatatan dan pemantauan harian stok awal, pemakaian, penerimaan, dan stok akhir BBM pembangkit.',
        icon: Droplet,
        url: resourcePembangkit.index().url,
        buttonLabel: 'Buka Input Resource Pembangkit',
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