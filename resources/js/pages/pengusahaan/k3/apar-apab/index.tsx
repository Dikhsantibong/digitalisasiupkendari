import { router } from '@inertiajs/react';
import { CheckCircle2, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { CellDate, CellInput, CellSelect, chipTone, dmyToIso, isoToDmy, K3_OPTIONS, TONE_BAD, TONE_GOOD, withCurrent } from '@/components/pengusahaan/k3-cells';
import { K3Sheet, sheetPrintCss } from '@/components/pengusahaan/k3-sheet';
import type { SheetMeta } from '@/components/pengusahaan/k3-sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import k3PengusahaanAparApab from '@/routes/k3/pengusahaan/apar-apab';
import type { IdName } from '@/types';

export type AparApabItem = {
    _key: number;
    id?: number | null;
    no_urut: number;
    no_rfid: string;
    lokasi: string;
    tgl_periksa: string;
    merk_apar: string;
    jenis_apar: string;
    berat_kg: number | string;
    kondisi_tabung: string;
    kondisi_nozzle_selang: string;
    indikator_tekanan: string;
    kondisi_pin_segel: string;
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
        catatan: string;
        items: Array<Omit<AparApabItem, '_key'>>;
    };
    has_saved: boolean;
    can_write: boolean;
};

const O = K3_OPTIONS.apar;
const PRINT_CSS = sheetPrintCss('apar-table', 10);

/** Whether the "Ex. DD/MM/YYYY" expiry in Keterangan lies before the active period. */
function isExpiredString(keterangan: string, filterYear: number, filterMonth: number): boolean {
    const match = (keterangan ?? '').trim().match(/(?:Ex\.?\s*)?(\d{1,2})[-/](\d{1,2})[-/](\d{4})/i);

    if (!match) {
        return false;
    }

    const itemMonth = parseInt(match[2], 10);
    const itemYear = parseInt(match[3], 10);

    return itemYear < filterYear || (itemYear === filterYear && itemMonth < filterMonth);
}

const hydrate = (items: Props['record']['items']): AparApabItem[] =>
    items.map((it, idx) => ({
        ...it,
        _key: idx + 1,
        berat_kg: it.berat_kg ?? '',
        kondisi_tabung: it.kondisi_tabung ?? 'baik',
        kondisi_nozzle_selang: it.kondisi_nozzle_selang ?? 'baik',
        indikator_tekanan: it.indikator_tekanan ?? 'ok',
        kondisi_pin_segel: it.kondisi_pin_segel ?? 'baik',
    }));

const tone = (value: string) => (O.good.includes((value ?? '').toLowerCase()) ? TONE_GOOD : TONE_BAD);

