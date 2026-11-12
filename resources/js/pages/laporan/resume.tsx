import { Head, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Download,
    FileSpreadsheet,
    FileText,
    LoaderCircle,
    Minus,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    BarList,
    BarsChart,
    Donut,
    Gauge,
    Heatmap,
    LevelBars,
    LinesChart,
    Panel,
    SCurve,
    Sparkline,
} from '@/components/dashboard/charts';
import type { Datum, MultiSeries } from '@/components/dashboard/charts';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { downloadResumeWorkbook } from '@/lib/resume-excel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import resumeRoutes from '@/routes/laporan/resume';
import type { IdName } from '@/types';

type Chart = { title: string; subtitle?: string; svg: string } & (
    | ({ type: 'bar' | 'stacked'; line_last?: boolean } & MultiSeries)
    | ({
          type: 'line';
          threshold?: number;
          threshold_label?: string;
      } & MultiSeries)
    | { type: 'donut' | 'hbar'; data: Datum[] }
    | { type: 'level'; data: (Datum & { capacity: number })[] }
    | {
          type: 'gauge';
          items: {
              label: string;
              value: number;
              target: number;
              good: 'up' | 'down';
          }[];
      }
    | {
          type: 'scurve';
          points: { day: number; plan: number; real: number | null }[];
      }
    | {
          type: 'heatmap';
          columns: string[];
          rows: { label: string; values: (number | null)[] }[];
      }
);

type Resume = {
    module: string;
    title: string;
    unit: { id: number; name: string; service_unit: string | null };
    period: { month: number; year: number; label: string };
    kpis: {
        label: string;
        value: string;
        delta: number | null;
        trend: 'naik' | 'turun' | 'tetap' | null;
        assessment: 'membaik' | 'memburuk' | 'stabil' | null;
        spark: number[];
    }[];
    insights: {
        tone: 'success' | 'danger' | 'warning' | 'info' | 'neutral';
        text: string;
    }[];
    charts: Chart[];
    sections: {
        title: string;
        columns: string[];
        rows: string[][];
        note?: string;
    }[];
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    resume: Resume;
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    modules: { key: string; label: string }[];
};

const DONUT_COLORS = [
    'text-chart-1',
    'text-amber-500',
    'text-emerald-500',
    'text-rose-500',
    'text-chart-5',
    'text-slate-400',
];

function NoData() {
    return (
        <p className="py-8 text-center text-[13px] text-muted-foreground">
            Belum ada data.
        </p>
    );
}

/** One chart of the resume, drawn with the dashboard's chart components. */
function ResumeChart({ chart }: { chart: Chart }) {
    switch (chart.type) {
        case 'bar':
        case 'stacked':
            return (
                <BarsChart
                    data={chart}
                    mode={chart.type === 'stacked' ? 'stacked' : 'grouped'}
                    lineLast={chart.line_last}
                    format="decimal"
                    lineFormat="decimal"
                />
            );
        case 'line':
            return chart.series.length === 0 ? (
                <NoData />
            ) : (
                <LinesChart
                    data={chart}
                    threshold={chart.threshold}
                    thresholdLabel={chart.threshold_label}
                />
            );
        case 'donut':
            return <Donut data={chart.data} colors={DONUT_COLORS} />;
        case 'hbar':
            return chart.data.length === 0 ? (
                <NoData />
            ) : (
                <BarList data={chart.data} format="decimal" />
            );
        case 'level':
            return chart.data.length === 0 ? (
                <NoData />
            ) : (
                <LevelBars
                    data={chart.data}
                    suffix={(d) =>
                        `${(d.capacity ?? 0).toLocaleString('id-ID')} kL`
                    }
                />
            );
        case 'gauge':
            return (
                <div className="grid grid-cols-3 gap-2">
                    {chart.items.map((item) => (
                        <Gauge key={item.label} {...item} />
                    ))}
                </div>
            );
        case 'scurve':
            return <SCurve points={chart.points} />;
        case 'heatmap':
            return chart.rows.length === 0 ? (
                <NoData />
            ) : (
                <Heatmap columns={chart.columns} rows={chart.rows} />
            );
    }
}

/**
 * Resume Bulanan per modul: KPI, every chart and the tables of one unit &
 * month, with the PDF (server, charts included) and the Excel (tables +
 * a "Grafik" sheet of chart images) downloads.
 */
