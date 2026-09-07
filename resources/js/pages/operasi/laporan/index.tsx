import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, Printer } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import laporan from '@/routes/operasi/laporan';
import type { IdName } from '@/types';

type ReportDef = {
    code: string;
    title: string;
    description: string;
    requires_engine: boolean;
};

type Filters = {
    unit_id: number;
    engine_id: number | null;
    month: number;
    year: number;
};

type Props = {
    filters: Filters;
    reports: ReportDef[];
    options: { units: IdName[]; machines: IdName[]; years: number[] };
};

export default function LaporanIndex({ filters, reports, options }: Props) {
    const visit = (patch: Partial<Filters>) => {
        router.get(
            laporan.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reportQuery = () => ({
        unit_id: filters.unit_id,
        engine_id: filters.engine_id ?? undefined,
        month: filters.month,
        year: filters.year,
    });

    const openReport = (report: ReportDef) => {
        router.get(laporan.show(report.code, { query: reportQuery() }).url);
    };

    const openExcel = (report: ReportDef) => {
        router.get(laporan.spreadsheet(report.code, { query: reportQuery() }).url);
    };

    return (
        <>
            <Head title="Laporan Operasi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Operasi"
                    description="Pilih unit, mesin, dan periode, lalu satu klik untuk melihat & mencetak."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({ unit_id: Number(value), engine_id: null })
                        }
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Mesin"
                        value={filters.engine_id ? String(filters.engine_id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                {reports.length === 0 ? (
                    <EmptyState title="Belum ada laporan" description="Belum ada laporan terdaftar." />
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {reports.map((report) => {
                            const needsEngine =
                                report.requires_engine && !filters.engine_id;

                            return (
                                <div
                                    key={report.code}
                                    className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4"
                                >
                                    <div>
                                        <h2 className="text-base font-semibold text-foreground">
                                            {report.title}
                                        </h2>
                                        <p className="mt-1 text-[13px] text-muted-foreground">
                                            {report.description}
                                        </p>
                                    </div>
                                    <div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                onClick={() => openReport(report)}
                                                disabled={needsEngine}
                                            >
                                                <Printer className="size-4" />
                                                Lihat &amp; Cetak
                                            </Button>
                                            <Button
                                                variant="secondary"
                                                onClick={() => openExcel(report)}
                                                disabled={needsEngine}
                                            >
                                                <FileSpreadsheet className="size-4" />
                                                Excel
                                            </Button>
                                        </div>
                                        {needsEngine && (
                                            <p className="mt-1 text-[12px] text-amber-600">
                                                Pilih mesin dulu.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

LaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operasi', href: laporan.index() },
    ],
};