export default function PengusahaanAparApabPage({ unit, filters, options, record: initialRecord, has_saved, can_write }: Props) {
    const [meta, setMeta] = useState<SheetMeta>({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
    const [catatan, setCatatan] = useState(initialRecord.catatan);
    const [items, setItems] = useState<AparApabItem[]>(() => hydrate(initialRecord.items));
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRecord.items.length + 10);
    const [bulkDate, setBulkDate] = useState(`12/${String(filters.month).padStart(2, '0')}/${filters.year}`);

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', value: number) => {
        router.get(k3PengusahaanAparApab.index().url, { ...filters, [key]: value }, { preserveState: false, preserveScroll: true });
    };

    const addItem = () => {
        setItems((prev) => [
            ...prev,
            {
                _key: nextKey,
                no_urut: prev.length + 1,
                no_rfid: '',
                lokasi: '',
                tgl_periksa: bulkDate,
                merk_apar: 'Fire Venom',
                jenis_apar: 'Gas Cair',
                berat_kg: 6,
                kondisi_tabung: 'baik',
                kondisi_nozzle_selang: 'baik',
                indikator_tekanan: 'ok',
                kondisi_pin_segel: 'baik',
                keterangan: '',
                sort_order: prev.length,
            },
        ]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const updateItem = (key: number, field: keyof AparApabItem, value: string | number) => {
        setItems((prev) => prev.map((item) => (item._key === key ? { ...item, [field]: value } : item)));
        setDirty(true);
    };

    const removeItem = (key: number) => {
        setItems((prev) => prev.filter((it) => it._key !== key));
        setDirty(true);
    };

    const setAll = (patch: Partial<AparApabItem>) => {
        setItems((prev) => prev.map((item) => ({ ...item, ...patch })));
        setDirty(true);
    };

    const resetChanges = () => {
        setMeta({ no_dokumen: initialRecord.no_dokumen, revisi: initialRecord.revisi, tanggal_dokumen: initialRecord.tanggal_dokumen });
        setCatatan(initialRecord.catatan);
        setItems(hydrate(initialRecord.items));
        setDirty(false);
    };

    const handleSubmit = () => {
        setSaving(true);
        router.post(
            k3PengusahaanAparApab.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...meta,
                catatan,
                items: items.map((it, idx) => ({
                    no_urut: idx + 1,
                    no_rfid: it.no_rfid,
                    lokasi: it.lokasi,
                    tgl_periksa: it.tgl_periksa,
                    merk_apar: it.merk_apar,
                    jenis_apar: it.jenis_apar,
                    berat_kg: it.berat_kg === '' ? null : Number(it.berat_kg),
                    kondisi_tabung: it.kondisi_tabung,
                    kondisi_nozzle_selang: it.kondisi_nozzle_selang,
                    indikator_tekanan: it.indikator_tekanan,
                    kondisi_pin_segel: it.kondisi_pin_segel,
                    keterangan: it.keterangan,
                    sort_order: idx,
                })),
            },
            { preserveScroll: true, preserveState: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const summary = useMemo(() => {
        const count = (field: keyof AparApabItem, good: string) => items.filter((it) => String(it[field] ?? '').toLowerCase() === good).length;

        return {
            total: items.length,
            tabungBaik: count('kondisi_tabung', 'baik'),
            tekananOk: count('indikator_tekanan', 'ok'),
            pinBaik: count('kondisi_pin_segel', 'baik'),
            expired: items.filter((it) => isExpiredString(it.keterangan, filters.year, filters.month)).length,
        };
    }, [items, filters.year, filters.month]);

    const conditionColumns: { field: keyof AparApabItem; label: string; options: string[] }[] = [
        { field: 'kondisi_tabung', label: 'Kondisi Tabung', options: O.tabung },
        { field: 'kondisi_nozzle_selang', label: 'Kondisi Nozzle/Selang', options: O.nozzle },
        { field: 'indikator_tekanan', label: 'Indikator Tekanan', options: O.tekanan },
        { field: 'kondisi_pin_segel', label: 'Kondisi Pin/Segel', options: O.pin },
    ];

    const th = 'border border-black p-2 text-[13px] font-bold dark:border-border';
    const td = 'border-r border-black p-1 text-center align-middle dark:border-border';

    const table = (
        <table className="apar-table w-full min-w-[1100px] border-collapse border-2 border-black text-sm dark:border-border">
            <thead>
                <tr className="bg-muted/40 text-center text-foreground">
                    <th className={`${th} w-12`}>NO.</th>
                    <th className={`${th} w-24`}>NO RFID</th>
                    <th className={`${th} w-56 text-left`}>LOKASI</th>
                    <th className={`${th} w-36`}>TGL PERIKSA</th>
                    <th className={`${th} w-32`}>MERK APAR</th>
                    <th className={`${th} w-28`}>JENIS APAR</th>
                    <th className={`${th} w-20`}>BERAT (KG)</th>
                    <th className={`${th} w-28`}>KONDISI TABUNG</th>
                    <th className={`${th} w-28 leading-tight`}>KONDISI NOZZLE/ SELANG</th>
                    <th className={`${th} w-28 leading-tight`}>INDIKATOR TEKANAN TABUNG</th>
                    <th className={`${th} w-28 leading-tight`}>KONDISI PIN/SEGEL</th>
                    <th className={`${th} w-36`}>KETERANGAN</th>
                    {can_write && <th className={`${th} no-print w-12`}>AKSI</th>}
                </tr>
            </thead>
            <tbody>
                {items.length === 0 ? (
                    <tr>
                        <td colSpan={can_write ? 13 : 12} className="p-6 text-center text-muted-foreground italic">
                            Belum ada data tabung APAR/APAB. Klik "Tambah Tabung APAR / APAB" untuk menambahkan.
                        </td>
                    </tr>
                ) : (
                    items.map((item, index) => {
                        const expired = isExpiredString(item.keterangan, filters.year, filters.month);

                        return (
                            <tr key={item._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                                <td className={`${td} font-mono`}>{index + 1}</td>
                                <td className={td}>
                                    {can_write ? <CellInput value={item.no_rfid} onChange={(e) => updateItem(item._key, 'no_rfid', e.target.value)} placeholder="-" aria-label={`No RFID baris ${index + 1}`} className="text-center font-mono" /> : item.no_rfid || '-'}
                                </td>
                                <td className={`${td} text-left font-medium`}>
                                    {can_write ? <CellInput value={item.lokasi} onChange={(e) => updateItem(item._key, 'lokasi', e.target.value)} placeholder="Lokasi penempatan…" aria-label={`Lokasi baris ${index + 1}`} /> : item.lokasi}
                                </td>
                                <td className={td}>
                                    {can_write ? <CellDate value={item.tgl_periksa} onChange={(v) => updateItem(item._key, 'tgl_periksa', v)} ariaLabel={`Tanggal periksa baris ${index + 1}`} /> : item.tgl_periksa || '-'}
                                </td>
                                <td className={`${td} font-semibold`}>
                                    {can_write ? <CellInput list="apar-merk" value={item.merk_apar} onChange={(e) => updateItem(item._key, 'merk_apar', e.target.value)} placeholder="Merk…" aria-label={`Merk baris ${index + 1}`} className="text-center" /> : item.merk_apar || '-'}
                                </td>
                                <td className={td}>
                                    {can_write ? <CellSelect value={item.jenis_apar} options={withCurrent(O.jenis, item.jenis_apar)} onChange={(v) => updateItem(item._key, 'jenis_apar', v)} ariaLabel={`Jenis APAR baris ${index + 1}`} /> : item.jenis_apar || '-'}
                                </td>
                                <td className={`${td} font-mono`}>
                                    {can_write ? (
                                        <CellInput type="number" step="any" min={0} list="apar-berat" value={item.berat_kg} onChange={(e) => updateItem(item._key, 'berat_kg', e.target.value)} placeholder="-" aria-label={`Berat baris ${index + 1}`} className="text-center font-mono" />
                                    ) : item.berat_kg !== '' && item.berat_kg !== null ? (
                                        item.berat_kg
                                    ) : (
                                        '-'
                                    )}
                                </td>
                                {conditionColumns.map(({ field, label, options: choices }) => (
                                    <td key={field} className={td}>
                                        {can_write ? (
                                            <CellSelect value={String(item[field] ?? '')} options={choices} good={O.good} onChange={(v) => updateItem(item._key, field, v)} ariaLabel={`${label} baris ${index + 1}`} />
                                        ) : (
                                            <span className={tone(String(item[field] ?? ''))}>{String(item[field] ?? '-')}</span>
                                        )}
                                    </td>
                                ))}
                                <td className={`${td} ${expired ? TONE_BAD : ''}`}>
                                    {can_write ? (
                                        <CellInput value={item.keterangan} onChange={(e) => updateItem(item._key, 'keterangan', e.target.value)} placeholder="Ex. DD/MM/YYYY" aria-label={`Keterangan baris ${index + 1}`} className={`text-center font-mono ${expired ? TONE_BAD : ''}`} />
                                    ) : (
                                        item.keterangan || '-'
                                    )}
                                </td>
                                {can_write && (
                                    <td className="no-print border border-black p-1 text-center dark:border-border">
                                        <Button variant="ghost" size="icon" onClick={() => removeItem(item._key)} className="size-8 text-destructive hover:bg-destructive/10" title="Hapus baris ini">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        );
                    })
                )}
            </tbody>
        </table>
    );

    const mobileFields: RowField<AparApabItem>[] = [
        { key: 'lokasi', label: 'Lokasi', placeholder: 'Lokasi penempatan', group: 'Identitas' },
        { key: 'no_rfid', label: 'No RFID', placeholder: '-' },
        { key: 'tgl_periksa', label: 'Tanggal Periksa', type: 'date', parse: (v) => isoToDmy(String(v)) },
        { key: 'merk_apar', label: 'Merk APAR', placeholder: 'Merk' },
        { key: 'jenis_apar', label: 'Jenis APAR', type: 'select', options: withCurrent(O.jenis, '') },
        { key: 'berat_kg', label: 'Berat (kg)', type: 'number', parse: (v) => String(v) },
        ...conditionColumns.map(({ field, label, options: choices }): RowField<AparApabItem> => ({
            key: field,
            label,
            type: 'select',
            options: choices,
            optionTone: chipTone(O.good),
            group: field === 'kondisi_tabung' ? 'Kondisi' : undefined,
            parse: (v) => String(v) || choices[0],
        })),
        { key: 'keterangan', label: 'Keterangan / Masa Berlaku', placeholder: 'Ex. DD/MM/YYYY' },
    ];

    const mobile = (
        <MobileRowEditor<AparApabItem>
            rows={items.map((it) => ({ ...it, tgl_periksa: dmyToIso(it.tgl_periksa) }))}
            fields={mobileFields}
            title={(row) => row.lokasi || 'Lokasi belum diisi'}
            subtitle={(row) => `${row.jenis_apar || '-'} · ${row.berat_kg || '-'} kg · Tabung ${row.kondisi_tabung}${isExpiredString(row.keterangan, filters.year, filters.month) ? ' · Kadaluarsa' : ''}`}
            onChange={(index, key, value) => updateItem(items[index]._key, key, value as string)}
            onRemove={(index) => removeItem(items[index]._key)}
            canWrite={can_write}
            rowKey={(row) => row._key}
            removeLabel="Hapus tabung"
        />
    );

    const tools = (
        <>
            <div className="flex items-center gap-2">
                <Input type="date" value={dmyToIso(bulkDate)} onChange={(e) => setBulkDate(isoToDmy(e.target.value))} aria-label="Tanggal periksa untuk semua baris" className="w-40" />
                <Button type="button" variant="secondary" onClick={() => setAll({ tgl_periksa: bulkDate })} title="Terapkan tanggal ini ke seluruh baris">
                    Isi Tanggal Semua
                </Button>
            </div>
            <Button
                type="button"
                variant="outline"
                onClick={() => setAll({ kondisi_tabung: 'baik', kondisi_nozzle_selang: 'baik', indikator_tekanan: 'ok', kondisi_pin_segel: 'baik' })}
                className="gap-1.5 text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
            >
                <CheckCircle2 className="size-4" />
                Setel Semua Baik & OK
            </Button>
        </>
    );

    return (
        <>
            <datalist id="apar-merk">
                {O.merk.map((merk) => (
                    <option key={merk} value={merk} />
                ))}
            </datalist>
            <datalist id="apar-berat">
                {O.berat.map((berat) => (
                    <option key={berat} value={berat} />
                ))}
            </datalist>
            <K3Sheet
                title="Kondisi APAR dan APAB"
                formCode="SMT-FM-AK3-12.03"
                description="Pemeriksaan fisik, tekanan, pin segel, dan masa kadaluarsa APAR & APAB"
                docTitle="KONDISI ALAT PEMADAM API RINGAN DAN BESAR (APAR-APAB)"
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
                catatanLabel="Catatan / Tindak Lanjut Pemeliharaan APAR:"
                catatanPlaceholder="Catatan khusus kondisi tabung atau jadwal isi ulang APAR/APAB jika ada…"
                stats={[
                    { label: `Total Tabung: ${summary.total} Unit` },
                    { label: `Tabung Baik: ${summary.tabungBaik} / ${summary.total}`, tone: 'good' },
                    { label: `Tekanan OK: ${summary.tekananOk} / ${summary.total}`, tone: 'info' },
                    { label: `Pin/Segel Baik: ${summary.pinBaik} / ${summary.total}`, tone: 'info' },
                    ...(summary.expired > 0 ? [{ label: `${summary.expired} Tabung Kadaluarsa!`, tone: 'bad' as const }] : []),
                ]}
                tools={tools}
                table={table}
                mobile={mobile}
                addLabel="Tambah Tabung APAR / APAB"
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
