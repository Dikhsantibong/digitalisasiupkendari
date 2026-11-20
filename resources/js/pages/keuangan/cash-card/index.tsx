import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    FileText,
    ImagePlus,
    Plus,
    Printer,
    Save,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import cashCard from '@/routes/keuangan/cash-card';
import keuanganDocument from '@/routes/keuangan/document';
import supplierRoutes from '@/routes/keuangan/supplier';
import type { IdName } from '@/types';

type Photo = { path: string; url: string | null };

type Entry = {
    id?: number;
    tanggal: string | null;
    period: number | null;
    no_entry: number | null;
    uraian: string | null;
    supplier_id: number | null;
    penerimaan: number;
    pengeluaran: number;
    kode_perkiraan: string | null;
    kode_sap: string | null;
    cost_centre: string | null;
    photos: Photo[];
};

type Row = Entry & { files: File[] };

type Supplier = {
    id: number;
    name: string;
    email: string | null;
    npwp: string | null;
    has_ktp: boolean;
    is_active: boolean;
};

type Props = {
    ul: IdName;
    filters: { ul_id: number; month: number; year: number };
    options: { uls: IdName[]; years: number[] };
    saldo_awal: number;
    has_saved: boolean;
    entries: Entry[];
    suppliers: Supplier[];
    can_write: boolean;
    can_manage_suppliers: boolean;
};

const NO_SUPPLIER = 'none';
const MAX_PHOTOS = 8;

const money = (value: number): string =>
    value === 0 ? '-' : new Intl.NumberFormat('id-ID').format(value);

const blankRow = (index: number): Row => ({
    tanggal: null,
    period: index + 1,
    no_entry: index + 1,
    uraian: '',
    supplier_id: null,
    penerimaan: 0,
    pengeluaran: 0,
    kode_perkiraan: null,
    kode_sap: null,
    cost_centre: null,
    photos: [],
    files: [],
});

/**
 * Laporan Realisasi Cash Card of a UL (Administrasi & Keuangan, JOF
 * Administrasi Umum): the month's rows with the running saldo, the supplier
 * from the Master Supplier, photo attachments per row, and per row the Bukti
 * Penerimaan Kas Kecil + Lampiran (the photos plus the supplier's KTP, e-mail
 * & NPWP) printed automatically.
 */
