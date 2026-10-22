import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    ExternalLink,
    Image,
    Sparkles,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import k3Rekap5s5r from '@/routes/k3/input/rekap-5s5r';
import type { IdName } from '@/types';

/** One division's 5S5R input (App\Services\K3\K3DivisionFeed::program5s5r). */
type Program = {
    key: string;
    sumber: string;
    judul: string;
    saved: boolean;
    rows: {
        program: string;
        rencana: number;
        realisasi: number;
        kinerja: string;
        catatan: string | null;
    }[];
    total: { rencana: number; realisasi: number; kinerja: string };
    temuan: string[];
    eviden: number;
    url: string | null;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    programs: Program[];
    summary: {
        divisions: number;
        filled: number;
        rencana: number;
        realisasi: number;
        kinerja: string;
    };
};

/**
 * Rekap 5S5R Seluruh Divisi: what Operasi, Pemeliharaan, PdM and Logistik &
 * Gudang filled in their own 5S5R pages for the month, side by side. Read-only.
 */
export default function K3Rekap5s5rPage({
    unit,
    filters,
    options,
    programs,
    summary,
}: Props) {
    const monthName =
        OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            k3Rekap5s5r.index().url,
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`Rekap 5S5R Seluruh Divisi - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Rekap 5S5R Seluruh Divisi"
                    description="Pelaksanaan 5S5R (Ringkas, Rapi, Resik, Rawat, Rajin) yang diinput Operasi, Pemeliharaan, PdM, dan Logistik & Gudang — terisi otomatis dan ikut tercetak di Laporan K3."
                />

                <Card className="flex flex-row flex-wrap items-end gap-3 p-3 py-3">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(v) => visit({ unit_id: Number(v) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </Card>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <SummaryTile
                        icon={ClipboardCheck}
                        label="Divisi sudah input"
                        value={`${summary.filled} / ${summary.divisions}`}
                    />
                    <SummaryTile
                        icon={Sparkles}
                        label="Rencana"
                        value={String(summary.rencana)}
                    />
                    <SummaryTile
                        icon={CheckCircle2}
                        label="Realisasi"
                        value={String(summary.realisasi)}
                    />
                    <SummaryTile
                        icon={CheckCircle2}
                        label="Kinerja"
                        value={summary.kinerja}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    {programs.map((program) => (
                        <Card
                            key={program.key}
                            className="gap-0 overflow-hidden p-0 py-0 shadow-xs"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border bg-muted/20 p-3">
                                <div>
                                    <div className="text-sm font-semibold text-foreground">
                                        {program.sumber}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {program.judul} — {monthName}{' '}
                                        {filters.year}
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {program.saved ? (
                                        <Badge variant="secondary">
                                            Kinerja {program.total.kinerja}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Belum diinput
                                        </Badge>
                                    )}
                                    {program.eviden > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="gap-1"
                                        >
                                            <Image className="size-3" />
                                            {program.eviden} eviden
                                        </Badge>
                                    )}
                                    {program.url && (
                                        <Link
                                            href={program.url}
                                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                        >
                                            <ExternalLink className="size-3.5" />
                                            Buka
                                        </Link>
                                    )}
                                </div>
                            </div>

                            {program.saved ? (
                                <>
                                    <table className="w-full border-collapse text-xs">
                                        <thead className="bg-muted/40 text-left text-[11px] text-muted-foreground uppercase">
                                            <tr>
                                                <th className="border-b border-border px-3 py-2">
                                                    Program
                                                </th>
                                                <th className="border-b border-border px-3 py-2 text-center">
                                                    Rencana
                                                </th>
                                                <th className="border-b border-border px-3 py-2 text-center">
                                                    Realisasi
                                                </th>
                                                <th className="border-b border-border px-3 py-2 text-center">
                                                    Kinerja
                                                </th>
                                                <th className="border-b border-border px-3 py-2">
                                                    Catatan
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {program.rows.map((row) => (
                                                <tr key={row.program}>
                                                    <td className="border-b border-border px-3 py-2 font-medium">
                                                        {row.program}
                                                    </td>
                                                    <td className="border-b border-border px-3 py-2 text-center tabular-nums">
                                                        {row.rencana}
                                                    </td>
                                                    <td className="border-b border-border px-3 py-2 text-center tabular-nums">
                                                        {row.realisasi}
                                                    </td>
                                                    <td className="border-b border-border px-3 py-2 text-center tabular-nums">
                                                        {row.kinerja}
                                                    </td>
                                                    <td className="border-b border-border px-3 py-2 text-muted-foreground">
                                                        {row.catatan ?? '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="font-semibold">
                                                <td className="px-3 py-2">
                                                    Total
                                                </td>
                                                <td className="px-3 py-2 text-center tabular-nums">
                                                    {program.total.rencana}
                                                </td>
                                                <td className="px-3 py-2 text-center tabular-nums">
                                                    {program.total.realisasi}
                                                </td>
                                                <td className="px-3 py-2 text-center tabular-nums">
                                                    {program.total.kinerja}
                                                </td>
                                                <td className="px-3 py-2" />
                                            </tr>
                                        </tbody>
                                    </table>
                                    {program.temuan.length > 0 && (
                                        <div className="border-t border-border p-3 text-xs">
                                            <div className="mb-1 font-semibold text-foreground">
                                                Temuan / catatan
                                            </div>
                                            <ul className="list-disc space-y-0.5 pl-4 text-muted-foreground">
                                                {program.temuan.map(
                                                    (text, index) => (
                                                        <li key={index}>
                                                            {text}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <p className="p-4 text-center text-xs text-muted-foreground">
                                    {program.sumber} belum menginput 5S5R pada
                                    periode ini.
                                </p>
                            )}
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

function SummaryTile({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof CheckCircle2;
    label: string;
    value: string;
}) {
    return (
        <Card className="flex flex-row items-center gap-3 p-3 py-3">
            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-5" />
            </div>
            <div>
                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                    {label}
                </div>
                <div className="text-base font-bold text-foreground">
                    {value}
                </div>
            </div>
        </Card>
    );
}

K3Rekap5s5rPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Rekap 5S5R Seluruh Divisi', href: k3Rekap5s5r.index() },
    ],
};
