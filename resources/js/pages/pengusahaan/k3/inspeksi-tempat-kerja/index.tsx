import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Printer,
    RotateCcw,
    Save,
    Sparkles,
    XCircle,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanInspeksiTempatKerja from '@/routes/k3/pengusahaan/inspeksi-tempat-kerja';
import type { IdName } from '@/types';

export type InspeksiItem = {
    _key: number;
    id?: number | null;
    category: string;
    no_urut: number;
    item: string;
    status: string | null;
    comment: string;
    sort_order?: number;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
    };
    record: {
        no_dokumen: string;
        revisi: string;
        tanggal_dokumen: string;
        tanggal_inspeksi: string;
        departemen: string;
        lokasi: string;
        tim_inspektur: string;
        ketua_tim: string;
        inspektur: string;
        catatan: string;
        items: Array<Omit<InspeksiItem, '_key'>>;
    };
    sample_items?: Array<Omit<InspeksiItem, '_key'>>;
    has_saved: boolean;
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { 
        position: absolute !important; 
        left: 0 !important; 
        top: 0 !important; 
        width: 100% !important; 
        background: #fff !important; 
        color: #000 !important; 
        padding: 0 !important; 
        margin: 0 !important; 
        font-size: 11px !important; 
    }
    .no-print { display: none !important; }
    .inspeksi-table { width: 100% !important; border-collapse: collapse !important; }
    .inspeksi-table th, .inspeksi-table td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    tr { page-break-inside: avoid !important; break-inside: avoid !important; }
    input, textarea, select { 
        border: none !important; 
        background: transparent !important; 
        padding: 0 !important; 
        font-size: inherit !important; 
        appearance: none !important; 
        -webkit-appearance: none !important;
    }
}
`;

export default function PengusahaanInspeksiTempatKerjaPage(props: Props) {
    const {
        unit,
        filters,
        options,
        record: initialRecord,
        sample_items = [],
        has_saved,
        can_write,
    } = props;

    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const [noDokumen, setNoDokumen] = useState(initialRecord.no_dokumen || 'SMT-FM-AK3-12-01');
    const [revisi, setRevisi] = useState(initialRecord.revisi || '01');
    const [tanggalDokumen, setTanggalDokumen] = useState(initialRecord.tanggal_dokumen || '23 September 2019');
    
    const [tanggalInspeksi, setTanggalInspeksi] = useState(initialRecord.tanggal_inspeksi || '');
    const [departemen, setDepartemen] = useState(initialRecord.departemen || '');
    const [lokasi, setLokasi] = useState(initialRecord.lokasi || '');
    const [timInspektur, setTimInspektur] = useState(initialRecord.tim_inspektur || '');
    const [ketuaTim, setKetuaTim] = useState(initialRecord.ketua_tim || '');
    const [inspektur, setInspektur] = useState(initialRecord.inspektur || '');
    const [catatan, setCatatan] = useState(initialRecord.catatan || '');

    const [items, setItems] = useState<InspeksiItem[]>(() => {
        let counter = 1;

        return (initialRecord.items || []).map((it) => ({
            ...it,
            _key: counter++,
        }));
    });

    const monthName = useMemo(() => {
        return OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    }, [filters.month]);

    const stats = useMemo(() => {
        let yCount = 0;
        let nCount = 0;
        let naCount = 0;
        let unsetCount = 0;
        
        items.forEach(it => {
            if (it.status === 'Y') {
yCount++;
} else if (it.status === 'N') {
nCount++;
} else if (it.status === 'NA') {
naCount++;
} else {
unsetCount++;
}
        });

        return { yCount, nCount, naCount, unsetCount, total: items.length };
    }, [items]);

    const groupedItems = useMemo(() => {
        const groups: Record<string, InspeksiItem[]> = {};
        items.forEach(it => {
            if (!groups[it.category]) {
                groups[it.category] = [];
            }

            groups[it.category].push(it);
        });

        return groups;
    }, [items]);

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: number) => {
        if (dirty && !confirm('Perubahan yang belum disimpan akan hilang. Lanjutkan ganti periode/unit?')) {
            return;
        }
        
        router.get(
            k3PengusahaanInspeksiTempatKerja.index().url,
            {
                unit_id: key === 'unit_id' ? val : filters.unit_id,
                month: key === 'month' ? val : filters.month,
                year: key === 'year' ? val : filters.year,
            },
            { preserveState: false }
        );
    };

    const updateItemStatus = (key: number, status: 'Y' | 'N' | 'NA') => {
        if (!can_write) {
return;
}

        setItems(prev => prev.map(it => it._key === key ? { ...it, status } : it));
        setDirty(true);
    };

    const updateItemComment = (key: number, comment: string) => {
        if (!can_write) {
return;
}

        setItems(prev => prev.map(it => it._key === key ? { ...it, comment } : it));
        setDirty(true);
    };

    const setAllStatus = (status: 'Y' | null) => {
        setItems(prev => prev.map(it => ({ ...it, status })));
        setDirty(true);
    };

    const loadSampleItems = () => {
        if (!confirm('Muat contoh formulir checklist? Ini akan menimpa baris saat ini.')) {
return;
}

        let counter = 1;
        setItems(sample_items.map(it => ({ ...it, _key: counter++ })));
        setDirty(true);
    };

    const handleSave = () => {
        if (!can_write) {
return;
}

        setSaving(true);
        router.post(
            k3PengusahaanInspeksiTempatKerja.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                no_dokumen: noDokumen,
                revisi,
                tanggal_dokumen: tanggalDokumen,
                tanggal_inspeksi: tanggalInspeksi,
                departemen,
                lokasi,
                tim_inspektur: timInspektur,
                ketua_tim: ketuaTim,
                inspektur,
                catatan,
                items: items.map((it, idx) => ({
                    id: it.id,
                    category: it.category,
                    no_urut: it.no_urut,
                    item: it.item,
                    status: it.status,
                    comment: it.comment,
                    sort_order: idx,
                })),
            },
            {
                onSuccess: () => {
                    setSaving(false);
                    setDirty(false);
                },
                onError: (err) => {
                    setSaving(false);
                    const msg = Object.values(err)[0] || 'Gagal menyimpan data.';
                    alert(msg);
                },
            }
        );
    };

    return (
        <div className="space-y-6 p-4 sm:p-6 lg:p-8">
            <style>{PRINT_CSS}</style>
            <Head title={`Inspeksi Tempat Kerja - ${unit.name}`} />

            {/* TOP BAR */}
            <div className="no-print flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9 shrink-0"
                        onClick={() => router.get(k3Pengusahaan.index('input').url)}
                    >
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Inspeksi Tempat Kerja
                            </h1>
                            <Badge variant={has_saved ? 'default' : 'outline'} className={has_saved ? 'bg-emerald-600' : 'border-amber-500 text-amber-600'}>
                                {has_saved ? 'Tersimpan' : 'Draft'}
                            </Badge>
                            {dirty && <Badge variant="secondary">Belum Disimpan</Badge>}
                        </div>
                        <p className="text-xs text-muted-foreground sm:text-sm">
                            Formulir Checklist Inspeksi Tempat Kerja dan Fasilitas (SMT-FM-AK3-12-01).
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 font-semibold text-xs">
                        <Printer className="size-3.5" /> Cetak A4
                    </Button>
                    {can_write && (
                        <Button size="sm" onClick={handleSave} disabled={saving} className="gap-1.5 font-semibold text-xs">
                            <Save className="size-3.5" /> {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    )}
                </div>
            </div>

            {/* FILTER BAR */}
            <Card className="no-print p-4 border border-border shadow-xs">
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <OperasiSelect
                            label="Unit Layanan"
                            value={String(filters.unit_id)}
                            options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                            onChange={(val) => handleFilterChange('unit_id', Number(val))}
                        />
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            options={OPERASI_MONTHS.map((label, idx) => ({ value: String(idx + 1), label }))}
                            onChange={(val) => handleFilterChange('month', Number(val))}
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                            onChange={(val) => handleFilterChange('year', Number(val))}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            <Building2 className="size-3.5 text-sky-600" />
                            <span>Total Item: {stats.total}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            <span>Y (Sesuai): {stats.yCount}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                            <XCircle className="size-3.5 text-red-600" />
                            <span>N (Tidak Sesuai): {stats.nCount}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            <RotateCcw className="size-3.5 text-indigo-600" />
                            <span>NA: {stats.naCount} / Belum Diisi: {stats.unsetCount}</span>
                        </div>
                    </div>

                    {can_write && (
                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                            <Button variant="outline" size="sm" onClick={() => setAllStatus('Y')} className="gap-1.5 text-xs font-medium">
                                <Sparkles className="size-3.5 text-emerald-500" /> Setel Semua Ya (Y)
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => setAllStatus(null)} className="gap-1.5 text-xs font-medium">
                                <RotateCcw className="size-3.5" /> Reset Pilihan
                            </Button>
                            {sample_items.length > 0 && (
                                <Button variant="secondary" size="sm" onClick={loadSampleItems} className="gap-1.5 text-xs font-medium">
                                    <Sparkles className="size-3.5 text-amber-500" /> Muat Contoh Dokumen
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </Card>

            {/* PRINT CONTAINER */}
            <div className="print-container rounded-lg border border-border bg-card p-4 sm:p-6 shadow-xs text-card-foreground">
                <div className="relative rounded border-2 border-black p-3 dark:border-border">
                    <div className="absolute left-3 top-3 flex items-center">
                        <img src="/logo/sidebar-logo.png" alt="PLN" className="max-h-12 object-contain" onError={(e) => {
 (e.target as HTMLElement).style.display = 'none'; 
}} />
                    </div>
                    <div className="absolute right-3 top-3 flex items-center">
                        <img src="/logo/k3.png" alt="K3" className="max-h-12 object-contain" onError={(e) => {
 (e.target as HTMLElement).style.display = 'none'; 
}} />
                    </div>
                    <div className="text-center px-16">
                        <div className="text-xs font-black tracking-wide sm:text-sm text-foreground">PT. PLN NUSANTARA POWER</div>
                        <div className="text-[11px] font-bold tracking-wider sm:text-xs text-foreground">UNIT PEMBANGKITAN KENDARI</div>
                        <div className="text-[10px] font-semibold uppercase sm:text-[11px] text-foreground">UNIT LAYANAN {unit.name}</div>
                    </div>
                </div>

                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_260px] sm:items-stretch">
                    <div className="flex items-center justify-center border-2 border-black p-2.5 text-center dark:border-border">
                        <h2 className="text-xs font-black uppercase tracking-wide sm:text-sm text-foreground">
                            FORMULIR CHECKLIST INSPEKSI TEMPAT KERJA DAN FASILITAS
                        </h2>
                    </div>
                    <div className="border-2 border-black text-[10px] dark:border-border sm:text-[11px]">
                        <div className="grid grid-cols-[85px_1fr] border-b border-black dark:border-border">
                            <div className="border-r border-black p-1 font-bold dark:border-border">No. Dokumen</div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input type="text" value={noDokumen} onChange={(e) => {
 setNoDokumen(e.target.value); setDirty(true); 
}} className="w-full bg-transparent font-semibold focus:outline-none" />
                                ) : noDokumen}
                            </div>
                        </div>
                        <div className="grid grid-cols-[85px_1fr] border-b border-black dark:border-border">
                            <div className="border-r border-black p-1 font-bold dark:border-border">Revisi</div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input type="text" value={revisi} onChange={(e) => {
 setRevisi(e.target.value); setDirty(true); 
}} className="w-full bg-transparent font-semibold focus:outline-none" />
                                ) : revisi}
                            </div>
                        </div>
                        <div className="grid grid-cols-[85px_1fr]">
                            <div className="border-r border-black p-1 font-bold dark:border-border">Tanggal</div>
                            <div className="p-1 font-semibold">
                                {can_write ? (
                                    <input type="text" value={tanggalDokumen} onChange={(e) => {
 setTanggalDokumen(e.target.value); setDirty(true); 
}} className="w-full bg-transparent font-semibold focus:outline-none" />
                                ) : tanggalDokumen}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Inspection Info section */}
                <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 text-[10px] font-semibold sm:text-[11px] mb-3 border border-black p-2 dark:border-border">
                    <div className="space-y-1">
                        <div className="flex"><div className="w-24">TANGGAL</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={tanggalInspeksi} onChange={e => {
setTanggalInspeksi(e.target.value); setDirty(true);
}} className="bg-transparent focus:outline-none" placeholder="..." /> : tanggalInspeksi}</div></div>
                        <div className="flex"><div className="w-24">DEPARTEMEN</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={departemen} onChange={e => {
setDepartemen(e.target.value); setDirty(true);
}} className="w-full min-w-0 bg-transparent focus:outline-none sm:w-48" placeholder="..." /> : departemen}</div></div>
                        <div className="flex"><div className="w-24">LOKASI</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={lokasi} onChange={e => {
setLokasi(e.target.value); setDirty(true);
}} className="w-full min-w-0 bg-transparent focus:outline-none sm:w-48" placeholder="..." /> : lokasi}</div></div>
                    </div>
                    <div className="space-y-1">
                        <div className="flex"><div className="w-32">TIM INSPEKTUR</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={timInspektur} onChange={e => {
setTimInspektur(e.target.value); setDirty(true);
}} className="w-full min-w-0 bg-transparent focus:outline-none sm:w-48" placeholder="..." /> : timInspektur}</div></div>
                        <div className="flex"><div className="w-32">KETUA TIM</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={ketuaTim} onChange={e => {
setKetuaTim(e.target.value); setDirty(true);
}} className="w-full min-w-0 bg-transparent focus:outline-none sm:w-48" placeholder="..." /> : ketuaTim}</div></div>
                        <div className="flex"><div className="w-32">INSPEKTUR</div><div className="flex min-w-0 flex-1 gap-1">: {can_write ? <input type="text" value={inspektur} onChange={e => {
setInspektur(e.target.value); setDirty(true);
}} className="w-full min-w-0 bg-transparent focus:outline-none sm:w-48" placeholder="..." /> : inspektur}</div></div>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="inspeksi-table w-full border-collapse border-2 border-black text-[10px] dark:border-border sm:text-[11px]">
                        <thead>
                            <tr className="bg-muted/40 font-bold uppercase text-foreground">
                                <th className="w-10 border border-black px-1.5 py-1 text-center dark:border-border">NO</th>
                                <th className="border border-black px-2 py-1 text-left dark:border-border">ITEM</th>
                                <th className="w-8 border border-black px-1 py-1 text-center dark:border-border">Y</th>
                                <th className="w-8 border border-black px-1 py-1 text-center dark:border-border">N</th>
                                <th className="w-8 border border-black px-1 py-1 text-center dark:border-border">NA</th>
                                <th className="w-48 border border-black px-2 py-1 text-center dark:border-border">COMMENT</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(groupedItems).map(([category, catItems]) => (
                                <React.Fragment key={category}>
                                    <tr className="bg-muted/20 font-bold">
                                        <td colSpan={6} className="border border-black px-2 py-1.5 dark:border-border">
                                            {category}
                                        </td>
                                    </tr>
                                    {catItems.map((it, idx) => (
                                        <tr key={it._key}>
                                            <td className="border border-black px-1.5 py-1 text-center align-top dark:border-border">
                                                {idx + 1}
                                            </td>
                                            <td className="border border-black px-2 py-1 align-top dark:border-border">
                                                {it.item}
                                            </td>
                                            {['Y', 'N', 'NA'].map((status) => (
                                                <td
                                                    key={status}
                                                    className={`border border-black px-1 py-1 text-center align-middle dark:border-border ${can_write ? 'cursor-pointer hover:bg-muted/30' : ''}`}
                                                    onClick={() => updateItemStatus(it._key, status as 'Y'|'N'|'NA')}
                                                >
                                                    {it.status === status ? (
                                                        can_write ? '●' : 'x'
                                                    ) : (
                                                        can_write ? '○' : ''
                                                    )}
                                                </td>
                                            ))}
                                            <td className="border border-black px-1.5 py-1 align-top dark:border-border">
                                                {can_write ? (
                                                    <textarea
                                                        rows={1}
                                                        value={it.comment || ''}
                                                        onChange={(e) => updateItemComment(it._key, e.target.value)}
                                                        className="w-full bg-transparent focus:bg-background focus:outline-none resize-none"
                                                    />
                                                ) : (
                                                    it.comment
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 border border-black p-3 text-[10px] dark:border-border sm:text-[11px]">
                    <div className="font-bold uppercase text-foreground mb-1">Catatan :</div>
                    {can_write ? (
                        <textarea
                            rows={3}
                            value={catatan}
                            onChange={(e) => {
 setCatatan(e.target.value); setDirty(true); 
}}
                            className="w-full bg-transparent focus:outline-none resize-none"
                        />
                    ) : (
                        <div className="whitespace-pre-wrap">{catatan || '-'}</div>
                    )}
                </div>

                <div className="mt-6 grid grid-cols-2 gap-8 text-center text-[10px] sm:text-[11px]">
                    <div>
                        <div className="text-muted-foreground">Mengetahui,</div>
                        <div className="font-bold text-foreground">Ketua Tim</div>
                        <div className="h-16" />
                        <div className="font-bold text-foreground inline-block border-b border-black border-dashed min-w-[150px] pb-1">
                            {ketuaTim || '( ........................................ )'}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Kendari, {monthName} {filters.year}</div>
                        <div className="font-bold text-foreground">Inspektur</div>
                        <div className="h-16" />
                        <div className="font-bold text-foreground inline-block border-b border-black border-dashed min-w-[150px] pb-1">
                            {inspektur || '( ........................................ )'}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
