import { router } from '@inertiajs/react';
import { CheckCircle2, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { CellDate, CellInput, CellSelect, chipTone, dmyToIso, isoToDmy, K3_OPTIONS, TONE_BAD, TONE_GOOD } from '@/components/pengusahaan/k3-cells';
import { K3Sheet, sheetPrintCss } from '@/components/pengusahaan/k3-sheet';
import type { SheetMeta } from '@/components/pengusahaan/k3-sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import k3PengusahaanHydrant from '@/routes/k3/pengusahaan/hydrant';
import type { IdName } from '@/types';

export type HydrantItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    lokasi: string;
    tanggal_periksa: string;
    hose: string;
    nozzle: string;
    box_hydrant: string;
    kondisi_tekanan_air: string;
    keterangan: string;
    sort_order?: number;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    record: {
        no_dokumen: string;
        revisi: string;
        tanggal_dokumen: string;
        tanggal_periksa: string;
        catatan: string;
        items: Array<Omit<HydrantItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const O = K3_OPTIONS.hydrant;
const PRINT_CSS = sheetPrintCss('hydrant-table', 11);

const CONDITIONS: { field: 'hose' | 'nozzle' | 'box_hydrant' | 'kondisi_tekanan_air'; label: string; options: string[] }[] = [
    { field: 'hose', label: 'Hose', options: O.hose },
    { field: 'nozzle', label: 'Nozzle', options: O.nozzle },
    { field: 'box_hydrant', label: 'Box Hydrant', options: O.box },
    { field: 'kondisi_tekanan_air', label: 'Kondisi Tekanan Air', options: O.tekanan },
];

const ALL_GOOD = { hose: 'Normal', nozzle: 'Normal', box_hydrant: 'Baik', kondisi_tekanan_air: 'Baik' };

export default function PengusahaanHydrantPage({ unit, filters, options, record: initialRecord, has_saved, can_write }: Props) {
    const defaultDate = initialRecord.tanggal_periksa || `13/${String(filters.month).padStart(2, '0')}/${filters.year}`;
    const hydrate = (): HydrantItem[] =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            hose: it.hose ?? 'Normal',
            nozzle: it.nozzle ?? 'Normal',
            box_hydrant: it.box_hydrant ?? 'Baik',
            kondisi_tekanan_air: it.kondisi_tekanan_air ?? 'Baik',
            tanggal_periksa: it.tanggal_periksa || defaultDate,
        }));

    const [meta, setMeta] = useState<SheetMeta>({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
    const [tanggalPeriksa, setTanggalPeriksa] = useState(defaultDate);
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<HydrantItem[]>(hydrate);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: number) => {
        router.get(k3PengusahaanHydrant.index().url, { ...filters, [key]: value }, { preserveState: false, preserveScroll: true });
    };

    const addItem = () => {
        setItems((prev) => [...prev, { _key: nextKey, no_urut: prev.length + 1, lokasi: '', tanggal_periksa: tanggalPeriksa, ...ALL_GOOD, keterangan: '', sort_order: prev.length }]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof HydrantItem, value: string | number) => {
        setItems((prev) => prev.map((item) => (item._key === key ? { ...item, [field]: value } : item)));
        setDirty(true);
    };

    const removeItem = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    const setAll = (patch: Partial<HydrantItem>) => {
        setItems((prev) => prev.map((item) => ({ ...item, ...patch })));
        setDirty(true);
    };

    const resetChanges = () => {
        setMeta({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
        setTanggalPeriksa(defaultDate);
        setCatatan(initialRecord.catatan);
        setItems(hydrate());
        setDirty(false);
    };

    const handleSubmit = () => {
        setSaving(true);
        router.post(
            k3PengusahaanHydrant.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...meta,
                tanggal_periksa: tanggalPeriksa,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    lokasi: it.lokasi,
                    tanggal_periksa: it.tanggal_periksa || tanggalPeriksa,
                    hose: it.hose,
                    nozzle: it.nozzle,
                    box_hydrant: it.box_hydrant,
                    kondisi_tekanan_air: it.kondisi_tekanan_air,
                    keterangan: it.keterangan,
                    sort_order: idx,
                })),
            },
            { preserveScroll: true, preserveState: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const summary = useMemo(() => {
        const good = (field: keyof HydrantItem) => items.filter((it) => O.good.includes(String(it[field] ?? '').toLowerCase())).length;

        return { total: items.length, hose: good('hose'), nozzle: good('nozzle'), box: good('box_hydrant'), tekanan: good('kondisi_tekanan_air') };
    }, [items]);

    const th = 'border border-black p-2 text-[13px] font-bold dark:border-border';
    const td = 'border-r border-black p-1.5 text-center align-middle dark:border-border';

    const table = (
        <table className="hydrant-table w-full min-w-[1000px] border-collapse border-2 border-black text-sm dark:border-border">
            <thead>
                <tr className="bg-muted/40 text-center text-foreground">
                    <th className={`${th} w-12`}>NO.</th>
                    <th className={`${th} w-64 text-left`}>LOKASI</th>
                    <th className={`${th} w-40`}>TANGGAL PERIKSA</th>
                    <th className={`${th} w-28`}>HOSE</th>
                    <th className={`${th} w-28`}>NOZZLE</th>
                    <th className={`${th} w-32`}>BOX HYDRANT</th>
                    <th className={`${th} w-36`}>KONDISI TEKANAN AIR</th>
                    <th className={`${th} text-left`}>KETERANGAN</th>
                    {can_write && <th className={`${th} no-print w-12`}>AKSI</th>}
                </tr>
            </thead>
            <tbody>
                {items.length === 0 ? (
                    <tr>
                        <td colSpan={can_write ? 9 : 8} className="p-6 text-center text-muted-foreground italic">
                            Belum ada titik hydrant tercatat. Klik "Tambah Titik Hydrant" untuk menambahkan.
                        </td>
                    </tr>
                ) : (
                    items.map((item, index) => (
                        <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                            <td className={`${td} font-mono`}>{index + 1}</td>
                            <td className={`${td} text-left font-medium`}>
                                {can_write ? <CellInput value={item.lokasi} onChange={(e) => updateItem(item._key, 'lokasi', e.target.value)} placeholder="Lokasi titik hydrant…" aria-label={`Lokasi baris ${index + 1}`} /> : item.lokasi}
                            </td>
                            <td className={td}>
                                {can_write ? <CellDate value={item.tanggal_periksa} onChange={(v) => updateItem(item._key, 'tanggal_periksa', v)} ariaLabel={`Tanggal periksa baris ${index + 1}`} /> : item.tanggal_periksa || '-'}
                            </td>
                            {CONDITIONS.map(({ field, label, options: choices }) => (
                                <td key={field} className={td}>
                                    {can_write ? (
                                        <CellSelect value={item[field]} options={choices} good={O.good} onChange={(v) => updateItem(item._key, field, v)} ariaLabel={`${label} baris ${index + 1}`} />
                                    ) : (
                                        <span className={O.good.includes(item[field]?.toLowerCase()) ? TONE_GOOD : TONE_BAD}>{item[field] || '-'}</span>
                                    )}
                                </td>
                            ))}
                            <td className={`${td} text-left`}>
                                {can_write ? <CellInput value={item.keterangan} onChange={(e) => updateItem(item._key, 'keterangan', e.target.value)} placeholder="Keterangan…" aria-label={`Keterangan baris ${index + 1}`} /> : item.keterangan || '-'}
                            </td>
                            {can_write && (
                                <td className="no-print border border-black p-1 text-center dark:border-border">
                                    <Button variant="ghost" size="icon" onClick={() => removeItem(item._key)} className="size-8 text-destructive hover:bg-destructive/10" title="Hapus baris ini">
                                        <Trash2 className="size-4" />
                                    </Button>
                                </td>
                            )}
                        </tr>
                    ))
                )}
            </tbody>
            <tfoot>
                <tr className="border-t-2 border-black bg-muted/40 text-[13px] font-bold dark:border-border">
                    <td colSpan={3} className="border-r border-black p-2 text-right uppercase dark:border-border">
                        TOTAL TITIK HYDRANT
                    </td>
                    <td className="border-r border-black p-2 text-center text-emerald-700 dark:border-border dark:text-emerald-400">
                        {summary.hose} / {summary.total} Normal
                    </td>
                    <td className="border-r border-black p-2 text-center text-sky-700 dark:border-border dark:text-sky-400">
                        {summary.nozzle} / {summary.total} Normal
                    </td>
                    <td className="border-r border-black p-2 text-center text-indigo-700 dark:border-border dark:text-indigo-400">
                        {summary.box} / {summary.total} Baik
                    </td>
                    <td className="border-r border-black p-2 text-center text-teal-700 dark:border-border dark:text-teal-400">
                        {summary.tekanan} / {summary.total} Baik
                    </td>
                    <td colSpan={can_write ? 2 : 1} className="p-2 text-muted-foreground italic">
                        {summary.total} Lokasi Terdaftar
                    </td>
                </tr>
            </tfoot>
        </table>
    );

    const mobileFields: RowField<HydrantItem>[] = [
        { key: 'lokasi', label: 'Lokasi', placeholder: 'Lokasi titik hydrant' },
        { key: 'tanggal_periksa', label: 'Tanggal Periksa', type: 'date', parse: (v) => isoToDmy(String(v)) },
        ...CONDITIONS.map(({ field, label, options: choices }, i): RowField<HydrantItem> => ({
            key: field,
            label,
            type: 'select',
            options: choices,
            optionTone: chipTone(O.good),
            group: i === 0 ? 'Kondisi' : undefined,
            parse: (v) => String(v) || choices[0],
        })),
        { key: 'keterangan', label: 'Keterangan', placeholder: 'Keterangan' },
    ];

    const mobile = (
        <MobileRowEditor<HydrantItem>
            rows={items.map((it) => ({ ...it, tanggal_periksa: dmyToIso(it.tanggal_periksa) }))}
            fields={mobileFields}
            title={(row) => row.lokasi || 'Lokasi belum diisi'}
            subtitle={(row) => `Hose ${row.hose} · Nozzle ${row.nozzle} · Tekanan ${row.kondisi_tekanan_air}`}
            onChange={(index, key, value) => updateItem(items[index]._key, key, value as string)}
            onRemove={(index) => removeItem(items[index]._key)}
            canWrite={can_write}
            rowKey={(row) => row._key}
            removeLabel="Hapus titik hydrant"
        />
    );

    const tools = (
        <>
            <div className="flex items-center gap-2">
                <Input type="date" value={dmyToIso(tanggalPeriksa)} onChange={(e) => setTanggalPeriksa(isoToDmy(e.target.value))} aria-label="Tanggal periksa untuk semua baris" className="w-40" />
                <Button type="button" variant="secondary" onClick={() => setAll({ tanggal_periksa: tanggalPeriksa })} title="Terapkan tanggal periksa ini ke seluruh baris">
                    Isi Tanggal Semua
                </Button>
            </div>
            <Button type="button" variant="outline" onClick={() => setAll(ALL_GOOD)} className="gap-1.5 text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">
                <CheckCircle2 className="size-4" />
                Setel Semua Normal & Baik
            </Button>
        </>
    );

    return (
        <K3Sheet
            title="Pemeriksaan Hydrant"
            formCode="SMT-FM-AK3-12.06"
            description="Pemeriksaan berkala kondisi hose, nozzle, box hydrant, dan tekanan air"
            docTitle="PEMERIKSAAN HYDRANT"
            unit={unit}
            filters={filters}
            units={options.units}
            years={options.years}
            onFilter={handleFilterChange}
            meta={meta}
            onMeta={(key, value) => {
                setMeta((m) => ({ ...m, [key]: value }));
                setDirty(true);
            }}
            catatan={catatan}
            onCatatan={(value) => {
                setCatatan(value);
                setDirty(true);
            }}
            catatanLabel="Catatan / Tindak Lanjut Pemeliharaan Hydrant:"
            catatanPlaceholder="Catatan khusus kondisi pipa, valve, atau tekanan pompa hydrant jika ada…"
            stats={[
                { label: `Total Titik: ${summary.total}` },
                { label: `Hose Normal: ${summary.hose} / ${summary.total}`, tone: 'good' },
                { label: `Nozzle Normal: ${summary.nozzle} / ${summary.total}`, tone: 'info' },
                { label: `Box Baik: ${summary.box} / ${summary.total}`, tone: 'info' },
                { label: `Tekanan Baik: ${summary.tekanan} / ${summary.total}`, tone: 'good' },
            ]}
            tools={tools}
            table={table}
            mobile={mobile}
            addLabel="Tambah Titik Hydrant"
            onAdd={addItem}
            hasSaved={has_saved}
            canWrite={can_write}
            dirty={dirty}
            saving={saving}
            onReset={resetChanges}
            onSave={handleSubmit}
            printCss={PRINT_CSS}
        />
    );
}
