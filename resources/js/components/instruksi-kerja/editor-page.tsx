import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    CornerDownRight,
    FileDown,
    FilePlus2,
    FileText,
    Plus,
    Printer,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ClipboardEvent, KeyboardEvent, ReactNode } from 'react';
import {
    A4Sheet,
    AutoText,
    EDITABLE,
    EditorTips,
    Kbd,
    PART_FRAME,
    PART_TOOLS,
    ToolButton,
    ToolDivider,
    useFieldFocus,
    useSaveShortcut,
    useUnsavedWarning,
    EditorViewTabs,
    PdfPreview,
} from '@/components/instruksi-kerja/visual-editor';
import type { EditorView } from '@/components/instruksi-kerja/visual-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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

/** On screen a part's points are lines; a sub-point (`sub`) belongs to the point above it. */
type Line = { key: number; text: string; sub: boolean };

type Section = Omit<ServerSection, 'butir'> & { key: number; lines: Line[] };

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
    /** POST: PDF of the IK as currently edited (not saved). */
    preview: string;
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

const STYLES: { value: Style; label: string; hint: string }[] = [
    { value: 'angka', label: '1.', hint: 'Langkah bernomor 1. 2. 3.' },
    { value: 'huruf', label: 'a.', hint: 'Langkah berhuruf a. b. c.' },
    { value: 'butir', label: '•', hint: 'Butir •' },
    { value: 'panah', label: '➢', hint: 'Panah ➢' },
    { value: 'strip', label: '–', hint: 'Strip –' },
    { value: 'paragraf', label: '¶', hint: 'Paragraf tanpa tanda' },
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

const line = (text = '', sub = false): Line => ({ key: nextKey++, text, sub });

const toLines = (points: Point[]): Line[] => {
    const lines = points.flatMap((p) => [
        line(p.teks),
        ...p.sub.map((s) => line(s, true)),
    ]);

    return lines.length ? lines : [line()];
};

/** Lines back to points: a sub-point joins the point above it (a leading sub-point becomes a point). */
const toPoints = (lines: Line[]): Point[] => {
    const points: Point[] = [];

    for (const l of lines) {
        const text = l.text.trim();

        if (!text) {
            continue;
        }

        if (l.sub && points.length > 0) {
            points[points.length - 1].sub.push(text);
        } else {
            points.push({ teks: text, sub: [] });
        }
    }

    return points;
};

/** A pasted line: its own marker removed, "-" / indented = sub-point. */
const pastedLine = (raw: string): Line | null => {
    const sub = /^\s+\S/.test(raw) || /^\s*-/.test(raw);
    const text = raw
        .replace(/^\s*(?:-+\.?|[•➢*–]|\d+[.)]|[a-z]\.)\s*/i, '')
        .trim();

    return text ? line(text, sub) : null;
};

