import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, ClipboardCheck, FileCheck2, UserX } from 'lucide-react';
import { MonitoringFilterBar, MonitoringTabs, PercentBar, PercentPill, filterQuery } from '@/components/monitoring/shared';
import type { MonitoringFilters, MonitoringOptions } from '@/components/monitoring/shared';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';

type Group = { key: string; label: string; percent: number | null };

type StuckRow = { id: number; module: string; unit: string; period: string; status: string; waiting_for: string | null; days: number; url: string };

type Props = {
    filters: MonitoringFilters;
    options: MonitoringOptions;
    period_label: string;
    input: {
        state: 'past' | 'current' | 'future';
        percent: number | null;
        groups: Group[];
        lowest_units: { id: number; name: string; percent: number }[];
        empty_inputs: { label: string; group: string; units: number }[];
    };
    laporan: {
        counts: Record<string, number>;
        stuck: StuckRow[];
        stuck_total: number;
        open_rejections: number;
        missing_signers: { unit: string; positions: string[] }[];
        total: number;
    };
};

const STATUS_CARDS = [
    { key: 'belum', label: 'Belum dibuat', tone: 'text-muted-foreground' },
    { key: 'draft', label: 'Draft', tone: 'text-muted-foreground' },
    { key: 'diajukan', label: 'Menunggu verifikasi', tone: 'text-sky-600 dark:text-sky-400' },
    { key: 'verifikasi', label: 'Menunggu persetujuan', tone: 'text-sky-600 dark:text-sky-400' },
    { key: 'disetujui', label: 'Menunggu pengesahan', tone: 'text-sky-600 dark:text-sky-400' },
    { key: 'final', label: 'Final', tone: 'text-emerald-600 dark:text-emerald-400' },
    { key: 'ditolak', label: 'Ditolak', tone: 'text-red-600 dark:text-red-400' },
] as const;

