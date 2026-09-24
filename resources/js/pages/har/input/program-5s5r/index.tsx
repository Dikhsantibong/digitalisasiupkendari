import { Head, router } from '@inertiajs/react';
import { Check, Download, ImagePlus, Save, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import program5s5rRoutes from '@/routes/har/input/program-5s5r';
import type { IdName } from '@/types';

type TindakanKey = 'membersihkan' | 'merapikan' | 'membuang_sampah' | 'mengecat' | 'lainnya';

type Row = {
    minggu: number;
    program: string;
    detail: string | null;
    pic: string | null;
    kondisi_awal: string | null;
    progres: string | null;
    kondisi_akhir: string | null;
    jumlah: number | null;
    keterangan: string | null;
    saved: boolean;
} & Record<TindakanKey, boolean>;

type Week = { minggu: number; rows: Row[]; evidence: { id: number; url: string }[] };

type Props = {
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
        programs: { key: string; label: string; istilah: string; detail: string }[];
        tindakan: { key: TindakanKey; label: string }[];
        progres: string[];
        kondisi: string[];
        max_evidence: number;
    };
    weeks: Week[];
    has_saved: boolean;
    can_write: boolean;
};

const th = 'border border-slate-400 p-1';
const td = 'border border-border';
const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const cellTextarea =
    'block min-h-16 w-full resize-y rounded-none border-0 bg-transparent px-1 py-1 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

/**
 * Input Jadwal Program 5S 5R Pemeliharaan: per minggu lima program (Ringkas …
 * Rajin) dengan kondisi, tindakan, progres, jumlah, keterangan dan foto eviden
 * per minggu. Hanya baris yang diisi yang disimpan; definisi di App\Support\HarProgram5s5r.
 */
