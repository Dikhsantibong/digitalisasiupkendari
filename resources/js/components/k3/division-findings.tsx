import { Link } from '@inertiajs/react';
import { Building2, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';

/** A finding reported by another division (App\Services\K3\K3DivisionFeed::unsafeFindings). */
export type DivisionFinding = {
    sumber: string;
    sumber_key: string;
    periode: string | null;
    kategori: string;
    temuan: string;
    kondisi: string | null;
    lokasi: string | null;
    tindak_lanjut: string | null;
    rekomendasi: string | null;
    status: 'Open' | 'Close' | null;
    keterangan: string | null;
    foto_sebelum: string | null;
    foto_sesudah: string | null;
    url: string | null;
};

export type DivisionSummary = {
    total: number;
    unsafe_action: number;
    unsafe_condition: number;
    open: number;
    closed: number;
};

/**
 * Unsafe Action & Unsafe Condition findings of Operasi, Pemeliharaan and
 * Logistik & Gudang for the period. Read-only: each division edits its own
 * page; here K3 sees them all, and they are printed in the Laporan K3.
 */
export function DivisionFindings({
    findings,
    summary,
    onPreview,
}: {
    findings: DivisionFinding[];
    summary: DivisionSummary;
    onPreview: (photo: { url: string; title: string }) => void;
}) {
    return (
        <Card className="gap-0 overflow-hidden p-0 py-0 shadow-xs">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-muted/20 p-4">
                <div className="flex items-start gap-2">
                    <Building2 className="mt-0.5 size-4 shrink-0 text-primary" />
                    <div>
                        <div className="text-sm font-semibold text-foreground">
                            Temuan dari Divisi Lain (otomatis)
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Laporan Unsafe Action &amp; Unsafe Condition yang
                            diinput Operasi, Pemeliharaan, dan Logistik &amp;
                            Gudang. Hanya-baca di sini; ubah dari halaman divisi
                            asalnya. Ikut tercetak di Laporan K3.
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-1.5 text-[11px]">
                    <Badge variant="secondary">{summary.total} temuan</Badge>
                    <Badge variant="secondary">
                        {summary.unsafe_action} Unsafe Action
                    </Badge>
                    <Badge variant="secondary">
                        {summary.unsafe_condition} Unsafe Condition
                    </Badge>
                    <Badge variant="secondary">
                        {summary.closed} Close / {summary.open} Open
                    </Badge>
                </div>
            </div>

            {findings.length === 0 ? (
                <p className="p-4 text-center text-xs text-muted-foreground">
                    Belum ada temuan dari divisi lain pada periode ini.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[960px] border-collapse text-xs">
                        <thead className="bg-muted/40 text-left text-[11px] text-muted-foreground uppercase">
                            <tr>
                                <th className="border-b border-border px-2 py-2 text-center">
                                    No
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Sumber
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Periode
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Kategori
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Temuan
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Lokasi
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Tindak Lanjut
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Status
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Eviden
                                </th>
                                <th className="border-b border-border px-2 py-2">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {findings.map((finding, index) => (
                                <tr
                                    key={`${finding.sumber_key}-${index}`}
                                    className="align-top"
                                >
                                    <td className="border-b border-border px-2 py-2 text-center">
                                        {index + 1}
                                    </td>
                                    <td className="border-b border-border px-2 py-2 font-medium">
                                        {finding.sumber}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.periode ?? '-'}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.kategori}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.temuan}
                                        {finding.kondisi && (
                                            <span className="block text-muted-foreground">
                                                Kondisi: {finding.kondisi}
                                            </span>
                                        )}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.lokasi ?? '-'}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.tindak_lanjut ?? '-'}
                                        {finding.rekomendasi && (
                                            <span className="block text-muted-foreground">
                                                Rekomendasi:{' '}
                                                {finding.rekomendasi}
                                            </span>
                                        )}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.status ? (
                                            <Badge
                                                variant={
                                                    finding.status === 'Close'
                                                        ? 'secondary'
                                                        : 'destructive'
                                                }
                                            >
                                                {finding.status}
                                            </Badge>
                                        ) : (
                                            (finding.keterangan ?? '-')
                                        )}
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        <div className="flex gap-1">
                                            {(
                                                [
                                                    [
                                                        'Sebelum',
                                                        finding.foto_sebelum,
                                                    ],
                                                    [
                                                        'Sesudah',
                                                        finding.foto_sesudah,
                                                    ],
                                                ] as const
                                            ).map(([label, url]) =>
                                                url ? (
                                                    <button
                                                        key={label}
                                                        type="button"
                                                        onClick={() =>
                                                            onPreview({
                                                                url,
                                                                title: `Eviden ${label.toLowerCase()} — ${finding.sumber}`,
                                                            })
                                                        }
                                                        className="overflow-hidden rounded border border-border"
                                                        title={`Eviden ${label.toLowerCase()}`}
                                                    >
                                                        <img
                                                            src={url}
                                                            alt={`Eviden ${label.toLowerCase()}`}
                                                            className="size-10 object-cover"
                                                        />
                                                    </button>
                                                ) : null,
                                            )}
                                            {!finding.foto_sebelum &&
                                                !finding.foto_sesudah &&
                                                '-'}
                                        </div>
                                    </td>
                                    <td className="border-b border-border px-2 py-2">
                                        {finding.url ? (
                                            <Link
                                                href={finding.url}
                                                className="inline-flex items-center gap-1 text-primary hover:underline"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Buka
                                            </Link>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