function Panel({ title, icon: Icon, action, children }: { title: string; icon: typeof ClipboardCheck; action?: React.ReactNode; children: React.ReactNode }) {
    return (
        <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-[15px] font-semibold text-foreground">
                    <Icon className="size-4 text-primary" />
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export default function MonitoringIndex({ filters, options, period_label, input, laporan }: Props) {
    const query = filterQuery(filters);
    const current = input.state === 'current';
    const signerGaps = laporan.missing_signers.reduce((sum, row) => sum + row.positions.length, 0);

    return (
        <>
            <Head title="Monitoring" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Monitoring" description={`Ringkasan kelengkapan input & verifikasi laporan seluruh unit — ${period_label}.`} />
                <MonitoringTabs filters={filters} />
                <MonitoringFilterBar url={monitoring.index().url} filters={filters} options={options} />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-md border border-border bg-card p-4">
                        <p className="text-[13px] text-muted-foreground">Kelengkapan input</p>
                        <p className="mt-1 text-3xl font-bold text-foreground tabular-nums">{input.percent === null ? '—' : `${input.percent}%`}</p>
                        <div className="mt-2">
                            <PercentBar percent={input.percent} />
                        </div>
                        <p className="mt-2 text-[12px] text-muted-foreground">
                            {input.state === 'future' ? 'Periode belum berjalan.' : current ? 'Bulan berjalan — dihitung sampai hari ini.' : 'Periode sudah lewat.'}
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-4">
                        <p className="text-[13px] text-muted-foreground">Laporan final</p>
                        <p className="mt-1 text-3xl font-bold text-foreground tabular-nums">
                            {laporan.counts.final ?? 0}
                            <span className="text-base font-medium text-muted-foreground"> / {laporan.total}</span>
                        </p>
                        <p className="mt-2 text-[12px] text-muted-foreground">Laporan Pembangkit unit × modul periode ini.</p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-4">
                        <p className="text-[13px] text-muted-foreground">Laporan macet / ditolak</p>
                        <p className="mt-1 text-3xl font-bold text-foreground tabular-nums">
                            <span className={laporan.stuck_total > 0 ? 'text-red-600 dark:text-red-400' : ''}>{laporan.stuck_total}</span>
                            <span className="text-base font-medium text-muted-foreground"> / {laporan.open_rejections} ditolak</span>
                        </p>
                        <p className="mt-2 text-[12px] text-muted-foreground">Macet = menunggu di satu tahap ≥ 3 hari (semua periode).</p>
                    </div>
                    <div className="rounded-md border border-border bg-card p-4">
                        <p className="text-[13px] text-muted-foreground">Jabatan penanda tangan kosong</p>
                        <p className="mt-1 text-3xl font-bold tabular-nums">
                            <span className={signerGaps > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}>{signerGaps}</span>
                        </p>
                        <p className="mt-2 text-[12px] text-muted-foreground">Di {laporan.missing_signers.length} unit — laporan tidak bisa diajukan.</p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Kelengkapan per modul"
                        icon={ClipboardCheck}
                        action={
                            <Link href={monitoring.input({ query }).url} className="flex items-center gap-1 text-[13px] font-medium text-primary">
                                Detail <ArrowRight className="size-4" />
                            </Link>
                        }
                    >
                        <div className="flex flex-col gap-3">
                            {input.groups.map((group) => (
                                <div key={group.key} className="flex items-center gap-3">
                                    <span className="w-44 shrink-0 truncate text-[13px] text-foreground sm:w-56">{group.label}</span>
                                    <div className="flex-1">
                                        <PercentBar percent={group.percent} />
                                    </div>
                                    <PercentPill percent={group.percent} current={current} />
                                </div>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Unit dengan kelengkapan terendah" icon={AlertTriangle}>
                        {input.lowest_units.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">Tidak ada data.</p>
                        ) : (
                            <div className="flex flex-col divide-y divide-border">
                                {input.lowest_units.map((unit) => (
                                    <div key={unit.id} className="flex items-center justify-between gap-3 py-2">
                                        <span className="truncate text-[13px] text-foreground">{unit.name}</span>
                                        <PercentPill percent={unit.percent} current={current} />
                                    </div>
                                ))}
                            </div>
                        )}
                        {input.empty_inputs.length > 0 && (
                            <div className="mt-1 rounded-md bg-muted/50 p-3">
                                <p className="mb-2 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">Input paling sering kosong</p>
                                <ul className="flex flex-col gap-1.5">
                                    {input.empty_inputs.map((row) => (
                                        <li key={`${row.group}-${row.label}`} className="flex items-start justify-between gap-3 text-[13px]">
                                            <span className="min-w-0">
                                                <span className="text-foreground">{row.label}</span>
                                                <span className="block text-[11.5px] text-muted-foreground">{row.group}</span>
                                            </span>
                                            <span className="shrink-0 text-[12px] font-semibold text-red-600 dark:text-red-400">{row.units} unit</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </Panel>

                    <Panel
                        title="Status Laporan Pembangkit"
                        icon={FileCheck2}
                        action={
                            <Link href={monitoring.laporan({ query }).url} className="flex items-center gap-1 text-[13px] font-medium text-primary">
                                Detail <ArrowRight className="size-4" />
                            </Link>
                        }
                    >
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {STATUS_CARDS.map((card) => (
                                <div key={card.key} className="rounded-md border border-border p-2.5">
                                    <p className={`text-xl font-bold tabular-nums ${card.tone}`}>{laporan.counts[card.key] ?? 0}</p>
                                    <p className="text-[12px] leading-tight text-muted-foreground">{card.label}</p>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Perlu tindakan" icon={UserX}>
                        {laporan.stuck.length === 0 && laporan.missing_signers.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">Tidak ada laporan macet atau jabatan kosong. 👍</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {laporan.stuck.map((row) => (
                                    <li key={row.id}>
                                        <Link href={row.url} className="block rounded-md border border-red-500/30 bg-red-500/5 p-2.5 hover:bg-red-500/10">
                                            <p className="text-[13px] font-semibold text-foreground">
                                                {row.module} · {row.unit}
                                            </p>
                                            <p className="text-[12px] text-muted-foreground">
                                                {row.period} — {row.days} hari menunggu {row.waiting_for ? `(${row.waiting_for})` : ''}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                                {laporan.missing_signers.slice(0, 5).map((row) => (
                                    <li key={row.unit} className="rounded-md border border-amber-500/30 bg-amber-500/5 p-2.5">
                                        <p className="text-[13px] font-semibold text-foreground">{row.unit}</p>
                                        <p className="text-[12px] text-muted-foreground">Belum ada pegawai aktif: {row.positions.join(', ')}</p>
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

MonitoringIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring', href: monitoring.index() },
    ],
};