const toSection = (s: ServerSection): Section => ({
    key: nextKey++,
    judul: s.judul,
    bernomor: s.bernomor,
    gaya: s.gaya,
    lanjut: s.lanjut,
    pengantar: s.pengantar ?? '',
    lines: toLines(s.butir ?? []),
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

/** "**tebal**" → bold (the list and template cards). */
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
 * unit's IK library, written straight on one A4 sheet in the MKP layout
 * (resources/views/har/instruksi-kerja/document.blade.php — keep the sheet in
 * sync). Start from a template, then edit everything in place: kop, title,
 * numbered parts or bold sub-headings, point style, sub-points (Tab), bold
 * (**…**, Ctrl+B), continued numbering and the signature box. Each module page
 * (e.g. pages/har/input/instruksi-kerja/index.tsx) only passes its routes and
 * texts.
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
    const [view, setView] = useState<EditorView>('edit');

    const signature = `${filters.unit_id}-${selected_id}-${docs.map((d) => `${d.id}:${d.updated_at}`).join(',')}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setDraft(initialDoc ? toDraft(initialDoc) : null);
        setDirty(false);
    }

    useUnsavedWarning(dirty);

    const confirmLeave = (): boolean =>
        !dirty ||
        window.confirm('Perubahan pada IK ini belum disimpan. Tetap lanjut?');

    const open = (doc: ServerDoc) => {
        if (draft?.id !== doc.id && confirmLeave()) {
            setDraft(toDraft(doc));
            setDirty(false);
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
    };

    const change = (patch: Partial<Draft>) => {
        setDraft((prev) => (prev ? { ...prev, ...patch } : prev));
        setDirty(true);
    };

    /** The document as sent to store and to the PDF preview. */
    const payload = (doc: Draft) => ({
        unit_id: filters.unit_id,
        id: doc.id,
        kop: doc.kop,
        judul: doc.judul,
        mesin: doc.mesin,
        no_dokumen: doc.no_dokumen,
        tanggal: doc.tanggal || null,
        revisi: doc.revisi,
        sections: doc.sections.map((s) => ({
            judul: s.judul,
            bernomor: s.bernomor,
            gaya: s.gaya,
            lanjut: s.lanjut,
            pengantar: s.pengantar,
            butir: toPoints(s.lines),
        })),
        dibuat_jabatan: doc.dibuat_jabatan,
        dibuat_nama: doc.dibuat_nama,
        disetujui_jabatan: doc.disetujui_jabatan,
        disetujui_nama: doc.disetujui_nama,
    });

    const save = () => {
        if (!draft || saving || !draft.judul.trim()) {
            return;
        }

        setSaving(true);
        router.post(routes.store, payload(draft), {
            preserveScroll: true,
            onSuccess: () => setDirty(false),
            onFinish: () => setSaving(false),
        });
    };

    useSaveShortcut(() => {
        if (can_write && dirty) {
            save();
        }
    });

    const duplicate = () => {
        if (draft) {
            setDraft({
                ...draft,
                id: null,
                judul: `${draft.judul} (Salinan)`,
                sections: draft.sections.map((s) => ({
                    ...s,
                    key: nextKey++,
                    lines: s.lines.map((l) => line(l.text, l.sub)),
                })),
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
            router.delete(routes.destroy(draft.id), { preserveScroll: true });
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
            title="Simpan (Ctrl+S)"
        >
            <Save className="size-4" />
            {saving
                ? 'Menyimpan…'
                : draft.id === null
                  ? 'Simpan IK Baru'
                  : 'Simpan'}
        </Button>
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
                    <div className="flex rounded-md border border-border bg-secondary p-3">
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

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
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
                                <p className="rounded-md border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                                    Belum ada Instruksi Kerja.
                                </p>
                            )}
                        </div>
                    </aside>

                    <section className="min-w-0">
                        {draft === null ? (
                            <div className="flex flex-col gap-4 rounded-md border border-dashed border-border p-6 text-center">
                                <FileText className="mx-auto size-10 text-muted-foreground" />
                                <div>
                                    <p className="font-semibold text-foreground">
                                        Mulai dari template IK
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Pilih template, lalu ubah judul, langkah
                                        dan tanda tangannya langsung pada
                                        dokumen.
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
                                <div className="sticky top-0 z-10 flex flex-wrap items-center gap-2 rounded-md border border-border bg-card p-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {title(draft.judul) || 'IK baru'}
                                        </p>
                                        <p
                                            className={cn(
                                                'text-xs',
                                                dirty
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
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

                                {/* Not printed: only for finding the IK in the list. */}
                                <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground sm:flex-row sm:items-center sm:gap-2">
                                    <span className="shrink-0">
                                        Mesin / Peralatan (untuk pencarian,
                                        tidak dicetak):
                                    </span>
                                    <Input
                                        value={draft.mesin}
                                        onChange={(e) =>
                                            change({ mesin: e.target.value })
                                        }
                                        disabled={!can_write}
                                        placeholder={copy.mesinPlaceholder}
                                        className="h-8 sm:max-w-xs"
                                    />
                                </label>

                                <EditorViewTabs
                                    view={view}
                                    onChange={setView}
                                />

                                {view === 'pdf' ? (
                                    <PdfPreview
                                        previewUrl={routes.preview}
                                        payload={payload(draft)}
                                        savedUrl={
                                            draft.id !== null
                                                ? pdfUrl(draft.id)
                                                : null
                                        }
                                        useSaved={
                                            !can_write ||
                                            (!dirty && draft.id !== null)
                                        }
                                    />
                                ) : (
                                    <>
                                        {can_write && !compact && (
                                            <EditorTips
                                                extra={
                                                    <>
                                                        <span>
                                                            <Kbd>Tab</Kbd> /{' '}
                                                            <Kbd>Shift</Kbd>+
                                                            <Kbd>Tab</Kbd>{' '}
                                                            sub-poin
                                                        </span>
                                                        <span>
                                                            <Kbd>Ctrl</Kbd>+
                                                            <Kbd>B</Kbd> tebal
                                                        </span>
                                                    </>
                                                }
                                            />
                                        )}

                                        <IkSheetEditor
                                            key={draft.id ?? 'baru'}
                                            draft={draft}
                                            editable={can_write}
                                            judulPlaceholder={
                                                copy.judulPlaceholder
                                            }
                                            onChange={change}
                                        />
                                    </>
                                )}
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
                            Semua isi template bisa diubah langsung pada dokumen
                            setelah dipilih.
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

/**
 * The MKP IK form edited in place on one A4 sheet (16 × 18 mm margins as the
 * PDF): kop, title lines, document data, every part (heading, opening
 * sentence, points & sub-points) and the Dibuat / Disetujui box are text
 * fields that look like the print. A part's tools appear on hover / focus.
 */
function IkSheetEditor({
    draft,
    editable,
    judulPlaceholder,
    onChange,
}: {
    draft: Draft;
    editable: boolean;
    judulPlaceholder: string;
    onChange: (patch: Partial<Draft>) => void;
}) {
    const { register, focusNow, focusAfterRender } = useFieldFocus();

    const sections = draft.sections;
    const setSections = (next: Section[]) => onChange({ sections: next });
    const patchSection = (key: number, patch: Partial<Section>) =>
        setSections(
            sections.map((s) => (s.key === key ? { ...s, ...patch } : s)),
        );
    const setLines = (section: Section, next: Line[]) =>
        patchSection(section.key, { lines: next.length ? next : [line()] });

    // Heading number and first point number per part ("lanjut" continues the previous count).
    const layout: { heading: number; start: number }[] = [];
    let heading = 0;
    let counter = 0;

    for (const section of sections) {
        heading += section.bernomor ? 1 : 0;
        counter = section.lanjut ? counter : 0;
        layout.push({ heading, start: counter });
        counter += numbered(section.gaya)
            ? section.lines.filter((l) => !l.sub).length
            : 0;
    }

    const moveSection = (index: number, delta: number) => {
        const list = [...sections];
        const target = index + delta;

        if (target >= 0 && target < list.length) {
            [list[index], list[target]] = [list[target], list[index]];
            setSections(list);
        }
    };

    const removeSection = (section: Section) => {
        if (
            toPoints(section.lines).length === 0 ||
            window.confirm(
                `Hapus bagian "${section.judul || 'tanpa judul'}" beserta isinya?`,
            )
        ) {
            setSections(sections.filter((s) => s.key !== section.key));
        }
    };

    const addSection = (
        judul = '',
        bernomor = false,
        gaya: Style = 'angka',
    ) => {
        const created: Section = {
            key: nextKey++,
            judul,
            bernomor,
            gaya,
            lanjut: false,
            pengantar: '',
            lines: [line()],
        };
        setSections([...sections, created]);
        focusAfterRender({
            id: judul ? `l-${created.lines[0].key}` : `j-${created.key}`,
            at: 'end',
        });
    };

    /** Wraps the selection (or the word at the caret) in **…** for bold. */
    const toggleBold = (
        section: Section,
        current: Line,
        field: HTMLTextAreaElement,
    ) => {
        const { selectionStart: from, selectionEnd: to, value } = field;
        const text =
            from === to
                ? value
                : value.slice(0, from) +
                  `**${value.slice(from, to)}**` +
                  value.slice(to);
        const next =
            from === to
                ? /^\*\*.*\*\*$/.test(value)
                    ? value.slice(2, -2)
                    : `**${value}**`
                : text;
        setLines(
            section,
            section.lines.map((l) =>
                l.key === current.key ? { ...l, text: next } : l,
            ),
        );
        focusAfterRender({ id: `l-${current.key}`, at: 'end' });
    };

    const lineKeyDown =
        (section: Section, index: number) =>
        (event: KeyboardEvent<HTMLTextAreaElement>) => {
            const field = event.currentTarget;
            const current = section.lines[index];
            const replace = (
                next: Line[],
                focus: { key: number; at: 'start' | 'end' },
            ) => {
                setLines(section, next);
                focusAfterRender({ id: `l-${focus.key}`, at: focus.at });
            };

            if (event.key === 'Enter' && !event.shiftKey) {
                // Split at the caret: the rest becomes the next point (same level).
                event.preventDefault();
                const created = line(
                    field.value.slice(field.selectionEnd),
                    current.sub,
                );
                const next = [...section.lines];
                next.splice(
                    index,
                    1,
                    {
                        ...current,
                        text: field.value.slice(0, field.selectionStart),
                    },
                    created,
                );
                replace(next, { key: created.key, at: 'start' });

                return;
            }

            if (event.key === 'Tab') {
                // Tab: sub-point of the point above · Shift+Tab: back to a point.
                const sub = !event.shiftKey;

                if (sub === current.sub || (sub && index === 0)) {
                    return;
                }

                event.preventDefault();
                replace(
                    section.lines.map((l) =>
                        l.key === current.key ? { ...l, sub } : l,
                    ),
                    {
                        key: current.key,
                        at: field.selectionStart === 0 ? 'start' : 'end',
                    },
                );

                return;
            }

            if (
                event.key === 'Backspace' &&
                field.selectionStart === 0 &&
                field.selectionEnd === 0
            ) {
                if (current.sub) {
                    // A sub-point first goes back to a point.
                    event.preventDefault();
                    replace(
                        section.lines.map((l) =>
                            l.key === current.key ? { ...l, sub: false } : l,
                        ),
                        { key: current.key, at: 'start' },
                    );
                } else if (index > 0) {
                    // Join the point to the line above.
                    event.preventDefault();
                    const previous = section.lines[index - 1];
                    const next = [...section.lines];
                    next.splice(index - 1, 2, {
                        ...previous,
                        text: previous.text + current.text,
                    });
                    replace(next, { key: previous.key, at: 'end' });
                }

                return;
            }

            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'b'
            ) {
                event.preventDefault();
                toggleBold(section, current, field);

                return;
            }

            if (
                event.key === 'ArrowUp' &&
                field.selectionStart === 0 &&
                index > 0
            ) {
                event.preventDefault();
                focusNow({
                    id: `l-${section.lines[index - 1].key}`,
                    at: 'end',
                });
            } else if (
                event.key === 'ArrowDown' &&
                field.selectionStart === field.value.length &&
                index < section.lines.length - 1
            ) {
                event.preventDefault();
                focusNow({
                    id: `l-${section.lines[index + 1].key}`,
                    at: 'start',
                });
            }
        };

    const linePaste =
        (section: Section, index: number) =>
        (event: ClipboardEvent<HTMLTextAreaElement>) => {
            const pasted = event.clipboardData
                .getData('text')
                .split(/\r?\n/)
                .map(pastedLine)
                .filter((l): l is Line => l !== null);

            if (pasted.length < 2) {
                return;
            }

            // Several lines pasted: one point each ("-" lines = sub-points).
            event.preventDefault();
            const field = event.currentTarget;
            const current = section.lines[index];
            const before = field.value.slice(0, field.selectionStart);
            const after = field.value.slice(field.selectionEnd);
            pasted[0] = {
                ...pasted[0],
                key: current.key,
                text: before + pasted[0].text,
                sub: current.sub || pasted[0].sub,
            };
            const last = pasted.length - 1;
            pasted[last] = { ...pasted[last], text: pasted[last].text + after };
            const next = [...section.lines];
            next.splice(index, 1, ...pasted);
            setLines(section, next);
            focusAfterRender({ id: `l-${pasted[last].key}`, at: 'end' });
        };

    const meta: ['no_dokumen' | 'revisi' | 'tanggal', string][] = [
        ['no_dokumen', 'No. Dokumen'],
        ['revisi', 'Revisi'],
        ['tanggal', 'Tanggal'],
    ];
    const showSignatures = Boolean(
        draft.dibuat_jabatan || draft.disetujui_jabatan,
    );

    return (
        <A4Sheet padding="sm:px-[18mm] sm:py-[16mm]">
            <div className="flex flex-1 flex-col text-[12px] leading-relaxed">
                {/* Kop */}
                <div className="text-center">
                    <img
                        src="/logo/mkp.jpg"
                        alt="MKP Mitra Karya Prima"
                        className="mx-auto max-h-16 object-contain"
                    />
                    <AutoText
                        value={draft.kop}
                        onValue={(v) =>
                            onChange({ kop: v.replace(/\r?\n/g, ' ') })
                        }
                        disabled={!editable}
                        placeholder="KOP"
                        className={cn(
                            EDITABLE,
                            'mt-2.5 text-center text-[16px] font-bold tracking-wide',
                        )}
                        aria-label="Kop"
                    />
                </div>
                <div className="mt-2 mb-3 border-b border-neutral-500" />

                {/* Title: one printed line per line */}
                <div className="text-center text-[15px] leading-snug font-bold">
                    <div>INSTRUKSI KERJA (IK)</div>
                    <AutoText
                        value={draft.judul}
                        onValue={(v) => onChange({ judul: v.toUpperCase() })}
                        disabled={!editable}
                        placeholder={judulPlaceholder}
                        className={cn(
                            EDITABLE,
                            'text-center uppercase',
                            !draft.judul.trim() && 'bg-red-50',
                        )}
                        aria-label="Judul pekerjaan (Enter = baris baru)"
                        title="Enter = baris judul berikutnya"
                    />
                </div>

                {/* Document data (printed only when filled) */}
                <div className="mt-1 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[10px] text-neutral-600">
                    {meta.map(([field, label]) =>
                        editable || draft[field] ? (
                            <label
                                key={field}
                                className="flex items-center gap-1"
                            >
                                {label}:
                                <input
                                    type={field === 'tanggal' ? 'date' : 'text'}
                                    value={draft[field]}
                                    onChange={(e) =>
                                        onChange({ [field]: e.target.value })
                                    }
                                    disabled={!editable}
                                    placeholder="(opsional)"
                                    className={cn(
                                        EDITABLE,
                                        'h-5 py-0',
                                        field === 'tanggal' ? 'w-32' : 'w-28',
                                    )}
                                />
                            </label>
                        ) : null,
                    )}
                </div>

                {/* Parts */}
                <div className="mt-3 flex-1">
                    {sections.map((section, sIndex) => {
                        const { heading: number, start } = layout[sIndex];
                        const mainIndent = section.bernomor ? 'ml-8' : 'ml-11';
                        let main = 0;

                        return (
                            <div
                                key={section.key}
                                className={cn(
                                    'group/section relative mt-2 rounded-sm',
                                    editable && PART_FRAME,
                                )}
                            >
                                <div className="flex items-start gap-1 font-bold">
                                    {section.bernomor && (
                                        <span className="w-6 shrink-0 pt-px">
                                            {number}.
                                        </span>
                                    )}
                                    <AutoText
                                        ref={register(`j-${section.key}`)}
                                        value={section.judul}
                                        onValue={(v) =>
                                            patchSection(section.key, {
                                                judul: v
                                                    .toUpperCase()
                                                    .replace(/\r?\n/g, ' '),
                                            })
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                focusNow({
                                                    id: `l-${section.lines[0].key}`,
                                                    at: 'start',
                                                });
                                            }
                                        }}
                                        disabled={!editable}
                                        placeholder={
                                            section.bernomor
                                                ? 'JUDUL BAGIAN (mis. ALAT)'
                                                : 'SUBJUDUL (mis. PROSEDUR PEKERJAAN)'
                                        }
                                        className={cn(
                                            EDITABLE,
                                            'uppercase',
                                            !section.bernomor && 'mt-1',
                                        )}
                                        aria-label={`Judul bagian ${sIndex + 1}`}
                                    />

                                    {editable && (
                                        <div
                                            className={cn(
                                                PART_TOOLS,
                                                'font-normal',
                                            )}
                                        >
                                            <ToolButton
                                                label="Judul bernomor (1. ALAT)"
                                                active={section.bernomor}
                                                onClick={() =>
                                                    patchSection(section.key, {
                                                        bernomor: true,
                                                    })
                                                }
                                            >
                                                No.
                                            </ToolButton>
                                            <ToolButton
                                                label="Subjudul tebal tanpa nomor"
                                                active={!section.bernomor}
                                                onClick={() =>
                                                    patchSection(section.key, {
                                                        bernomor: false,
                                                    })
                                                }
                                            >
                                                Sub
                                            </ToolButton>
                                            <ToolDivider />
                                            {STYLES.map((style) => (
                                                <ToolButton
                                                    key={style.value}
                                                    label={`Gaya poin: ${style.hint}`}
                                                    active={
                                                        section.gaya ===
                                                        style.value
                                                    }
                                                    onClick={() =>
                                                        patchSection(
                                                            section.key,
                                                            {
                                                                gaya: style.value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {style.label}
                                                </ToolButton>
                                            ))}
                                            {numbered(section.gaya) &&
                                                sIndex > 0 && (
                                                    <ToolButton
                                                        label="Lanjutkan nomor dari bagian sebelumnya"
                                                        active={section.lanjut}
                                                        onClick={() =>
                                                            patchSection(
                                                                section.key,
                                                                {
                                                                    lanjut: !section.lanjut,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <CornerDownRight className="size-3.5" />
                                                    </ToolButton>
                                                )}
                                            <ToolDivider />
                                            <ToolButton
                                                label="Naikkan bagian"
                                                onClick={() =>
                                                    moveSection(sIndex, -1)
                                                }
                                                disabled={sIndex === 0}
                                            >
                                                <ArrowUp className="size-3.5" />
                                            </ToolButton>
                                            <ToolButton
                                                label="Turunkan bagian"
                                                onClick={() =>
                                                    moveSection(sIndex, 1)
                                                }
                                                disabled={
                                                    sIndex ===
                                                    sections.length - 1
                                                }
                                            >
                                                <ArrowDown className="size-3.5" />
                                            </ToolButton>
                                            <ToolButton
                                                label="Hapus bagian"
                                                onClick={() =>
                                                    removeSection(section)
                                                }
                                                danger
                                            >
                                                <Trash2 className="size-3.5" />
                                            </ToolButton>
                                        </div>
                                    )}
                                </div>

                                {(section.pengantar.trim() !== '' ||
                                    editable) && (
                                    <div
                                        className={cn(
                                            'ml-7',
                                            section.pengantar.trim() === '' &&
                                                'hidden group-focus-within/section:block group-hover/section:block',
                                        )}
                                    >
                                        <AutoText
                                            value={section.pengantar}
                                            onValue={(v) =>
                                                patchSection(section.key, {
                                                    pengantar: v,
                                                })
                                            }
                                            disabled={!editable}
                                            placeholder="+ Kalimat pembuka (opsional)"
                                            className={cn(EDITABLE, 'mt-0.5')}
                                            aria-label={`Kalimat pembuka bagian ${sIndex + 1}`}
                                        />
                                    </div>
                                )}

                                {section.lines.map((l, lIndex) => {
                                    const isMain = !l.sub;

                                    if (isMain) {
                                        main++;
                                    }

                                    const indent = l.sub
                                        ? section.bernomor
                                            ? 'ml-16'
                                            : 'ml-[4.75rem]'
                                        : section.gaya === 'paragraf'
                                          ? 'ml-7'
                                          : mainIndent;
                                    const mark = l.sub
                                        ? '-'
                                        : section.gaya === 'paragraf'
                                          ? ''
                                          : marker(section.gaya, start + main);

                                    return (
                                        <div
                                            key={l.key}
                                            className={cn(
                                                'mt-0.5 flex items-start',
                                                indent,
                                            )}
                                        >
                                            {mark !== '' && (
                                                <span
                                                    className={cn(
                                                        'shrink-0 pt-px pr-1.5',
                                                        l.sub ? 'w-3.5' : 'w-6',
                                                        !l.sub &&
                                                            numbered(
                                                                section.gaya,
                                                            ) &&
                                                            'text-right',
                                                    )}
                                                >
                                                    {mark}
                                                </span>
                                            )}
                                            <AutoText
                                                ref={register(`l-${l.key}`)}
                                                value={l.text}
                                                onValue={(v) =>
                                                    setLines(
                                                        section,
                                                        section.lines.map(
                                                            (x) =>
                                                                x.key === l.key
                                                                    ? {
                                                                          ...x,
                                                                          text: v.replace(
                                                                              /\r?\n/g,
                                                                              ' ',
                                                                          ),
                                                                      }
                                                                    : x,
                                                        ),
                                                    )
                                                }
                                                onKeyDown={lineKeyDown(
                                                    section,
                                                    lIndex,
                                                )}
                                                onPaste={linePaste(
                                                    section,
                                                    lIndex,
                                                )}
                                                disabled={!editable}
                                                placeholder={
                                                    lIndex === 0
                                                        ? 'Tulis langkah pertama, Enter untuk langkah berikutnya…'
                                                        : l.sub
                                                          ? 'Sub-poin…'
                                                          : 'Langkah…'
                                                }
                                                className={cn(
                                                    EDITABLE,
                                                    /\*\*/.test(l.text) &&
                                                        'font-mono text-[11px]',
                                                )}
                                                aria-label={`Bagian ${sIndex + 1}, ${l.sub ? 'sub-poin' : 'poin'} ${lIndex + 1}`}
                                            />
                                            {editable &&
                                                section.lines.length > 1 && (
                                                    <button
                                                        type="button"
                                                        tabIndex={-1}
                                                        title="Hapus poin"
                                                        aria-label={`Hapus baris ${lIndex + 1}`}
                                                        onClick={() =>
                                                            setLines(
                                                                section,
                                                                section.lines.filter(
                                                                    (x) =>
                                                                        x.key !==
                                                                        l.key,
                                                                ),
                                                            )
                                                        }
                                                        className="mt-0.5 shrink-0 rounded p-0.5 text-neutral-300 opacity-0 group-hover/section:opacity-100 hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                )}
                                        </div>
                                    );
                                })}

                                {editable && (
                                    <div
                                        className={cn(
                                            'mt-0.5 hidden gap-1 group-focus-within/section:flex group-hover/section:flex',
                                            mainIndent,
                                        )}
                                    >
                                        <InlineAdd
                                            onClick={() => {
                                                const created = line();
                                                setLines(section, [
                                                    ...section.lines,
                                                    created,
                                                ]);
                                                focusAfterRender({
                                                    id: `l-${created.key}`,
                                                    at: 'start',
                                                });
                                            }}
                                        >
                                            Tambah{' '}
                                            {section.gaya === 'paragraf'
                                                ? 'paragraf'
                                                : 'langkah'}
                                        </InlineAdd>
                                        <InlineAdd
                                            onClick={() => {
                                                const created = line('', true);
                                                setLines(section, [
                                                    ...section.lines,
                                                    created,
                                                ]);
                                                focusAfterRender({
                                                    id: `l-${created.key}`,
                                                    at: 'start',
                                                });
                                            }}
                                        >
                                            Tambah sub-poin
                                        </InlineAdd>
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {editable && (
                        <div className="mt-6 flex flex-wrap items-center gap-1.5 border-t border-dashed border-neutral-300 pt-3">
                            <span className="text-[11px] text-neutral-500">
                                Tambah bagian:
                            </span>
                            {QUICK_SECTIONS.filter(
                                (q) =>
                                    !sections.some((s) => s.judul === q.judul),
                            ).map((q) => (
                                <AddPartButton
                                    key={q.judul}
                                    onClick={() =>
                                        addSection(q.judul, q.bernomor, q.gaya)
                                    }
                                >
                                    {q.judul}
                                </AddPartButton>
                            ))}
                            <AddPartButton onClick={() => addSection('', true)}>
                                Bagian bernomor
                            </AddPartButton>
                            <AddPartButton
                                onClick={() => addSection('', false)}
                            >
                                Subjudul
                            </AddPartButton>
                        </div>
                    )}
                </div>

                {/* Dibuat / Disetujui (printed when a jabatan is filled) */}
                {(showSignatures || editable) && (
                    <div
                        className={cn('mt-7', !showSignatures && 'opacity-60')}
                    >
                        <div className="grid grid-cols-[34%_1fr_34%] border border-black text-center text-[11px]">
                            <div className="border-r border-b border-black py-0.5">
                                Dibuat
                            </div>
                            <div className="border-r border-b border-black" />
                            <div className="border-b border-black py-0.5">
                                Disetujui
                            </div>
                            <div className="border-r border-black px-1 py-0.5">
                                <SignField
                                    value={draft.dibuat_jabatan}
                                    onValue={(v) =>
                                        onChange({ dibuat_jabatan: v })
                                    }
                                    editable={editable}
                                    placeholder="Jabatan"
                                    label="Dibuat — jabatan"
                                />
                            </div>
                            <div className="border-r border-black" />
                            <div className="px-1 py-0.5">
                                <SignField
                                    value={draft.disetujui_jabatan}
                                    onValue={(v) =>
                                        onChange({ disetujui_jabatan: v })
                                    }
                                    editable={editable}
                                    placeholder="Jabatan"
                                    label="Disetujui — jabatan"
                                />
                            </div>
                            <div className="h-12 border-r border-black" />
                            <div className="border-r border-black" />
                            <div />
                            <div className="border-r border-black px-1 py-0.5">
                                <SignField
                                    value={draft.dibuat_nama}
                                    onValue={(v) =>
                                        onChange({ dibuat_nama: v })
                                    }
                                    editable={editable}
                                    placeholder="NAMA"
                                    label="Dibuat — nama"
                                    upper
                                />
                            </div>
                            <div className="border-r border-black" />
                            <div className="px-1 py-0.5">
                                <SignField
                                    value={draft.disetujui_nama}
                                    onValue={(v) =>
                                        onChange({ disetujui_nama: v })
                                    }
                                    editable={editable}
                                    placeholder="NAMA"
                                    label="Disetujui — nama"
                                    upper
                                />
                            </div>
                        </div>
                        {editable && !showSignatures && (
                            <p className="mt-1 text-center text-[10px] text-neutral-500 italic">
                                Kotak tanda tangan tidak dicetak selama kedua
                                jabatan kosong.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </A4Sheet>
    );
}

function SignField({
    value,
    onValue,
    editable,
    placeholder,
    label,
    upper = false,
}: {
    value: string;
    onValue: (value: string) => void;
    editable: boolean;
    placeholder: string;
    label: string;
    upper?: boolean;
}) {
    return (
        <input
            value={upper ? value.toUpperCase() : value}
            onChange={(e) => onValue(e.target.value)}
            disabled={!editable}
            placeholder={placeholder}
            aria-label={label}
            className={cn(EDITABLE, 'h-5 py-0 text-center')}
        />
    );
}

function InlineAdd({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex items-center gap-1 rounded px-1 text-[11px] text-sky-700 hover:bg-sky-50"
        >
            <Plus className="size-3" />
            {children}
        </button>
    );
}

function AddPartButton({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex items-center gap-1 rounded border border-neutral-300 px-2 py-0.5 text-[11px] text-neutral-700 hover:border-sky-500 hover:bg-sky-50"
        >
            <Plus className="size-3" />
            {children}
        </button>
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
                        className="flex flex-col gap-1.5 rounded-md border border-border bg-card p-3 text-left transition hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="text-sm font-semibold text-foreground">
                            <Rich text={t.nama} />
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
                'flex flex-col gap-0.5 rounded-md border p-2.5 text-left transition',
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
