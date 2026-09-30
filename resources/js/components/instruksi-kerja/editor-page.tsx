import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    FileDown,
    FilePlus2,
    FileText,
    Info,
    PencilLine,
    Plus,
    Printer,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import type { IdName } from '@/types';

type Style = 'angka' | 'huruf' | 'butir' | 'panah' | 'strip' | 'paragraf';

type Point = { teks: string; sub: string[] };

type ServerSection = {
    judul: string;
    bernomor: boolean;
    gaya: Style;
    lanjut: boolean;
    pengantar: string;
    butir: Point[];
};

type Signatories = {
    dibuat_jabatan: string;
    dibuat_nama: string;
    disetujui_jabatan: string;
    disetujui_nama: string;
};

type ServerDoc = Signatories & {
    id: number;
    kop: string;
    judul: string;
    mesin: string;
    no_dokumen: string;
    tanggal: string;
    revisi: string;
    sections: ServerSection[];
    updated_at: string | null;
};

type Template = {
    key: string;
    nama: string;
    deskripsi: string;
    judul: string;
    mesin: string;
    sections: ServerSection[];
};

/** On screen the points of a part are one text: a line per point, "- …" lines are sub-points. */
type Section = Omit<ServerSection, 'butir'> & { key: number; teks: string };

type Draft = Omit<ServerDoc, 'id' | 'sections' | 'updated_at'> & {
    id: number | null;
    sections: Section[];
};

/** Module routes of the IK input (Wayfinder URLs). */
export type InstruksiKerjaRoutes = {
    index: string;
    store: string;
    pdf: (query: { unit_id: number; doc?: number }) => string;
    destroy: (id: number) => string;
};

/** Module texts of the IK input. */
export type InstruksiKerjaCopy = {
    /** Browser tab title, e.g. "Instruksi Kerja Pemeliharaan". */
    headTitle: string;
    title: string;
    description: string;
    judulPlaceholder: string;
    mesinPlaceholder: string;
};

export type InstruksiKerjaPageProps = {
    unit: IdName;
    filters: { unit_id: number };
    options: { units: IdName[] };
    docs: ServerDoc[];
    selected_id: number | null;
    templates: Template[];
    signatories: Signatories;
    can_write: boolean;
};

const DEFAULT_KOP = 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI';

const STYLES: { value: Style; label: string }[] = [
    { value: 'angka', label: '1. 2. 3.' },
    { value: 'huruf', label: 'a. b. c.' },
    { value: 'butir', label: '• Butir' },
    { value: 'panah', label: '➢ Panah' },
    { value: 'strip', label: '– Strip' },
    { value: 'paragraf', label: '¶ Paragraf' },
];

const QUICK_SECTIONS: { judul: string; bernomor: boolean; gaya: Style }[] = [
    { judul: 'ALAT', bernomor: true, gaya: 'angka' },
    { judul: 'PELAKSANA', bernomor: true, gaya: 'paragraf' },
    { judul: 'LANGKAH PELAKSANAAN', bernomor: true, gaya: 'angka' },
    {
        judul: 'PERSIAPAN AWAL SEBELUM PEMELIHARAAN',
        bernomor: false,
        gaya: 'angka',
    },
    { judul: 'PROSEDUR PEKERJAAN', bernomor: false, gaya: 'panah' },
    { judul: 'PEKERJAAN FINISHING', bernomor: false, gaya: 'angka' },
];

let nextKey = 1;

const toText = (points: Point[]): string =>
    points
        .map((p) => [p.teks, ...p.sub.map((s) => `  - ${s}`)].join('\n'))
        .join('\n');

/** A line starting with "-" (or indented) is a sub-point of the point above it. */
const parse = (text: string): Point[] => {
    const points: Point[] = [];

    for (const raw of text.split('\n')) {
        if (!raw.trim()) {
            continue;
        }

        const isSub = /^\s+\S/.test(raw) || /^\s*-/.test(raw);
        const clean = raw
            .replace(/^\s*(?:-+\.?|[•➢*]|\d+[.)]|[a-z]\.)\s*/i, '')
            .trim();

        if (!clean) {
            continue;
        }

        if (isSub && points.length > 0) {
            points[points.length - 1].sub.push(clean);
        } else {
            points.push({ teks: clean, sub: [] });
        }
    }

    return points;
};

