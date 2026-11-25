import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    FileDown,
    FilePlus2,
    FileText,
    Plus,
    Printer,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { ClipboardEvent, KeyboardEvent, ReactNode } from 'react';
import {
    A4Sheet,
    AutoText,
    EDITABLE,
    EditorTips,
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
import {
    K3MobileFilterCard,
    K3MobileHeader,
} from '@/components/k3/mobile-page-shell';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
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
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import dokumenIk from '@/routes/k3/input/dokumen-ik';
import type { IdName } from '@/types';

type Style = 'butir' | 'huruf' | 'angka' | 'paragraf';

type ServerSection = {
    judul: string;
    gaya: Style;
    pengantar: string;
    butir: string[];
};

type ServerDoc = {
    id: number;
    sistem: string;
    judul: string;
    no_dokumen: string;
    tanggal: string;
    revisi: string;
    halaman: string;
    sections: ServerSection[];
    updated_at: string | null;
};

type Template = {
    key: string;
    judul: string;
    no_dokumen: string;
    deskripsi: string;
    sections: ServerSection[];
};

/** A point of a part, with a stable key for focus & React. */
type Point = { key: number; text: string };

type Section = {
    key: number;
    judul: string;
    gaya: Style;
    pengantar: string;
    points: Point[];
};

type Draft = {
    id: number | null;
    sistem: string;
    judul: string;
    no_dokumen: string;
    tanggal: string;
    revisi: string;
    halaman: string;
    sections: Section[];
};

type Props = {
    unit: IdName;
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    docs: ServerDoc[];
    selected_id: number | null;
    templates: Template[];
    can_write: boolean;
};

const STYLES: { value: Style; label: string; hint: string }[] = [
    { value: 'butir', label: '•', hint: 'Daftar bertanda •' },
    { value: 'huruf', label: 'a.', hint: 'Langkah berurutan huruf (a. b. c.)' },
    { value: 'angka', label: '1.', hint: 'Langkah berurutan angka (1. 2. 3.)' },
    { value: 'paragraf', label: '¶', hint: 'Paragraf biasa tanpa tanda' },
];

const QUICK_SECTIONS: { judul: string; gaya: Style }[] = [
    { judul: 'ALAT', gaya: 'butir' },
    { judul: 'BAHAN', gaya: 'butir' },
    { judul: 'REFERENSI', gaya: 'butir' },
    { judul: 'LANGKAH PELAKSANAAN', gaya: 'huruf' },
    { judul: 'KESELAMATAN KERJA', gaya: 'butir' },
    { judul: 'CATATAN', gaya: 'paragraf' },
];

let nextKey = 1;

const point = (text = ''): Point => ({ key: nextKey++, text });

const toSection = (s: ServerSection): Section => {
    const butir = (s.butir ?? []).filter((b) => b.trim() !== '');

    return {
        key: nextKey++,
        judul: s.judul,
        gaya: s.gaya,
        pengantar: s.pengantar ?? '',
        points: (butir.length ? butir : ['']).map((b) => point(b)),
    };
};

const toDraft = (doc: ServerDoc): Draft => ({
    id: doc.id,
    sistem: doc.sistem,
    judul: doc.judul,
    no_dokumen: doc.no_dokumen,
    tanggal: doc.tanggal,
    revisi: doc.revisi,
    halaman: doc.halaman,
    sections: doc.sections.map(toSection),
});

const today = (): string => new Date().toISOString().slice(0, 10);

/** A pasted line without its own marker ("- ", "• ", "a. ", "1) "). */
const clean = (line: string): string =>
    line.replace(/^\s*(?:[-•*]|[a-z]\.|\d+[.)])\s+/i, '').trim();

const filled = (points: Point[]): string[] =>
    points.map((p) => p.text.trim()).filter(Boolean);

const marker = (gaya: Style, index: number): string =>
    gaya === 'huruf'
        ? `${String.fromCharCode(97 + (index % 26))}.`
        : gaya === 'angka'
          ? `${index + 1}.`
          : gaya === 'butir'
            ? '•'
            : '';

