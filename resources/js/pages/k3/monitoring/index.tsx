import { Head, router } from '@inertiajs/react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import monitoring from '@/routes/k3/monitoring';
import type { IdName, Tone } from '@/types';

type Expiry = {
    status: string;
    tone: string;
    days: number | null;
    months: number | null;
};

type Certificate = Expiry & {
    jenis: string;
    category: string | null;
    kapasitas: string | null;
    lokasi: string | null;
    no_seri: string | null;
    uji_terakhir_tanggal: string | null;
    uji_ulang_tanggal: string | null;
};

type Extinguisher = Expiry & {
    rfid: string | null;
    location: string | null;
    jenis: string | null;
    exp_date: string | null;
    kondisi_tabung: string | null;
};

type Data = {
    unit: { name: string };
    period: { month: number; year: number };
    threshold_days: number;
    certificates: Certificate[];
    extinguishers: Extinguisher[];
    summary: {
        accident_nihil: boolean;
        accident_casualties: number;
        activities_realized: number;
        patrol_total_scan: number;
        emergency_readiness: number | null;
        cert_expired: number;
        cert_mendekati: number;
        apar_expired: number;
        apar_mendekati: number;
    };
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    data: Data;
};

const STATUS_LABEL: Record<string, string> = {
    aktif: 'Aktif',
    mendekati: 'Mendekati',
    expired: 'Expired',
    belum: 'Belum Ada Data',
};

function ExpiryBadge({ item }: { item: Expiry }) {
    const remaining =
        item.days === null
            ? ''
            : item.days < 0
              ? ` · lewat ${Math.abs(item.days)} hari`
              : ` · ${item.days} hari lagi`;

    return (
        <StatusBadge tone={item.tone as Tone}>
            {STATUS_LABEL[item.status] ?? item.status}
            {remaining}
        </StatusBadge>
    );
}

function Metric({ label, value, tone }: { label: string; value: string; tone?: Tone }) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <div className="text-[11px] text-muted-foreground">{label}</div>
            <div className="mt-1 flex items-center gap-2">
                <span className="text-lg font-semibold text-foreground">{value}</span>
                {tone && <span className={`size-2 rounded-full ${tone === 'danger' ? 'bg-red-500' : tone === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'}`} />}
            </div>
        </div>
    );
}

export default function K3Monitoring({ filters, options, data }: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            monitoring.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const { summary } = data;

    return (
        <>
            <Head title="Monitoring K3 & Keamanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Monitoring K3 & Keamanan"
                    description={`Status kadaluarsa sertifikat & APAR (ambang mendekati ≤ ${data.threshold_days} hari) dan ringkasan bulan berjalan. Label di sistem, bukan notifikasi.`}
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
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

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Metric
                        label="Kecelakaan Bulan Ini"
                        value={summary.accident_nihil ? 'NIHIL' : `${summary.accident_casualties} korban`}
                        tone={summary.accident_nihil ? 'success' : 'danger'}
                    />
                    <Metric label="Kegiatan Terealisasi" value={String(summary.activities_realized)} />
                    <Metric label="Total Scan Patroli" value={String(summary.patrol_total_scan)} />
                    <Metric
                        label="Kesiapan Darurat"
                        value={summary.emergency_readiness === null ? '—' : `${summary.emergency_readiness}%`}
                    />
                    <Metric
                        label="Sertifikat Expired"
                        value={String(summary.cert_expired)}
                        tone={summary.cert_expired > 0 ? 'danger' : 'success'}
                    />
                    <Metric
                        label="Sertifikat Mendekati"
                        value={String(summary.cert_mendekati)}
                        tone={summary.cert_mendekati > 0 ? 'warning' : 'success'}
                    />
                    <Metric
                        label="APAR Expired"
                        value={String(summary.apar_expired)}
                        tone={summary.apar_expired > 0 ? 'danger' : 'success'}
                    />
                    <Metric
                        label="APAR Mendekati"
                        value={String(summary.apar_mendekati)}
                        tone={summary.apar_mendekati > 0 ? 'warning' : 'success'}
                    />
                </div>

                <section className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">Sertifikasi Peralatan</h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Lokasi</TableHead>
                                    <TableHead>No. Seri</TableHead>
                                    <TableHead>Uji Ulang</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.certificates.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground">
                                            Belum ada data sertifikat.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    data.certificates.map((cert, index) => (
                                        <TableRow key={index}>
                                            <TableCell className="font-medium">{cert.jenis}</TableCell>
                                            <TableCell>{cert.category ?? '—'}</TableCell>
                                            <TableCell>{cert.lokasi ?? '—'}</TableCell>
                                            <TableCell>{cert.no_seri ?? '—'}</TableCell>
                                            <TableCell>{cert.uji_ulang_tanggal ?? '—'}</TableCell>
                                            <TableCell>
                                                <ExpiryBadge item={cert} />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold text-foreground">APAR/APAB</h2>
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>RFID</TableHead>
                                    <TableHead>Lokasi</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Kondisi</TableHead>
                                    <TableHead>Exp Date</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.extinguishers.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground">
                                            Belum ada APAR/APAB pada master.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    data.extinguishers.map((ext, index) => (
                                        <TableRow key={index}>
                                            <TableCell className="font-medium">{ext.rfid ?? '—'}</TableCell>
                                            <TableCell>{ext.location ?? '—'}</TableCell>
                                            <TableCell>{ext.jenis ?? '—'}</TableCell>
                                            <TableCell>{ext.kondisi_tabung ?? '—'}</TableCell>
                                            <TableCell>{ext.exp_date ?? '—'}</TableCell>
                                            <TableCell>
                                                <ExpiryBadge item={ext} />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </>
    );
}

K3Monitoring.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring K3', href: monitoring.index() },
    ],
};