export default function HarProgram5s5rInput({ unit, filters, options, weeks: initialWeeks, has_saved, can_write }: Props) {
    const [weeks, setWeeks] = useState<Week[]>(initialWeeks);
    // New photos per minggu, uploaded on Simpan.
    const [uploads, setUploads] = useState<Record<number, File[]>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const programs = Object.fromEntries(options.programs.map((p) => [p.key, p]));
    const filled = weeks.flatMap((w) => w.rows).filter((r) => r.saved).length;

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(program5s5rRoutes.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const setRow = (minggu: number, program: string, patch: Partial<Row>) => {
        setWeeks((current) =>
            current.map((week) =>
                week.minggu !== minggu ? week : { ...week, rows: week.rows.map((row) => (row.program === program ? { ...row, ...patch } : row)) },
            ),
        );
        setDirty(true);
    };

    const removeSavedPhoto = (minggu: number, id: number) => {
        setWeeks((current) => current.map((week) => (week.minggu === minggu ? { ...week, evidence: week.evidence.filter((p) => p.id !== id) } : week)));
        setDirty(true);
    };

    const addPhotos = (minggu: number, files: FileList | null, room: number) => {
        if (!files) {
            return;
        }

        setUploads((current) => ({ ...current, [minggu]: [...(current[minggu] ?? []), ...Array.from(files)].slice(0, room) }));
        setDirty(true);
    };

    const removeUpload = (minggu: number, index: number) => {
        setUploads((current) => ({ ...current, [minggu]: (current[minggu] ?? []).filter((_, i) => i !== index) }));
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            program5s5rRoutes.store().url,
            {
                ...filters,
                rows: weeks.flatMap((week) =>
                    week.rows.map((row) => {
                        const { saved, ...values } = row; // eslint-disable-line @typescript-eslint/no-unused-vars

                        return values;
                    }),
                ),
                keep_evidence: Object.fromEntries(weeks.map((week) => [week.minggu, week.evidence.map((p) => p.id)])),
                evidence: uploads,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setWeeks((page.props as unknown as Props).weeks);
                    setUploads({});
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const pdfUrl = (download: boolean) => program5s5rRoutes.pdf({ query: { ...filters, ...(download ? { download: 1 } : {}) } }).url;

    return (
        <>
            <Head title={`Jadwal Program 5S 5R Pemeliharaan - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Program 5S 5R Pemeliharaan"
                    description={`Pelaksanaan Ringkas, Rapi, Resik, Rawat, Rajin per minggu — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(harInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={() => window.open(pdfUrl(false), '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-end gap-3">
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
                    <div className="flex items-center gap-2 text-[13px]">
                        {dirty ? (
                            <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                        ) : has_saved ? (
                            <StatusBadge tone="success">{filled} program terisi</StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">Belum ada data</StatusBadge>
                        )}
                    </div>
                </div>

                <div className="rounded-md border border-border bg-muted/10 p-4 text-center">
                    <div className="text-xs font-bold tracking-wider text-muted-foreground uppercase">Jasa Pendukung Teknik 6 Site UP Kendari</div>
                    <div className="text-sm font-semibold text-foreground uppercase">{unit.name}</div>
                    <div className="text-xs text-muted-foreground uppercase">Laporan Project</div>
                    <div className="mt-0.5 text-base font-bold tracking-tight text-foreground uppercase">Jadwal Program 5S 5R Pemeliharaan</div>
                    <div className="mt-1 text-xs text-muted-foreground">Periode {periodLabel}</div>
                </div>

                <p className="text-[13px] text-muted-foreground">
                    Isi kondisi, centang tindakan, pilih progres, lalu unggah foto eviden per minggu. Baris yang tidak diisi tidak disimpan dan tidak dicetak sebagai hasil.
                </p>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1400px] border-collapse text-xs">
                        <thead className="bg-slate-100 text-center text-[11px] font-semibold text-slate-800 dark:bg-slate-900 dark:text-slate-200">
                            <tr>
                                <th className={th} colSpan={3}>Program Kerja 5S 5R</th>
                                <th className={`${th} w-32`} rowSpan={2}>PIC</th>
                                <th className={`${th} w-28`} rowSpan={2}>Kondisi Awal</th>
                                <th className={th} colSpan={options.tindakan.length}>Tindakan</th>
                                <th className={`${th} w-28`} rowSpan={2}>Progres</th>
                                <th className={`${th} w-28`} rowSpan={2}>Kondisi Akhir</th>
                                <th className={`${th} w-16`} rowSpan={2}>Jumlah</th>
                                <th className={`${th} w-40`} rowSpan={2}>Keterangan</th>
                                <th className={`${th} w-48`} rowSpan={2}>Eviden</th>
                            </tr>
                            <tr>
                                <th className={`${th} w-16`}>Periode</th>
                                <th className={`${th} w-24`}>Uraian</th>
                                <th className={`${th} min-w-56`}>Detail</th>
                                {options.tindakan.map((t) => (
                                    <th key={t.key} className={`${th} w-20`}>{t.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {weeks.map((week) => {
                                const pending = uploads[week.minggu] ?? [];
                                const room = options.max_evidence - week.evidence.length;

                                return (
                                    <Fragment key={week.minggu}>
                                        {week.rows.map((row, index) => (
                                            <tr key={row.program} className="align-middle hover:bg-muted/30">
                                                {index === 0 && (
                                                    <td className={`${td} bg-muted/30 text-center font-semibold`} rowSpan={week.rows.length}>
                                                        Minggu ke {week.minggu}
                                                    </td>
                                                )}
                                                <td className={`${td} px-1 text-center`}>
                                                    <div className="font-medium">{programs[row.program]?.label}</div>
                                                    <div className="text-[11px] text-muted-foreground italic">({programs[row.program]?.istilah})</div>
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <textarea
                                                        value={row.detail ?? ''}
                                                        onChange={(e) => setRow(week.minggu, row.program, { detail: e.target.value })}
                                                        className={cellTextarea}
                                                        disabled={!can_write}
                                                        aria-label={`Detail ${programs[row.program]?.label} minggu ke ${week.minggu}`}
                                                    />
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <textarea
                                                        value={row.pic ?? ''}
                                                        onChange={(e) => setRow(week.minggu, row.program, { pic: e.target.value })}
                                                        className={`${cellTextarea} text-center`}
                                                        disabled={!can_write}
                                                        aria-label={`PIC ${programs[row.program]?.label} minggu ke ${week.minggu}`}
                                                    />
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <PdmCellSelect value={row.kondisi_awal ?? ''} onChange={(v) => setRow(week.minggu, row.program, { kondisi_awal: v || null })} options={options.kondisi} className="justify-center" disabled={!can_write} />
                                                </td>
                                                {options.tindakan.map((t) => (
                                                    <td key={t.key} className={`${td} text-center`}>
                                                        <button
                                                            type="button"
                                                            role="checkbox"
                                                            aria-checked={row[t.key]}
                                                            aria-label={`${t.label} ${programs[row.program]?.label} minggu ke ${week.minggu}`}
                                                            onClick={() => setRow(week.minggu, row.program, { [t.key]: !row[t.key] })}
                                                            disabled={!can_write}
                                                            className={`mx-auto flex size-5 items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                                                                row[t.key] ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background hover:border-primary'
                                                            }`}
                                                        >
                                                            {row[t.key] && <Check className="size-3.5" />}
                                                        </button>
                                                    </td>
                                                ))}
                                                <td className={`${td} p-0`}>
                                                    <PdmCellSelect
                                                        value={row.progres ?? ''}
                                                        onChange={(v) => setRow(week.minggu, row.program, { progres: v || null })}
                                                        options={options.progres}
                                                        className="justify-center"
                                                        disabled={!can_write}
                                                    />
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <PdmCellSelect value={row.kondisi_akhir ?? ''} onChange={(v) => setRow(week.minggu, row.program, { kondisi_akhir: v || null })} options={options.kondisi} className="justify-center" disabled={!can_write} />
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        value={row.jumlah ?? ''}
                                                        onChange={(e) => setRow(week.minggu, row.program, { jumlah: e.target.value === '' ? null : Number(e.target.value) })}
                                                        className={`${cellInput} text-center`}
                                                        disabled={!can_write}
                                                        aria-label={`Jumlah ${programs[row.program]?.label} minggu ke ${week.minggu}`}
                                                    />
                                                </td>
                                                <td className={`${td} p-0`}>
                                                    <textarea
                                                        value={row.keterangan ?? ''}
                                                        onChange={(e) => setRow(week.minggu, row.program, { keterangan: e.target.value })}
                                                        className={cellTextarea}
                                                        disabled={!can_write}
                                                        aria-label={`Keterangan ${programs[row.program]?.label} minggu ke ${week.minggu}`}
                                                    />
                                                </td>
                                                {index === 0 && (
                                                    <td className={`${td} p-2 align-top`} rowSpan={week.rows.length}>
                                                        <div className="flex flex-col items-center gap-2">
                                                            {week.evidence.map((photo) => (
                                                                <span key={photo.id} className="relative">
                                                                    <img src={photo.url} alt={`Eviden minggu ke ${week.minggu}`} className="h-24 w-36 rounded object-cover" />
                                                                    {can_write && (
                                                                        <button type="button" onClick={() => removeSavedPhoto(week.minggu, photo.id)} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Hapus foto">
                                                                            <X className="size-3" />
                                                                        </button>
                                                                    )}
                                                                </span>
                                                            ))}
                                                            {pending.map((file, i) => (
                                                                <span key={`${file.name}-${i}`} className="relative">
                                                                    <img src={URL.createObjectURL(file)} alt={file.name} className="h-24 w-36 rounded object-cover ring-2 ring-amber-400" />
                                                                    <button type="button" onClick={() => removeUpload(week.minggu, i)} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Batalkan foto">
                                                                        <X className="size-3" />
                                                                    </button>
                                                                </span>
                                                            ))}
                                                            {can_write && room - pending.length > 0 && (
                                                                <label className="flex cursor-pointer items-center gap-1 rounded border border-dashed border-input px-2 py-1.5 text-[11px] text-muted-foreground hover:border-primary hover:text-primary">
                                                                    <ImagePlus className="size-3.5" />
                                                                    Tambah foto ({room - pending.length})
                                                                    <input
                                                                        type="file"
                                                                        accept="image/*"
                                                                        multiple
                                                                        className="sr-only"
                                                                        onChange={(e) => {
                                                                            addPhotos(week.minggu, e.target.files, room);
                                                                            e.target.value = '';
                                                                        }}
                                                                    />
                                                                </label>
                                                            )}
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

HarProgram5s5rInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Jadwal Program 5S 5R', href: program5s5rRoutes.index() },
    ],
};