const toSection = (s: ServerSection): Section => ({
    key: nextKey++,
    judul: s.judul,
    bernomor: s.bernomor,
    gaya: s.gaya,
    lanjut: s.lanjut,
    pengantar: s.pengantar ?? '',
    teks: toText(s.butir ?? []),
});

const toDraft = (doc: ServerDoc): Draft => ({
    ...doc,
    sections: doc.sections.map(toSection),
});

const numbered = (gaya: Style) => gaya === 'angka' || gaya === 'huruf';

const marker = (gaya: Style, n: number): string =>
    ({
        angka: `${n}.`,
        huruf: `${String.fromCharCode(96 + (((n - 1) % 26) + 1))}.`,
        butir: '•',
        panah: '➢',
        strip: '–',
        paragraf: '',
    })[gaya];

/** "**tebal**" → bold. */
function Rich({ text }: { text: string }) {
    return (
        <>
            {text
                .split(/(\*\*[^*]+\*\*)/g)
                .map((part, i) =>
                    part.startsWith('**') &&
                    part.endsWith('**') &&
                    part.length > 4 ? (
                        <strong key={i}>{part.slice(2, -2)}</strong>
                    ) : (
                        <Fragment key={i}>{part}</Fragment>
                    ),
                )}
        </>
    );
}

/**
 * Shared Instruksi Kerja (IK) input of a module (Pemeliharaan, Operasi …): the
 * unit's IK library. Start from a template, adjust everything (numbered parts,
 * bold sub-headings, point style, sub-points, continued numbering, signatures)
 * and check the printed form live. Printed as A4 PDF in the MKP layout
 * (resources/views/har/instruksi-kerja/document.blade.php — keep IkPreview in
 * sync). Each module page (e.g. pages/har/input/instruksi-kerja/index.tsx)
 * only passes its routes and texts.
 */
