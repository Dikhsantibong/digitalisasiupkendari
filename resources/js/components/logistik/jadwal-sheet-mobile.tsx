import {
    ChevronDown,
    Download,
    FileSpreadsheet,
    ImagePlus,
    Plus,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import { MobileModeBar } from '@/components/mobile/mode-bar';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    dailyProgress,
    jadwalProgress,
    maturityLevel,
    patrolSummary,
    rekapAbsensi,
} from '@/lib/logistik-jadwal-excel';
import type {
    JadwalColumn,
    JadwalRow,
    JadwalSection,
    JadwalSheetDef,
} from '@/lib/logistik-jadwal-excel';
import { cn } from '@/lib/utils';
import type { IdName } from '@/types';

type Filters = { unit_id: number; month: number; year: number };

export type LogistikSheetMobileProps = {
    sheet: JadwalSheetDef;
    sections: JadwalSection[];
    codes: Record<string, string>;
    columns: JadwalColumn[];
    rows: JadwalRow[];
    unit: IdName;
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    periodLabel: string;
    hasSaved: boolean;
    canWrite: boolean;
    dirty: boolean;
    saving: boolean;
    exporting: boolean;
    uploads: Record<number, File[]>;
    onVisit: (patch: Partial<Filters>) => void;
    onSetCode: (index: number, col: number, code: string) => void;
    onUpdateRow: (index: number, patch: Partial<JadwalRow>) => void;
    onAddRow: (section?: string | null) => void;
    onRemoveRow: (index: number) => void;
    onAddPhotos: (index: number, files: FileList | null) => void;
    onRemoveKeptPhoto: (index: number, photo: number) => void;
    onRemoveNewPhoto: (index: number, photo: number) => void;
    onSave: () => void;
    onExportExcel: () => void;
    pdfUrl: string;
};

/** Rows rendered per group before "Tampilkan lagi". */
const PAGE_SIZE = 12;

const RD_TONE = (code: string) =>
    code === 'D'
        ? 'border-emerald-600 bg-emerald-500 text-black'
        : 'border-sky-600 bg-sky-500 text-white';
const PATROL_TONE = (code: string) =>
    code === 'T'
        ? 'border-rose-600 bg-rose-500 text-white'
        : 'border-emerald-600 bg-emerald-500 text-black';
const SHIFT_TONE = (code: string) =>
    code === 'OF'
        ? 'border-amber-500 bg-amber-400 text-red-800'
        : ['S', 'I', 'C', 'M'].includes(code)
          ? 'border-rose-500 bg-rose-500 text-white'
          : 'border-emerald-600 bg-emerald-500 text-black';

/**
 * Phone layout of a Logistik & Gudang grid sheet (every layout of
 * App\Support\LogistikJadwal): pick a date (or month / level) and set each
 * row's code for it; row fields, eviden photos and rows are edited per card.
 * Jadwal sheets open read-only ("Ubah Jadwal" to edit), input sheets open for
 * input. State lives in LogistikJadwalSheetPage; this only renders & calls back.
 */
