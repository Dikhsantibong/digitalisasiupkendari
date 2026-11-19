import { Head, Link, router } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    Download,
    FilePenLine,
    FileText,
    Target,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/laporan';
import slaRoutes from '@/routes/sla';
import type { IdName, Tone } from '@/types';

type Filters = { unit_id: number; month: number; year: number };

type ReportItem = {
    module: string;
    title: string;
    kind: string;
    status: string;
    status_tone: Tone;
    document_url: string;
    pdf_url: string;
    download_url: string;
};

type Props = {
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    groups: {
        key: string;
        title: string;
        reports: ReportItem[];
        resume_url: string | null;
    }[];
    can_sla: boolean;
};

/**
 * One "Laporan" menu (Project Leader): every report the account may view,
 * grouped by module — Laporan Project, Laporan Divisi and the Resume Bulanan —
 * with the approval status, for one unit and month.
 */
export default function LaporanHub({
    filters,
    options,
    groups,
    can_sla,
}: Props) {
    const visit = (patch: Partial<Filters>) =>
        router.get(
            laporan.hub().url,
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Laporan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan"
                    description="Seluruh laporan per modul untuk unit & bulan yang dipilih — buka dokumen, lihat status persetujuan, atau unduh PDF."
                    actions={
                        can_sla ? (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        slaRoutes.combined.index({
                                            query: filters,
                                        }).url
                                    }
                                >
                                    <Target className="size-4" />
                                    Laporan SLA Gabungan
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
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

                {groups.length === 0 && (
                    <p className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        Belum ada laporan yang dapat Anda lihat.
                    </p>
                )}

                {groups.map((group) => (
                    <section
                        key={group.key}
                        className="flex flex-col gap-3 rounded-md border border-border bg-card p-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-base font-semibold text-foreground">
                                {group.title}
                            </h2>
                            {group.resume_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={group.resume_url}>
                                        <ChartNoAxesCombined className="size-4" />
                                        Resume Bulanan
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            {group.reports.map((report) => (
                                <div
                                    key={report.module}
                                    className="flex flex-col justify-between gap-3 rounded-md border border-l-4 border-border border-l-primary p-4"
                                >
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                            {report.kind}
                                        </p>
                                        <h3 className="text-sm font-semibold text-foreground">
                                            {report.title}
                                        </h3>
                                        <div className="mt-1.5">
                                            <StatusBadge
                                                tone={report.status_tone}
                                            >
                                                {report.status}
                                            </StatusBadge>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" asChild>
                                            <Link href={report.document_url}>
                                                <FilePenLine className="size-4" />
                                                Buka Dokumen
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <a
                                                href={report.pdf_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText className="size-4" />
                                                PDF
                                            </a>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <a href={report.download_url}>
                                                <Download className="size-4" />
                                                Unduh
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

LaporanHub.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan', href: laporan.hub() },
    ],
};