/**
 * Input Dokumen IK K3 (Akses 1): the Instruksi Kerja documents of a period,
 * written straight on one A4 sheet that looks like the print
 * (resources/views/k3/dokumen-ik/document.blade.php). Enter adds a point,
 * Backspace at the start of a point joins it to the previous one, pasting
 * several lines makes several points; each part has its own style (•, a., 1.,
 * ¶) and can be moved. Saved IKs are printed as PDF and attached to the
 * Laporan K3.
 */
export default function DokumenIkPage({
    unit,
    filters,
    options,
    docs,
    selected_id,
    templates,
    can_write,
}: Props) {
    const compact = useCompactLayout();
    const initialDoc =
        docs.find((d) => d.id === selected_id) ?? docs[0] ?? null;
    const [draft, setDraft] = useState<Draft | null>(() =>
        initialDoc ? toDraft(initialDoc) : null,
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [view, setView] = useState<EditorView>('edit');

    // New server data (saved / switched period) replaces the working copy.
    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${selected_id}-${docs.map((d) => `${d.id}:${d.updated_at}`).join(',')}`;
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

    const visit = (patch: Partial<Props['filters']>) => {
        if (confirmLeave()) {
            router.get(
                dokumenIk.index().url,
                { ...filters, ...patch },
                { preserveScroll: true, replace: true },
            );
        }
    };

    const open = (doc: ServerDoc) => {
        if (draft?.id === doc.id || !confirmLeave()) {
            return;
        }

        setDraft(toDraft(doc));
        setDirty(false);
    };

    const startFromTemplate = (template: Template) => {
        if (!confirmLeave()) {
            return;
        }

        setDraft({
            id: null,
            sistem: 'SMK3 LEVEL 3',
            judul: template.judul,
            no_dokumen: template.no_dokumen,
            tanggal: today(),
            revisi: '00',
            halaman: '1/1',
            sections: template.sections.map(toSection),
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
        month: filters.month,
        year: filters.year,
        id: doc.id,
        sistem: doc.sistem,
        judul: doc.judul,
        no_dokumen: doc.no_dokumen,
        tanggal: doc.tanggal || null,
        revisi: doc.revisi,
        halaman: doc.halaman,
        sections: doc.sections.map((s) => ({
            judul: s.judul,
            gaya: s.gaya,
            pengantar: s.pengantar,
            butir: filled(s.points),
        })),
    });

    const save = () => {
        if (!draft || saving || !draft.judul.trim()) {
            return;
        }

        setSaving(true);
        router.post(dokumenIk.store().url, payload(draft), {
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
        if (!draft) {
            return;
        }

        setDraft({
            ...draft,
            id: null,
            judul: `${draft.judul} (Salinan)`,
            sections: draft.sections.map((s) => ({
                ...s,
                key: nextKey++,
                points: s.points.map((p) => point(p.text)),
            })),
        });
        setDirty(true);
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
                `Hapus dokumen "${draft.judul}"? Tindakan ini tidak dapat dibatalkan.`,
            )
        ) {
            router.delete(dokumenIk.destroy(draft.id).url, {
                preserveScroll: true,
            });
        }
    };

    const pdfUrl = (id?: number) =>
        dokumenIk.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...(id ? { doc: id } : {}),
            },
        }).url;

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1] ?? ''} ${filters.year}`;

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
            <Head title={`Dokumen IK K3 — ${unit.name}`} />
            <div
                className={cn(
                    'flex flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                {compact ? (
                    <K3MobileHeader
                        title="Dokumen IK K3"
                        backHref={k3Input.index().url}
                        right={
                            docs.length > 0 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        window.open(
                                            pdfUrl(),
                                            '_blank',
                                            'noopener',
                                        )
                                    }
                                    className="h-8 gap-1 text-xs"
                                >
                                    <Printer className="size-3.5" />
                                    PDF Semua
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <PageHeader
                        title="Dokumen IK K3 Lingkungan Pembangkit"
                        description="Pilih template, lalu klik langsung pada dokumen untuk mengubah isinya — tampilan sama dengan hasil cetak. IK yang tersimpan otomatis dilampirkan di Laporan K3 periode ini."
                        actions={
                            docs.length > 0 ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        window.open(
                                            pdfUrl(),
                                            '_blank',
                                            'noopener',
                                        )
                                    }
                                    className="gap-1.5"
                                >
                                    <Printer className="size-4" />
                                    Cetak Semua IK ({docs.length})
                                </Button>
                            ) : undefined
                        }
                    />
                )}

                {compact ? (
                    <K3MobileFilterCard
                        unitId={filters.unit_id}
                        units={options.units}
                        onUnitChange={(v) => visit({ unit_id: v })}
                        month={filters.month}
                        onMonthChange={(v) => visit({ month: v })}
                        year={filters.year}
                        years={options.years}
                        onYearChange={(v) => visit({ year: v })}
                    />
                ) : (
                    <div className="flex flex-col gap-3 rounded-md border border-border bg-secondary p-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(v) => visit({ unit_id: Number(v) })}
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            className="w-full sm:w-48"
                        />
                        <OperasiSelect
                            label="Bulan Laporan"
                            value={String(filters.month)}
                            onChange={(v) => visit({ month: Number(v) })}
                            options={OPERASI_MONTHS.map((label, i) => ({
                                value: String(i + 1),
                                label,
                            }))}
                            className="w-full sm:w-40"
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            onChange={(v) => visit({ year: Number(v) })}
                            options={options.years.map((y) => ({
                                value: String(y),
                                label: String(y),
                            }))}
                            className="w-full sm:w-32"
                        />
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
                    {/* IK list */}
                    <aside className="flex flex-col gap-2">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">
                                Daftar IK · {periodLabel}
                            </h2>
                            <Badge variant="secondary">{docs.length}</Badge>
                        </div>
                        {can_write && !compact && (
                            <Button
                                onClick={() => setPickerOpen(true)}
                                className="w-full gap-1.5"
                            >
                                <FilePlus2 className="size-4" />
                                Buat IK Baru
                            </Button>
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
                                    title={draft.judul || 'IK baru'}
                                    subtitle="Draf — belum disimpan"
                                    draft
                                    compact={compact}
                                    onClick={() => undefined}
                                />
                            )}
                            {docs.map((doc) => (
                                <ListItem
                                    key={doc.id}
                                    active={draft?.id === doc.id}
                                    title={doc.judul}
                                    subtitle={doc.no_dokumen || 'Tanpa nomor'}
                                    unsaved={draft?.id === doc.id && dirty}
                                    compact={compact}
                                    onClick={() => open(doc)}
                                />
                            ))}
                            {docs.length === 0 && draft === null && (
                                <p className="rounded-md border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                                    Belum ada IK pada periode ini.
                                </p>
                            )}
                        </div>
                        {compact && can_write && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setPickerOpen(true)}
                                className="mt-2 h-9 w-full justify-center gap-1.5 border-dashed text-xs"
                            >
                                <FilePlus2 className="size-4" />
                                Tambah IK Baru
                            </Button>
                        )}
                    </aside>

                    {/* Workspace */}
                    <section className="min-w-0">
                        {draft === null ? (
                            <div className="flex flex-col gap-4 rounded-md border border-dashed border-border p-6 text-center">
                                <FileText className="mx-auto size-10 text-muted-foreground" />
                                <div>
                                    <p className="font-semibold text-foreground">
                                        Mulai dari template IK
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Pilih template, lalu ubah judul, nomor
                                        dokumen dan poin-poinnya langsung pada
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
                                            {draft.judul || 'IK baru'}
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
                                                  : 'Tersimpan · ikut dilampirkan di Laporan K3'}
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

                                <EditorViewTabs
                                    view={view}
                                    onChange={setView}
                                />

                                {view === 'pdf' ? (
                                    <PdfPreview
                                        previewUrl={dokumenIk.preview().url}
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
                                            <EditorTips />
                                        )}

                                        <IkDocumentEditor
                                            key={draft.id ?? 'baru'}
                                            draft={draft}
                                            unitName={unit.name}
                                            editable={can_write}
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
                        <DialogTitle>Pilih Template IK</DialogTitle>
                        <DialogDescription>
                            Semua isi template bisa diubah langsung pada dokumen
                            setelah dipilih. Pilih “Kosong” untuk format standar
                            tanpa isi.
                        </DialogDescription>
                    </DialogHeader>
                    <TemplateGrid
                        templates={templates}
                        onPick={startFromTemplate}
                    />
                </DialogContent>
            </Dialog>

            {compact && can_write && (
                <StickyActionBar>
                    {docs.length > 0 ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                window.open(
                                    draft?.id ? pdfUrl(draft.id) : pdfUrl(),
                                    '_blank',
                                    'noopener',
                                )
                            }
                            className="gap-1.5"
                        >
                            <FileDown className="size-4 text-rose-600" />
                            PDF
                        </Button>
                    ) : (
                        <div aria-hidden="true" />
                    )}
                    {draft ? saveButton : <div aria-hidden="true" />}
                </StickyActionBar>
            )}
        </>
    );
}

