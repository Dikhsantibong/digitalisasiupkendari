import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Copy, Eye, FileDown, FilePlus2, FileText, PencilLine, Plus, Printer, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import dokumenIk from '@/routes/k3/input/dokumen-ik';
import type { IdName } from '@/types';

type Style = 'butir' | 'huruf' | 'angka' | 'paragraf';

type ServerSection = { judul: string; gaya: Style; pengantar: string; butir: string[] };

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

type Template = { key: string; judul: string; no_dokumen: string; deskripsi: string; sections: ServerSection[] };

/** On screen the points of a section are one text: a line per point. */
type Section = { key: number; judul: string; gaya: Style; pengantar: string; teks: string };

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
    { value: 'butir', label: '• Butir', hint: 'Daftar bertanda •' },
    { value: 'huruf', label: 'a. b. c.', hint: 'Langkah berurutan huruf' },
    { value: 'angka', label: '1. 2. 3.', hint: 'Langkah berurutan angka' },
    { value: 'paragraf', label: '¶ Paragraf', hint: 'Kalimat biasa tanpa tanda' },
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

const toSection = (s: ServerSection): Section => ({ key: nextKey++, judul: s.judul, gaya: s.gaya, pengantar: s.pengantar ?? '', teks: (s.butir ?? []).join('\n') });

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

const points = (teks: string): string[] =>
    teks
        .split('\n')
        .map((line) => line.replace(/^\s*(?:[-•*]|[a-z]\.|\d+[.)])\s+/i, '').trim())
        .filter(Boolean);

const marker = (gaya: Style, index: number): string => (gaya === 'huruf' ? `${String.fromCharCode(97 + (index % 26))}.` : gaya === 'angka' ? `${index + 1}.` : gaya === 'butir' ? '•' : '');

const formatDate = (iso: string): string => {
    if (!iso) {
        return '';
    }

    const [y, m, d] = iso.split('-');

    return `${d}-${m}-${y}`;
};

/**
 * Input Dokumen IK K3 (Akses 1): the Instruksi Kerja documents of a period.
 * Pick a template, adjust the identity and the numbered parts (a line per
 * point), and see the official form live on the right. Saved IKs are printed
 * as PDF and attached to the Laporan K3.
 */