export default function CashCardIndex({
    ul,
    filters,
    options,
    saldo_awal,
    has_saved,
    entries,
    suppliers,
    can_write,
    can_manage_suppliers,
}: Props) {
    const [saldoAwal, setSaldoAwal] = useState(saldo_awal);
    const [rows, setRows] = useState<Row[]>(() =>
        entries.length > 0
            ? entries.map((entry) => ({ ...entry, files: [] }))
            : [blankRow(0)],
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [buktiColumns, setBuktiColumns] = useState(false);
    const query = {
        ul_id: filters.ul_id,
        month: filters.month,
        year: filters.year,
    };
    const supplierById = useMemo(
        () => new Map(suppliers.map((s) => [s.id, s])),
        [suppliers],
    );

    const visit = (patch: Partial<typeof query>) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan. Pindah halaman?')
        ) {
            return;
        }

        router.get(cashCard.index({ query: { ...query, ...patch } }).url);
    };

    const update = (index: number, patch: Partial<Row>) => {
        setRows((current) =>
            current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
        setDirty(true);
    };

    // Running saldo after each row.
    const saldo = useMemo(
        () =>
            rows.reduce<number[]>(
                (list, row) => [
                    ...list,
                    (list.length > 0 ? list[list.length - 1] : saldoAwal) +
                        (row.penerimaan || 0) -
                        (row.pengeluaran || 0),
                ],
                [],
            ),
        [rows, saldoAwal],
    );
    const totalIn = rows.reduce((sum, row) => sum + (row.penerimaan || 0), 0);
    const totalOut = rows.reduce((sum, row) => sum + (row.pengeluaran || 0), 0);

    const save = () => {
        setSaving(true);
        router.post(
            cashCard.store().url,
            {
                ...query,
                saldo_awal: saldoAwal,
                rows: rows.map((row) => ({
                    tanggal: row.tanggal ?? '',
                    period: row.period ?? '',
                    no_entry: row.no_entry ?? '',
                    uraian: row.uraian ?? '',
                    supplier_id: row.supplier_id ?? '',
                    penerimaan: row.penerimaan || 0,
                    pengeluaran: row.pengeluaran || 0,
                    kode_perkiraan: row.kode_perkiraan ?? '',
                    kode_sap: row.kode_sap ?? '',
                    cost_centre: row.cost_centre ?? '',
                    photos_keep: row.photos.map((photo) => photo.path),
                    files: row.files,
                })),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as unknown as Props;
                    setRows(
                        props.entries.length > 0
                            ? props.entries.map((e) => ({ ...e, files: [] }))
                            : [blankRow(0)],
                    );
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const buktiUrl = (entryId?: number) =>
        cashCard.bukti({
            query: { ...query, ...(entryId ? { entry: entryId } : {}) },
        }).url;
    const number = (value: string) => {
        const digits = value.replace(/[^\d]/g, '');

        return digits === '' ? 0 : Number(digits);
    };
    const cell =
        'h-8 rounded-none border-0 bg-transparent px-1.5 text-[12.5px] shadow-none focus-visible:ring-0';

    return (
        <>
            <Head title="Laporan Realisasi Cash Card" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Laporan Realisasi Cash Card — ${ul.name}`}
                    description="Isi transaksi cash card bulan ini, pilih supplier dari Master Supplier, dan unggah foto nota/barang per baris. Bukti Penerimaan Kas Kecil & Lampiran (foto + KTP, email, NPWP supplier) dibuat otomatis."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={keuanganDocument.show({ query }).url}
                                >
                                    <FileText className="size-4" /> Dokumen
                                    Laporan (Edit & Cetak)
                                </Link>
                            </Button>
                            {has_saved && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={buktiUrl()}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Printer className="size-4" /> Semua
                                        Bukti & Lampiran
                                    </a>
                                </Button>
                            )}
                            {can_write && (
                                <Button onClick={save} disabled={saving}>
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    {options.uls.length > 1 && (
                        <OperasiSelect
                            label="Unit Layanan (UL)"
                            value={String(filters.ul_id)}
                            onChange={(value) =>
                                visit({ ul_id: Number(value) })
                            }
                            className="w-56"
                            options={options.uls.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                        />
                    )}
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">
                            Saldo awal (Rp)
                        </span>
                        <Input
                            inputMode="numeric"
                            value={saldoAwal === 0 ? '' : String(saldoAwal)}
                            onChange={(e) => {
                                setSaldoAwal(number(e.target.value));
                                setDirty(true);
                            }}
                            placeholder="0"
                            className="h-9 w-40 bg-card"
                            disabled={!can_write}
                        />
                    </label>
                    <label className="flex items-center gap-2 pb-2 text-[13px]">
                        <Checkbox
                            checked={buktiColumns}
                            onCheckedChange={(checked) =>
                                setBuktiColumns(checked === true)
                            }
                        />
                        Kolom bukti (kode perkiraan, SAP, cost centre)
                    </label>
                    <Button
                        variant="outline"
                        className="ml-auto"
                        onClick={() =>
                            router.get(
                                supplierRoutes.index({
                                    query: { ul_id: filters.ul_id },
                                }).url,
                            )
                        }
                    >
                        <Users className="size-4" />
                        {can_manage_suppliers
                            ? 'Kelola Master Supplier'
                            : 'Master Supplier'}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table
                        className={`excel-sheet w-full text-[12.5px] ${buktiColumns ? 'min-w-[1650px]' : 'min-w-[1280px]'}`}
                    >
                        <thead>
                            <tr>
                                <th className="w-9 p-1.5">No</th>
                                <th className="w-32 p-1.5">Tanggal</th>
                                <th className="w-16 p-1.5">Period</th>
                                <th className="w-16 p-1.5">No. Entry</th>
                                <th className="min-w-[260px] p-1.5">Uraian</th>
                                <th className="w-48 p-1.5">Supplier</th>
                                <th className="w-32 p-1.5">Nilai Penerimaan</th>
                                <th className="w-32 p-1.5">
                                    Nilai Pengeluaran
                                </th>
                                <th className="w-32 p-1.5">Saldo</th>
                                {buktiColumns && (
                                    <>
                                        <th className="w-32 p-1.5">
                                            Kode Perkiraan
                                        </th>
                                        <th className="w-28 p-1.5">Kode SAP</th>
                                        <th className="w-28 p-1.5">
                                            Cost Centre
                                        </th>
                                    </>
                                )}
                                <th className="w-56 p-1.5">Lampiran (foto)</th>
                                <th className="w-24 p-1.5">Bukti</th>
                                {can_write && <th className="w-9 p-1.5" />}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => {
                                const supplier =
                                    row.supplier_id !== null
                                        ? supplierById.get(row.supplier_id)
                                        : undefined;
                                const photoCount =
                                    row.photos.length + row.files.length;

                                return (
                                    <tr key={index}>
                                        <td className="text-center">
                                            {index + 1}
                                        </td>
                                        <td>
                                            <Input
                                                type="date"
                                                value={row.tanggal ?? ''}
                                                onChange={(e) =>
                                                    update(index, {
                                                        tanggal:
                                                            e.target.value ||
                                                            null,
                                                    })
                                                }
                                                className={cell}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                inputMode="numeric"
                                                value={row.period ?? ''}
                                                onChange={(e) =>
                                                    update(index, {
                                                        period:
                                                            number(
                                                                e.target.value,
                                                            ) || null,
                                                    })
                                                }
                                                className={`${cell} text-center`}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                inputMode="numeric"
                                                value={row.no_entry ?? ''}
                                                onChange={(e) =>
                                                    update(index, {
                                                        no_entry:
                                                            number(
                                                                e.target.value,
                                                            ) || null,
                                                    })
                                                }
                                                className={`${cell} text-center`}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                value={row.uraian ?? ''}
                                                onChange={(e) =>
                                                    update(index, {
                                                        uraian: e.target.value,
                                                    })
                                                }
                                                placeholder="mis. By. Perlengkapan Kantor (ATK dll). Poasia"
                                                className={cell}
                                                disabled={!can_write}
                                            />
                                            {supplier && (
                                                <p className="px-1.5 pb-1 text-[11px] text-muted-foreground">
                                                    … / {supplier.name}
                                                </p>
                                            )}
                                        </td>
                                        <td>
                                            <Select
                                                value={
                                                    row.supplier_id !== null
                                                        ? String(
                                                              row.supplier_id,
                                                          )
                                                        : NO_SUPPLIER
                                                }
                                                onValueChange={(value) =>
                                                    update(index, {
                                                        supplier_id:
                                                            value ===
                                                            NO_SUPPLIER
                                                                ? null
                                                                : Number(value),
                                                    })
                                                }
                                                disabled={!can_write}
                                            >
                                                <SelectTrigger className="h-8 w-full rounded-none border-0 shadow-none">
                                                    <SelectValue placeholder="—" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={NO_SUPPLIER}
                                                    >
                                                        — Tanpa supplier —
                                                    </SelectItem>
                                                    {suppliers
                                                        .filter(
                                                            (s) =>
                                                                s.is_active ||
                                                                s.id ===
                                                                    row.supplier_id,
                                                        )
                                                        .map((s) => (
                                                            <SelectItem
                                                                key={s.id}
                                                                value={String(
                                                                    s.id,
                                                                )}
                                                            >
                                                                {s.name}
                                                            </SelectItem>
                                                        ))}
                                                </SelectContent>
                                            </Select>
                                            {supplier && (
                                                <p className="px-1.5 pb-1 text-[11px] text-muted-foreground">
                                                    {[
                                                        supplier.email,
                                                        supplier.npwp &&
                                                            `NPWP ${supplier.npwp}`,
                                                        supplier.has_ktp
                                                            ? 'KTP ✓'
                                                            : 'KTP belum ada',
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            )}
                                        </td>
                                        <td>
                                            <Input
                                                inputMode="numeric"
                                                value={
                                                    row.penerimaan
                                                        ? String(row.penerimaan)
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    update(index, {
                                                        penerimaan: number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                                className={`${cell} text-right`}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                inputMode="numeric"
                                                value={
                                                    row.pengeluaran
                                                        ? String(
                                                              row.pengeluaran,
                                                          )
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    update(index, {
                                                        pengeluaran: number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                                className={`${cell} text-right`}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td className="px-1.5 text-right font-semibold tabular-nums">
                                            {money(saldo[index])}
                                        </td>
                                        {buktiColumns &&
                                            (
                                                [
                                                    'kode_perkiraan',
                                                    'kode_sap',
                                                    'cost_centre',
                                                ] as const
                                            ).map((key) => (
                                                <td key={key}>
                                                    <Input
                                                        value={row[key] ?? ''}
                                                        onChange={(e) =>
                                                            update(index, {
                                                                [key]:
                                                                    e.target
                                                                        .value ||
                                                                    null,
                                                            })
                                                        }
                                                        className={cell}
                                                        disabled={!can_write}
                                                    />
                                                </td>
                                            ))}
                                        <td className="p-1">
                                            <div className="flex flex-wrap items-center gap-1">
                                                {row.photos.map((photo, p) => (
                                                    <span
                                                        key={photo.path}
                                                        className="relative"
                                                    >
                                                        <a
                                                            href={
                                                                photo.url ?? '#'
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <img
                                                                src={
                                                                    photo.url ??
                                                                    ''
                                                                }
                                                                alt={`Lampiran ${p + 1}`}
                                                                className="size-10 rounded object-cover"
                                                            />
                                                        </a>
                                                        {can_write && (
                                                            <button
                                                                type="button"
                                                                aria-label="Hapus foto"
                                                                onClick={() =>
                                                                    update(
                                                                        index,
                                                                        {
                                                                            photos: row.photos.filter(
                                                                                (
                                                                                    _,
                                                                                    i,
                                                                                ) =>
                                                                                    i !==
                                                                                    p,
                                                                            ),
                                                                        },
                                                                    )
                                                                }
                                                                className="absolute -top-1.5 -right-1.5 rounded-full bg-red-600 p-0.5 text-white"
                                                            >
                                                                <X className="size-3" />
                                                            </button>
                                                        )}
                                                    </span>
                                                ))}
                                                {row.files.map((file, f) => (
                                                    <span
                                                        key={`${file.name}-${f}`}
                                                        className="relative"
                                                    >
                                                        <img
                                                            src={URL.createObjectURL(
                                                                file,
                                                            )}
                                                            alt={file.name}
                                                            className="size-10 rounded object-cover ring-2 ring-amber-400"
                                                        />
                                                        <button
                                                            type="button"
                                                            aria-label="Batalkan foto"
                                                            onClick={() =>
                                                                update(index, {
                                                                    files: row.files.filter(
                                                                        (
                                                                            _,
                                                                            i,
                                                                        ) =>
                                                                            i !==
                                                                            f,
                                                                    ),
                                                                })
                                                            }
                                                            className="absolute -top-1.5 -right-1.5 rounded-full bg-red-600 p-0.5 text-white"
                                                        >
                                                            <X className="size-3" />
                                                        </button>
                                                    </span>
                                                ))}
                                                {can_write &&
                                                    photoCount < MAX_PHOTOS && (
                                                        <label
                                                            className="flex size-10 cursor-pointer items-center justify-center rounded border border-dashed border-border text-muted-foreground hover:bg-secondary"
                                                            title="Tambah foto nota / barang"
                                                        >
                                                            <ImagePlus className="size-4" />
                                                            <input
                                                                type="file"
                                                                accept="image/*"
                                                                multiple
                                                                className="hidden"
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    const picked =
                                                                        Array.from(
                                                                            e
                                                                                .target
                                                                                .files ??
                                                                                [],
                                                                        ).slice(
                                                                            0,
                                                                            MAX_PHOTOS -
                                                                                photoCount,
                                                                        );
                                                                    update(
                                                                        index,
                                                                        {
                                                                            files: [
                                                                                ...row.files,
                                                                                ...picked,
                                                                            ],
                                                                        },
                                                                    );
                                                                    e.target.value =
                                                                        '';
                                                                }}
                                                            />
                                                        </label>
                                                    )}
                                            </div>
                                        </td>
                                        <td className="text-center">
                                            {row.id && !dirty ? (
                                                <a
                                                    href={buktiUrl(row.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-[12px] font-medium text-primary hover:underline"
                                                >
                                                    <Download className="size-3.5" />
                                                    Bukti
                                                </a>
                                            ) : (
                                                <span className="text-[11px] text-muted-foreground">
                                                    Simpan dulu
                                                </span>
                                            )}
                                        </td>
                                        {can_write && (
                                            <td className="text-center">
                                                <button
                                                    type="button"
                                                    aria-label={`Hapus baris ${index + 1}`}
                                                    onClick={() => {
                                                        setRows((current) =>
                                                            current.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        );
                                                        setDirty(true);
                                                    }}
                                                    className="p-1 text-red-600 hover:opacity-80"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                            <tr className="font-semibold">
                                <td
                                    colSpan={6}
                                    className="bg-[#92d050] px-2 py-1.5 text-right text-black"
                                >
                                    Total
                                </td>
                                <td className="bg-[#92d050] px-1.5 text-right text-black tabular-nums">
                                    {money(totalIn)}
                                </td>
                                <td className="bg-[#92d050] px-1.5 text-right text-black tabular-nums">
                                    {money(totalOut)}
                                </td>
                                <td className="bg-[#92d050] px-1.5 text-right text-black tabular-nums">
                                    {money(saldoAwal + totalIn - totalOut)}
                                </td>
                                <td
                                    colSpan={
                                        (buktiColumns ? 3 : 0) +
                                        2 +
                                        (can_write ? 1 : 0)
                                    }
                                    className="bg-[#92d050]"
                                />
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    {can_write ? (
                        <Button
                            variant="outline"
                            onClick={() => {
                                setRows((current) => [
                                    ...current,
                                    blankRow(current.length),
                                ]);
                                setDirty(true);
                            }}
                        >
                            <Plus className="size-4" /> Tambah Baris
                        </Button>
                    ) : (
                        <span />
                    )}
                    <table className="text-[13px] font-semibold">
                        <tbody>
                            {(
                                [
                                    ['Saldo Awal', saldoAwal],
                                    ['Penerimaan', totalIn],
                                    ['Pengeluaran', totalOut],
                                    [
                                        'Saldo Akhir',
                                        saldoAwal + totalIn - totalOut,
                                    ],
                                ] as const
                            ).map(([label, value]) => (
                                <tr key={label}>
                                    <td className="pr-3">{label}</td>
                                    <td className="pr-2">:</td>
                                    <td className="text-right tabular-nums">
                                        {money(value)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {suppliers.length === 0 && can_write && (
                    <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-[13px] text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                        Belum ada supplier di Master Supplier UL ini. Tambahkan
                        supplier agar nama, email, NPWP & foto KTP-nya otomatis
                        masuk ke lampiran bukti.
                    </p>
                )}
            </div>
        </>
    );
}

CashCardIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Realisasi Cash Card', href: cashCard.index() },
    ],
};
