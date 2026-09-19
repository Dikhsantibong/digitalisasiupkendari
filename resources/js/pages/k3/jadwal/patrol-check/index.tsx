import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CheckCheck,
    Download,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import jadwal from '@/routes/k3/jadwal';
import patrolCheck from '@/routes/k3/jadwal/patrol-check';
import type { IdName } from '@/types';

type DayInfo = { day: number; dow: string; is_red: boolean; holiday?: string | null };

type ServerRow = {
    id: number | null;
    no_urut: number | null;
    uraian: string;
    rencana: number[];
    realisasi: number[];
    bobot_sla: number;
    keterangan: string;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string; service_unit_id: number | null; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    days: DayInfo[];
    rows: ServerRow[];
    can_write: boolean;
};

type Category = 'rencana' | 'realisasi';

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; }
    .no-print { display: none !important; }
    .print-table { width: 100% !important; border-collapse: collapse !important; font-size: 7px !important; }
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 1px !important; text-align: center !important; vertical-align: middle !important; }
    .print-thead th { background-color: #fff !important; color: #000 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-text { color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-cell { background-color: #ff0000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-green-cell { background-color: #22c55e !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-yellow-cell { background-color: #fde047 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

export default function K3PatrolCheckPage({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [isAddOpen, setIsAddOpen] = useState(false);
    const [form, setForm] = useState({ uraian: '', bobot_sla: 0 });

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
        setDirty(false);
    }

    const monthName = useMemo(() => OPERASI_MONTHS[filters.month - 1] ?? '', [filters.month]);
    const workingDaysList = useMemo(() => days.filter((d) => !d.is_red).map((d) => d.day), [days]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(patrolCheck.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleCell = (key: number, category: Category, day: number) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== key) return r;
                const set = new Set(r[category] || []);
                if (set.has(day)) {
                    set.delete(day);
                } else {
                    set.add(day);
                }
                return { ...r, [category]: Array.from(set).sort((a, b) => a - b) };
            }),
        );
        setDirty(true);
    };

    const updateField = (key: number, field: 'uraian' | 'keterangan' | 'bobot_sla', value: string | number) => {
        if (!can_write) return;
        setRows((prev) => prev.map((r) => (r._key === key ? { ...r, [field]: value } : r)));
        setDirty(true);
    };

    const handleMarkPlanWorkingDays = () => {
        if (!can_write) return;
        setRows((prev) => prev.map((r) => ({ ...r, rencana: [...workingDaysList] })));
        setDirty(true);
    };

    const handleAdd = () => {
        if (!form.uraian.trim()) return;
        setRows((prev) => [
            ...prev,
            {
                _key: nextKey,
                id: null,
                no_urut: null,
                uraian: form.uraian.trim(),
                rencana: [],
                realisasi: [],
                bobot_sla: Number(form.bobot_sla) || 0,
                keterangan: '',
            },
        ]);
        setNextKey((k) => k + 1);
        setDirty(true);
        setIsAddOpen(false);
        setForm({ uraian: '', bobot_sla: 0 });
    };

    const handleRemove = (key: number) => {
        if (!can_write) return;
        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
    };

    const handleReset = () => {
        setRows(hydrate(initialRows));
        setDirty(false);
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            patrolCheck.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    uraian: r.uraian,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    bobot_sla: r.bobot_sla,
                    keterangan: r.keterangan,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Overall weighted SLA = Σ(bobot × persentase) ÷ Σ bobot.
    const overallSla = useMemo(() => {
        const totalBobot = rows.reduce((s, r) => s + (Number(r.bobot_sla) || 0), 0);
        if (totalBobot <= 0) return 0;
        const weighted = rows.reduce((s, r) => {
            const target = r.rencana.length;
            const perf = target > 0 ? (r.realisasi.length / target) * 100 : 0;
            return s + (Number(r.bobot_sla) || 0) * perf;
        }, 0);
        return Math.round(weighted / totalBobot);
    }, [rows]);

    const dayCells = (row: Row, category: Category) =>
        days.map((d) => {
            if (d.is_red) {
                return <td key={`${category}-${d.day}`} className="print-red-cell border border-black bg-[#ff0000] select-none" title={`${d.day} (${d.dow}): Libur / Akhir Pekan`} />;
            }
            const planned = row.rencana.includes(d.day);
            const realized = row.realisasi.includes(d.day);
            const on = category === 'rencana' ? planned : realized;
            let color = '';
            if (on) {
                if (category === 'realisasi') {
                    color = 'bg-emerald-400 text-black print-green-cell';
                } else {
                    color = realized ? 'bg-emerald-400 text-black print-green-cell' : 'bg-yellow-300 text-black print-yellow-cell';
                }
            }
            return (
                <td
                    key={`${category}-${d.day}`}
                    onClick={() => toggleCell(row._key, category, d.day)}
                    className={`border border-black text-center text-[11px] font-bold transition-colors select-none ${color || 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}
                    title={`Tanggal ${d.day} (${d.dow}) — ${category === 'rencana' ? 'Rencana' : 'Realisasi'}: klik untuk ${on ? 'batalkan' : 'tandai (1)'}`}
                >
                    {on ? '1' : ''}
                </td>
            );
        });

    return (
        <>
            <Head title={`Jadwal Patrol Check Harian K3L — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" onClick={() => router.get(jadwal.index().url)} title="Kembali ke Daftar Jadwal">
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">Jadwal Patrol Check Harian K3L &amp; Lingkungan</h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">{unit.name}</Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">Klik kotak tanggal pada baris RENC / REAL — otomatis terisi 1. Target = jumlah rencana, persentase = realisasi ÷ target.</p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button variant="outline" size="sm" onClick={handleReset} disabled={saving} className="gap-1.5 text-xs text-muted-foreground">
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <>
                                <Button variant="outline" size="sm" onClick={handleMarkPlanWorkingDays} className="gap-1.5 text-xs" title="Isi rencana untuk seluruh hari kerja">
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Rencana Hari Kerja
                                </Button>
                                <Button variant="outline" size="sm" onClick={() => setIsAddOpen(true)} className="gap-1.5 text-xs">
                                    <Plus className="size-3.5" />
                                    Tambah Pekerjaan
                                </Button>
                                <Button size="sm" onClick={handleSave} disabled={saving || !dirty} className="gap-1.5 text-xs">
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </>
                        )}
                        <a href={`${patrolCheck.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`} target="_blank" rel="noreferrer">
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs" title="Unduh format PDF resmi">
                                <Download className="size-3.5 text-rose-600" />
                                PDF
                            </Button>
                        </a>
                        <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 text-xs" title="Cetak Lanskap">
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect label="Unit Pembangkit" value={String(filters.unit_id)} onChange={(value) => visit({ unit_id: Number(value) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(value) => visit({ month: Number(value) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(value) => visit({ year: Number(value) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    <div className="flex items-center gap-1.5 pb-1 text-xs font-semibold text-foreground">
                        <span className="text-muted-foreground">Bobot SLA Total:</span>
                        <span className={overallSla >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}>{overallSla}%</span>
                    </div>
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Printable card */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase">JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</div>
                                <div className="text-xs font-bold text-foreground uppercase">PLN NP UP KENDARI - {unit.name.toUpperCase()}</div>
                                <div className="mt-0.5 text-[11px] font-semibold text-foreground uppercase">LAPORAN PROJECT</div>
                                <div className="text-[11px] font-bold text-foreground uppercase">PATROL CHECK HARIAN K3 DAN LINGKUNGAN BULAN {monthName.toUpperCase()} {filters.year}</div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-xs">
                            <thead className="print-thead bg-muted/60 dark:bg-muted/30">
                                <tr>
                                    <th rowSpan={2} className="w-8 border border-black p-1 text-center font-bold text-foreground">No</th>
                                    <th rowSpan={2} className="w-64 min-w-56 border border-black p-1 text-left font-bold text-foreground">Uraian Pekerjaan</th>
                                    <th rowSpan={2} className="w-12 border border-black p-1 text-center font-bold text-foreground">Status</th>
                                    <th colSpan={days.length} className="border border-black p-1 text-center font-bold tracking-wider text-foreground uppercase">Tanggal</th>
                                    <th rowSpan={2} className="w-12 border border-black p-1 text-center font-bold text-foreground">TARGET</th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">REALISASI</th>
                                    <th rowSpan={2} className="w-16 border border-black p-1 text-center font-bold text-foreground">PERSENTASE</th>
                                    <th rowSpan={2} className="w-36 min-w-28 border border-black p-1 text-left font-bold text-foreground">KETERANGAN</th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">BOBOT SLA</th>
                                    {can_write && <th rowSpan={2} className="no-print w-9 border border-black p-1 text-center font-bold text-foreground">Aksi</th>}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th key={d.day} className={`w-6 border border-black p-0.5 text-center text-[10px] font-bold ${d.is_red ? 'print-red-text text-red-600' : 'text-foreground'}`} title={`${d.day} (${d.dow})${d.holiday ? ` - ${d.holiday}` : ''}`}>
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, idx) => {
                                    const target = row.rencana.length;
                                    const realisasi = row.realisasi.length;
                                    const persentase = target > 0 ? Math.round((realisasi / target) * 100) : 0;

                                    return (
                                        <Fragment key={row._key}>
                                            <tr className="transition-colors hover:bg-muted/10">
                                                <td rowSpan={2} className="border border-black p-1 text-center font-medium text-foreground">{idx + 1}</td>
                                                <td rowSpan={2} className="border border-black p-1 text-left align-top text-foreground">
                                                    {can_write ? (
                                                        <input type="text" value={row.uraian} onChange={(e) => updateField(row._key, 'uraian', e.target.value)} className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" />
                                                    ) : (
                                                        <span className="px-1 text-xs">{row.uraian}</span>
                                                    )}
                                                </td>
                                                <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">RENC</td>
                                                {dayCells(row, 'rencana')}
                                                <td rowSpan={2} className="border border-black p-1 text-center align-middle font-bold text-foreground">{target}</td>
                                                <td rowSpan={2} className="border border-black p-1 text-center align-middle font-extrabold text-foreground">{realisasi}</td>
                                                <td rowSpan={2} className={`border border-black p-1 text-center align-middle font-extrabold ${persentase >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{persentase}%</td>
                                                <td rowSpan={2} className="border border-black p-1 text-left align-top">
                                                    {can_write ? (
                                                        <input type="text" value={row.keterangan || ''} onChange={(e) => updateField(row._key, 'keterangan', e.target.value)} placeholder="Catatan…" className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" />
                                                    ) : (
                                                        <span className="px-1 text-xs">{row.keterangan || '-'}</span>
                                                    )}
                                                </td>
                                                <td rowSpan={2} className="border border-black p-1 text-center align-middle">
                                                    {can_write ? (
                                                        <input type="number" min="0" step="any" value={row.bobot_sla || ''} onChange={(e) => updateField(row._key, 'bobot_sla', Number(e.target.value))} placeholder="0" className="w-12 bg-transparent text-center text-xs font-bold focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none" />
                                                    ) : (
                                                        <span className="text-xs font-bold">{row.bobot_sla || 0}</span>
                                                    )}
                                                </td>
                                                {can_write && (
                                                    <td rowSpan={2} className="no-print border border-black p-1 text-center align-middle">
                                                        <Button variant="ghost" size="icon" className="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" onClick={() => handleRemove(row._key)} title="Hapus Pekerjaan">
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                            <tr className="transition-colors hover:bg-muted/10">
                                                <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">REAL</td>
                                                {dayCells(row, 'realisasi')}
                                            </tr>
                                        </Fragment>
                                    );
                                })}
                                {/* Overall SLA */}
                                <tr className="bg-muted/50 font-bold">
                                    <td colSpan={3 + days.length + 3} className="border border-black p-1 text-right text-foreground uppercase">Bobot SLA Keseluruhan</td>
                                    <td className="border border-black p-1 text-center text-foreground" />
                                    <td className={`border border-black p-1 text-center font-extrabold ${overallSla >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{overallSla}%</td>
                                    {can_write && <td className="no-print border border-black" />}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Legend */}
                    <div className="border-t border-border bg-muted/20 p-4">
                        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-emerald-700 bg-emerald-400" />
                                <span>Terealisasi</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-yellow-500 bg-yellow-300" />
                                <span>Tidak Terealisasi (rencana belum terealisasi)</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-red-700 bg-[#ff0000]" />
                                <span>Hari Libur / Akhir Pekan</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>Bobot SLA keseluruhan = rata-rata persentase tertimbang bobot tiap pekerjaan.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Add dialog */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Activity className="size-5 text-primary" />
                            Tambah Pekerjaan Patrol Check
                        </DialogTitle>
                        <DialogDescription>Tambahkan uraian pekerjaan patrol check baru untuk unit {unit.name}.</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label htmlFor="pc-uraian">Uraian Pekerjaan</Label>
                            <Input id="pc-uraian" placeholder="Contoh: Periksa kesiapan pompa oil trap" value={form.uraian} onChange={(e) => setForm((f) => ({ ...f, uraian: e.target.value }))} autoFocus />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="pc-bobot">Bobot SLA</Label>
                            <Input id="pc-bobot" type="number" min="0" step="any" value={form.bobot_sla} onChange={(e) => setForm((f) => ({ ...f, bobot_sla: Number(e.target.value) }))} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAddOpen(false)}>Batal</Button>
                        <Button onClick={handleAdd} disabled={!form.uraian.trim()}>Tambahkan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

K3PatrolCheckPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3 & Lingkungan', href: jadwal.index() },
        { title: 'Patrol Check Harian K3L', href: patrolCheck.index() },
    ],
};