/**
 * The official IK form (as printed), edited in place on one A4 sheet: the
 * identity box and every part's title, opening sentence and points are text
 * fields that look like the document. A part's tools (style, move, delete)
 * appear on hover or while one of its fields has the focus.
 */
function IkDocumentEditor({
    draft,
    unitName,
    editable,
    onChange,
}: {
    draft: Draft;
    unitName: string;
    editable: boolean;
    onChange: (patch: Partial<Draft>) => void;
}) {
    const { register, focusNow, focusAfterRender } = useFieldFocus();

    const sections = draft.sections;
    const setSections = (next: Section[]) => onChange({ sections: next });
    const patchSection = (key: number, patch: Partial<Section>) =>
        setSections(
            sections.map((s) => (s.key === key ? { ...s, ...patch } : s)),
        );
    const setPoints = (section: Section, next: Point[]) =>
        patchSection(section.key, { points: next.length ? next : [point()] });

    const moveSection = (index: number, delta: number) => {
        const list = [...sections];
        const target = index + delta;

        if (target < 0 || target >= list.length) {
            return;
        }

        [list[index], list[target]] = [list[target], list[index]];
        setSections(list);
    };

    const removeSection = (section: Section) => {
        if (
            filled(section.points).length === 0 ||
            window.confirm(
                `Hapus bagian "${section.judul || 'tanpa judul'}" beserta isinya?`,
            )
        ) {
            setSections(sections.filter((s) => s.key !== section.key));
        }
    };

    const addSection = (judul = '', gaya: Style = 'butir') => {
        const created: Section = {
            key: nextKey++,
            judul,
            gaya,
            pengantar: '',
            points: [point()],
        };
        setSections([...sections, created]);
        focusAfterRender({
            id: judul ? `p-${created.points[0].key}` : `j-${created.key}`,
            at: 'end',
        });
    };

    const pointKeyDown =
        (section: Section, index: number) =>
        (event: KeyboardEvent<HTMLTextAreaElement>) => {
            const field = event.currentTarget;
            const current = section.points[index];

            if (event.key === 'Enter' && !event.shiftKey) {
                // Split at the caret: the rest of the line becomes the next point.
                event.preventDefault();
                const created = point(field.value.slice(field.selectionEnd));
                const next = [...section.points];
                next.splice(
                    index,
                    1,
                    {
                        ...current,
                        text: field.value.slice(0, field.selectionStart),
                    },
                    created,
                );
                setPoints(section, next);
                focusAfterRender({ id: `p-${created.key}`, at: 'start' });

                return;
            }

            if (
                event.key === 'Backspace' &&
                field.selectionStart === 0 &&
                field.selectionEnd === 0 &&
                index > 0
            ) {
                // At the start of a point: join it to the previous one.
                event.preventDefault();
                const previous = section.points[index - 1];
                const next = [...section.points];
                next.splice(index - 1, 2, {
                    ...previous,
                    text: previous.text + current.text,
                });
                setPoints(section, next);
                focusAfterRender({ id: `p-${previous.key}`, at: 'end' });

                return;
            }

            if (
                event.key === 'ArrowUp' &&
                field.selectionStart === 0 &&
                index > 0
            ) {
                event.preventDefault();
                focusNow({
                    id: `p-${section.points[index - 1].key}`,
                    at: 'end',
                });
            } else if (
                event.key === 'ArrowDown' &&
                field.selectionStart === field.value.length &&
                index < section.points.length - 1
            ) {
                event.preventDefault();
                focusNow({
                    id: `p-${section.points[index + 1].key}`,
                    at: 'start',
                });
            }
        };

    const pointPaste =
        (section: Section, index: number) =>
        (event: ClipboardEvent<HTMLTextAreaElement>) => {
            const lines = event.clipboardData
                .getData('text')
                .split(/\r?\n/)
                .map(clean)
                .filter(Boolean);

            if (lines.length < 2) {
                return;
            }

            // Several lines pasted: one point each.
            event.preventDefault();
            const field = event.currentTarget;
            const current = section.points[index];
            const before = field.value.slice(0, field.selectionStart);
            const after = field.value.slice(field.selectionEnd);
            const created = lines.map((line, i) =>
                i === 0 ? { ...current, text: before + line } : point(line),
            );
            const last = created.length - 1;
            created[last] = {
                ...created[last],
                text: created[last].text + after,
            };
            const next = [...section.points];
            next.splice(index, 1, ...created);
            setPoints(section, next);
            focusAfterRender({ id: `p-${created[last].key}`, at: 'end' });
        };

    const identity: [
        'no_dokumen' | 'tanggal' | 'revisi' | 'halaman',
        string,
        string,
    ][] = [
        ['no_dokumen', 'No. Dokumen', 'SMK3/IK/..'],
        ['tanggal', 'Tanggal', ''],
        ['revisi', 'Revisi', '00'],
        ['halaman', 'Halaman', '1/1'],
    ];

    return (
        <A4Sheet padding="sm:p-[14mm]">
            <div className="flex flex-1 flex-col border border-black text-[12px] leading-relaxed">
                {/* Kop (fixed) */}
                <div className="grid grid-cols-[22%_1fr_22%] items-center border-b border-black">
                    <div className="p-2">
                        <img
                            src="/logo/sidebar-logo.png"
                            alt="PLN Nusantara Power"
                            className="max-h-9 object-contain"
                        />
                    </div>
                    <div className="py-2 text-center text-[10px] leading-snug sm:text-[11px]">
                        <div>
                            JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE
                            -KIT
                        </div>
                        <div>{unitName.toUpperCase()}</div>
                        <div>LAPORAN PROJECT</div>
                        <div>DOKUMEN IK K3 LINGKUNGAN PEMBANGKIT</div>
                    </div>
                    <div className="p-2 text-right">
                        <img
                            src="/logo/mkp.jpg"
                            alt="MKP"
                            className="ml-auto max-h-9 object-contain"
                        />
                    </div>
                </div>
                <div className="h-3 border-b border-black" />

                {/* Identity box */}
                <div className="grid grid-cols-1 border-b border-black sm:grid-cols-[15%_1fr_36%]">
                    <div className="flex items-center justify-center border-b border-black p-1.5 sm:border-r sm:border-b-0">
                        <AutoText
                            value={draft.sistem}
                            onValue={(v) => onChange({ sistem: v })}
                            disabled={!editable}
                            placeholder="SMK3 LEVEL 3"
                            className={cn(EDITABLE, 'text-center text-[11px]')}
                            aria-label="Sistem / Level"
                        />
                    </div>
                    <div className="flex items-center justify-center border-b border-black p-2 sm:border-r sm:border-b-0">
                        <AutoText
                            value={draft.judul}
                            onValue={(v) =>
                                onChange({ judul: v.toUpperCase() })
                            }
                            disabled={!editable}
                            placeholder="JUDUL INSTRUKSI KERJA (wajib)"
                            className={cn(
                                EDITABLE,
                                'text-center text-[13px] font-semibold uppercase',
                                !draft.judul.trim() && 'bg-red-50',
                            )}
                            aria-label="Judul Instruksi Kerja"
                        />
                    </div>
                    <div>
                        {identity.map(([field, label, placeholder], i) => (
                            <div
                                key={field}
                                className={cn(
                                    'grid grid-cols-[36%_1fr] items-center',
                                    i < identity.length - 1 &&
                                        'border-b border-black',
                                )}
                            >
                                <span className="self-stretch border-r border-black px-1.5 py-0.5">
                                    {label}
                                </span>
                                <span className="flex items-center gap-0.5 px-1">
                                    :
                                    <input
                                        type={
                                            field === 'tanggal'
                                                ? 'date'
                                                : 'text'
                                        }
                                        value={draft[field]}
                                        onChange={(e) =>
                                            onChange({
                                                [field]: e.target.value,
                                            })
                                        }
                                        disabled={!editable}
                                        placeholder={placeholder}
                                        className={cn(EDITABLE, 'h-6 py-0')}
                                        aria-label={label}
                                    />
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 px-3 pt-3 pb-6 sm:px-5">
                    {sections.length === 0 && (
                        <p className="text-neutral-500">
                            Belum ada bagian. Tambahkan bagian dengan tombol di
                            bawah.
                        </p>
                    )}

                    {sections.map((section, sIndex) => (
                        <div
                            key={section.key}
                            className={cn(
                                'group/section relative rounded-sm',
                                sIndex === 0 ? 'mt-0.5' : 'mt-4',
                                editable && PART_FRAME,
                            )}
                        >
                            <div className="flex items-start gap-1">
                                <span className="shrink-0 pt-px">
                                    {sIndex + 1}.
                                </span>
                                <AutoText
                                    ref={register(`j-${section.key}`)}
                                    value={section.judul}
                                    onValue={(v) =>
                                        patchSection(section.key, {
                                            judul: v.toUpperCase(),
                                        })
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            focusNow({
                                                id: `p-${section.points[0].key}`,
                                                at: 'start',
                                            });
                                        }
                                    }}
                                    disabled={!editable}
                                    placeholder="JUDUL BAGIAN (mis. ALAT)"
                                    className={cn(
                                        EDITABLE,
                                        'font-medium uppercase',
                                    )}
                                    aria-label={`Judul bagian ${sIndex + 1}`}
                                />

                                {editable && (
                                    <div className={PART_TOOLS}>
                                        {STYLES.map((style) => (
                                            <ToolButton
                                                key={style.value}
                                                label={`Gaya: ${style.hint}`}
                                                active={
                                                    section.gaya === style.value
                                                }
                                                onClick={() =>
                                                    patchSection(section.key, {
                                                        gaya: style.value,
                                                    })
                                                }
                                            >
                                                {style.label}
                                            </ToolButton>
                                        ))}
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
                                                sIndex === sections.length - 1
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

                            {/* Opening sentence (optional): shown when filled, or while editing the part. */}
                            {(section.pengantar.trim() !== '' || editable) && (
                                <div
                                    className={cn(
                                        'ml-4',
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
                                        placeholder="+ Kalimat pembuka (opsional), mis. “Bila terdengar bunyi sirine … maka segera dilakukan :”"
                                        className={cn(EDITABLE, 'mt-1')}
                                        aria-label={`Kalimat pembuka bagian ${sIndex + 1}`}
                                    />
                                </div>
                            )}

                            {section.points.map((p, pIndex) => (
                                <div
                                    key={p.key}
                                    className="mt-0.5 ml-4 flex items-start gap-1"
                                >
                                    {section.gaya !== 'paragraf' && (
                                        <span className="w-5 shrink-0 pt-px">
                                            {marker(section.gaya, pIndex)}
                                        </span>
                                    )}
                                    <AutoText
                                        ref={register(`p-${p.key}`)}
                                        value={p.text}
                                        onValue={(v) =>
                                            setPoints(
                                                section,
                                                section.points.map((x) =>
                                                    x.key === p.key
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
                                        onKeyDown={pointKeyDown(
                                            section,
                                            pIndex,
                                        )}
                                        onPaste={pointPaste(section, pIndex)}
                                        disabled={!editable}
                                        placeholder={
                                            pIndex === 0
                                                ? section.gaya === 'paragraf'
                                                    ? 'Tulis paragraf…'
                                                    : 'Tulis poin pertama, Enter untuk poin berikutnya…'
                                                : 'Poin…'
                                        }
                                        className={EDITABLE}
                                        aria-label={`Bagian ${sIndex + 1}, poin ${pIndex + 1}`}
                                    />
                                    {editable && section.points.length > 1 && (
                                        <button
                                            type="button"
                                            tabIndex={-1}
                                            title="Hapus poin"
                                            aria-label={`Hapus poin ${pIndex + 1}`}
                                            onClick={() =>
                                                setPoints(
                                                    section,
                                                    section.points.filter(
                                                        (x) => x.key !== p.key,
                                                    ),
                                                )
                                            }
                                            className="mt-0.5 shrink-0 rounded p-0.5 text-neutral-300 opacity-0 group-hover/section:opacity-100 hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                            ))}

                            {editable && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        const created = point();
                                        setPoints(section, [
                                            ...section.points,
                                            created,
                                        ]);
                                        focusAfterRender({
                                            id: `p-${created.key}`,
                                            at: 'start',
                                        });
                                    }}
                                    className="mt-0.5 ml-4 hidden items-center gap-1 rounded px-1 text-[11px] text-sky-700 group-focus-within/section:inline-flex group-hover/section:inline-flex hover:bg-sky-50"
                                >
                                    <Plus className="size-3" />
                                    Tambah{' '}
                                    {section.gaya === 'paragraf'
                                        ? 'paragraf'
                                        : 'poin'}
                                </button>
                            )}
                        </div>
                    ))}

                    {editable && (
                        <AddPartBar>
                            {QUICK_SECTIONS.filter(
                                (q) =>
                                    !sections.some((s) => s.judul === q.judul),
                            ).map((q) => (
                                <AddPartButton
                                    key={q.judul}
                                    onClick={() => addSection(q.judul, q.gaya)}
                                >
                                    {q.judul}
                                </AddPartButton>
                            ))}
                            <AddPartButton onClick={() => addSection()}>
                                Bagian lain
                            </AddPartButton>
                        </AddPartBar>
                    )}
                </div>
            </div>
        </A4Sheet>
    );
}

function AddPartBar({ children }: { children: ReactNode }) {
    return (
        <div className="mt-6 flex flex-wrap items-center gap-1.5 border-t border-dashed border-neutral-300 pt-3">
            <span className="text-[11px] text-neutral-500">Tambah bagian:</span>
            {children}
        </div>
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
                    (n, s) => n + s.butir.filter(Boolean).length,
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
                            {t.key === 'kosong'
                                ? 'Kosong (format standar)'
                                : t.judul.replace('INSTRUKSI KERJA ', '')}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {t.deskripsi}
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            {t.sections.map((s) => s.judul).join(' · ')}
                            {pointCount > 0 ? ` — ${pointCount} poin` : ''}
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
                compact ? 'w-56 shrink-0' : 'w-full',
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
                {title.replace('INSTRUKSI KERJA ', 'IK ')}
            </span>
            <span className="truncate text-[11px] text-muted-foreground">
                {subtitle}
            </span>
        </button>
    );
}

DokumenIkPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Dokumen IK K3', href: dokumenIk.index() },
    ],
};
