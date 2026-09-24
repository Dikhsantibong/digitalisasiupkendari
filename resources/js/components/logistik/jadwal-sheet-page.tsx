import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, ImagePlus, Plus, Save, Trash2, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dailyProgress, downloadLogistikJadwalWorkbook, jadwalProgress, maturityLevel, patrolSummary, rekapAbsensi } from '@/lib/logistik-jadwal-excel';
import type { JadwalColumn, JadwalRow, JadwalSection, JadwalSheetDef } from '@/lib/logistik-jadwal-excel';
import { dashboard } from '@/routes';
import logistikInput from '@/routes/logistik/input';
import inputSheet from '@/routes/logistik/input/sheet';
import logistikJadwal from '@/routes/logistik/jadwal';
import jadwalSheet from '@/routes/logistik/jadwal/sheet';
import type { IdName } from '@/types';

type Filters = { unit_id: number; month: number; year: number };

export type LogistikJadwalSheetPageProps = {
    sheet: JadwalSheetDef;
    sections: JadwalSection[];
    codes: Record<string, string>;
    unit: IdName;
    filters: Filters;
    options: { units: IdName[]; years: number[] };
    columns: JadwalColumn[];
    rows: JadwalRow[];
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const th = 'border border-slate-400 p-1';
const td = 'border border-border';

/** Kegiatan & IK cells cycle empty → rencana → realisasi; patrol cells cycle empty → N → T. */
const NEXT_CODE: Record<string, Record<string, string>> = {
    rd: { '': 'R', R: 'D', D: '' },
    patrol: { '': 'N', N: 'T', T: '' },
};

/** PHP sends an empty `days` map as a JSON array. */
const normalize = (list: JadwalRow[]): JadwalRow[] => list.map((row) => ({ ...row, days: Array.isArray(row.days) ? {} : row.days }));

const blankRow = (section: string | null = null): JadwalRow => ({ section, nama: '', pic: '', days: {}, target: null, keterangan: '', evidence: [], evidence_urls: [] });

/**
 * One Logistik & Gudang grid sheet (App\Support\LogistikJadwal) — the jadwal
 * (Kegiatan, Shift Operator, Piket Patrol Check, 5S5R, Meeting, Inventarisasi,
 * Pembuatan IK) and the grid inputs (Patrol Checklist, Inspeksi 5S5R with
 * eviden photos, Input Data Aplikasi, Maturity Level) — with Simpan, PDF and Excel.
 */
export function LogistikJadwalSheetPage({ sheet, sections, codes, unit, filters, options, columns, rows: initialRows, has_saved, can_write }: LogistikJadwalSheetPageProps) {
    const [rows, setRows] = useState<JadwalRow[]>(() => normalize(initialRows));
    // New eviden photos per row index, uploaded on Simpan.
    const [uploads, setUploads] = useState<Record<number, File[]>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const routes = sheet.menu === 'input' ? inputSheet : jadwalSheet;
    const menuIndex = sheet.menu === 'input' ? logistikInput.index().url : logistikJadwal.index().url;
    const periodLabel = sheet.yearly ? `Tahun ${filters.year}` : `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };

    const visit = (patch: Partial<Filters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(routes.index(sheet.key).url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const updateRow = (index: number, patch: Partial<JadwalRow>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const setCode = (index: number, col: number, code: string) => {
        const days = { ...rows[index].days };

        if (code === '') {
            delete days[String(col)];
        } else {
            days[String(col)] = code;
        }

        updateRow(index, { days });
    };

    const code = (row: JadwalRow, col: number) => row.days[String(col)] ?? '';

    const addRow = (section: string | null = null) => {
        setRows((current) => {
            if (section === null) {
                return [...current, blankRow()];
            }

            const last = current.map((row) => row.section).lastIndexOf(section);
            const next = [...current];
            next.splice(last === -1 ? next.length : last + 1, 0, blankRow(section));

            return next;
        });
        setDirty(true);
    };

    const removeRow = (index: number) => {
        setRows((current) => current.filter((_, i) => i !== index));
        setUploads((current) => Object.fromEntries(Object.entries(current).filter(([key]) => Number(key) !== index).map(([key, files]) => [Number(key) > index ? Number(key) - 1 : Number(key), files])));
        setDirty(true);
    };

    const addPhotos = (index: number, files: FileList | null) => {
        const room = sheet.evidence - rows[index].evidence.length - (uploads[index]?.length ?? 0);
        const picked = Array.from(files ?? []).slice(0, Math.max(0, room));

        if (picked.length > 0) {
            setUploads((current) => ({ ...current, [index]: [...(current[index] ?? []), ...picked] }));
            setDirty(true);
        }
    };

    const removeKeptPhoto = (index: number, photo: number) =>
        updateRow(index, { evidence: rows[index].evidence.filter((_, i) => i !== photo), evidence_urls: rows[index].evidence_urls.filter((_, i) => i !== photo) });

    const removeNewPhoto = (index: number, photo: number) => {
        setUploads((current) => ({ ...current, [index]: (current[index] ?? []).filter((_, i) => i !== photo) }));
        setDirty(true);
    };

    const save = () => {
        const hasFiles = Object.values(uploads).some((files) => files.length > 0);
        const payload = rows.map(({ section, nama, pic, days, target, keterangan, evidence }, index) => ({
            section,
            nama,
            pic,
            days,
            target,
            keterangan,
            evidence,
            ...(hasFiles ? { evidence_files: uploads[index] ?? [] } : {}),
        }));

        setSaving(true);
        router.post(routes.store(sheet.key).url, { ...query, rows: payload }, {
            forceFormData: hasFiles,
            preserveScroll: true,
            onSuccess: (page) => {
                // Pick up the stored photo paths of the new uploads.
                setRows(normalize((page.props as unknown as LogistikJadwalSheetPageProps).rows));
                setDirty(false);
                setUploads({});
            },
            onFinish: () => setSaving(false),
        });
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadLogistikJadwalWorkbook(sheet, unit.name, periodLabel, columns, rows, sections, `${sheet.title.replace(/[^A-Za-z0-9]+/g, '_')}_${unit.name.replace(/\s+/g, '_')}_${sheet.yearly ? filters.year : `${filters.month}_${filters.year}`}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const dayHeaders = (withDow: boolean) => (
        <>
            {withDow && (
                <tr>
                    {columns.map((column) => (
                        <th key={column.col} className={`${th} w-7 text-[10px] ${column.is_red ? 'text-red-600' : ''}`}>{column.dow}</th>
                    ))}
                </tr>
            )}
            <tr>
                {columns.map((column) => (
                    <th key={column.col} className={`${th} w-7 text-[10px] ${column.is_red ? 'text-red-600' : ''}`}>{sheet.yearly ? column.label : column.col}</th>
                ))}
            </tr>
        </>
    );

    const toggleCell = (index: number, column: JadwalColumn, cycle: 'rd' | 'patrol', content: ReactNode, className: string) => (
        <td
            key={column.col}
            onClick={() => can_write && setCode(index, column.col, NEXT_CODE[cycle][code(rows[index], column.col)] ?? '')}
            className={`${td} h-7 w-7 cursor-pointer text-center text-[11px] font-semibold select-none ${className}`}
            title={`Tanggal ${column.col}`}
        >
            {content}
        </td>
    );

    /** Kegiatan & IK: one line per row; 1 = rencana, ✓ = realisasi. */
    const rdCell = (index: number, column: JadwalColumn) => {
        const value = code(rows[index], column.col);
        const tone = value === 'D' ? 'bg-emerald-200 dark:bg-emerald-900' : column.is_red ? 'bg-red-500/80 text-white' : value === 'R' ? 'bg-sky-100 dark:bg-sky-950' : 'hover:bg-muted';

        return toggleCell(index, column, 'rd', value === 'D' ? '✓' : value === 'R' ? '1' : '', tone);
    };

    const removeButton = (index: number) =>
        can_write && (
            <td className={`${td} p-0 text-center`}>
                <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                    <Trash2 className="size-3.5" />
                </Button>
            </td>
        );

    const namaInput = (index: number, placeholder = '') => (
        <Input value={rows[index].nama} onChange={(e) => updateRow(index, { nama: e.target.value })} className={cellInput} placeholder={placeholder} disabled={!can_write} />
    );

    const targetInput = (index: number, fallback: number) => (
        <Input
            type="number"
            min={0}
            value={rows[index].target ?? ''}
            placeholder={String(fallback)}
            onChange={(e) => updateRow(index, { target: e.target.value === '' ? null : Number(e.target.value) })}
            className={`${cellInput} text-center font-semibold`}
            disabled={!can_write}
        />
    );

    const renderKegiatan = () => (
        <table className="w-full min-w-[1400px] border-collapse text-xs">
            <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                <tr>
                    <th rowSpan={2} className={`${th} w-10`}>No</th>
                    <th rowSpan={2} className={`${th} min-w-52`}>{sheet.row_label}</th>
                    <th colSpan={columns.length} className={th}>{periodLabel.toUpperCase()}</th>
                    <th rowSpan={2} className={`${th} w-16`}>TARGET</th>
                    <th rowSpan={2} className={`${th} w-16`}>RENCANA</th>
                    <th rowSpan={2} className={`${th} w-16`}>REALISASI</th>
                    <th rowSpan={2} className={`${th} w-16`}>A. KINERJA</th>
                    <th rowSpan={2} className={`${th} min-w-36`}>Keterangan</th>
                    {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                </tr>
                {dayHeaders(false)}
            </thead>
            <tbody>
                {sections.map((section) => (
                    <Fragment key={section.key}>
                        <tr className="bg-[#70ad47] font-semibold text-slate-900 italic">
                            <td className={`${td} p-1 text-center`}>{section.number}</td>
                            <td colSpan={columns.length + 6} className={`${td} p-1`}>
                                <div className="flex items-center justify-between">
                                    {section.title}
                                    {can_write && (
                                        <Button variant="ghost" size="sm" className="h-6 gap-1 text-xs not-italic" onClick={() => addRow(section.key)}>
                                            <Plus className="size-3.5" />
                                            Tambah Kegiatan
                                        </Button>
                                    )}
                                </div>
                            </td>
                            {can_write && <td className={td} />}
                        </tr>
                        {rows.map((row, index) => {
                            if ((row.section ?? 'non-rutin') !== section.key) {
                                return null;
                            }

                            const number = rows.slice(0, index + 1).filter((r) => (r.section ?? 'non-rutin') === section.key).length;
                            const p = jadwalProgress(row);

                            return (
                                <tr key={index}>
                                    <td className={`${td} p-1 text-center`}>{number}</td>
                                    <td className={`${td} p-0`}>{namaInput(index)}</td>
                                    {columns.map((column) => rdCell(index, column))}
                                    <td className={`${td} p-0`}>{targetInput(index, p.rencana)}</td>
                                    <td className={`${td} p-1 text-center font-semibold`}>{p.rencana}</td>
                                    <td className={`${td} p-1 text-center font-semibold`}>{p.realisasi}</td>
                                    <td className={`${td} p-1 text-center font-semibold`}>{p.kinerja}</td>
                                    <td className={`${td} p-0`}>
                                        <Input value={row.keterangan} onChange={(e) => updateRow(index, { keterangan: e.target.value })} className={cellInput} disabled={!can_write} />
                                    </td>
                                    {removeButton(index)}
                                </tr>
                            );
                        })}
                    </Fragment>
                ))}
            </tbody>
        </table>
    );

    const renderPelaksana = () => (
        <table className="w-full min-w-[1400px] border-collapse text-xs">
            <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                <tr>
                    <th rowSpan={3} className={`${th} min-w-48`}>{sheet.row_label}</th>
                    <th rowSpan={3} className={`${th} w-24`}>RENCANA / REALISASI</th>
                    <th colSpan={columns.length} className={th}>BULAN {periodLabel.toUpperCase()}</th>
                    <th rowSpan={3} className={`${th} w-16`}>RENCANA</th>
                    <th rowSpan={3} className={`${th} w-16`}>TARGET</th>
                    <th rowSpan={3} className={`${th} w-16`}>REALISASI</th>
                    <th rowSpan={3} className={`${th} w-16`}>A. KINERJA</th>
                    {can_write && <th rowSpan={3} className={`${th} w-9`} />}
                </tr>
                {dayHeaders(true)}
            </thead>
            <tbody>
                {rows.map((row, index) => {
                    const p = jadwalProgress(row);

                    return (
                        <Fragment key={index}>
                            <tr>
                                <td rowSpan={2} className={`${td} p-0`}>{namaInput(index)}</td>
                                <td className={`${td} bg-sky-500 p-1 text-center text-[10px] font-bold text-white`}>RENCANA</td>
                                {columns.map((column) => {
                                    const value = code(row, column.col);
                                    const planned = value === 'R' || value === 'D';

                                    return (
                                        <td
                                            key={column.col}
                                            onClick={() => can_write && setCode(index, column.col, planned ? '' : 'R')}
                                            className={`${td} h-7 w-7 cursor-pointer text-center text-[11px] font-semibold select-none ${planned ? 'bg-sky-100 dark:bg-sky-950' : column.is_red ? 'bg-red-50 dark:bg-red-950/40' : 'hover:bg-muted'}`}
                                            title={`Rencana tanggal ${column.col}`}
                                        >
                                            {planned ? '1' : ''}
                                        </td>
                                    );
                                })}
                                <td rowSpan={2} className={`${td} p-1 text-center font-semibold`}>{p.rencana}</td>
                                <td rowSpan={2} className={`${td} p-0`}>{targetInput(index, p.rencana)}</td>
                                <td rowSpan={2} className={`${td} p-1 text-center font-semibold`}>{p.realisasi}</td>
                                <td rowSpan={2} className={`${td} p-1 text-center font-semibold`}>{p.kinerja}</td>
                                {can_write && (
                                    <td rowSpan={2} className={`${td} p-0 text-center`}>
                                        <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </td>
                                )}
                            </tr>
                            <tr>
                                <td className={`${td} bg-lime-500 p-1 text-center text-[10px] font-bold text-white`}>REALISASI</td>
                                {columns.map((column) => {
                                    const done = code(row, column.col) === 'D';

                                    return (
                                        <td
                                            key={column.col}
                                            onClick={() => can_write && setCode(index, column.col, done ? 'R' : 'D')}
                                            className={`${td} h-7 w-7 cursor-pointer text-center text-[11px] font-semibold select-none ${done ? 'bg-emerald-200 dark:bg-emerald-900' : column.is_red ? 'bg-red-50 dark:bg-red-950/40' : 'hover:bg-muted'}`}
                                            title={`Realisasi tanggal ${column.col}`}
                                        >
                                            {done ? '1' : ''}
                                        </td>
                                    );
                                })}
                            </tr>
                        </Fragment>
                    );
                })}
            </tbody>
        </table>
    );

    const renderShift = () => (
        <table className="w-full min-w-[1500px] border-collapse text-xs">
            <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                <tr>
                    <th rowSpan={3} className={`${th} w-10`}>NO</th>
                    <th rowSpan={3} className={`${th} min-w-40`}>PIC</th>
                    <th rowSpan={3} className={`${th} min-w-40`}>NAMA</th>
                    <th colSpan={columns.length} className={th}>BULAN {periodLabel.toUpperCase()}</th>
                    <th colSpan={5} className={th}>REKAP ABSENSI</th>
                    <th rowSpan={3} className={`${th} w-20`}>PERSENTASE KEHADIRAN</th>
                    {can_write && <th rowSpan={3} className={`${th} w-9`} />}
                </tr>
                <tr>
                    {columns.map((column) => (
                        <th key={column.col} className={`${th} w-12 text-[10px] ${column.is_holiday ? 'bg-red-600 text-white' : column.is_weekend ? 'bg-[#ffc000] text-red-700' : ''}`}>{column.dow}</th>
                    ))}
                    {['PAGI', 'SAKIT', 'IZIN', 'CUTI', 'MANKIR'].map((label) => (
                        <th key={label} rowSpan={2} className={`${th} w-12 text-[10px]`}>{label}</th>
                    ))}
                </tr>
                <tr>
                    {columns.map((column) => (
                        <th key={column.col} className={`${th} text-[10px] ${column.is_holiday ? 'bg-red-600 text-white' : column.is_weekend ? 'bg-[#ffc000] text-red-700' : ''}`}>{column.label}</th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row, index) => {
                    const rekap = rekapAbsensi(row);

                    return (
                        <tr key={index}>
                            <td className={`${td} p-1 text-center font-semibold`}>{index + 1}</td>
                            <td className={`${td} p-0`}>
                                <Input value={row.pic} onChange={(e) => updateRow(index, { pic: e.target.value })} className={cellInput} disabled={!can_write} />
                            </td>
                            <td className={`${td} p-0`}>{namaInput(index, 'Nama personil')}</td>
                            {columns.map((column) => {
                                const value = code(row, column.col);

                                return (
                                    <td key={column.col} className={`${td} p-0 ${column.is_holiday ? 'bg-red-500/80' : value === 'OF' ? 'bg-[#ffc000]/70' : ''}`}>
                                        <PdmCellSelect value={value} onChange={(next) => setCode(index, column.col, next)} options={Object.keys(codes)} className="justify-center font-semibold" disabled={!can_write} />
                                    </td>
                                );
                            })}
                            {(['P', 'S', 'I', 'C', 'M'] as const).map((key) => (
                                <td key={key} className={`${td} p-1 text-center font-semibold`}>{rekap[key]}</td>
                            ))}
                            <td className={`${td} bg-lime-500/80 p-1 text-center font-bold`}>{rekap.kehadiran}</td>
                            {removeButton(index)}
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );

    const renderIk = () => {
        const planned = rows.filter((row) => jadwalProgress(row).rencana > 0).length;
        const done = rows.filter((row) => jadwalProgress(row).realisasi > 0).length;

        return (
            <div className="flex flex-col gap-3">
                <table className="w-full min-w-[1100px] border-collapse text-xs">
                    <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                        <tr>
                            <th rowSpan={2} className={`${th} w-10`}>NO</th>
                            <th rowSpan={2} className={`${th} min-w-64`}>INSTRUKSI KERJA</th>
                            <th rowSpan={2} className={`${th} min-w-40`}>PIC PEMBUAT</th>
                            <th colSpan={columns.length} className={th}>BULAN — {periodLabel.toUpperCase()}</th>
                            <th rowSpan={2} className={`${th} w-16`}>JUMLAH</th>
                            {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                        </tr>
                        {dayHeaders(false)}
                    </thead>
                    <tbody>
                        <tr className="bg-[#70ad47] font-semibold text-slate-900">
                            <td className={`${td} p-1 text-center`}>A.</td>
                            <td colSpan={columns.length + 3 + (can_write ? 1 : 0)} className={`${td} p-1`}>PEMBUATAN INSTRUKSI KERJA</td>
                        </tr>
                        {rows.map((row, index) => (
                            <tr key={index}>
                                <td className={`${td} p-1 text-center`}>{index + 1}</td>
                                <td className={`${td} p-0`}>{namaInput(index, 'IK ....')}</td>
                                <td className={`${td} p-0`}>
                                    <Input value={row.pic} onChange={(e) => updateRow(index, { pic: e.target.value })} className={cellInput} disabled={!can_write} />
                                </td>
                                {columns.map((column) => rdCell(index, column))}
                                <td className={`${td} p-1 text-center font-semibold`}>{jadwalProgress(row).rencana}</td>
                                {removeButton(index)}
                            </tr>
                        ))}
                        <tr className="bg-muted/50 font-semibold">
                            <td colSpan={columns.length + 3} className={`${td} p-1 text-center`}>TOTAL IK</td>
                            <td className={`${td} p-1 text-center`}>{rows.reduce((sum, row) => sum + jadwalProgress(row).rencana, 0)}</td>
                            {can_write && <td className={td} />}
                        </tr>
                    </tbody>
                </table>
                <table className="w-80 border-collapse text-xs">
                    <thead className="bg-[#5bc8f5] text-[11px] font-semibold text-slate-900">
                        <tr>
                            <th className={th}>IK</th>
                            <th className={`${th} w-16`}>NILAI</th>
                            <th className={`${th} w-20`}>A. DATA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className={`${td} p-1`}>RENCANA</td>
                            <td className={`${td} p-1 text-center`}>{planned}</td>
                            <td rowSpan={2} className={`${td} p-1 text-center font-bold`}>{planned > 0 ? `${Math.round((done / planned) * 100)}%` : '0%'}</td>
                        </tr>
                        <tr>
                            <td className={`${td} p-1`}>REALISASI</td>
                            <td className={`${td} p-1 text-center`}>{done}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        );
    };

    const renderPatrol = () => {
        const summaries = rows.map((row) => patrolSummary(row, columns.length));
        const sum = (key: 'normal' | 'tidak_normal' | 'rencana' | 'realisasi') => summaries.reduce((total, s) => total + s[key], 0);

        return (
            <table className="w-full min-w-[1500px] border-collapse text-xs">
                <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                    <tr>
                        <th rowSpan={2} className={`${th} w-10`}>NO</th>
                        <th rowSpan={2} className={`${th} min-w-56`}>{sheet.row_label}</th>
                        <th colSpan={columns.length} className={th}>PATROL CHECKLIST LOGISTIK &amp; GUDANG — {periodLabel.toUpperCase()}</th>
                        <th colSpan={2} className={th}>HASIL TEMUAN</th>
                        <th colSpan={3} className={th}>PELAKSANAAN</th>
                        {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                    </tr>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.col} className={`${th} w-7 text-[10px]`}>{column.col}</th>
                        ))}
                        <th className={`${th} w-16 text-[10px]`}>NORMAL</th>
                        <th className={`${th} w-16 text-[10px]`}>T. NORMAL</th>
                        <th className={`${th} w-14 text-[10px]`}>RNC</th>
                        <th className={`${th} w-14 text-[10px]`}>REAL</th>
                        <th className={`${th} w-14 text-[10px]`}>HASIL</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index}>
                            <td className={`${td} p-1 text-center`}>{index + 1}</td>
                            <td className={`${td} p-0`}>{namaInput(index)}</td>
                            {columns.map((column) => {
                                const value = code(row, column.col);
                                const tone = value === 'N' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : value === 'T' ? 'bg-rose-200 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'hover:bg-muted';

                                return toggleCell(index, column, 'patrol', value, tone);
                            })}
                            <td className={`${td} p-1 text-center font-semibold`}>{summaries[index].normal}</td>
                            <td className={`${td} p-1 text-center font-semibold`}>{summaries[index].tidak_normal}</td>
                            <td className={`${td} p-0`}>{targetInput(index, columns.length)}</td>
                            <td className={`${td} p-1 text-center font-semibold`}>{summaries[index].realisasi}</td>
                            <td className={`${td} p-1 text-center font-semibold`}>{summaries[index].hasil}</td>
                            {removeButton(index)}
                        </tr>
                    ))}
                    <tr className="bg-muted/50 font-semibold">
                        <td colSpan={columns.length + 2} className={`${td} p-1 text-right`}>TOTAL</td>
                        <td className={`${td} p-1 text-center`}>{sum('normal')}</td>
                        <td className={`${td} p-1 text-center`}>{sum('tidak_normal')}</td>
                        <td className={`${td} p-1 text-center`}>{sum('rencana')}</td>
                        <td className={`${td} p-1 text-center`}>{sum('realisasi')}</td>
                        <td className={`${td} p-1 text-center`}>{sum('rencana') > 0 ? `${Math.round((sum('realisasi') / sum('rencana')) * 100)}%` : '-'}</td>
                        {can_write && <td className={td} />}
                    </tr>
                </tbody>
            </table>
        );
    };

    const renderAplikasi = () => (
        <table className="w-full min-w-[1300px] border-collapse text-xs">
            <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                <tr>
                    <th rowSpan={2} className={`${th} w-10`}>No</th>
                    <th rowSpan={2} className={`${th} min-w-44`}>{sheet.row_label}</th>
                    <th colSpan={columns.length} className={th}>{periodLabel.toUpperCase()}</th>
                    <th rowSpan={2} className={`${th} w-16`}>TARGET</th>
                    <th rowSpan={2} className={`${th} w-16`}>REALISASI</th>
                    <th rowSpan={2} className={`${th} w-16`}>A. KINERJA</th>
                    {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                </tr>
                {dayHeaders(false)}
            </thead>
            <tbody>
                {rows.map((row, index) => {
                    const p = dailyProgress(row, columns.length);

                    return (
                        <tr key={index}>
                            <td className={`${td} p-1 text-center`}>{index + 1}</td>
                            <td className={`${td} p-0`}>{namaInput(index, 'Nama aplikasi')}</td>
                            {columns.map((column) => doneCell(index, column))}
                            <td className={`${td} p-0`}>{targetInput(index, columns.length)}</td>
                            <td className={`${td} p-1 text-center font-semibold`}>{p.realisasi}</td>
                            <td className={`${td} p-1 text-center font-semibold`}>{p.kinerja}</td>
                            {removeButton(index)}
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );

    /** A 1/empty toggle cell (aplikasi & checklist). */
    const doneCell = (index: number, column: JadwalColumn) => {
        const done = code(rows[index], column.col) === 'D';

        return (
            <td
                key={column.col}
                onClick={() => can_write && setCode(index, column.col, done ? '' : 'D')}
                className={`${td} h-7 w-7 cursor-pointer text-center text-[11px] font-semibold select-none ${done ? 'bg-emerald-200 dark:bg-emerald-900' : column.is_red ? 'bg-red-50 dark:bg-red-950/40' : 'hover:bg-muted'}`}
                title={`Kolom ${column.label}`}
            >
                {done ? '1' : ''}
            </td>
        );
    };

    const evidenceCell = (index: number) => {
        const row = rows[index];
        const pending = uploads[index] ?? [];
        const room = sheet.evidence - row.evidence.length - pending.length;

        return (
            <td className={`${td} p-1`}>
                <div className="flex flex-wrap items-center gap-1">
                    {row.evidence_urls.map((url, photo) => (
                        <span key={url} className="relative">
                            <img src={url} alt="Eviden" className="size-12 rounded object-cover" />
                            {can_write && (
                                <button type="button" onClick={() => removeKeptPhoto(index, photo)} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Hapus foto">
                                    <X className="size-3" />
                                </button>
                            )}
                        </span>
                    ))}
                    {pending.map((file, photo) => (
                        <span key={`${file.name}-${photo}`} className="relative">
                            <img src={URL.createObjectURL(file)} alt={file.name} className="size-12 rounded object-cover opacity-80 ring-2 ring-amber-400" />
                            <button type="button" onClick={() => removeNewPhoto(index, photo)} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Batalkan foto">
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                    {can_write && room > 0 && (
                        <label className="flex size-12 cursor-pointer items-center justify-center rounded border border-dashed border-border text-muted-foreground hover:bg-muted" title="Tambah foto eviden">
                            <ImagePlus className="size-4" />
                            <input type="file" accept="image/*" multiple className="hidden" onChange={(e) => addPhotos(index, e.target.files)} />
                        </label>
                    )}
                </div>
            </td>
        );
    };

    const renderChecklist = () => {
        const totals = sections.map((section) => {
            const list = rows.filter((row) => row.section === section.key).map((row) => dailyProgress(row, columns.length));

            return { section, rencana: Math.max(0, ...list.map((p) => p.target)), realisasi: list.reduce((sum, p) => sum + p.realisasi, 0) };
        });
        const pct = (done: number, of: number) => (of > 0 ? `${Math.round((done / of) * 100)}%` : '0%');

        return (
            <div className="flex flex-col gap-4 p-3">
                <table className="w-96 border-collapse text-xs">
                    <thead className="bg-[#5bc8f5] text-[11px] font-semibold text-slate-900">
                        <tr>
                            <th className={`${th} w-10`}>NO</th>
                            <th className={th}>A. IDENTITAS</th>
                            <th className={th}>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td className={`${td} p-1 text-center`}>1</td>
                            <td className={`${td} p-1`}>Nama Pembangkit</td>
                            <td className={`${td} p-1 text-center`}>{unit.name.toUpperCase()}</td>
                        </tr>
                        <tr>
                            <td className={`${td} p-1 text-center`}>2</td>
                            <td className={`${td} p-1`}>Bulan</td>
                            <td className={`${td} p-1 text-center`}>{OPERASI_MONTHS[filters.month - 1].toUpperCase()}</td>
                        </tr>
                    </tbody>
                </table>

                {sections.map((section) => (
                    <div key={section.key} className="flex flex-col gap-1">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-semibold">{section.number}. {section.title}</p>
                                <p className="text-[12px] text-muted-foreground">Tujuan: {section.note}</p>
                            </div>
                            {can_write && (
                                <Button variant="outline" size="sm" className="h-7 gap-1 text-xs" onClick={() => addRow(section.key)}>
                                    <Plus className="size-3.5" />
                                    Tambah Item
                                </Button>
                            )}
                        </div>
                        <table className="w-full min-w-[1300px] border-collapse text-xs">
                            <thead className="bg-[#5bc8f5] text-center text-[11px] font-semibold text-slate-900">
                                <tr>
                                    <th rowSpan={2} className={`${th} w-10`}>No</th>
                                    <th rowSpan={2} className={`${th} min-w-56`}>{sheet.row_label}</th>
                                    <th colSpan={columns.length} className={th}>TANGGAL PELAKSANAAN</th>
                                    <th rowSpan={2} className={`${th} w-16`}>RENCANA</th>
                                    <th rowSpan={2} className={`${th} w-16`}>REALISASI</th>
                                    <th rowSpan={2} className={`${th} w-16`}>A. DATA</th>
                                    <th rowSpan={2} className={`${th} min-w-52`}>EVIDEN (maks. {sheet.evidence} foto)</th>
                                    {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                                </tr>
                                {dayHeaders(false)}
                            </thead>
                            <tbody>
                                {rows.map((row, index) => {
                                    if (row.section !== section.key) {
                                        return null;
                                    }

                                    const p = dailyProgress(row, columns.length);
                                    const number = rows.slice(0, index + 1).filter((r) => r.section === section.key).length;

                                    return (
                                        <tr key={index}>
                                            <td className={`${td} p-1 text-center`}>{number}</td>
                                            <td className={`${td} p-0`}>{namaInput(index)}</td>
                                            {columns.map((column) => doneCell(index, column))}
                                            <td className={`${td} p-0`}>{targetInput(index, columns.length)}</td>
                                            <td className={`${td} p-1 text-center font-semibold`}>{p.realisasi}</td>
                                            <td className={`${td} p-1 text-center font-semibold`}>{p.kinerja}</td>
                                            {evidenceCell(index)}
                                            {removeButton(index)}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ))}

                <table className="w-[28rem] border-collapse text-xs">
                    <thead className="bg-[#5bc8f5] text-[11px] font-semibold text-slate-900">
                        <tr>
                            <th className={th}>AKUMULATIF</th>
                            <th className={`${th} w-20`}>RENCANA</th>
                            <th className={`${th} w-20`}>REALISASI</th>
                            <th className={`${th} w-20`}>A. KINERJA</th>
                        </tr>
                    </thead>
                    <tbody>
                        {totals.map(({ section, rencana, realisasi }) => (
                            <tr key={section.key}>
                                <td className={`${td} p-1`}>{section.title.split(' ')[0]}</td>
                                <td className={`${td} p-1 text-center`}>{rencana}</td>
                                <td className={`${td} p-1 text-center`}>{realisasi}</td>
                                <td className={`${td} p-1 text-center`}>{pct(realisasi, rencana)}</td>
                            </tr>
                        ))}
                        <tr className="font-semibold">
                            <td className={`${td} p-1`}>TOTAL</td>
                            <td className={`${td} p-1 text-center`}>{totals.reduce((sum, t) => sum + t.rencana, 0)}</td>
                            <td className={`${td} p-1 text-center`}>{totals.reduce((sum, t) => sum + t.realisasi, 0)}</td>
                            <td className={`${td} p-1 text-center`}>{pct(totals.reduce((sum, t) => sum + t.realisasi, 0), totals.reduce((sum, t) => sum + t.rencana, 0))}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        );
    };

    const renderMaturity = () => (
        <table className="w-full min-w-[760px] border-collapse text-sm">
            <thead className="bg-[#5bc8f5] text-center text-[12px] font-semibold text-slate-900">
                <tr>
                    <th className={`${th} w-16`}>NO</th>
                    <th className={th}>{sheet.row_label}</th>
                    <th className={`${th} w-64`}>LEVEL</th>
                    {can_write && <th className={`${th} w-9`} />}
                </tr>
            </thead>
            <tbody>
                <tr className="font-semibold italic">
                    <td className={`${td} p-1 text-center`}>D</td>
                    <td colSpan={2 + (can_write ? 1 : 0)} className={`${td} p-1`}>LOGISTIK</td>
                </tr>
                {sections.map((section) => (
                    <Fragment key={section.key}>
                        <tr className="bg-muted/50 font-semibold italic">
                            <td className={`${td} p-1 text-center`}>{section.number}</td>
                            <td colSpan={2 + (can_write ? 1 : 0)} className={`${td} p-1`}>
                                <div className="flex items-center justify-between">
                                    {section.title}
                                    {can_write && (
                                        <Button variant="ghost" size="sm" className="h-6 gap-1 text-xs not-italic" onClick={() => addRow(section.key)}>
                                            <Plus className="size-3.5" />
                                            Tambah Item
                                        </Button>
                                    )}
                                </div>
                            </td>
                        </tr>
                        {rows.map((row, index) => {
                            if (row.section !== section.key) {
                                return null;
                            }

                            const level = maturityLevel(row);
                            const number = rows.slice(0, index + 1).filter((r) => r.section === section.key).length;

                            return (
                                <tr key={index}>
                                    <td className={`${td} p-1 text-center`}>{number}</td>
                                    <td className={`${td} p-0`}>{namaInput(index)}</td>
                                    <td className={`${td} p-1`}>
                                        <div className="flex justify-center">
                                            {columns.map((column) => (
                                                <button
                                                    key={column.col}
                                                    type="button"
                                                    disabled={!can_write}
                                                    onClick={() => updateRow(index, { days: level === column.col ? {} : { [String(column.col)]: 'L' } })}
                                                    className={`size-8 border border-slate-500 text-sm font-semibold first:rounded-l last:rounded-r ${level === column.col ? 'bg-[#ffd966] text-slate-900' : 'hover:bg-muted'}`}
                                                    aria-label={`Level ${column.col}`}
                                                >
                                                    {column.col}
                                                </button>
                                            ))}
                                        </div>
                                    </td>
                                    {removeButton(index)}
                                </tr>
                            );
                        })}
                    </Fragment>
                ))}
            </tbody>
        </table>
    );

    const legend: Record<JadwalSheetDef['layout'], string> = {
        kegiatan: 'Klik sel tanggal: kosong → 1 (rencana) → ✓ (realisasi). Target kosong = jumlah rencana.',
        pelaksana: 'Klik sel baris RENCANA untuk menandai rencana, baris REALISASI untuk menandai terlaksana. Target kosong = jumlah rencana.',
        shift: `Pilih kode per tanggal: ${Object.entries(codes).map(([key, label]) => `${key} = ${label}`).join(' · ')}.`,
        ik: 'Klik sel bulan: kosong → 1 (rencana) → ✓ (realisasi). Rencana = IK yang dijadwalkan, Realisasi = IK yang selesai dibuat.',
        patrol: `Klik sel tanggal: kosong → N (${codes.N ?? 'normal'}) → T (${codes.T ?? 'tidak normal'}). RNC kosong = jumlah hari dalam bulan.`,
        aplikasi: 'Klik sel tanggal untuk menandai data sudah diinput (1). Target kosong = jumlah hari dalam bulan.',
        checklist: `Klik sel pelaksanaan untuk menandai kegiatan dilaksanakan (1), lampirkan hingga ${sheet.evidence} foto eviden per item. Rencana kosong = ${columns.length}.`,
        maturity: 'Pilih level 0–5 untuk setiap item (klik lagi untuk mengosongkan).',
    };

    return (
        <>
            <Head title={sheet.title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={sheet.title}
                    description={`${sheet.description} — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(menuIndex, query)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(routes.pdf(sheet.key, { query }).url, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className="w-56"
                    />
                    {!sheet.yearly && (
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            onChange={(value) => visit({ month: Number(value) })}
                            options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                        />
                    )}
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                        className="w-28"
                    />
                    {dirty && <span className="pb-2 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                </div>

                <p className="text-[13px] text-muted-foreground">
                    {!has_saved && 'Belum ada data tersimpan untuk periode ini — isian bawaan sudah disiapkan. '}
                    {legend[sheet.layout]}
                </p>

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    {sheet.layout === 'kegiatan' && renderKegiatan()}
                    {sheet.layout === 'pelaksana' && renderPelaksana()}
                    {sheet.layout === 'shift' && renderShift()}
                    {sheet.layout === 'ik' && renderIk()}
                    {sheet.layout === 'patrol' && renderPatrol()}
                    {sheet.layout === 'aplikasi' && renderAplikasi()}
                    {sheet.layout === 'checklist' && renderChecklist()}
                    {sheet.layout === 'maturity' && renderMaturity()}
                </div>

                {can_write && !['kegiatan', 'checklist', 'maturity'].includes(sheet.layout) && (
                    <div>
                        <Button variant="outline" size="sm" onClick={() => addRow()} className="gap-1.5">
                            <Plus className="size-4" />
                            Tambah Baris
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Breadcrumbs of a Logistik sheet page: jadwal sheets live in
 * pages/logistik/jadwal/{sheet}/index.tsx, input sheets in
 * pages/logistik/input/{sheet}/index.tsx.
 */
export function logistikSheetBreadcrumbs(menu: 'jadwal' | 'input', sheetKey: string, title: string) {
    return [
        { title: 'Dashboard', href: dashboard() },
        menu === 'input'
            ? { title: 'Input Logistik & Gudang', href: logistikInput.index() }
            : { title: 'Jadwal Logistik & Gudang', href: logistikJadwal.index() },
        { title, href: menu === 'input' ? inputSheet.index(sheetKey) : jadwalSheet.index(sheetKey) },
    ];
}
