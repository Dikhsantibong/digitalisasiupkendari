import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, UserX, XCircle } from 'lucide-react';
import { MonitoringFilterBar, MonitoringTabs, formatStamp } from '@/components/monitoring/shared';
import type { MonitoringFilters, MonitoringOptions } from '@/components/monitoring/shared';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type { Tone } from '@/types';

type Cell = { status: string; label: string; tone: string; waiting_for: string | null; days: number | null; stuck: boolean; url: string };

type Props = {
    filters: MonitoringFilters;
    options: MonitoringOptions;
    period_label: string;
    stuck_after_days: number;
    modules: { key: string; label: string }[];
    matrix: { id: number; name: string; cells: Record<string, Cell> }[];
    counts: Record<string, number>;
    stuck: { id: number; module: string; unit: string; period: string; status: string; waiting_for: string | null; days: number; url: string }[];
    rejections: { id: number; module: string; unit: string; period: string; by: string; reason: string; at: string | null; resolved: boolean; url: string }[];
    durations: { module: string; verifikasi: number | null; setujui: number | null; sahkan: number | null; final: number }[];
    missing_signers: { unit: string; positions: string[] }[];
};

/** Short label of a status inside the matrix (the full label is the tooltip). */
const SHORT: Record<string, string> = {
    belum: 'Belum dibuat',
    draft: 'Draft',
    diajukan: 'Verifikasi Koordinator',
    verifikasi: 'Persetujuan TL',
    disetujui: 'Pengesahan Manager',
    disahkan: 'Disahkan',
    ditandatangani: 'Ditandatangani',
    final: 'Final',
    ditolak: 'Ditolak',
};

function Section({ title, icon: Icon, count, children }: { title: string; icon: typeof Clock; count?: number; children: React.ReactNode }) {
    return (
        <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
            <h2 className="flex items-center gap-2 text-[15px] font-semibold text-foreground">
                <Icon className="size-4 text-primary" />
                {title}
                {count !== undefined && <span className="rounded-sm bg-muted px-1.5 text-[12px] tabular-nums font-medium text-muted-foreground">{count}</span>}
            </h2>
            {children}
        </section>
    );
}

const days = (value: number | null) => (value === null ? '—' : `${value.toLocaleString('id-ID')} hari`);