export function LogistikSheetMobile(props: LogistikSheetMobileProps) {
    const {
        sheet,
        sections,
        codes,
        columns,
        rows,
        filters,
        options,
        periodLabel,
        hasSaved,
        canWrite,
        dirty,
        saving,
        exporting,
        uploads,
    } = props;
    const layout = sheet.layout;
    const [editing, setEditing] = useState(sheet.menu === 'input');
    const [openRow, setOpenRow] = useState<number | null>(null);
    // Rows render in batches per group so long sheets stay light on a phone.
    const [limits, setLimits] = useState<Record<string, number>>({});
    // Sectioned sheets show one section at a time (the first by default).
    const [openGroup, setOpenGroup] = useState<string | null>(null);

    /** Adds a row and shows the whole group so the new row is visible. */
    const addRow = (section?: string | null) => {
        setLimits((current) => ({
            ...current,
            [section ?? 'all']: Number.MAX_SAFE_INTEGER,
        }));
        setOpenGroup(section ?? null);
        props.onAddRow(section);
    };
    const [selected, setSelected] = useState(() => {
        const today = new Date();
        const current = sheet.yearly
            ? today.getMonth() + 1
            : today.getFullYear() === filters.year &&
                today.getMonth() + 1 === filters.month
              ? today.getDate()
              : 1;

        return String(
            columns.some((c) => c.col === current)
                ? current
                : (columns[0]?.col ?? 1),
        );
    });

    const writable = canWrite && editing;
    const column =
        columns.find((c) => String(c.col) === selected) ?? columns[0];
    const code = (row: JadwalRow) =>
        column ? (row.days[String(column.col)] ?? '') : '';

    /** The codes the row can take for one date, per layout. */
    const choice = (): {
        options: string[];
        labels: Record<string, string>;
        tone: (code: string) => string;
    } => {
        switch (layout) {
            case 'shift':
                return {
                    options: Object.keys(codes),
                    labels: Object.fromEntries(
                        Object.entries(codes).map(([key, label]) => [
                            key,
                            `${key} · ${label}`,
                        ]),
                    ),
                    tone: SHIFT_TONE,
                };
            case 'patrol':
                return {
                    options: ['N', 'T'],
                    labels: {
                        N: `N · ${codes.N ?? 'Normal'}`,
                        T: `T · ${codes.T ?? 'Tidak normal'}`,
                    },
                    tone: PATROL_TONE,
                };
            case 'aplikasi':
                return {
                    options: ['D'],
                    labels: { D: '✓ Sudah diinput' },
                    tone: RD_TONE,
                };
            case 'checklist':
                return {
                    options: ['D'],
                    labels: { D: '✓ Dilaksanakan' },
                    tone: RD_TONE,
                };
            default:
                return {
                    options: ['R', 'D'],
                    labels: { R: '1 · Rencana', D: '✓ Realisasi' },
                    tone: RD_TONE,
                };
        }
    };

    const summary = (row: JadwalRow): string => {
        switch (layout) {
            case 'shift': {
                const r = rekapAbsensi(row);

                return `P ${r.P} · S ${r.S} · I ${r.I} · C ${r.C} · M ${r.M} · Hadir ${r.kehadiran}`;
            }

            case 'patrol': {
                const r = patrolSummary(row, columns.length);

                return `N ${r.normal} · T ${r.tidak_normal} · Rencana ${r.rencana} · Hasil ${r.hasil}`;
            }

            case 'aplikasi':
            case 'checklist': {
                const r = dailyProgress(row, columns.length);

                return `Target ${r.target} · Realisasi ${r.realisasi} · Kinerja ${r.kinerja}`;
            }

            case 'maturity': {
                const level = maturityLevel(row);

                return level === null ? 'Belum dinilai' : `Level ${level}`;
            }

            default: {
                const r = jadwalProgress(row);

                return `Target ${r.target} · Rencana ${r.rencana} · Realisasi ${r.realisasi} · Kinerja ${r.kinerja}`;
            }
        }
    };

    const sectioned =
        sections.length > 0 &&
        ['kegiatan', 'checklist', 'maturity'].includes(layout);
    const groups: {
        key: string | null;
        title: string | null;
        indexes: number[];
    }[] = sectioned
        ? sections.map((s) => ({
              key: s.key,
              title: `${s.number}. ${s.title}`,
              indexes: rows
                  .map((r, i) =>
                      (r.section ??
                          (layout === 'kegiatan' ? 'non-rutin' : '')) === s.key
                          ? i
                          : -1,
                  )
                  .filter((i) => i >= 0),
          }))
        : [{ key: null, title: null, indexes: rows.map((_, i) => i) }];
    const hasPic = layout === 'shift' || layout === 'ik';
    const hasTarget = [
        'kegiatan',
        'pelaksana',
        'patrol',
        'aplikasi',
        'checklist',
    ].includes(layout);
    const canAdd = writable && !['checklist', 'maturity'].includes(layout);

    const rowCard = (index: number, number: number) => {
        const row = rows[index];
        const current = code(row);
        const { options: choices, labels, tone } = choice();
        const open = openRow === index;
        const pending = uploads[index] ?? [];
        const room = sheet.evidence - row.evidence.length - pending.length;

        return (
            <div
                key={index}
                className={cn(
                    'flex flex-col gap-2.5 rounded-xl border bg-card p-3',
                    current && !writable
                        ? 'border-primary/40'
                        : 'border-border',
                )}
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-[14px] leading-snug font-semibold text-foreground">
                            <span className="mr-1 text-muted-foreground">
                                {number}.
                            </span>
                            {row.nama ||
                                (writable ? 'Baris baru — isi nama' : '—')}
                        </p>
                        {hasPic && row.pic && (
                            <p className="text-[11.5px] text-muted-foreground">
                                PIC: {row.pic}
                            </p>
                        )}
                        <p className="text-[11.5px] text-muted-foreground">
                            {summary(row)}
                        </p>
                    </div>
                    {layout !== 'maturity' && !writable && (
                        <span
                            className={cn(
                                'inline-flex h-8 min-w-12 shrink-0 items-center justify-center rounded-lg border px-2 text-[12px] font-bold',
                                current
                                    ? tone(current)
                                    : 'border-border text-muted-foreground',
                            )}
                        >
                            {current
                                ? (labels[current]?.split(' · ')[0] ?? current)
                                : '—'}
                        </span>
                    )}
                </div>

                {layout === 'maturity' ? (
                    <div className="grid grid-cols-6 gap-1.5">
                        {columns.map((c) => {
                            const active = maturityLevel(row) === c.col;

                            return (
                                <button
                                    key={c.col}
                                    type="button"
                                    disabled={!writable}
                                    onClick={() =>
                                        props.onUpdateRow(index, {
                                            days: active
                                                ? {}
                                                : { [String(c.col)]: 'L' },
                                        })
                                    }
                                    className={cn(
                                        'h-10 rounded-lg border text-[14px] font-bold transition active:scale-95 disabled:opacity-70',
                                        active
                                            ? 'border-amber-500 bg-[#ffd966] text-slate-900'
                                            : 'border-border bg-background',
                                    )}
                                    aria-label={`Level ${c.col}`}
                                >
                                    {c.col}
                                </button>
                            );
                        })}
                    </div>
                ) : (
                    writable &&
                    column && (
                        <ChoiceChips
                            options={choices}
                            value={current}
                            onChange={(next) =>
                                props.onSetCode(index, column.col, next)
                            }
                            labels={labels}
                            tone={tone}
                        />
                    )
                )}

                {layout === 'checklist' &&
                    (row.evidence_urls.length > 0 ||
                        pending.length > 0 ||
                        writable) && (
                        <div className="flex flex-wrap gap-2">
                            {row.evidence_urls.map((url, photo) => (
                                <span key={url} className="relative">
                                    <img
                                        src={url}
                                        alt="Eviden"
                                        className="size-16 rounded-md object-cover"
                                    />
                                    {writable && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                props.onRemoveKeptPhoto(
                                                    index,
                                                    photo,
                                                )
                                            }
                                            className="absolute -top-1.5 -right-1.5 rounded-full bg-destructive p-0.5 text-white"
                                            aria-label="Hapus foto"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    )}
                                </span>
                            ))}
                            {pending.map((file, photo) => (
                                <span
                                    key={`${file.name}-${photo}`}
                                    className="relative"
                                >
                                    <img
                                        src={URL.createObjectURL(file)}
                                        alt={file.name}
                                        className="size-16 rounded-md object-cover ring-2 ring-amber-400"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            props.onRemoveNewPhoto(index, photo)
                                        }
                                        className="absolute -top-1.5 -right-1.5 rounded-full bg-destructive p-0.5 text-white"
                                        aria-label="Batalkan foto"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            ))}
                            {writable && room > 0 && (
                                <label className="flex size-16 cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-input text-[10px] text-muted-foreground">
                                    <ImagePlus className="size-4" />
                                    Foto ({room})
                                    <input
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        className="sr-only"
                                        onChange={(e) => {
                                            props.onAddPhotos(
                                                index,
                                                e.target.files,
                                            );
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                        </div>
                    )}

                {writable && (
                    <>
                        <button
                            type="button"
                            onClick={() => setOpenRow(open ? null : index)}
                            className="self-start text-[12px] font-medium text-primary"
                        >
                            {open ? 'Tutup detail' : 'Ubah detail baris'}
                        </button>
                        {open && (
                            <div className="flex flex-col gap-2 rounded-lg bg-muted/40 p-2.5">
                                <Field label={sheet.row_label || 'Nama'}>
                                    <Input
                                        value={row.nama}
                                        onChange={(e) =>
                                            props.onUpdateRow(index, {
                                                nama: e.target.value,
                                            })
                                        }
                                        className="h-10"
                                    />
                                </Field>
                                {hasPic && (
                                    <Field label="PIC">
                                        <Input
                                            value={row.pic}
                                            onChange={(e) =>
                                                props.onUpdateRow(index, {
                                                    pic: e.target.value,
                                                })
                                            }
                                            className="h-10"
                                        />
                                    </Field>
                                )}
                                {hasTarget && (
                                    <Field label="Target (kosong = otomatis)">
                                        <Input
                                            type="number"
                                            inputMode="numeric"
                                            min={0}
                                            value={row.target ?? ''}
                                            onChange={(e) =>
                                                props.onUpdateRow(index, {
                                                    target:
                                                        e.target.value === ''
                                                            ? null
                                                            : Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                })
                                            }
                                            className="h-10"
                                        />
                                    </Field>
                                )}
                                {layout === 'kegiatan' && (
                                    <Field label="Keterangan">
                                        <Input
                                            value={row.keterangan}
                                            onChange={(e) =>
                                                props.onUpdateRow(index, {
                                                    keterangan: e.target.value,
                                                })
                                            }
                                            className="h-10"
                                        />
                                    </Field>
                                )}
                                {!['checklist', 'maturity'].includes(
                                    layout,
                                ) && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => props.onRemoveRow(index)}
                                        className="gap-1.5 self-start text-destructive"
                                    >
                                        <Trash2 className="size-4" />
                                        Hapus baris
                                    </Button>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        );
    };

    const visibleIndexes = (indexes: number[]) =>
        writable || layout === 'maturity' || !column
            ? indexes
            : indexes.filter(
                  (i) =>
                      code(rows[i]) !== '' ||
                      layout === 'shift' ||
                      layout === 'patrol',
              );

    return (
        <>
            <div
                className={cn(
                    'flex flex-col gap-3 p-4',
                    canWrite && (editing || dirty) && 'pb-28',
                )}
            >
                <PageHeader
                    title={sheet.title}
                    description={`${unit(props)} · ${periodLabel}`}
                />

                <div className="grid grid-cols-2 gap-2">
                    <Button
                        variant="outline"
                        onClick={() => window.open(props.pdfUrl, '_blank')}
                        className="gap-1.5"
                    >
                        <Download className="size-4 text-rose-600" />
                        PDF
                    </Button>
                    <Button
                        variant="outline"
                        onClick={props.onExportExcel}
                        disabled={exporting}
                        className="gap-1.5"
                    >
                        <FileSpreadsheet className="size-4 text-emerald-600" />
                        {exporting ? 'Menyiapkan…' : 'Excel'}
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-2 rounded-xl border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(v) => props.onVisit({ unit_id: Number(v) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                        className="col-span-2 w-full"
                    />
                    {!sheet.yearly && (
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            onChange={(v) =>
                                props.onVisit({ month: Number(v) })
                            }
                            options={OPERASI_MONTHS.map((label, i) => ({
                                value: String(i + 1),
                                label,
                            }))}
                            className="w-full"
                        />
                    )}
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => props.onVisit({ year: Number(v) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                        className={cn('w-full', sheet.yearly && 'col-span-2')}
                    />
                </div>

                {canWrite && sheet.menu === 'jadwal' && (
                    <MobileModeBar editing={editing} onChange={setEditing} />
                )}
                {!hasSaved && (
                    <p className="text-[12px] text-muted-foreground">
                        Belum ada data tersimpan untuk periode ini — isian
                        bawaan sudah disiapkan.
                    </p>
                )}

                {layout !== 'maturity' && columns.length > 0 && (
                    <>
                        <DayStrip
                            items={columns.map((c) => ({
                                key: String(c.col),
                                label: sheet.yearly ? c.label : String(c.col),
                                sub: sheet.yearly ? null : c.dow,
                                isRed: c.is_red,
                                done: rows.some((r) => !!r.days[String(c.col)]),
                            }))}
                            value={selected}
                            onChange={setSelected}
                        />
                        {column && (
                            <p className="px-0.5 text-[13px] font-semibold text-foreground">
                                {sheet.yearly
                                    ? column.label
                                    : `${column.dow}, tanggal ${column.col}`}
                                {column.is_holiday && (
                                    <span className="ml-1.5 text-red-600">
                                        · Libur
                                    </span>
                                )}
                            </p>
                        )}
                    </>
                )}

                {rows.length === 0 && (
                    <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                        Belum ada baris.
                    </p>
                )}

                {groups.map((group) => {
                    const visible = visibleIndexes(group.indexes);
                    const groupKey = group.key ?? 'all';
                    const limit = limits[groupKey] ?? PAGE_SIZE;
                    const shown = visible.slice(0, limit);
                    const collapsible = groups.length > 1;
                    const expanded =
                        !collapsible ||
                        (openGroup ?? groups[0]?.key ?? 'all') === groupKey;

                    return (
                        <div
                            key={group.key ?? 'all'}
                            className="flex flex-col gap-2"
                        >
                            {group.title && (
                                <div className="flex items-center justify-between gap-2 px-0.5">
                                    {collapsible ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpenGroup(
                                                    expanded ? '' : groupKey,
                                                )
                                            }
                                            className="flex min-w-0 flex-1 items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2.5 text-left"
                                            aria-expanded={expanded}
                                        >
                                            <span className="min-w-0 flex-1 truncate text-[12px] font-semibold tracking-wide text-foreground uppercase">
                                                {group.title}
                                            </span>
                                            <span className="shrink-0 text-[11px] text-muted-foreground">
                                                {group.indexes.length} item
                                            </span>
                                            <ChevronDown
                                                className={cn(
                                                    'size-4 shrink-0 text-muted-foreground transition',
                                                    expanded && 'rotate-180',
                                                )}
                                            />
                                        </button>
                                    ) : (
                                        <p className="text-[11.5px] font-semibold tracking-wide text-muted-foreground uppercase">
                                            {group.title}
                                        </p>
                                    )}
                                    {writable && layout === 'kegiatan' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => addRow(group.key)}
                                            className="h-7 gap-1 text-xs"
                                        >
                                            <Plus className="size-3.5" />
                                            Tambah
                                        </Button>
                                    )}
                                </div>
                            )}
                            {expanded && (
                                <>
                                    {shown.length === 0 &&
                                        group.indexes.length > 0 && (
                                            <p className="rounded-lg border border-dashed border-border p-3 text-center text-[12px] text-muted-foreground">
                                                Tidak ada jadwal pada tanggal
                                                ini.
                                            </p>
                                        )}
                                    {shown.map((index) =>
                                        rowCard(
                                            index,
                                            group.indexes.indexOf(index) + 1,
                                        ),
                                    )}
                                    {visible.length > limit && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setLimits((current) => ({
                                                    ...current,
                                                    [groupKey]:
                                                        limit + PAGE_SIZE,
                                                }))
                                            }
                                            className="w-full"
                                        >
                                            Tampilkan{' '}
                                            {Math.min(
                                                PAGE_SIZE,
                                                visible.length - limit,
                                            )}{' '}
                                            lagi ({visible.length - limit}{' '}
                                            tersisa)
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    );
                })}

                {canAdd && layout !== 'kegiatan' && (
                    <Button
                        variant="outline"
                        onClick={() => addRow()}
                        className="gap-1.5"
                    >
                        <Plus className="size-4" />
                        Tambah Baris
                    </Button>
                )}
            </div>

            {canWrite && (editing || dirty) && (
                <StickyActionBar>
                    <Button
                        onClick={props.onSave}
                        disabled={saving || !dirty}
                        className="h-11 w-full gap-1.5"
                    >
                        <Save className="size-4" />
                        {saving ? 'Menyimpan…' : dirty ? 'Simpan' : 'Tersimpan'}
                    </Button>
                </StickyActionBar>
            )}
        </>
    );
}

const unit = (props: LogistikSheetMobileProps) => props.unit.name;

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-[12px] font-medium text-muted-foreground">
            {label}
            {children}
        </label>
    );
}
