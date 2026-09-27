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
import k3PengusahaanApat from '@/routes/k3/pengusahaan/apat';
import type { IdName } from '@/types';

export type ApatItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    nama_alat: string;
    tanggal_inspeksi: string;
    kondisi: string;
    jumlah: number | string;
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
        tanggal_inspeksi: string;
        catatan: string;
        items: Array<Omit<ApatItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const O = K3_OPTIONS.apat;
const PRINT_CSS = sheetPrintCss('apat-table', 11);

export default function PengusahaanApatPage({ unit, filters, options, record: initialRecord, has_saved, can_write }: Props) {
    const defaultDate = initialRecord.tanggal_inspeksi || `12/${String(filters.month).padStart(2, '0')}/${filters.year}`;
    const hydrate = (): ApatItem[] =>
        initialRecord.items.map((it, idx) => ({
            ...it,
            _key: idx + 1,
            jumlah: it.jumlah ?? 0,
            kondisi: it.kondisi ?? 'Baik',
            tanggal_inspeksi: it.tanggal_inspeksi || defaultDate,
        }));

    const [meta, setMeta] = useState<SheetMeta>({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
    const [tanggalInspeksi, setTanggalInspeksi] = useState(defaultDate);
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<ApatItem[]>(hydrate);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: number) => {
        router.get(k3PengusahaanApat.index().url, { ...filters, [key]: value }, { preserveState: false, preserveScroll: true });
    };

    const addItem = () => {
        setItems((prev) => [
            ...prev,
            { _key: nextKey, no_urut: prev.length + 1, nama_alat: '', tanggal_inspeksi: tanggalInspeksi, kondisi: 'Baik', jumlah: 1, keterangan: '', sort_order: prev.length },
        ]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof ApatItem, value: string | number) => {
        setItems((prev) => prev.map((item) => (item._key === key ? { ...item, [field]: value } : item)));
        setDirty(true);
    };

    const removeItem = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    const setAll = (patch: Partial<ApatItem>) => {
        setItems((prev) => prev.map((item) => ({ ...item, ...patch })));
        setDirty(true);
    };

    const resetChanges = () => {
        setMeta({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
        setTanggalInspeksi(defaultDate);
        setCatatan(initialRecord.catatan);
        setItems(hydrate());
        setDirty(false);
    };

    const handleSubmit = () => {
        setSaving(true);
        router.post(
            k3PengusahaanApat.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...meta,
                tanggal_inspeksi: tanggalInspeksi,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    nama_alat: it.nama_alat,
                    tanggal_inspeksi: it.tanggal_inspeksi || tanggalInspeksi,
                    kondisi: it.kondisi,
                    jumlah: it.jumlah === '' ? 0 : Number(it.jumlah),
                    keterangan: it.keterangan,
                    sort_order: idx,
                })),
            },
            { preserveScroll: true, preserveState: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const summary = useMemo(
        () => ({
            total: items.length,
            jumlah: items.reduce((sum, it) => sum + (it.jumlah !== '' && !isNaN(Number(it.jumlah)) ? Number(it.jumlah) : 0), 0),
            baik: items.filter((it) => it.kondisi?.toLowerCase() === 'baik').length,
        }),
        [items],
    );

    const th = 'border border-black p-2 text-[13px] font-bold dark:border-border';
    const td = 'border-r border-black p-1.5 text-center align-middle dark:border-border';

    const table = (
        <table className="apat-table w-full min-w-[900px] border-collapse border-2 border-black text-sm dark:border-border">
            <thead>
                <tr className="bg-muted/40 text-center text-foreground">
                    <th className={`${th} w-12`}>NO</th>
                    <th className={`${th} w-72 text-left`}>NAMA ALAT</th>
                    <th className={`${th} w-44`}>TANGGAL INSPEKSI</th>
                    <th className={`${th} w-36`}>KONDISI</th>
                    <th className={`${th} w-28`}>JUMLAH</th>
                    <th className={`${th} text-left`}>KET</th>
                    {can_write && <th className={`${th} no-print w-12`}>AKSI</th>}
                </tr>
            </thead>
            <tbody>
                {items.length === 0 ? (
                    <tr>
                        <td colSpan={can_write ? 7 : 6} className="p-6 text-center text-muted-foreground italic">
                            Belum ada data alat pemadam api tradisional. Klik "Tambah Jenis Alat" untuk menambahkan.
                        </td>
                    </tr>
                ) : (
                    items.map((item, index) => (
                        <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                            <td className={`${td} font-mono`}>{index + 1}</td>
                            <td className={`${td} text-left font-semibold uppercase`}>
                                {can_write ? <CellInput list="apat-alat" value={item.nama_alat} onChange={(e) => updateItem(item._key, 'nama_alat', e.target.value)} placeholder="Nama peralatan…" aria-label={`Nama alat baris ${index + 1}`} className="font-semibold uppercase" /> : item.nama_alat}
                            </td>
                            <td className={td}>
                                {can_write ? <CellDate value={item.tanggal_inspeksi} onChange={(v) => updateItem(item._key, 'tanggal_inspeksi', v)} ariaLabel={`Tanggal inspeksi baris ${index + 1}`} /> : item.tanggal_inspeksi || '-'}
                            </td>
                            <td className={td}>
                                {can_write ? (
                                    <CellSelect value={item.kondisi} options={O.kondisi} good={O.good} onChange={(v) => updateItem(item._key, 'kondisi', v)} ariaLabel={`Kondisi baris ${index + 1}`} />
                                ) : (
                                    <span className={O.good.includes(item.kondisi?.toLowerCase()) ? TONE_GOOD : TONE_BAD}>{item.kondisi || '-'}</span>
                                )}
                            </td>
                            <td className={`${td} font-mono font-bold`}>
                                {can_write ? <CellInput type="number" min={0} value={item.jumlah} onChange={(e) => updateItem(item._key, 'jumlah', parseInt(e.target.value, 10) || 0)} aria-label={`Jumlah baris ${index + 1}`} className="text-center font-mono font-bold" /> : item.jumlah}
                            </td>
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
                <tr className="border-t-2 border-black bg-muted/40 font-bold dark:border-border">
                    <td colSpan={4} className="border-r border-black p-2 text-right uppercase dark:border-border">
                        TOTAL PERALATAN TRADISIONAL
                    </td>
                    <td className="border-r border-black p-2 text-center font-mono text-primary dark:border-border">{summary.jumlah}</td>
                    <td colSpan={can_write ? 2 : 1} className="p-2 text-[13px] text-muted-foreground italic">
                        Kondisi Baik: {summary.baik} dari {summary.total} jenis alat
                    </td>
                </tr>
            </tfoot>
        </table>
    );

    const mobileFields: RowField<ApatItem>[] = [
        { key: 'nama_alat', label: 'Nama Alat', placeholder: 'Mis. Karung Goni' },
        { key: 'tanggal_inspeksi', label: 'Tanggal Inspeksi', type: 'date', parse: (v) => isoToDmy(String(v)) },
        { key: 'kondisi', label: 'Kondisi', type: 'select', options: O.kondisi, optionTone: chipTone(O.good), parse: (v) => String(v) || 'Baik' },
        { key: 'jumlah', label: 'Jumlah', type: 'number' },
        { key: 'keterangan', label: 'Keterangan', placeholder: 'Keterangan' },
    ];

    const mobile = (
        <MobileRowEditor<ApatItem>
            rows={items.map((it) => ({ ...it, tanggal_inspeksi: dmyToIso(it.tanggal_inspeksi) }))}
            fields={mobileFields}
            title={(row) => row.nama_alat || 'Nama alat belum diisi'}
            subtitle={(row) => `${row.jumlah} buah · ${row.kondisi}`}
            onChange={(index, key, value) => updateItem(items[index]._key, key, value as string | number)}
            onRemove={(index) => removeItem(items[index]._key)}
            canWrite={can_write}
            rowKey={(row) => row._key}
            removeLabel="Hapus alat"
        />
    );

    const tools = (
        <>
            <div className="flex items-center gap-2">
                <Input type="date" value={dmyToIso(tanggalInspeksi)} onChange={(e) => setTanggalInspeksi(isoToDmy(e.target.value))} aria-label="Tanggal inspeksi untuk semua baris" className="w-40" />
                <Button type="button" variant="secondary" onClick={() => setAll({ tanggal_inspeksi: tanggalInspeksi })} title="Terapkan tanggal inspeksi ini ke seluruh item">
                    Isi Tanggal Semua
                </Button>
            </div>
            <Button type="button" variant="outline" onClick={() => setAll({ kondisi: 'Baik' })} className="gap-1.5 text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">
                <CheckCircle2 className="size-4" />
                Setel Semua Baik
            </Button>
        </>
    );

    return (
        <>
            <datalist id="apat-alat">
                {O.alat.map((alat) => (
                    <option key={alat} value={alat} />
                ))}
            </datalist>
            <K3Sheet
                title="Kondisi Alat Pemadam Api Tradisional (APAT)"
                formCode="SMT-FM-AK3-12.04"
                description="Pemeriksaan dan monitoring kesiapan alat pemadam api tradisional"
                docTitle="KONDISI ALAT PEMADAM API TRADISIONAL"
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
                catatanLabel="Catatan / Tindak Lanjut:"
                catatanPlaceholder="Catatan khusus kondisi alat pemadam api tradisional jika ada…"
                stats={[
                    { label: `Jenis Alat: ${summary.total} Jenis` },
                    { label: `Total Jumlah: ${summary.jumlah} Unit/Buah`, tone: 'good' },
                    { label: `Kondisi Baik: ${summary.baik} / ${summary.total} Jenis`, tone: 'info' },
                ]}
                tools={tools}
                table={table}
                mobile={mobile}
                addLabel="Tambah Jenis Alat"
                onAdd={addItem}
                hasSaved={has_saved}
                canWrite={can_write}
                dirty={dirty}
                saving={saving}
                onReset={resetChanges}
                onSave={handleSubmit}
                printCss={PRINT_CSS}
            />
        </>
    );
}