export default function ResumePage({
    resume,
    filters,
    options,
    modules,
}: Props) {
    const [exporting, setExporting] = useState(false);
    const query = { query: filters };
    const visit = (module: string, patch: Partial<Filters> = {}) => {
        router.get(
            resumeRoutes.show(module, { query: { ...filters, ...patch } }).url,
            {},
            { preserveScroll: true },
        );
    };
    const fileBase =
        `Resume_${resume.module}_${resume.unit.name}_${resume.period.month}_${resume.period.year}`.replace(
            /\s+/g,
            '_',
        );

    // Opened from a Laporan page's "Excel" button (?export=excel): download once, then drop the flag from the URL.
    const autoExported = useRef(false);
    useEffect(() => {
        const url = new URL(window.location.href);

        if (
            autoExported.current ||
            url.searchParams.get('export') !== 'excel'
        ) {
            return;
        }

        autoExported.current = true;
        url.searchParams.delete('export');
        window.history.replaceState(window.history.state, '', url.toString());
        void downloadResumeWorkbook(resume, `${fileBase}.xlsx`);
    }, [resume, fileBase]);

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadResumeWorkbook(resume, `${fileBase}.xlsx`);
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title={`${resume.title} - ${resume.unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={resume.title}
                    description={`${resume.unit.name} · periode ${resume.period.label} — grafik & tabel ringkas dari input modul, siap diunduh PDF atau Excel.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        resumeRoutes.pdf(resume.module, query)
                                            .url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText className="size-4" /> Lihat PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        resumeRoutes.pdf(resume.module, {
                                            query: { ...filters, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh PDF
                                </a>
                            </Button>
                            <Button onClick={exportExcel} disabled={exporting}>
                                {exporting ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <FileSpreadsheet className="size-4" />
                                )}
                                Unduh Excel
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    {modules.length > 1 && (
                        <OperasiSelect
                            label="Modul"
                            value={resume.module}
                            onChange={(value) => visit(value)}
                            options={modules.map((m) => ({
                                value: m.key,
                                label: m.label,
                            }))}
                        />
                    )}
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit(resume.module, { unit_id: Number(value) })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) =>
                            visit(resume.module, { month: Number(value) })
                        }
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) =>
                            visit(resume.module, { year: Number(value) })
                        }
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    {resume.kpis.map((kpi) => {
                        const Arrow =
                            kpi.trend === 'naik'
                                ? ArrowUpRight
                                : kpi.trend === 'turun'
                                  ? ArrowDownRight
                                  : Minus;

                        return (
                            <div
                                key={kpi.label}
                                className="flex flex-col gap-1.5 rounded-md border border-border bg-card p-3.5"
                            >
                                <p className="truncate text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    {kpi.label}
                                </p>
                                <p className="text-[20px] leading-tight font-semibold text-foreground tabular-nums">
                                    {kpi.value}
                                </p>
                                {kpi.spark.length > 1 ? (
                                    <Sparkline
                                        values={kpi.spark}
                                        className="text-chart-1"
                                    />
                                ) : (
                                    <div className="h-7" />
                                )}
                                {kpi.trend === null || kpi.delta === null ? (
                                    <p className="text-xs text-muted-foreground">
                                        Bulan berjalan
                                    </p>
                                ) : (
                                    <p
                                        className={cn(
                                            'inline-flex items-center gap-1 text-xs font-semibold',
                                            kpi.assessment === 'membaik' &&
                                                'text-emerald-700',
                                            kpi.assessment === 'memburuk' &&
                                                'text-red-700',
                                            kpi.assessment === 'stabil' &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <Arrow className="size-3.5" />
                                        {kpi.trend === 'tetap'
                                            ? 'Stabil'
                                            : `${Math.abs(kpi.delta).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`}{' '}
                                        vs bulan lalu · {kpi.assessment}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </section>

                <Panel
                    title="Ringkasan Arah Data"
                    subtitle="Dibaca otomatis dari indikator & tren — hijau membaik, merah memburuk"
                >
                    <ul className="flex flex-col gap-1.5">
                        {resume.insights.map((line) => (
                            <li
                                key={line.text}
                                className="flex items-start gap-2 text-[13px] text-foreground"
                            >
                                <TrendingUp
                                    className={cn(
                                        'mt-0.5 size-4 shrink-0',
                                        line.tone === 'success' &&
                                            'text-emerald-600',
                                        line.tone === 'danger' &&
                                            'text-red-600',
                                        line.tone === 'warning' &&
                                            'text-amber-600',
                                        line.tone === 'info' && 'text-chart-1',
                                        line.tone === 'neutral' &&
                                            'text-muted-foreground',
                                    )}
                                />
                                <span>{line.text}</span>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <section className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    {resume.charts.map((chart, index) => (
                        <Panel
                            key={chart.title}
                            title={`${index + 1}. ${chart.title}`}
                            subtitle={chart.subtitle}
                        >
                            <ResumeChart chart={chart} />
                        </Panel>
                    ))}
                </section>

                <section className="flex flex-col gap-4">
                    {resume.sections.map((section, index) => (
                        <div
                            key={section.title}
                            className="flex flex-col gap-1.5"
                        >
                            <h2 className="text-sm font-semibold text-foreground">
                                {index + 1}. {section.title}
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="excel-sheet w-full text-[13px]">
                                    <thead>
                                        <tr>
                                            <th className="w-10">No</th>
                                            {section.columns.map((column) => (
                                                <th key={column}>{column}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {section.rows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        section.columns.length +
                                                        1
                                                    }
                                                    className="py-3 text-center text-muted-foreground"
                                                >
                                                    Belum ada data.
                                                </td>
                                            </tr>
                                        ) : (
                                            section.rows.map((row, r) => (
                                                <tr key={r}>
                                                    <td className="text-center">
                                                        {r + 1}
                                                    </td>
                                                    {row.map((cell, c) => (
                                                        <td
                                                            key={c}
                                                            className={
                                                                c === 0
                                                                    ? 'px-2'
                                                                    : 'px-2 text-right tabular-nums'
                                                            }
                                                        >
                                                            {cell}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {section.note && (
                                <p className="text-xs text-muted-foreground italic">
                                    {section.note}
                                </p>
                            )}
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}

ResumePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Resume Bulanan', href: '#' },
    ],
};
