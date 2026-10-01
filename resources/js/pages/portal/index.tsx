import { Head, Link } from '@inertiajs/react';
import { Download, Eye, Info, PenLine } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    PercentBar,
    PercentPill,
    formatStamp,
} from '@/components/monitoring/shared';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import monitoring from '@/routes/monitoring';
import portal from '@/routes/portal';

type ReportLink = {
    key: number | string;
    unit: string;
    title: string;
    period?: string;
    status?: string;
    finalized_at?: string | null;
    view_url: string;
    download_url: string;
    document_url: string;
};

type Props = {
    period: { month: number; year: number; label: string };
    scope_label: string;
    read_only: boolean;
    unit_count: number;
    input_percent: number | null;
    areas: {
        key: string;
        label: string;
        input_percent: number | null;
        final: number;
        waiting: number;
        total: number;
    }[];
    stuck: {
        id: number;
        module: string;
        unit: string;
        period: string;
        status: string;
        waiting_for: string | null;
        days: number;
        url: string;
    }[];
    recent_final: ReportLink[];
    my_turn: ReportLink[];
};

/** A bordered content panel with a title row and an optional link on the right. */
function Panel({
    title,
    count,
    action,
    children,
}: {
    title: string;
    count?: number;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col rounded-md border border-border bg-card">
            <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                <h2 className="flex-1 text-base font-semibold text-foreground">
                    {title}
                    {count !== undefined && (
                        <span className="ml-2 rounded-sm bg-muted px-1.5 text-[12px] font-medium text-muted-foreground tabular-nums">
                            {count}
                        </span>
                    )}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function ReportRow({ report, sign }: { report: ReportLink; sign?: boolean }) {
    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">
                    {report.title}
                </p>
                <p className="text-[13px] text-muted-foreground">
                    {report.unit} · {report.period}
                    {report.finalized_at
                        ? ` · disahkan ${formatStamp(report.finalized_at)}`
                        : ''}
                    {report.status ? ` · ${report.status}` : ''}
                </p>
            </div>
            <div className="flex shrink-0 gap-2">
                {sign ? (
                    <Button size="sm" asChild>
                        <Link href={report.document_url}>
                            <PenLine className="size-4" /> Buka & tanda tangani
                        </Link>
                    </Button>
                ) : (
                    <>
                        <Button size="sm" variant="outline" asChild>
                            <a
                                href={report.view_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye className="size-4" /> Lihat
                            </a>
                        </Button>
                        <Button size="sm" variant="ghost" asChild>
                            <a href={report.download_url}>
                                <Download className="size-4" /> Unduh
                            </a>
                        </Button>
                    </>
                )}
            </div>
        </li>
    );
}

export default function PortalIndex({
    period,
    scope_label,
    read_only,
    unit_count,
    input_percent,
    areas,
    stuck,
    recent_final,
    my_turn,
}: Props) {
    const query = { query: { month: period.month, year: period.year } };
    const totals = areas.reduce(
        (sum, area) => ({
            final: sum.final + area.final,
            waiting: sum.waiting + area.waiting,
            total: sum.total + area.total,
        }),
        { final: 0, waiting: 0, total: 0 },
    );

    return (
        <>
            <Head title="Portal Pemantauan" />
            <div className="flex min-w-0 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Portal Pemantauan"
                    description={`${scope_label} · ${unit_count} unit · periode ${period.label}`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={portal.input().url}>
                                    Data input
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={portal.laporan().url}>
                                    Laporan final
                                </Link>
                            </Button>
                        </>
                    }
                />

                {read_only && (
                    <p className="flex items-start gap-2 rounded-md border border-border bg-secondary px-3 py-2 text-[13px] text-muted-foreground">
                        <Info className="mt-0.5 size-4 shrink-0 text-primary" />
                        Akun ini hanya dapat melihat data input, status, dan
                        laporan, serta mengunduh PDF. Perubahan data dilakukan
                        oleh unit.
                    </p>
                )}

                {my_turn.length > 0 && (
                    <Panel
                        title="Menunggu tanda tangan Anda"
                        count={my_turn.length}
                    >
                        <ul className="divide-y divide-border">
                            {my_turn.map((report) => (
                                <ReportRow
                                    key={report.key}
                                    report={report}
                                    sign
                                />
                            ))}
                        </ul>
                    </Panel>
                )}

                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <SummaryCard
                        label="Kelengkapan input"
                        value={
                            input_percent === null ? '—' : `${input_percent}%`
                        }
                        hint="Rata-rata seluruh bidang"
                    />
                    <SummaryCard
                        label="Laporan final"
                        value={totals.final}
                        unit={`/ ${totals.total}`}
                        hint="Laporan Pembangkit periode ini"
                    />
                    <SummaryCard
                        label="Dalam proses"
                        value={totals.waiting}
                        hint="Diajukan, diperiksa, atau disetujui"
                    />
                    <SummaryCard
                        label="Laporan tertahan"
                        value={stuck.length}
                        hint="Menunggu ≥ 3 hari di satu tahap"
                    />
                </div>

                <Panel title={`Ringkasan per bidang · ${period.label}`}>
                    <div className="overflow-x-auto">
                        <table
                            className="w-full border-collapse text-[13px]"
                            data-keep-table
                        >
                            <thead>
                                <tr className="border-b border-border bg-secondary text-left text-[12px] whitespace-nowrap text-muted-foreground">
                                    <th className="px-4 py-2.5 font-semibold">
                                        Bidang
                                    </th>
                                    <th className="min-w-48 px-3 py-2.5 font-semibold">
                                        Kelengkapan input
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-semibold">
                                        Final
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-semibold">
                                        Dalam proses
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-semibold">
                                        Total laporan
                                    </th>
                                    <th className="px-4 py-2.5 text-right font-semibold">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {areas.map((area) => (
                                    <tr
                                        key={area.key}
                                        className="border-b border-border last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-2.5 font-medium whitespace-nowrap text-foreground">
                                            {area.label}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <div className="flex items-center gap-3">
                                                <div className="flex-1">
                                                    <PercentBar
                                                        percent={
                                                            area.input_percent
                                                        }
                                                    />
                                                </div>
                                                <PercentPill
                                                    percent={area.input_percent}
                                                />
                                            </div>
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-foreground tabular-nums">
                                            {area.final}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-foreground tabular-nums">
                                            {area.waiting}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-foreground tabular-nums">
                                            {area.total}
                                        </td>
                                        <td className="px-4 py-2.5 text-right whitespace-nowrap">
                                            <Link
                                                href={
                                                    portal.input({
                                                        query: {
                                                            bidang: area.key,
                                                            month: period.month,
                                                            year: period.year,
                                                        },
                                                    }).url
                                                }
                                                className="font-medium text-primary hover:underline"
                                            >
                                                Data input
                                            </Link>
                                            <span className="px-2 text-border">
                                                |
                                            </span>
                                            <Link
                                                href={portal.laporan(query).url}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                Laporan
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Laporan final terbaru"
                        action={
                            <Link
                                href={portal.laporan().url}
                                className="text-[13px] font-medium text-primary hover:underline"
                            >
                                Lihat semua
                            </Link>
                        }
                    >
                        {recent_final.length === 0 ? (
                            <p className="px-4 py-8 text-center text-[13px] text-muted-foreground">
                                Belum ada laporan yang final.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recent_final.map((report) => (
                                    <ReportRow
                                        key={report.key}
                                        report={report}
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Laporan tertahan"
                        count={stuck.length}
                        action={
                            <Link
                                href={monitoring.laporan(query).url}
                                className="text-[13px] font-medium text-primary hover:underline"
                            >
                                Status laporan
                            </Link>
                        }
                    >
                        {stuck.length === 0 ? (
                            <p className="px-4 py-8 text-center text-[13px] text-muted-foreground">
                                Tidak ada laporan yang tertahan ≥ 3 hari.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {stuck.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex items-start justify-between gap-3 px-4 py-3"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-foreground">
                                                {row.module} · {row.unit}
                                            </span>
                                            <span className="block text-[13px] text-muted-foreground">
                                                {row.period} — menunggu{' '}
                                                {row.waiting_for ??
                                                    'penanda tangan'}
                                            </span>
                                        </span>
                                        <span className="shrink-0 rounded-sm bg-red-500/10 px-2 py-0.5 text-[12px] font-semibold text-red-700 tabular-nums">
                                            {row.days} hari
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

PortalIndex.layout = { breadcrumbs: [] };
