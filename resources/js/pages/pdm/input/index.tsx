import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    CalendarCheck2,
    ClipboardCheck,
    Droplets,
    FileCheck2,
    FlaskConical,
    Gauge,
    HardHat,
    NotebookPen,
    ShieldCheck,
    Sparkles,
    Wrench,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import pdmForms from '@/routes/pdm/input/forms';
import kesiapanApd from '@/routes/pdm/input/kesiapan-apd';
import permitToWork from '@/routes/pdm/input/permit-to-work';
import realisasiPrediktif from '@/routes/pdm/input/realisasi-prediktif';
import sampleMonitoring from '@/routes/pdm/input/sample-monitoring';

type InputCard = {
    title: string;
    description: string;
    category: string;
    icon: typeof HardHat;
    /** The form's page; cards without one are still being built. */
    href?: string;
};

const INPUT_MENUS: InputCard[] = [
    {
        title: 'Kesiapan APD Bagian PdM Pembangkit',
        description:
            'Inspeksi kelayakan Alat Pelindung Diri (APD), peralatan kerja, kesesuaian SOP/IK, kotak P3K, dan ergonomi kerja tim PdM.',
        category: 'K3 & Kesiapan',
        icon: HardHat,
        href: kesiapanApd.index().url,
    },
    {
        title: 'Form Monitoring Pemeriksaan & Pengiriman Sample PdM',
        description:
            'Monitoring jadwal pengiriman sampel oli/trafo, penerimaan hasil uji laboratorium, rekap monitoring, dan tindak lanjut temuan.',
        category: 'Sampling & Lab',
        icon: FlaskConical,
        href: sampleMonitoring.index().url,
    },
    {
        title: 'Laporan Permit to Work (PTW) Pembangkit',
        description:
            'Pencatatan, pengawasan izin kerja keselamatan, dan tracking status Open/Close dokumen PTW tim PdM.',
        category: 'K3 & Operasi',
        icon: FileCheck2,
        href: permitToWork.index().url,
    },
    {
        title: 'Laporan Inspeksi Checklist 5S5R PdM',
        description:
            'Formulir penilaian berkala budaya kerja Ringkas, Rapi, Resik, Rawat, Rajin, evaluasi target & realisasi, serta bukti eviden temuan.',
        category: 'Budaya Kerja 5S5R',
        icon: Sparkles,
        href: pdmForms.index('checklist-5s5r').url,
    },
    {
        title: 'Laporan Pengukuran Kualitas Air Pendingin',
        description:
            'Pencatatan parameter kualitas air pendingin mesin (pH, hardness, conductivity, temperatur, CaCO3, dan test strips).',
        category: 'Kimia & Pendingin',
        icon: Droplets,
        href: pdmForms.index('air-pendingin').url,
    },
    {
        title: 'Laporan Pengukuran Kualitas Pelumas',
        description:
            'Pencatatan dan analisis uji laboratorium pelumas (TBN, water content, viskositas, aditif, nitrasi, soot, CBA, dan rekomendasi).',
        category: 'Pelumas & Oli',
        icon: Gauge,
        href: pdmForms.index('pelumas').url,
    },
    {
        title: 'Laporan Pengukuran Vibrasi Mesin & Generator',
        description:
            'Pengukuran getaran bearing mesin & generator arah vertikal dan horizontal (titik A1-C2, D1-F2, G1-G3) serta evaluasi batas alarm.',
        category: 'Vibrasi & Kondisi',
        icon: Activity,
        href: pdmForms.index('vibrasi').url,
    },
    {
        title: 'Form Kontrol Material, Peralatan & Tools PdM',
        description:
            'Kontrol inventaris material pelumas, peralatan komunikasi/senter/kunci panel, kelayakan tool set, dan serah terima shift.',
        category: 'Material & Tools',
        icon: Wrench,
        href: pdmForms.index('kontrol-material').url,
    },
    {
        title: 'Realisasi Pemeliharaan Prediktif Bulanan',
        description:
            'Rencana dan realisasi harian kegiatan predictive (vibrasi, sistem DC, dll.) per mesin, durasi, target, realisasi, dan kinerja bulanan.',
        category: 'Realisasi PdM',
        icon: CalendarCheck2,
        href: realisasiPrediktif.index().url,
    },
    {
        title: 'Patrol Check Predictive Maintenance (PdM)',
        description:
            'Pencatatan hasil patroli pengukuran parameter predictive (HARMES & HARLIS) mesin pembangkit secara berkala.',
        category: 'Patrol Check',
        icon: ShieldCheck,
    },
    {
        title: 'Laporan Checklist Patrol Check PdM',
        description:
            'Rekapitulasi checklist pelaksanaan patrol check PdM sebagai bukti kelengkapan item pemeriksaan per unit pembangkit.',
        category: 'Pelaporan PdM',
        icon: ClipboardCheck,
    },
    {
        title: 'Log Sheet Predictive Maintenance',
        description:
            'Pencatatan log sheet harian aktivitas pemeliharaan prediktif beserta catatan analisa dan tindakan teknis di lapangan.',
        category: 'Log Sheet',
        icon: NotebookPen,
    },
];

export default function PdmInputIndex() {
    return (
        <>
            <Head title="Input PdM & Maturity Level" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Input PdM & Maturity Level"
                    description="Pilih jenis formulir data kegiatan predictive maintenance (PdM) pembangkit untuk diinput dan dikelola."
                />

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {INPUT_MENUS.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.title}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4.5 transition-all hover:border-primary/40 shadow-xs"
                            >
                                <div>
                                    <div className="mb-2.5 flex items-center justify-between gap-2">
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-4.5" />
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                {item.category}
                                            </span>
                                            {item.href ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-600/30 bg-emerald-50 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                                >
                                                    Tersedia
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px] font-normal text-muted-foreground"
                                                >
                                                    Segera Hadir
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <h2 className="text-sm font-semibold text-foreground leading-snug">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1.5 text-xs text-muted-foreground leading-relaxed">
                                        {item.description}
                                    </p>
                                </div>

                                <div className="space-y-1.5 pt-2 border-t border-border/60">
                                    {item.href ? (
                                        <>
                                            <Button asChild className="w-full justify-center gap-2 text-xs">
                                                <Link href={item.href}>
                                                    <Icon className="size-3.5" />
                                                    Input Formulir
                                                </Link>
                                            </Button>
                                            <p className="text-center text-[10.5px] text-muted-foreground">
                                                Input data, simpan, cetak PDF &amp; unduh Excel
                                            </p>
                                        </>
                                    ) : (
                                        <>
                                            <Button
                                                disabled
                                                variant="outline"
                                                className="w-full cursor-not-allowed justify-center gap-2 text-xs opacity-75"
                                                title="Tombol sementara dinonaktifkan (halaman formulir sedang disusun)"
                                            >
                                                <Icon className="size-3.5" />
                                                Input Formulir
                                            </Button>
                                            <p className="text-center text-[10.5px] text-muted-foreground italic">
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

PdmInputIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
    ],
};