export function InstruksiKerjaEditorPage({
    unit,
    filters,
    options,
    docs,
    selected_id,
    templates,
    signatories,
    can_write,
    routes,
    copy,
}: InstruksiKerjaPageProps & {
    routes: InstruksiKerjaRoutes;
    copy: InstruksiKerjaCopy;
}) {
    const compact = useCompactLayout();
    const initialDoc =
        docs.find((d) => d.id === selected_id) ?? docs[0] ?? null;
    const [draft, setDraft] = useState<Draft | null>(() =>
        initialDoc ? toDraft(initialDoc) : null,
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [view, setView] = useState<'isi' | 'pratinjau'>('isi');

    const signature = `${filters.unit_id}-${selected_id}-${docs.map((d) => `${d.id}:${d.updated_at}`).join(',')}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setDraft(initialDoc ? toDraft(initialDoc) : null);
        setDirty(false);
    }

    const confirmLeave = (): boolean =>
        !dirty ||
        window.confirm('Perubahan pada IK ini belum disimpan. Tetap lanjut?');

    const open = (doc: ServerDoc) => {
        if (draft?.id !== doc.id && confirmLeave()) {
            setDraft(toDraft(doc));
            setDirty(false);
            setView('isi');
        }
    };

    const startFromTemplate = (template: Template) => {
        if (!confirmLeave()) {
            return;
        }

        setDraft({
            id: null,
            kop: DEFAULT_KOP,
            judul: template.judul,
            mesin: template.mesin,
            no_dokumen: '',
            tanggal: '',
            revisi: '',
            sections: template.sections.map(toSection),
            ...signatories,
        });
        setDirty(true);
        setPickerOpen(false);
        setView('isi');
    };

    const change = (patch: Partial<Draft>) => {
        setDraft((prev) => (prev ? { ...prev, ...patch } : prev));
        setDirty(true);
    };

    const changeSection = (key: number, patch: Partial<Section>) =>
        change({
            sections: (draft?.sections ?? []).map((s) =>
                s.key === key ? { ...s, ...patch } : s,
            ),
        });

    const moveSection = (index: number, delta: number) => {
        const list = [...(draft?.sections ?? [])];
        const target = index + delta;

        if (target >= 0 && target < list.length) {
            [list[index], list[target]] = [list[target], list[index]];
            change({ sections: list });
        }
    };

    const removeSection = (key: number) => {
        const section = draft?.sections.find((s) => s.key === key);

        if (
            section &&
            (section.teks.trim() === '' ||
                window.confirm(`Hapus bagian "${section.judul}"?`))
        ) {
            change({
                sections: (draft?.sections ?? []).filter((s) => s.key !== key),
            });
        }
    };

    const addSection = (judul = '', bernomor = false, gaya: Style = 'angka') =>
        change({
            sections: [
                ...(draft?.sections ?? []),
                {
                    key: nextKey++,
                    judul,
                    bernomor,
                    gaya,
                    lanjut: false,
                    pengantar: '',
                    teks: '',
                },
            ],
        });

    const save = () => {
        if (!draft) {
            return;
        }

        setSaving(true);
        router.post(
            routes.store,
            {
                unit_id: filters.unit_id,
                id: draft.id,
                kop: draft.kop,
                judul: draft.judul,
                mesin: draft.mesin,
                no_dokumen: draft.no_dokumen,
                tanggal: draft.tanggal || null,
                revisi: draft.revisi,
                sections: draft.sections.map((s) => ({
                    judul: s.judul,
                    bernomor: s.bernomor,
                    gaya: s.gaya,
                    lanjut: s.lanjut,
                    pengantar: s.pengantar,
                    butir: parse(s.teks),
                })),
                dibuat_jabatan: draft.dibuat_jabatan,
                dibuat_nama: draft.dibuat_nama,
                disetujui_jabatan: draft.disetujui_jabatan,
                disetujui_nama: draft.disetujui_nama,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const duplicate = () => {
        if (draft) {
            setDraft({
                ...draft,
                id: null,
                judul: `${draft.judul} (Salinan)`,
                sections: draft.sections.map((s) => ({ ...s, key: nextKey++ })),
            });
            setDirty(true);
        }
    };

    const remove = () => {
        if (!draft) {
            return;
        }

        if (draft.id === null) {
            if (window.confirm('Buang draf IK yang belum disimpan ini?')) {
                setDraft(docs[0] ? toDraft(docs[0]) : null);
                setDirty(false);
            }

            return;
        }

        if (
            window.confirm(
                'Hapus Instruksi Kerja ini? Tindakan ini tidak dapat dibatalkan.',
            )
        ) {
            router.delete(routes.destroy(draft.id), {
                preserveScroll: true,
            });
        }
    };

    const pdfUrl = (id?: number) =>
        routes.pdf({ unit_id: filters.unit_id, ...(id ? { doc: id } : {}) });

    const title = (judul: string) =>
        judul.split('\n').filter(Boolean).join(' — ');

    const term = search.trim().toLowerCase();
    const shown = term
        ? docs.filter((d) =>
              `${d.judul} ${d.mesin}`.toLowerCase().includes(term),
          )
        : docs;

    const saveButton = can_write && draft && (
        <Button
            onClick={save}
            disabled={saving || !dirty || !draft.judul.trim()}
            className="gap-1.5"
        >
            <Save className="size-4" />
            {saving
                ? 'Menyimpan…'
                : draft.id === null
                  ? 'Simpan IK Baru'
                  : 'Simpan'}
        </Button>
    );

    const editor = draft && (
        <div className="flex flex-col gap-4">
            <Card
                title="1. Identitas IK"
                hint="Kop dan judul yang tampil di bagian atas dokumen."
            >
                <Field label="Judul pekerjaan (setiap baris tampil sebagai baris judul)">
                    <Textarea
                        value={draft.judul}
                        onChange={(e) => change({ judul: e.target.value })}
                        disabled={!can_write}
                        rows={2}
                        placeholder={copy.judulPlaceholder}
                        className="font-semibold uppercase"
                    />
                </Field>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field label="Mesin / Peralatan (untuk pencarian)">
                        <Input
                            value={draft.mesin}
                            onChange={(e) => change({ mesin: e.target.value })}
                            disabled={!can_write}
                            placeholder={copy.mesinPlaceholder}
                        />
                    </Field>
                    <Field label="Kop">
                        <Input
                            value={draft.kop}
                            onChange={(e) => change({ kop: e.target.value })}
                            disabled={!can_write}
                        />
                    </Field>
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <Field label="No. Dokumen (opsional)">
                        <Input
                            value={draft.no_dokumen}
                            onChange={(e) =>
                                change({ no_dokumen: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                    <Field label="Revisi (opsional)">
                        <Input
                            value={draft.revisi}
                            onChange={(e) => change({ revisi: e.target.value })}
                            disabled={!can_write}
                        />
                    </Field>
                    <Field label="Tanggal (opsional)">
                        <Input
                            type="date"
                            value={draft.tanggal}
                            onChange={(e) =>
                                change({ tanggal: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                </div>
            </Card>

            <Card
                title="2. Isi Instruksi Kerja"
                hint="Bagian bernomor (1. ALAT …) atau subjudul tebal (PERSIAPAN AWAL …). Nomor & tanda poin dibuat otomatis."
            >
                <div className="flex gap-2 rounded-md bg-sky-50 p-2.5 text-xs text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Satu baris = satu poin. Awali baris dengan <b>-</b>{' '}
                        untuk <b>sub-poin</b> (seperti langkah “Buka Filter
                        bahan bakar”). Tulis <b>**kata**</b> untuk huruf{' '}
                        <b>tebal</b>.
                    </span>
                </div>
                {draft.sections.map((section, index) => (
                    <div
                        key={section.key}
                        className="flex flex-col gap-2 rounded-lg border border-border bg-muted/20 p-3"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className={cn(
                                    'flex h-7 shrink-0 items-center justify-center rounded-md px-2 text-[12px] font-bold',
                                    section.bernomor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-foreground',
                                )}
                            >
                                {section.bernomor
                                    ? `No. ${draft.sections.slice(0, index + 1).filter((s) => s.bernomor).length}`
                                    : 'Subjudul'}
                            </span>
                            <Input
                                value={section.judul}
                                onChange={(e) =>
                                    changeSection(section.key, {
                                        judul: e.target.value.toUpperCase(),
                                    })
                                }
                                disabled={!can_write}
                                placeholder="JUDUL BAGIAN"
                                className="h-9 min-w-44 flex-1 font-semibold uppercase"
                                aria-label={`Judul bagian ${index + 1}`}
                            />
                            {can_write && (
                                <div className="flex items-center">
                                    <IconButton
                                        label="Naikkan"
                                        onClick={() => moveSection(index, -1)}
                                        disabled={index === 0}
                                    >
                                        <ArrowUp className="size-4" />
                                    </IconButton>
                                    <IconButton
                                        label="Turunkan"
                                        onClick={() => moveSection(index, 1)}
                                        disabled={
                                            index === draft.sections.length - 1
                                        }
                                    >
                                        <ArrowDown className="size-4" />
                                    </IconButton>
                                    <IconButton
                                        label="Hapus bagian"
                                        onClick={() =>
                                            removeSection(section.key)
                                        }
                                        danger
                                    >
                                        <Trash2 className="size-4" />
                                    </IconButton>
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                            <Select
                                value={section.bernomor ? 'nomor' : 'sub'}
                                onValueChange={(v) =>
                                    changeSection(section.key, {
                                        bernomor: v === 'nomor',
                                    })
                                }
                                disabled={!can_write}
                            >
                                <SelectTrigger
                                    className="h-8 w-44"
                                    aria-label={`Jenis judul bagian ${index + 1}`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="nomor">
                                        Judul bernomor (1. ALAT)
                                    </SelectItem>
                                    <SelectItem value="sub">
                                        Subjudul tebal
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={section.gaya}
                                onValueChange={(v) =>
                                    changeSection(section.key, {
                                        gaya: v as Style,
                                    })
                                }
                                disabled={!can_write}
                            >
                                <SelectTrigger
                                    className="h-8 w-32"
                                    aria-label={`Gaya poin bagian ${index + 1}`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STYLES.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {numbered(section.gaya) && index > 0 && (
                                <label className="flex items-center gap-1.5 text-muted-foreground">
                                    <Checkbox
                                        checked={section.lanjut}
                                        onCheckedChange={(v) =>
                                            changeSection(section.key, {
                                                lanjut: v === true,
                                            })
                                        }
                                        disabled={!can_write}
                                    />
                                    Lanjutkan nomor dari bagian sebelumnya
                                </label>
                            )}
                        </div>
                        <Textarea
                            value={section.pengantar}
                            onChange={(e) =>
                                changeSection(section.key, {
                                    pengantar: e.target.value,
                                })
                            }
                            disabled={!can_write}
                            rows={section.pengantar ? 2 : 1}
                            placeholder="(Opsional) kalimat pembuka sebelum poin"
                            className="min-h-9 text-sm"
                        />
                        <Textarea
                            value={section.teks}
                            onChange={(e) =>
                                changeSection(section.key, {
                                    teks: e.target.value,
                                })
                            }
                            disabled={!can_write}
                            rows={Math.max(
                                3,
                                section.teks.split('\n').length + 1,
                            )}
                            placeholder={
                                'Satu baris = satu poin\nBuka Filter bahan bakar\n- Tutup keran bahan bakar (sub-poin)'
                            }
                            className="font-mono text-sm leading-6"
                            aria-label={`Poin bagian ${index + 1}`}
                        />
                        <p className="text-[11px] text-muted-foreground">
                            {parse(section.teks).length} poin
                            {parse(section.teks).some((p) => p.sub.length > 0)
                                ? ` · ${parse(section.teks).reduce((n, p) => n + p.sub.length, 0)} sub-poin`
                                : ''}
                        </p>
                    </div>
                ))}
                {can_write && (
                    <div className="flex flex-col gap-2 rounded-lg border border-dashed border-border p-3">
                        <span className="text-xs font-medium text-muted-foreground">
                            Tambah bagian:
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {QUICK_SECTIONS.filter(
                                (q) =>
                                    !draft.sections.some(
                                        (s) => s.judul === q.judul,
                                    ),
                            ).map((q) => (
                                <Button
                                    key={q.judul}
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        addSection(q.judul, q.bernomor, q.gaya)
                                    }
                                    className="h-8 gap-1 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    {q.judul}
                                </Button>
                            ))}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => addSection('', true)}
                                className="h-8 gap-1 text-xs"
                            >
                                <Plus className="size-3.5" />
                                Bagian bernomor
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => addSection('', false)}
                                className="h-8 gap-1 text-xs"
                            >
                                <Plus className="size-3.5" />
                                Subjudul
                            </Button>
                        </div>
                    </div>
                )}
            </Card>

            <Card
                title="3. Tanda Tangan"
                hint="Kotak Dibuat / Disetujui di akhir dokumen. Kosongkan kedua jabatan untuk menyembunyikan kotak."
            >
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Dibuat — jabatan">
                        <Input
                            value={draft.dibuat_jabatan}
                            onChange={(e) =>
                                change({ dibuat_jabatan: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                    <Field label="Disetujui — jabatan">
                        <Input
                            value={draft.disetujui_jabatan}
                            onChange={(e) =>
                                change({ disetujui_jabatan: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                    <Field label="Dibuat — nama">
                        <Input
                            value={draft.dibuat_nama}
                            onChange={(e) =>
                                change({ dibuat_nama: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                    <Field label="Disetujui — nama">
                        <Input
                            value={draft.disetujui_nama}
                            onChange={(e) =>
                                change({ disetujui_nama: e.target.value })
                            }
                            disabled={!can_write}
                        />
                    </Field>
                </div>
            </Card>
        </div>
    );

    const preview = draft && (
        <div className="2xl:sticky 2xl:top-4">
            <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Eye className="size-3.5" />
                Pratinjau (sama dengan hasil cetak PDF)
            </div>
            <IkPreview draft={draft} />
        </div>
    );

    return (
        <>
            <Head title={`${copy.headTitle} — ${unit.name}`} />
            <div
                className={cn(
                    'flex flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title={copy.title}
                    description={copy.description}
                    actions={
                        docs.length > 0 ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    window.open(pdfUrl(), '_blank', 'noopener')
                                }
                                className="gap-1.5"
                            >
                                <Printer className="size-4" />
                                Cetak Semua IK ({docs.length})
                            </Button>
                        ) : undefined
                    }
                />

                {options.units.length > 1 && (
                    <div className="flex rounded-lg border border-border bg-card p-3">
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(v) =>
                                confirmLeave() &&
                                router.get(
                                    routes.index,
                                    { unit_id: Number(v) },
                                    { replace: true },
                                )
                            }
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            className="w-full sm:w-56"
                        />
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="flex flex-col gap-2">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">
                                Daftar IK · {unit.name}
                            </h2>
                            <Badge variant="secondary">{docs.length}</Badge>
                        </div>
                        {can_write && (
                            <Button
                                onClick={() => setPickerOpen(true)}
                                className="w-full gap-1.5"
                            >
                                <FilePlus2 className="size-4" />
                                Buat IK Baru
                            </Button>
                        )}
                        {docs.length > 3 && (
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari judul / mesin…"
                                    className="h-9 pl-8"
                                />
                            </div>
                        )}
                        <div
                            className={cn(
                                'flex gap-2',
                                compact
                                    ? '-mx-4 overflow-x-auto px-4 pb-1'
                                    : 'flex-col',
                            )}
                        >
                            {draft?.id === null && (
                                <ListItem
                                    active
                                    title={title(draft.judul) || 'IK baru'}
                                    subtitle="Draf — belum disimpan"
                                    draft
                                    compact={compact}
                                    onClick={() => undefined}
                                />
                            )}
                            {shown.map((doc) => (
                                <ListItem
                                    key={doc.id}
                                    active={draft?.id === doc.id}
                                    title={title(doc.judul)}
                                    subtitle={
                                        doc.mesin || 'Tanpa keterangan mesin'
                                    }
                                    unsaved={draft?.id === doc.id && dirty}
                                    compact={compact}
                                    onClick={() => open(doc)}
                                />
                            ))}
                            {docs.length === 0 && draft === null && (
                                <p className="rounded-lg border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                                    Belum ada Instruksi Kerja.
                                </p>
                            )}
                        </div>
                    </aside>

                    <section className="min-w-0">
                        {draft === null ? (
                            <div className="flex flex-col gap-4 rounded-xl border border-dashed border-border p-6 text-center">
                                <FileText className="mx-auto size-10 text-muted-foreground" />
                                <div>
                                    <p className="font-semibold text-foreground">
                                        Mulai dari template IK
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Pilih template, lalu sesuaikan judul,
                                        langkah dan tanda tangannya.
                                    </p>
                                </div>
                                {can_write && (
                                    <TemplateGrid
                                        templates={templates}
                                        onPick={startFromTemplate}
                                    />
                                )}
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-card p-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {title(draft.judul) || 'IK baru'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {draft.id === null
                                                ? 'Belum disimpan'
                                                : dirty
                                                  ? 'Ada perubahan belum disimpan'
                                                  : 'Tersimpan'}
                                        </p>
                                    </div>
                                    {draft.id !== null && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                window.open(
                                                    pdfUrl(
                                                        draft.id ?? undefined,
                                                    ),
                                                    '_blank',
                                                    'noopener',
                                                )
                                            }
                                            className="gap-1.5"
                                            title={
                                                dirty
                                                    ? 'PDF memakai data yang sudah tersimpan'
                                                    : undefined
                                            }
                                        >
                                            <FileDown className="size-4" />
                                            PDF
                                        </Button>
                                    )}
                                    {can_write && (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={duplicate}
                                                className="gap-1.5"
                                            >
                                                <Copy className="size-4" />
                                                Duplikat
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={remove}
                                                className="gap-1.5 text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                                Hapus
                                            </Button>
                                            {!compact && saveButton}
                                        </>
                                    )}
                                </div>

                                <div className="flex gap-1 rounded-lg bg-muted p-1 2xl:hidden">
                                    {(['isi', 'pratinjau'] as const).map(
                                        (v) => (
                                            <button
                                                key={v}
                                                type="button"
                                                onClick={() => setView(v)}
                                                className={cn(
                                                    'flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 text-sm font-medium transition',
                                                    view === v
                                                        ? 'bg-background text-foreground shadow-sm'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {v === 'isi' ? (
                                                    <PencilLine className="size-4" />
                                                ) : (
                                                    <Eye className="size-4" />
                                                )}
                                                {v === 'isi'
                                                    ? 'Isi IK'
                                                    : 'Pratinjau'}
                                            </button>
                                        ),
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 2xl:grid-cols-2">
                                    <div
                                        className={cn(
                                            view !== 'isi' &&
                                                'hidden 2xl:block',
                                        )}
                                    >
                                        {editor}
                                    </div>
                                    <div
                                        className={cn(
                                            view !== 'pratinjau' &&
                                                'hidden 2xl:block',
                                        )}
                                    >
                                        {preview}
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>

            <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            Pilih Template Instruksi Kerja
                        </DialogTitle>
                        <DialogDescription>
                            Semua isi template bisa diubah setelah dipilih.
                        </DialogDescription>
                    </DialogHeader>
                    <TemplateGrid
                        templates={templates}
                        onPick={startFromTemplate}
                    />
                </DialogContent>
            </Dialog>

            {compact && can_write && draft && (
                <StickyActionBar>{saveButton}</StickyActionBar>
            )}
        </>
    );
}

/** The MKP IK form as printed (mirrors resources/views/har/instruksi-kerja/document.blade.php). */
function IkPreview({ draft }: { draft: Draft }) {
    // Heading number and first point number per part ("lanjut" continues the previous count).
    const laidOut: {
        section: Section;
        points: Point[];
        heading: number;
        start: number;
    }[] = [];
    let heading = 0;
    let counter = 0;

    for (const section of draft.sections) {
        const points = parse(section.teks);
        heading += section.bernomor ? 1 : 0;
        counter = section.lanjut ? counter : 0;
        laidOut.push({ section, points, heading, start: counter });
        counter += numbered(section.gaya) ? points.length : 0;
    }

    const meta = [
        ['No. Dokumen', draft.no_dokumen],
        ['Revisi', draft.revisi],
        [
            'Tanggal',
            draft.tanggal ? draft.tanggal.split('-').reverse().join('-') : '',
        ],
    ].filter(([, v]) => v);

    return (
        <div className="overflow-x-auto rounded-md bg-neutral-200 p-3 dark:bg-neutral-800">
            <div className="mx-auto max-w-[720px] min-w-[520px] bg-white px-10 py-8 text-[12px] leading-relaxed text-black shadow-md">
                <div className="text-center">
                    <img
                        src="/logo/mkp.jpg"
                        alt="MKP Mitra Karya Prima"
                        className="mx-auto max-h-16 object-contain"
                    />
                    <div className="mt-2.5 text-[16px] font-bold tracking-wide">
                        {draft.kop}
                    </div>
                </div>
                <div className="mt-2 mb-3 border-b border-neutral-500" />
                <div className="text-center text-[15px] leading-snug font-bold">
                    <div>INSTRUKSI KERJA (IK)</div>
                    {draft.judul
                        .split('\n')
                        .filter((l) => l.trim())
                        .map((line, i) => (
                            <div key={i}>{line.trim().toUpperCase()}</div>
                        ))}
                </div>
                {meta.length > 0 && (
                    <div className="mt-1 text-center text-[10px] text-neutral-600">
                        {meta.map(([k, v]) => `${k}: ${v}`).join('   ·   ')}
                    </div>
                )}

                <div className="mt-3">
                    {laidOut.map(
                        ({ section, points, heading: number, start }) => {
                            const indent = section.bernomor ? 'ml-8' : 'ml-11';

                            return (
                                <div key={section.key} className="mt-2">
                                    {section.bernomor ? (
                                        <div className="flex font-bold">
                                            <span className="w-7 shrink-0">
                                                {number}.
                                            </span>
                                            <span>
                                                {section.judul.toUpperCase() ||
                                                    '…'}
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="mt-2.5 font-bold">
                                            {section.judul.toUpperCase() || '…'}
                                        </div>
                                    )}
                                    {section.pengantar.trim() && (
                                        <p className="mt-0.5 ml-7">
                                            <Rich text={section.pengantar} />
                                        </p>
                                    )}
                                    {points.map((point, i) => (
                                        <div key={i}>
                                            {section.gaya === 'paragraf' ? (
                                                <p className="mt-0.5 ml-7">
                                                    <Rich text={point.teks} />
                                                </p>
                                            ) : (
                                                <div
                                                    className={cn(
                                                        'mt-0.5 flex',
                                                        indent,
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'w-6 shrink-0 pr-1.5',
                                                            numbered(
                                                                section.gaya,
                                                            ) && 'text-right',
                                                        )}
                                                    >
                                                        {marker(
                                                            section.gaya,
                                                            start + i + 1,
                                                        )}
                                                    </span>
                                                    <span>
                                                        <Rich
                                                            text={point.teks}
                                                        />
                                                    </span>
                                                </div>
                                            )}
                                            {point.sub.map((sub, j) => (
                                                <div
                                                    key={j}
                                                    className={cn(
                                                        'mt-0.5 flex',
                                                        section.bernomor
                                                            ? 'ml-16'
                                                            : 'ml-[4.75rem]',
                                                    )}
                                                >
                                                    <span className="w-3.5 shrink-0">
                                                        -
                                                    </span>
                                                    <span>
                                                        <Rich text={sub} />
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            );
                        },
                    )}
                </div>

                {(draft.dibuat_jabatan || draft.disetujui_jabatan) && (
                    <div className="mt-7 grid grid-cols-[34%_1fr_34%] border border-black text-center text-[11px]">
                        <div className="border-r border-b border-black py-0.5">
                            Dibuat
                        </div>
                        <div className="border-r border-b border-black" />
                        <div className="border-b border-black py-0.5">
                            Disetujui
                        </div>
                        <div className="border-r border-black py-0.5">
                            {draft.dibuat_jabatan}
                        </div>
                        <div className="border-r border-black" />
                        <div className="py-0.5">{draft.disetujui_jabatan}</div>
                        <div className="h-12 border-r border-black" />
                        <div className="border-r border-black" />
                        <div />
                        <div className="border-r border-black py-0.5">
                            {draft.dibuat_nama.toUpperCase()}
                        </div>
                        <div className="border-r border-black" />
                        <div className="py-0.5">
                            {draft.disetujui_nama.toUpperCase()}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function TemplateGrid({
    templates,
    onPick,
}: {
    templates: Template[];
    onPick: (template: Template) => void;
}) {
    return (
        <div className="grid grid-cols-1 gap-3 text-left sm:grid-cols-2">
            {templates.map((t) => {
                const pointCount = t.sections.reduce(
                    (n, s) => n + s.butir.filter((b) => b.teks).length,
                    0,
                );

                return (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => onPick(t)}
                        className="flex flex-col gap-1.5 rounded-lg border border-border bg-card p-3 text-left transition hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="text-sm font-semibold text-foreground">
                            {t.nama}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {t.deskripsi}
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            {t.sections.length} bagian
                            {pointCount > 0 ? ` · ${pointCount} langkah` : ''}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function ListItem({
    title,
    subtitle,
    active,
    unsaved = false,
    draft = false,
    compact,
    onClick,
}: {
    title: string;
    subtitle: string;
    active: boolean;
    unsaved?: boolean;
    draft?: boolean;
    compact: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-col gap-0.5 rounded-lg border p-2.5 text-left transition',
                compact ? 'w-60 shrink-0' : 'w-full',
                active
                    ? 'border-primary bg-primary/10'
                    : 'border-border bg-card hover:bg-muted',
                draft && 'border-dashed',
            )}
        >
            <span className="line-clamp-2 text-[13px] font-medium text-foreground">
                {unsaved && (
                    <span className="mr-1 inline-block size-2 rounded-full bg-amber-500 align-middle" />
                )}
                {title}
            </span>
            <span className="truncate text-[11px] text-muted-foreground">
                {subtitle}
            </span>
        </button>
    );
}

function Card({
    title,
    hint,
    children,
}: {
    title: string;
    hint: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4">
            <div>
                <h3 className="text-sm font-semibold text-foreground">
                    {title}
                </h3>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            {children}
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
            {label}
            {children}
        </label>
    );
}

function IconButton({
    label,
    onClick,
    disabled = false,
    danger = false,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    danger?: boolean;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClick}
            disabled={disabled}
            title={label}
            aria-label={label}
            className={cn(
                'size-9 text-muted-foreground',
                danger ? 'hover:text-destructive' : 'hover:text-foreground',
            )}
        >
            {children}
        </Button>
    );
}
