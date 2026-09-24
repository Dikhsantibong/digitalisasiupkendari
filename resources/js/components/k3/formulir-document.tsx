import {
    AlertCircle,
    ArrowLeft,
    Download,
    FileCode2,
    History,
    Info,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { RichTextEditor } from '@/components/rich-text-editor';
import { StatusBadge } from '@/components/status-badge';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type SignerOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
};

export type DocumentFormat = 'form' | 'html';

export type DocumentSettings = {
    format: DocumentFormat;
    document_number: string;
    revision: string;
    effective_date: string;
    manager_ul_id: number | null;
    manager_ul_name: string;
    manager_ul_title: string;
    tl_k3_id: number | null;
    tl_k3_name: string;
    tl_k3_title: string;
    staff_k3_id: number | null;
    staff_k3_name: string;
    staff_k3_title: string;
    sign_place_date: string;
    page_margin_top: number;
    page_margin_bottom: number;
    page_margin_left: number;
    page_margin_right: number;
    line_spacing: string;
};

/** Props bersama yang dikirim K3FormulirDocumentBuilder::pageProps(). */
export type FormulirDocumentProps = {
    document: DocumentSettings;
    rendered_html: string;
    generated_html: string;
    document_styles: string;
    signers: { key: SignerKey; label: string }[];
    signer_options: Partial<Record<SignerKey, SignerOption[]>>;
    pdf_url: string;
};

export type ViewTab = 'form' | 'html' | 'pdf';

export type SignerKey = 'manager_ul' | 'tl_k3' | 'staff_k3';

const PAGE_PARAMS = [
    'page_margin_top',
    'page_margin_bottom',
    'page_margin_left',
    'page_margin_right',
    'line_spacing',
] as const;

/**
 * State dokumen resmi (kop, penandatangan, layout, editor teks, pratinjau PDF)
 * yang dipakai bersama oleh semua halaman Formulir K3.
 */
