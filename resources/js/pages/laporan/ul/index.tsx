import { Head, Link } from '@inertiajs/react';
import {
    Download,
    FileBarChart,
    HardHat,
    LoaderCircle,
    Presentation,
    Wallet,
    Warehouse,
} from 'lucide-react';
import { useState } from 'react';
import { UlFilters } from '@/components/laporan/ul-filters';
import type { UlFiltersValue } from '@/components/laporan/ul-filters';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { downloadUlPpt } from '@/lib/ul-ppt';
import type { UlReportData } from '@/lib/ul-report';
import { dashboard } from '@/routes';
import keuanganDocument from '@/routes/keuangan/document';
import laporanUl from '@/routes/laporan-ul';
import type { IdName, Tone } from '@/types';

type Props = {
    ul: IdName;
    units: IdName[];
    filters: UlFiltersValue;
    options: { uls: IdName[]; years: number[] };
    reports: {
        kind: string;
        title: string;
        parts: string[];
        /** Approval status of the month's document (Draft → TL → Manager UL → Final). */
        status: string;
        status_tone: Tone;
    }[];
};

const ICONS: Record<string, typeof FileBarChart> = {
    pengusahaan: FileBarChart,
    logistik: Warehouse,
    k3l: HardHat,
};

const DESCRIPTIONS: Record<string, string> = {
    pengusahaan:
        'Akumulasi seluruh unit di bawah UL: Laporan Kinerja (data input Operasi & Laporan Operasi TL), Laporan Pemeliharaan (HAR) dan Laporan K3L — KPI, arah data, grafik 3D dan perbandingan per unit.',
    logistik:
        'Laporan Logistik & Gudang dari seluruh unit di bawah UL: persediaan, material, BBM & pelumas, unsafe condition — akumulasi dan per unit.',
    k3l: 'Laporan K3 & Lingkungan dari seluruh unit di bawah UL: kecelakaan, APAR, inspeksi, patrol dan kondisi lingkungan — akumulasi dan per unit.',
};

/**
 * Laporan UL (Admin UL & Super Admin): the service unit's monthly reports
 * built automatically from every unit under it, the Administrasi Keuangan
 * placeholder (coming later) and the PPT rutin bulanan.
 */
export default function LaporanUlIndex({
    ul,
    units,
    filters,
    options,
    reports,
}: Props) {
    const { can } = usePermissions();
    const keuanganQuery = {
        ul_id: ul.id,
        month: filters.month,
        year: filters.year,
    };
    const [makingPpt, setMakingPpt] = useState(false);
    const [pptError, setPptError] = useState<string | null>(null);
    const query = { query: filters };

    const makePpt = async () => {
        setMakingPpt(true);
        setPptError(null);

        try {
            const response = await fetch(laporanUl.pptData(query).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = (await response.json()) as {
                reports: UlReportData[];
            };
            await downloadUlPpt(
                data.reports,
                `Laporan_Bulanan_${ul.name}_${filters.month}_${filters.year}.pptx`.replace(
                    /\s+/g,
                    '_',
                ),
            );
        } catch {
            setPptError('PPT gagal dibuat. Coba lagi beberapa saat.');
        } finally {
            setMakingPpt(false);
        }
    };

    return (
        <>
            <Head title={`Laporan UL — ${ul.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Laporan UL — ${ul.name}`}
                    description={`Laporan bulanan Unit Layanan, disusun otomatis dari ${units.length} unit pembangkit di bawahnya: ${units.map((u) => u.name).join(', ') || '—'}.`}
                />

                <UlFilters
                    url={laporanUl.index().url}
                    filters={filters}
                    options={options}
                />

                <div className="grid gap-4 md:grid-cols-2">
                    {reports.map((report) => {
                        const Icon = ICONS[report.kind] ?? FileBarChart;

                        return (
                            <div
                                key={report.kind}
                                className="flex flex-col justify-between gap-4 rounded-md border border-l-4 border-border border-l-primary bg-card p-5 shadow-xs"
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-base font-semibold text-foreground">
                                            {report.title}
                                        </h2>
                                        <StatusBadge tone={report.status_tone}>
                                            {report.status}
                                        </StatusBadge>
                                    </div>
                                    <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                                        {DESCRIPTIONS[report.kind]}
                                    </p>
                                    {report.parts.length > 1 && (
                                        <p className="mt-1.5 text-[12px] text-muted-foreground">
                                            Isi: {report.parts.join(' · ')}
                                        </p>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild>
                                        <Link
                                            href={
                                                laporanUl.show(
                                                    report.kind,
                                                    query,
                                                ).url
                                            }
                                        >
                                            <Icon className="size-4" />
                                            Buka Dokumen
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a
                                            href={
                                                laporanUl.pdf(report.kind, {
                                                    query: {
                                                        ...filters,
                                                        download: 1,
                                                    },
                                                }).url
                                            }
                                        >
                                            <Download className="size-4" />
                                            PDF
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        );
                    })}

                    {/* Administrasi & Keuangan (user, 2026-11-20): the UL's Laporan Realisasi Cash Card. */}
                    <div className="flex flex-col justify-between gap-4 rounded-md border border-l-4 border-border border-l-primary bg-card p-5 shadow-xs">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">
                                Laporan Administrasi Keuangan
                            </h2>
                            <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                                Dokumen Laporan Realisasi Cash Card UL bulan ini
                                (lihat, edit &amp; cetak): tabel transaksi &amp;
                                saldo, lalu bukti kas kecil &amp; lampiran (foto
                                nota/barang, KTP, email &amp; NPWP supplier).
                                Data dari input JOF Administrasi Umum.
                            </p>
                        </div>
                        {can('keuangan.view') ? (
                            <div className="flex flex-wrap gap-2">
                                <Button asChild>
                                    <Link
                                        href={
                                            keuanganDocument.show({
                                                query: keuanganQuery,
                                            }).url
                                        }
                                    >
                                        <Wallet className="size-4" />
                                        Buka Dokumen
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a
                                        href={
                                            keuanganDocument.pdf({
                                                query: {
                                                    ...keuanganQuery,
                                                    download: 1,
                                                },
                                            }).url
                                        }
                                    >
                                        <Download className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <Button
                                variant="outline"
                                disabled
                                className="w-fit cursor-not-allowed"
                                title="Akun ini belum diberi akses Administrasi & Keuangan"
                            >
                                <Wallet className="size-4" />
                                Buka Laporan
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-col justify-between gap-4 rounded-md border border-l-4 border-border border-l-primary bg-card p-5 shadow-xs">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">
                                PPT Rutin Bulanan
                            </h2>
                            <p className="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">
                                Satu file PowerPoint (16:9) berisi sampul,
                                agenda, lalu Laporan Pengusahaan Pembangkit,
                                Laporan Logistik dan Laporan K3L: KPI, arah
                                data, perbandingan per unit, grafik 3D, status
                                laporan dan kelengkapan input.
                            </p>
                            {pptError && (
                                <p className="mt-2 text-[13px] text-red-700 dark:text-red-300">
                                    {pptError}
                                </p>
                            )}
                        </div>
                        <Button
                            onClick={makePpt}
                            disabled={makingPpt || units.length === 0}
                            className="w-fit"
                        >
                            {makingPpt ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Presentation className="size-4" />
                            )}
                            {makingPpt ? 'Menyusun PPT…' : 'Unduh PPT Bulanan'}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

LaporanUlIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan UL', href: laporanUl.index() },
    ],
};