export default function DokumenIkPage({ unit, filters, options, docs, selected_id, templates, can_write }: Props) {
    const compact = useCompactLayout();
    const initialDoc = docs.find((d) => d.id === selected_id) ?? docs[0] ?? null;
    const [draft, setDraft] = useState<Draft | null>(() => (initialDoc ? toDraft(initialDoc) : null));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [mobileView, setMobileView] = useState<'isi' | 'pratinjau'>('isi');

    // New server data (saved / switched period) replaces the working copy.
    const signature = `${filters.unit_id}-${filters.month}-${filters.year}-${selected_id}-${docs.map((d) => `${d.id}:${d.updated_at}`).join(',')}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setDraft(initialDoc ? toDraft(initialDoc) : null);
        setDirty(false);
    }

    const confirmLeave = (): boolean => !dirty || window.confirm('Perubahan pada IK ini belum disimpan. Tetap lanjut?');

    const visit = (patch: Partial<Props['filters']>) => {
        if (confirmLeave()) {
            router.get(dokumenIk.index().url, { ...filters, ...patch }, { preserveScroll: true, replace: true });
        }
    };

    const open = (doc: ServerDoc) => {
        if (draft?.id === doc.id || !confirmLeave()) {
            return;
        }

        setDraft(toDraft(doc));
        setDirty(false);
        setMobileView('isi');
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
        setMobileView('isi');
    };

    const change = (patch: Partial<Draft>) => {
        setDraft((prev) => (prev ? { ...prev, ...patch } : prev));
        setDirty(true);
    };

    const changeSection = (key: number, patch: Partial<Section>) =>
        change({ sections: (draft?.sections ?? []).map((s) => (s.key === key ? { ...s, ...patch } : s)) });

    const moveSection = (index: number, delta: number) => {
        const list = [...(draft?.sections ?? [])];
        const target = index + delta;

        if (target < 0 || target >= list.length) {
            return;
        }

        [list[index], list[target]] = [list[target], list[index]];
        change({ sections: list });
    };

    const removeSection = (key: number) => {
        const section = draft?.sections.find((s) => s.key === key);

        if (section && (section.teks.trim() === '' || window.confirm(`Hapus bagian "${section.judul}"?`))) {
            change({ sections: (draft?.sections ?? []).filter((s) => s.key !== key) });
        }
    };

    const addSection = (judul = '', gaya: Style = 'butir') =>
        change({ sections: [...(draft?.sections ?? []), { key: nextKey++, judul, gaya, pengantar: '', teks: '' }] });

    const save = () => {
        if (!draft) {
            return;
        }

        setSaving(true);
        router.post(
            dokumenIk.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                id: draft.id,
                sistem: draft.sistem,
                judul: draft.judul,
                no_dokumen: draft.no_dokumen,
                tanggal: draft.tanggal || null,
                revisi: draft.revisi,
                halaman: draft.halaman,
                sections: draft.sections.map((s) => ({ judul: s.judul, gaya: s.gaya, pengantar: s.pengantar, butir: points(s.teks) })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const duplicate = () => {
        if (!draft) {
            return;
        }

        setDraft({ ...draft, id: null, judul: `${draft.judul} (Salinan)`, sections: draft.sections.map((s) => ({ ...s, key: nextKey++ })) });
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

        if (window.confirm(`Hapus dokumen "${draft.judul}"? Tindakan ini tidak dapat dibatalkan.`)) {
            router.delete(dokumenIk.destroy(draft.id).url, { preserveScroll: true });
        }
    };

    const pdfUrl = (id?: number) => dokumenIk.pdf({ query: { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...(id ? { doc: id } : {}) } }).url;

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1] ?? ''} ${filters.year}`;

    const saveButton = can_write && draft && (
        <Button onClick={save} disabled={saving || !dirty || !draft.judul.trim()} className="gap-1.5">
            <Save className="size-4" />
            {saving ? 'Menyimpan…' : draft.id === null ? 'Simpan IK Baru' : 'Simpan'}
        </Button>
    );

    const editor = draft && (
        <div className="flex flex-col gap-4">
            <Card title="1. Identitas Dokumen" hint="Muncul di kotak kepala dokumen IK.">
                <Field label="Judul Instruksi Kerja">
                    <Input value={draft.judul} onChange={(e) => change({ judul: e.target.value })} disabled={!can_write} placeholder="mis. INSTRUKSI KERJA PELAKSANAAN EVAKUASI" className="font-semibold" />
                </Field>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <Field label="Sistem / Level">
                        <Input value={draft.sistem} onChange={(e) => change({ sistem: e.target.value })} disabled={!can_write} />
                    </Field>
                    <Field label="No. Dokumen">
                        <Input value={draft.no_dokumen} onChange={(e) => change({ no_dokumen: e.target.value })} disabled={!can_write} placeholder="SMK3/IK/.." />
                    </Field>
                    <Field label="Tanggal">
                        <Input type="date" value={draft.tanggal} onChange={(e) => change({ tanggal: e.target.value })} disabled={!can_write} />
                    </Field>
                    <Field label="Revisi">
                        <Input value={draft.revisi} onChange={(e) => change({ revisi: e.target.value })} disabled={!can_write} placeholder="00" />
                    </Field>
                    <Field label="Halaman">
                        <Input value={draft.halaman} onChange={(e) => change({ halaman: e.target.value })} disabled={!can_write} placeholder="1/1" />
                    </Field>
                </div>
            </Card>

            <Card title="2. Isi Instruksi Kerja" hint="Tulis satu poin per baris — tanda •, a. b. c. atau 1. 2. 3. dibuat otomatis sesuai gaya.">
                {draft.sections.map((section, index) => (
                    <div key={section.key} className="flex flex-col gap-2 rounded-lg border border-border bg-muted/20 p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-[13px] font-bold text-primary-foreground">{index + 1}</span>
                            <Input
                                value={section.judul}
                                onChange={(e) => changeSection(section.key, { judul: e.target.value.toUpperCase() })}
                                disabled={!can_write}
                                placeholder="JUDUL BAGIAN (mis. ALAT)"
                                className="h-9 min-w-40 flex-1 font-semibold uppercase"
                                aria-label={`Judul bagian ${index + 1}`}
                            />
                            <Select value={section.gaya} onValueChange={(v) => changeSection(section.key, { gaya: v as Style })} disabled={!can_write}>
                                <SelectTrigger className="h-9 w-32" aria-label={`Gaya poin bagian ${index + 1}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STYLES.map((s) => (
                                        <SelectItem key={s.value} value={s.value} title={s.hint}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {can_write && (
                                <div className="flex items-center">
                                    <IconButton label="Naikkan bagian" onClick={() => moveSection(index, -1)} disabled={index === 0}>
                                        <ArrowUp className="size-4" />
                                    </IconButton>
                                    <IconButton label="Turunkan bagian" onClick={() => moveSection(index, 1)} disabled={index === draft.sections.length - 1}>
                                        <ArrowDown className="size-4" />
                                    </IconButton>
                                    <IconButton label="Hapus bagian" onClick={() => removeSection(section.key)} danger>
                                        <Trash2 className="size-4" />
                                    </IconButton>
                                </div>
                            )}
                        </div>
                        <Textarea
                            value={section.pengantar}
                            onChange={(e) => changeSection(section.key, { pengantar: e.target.value })}
                            disabled={!can_write}
                            rows={section.pengantar ? 2 : 1}
                            placeholder="(Opsional) Kalimat pembuka sebelum poin, mis. “Bila terdengar bunyi sirine … maka segera dilakukan :”"
                            className="min-h-9 text-sm"
                        />
                        <Textarea
                            value={section.teks}
                            onChange={(e) => changeSection(section.key, { teks: e.target.value })}
                            disabled={!can_write}
                            rows={Math.max(3, section.teks.split('\n').length + 1)}
                            placeholder={section.gaya === 'paragraf' ? 'Tulis paragraf; satu baris = satu paragraf' : 'Satu baris = satu poin\nmis.\nTandu\nKendaraan'}
                            className="font-mono text-sm leading-6"
                            aria-label={`Poin bagian ${index + 1}`}
                        />
                        <p className="text-[11px] text-muted-foreground">{points(section.teks).length} poin</p>
                    </div>
                ))}

                {can_write && (
                    <div className="flex flex-col gap-2 rounded-lg border border-dashed border-border p-3">
                        <span className="text-xs font-medium text-muted-foreground">Tambah bagian:</span>
                        <div className="flex flex-wrap gap-1.5">
                            {QUICK_SECTIONS.filter((q) => !draft.sections.some((s) => s.judul === q.judul)).map((q) => (
                                <Button key={q.judul} variant="outline" size="sm" onClick={() => addSection(q.judul, q.gaya)} className="h-8 gap-1 text-xs">
                                    <Plus className="size-3.5" />
                                    {q.judul}
                                </Button>
                            ))}
                            <Button variant="outline" size="sm" onClick={() => addSection()} className="h-8 gap-1 text-xs">
                                <Plus className="size-3.5" />
                                Bagian lain
                            </Button>
                        </div>
                    </div>
                )}
            </Card>
        </div>
    );

    const preview = draft && (
        <div className="2xl:sticky 2xl:top-4">
            <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Eye className="size-3.5" />
                Pratinjau dokumen (sama dengan hasil cetak)
            </div>
            <IkPreview draft={draft} unitName={unit.name} />
        </div>
    );

    return (
        <>
            <Head title={`Dokumen IK K3 — ${unit.name}`} />
            <div className={cn('flex flex-1 flex-col gap-4 p-4 md:p-6', compact && can_write && 'pb-28')}>
                <PageHeader
                    title="Dokumen IK K3 Lingkungan Pembangkit"
                    description="Susun Instruksi Kerja (IK) dari template, sesuaikan isinya, lalu cetak. IK yang tersimpan otomatis dilampirkan di Laporan K3 periode ini."
                    actions={
                        docs.length > 0 ? (
                            <Button variant="outline" onClick={() => window.open(pdfUrl(), '_blank', 'noopener')} className="gap-1.5">
                                <Printer className="size-4" />
                                Cetak Semua IK ({docs.length})
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-full sm:w-48" />
                    <OperasiSelect label="Bulan Laporan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, i) => ({ value: String(i + 1), label }))} className="w-full sm:w-40" />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} className="w-full sm:w-32" />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    {/* IK list */}
                    <aside className="flex flex-col gap-2">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">Daftar IK · {periodLabel}</h2>
                            <Badge variant="secondary">{docs.length}</Badge>
                        </div>
                        {can_write && (
                            <Button onClick={() => setPickerOpen(true)} className="w-full gap-1.5">
                                <FilePlus2 className="size-4" />
                                Buat IK Baru
                            </Button>
                        )}
                        <div className={cn('flex gap-2', compact ? '-mx-4 overflow-x-auto px-4 pb-1' : 'flex-col')}>
                            {draft?.id === null && (
                                <ListItem active title={draft.judul || 'IK baru'} subtitle="Draf — belum disimpan" draft compact={compact} onClick={() => undefined} />
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
                            {docs.length === 0 && draft === null && <p className="rounded-lg border border-dashed border-border p-4 text-center text-xs text-muted-foreground">Belum ada IK pada periode ini.</p>}
                        </div>
                    </aside>

                    {/* Workspace */}
                    <section className="min-w-0">
                        {draft === null ? (
                            <div className="flex flex-col gap-4 rounded-xl border border-dashed border-border p-6 text-center">
                                <FileText className="mx-auto size-10 text-muted-foreground" />
                                <div>
                                    <p className="font-semibold text-foreground">Mulai dari template IK</p>
                                    <p className="text-sm text-muted-foreground">Pilih template, lalu sesuaikan judul, nomor dokumen dan poin-poinnya.</p>
                                </div>
                                {can_write && <TemplateGrid templates={templates} onPick={startFromTemplate} />}
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-card p-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">{draft.judul || 'IK baru'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {draft.id === null ? 'Belum disimpan' : dirty ? 'Ada perubahan belum disimpan' : 'Tersimpan · ikut dilampirkan di Laporan K3'}
                                        </p>
                                    </div>
                                    {draft.id !== null && (
                                        <Button variant="outline" size="sm" onClick={() => window.open(pdfUrl(draft.id ?? undefined), '_blank', 'noopener')} className="gap-1.5" title={dirty ? 'PDF memakai data yang sudah tersimpan' : undefined}>
                                            <FileDown className="size-4" />
                                            PDF
                                        </Button>
                                    )}
                                    {can_write && (
                                        <>
                                            <Button variant="outline" size="sm" onClick={duplicate} className="gap-1.5">
                                                <Copy className="size-4" />
                                                Duplikat
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={remove} className="gap-1.5 text-muted-foreground hover:text-destructive">
                                                <Trash2 className="size-4" />
                                                Hapus
                                            </Button>
                                            {!compact && saveButton}
                                        </>
                                    )}
                                </div>

                                {/* Phone & tablet: switch between the form and the preview. */}
                                <div className="flex gap-1 rounded-lg bg-muted p-1 2xl:hidden">
                                    {(['isi', 'pratinjau'] as const).map((view) => (
                                        <button
                                            key={view}
                                            type="button"
                                            onClick={() => setMobileView(view)}
                                            className={cn('flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 text-sm font-medium transition', mobileView === view ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground')}
                                        >
                                            {view === 'isi' ? <PencilLine className="size-4" /> : <Eye className="size-4" />}
                                            {view === 'isi' ? 'Isi IK' : 'Pratinjau'}
                                        </button>
                                    ))}
                                </div>

                                <div className="grid grid-cols-1 gap-4 2xl:grid-cols-2">
                                    <div className={cn(mobileView !== 'isi' && 'hidden 2xl:block')}>{editor}</div>
                                    <div className={cn(mobileView !== 'pratinjau' && 'hidden 2xl:block')}>{preview}</div>
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>

            <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Pilih Template IK</DialogTitle>
                        <DialogDescription>Semua isi template bisa diubah setelah dipilih. Pilih “Kosong” untuk format standar tanpa isi.</DialogDescription>
                    </DialogHeader>
                    <TemplateGrid templates={templates} onPick={startFromTemplate} />
                </DialogContent>
            </Dialog>

            {compact && can_write && draft && <StickyActionBar>{saveButton}</StickyActionBar>}
        </>
    );
}

/** The official IK form, as printed (same layout as resources/views/k3/dokumen-ik/document.blade.php). */
function IkPreview({ draft, unitName }: { draft: Draft; unitName: string }) {
    return (
        <div className="overflow-x-auto rounded-md bg-neutral-200 p-3 dark:bg-neutral-800">
            <div className="mx-auto min-w-[520px] max-w-[720px] border border-black bg-white text-[11px] leading-relaxed text-black shadow-md">
                <div className="grid grid-cols-[20%_1fr_20%] items-center border-b border-black">
                    <div className="p-2">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-9 object-contain" />
                    </div>
                    <div className="py-2 text-center leading-snug">
                        <div>JASA PENDUKUNG TEKNIS UP KENDARI 11 &amp; 6 SITE -KIT</div>
                        <div>{unitName.toUpperCase()}</div>
                        <div>LAPORAN PROJECT</div>
                        <div>DOKUMEN IK K3 LINGKUNGAN PEMBANGKIT</div>
                    </div>
                    <div className="p-2 text-right">
                        <img src="/logo/mkp.jpg" alt="MKP" className="ml-auto max-h-9 object-contain" />
                    </div>
                </div>
                <div className="h-3 border-b border-black" />
                <div className="grid grid-cols-[13%_1fr_34%] border-b border-black">
                    <div className="flex items-center justify-center border-r border-black p-1.5 text-center whitespace-pre-line">{draft.sistem.replace(' LEVEL', '\nLEVEL')}</div>
                    <div className="flex items-center justify-center border-r border-black p-2 text-center text-[12px]">{draft.judul.toUpperCase() || '—'}</div>
                    <div>
                        {[
                            ['No. Dokumen', draft.no_dokumen],
                            ['Tanggal', formatDate(draft.tanggal)],
                            ['Revisi', draft.revisi],
                            ['Halaman', draft.halaman],
                        ].map(([label, value], i) => (
                            <div key={label} className={cn('grid grid-cols-[34%_1fr]', i < 3 && 'border-b border-black')}>
                                <span className="border-r border-black px-1.5 py-0.5">{label}</span>
                                <span className="px-1.5 py-0.5">: {value}</span>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="min-h-64 px-4 pt-3 pb-5">
                    {draft.sections.length === 0 && <p className="text-neutral-500">Belum ada bagian. Tambahkan bagian di sebelah kiri.</p>}
                    {draft.sections.map((section, sIndex) => (
                        <div key={section.key} className={sIndex === 0 ? 'mt-0.5' : 'mt-3'}>
                            <div>
                                {sIndex + 1}. {section.judul.toUpperCase() || '…'}
                            </div>
                            {section.pengantar.trim() && <p className="mt-1.5 mb-1 ml-4">{section.pengantar}</p>}
                            {points(section.teks).map((point, pIndex) =>
                                section.gaya === 'paragraf' ? (
                                    <p key={pIndex} className="mt-1 ml-4">
                                        {point}
                                    </p>
                                ) : (
                                    <div key={pIndex} className="mt-1 ml-4 flex gap-1.5">
                                        <span className="w-4 shrink-0">{marker(section.gaya, pIndex)}</span>
                                        <span>{point}</span>
                                    </div>
                                ),
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function TemplateGrid({ templates, onPick }: { templates: Template[]; onPick: (template: Template) => void }) {
    return (
        <div className="grid grid-cols-1 gap-3 text-left sm:grid-cols-2">
            {templates.map((t) => {
                const pointCount = t.sections.reduce((n, s) => n + s.butir.filter(Boolean).length, 0);

                return (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => onPick(t)}
                        className="flex flex-col gap-1.5 rounded-lg border border-border bg-card p-3 text-left transition hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="text-sm font-semibold text-foreground">{t.key === 'kosong' ? 'Kosong (format standar)' : t.judul.replace('INSTRUKSI KERJA ', '')}</span>
                        <span className="text-xs text-muted-foreground">{t.deskripsi}</span>
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

function ListItem({ title, subtitle, active, unsaved = false, draft = false, compact, onClick }: { title: string; subtitle: string; active: boolean; unsaved?: boolean; draft?: boolean; compact: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-col gap-0.5 rounded-lg border p-2.5 text-left transition',
                compact ? 'w-56 shrink-0' : 'w-full',
                active ? 'border-primary bg-primary/10' : 'border-border bg-card hover:bg-muted',
                draft && 'border-dashed',
            )}
        >
            <span className="line-clamp-2 text-[13px] font-medium text-foreground">
                {unsaved && <span className="mr-1 inline-block size-2 rounded-full bg-amber-500 align-middle" />}
                {title.replace('INSTRUKSI KERJA ', 'IK ')}
            </span>
            <span className="truncate text-[11px] text-muted-foreground">{subtitle}</span>
        </button>
    );
}

function Card({ title, hint, children }: { title: string; hint: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4">
            <div>
                <h3 className="text-sm font-semibold text-foreground">{title}</h3>
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

function IconButton({ label, onClick, disabled = false, danger = false, children }: { label: string; onClick: () => void; disabled?: boolean; danger?: boolean; children: ReactNode }) {
    return (
        <Button type="button" variant="ghost" size="icon" onClick={onClick} disabled={disabled} title={label} aria-label={label} className={cn('size-9 text-muted-foreground', danger ? 'hover:text-destructive' : 'hover:text-foreground')}>
            {children}
        </Button>
    );
}

DokumenIkPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Dokumen IK K3', href: dokumenIk.index() },
    ],
};