export function useFormulirDocument(props: FormulirDocumentProps, markDirty: () => void) {
    const [settings, setSettings] = useState<DocumentSettings>(props.document);
    const [htmlContent, setHtmlContent] = useState<string>(props.rendered_html);
    const [format, setFormat] = useState<DocumentFormat>(props.document.format);
    const [viewTab, setViewTab] = useState<ViewTab>(props.document.format === 'html' ? 'html' : 'form');
    const [previewKey, setPreviewKey] = useState<number>(0);

    const update = (patch: Partial<DocumentSettings>) => {
        setSettings((prev) => ({ ...prev, ...patch }));
        markDirty();
    };

    const changeTab = (tab: ViewTab) => {
        setViewTab(tab);

        if (tab !== 'pdf' && tab !== format) {
            setFormat(tab);
            markDirty();
        }
    };

    const updateHtml = (html: string) => {
        setHtmlContent(html);
        markDirty();
    };

    const reset = () => {
        setSettings(props.document);
        setHtmlContent(props.rendered_html);
        setFormat(props.document.format);
    };

    const pdfUrlWith = (extra: Record<string, string>) => {
        const params = new URLSearchParams(extra);
        PAGE_PARAMS.forEach((key) => params.set(key, String(settings[key])));
        const separator = props.pdf_url.includes('?') ? '&' : '?';

        return `${props.pdf_url}${separator}${params.toString()}`;
    };

    const previewUrl = useMemo(
        () => pdfUrlWith({ v: String(previewKey) }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [props.pdf_url, previewKey, settings.page_margin_top, settings.page_margin_bottom, settings.page_margin_left, settings.page_margin_right, settings.line_spacing],
    );

    return {
        settings,
        update,
        htmlContent,
        updateHtml,
        resetHtmlToForm: () => updateHtml(props.generated_html),
        format,
        viewTab,
        changeTab,
        setViewTab,
        previewKey,
        refreshPreview: () => setPreviewKey((key) => key + 1),
        previewUrl,
        downloadPdf: () => window.open(pdfUrlWith({ download: '1' }), '_blank'),
        reset,
        /** Kolom dokumen untuk payload simpan (dibaca K3FormulirDocumentBuilder::documentRules). */
        payload: () => ({
            ...settings,
            format,
            content_html: format === 'html' ? htmlContent : null,
        }),
    };
}

export type FormulirDocumentState = ReturnType<typeof useFormulirDocument>;

function SideCard({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <div className="space-y-4 rounded-lg border border-border bg-card p-4">
            <div className="border-b border-border pb-2">
                <h3 className="text-sm font-semibold text-foreground">{title}</h3>
                <p className="text-[12px] text-muted-foreground">{description}</p>
            </div>
            <div className="space-y-3 text-xs">{children}</div>
        </div>
    );
}

/** Kartu kiri 1: pemilih unit/periode (children) + No. Dokumen, Revisi, Tanggal Efektif. */
export function DocumentMetaCard({
    doc,
    disabled,
    showSignPlace = false,
    children,
}: {
    doc: FormulirDocumentState;
    disabled: boolean;
    /** Tampilkan isian "Kendari, 1 September 2026" di atas tanda tangan. */
    showSignPlace?: boolean;
    children: ReactNode;
}) {
    return (
        <SideCard title="Metadata Formulir" description="Unit, periode, dan identitas dokumen resmi.">
            {children}
            <div className="grid grid-cols-2 gap-2">
                <div className="space-y-1">
                    <Label className="text-xs">No. Dokumen</Label>
                    <Input
                        value={doc.settings.document_number}
                        onChange={(e) => doc.update({ document_number: e.target.value })}
                        disabled={disabled}
                        className="h-8 text-xs"
                    />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">Revisi</Label>
                    <Input
                        value={doc.settings.revision}
                        onChange={(e) => doc.update({ revision: e.target.value })}
                        disabled={disabled}
                        className="h-8 text-xs"
                    />
                </div>
            </div>
            <div className="space-y-1">
                <Label className="text-xs">Tanggal Efektif Dokumen</Label>
                <Input
                    value={doc.settings.effective_date}
                    onChange={(e) => doc.update({ effective_date: e.target.value })}
                    disabled={disabled}
                    placeholder="mis. 1 Januari 2026"
                    className="h-8 text-xs"
                />
            </div>
            {showSignPlace && (
                <div className="space-y-1">
                    <Label className="text-xs">Tempat, Tanggal Tanda Tangan</Label>
                    <Input
                        value={doc.settings.sign_place_date}
                        onChange={(e) => doc.update({ sign_place_date: e.target.value })}
                        disabled={disabled}
                        placeholder="Kendari, 1 September 2026"
                        className="h-8 text-xs"
                    />
                </div>
            )}
        </SideCard>
    );
}

/** Kartu kiri 2: penandatangan sesuai lembar formulir (Mengetahui / Diperiksa / Dibuat). */
export function SignatoriesCard({
    doc,
    signers,
    options,
    disabled,
}: {
    doc: FormulirDocumentState;
    signers: FormulirDocumentProps['signers'];
    options: FormulirDocumentProps['signer_options'];
    disabled: boolean;
}) {
    return (
        <SideCard
            title="Penandatangan Dokumen"
            description={signers.map((signer) => signer.label.replace(/,$/, '')).join(' · ')}
        >
            {signers.map((signer, index) => {
                const signerOptions = options[signer.key] ?? [];
                const selectedId = doc.settings[`${signer.key}_id`];
                const selected = signerOptions.find((e) => e.id === selectedId);
                const title = doc.settings[`${signer.key}_title`];

                return (
                    <div key={signer.key} className="space-y-2 rounded-md border border-border/70 bg-muted/20 p-2.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label className="font-semibold text-foreground">
                                {index + 1}. {signer.label.replace(/,$/, '')} {title ? `(${title})` : ''}
                            </Label>
                            {selected?.has_signature ? (
                                <Badge variant="outline" className="border-emerald-500/40 bg-emerald-50 py-0 text-[10px] text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    TTD Siap
                                </Badge>
                            ) : (
                                <Badge variant="outline" className="py-0 text-[10px] text-muted-foreground">
                                    Tanpa TTD
                                </Badge>
                            )}
                        </div>
                        <Select
                            value={selectedId ? String(selectedId) : ''}
                            onValueChange={(value) => {
                                const employee = signerOptions.find((e) => String(e.id) === value);
                                doc.update({
                                    [`${signer.key}_id`]: employee?.id ?? null,
                                    ...(employee ? { [`${signer.key}_name`]: employee.name } : {}),
                                    ...(employee?.position ? { [`${signer.key}_title`]: employee.position } : {}),
                                } as Partial<DocumentSettings>);
                            }}
                            disabled={disabled}
                        >
                            <SelectTrigger className="h-7 text-xs">
                                <SelectValue placeholder="Pilih pegawai..." />
                            </SelectTrigger>
                            <SelectContent>
                                {signerOptions.map((e) => (
                                    <SelectItem key={e.id} value={String(e.id)}>
                                        {e.name} {e.position ? `(${e.position})` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="grid grid-cols-2 gap-2">
                            <Input
                                value={doc.settings[`${signer.key}_name`]}
                                onChange={(e) => doc.update({ [`${signer.key}_name`]: e.target.value } as Partial<DocumentSettings>)}
                                disabled={disabled}
                                placeholder="Nama"
                                className="h-7 text-xs"
                            />
                            <Input
                                value={doc.settings[`${signer.key}_title`]}
                                onChange={(e) => doc.update({ [`${signer.key}_title`]: e.target.value } as Partial<DocumentSettings>)}
                                disabled={disabled}
                                placeholder="Jabatan"
                                className="h-7 text-xs"
                            />
                        </div>
                    </div>
                );
            })}
            <div className="rounded-md border border-border bg-card p-2 text-[11px] text-muted-foreground">
                <Info className="mr-1 inline size-3.5 text-primary" />
                Tanda tangan digital pegawai otomatis terpasang pada PDF jika sudah diunggah pada master pegawai.
            </div>
        </SideCard>
    );
}

/** Kartu kiri 3: margin & spasi baris PDF. */
export function PageSettingsCard({ doc, disabled }: { doc: FormulirDocumentState; disabled: boolean }) {
    const margins: { key: 'page_margin_top' | 'page_margin_bottom' | 'page_margin_left' | 'page_margin_right'; label: string }[] = [
        { key: 'page_margin_top', label: 'Atas (mm)' },
        { key: 'page_margin_bottom', label: 'Bawah (mm)' },
        { key: 'page_margin_left', label: 'Kiri (mm)' },
        { key: 'page_margin_right', label: 'Kanan (mm)' },
    ];

    return (
        <SideCard title="Page Settings" description="Pengaturan layout & margin PDF cetak.">
            <div className="grid grid-cols-4 gap-2">
                {margins.map((margin) => (
                    <div key={margin.key} className="space-y-1">
                        <Label className="text-[11px]">{margin.label}</Label>
                        <Input
                            type="number"
                            min={0}
                            max={50}
                            value={doc.settings[margin.key]}
                            onChange={(e) => doc.update({ [margin.key]: Math.max(0, Math.min(50, Number(e.target.value))) })}
                            disabled={disabled}
                            className="h-7 text-center text-xs"
                        />
                    </div>
                ))}
            </div>
            <div className="space-y-1">
                <Label className="text-[11px]">Spasi Baris</Label>
                <Select value={doc.settings.line_spacing} onValueChange={(value) => doc.update({ line_spacing: value })} disabled={disabled}>
                    <SelectTrigger className="h-7 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1.0">1,0 (Rapat)</SelectItem>
                        <SelectItem value="1.15">1,15 (Standar ISO)</SelectItem>
                        <SelectItem value="1.5">1,5 (Lebar)</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </SideCard>
    );
}

function TabButton({ active, onClick, icon, children }: { active: boolean; onClick: () => void; icon: ReactNode; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-1.5 whitespace-nowrap px-3 py-1.5 transition-colors ${
                active
                    ? 'bg-primary font-medium text-primary-foreground'
                    : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
            }`}
        >
            {icon}
            {children}
        </button>
    );
}

/**
 * Kolom kanan: tab Editor Formulir / Editor Teks (HTML) / Pratinjau PDF
 * beserta tombol aksi bawah (Riwayat, Batal, Pratinjau, Unduh, Simpan).
 */
export function DocumentWorkspace({
    doc,
    documentStyles,
    title,
    canWrite,
    dirty,
    saving,
    historyCount,
    onOpenHistory,
    onSave,
    onCancel,
    children,
}: {
    doc: FormulirDocumentState;
    documentStyles: string;
    title: string;
    canWrite: boolean;
    dirty: boolean;
    saving: boolean;
    historyCount: number;
    onOpenHistory: () => void;
    onSave: (onSuccess?: () => void) => void;
    onCancel: () => void;
    children: ReactNode;
}) {
    const openPreview = () => {
        if (canWrite && dirty) {
            onSave(() => {
                doc.setViewTab('pdf');
                doc.refreshPreview();
            });

            return;
        }

        doc.setViewTab('pdf');
        doc.refreshPreview();
    };

    return (
        <div className="flex w-full min-w-0 flex-1 flex-col rounded-lg border border-border bg-card shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div>
                    <h3 className="text-base font-semibold text-foreground">Isi Formulir &amp; Dokumen</h3>
                    <p className="text-[12px] text-muted-foreground">
                        Kop, periode, dan tanda tangan otomatis disesuaikan pada PDF.
                    </p>
                </div>
                <div className="flex flex-wrap overflow-hidden rounded-md border border-border text-xs">
                    <TabButton active={doc.viewTab === 'form'} onClick={() => doc.changeTab('form')} icon={<SlidersHorizontal className="size-3.5" />}>
                        Editor Formulir
                    </TabButton>
                    <TabButton active={doc.viewTab === 'html'} onClick={() => doc.changeTab('html')} icon={<FileCode2 className="size-3.5" />}>
                        Editor Teks (HTML)
                    </TabButton>
                    <TabButton active={doc.viewTab === 'pdf'} onClick={openPreview} icon={<Printer className="size-3.5" />}>
                        Pratinjau PDF
                    </TabButton>
                </div>
            </div>

            {doc.viewTab === 'form' && <div className="space-y-4 p-4">{children}</div>}

            {doc.viewTab === 'html' && (
                <div className="space-y-3 p-4">
                    <div className="flex flex-wrap items-start justify-between gap-2 rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
                        <span className="flex-1">
                            Mode Editor Teks memungkinkan penyesuaian isi dokumen secara langsung. Jika disimpan pada mode ini, PDF mengikuti teks hasil editan
                            (perubahan di Editor Formulir tidak ikut sampai Anda kembali ke tab Editor Formulir lalu menyimpan).
                        </span>
                        {canWrite && (
                            <Button size="sm" variant="outline" onClick={doc.resetHtmlToForm} className="h-7 gap-1 bg-background text-xs">
                                <RotateCcw className="size-3.5" />
                                Muat Ulang dari Data Tersimpan
                            </Button>
                        )}
                    </div>
                    <div className="rounded-md border border-border bg-card">
                        <RichTextEditor
                            value={doc.htmlContent}
                            onChange={doc.updateHtml}
                            disabled={!canWrite}
                            extraContentStyle={documentStyles}
                            autoGrow
                        />
                    </div>
                </div>
            )}

            {doc.viewTab === 'pdf' && (
                <div className="space-y-3 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>Pratinjau identik dengan hasil cetak PDF A4 resmi. Perubahan disimpan otomatis sebelum pratinjau.</span>
                        <Button size="sm" variant="outline" onClick={doc.refreshPreview} className="h-7 gap-1 text-xs">
                            <RotateCcw className="size-3.5" />
                            Segarkan Pratinjau
                        </Button>
                    </div>
                    <PdfPreviewFrame
                        key={doc.previewKey}
                        title={`Pratinjau PDF ${title}`}
                        src={doc.previewUrl}
                        className="h-[750px] w-full rounded-md border border-border bg-white"
                    />
                </div>
            )}

            <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border bg-muted/10 p-4">
                <Button variant="outline" size="sm" onClick={onOpenHistory} className="gap-1.5 text-xs">
                    <History className="size-3.5" />
                    Riwayat ({historyCount})
                </Button>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={onCancel}>
                        Batal
                    </Button>
                    <Button variant="outline" onClick={openPreview} className="gap-1.5">
                        <Printer className="size-4" />
                        Pratinjau PDF
                    </Button>
                    <Button variant="outline" onClick={doc.downloadPdf} className="gap-1.5">
                        <Download className="size-4" />
                        Unduh PDF
                    </Button>
                    {canWrite && (
                        <Button onClick={() => onSave()} disabled={saving || !dirty} className="gap-1.5">
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan Perubahan'}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

export type HistoryEntry = {
    key: string;
    label: string;
    description: string;
    onOpen: () => void;
};

export function HistoryDialog({
    open,
    onOpenChange,
    title,
    entries,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    entries: HistoryEntry[];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>Daftar periode formulir yang pernah disimpan untuk unit ini.</DialogDescription>
                </DialogHeader>
                <div className="max-h-[380px] divide-y divide-border overflow-y-auto">
                    {entries.length === 0 ? (
                        <div className="py-8 text-center text-xs text-muted-foreground">Belum ada riwayat formulir untuk unit ini.</div>
                    ) : (
                        entries.map((entry) => (
                            <div key={entry.key} className="flex items-center justify-between rounded px-2 py-2.5 text-xs hover:bg-muted/30">
                                <div>
                                    <div className="font-semibold text-foreground">{entry.label}</div>
                                    <div className="text-[11px] text-muted-foreground">{entry.description}</div>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        onOpenChange(false);
                                        entry.onOpen();
                                    }}
                                    className="h-7 text-xs"
                                >
                                    Buka Dokumen
                                </Button>
                            </div>
                        ))
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Label + kontrol kecil di kartu metadata (unit / bulan / tahun / minggu). */
export function MetaField({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <Label className="text-xs">{label}</Label>
            {children}
        </div>
    );
}

export function CompactSelect({
    value,
    onChange,
    options,
    disabled = false,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    disabled?: boolean;
    placeholder?: string;
}) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger className="h-8 text-xs">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value} className="text-xs">
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Kerangka halaman Formulir K3 (pola Formulir Prelube Test): header + status,
 * kolom kiri (metadata, penandatangan, page settings) dan kolom kanan (tab editor).
 */
export function FormulirDocumentLayout({
    title,
    description,
    unitName,
    serviceUnitName,
    periodLabel,
    hasSaved,
    canWrite,
    dirty,
    saving,
    doc,
    documentProps,
    showSignPlace = false,
    periodControls,
    history,
    onSave,
    onReset,
    onBack,
    children,
}: {
    title: string;
    description: string;
    unitName: string;
    serviceUnitName: string | null;
    periodLabel: string;
    hasSaved: boolean;
    canWrite: boolean;
    dirty: boolean;
    saving: boolean;
    doc: FormulirDocumentState;
    documentProps: FormulirDocumentProps;
    showSignPlace?: boolean;
    periodControls: ReactNode;
    history: HistoryEntry[];
    onSave: (onSuccess?: () => void) => void;
    onReset: () => void;
    onBack: () => void;
    children: ReactNode;
}) {
    const [showHistory, setShowHistory] = useState<boolean>(false);

    return (
        <>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={description}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={onBack} className="gap-2">
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button variant="outline" onClick={() => setShowHistory(true)} className="gap-2">
                                <History className="size-4" />
                                Riwayat ({history.length})
                            </Button>
                            <Button variant="outline" onClick={doc.downloadPdf} className="gap-2">
                                <Download className="size-4" />
                                Unduh PDF
                            </Button>
                            {canWrite && (
                                <Button onClick={() => onSave()} disabled={saving || !dirty} className="gap-2">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan Formulir'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-3 text-[13px]">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground">Unit:</span>
                        <strong className="text-foreground">{unitName}</strong>
                        <span className="mx-1 text-muted-foreground">•</span>
                        <span className="text-muted-foreground">Service Unit (UL):</span>
                        <span className="font-medium text-foreground">{serviceUnitName || '—'}</span>
                        <span className="mx-1 text-muted-foreground">•</span>
                        <span className="text-muted-foreground">Periode:</span>
                        <span className="font-medium text-foreground">{periodLabel}</span>
                        <span className="mx-1 text-muted-foreground">•</span>
                        {dirty ? (
                            <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                        ) : hasSaved ? (
                            <StatusBadge tone="success">
                                Tersimpan ({doc.format === 'html' ? 'Teks HTML' : 'Form'})
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">Template Standar</StatusBadge>
                        )}
                    </div>
                    <div className="text-[12px] text-muted-foreground">
                        No. Dokumen: <span className="font-mono font-medium">{doc.settings.document_number}</span> (Rev. {doc.settings.revision})
                    </div>
                </div>

                <div className="flex w-full flex-col items-start gap-6 xl:flex-row">
                    <div className="w-full shrink-0 space-y-5 xl:w-[360px] 2xl:w-[400px]">
                        <DocumentMetaCard doc={doc} disabled={!canWrite} showSignPlace={showSignPlace}>
                            {periodControls}
                        </DocumentMetaCard>
                        <SignatoriesCard
                            doc={doc}
                            signers={documentProps.signers}
                            options={documentProps.signer_options}
                            disabled={!canWrite}
                        />
                        <PageSettingsCard doc={doc} disabled={!canWrite} />
                    </div>

                    <DocumentWorkspace
                        doc={doc}
                        documentStyles={documentProps.document_styles}
                        title={title}
                        canWrite={canWrite}
                        dirty={dirty}
                        saving={saving}
                        historyCount={history.length}
                        onOpenHistory={() => setShowHistory(true)}
                        onSave={onSave}
                        onCancel={dirty ? onReset : onBack}
                    >
                        {children}
                    </DocumentWorkspace>
                </div>

                {dirty && canWrite && (
                    <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 shadow-lg dark:border-amber-900/60 dark:bg-amber-950/80">
                        <div className="flex items-center gap-2">
                            <AlertCircle className="size-4 text-amber-600 dark:text-amber-400" />
                            <span className="text-xs font-medium text-amber-900 dark:text-amber-200">
                                Perubahan data belum disimpan ke server.
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={onReset} className="h-8 gap-1 text-xs">
                                <RotateCcw className="size-3" />
                                Batal
                            </Button>
                            <Button size="sm" onClick={() => onSave()} disabled={saving} className="h-8 gap-1.5 text-xs font-semibold">
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan…' : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <HistoryDialog open={showHistory} onOpenChange={setShowHistory} title={`Riwayat ${title} - ${unitName}`} entries={history} />
        </>
    );
}
