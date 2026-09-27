import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, FileText, Plus, Printer, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PRINT_FORM_CONTROLS } from '@/components/pengusahaan/k3-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout, useInMobileShell } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import type { IdName } from '@/types';

export type SheetFilters = { unit_id: number; month: number; year: number };

export type SheetMeta = { no_dokumen: string; revisi: string; tanggal_dokumen: string };

export type SheetStat = { label: string; tone?: 'primary' | 'good' | 'info' | 'bad' };

const STAT_TONE: Record<NonNullable<SheetStat['tone']>, string> = {
    primary: 'border-primary/25 bg-primary/5 text-primary',
    good: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    info: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300',
    bad: 'border-destructive/30 bg-destructive/10 text-destructive',
};

/**
 * The frame of an Akses 2 — Pengusahaan K3 input sheet (APAR/APAB, APAT,
 * Hydrant …): header with Simpan / Reset / Cetak, unit & period filter,
 * summary, the official kop with the document number, the sheet table and
 * Catatan. On a phone (or inside the mobile shell) the table is swapped for
 * the page's card editor, with the actions pinned to the bottom.
 */
export function K3Sheet({
    title,
    formCode,
    description,
    docTitle,
    unit,
    filters,
    units,
    years,
    onFilter,
    meta,
    onMeta,
    catatan,
    onCatatan,
    catatanLabel,
    catatanPlaceholder,
    stats,
    tools,
    table,
    mobile,
    addLabel,
    onAdd,
    hasSaved,
    canWrite,
    dirty,
    saving,
    onReset,
    onSave,
    printCss,
}: {
    title: string;
    formCode: string;
    description: string;
    /** The sheet title printed in the kop, e.g. "KONDISI ALAT PEMADAM API TRADISIONAL". */
    docTitle: string;
    unit: IdName;
    filters: SheetFilters;
    units: IdName[];
    years: number[];
    onFilter: (key: keyof SheetFilters, value: number) => void;
    meta: SheetMeta;
    onMeta: (key: keyof SheetMeta, value: string) => void;
    catatan: string;
    onCatatan: (value: string) => void;
    catatanLabel: string;
    catatanPlaceholder: string;
    stats: SheetStat[];
    /** Bulk tools (fill date, set all good …); shown to writers only. */
    tools?: ReactNode;
    /** The printable sheet table (desktop & print). */
    table: ReactNode;
    /** The phone editor (MobileRowEditor). */
    mobile: ReactNode;
    addLabel: string;
    onAdd: () => void;
    hasSaved: boolean;
    canWrite: boolean;
    dirty: boolean;
    saving: boolean;
    onReset: () => void;
    onSave: () => void;
    /** Page print rules (table class, paper). */
    printCss: string;
}) {
    const compact = useCompactLayout();
    const inShell = useInMobileShell();
    const [showMeta, setShowMeta] = useState(false);
    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    const saveLabel = saving ? 'Menyimpan…' : hasSaved ? 'Simpan Perubahan' : 'Simpan Data';

    const filterFields = (width: string) => (
        <>
            <OperasiSelect className={width} label="Unit Layanan" value={String(filters.unit_id)} options={units.map((u) => ({ value: String(u.id), label: u.name }))} onChange={(v) => onFilter('unit_id', Number(v))} />
            <OperasiSelect className={width} label="Bulan" value={String(filters.month)} options={OPERASI_MONTHS.map((label, idx) => ({ value: String(idx + 1), label }))} onChange={(v) => onFilter('month', Number(v))} />
            <OperasiSelect className={width} label="Tahun" value={String(filters.year)} options={years.map((y) => ({ value: String(y), label: String(y) }))} onChange={(v) => onFilter('year', Number(v))} />
        </>
    );

    const statChips = (
        <div className="flex flex-wrap items-center gap-2">
            {stats.map((stat) => (
                <span key={stat.label} className={cn('rounded-lg border px-2.5 py-1 text-[13px] font-semibold', STAT_TONE[stat.tone ?? 'primary'])}>
                    {stat.label}
                </span>
            ))}
        </div>
    );

    const statusBadge = hasSaved ? (
        <Badge variant="secondary" className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            Tersimpan
        </Badge>
    ) : (
        <Badge variant="outline" className="border-amber-300 text-amber-600">
            Draf Baru
        </Badge>
    );

    const metaFields: [keyof SheetMeta, string][] = [
        ['no_dokumen', 'No. Dokumen'],
        ['revisi', 'Revisi'],
        ['tanggal_dokumen', 'Tanggal'],
    ];

    if (compact) {
        return (
            <>
                <Head title={`${title} — ${unit.name}`} />
                <div className={cn('flex flex-1 flex-col gap-3 p-3', canWrite && 'pb-28')}>
                    <div className="flex items-start gap-2">
                        {!inShell && (
                            <Button variant="outline" size="icon" onClick={() => router.get(k3Pengusahaan.index('input').url)} aria-label="Kembali ke Input K3">
                                <ArrowLeft className="size-4" />
                            </Button>
                        )}
                        <div className="min-w-0 flex-1">
                            <h1 className="text-lg leading-tight font-bold text-foreground">{title}</h1>
                            <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[13px]">
                                <span className="text-muted-foreground">
                                    {monthName} {filters.year} · {formCode}
                                </span>
                                {statusBadge}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 rounded-xl border border-border bg-card p-3 [&>label:first-child]:col-span-2">{filterFields('w-full')}</div>

                    {statChips}

                    {canWrite && tools && <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">{tools}</div>}

                    <div className="rounded-xl border border-border bg-card">
                        <button type="button" onClick={() => setShowMeta((v) => !v)} className="flex w-full items-center gap-2 p-3 text-left text-[14px] font-medium">
                            <FileText className="size-4 text-primary" />
                            <span className="flex-1">Info Dokumen</span>
                            <span className="truncate text-[12px] text-muted-foreground">{meta.no_dokumen}</span>
                            <ChevronDown className={cn('size-4 text-muted-foreground transition', showMeta && 'rotate-180')} />
                        </button>
                        {showMeta && (
                            <div className="flex flex-col gap-2 border-t border-border p-3">
                                {metaFields.map(([key, label]) => (
                                    <label key={key} className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        {label}
                                        <Input value={meta[key]} onChange={(e) => onMeta(key, e.target.value)} disabled={!canWrite} />
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>

                    {mobile}

                    <div className="flex flex-col gap-1.5">
                        <span className="text-[13px] font-semibold text-foreground">{catatanLabel}</span>
                        <Textarea value={catatan} onChange={(e) => onCatatan(e.target.value)} placeholder={catatanPlaceholder} rows={3} disabled={!canWrite} />
                    </div>
                </div>

                {canWrite && (
                    <StickyActionBar>
                        <Button variant="outline" onClick={onAdd} className="gap-1.5">
                            <Plus className="size-4" />
                            Tambah
                        </Button>
                        <Button onClick={onSave} disabled={saving} className="gap-1.5">
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={`${title} — ${unit.name}`} />
            <style>{printCss + PRINT_FORM_CONTROLS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="no-print flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" onClick={() => router.get(k3Pengusahaan.index('input').url)} title="Kembali ke Input K3">
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">{title}</h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="outline">
                                    {monthName} {filters.year}
                                </Badge>
                                {statusBadge}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {description} ({formCode})
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button variant="outline" onClick={onReset} disabled={saving} className="gap-1.5 text-muted-foreground">
                                <RotateCcw className="size-4" />
                                Reset
                            </Button>
                        )}
                        {canWrite && (
                            <Button onClick={onSave} disabled={saving} className="gap-1.5">
                                <Save className="size-4" />
                                {saveLabel}
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => window.print()} className="gap-1.5">
                            <Printer className="size-4" />
                            Cetak (A4 Landscape)
                        </Button>
                    </div>
                </div>

                <Card className="no-print gap-0 p-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div className="flex flex-wrap items-end gap-3">{filterFields('w-44')}</div>
                        {canWrite && tools && <div className="flex flex-wrap items-center gap-2">{tools}</div>}
                    </div>
                    <div className="mt-3 border-t border-border pt-3">{statChips}</div>
                </Card>

                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-6 shadow-xs">
                    <div className="sheet-header-box relative rounded border-2 border-black p-4 dark:border-border">
                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="absolute top-4 left-4 max-h-14 object-contain" />
                        <img src="/logo/k3.png" alt="Logo K3" className="absolute top-4 right-4 max-h-14 object-contain" />

                        <div className="px-24 text-center uppercase">
                            <div className="text-base font-extrabold tracking-wide text-foreground">PT. PLN NUSANTARA POWER</div>
                            <div className="text-sm font-bold text-foreground">UNIT PEMBANGKITAN KENDARI</div>
                            <div className="text-sm font-bold text-foreground">
                                UNIT LAYANAN {unit.name.toUpperCase().startsWith('ULPLTD') || unit.name.toUpperCase().startsWith('UP') ? unit.name.toUpperCase() : `PUSAT LISTRIK TENAGA DIESEL ${unit.name.toUpperCase()}`}
                            </div>
                        </div>

                        <div className="my-3 border-b-2 border-black dark:border-border" />

                        <div className="flex items-center justify-between gap-4">
                            <div className="flex-1 pl-28 text-center uppercase">
                                <h2 className="text-base font-black tracking-wider text-foreground">{docTitle}</h2>
                                <div className="text-sm font-bold text-foreground">{unit.name.toUpperCase()}</div>
                                <div className="text-[13px] font-bold text-muted-foreground">
                                    PERIODE BULAN {monthName.toUpperCase()} TAHUN {filters.year}
                                </div>
                            </div>

                            <div className="w-64 border border-black text-[13px] dark:border-border">
                                {metaFields.map(([key, label], index) => (
                                    <div key={key} className={cn('flex', index < metaFields.length - 1 && 'border-b border-black dark:border-border')}>
                                        <div className="w-24 border-r border-black bg-muted/30 px-2 py-1 font-semibold dark:border-border">{label}</div>
                                        <div className="flex-1 px-2 py-1 font-mono">
                                            {canWrite ? (
                                                <input value={meta[key]} onChange={(e) => onMeta(key, e.target.value)} aria-label={label} className="w-full bg-transparent focus:outline-none" />
                                            ) : (
                                                meta[key]
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 overflow-x-auto">{table}</div>

                    {canWrite && (
                        <div className="no-print mt-3">
                            <Button variant="outline" onClick={onAdd} className="gap-1.5 text-primary">
                                <Plus className="size-4" />
                                {addLabel}
                            </Button>
                        </div>
                    )}

                    <div className="mt-4">
                        <div className="text-sm font-semibold text-foreground">{catatanLabel}</div>
                        {canWrite ? (
                            <textarea
                                value={catatan}
                                onChange={(e) => onCatatan(e.target.value)}
                                placeholder={catatanPlaceholder}
                                rows={2}
                                className="mt-1 w-full rounded border border-border bg-background p-2 text-sm focus:ring-1 focus:ring-primary focus:outline-none"
                            />
                        ) : (
                            <p className="mt-1 text-sm text-muted-foreground italic">{catatan || 'Tidak ada catatan.'}</p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

/** Sheet print rules: A4 landscape, only the sheet, black borders. */
export function sheetPrintCss(tableClass: string, fontPx = 10): string {
    return `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: ${fontPx}px !important; border: none !important; }
    .print-container .text-sm, .print-container .text-base, .print-container .text-\\[13px\\] { font-size: ${fontPx + 1}px !important; }
    .no-print { display: none !important; }
    .${tableClass} th, .${tableClass} td { border: 1px solid #000 !important; padding: 4px 5px !important; }
    .sheet-header-box { border: 2px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;
}
