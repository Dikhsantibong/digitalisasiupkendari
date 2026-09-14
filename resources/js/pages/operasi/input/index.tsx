import { Head, router } from '@inertiajs/react';
import { Fuel, Gauge, Plug, TimerReset, Zap } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import auxiliary from '@/routes/operasi/input/auxiliary';
import dailyReport from '@/routes/operasi/input/daily-report';
import feeder from '@/routes/operasi/input/feeder';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
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