export default function MonitoringLaporan({ filters, options, period_label, stuck_after_days, modules, matrix, counts, stuck, rejections, durations, missing_signers }: Props) {
    return (
        <>
            <Head title="Monitoring — Verifikasi Laporan" />
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Verifikasi Laporan" description={`Status pemeriksaan → persetujuan → pengesahan Laporan Pembangkit — ${period_label}.`} />
                <MonitoringTabs filters={filters} />
                <MonitoringFilterBar url={monitoring.laporan().url} filters={filters} options={options} />

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                    {(['belum', 'draft', 'diajukan', 'verifikasi', 'disetujui', 'final', 'ditolak'] as const).map((key) => (
                        <div key={key} className="rounded-md border border-border bg-card p-3">
                            <p className={cn('text-2xl font-bold tabular-nums', key === 'final' ? 'text-emerald-600 dark:text-emerald-400' : key === 'ditolak' ? 'text-red-600 dark:text-red-400' : 'text-foreground')}>
                                {counts[key] ?? 0}
                            </p>
                            <p className="text-[12px] leading-tight text-muted-foreground">{SHORT[key]}</p>
                        </div>
                    ))}
                </div>

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[13px]" data-keep-table>
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-[12px] text-muted-foreground">
                                    <th className="sticky left-0 z-10 min-w-40 bg-muted px-3 py-2.5 font-semibold">Unit</th>
                                    {modules.map((module) => (
                                        <th key={module.key} className="min-w-44 px-2 py-2.5 font-semibold">
                                            {module.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {matrix.map((row) => (
                                    <tr key={row.id} className="border-b border-border align-top last:border-0">
                                        <td className="sticky left-0 z-10 bg-card px-3 py-2.5 font-medium text-foreground">{row.name}</td>
                                        {modules.map((module) => {
                                            const cell = row.cells[module.key];

                                            return (
                                                <td key={module.key} className={cn('px-2 py-2', cell.stuck && 'bg-red-500/5')}>
                                                    <Link href={cell.url} title={cell.label} className="flex flex-col gap-1">
                                                        <span>
                                                            <StatusBadge tone={cell.tone as Tone}>{SHORT[cell.status] ?? cell.label}</StatusBadge>
                                                        </span>
                                                        {cell.waiting_for && <span className="text-[11.5px] leading-snug text-muted-foreground">{cell.waiting_for}</span>}
                                                        {cell.days !== null && cell.status !== 'final' && (
                                                            <span className={cn('text-[11.5px]', cell.stuck ? 'font-semibold text-red-600 dark:text-red-400' : 'text-muted-foreground')}>
                                                                {cell.stuck ? 'Tertahan · ' : ''}
                                                                {cell.days} hari di tahap ini
                                                            </span>
                                                        )}
                                                    </Link>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title={`Antrean macet (≥ ${stuck_after_days} hari, semua periode)`} icon={AlertTriangle} count={stuck.length}>
                        {stuck.length === 0 ? (
                            <p className="flex items-center gap-2 text-[13px] text-muted-foreground">
                                <CheckCircle2 className="size-4 text-emerald-500" /> Tidak ada laporan yang tertahan.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {stuck.map((row) => (
                                    <li key={row.id}>
                                        <Link href={row.url} className="flex items-start justify-between gap-3 rounded-md border border-border p-2.5 hover:bg-muted/50">
                                            <span className="min-w-0">
                                                <span className="block text-[13px] font-semibold text-foreground">
                                                    {row.module} · {row.unit}
                                                </span>
                                                <span className="block text-[12px] text-muted-foreground">
                                                    {row.period} — {row.status}
                                                </span>
                                                {row.waiting_for && <span className="block text-[12px] text-muted-foreground">Giliran: {row.waiting_for}</span>}
                                            </span>
                                            <span className="shrink-0 rounded-md bg-red-500/10 px-2 py-0.5 text-[12px] font-semibold text-red-600 dark:text-red-400">{row.days} hari</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title="Penolakan 90 hari terakhir" icon={XCircle} count={rejections.length}>
                        {rejections.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">Tidak ada penolakan.</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {rejections.map((row) => (
                                    <li key={row.id}>
                                        <Link href={row.url} className="block rounded-md border border-border p-2.5 hover:bg-muted/50">
                                            <span className="flex items-start justify-between gap-2">
                                                <span className="text-[13px] font-semibold text-foreground">
                                                    {row.module} · {row.unit} · {row.period}
                                                </span>
                                                <StatusBadge tone={row.resolved ? 'success' : 'danger'}>{row.resolved ? 'Sudah diajukan ulang' : 'Belum diperbaiki'}</StatusBadge>
                                            </span>
                                            <span className="mt-1 block text-[12px] text-muted-foreground">
                                                Ditolak {row.by} · {formatStamp(row.at)}
                                            </span>
                                            {row.reason && <span className="mt-1 block text-[12.5px] text-foreground">“{row.reason}”</span>}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title={`Rata-rata lama tiap tahap (${filters.year})`} icon={Clock}>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-[13px]" data-keep-table>
                                <thead>
                                    <tr className="border-b border-border text-left text-[12px] text-muted-foreground">
                                        <th className="py-2 pr-2 font-semibold">Laporan</th>
                                        <th className="px-2 py-2 text-right font-semibold">Verifikasi</th>
                                        <th className="px-2 py-2 text-right font-semibold">Persetujuan</th>
                                        <th className="px-2 py-2 text-right font-semibold">Pengesahan</th>
                                        <th className="py-2 pl-2 text-right font-semibold">Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {durations.map((row) => (
                                        <tr key={row.module} className="border-b border-border last:border-0">
                                            <td className="py-2 pr-2 text-foreground">{row.module}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{days(row.verifikasi)}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{days(row.setujui)}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{days(row.sahkan)}</td>
                                            <td className="py-2 pl-2 text-right tabular-nums">{row.final}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>

                    <Section title="Jabatan penanda tangan belum terisi" icon={UserX} count={missing_signers.length}>
                        {missing_signers.length === 0 ? (
                            <p className="flex items-center gap-2 text-[13px] text-muted-foreground">
                                <CheckCircle2 className="size-4 text-emerald-500" /> Semua unit lengkap.
                            </p>
                        ) : (
                            <>
                                <p className="text-[12px] text-muted-foreground">Laporan unit ini tidak bisa diajukan sampai pegawai aktif dengan jabatan tersebut ada di Master Pegawai.</p>
                                <ul className="flex flex-col divide-y divide-border">
                                    {missing_signers.map((row) => (
                                        <li key={row.unit} className="py-2">
                                            <p className="text-[13px] font-semibold text-foreground">{row.unit}</p>
                                            <p className="mt-0.5 flex flex-wrap gap-1">
                                                {row.positions.map((position) => (
                                                    <span key={position} className="rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[11.5px] font-medium text-amber-700 dark:text-amber-300">
                                                        {position}
                                                    </span>
                                                ))}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}
                    </Section>
                </div>
            </div>
        </>
    );
}

MonitoringLaporan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring', href: monitoring.index() },
        { title: 'Verifikasi Laporan', href: monitoring.laporan() },
    ],
};
