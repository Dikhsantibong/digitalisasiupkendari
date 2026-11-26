import { Head, router } from '@inertiajs/react';
import { Download, FileWarning, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { entryDate, MobileEntryCard, MobileEntryList, MobileFrame } from '@/components/mobile/entry-flow';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import laporanGangguanRoutes from '@/routes/har/formulir/laporan-gangguan';
import type { IdName } from '@/types';

type MachineOption = {
    id: number;
    name: string;
    merek: string;
    type: string;
    no_seri: string;
    fungsi_pembangkit: string;
    daya_terpasang: string;
    daya_mampu: string;
};

type Report = {
    id: number;
    nomor: string | null;
    tanggal_laporan: string | null;
    hal: string | null;
    form_code: string | null;
    unit_kesatuan: string | null;
    machine_id: number | null;
    merek: string | null;
    type: string | null;
    no_seri: string | null;
    rh: string | null;
    jsb: string | null;
    jsmo: string | null;
    jsi_terakhir: string | null;
    fungsi_pembangkit: string | null;
    daya_terpasang: string | null;
    daya_mampu: string | null;
    tanggal_jam_kerusakan: string | null;
    peralatan_rusak: string | null;
    gejala: string | null;
    urutan_kejadian: string | null;
    parameter_terkait: string | null;
    analisa_penyebab: string | null;
    akibat: string | null;
    tindak_lanjut_pendek: string | null;
    tindak_lanjut_panjang: string | null;
    eviden: string | null;
};

type FormState = {
    id: number | null;
    nomor: string;
    tanggal_laporan: string;
    hal: string;
    form_code: string;
    unit_kesatuan: string;
    machine_id: string;
    merek: string;
    type: string;
    no_seri: string;
    rh: string;
    jsb: string;
    jsmo: string;
    jsi_terakhir: string;
    fungsi_pembangkit: string;
    daya_terpasang: string;
    daya_mampu: string;
    tanggal_jam_kerusakan: string;
    peralatan_rusak: string;
    gejala: string;
    urutan_kejadian: string;
    parameter_terkait: string;
    analisa_penyebab: string;
    akibat: string;
    tindak_lanjut_pendek: string;
    tindak_lanjut_panjang: string;
    eviden: string;
};

type StringField = Exclude<keyof FormState, 'id'>;

type Props = {
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: { unit_id: number; year: number };
    reports: Report[];
    options: {
        units: IdName[];
        years: number[];
        machines: MachineOption[];
    };
    can_write: boolean;
};

const emptyForm = (
    defaultMachine?: MachineOption,
    unitName = '',
): FormState => ({
    id: null,
    nomor: '',
    tanggal_laporan: '',
    hal: '',
    form_code: 'LH - 05',
    unit_kesatuan: unitName,
    machine_id: defaultMachine ? String(defaultMachine.id) : '',
    merek: defaultMachine?.merek ?? '',
    type: defaultMachine?.type ?? '',
    no_seri: defaultMachine?.no_seri ?? '',
    rh: '',
    jsb: '',
    jsmo: '',
    jsi_terakhir: '',
    fungsi_pembangkit: defaultMachine?.fungsi_pembangkit || unitName,
    daya_terpasang: defaultMachine?.daya_terpasang ?? '',
    daya_mampu: defaultMachine?.daya_mampu ?? '',
    tanggal_jam_kerusakan: '',
    peralatan_rusak: '',
    gejala: '',
    urutan_kejadian: '',
    parameter_terkait: '',
    analisa_penyebab: '',
    akibat: '',
    tindak_lanjut_pendek: '',
    tindak_lanjut_panjang: '',
    eviden: '',
});

const toForm = (
    r: Report,
    machines: MachineOption[],
    unitName = '',
): FormState => {
    const master = r.machine_id
        ? machines.find((m) => m.id === r.machine_id)
        : undefined;

    return {
        id: r.id,
        nomor: r.nomor ?? '',
        tanggal_laporan: r.tanggal_laporan ?? '',
        hal: r.hal ?? '',
        form_code: r.form_code ?? 'LH - 05',
        unit_kesatuan: r.unit_kesatuan || unitName,
        machine_id: r.machine_id ? String(r.machine_id) : '',
        merek: master ? master.merek : (r.merek ?? ''),
        type: master && master.type ? master.type : (r.type ?? ''),
        no_seri: master && master.no_seri ? master.no_seri : (r.no_seri ?? ''),
        rh: r.rh ?? '',
        jsb: r.jsb ?? '',
        jsmo: r.jsmo ?? '',
        jsi_terakhir: r.jsi_terakhir ?? '',
        fungsi_pembangkit:
            r.fungsi_pembangkit || master?.fungsi_pembangkit || unitName,
        daya_terpasang:
            master && master.daya_terpasang
                ? master.daya_terpasang
                : (r.daya_terpasang ?? ''),
        daya_mampu:
            master && master.daya_mampu
                ? master.daya_mampu
                : (r.daya_mampu ?? ''),
        tanggal_jam_kerusakan: r.tanggal_jam_kerusakan ?? '',
        peralatan_rusak: r.peralatan_rusak ?? '',
        gejala: r.gejala ?? '',
        urutan_kejadian: r.urutan_kejadian ?? '',
        parameter_terkait: r.parameter_terkait ?? '',
        analisa_penyebab: r.analisa_penyebab ?? '',
        akibat: r.akibat ?? '',
        tindak_lanjut_pendek: r.tindak_lanjut_pendek ?? '',
        tindak_lanjut_panjang: r.tindak_lanjut_panjang ?? '',
        eviden: r.eviden ?? '',
    };
};

/** Excel-style sheet classes (matching .ai/rules/pages-har.md). */
const xlSectionTh =
    'border border-black bg-yellow-300 px-2.5 py-1.5 text-left text-xs font-bold tracking-wide text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTh =
    'border border-black bg-yellow-200 px-2.5 py-1.5 text-left text-xs font-bold text-black dark:border-white/70 dark:bg-yellow-900/70 dark:text-yellow-50';
const xlTd = 'h-9 border border-black p-0 align-middle dark:border-white/60';
const xlAutoTd =
    'h-9 border border-black bg-amber-50/60 px-2.5 py-1.5 align-middle text-xs font-semibold text-foreground dark:border-white/60 dark:bg-amber-950/20';
const xlInput =
    'h-9 w-full rounded-none border-0 bg-transparent px-2.5 text-xs shadow-none outline-none focus:bg-yellow-50 focus-visible:border-0 focus-visible:ring-0 disabled:opacity-100 dark:bg-transparent dark:focus:bg-yellow-950/40';
const xlTextarea =
    'block w-full resize-y rounded-none border-0 bg-transparent px-2.5 py-2 text-xs leading-relaxed shadow-none outline-none focus:bg-yellow-50 focus-visible:border-0 focus-visible:ring-0 disabled:opacity-100 dark:bg-transparent dark:focus:bg-yellow-950/40';

const TEXTAREA_CLASS =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const MONTHS_LABEL = (r: Report): string => {
    if (r.tanggal_laporan) {
        return r.tanggal_laporan;
    }

    return '-';
};

const URAIAN_ITEMS: {
    no: string;
    label: string;
    field: StringField;
    rows?: number;
    placeholder?: string;
}[] = [
    {
        no: '1',
        label: 'Tanggal dan jam kerusakan',
        field: 'tanggal_jam_kerusakan',
        rows: 1,
        placeholder: 'Mis. Kamis, 21 November 2026 / 14:15 WITA',
    },
    {
        no: '2',
        label: 'Peralatan yang rusak',
        field: 'peralatan_rusak',
        rows: 2,
        placeholder: 'Nama komponen / peralatan yang mengalami kerusakan…',
    },
    {
        no: '3',
        label: 'Gejala / tanda - tanda',
        field: 'gejala',
        rows: 2,
        placeholder: 'Indikasi awal sebelum atau saat terjadi kerusakan…',
    },
    {
        no: '4',
        label: 'Urutan kejadian',
        field: 'urutan_kejadian',
        rows: 4,
        placeholder: 'Kronologi urutan waktu kejadian dan tindakan awal…',
    },
    {
        no: '5',
        label: 'Parameter terkait',
        field: 'parameter_terkait',
        rows: 2,
        placeholder: 'Mis. Jacket Water Temp: 87°C (Normal: 78-82°C)…',
    },
    {
        no: '6',
        label: 'Analisa penyebab kerusakan',
        field: 'analisa_penyebab',
        rows: 2,
        placeholder: 'Analisa akar penyebab gangguan / kerusakan…',
    },
    {
        no: '7',
        label: 'Akibat terhadap pembangkit',
        field: 'akibat',
        rows: 2,
        placeholder: 'Dampak terhadap operasi mesin / derating / trip…',
    },
    {
        no: '8a',
        label: 'Tindak lanjut jangka pendek',
        field: 'tindak_lanjut_pendek',
        rows: 2,
        placeholder: 'Tindakan perbaikan sementara / darurat…',
    },
    {
        no: '8b',
        label: 'Tindak lanjut jangka panjang',
        field: 'tindak_lanjut_panjang',
        rows: 2,
        placeholder: 'Rencana perbaikan permanen / pencegahan…',
    },
    {
        no: '9',
        label: 'Eviden',
        field: 'eviden',
        rows: 2,
        placeholder: 'Referensi dokumentasi / catatan eviden pemeriksaan…',
    },
];

export default function LaporanGangguanInput({
    unit,
    filters,
    reports,
    options,
    can_write,
}: Props) {
    const compact = useCompactLayout();
    const defaultMachine = options.machines[0];
    const [form, setForm] = useState<FormState>(() =>
        reports[0]
            ? toForm(reports[0], options.machines, unit.name)
            : emptyForm(defaultMachine, unit.name),
    );
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);
    // Phone (user, 2026-11-27): the reports as cards, or one report's form on its own screen.
    const [reportOpen, setReportOpen] = useState(false);

    // Reset editor when the unit/year context changes.
    const signature = `${filters.unit_id}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setForm(
            reports[0]
                ? toForm(reports[0], options.machines, unit.name)
                : emptyForm(options.machines[0], unit.name),
        );
        setIsDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            laporanGangguanRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const set = <K extends keyof FormState>(field: K, value: FormState[K]) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        setIsDirty(true);
    };

    const selectMachine = (machineId: string) => {
        const cleanId = machineId === '__none__' ? '' : machineId;
        const selected = options.machines.find((m) => String(m.id) === cleanId);

        setForm((prev) => ({
            ...prev,
            machine_id: cleanId,
            merek: selected ? selected.merek : prev.merek,
            type: selected ? selected.type : prev.type,
            no_seri: selected ? selected.no_seri : prev.no_seri,
            fungsi_pembangkit: selected
                ? selected.fungsi_pembangkit || unit.name
                : prev.fungsi_pembangkit,
            daya_terpasang: selected
                ? selected.daya_terpasang
                : prev.daya_terpasang,
            daya_mampu: selected ? selected.daya_mampu : prev.daya_mampu,
        }));
        setIsDirty(true);
    };

    const selectReport = (r: Report) => {
        setForm(toForm(r, options.machines, unit.name));
        setIsDirty(false);
    };

    const newReport = () => {
        setForm(emptyForm(options.machines[0], unit.name));
        setIsDirty(false);
    };

    const handleSave = (onSaved?: () => void) => {
        if (!can_write) {
            return;
        }

        setSaving(true);
        router.post(
            laporanGangguanRoutes.store().url,
            {
                ...form,
                unit_id: filters.unit_id,
                year: filters.year,
                machine_id: form.machine_id ? Number(form.machine_id) : null,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setIsDirty(false);
                    const nextReports = (page.props as unknown as Props)
                        .reports;

                    if (form.id === null && nextReports.length > 0) {
                        setForm(
                            toForm(nextReports[0], options.machines, unit.name),
                        );
                    }

                    onSaved?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const hasSelectedMachine = Boolean(
        form.machine_id &&
        options.machines.some((m) => String(m.id) === form.machine_id),
    );

    const textField = (label: string, field: StringField, placeholder = '') => (
        <div key={field} className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            <Input
                value={form[field]}
                onChange={(e) => set(field, e.target.value)}
                placeholder={placeholder}
                disabled={!can_write}
                className="h-9 text-sm"
            />
        </div>
    );

    const areaField = (
        label: string,
        field: StringField,
        rows = 3,
        placeholder = '',
    ) => (
        <div key={field} className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            <textarea
                value={form[field]}
                onChange={(e) => set(field, e.target.value)}
                placeholder={placeholder}
                disabled={!can_write}
                rows={rows}
                className={TEXTAREA_CLASS}
            />
        </div>
    );

    if (compact && !reportOpen) {
        return (
            <>
                <Head title="Input Laporan Gangguan" />
                <MobileEntryList
                    heading={`Laporan gangguan ${filters.year}`}
                    count={reports.length}
                    empty={`Belum ada laporan gangguan (LH-05) tahun ${filters.year}. Tekan Tambah untuk mengisi.`}
                    canWrite={can_write}
                    onAdd={() => {
                        newReport();
                        setReportOpen(true);
                    }}
                    onSave={() => undefined}
                >
                    {reports.map((r) => (
                        <MobileEntryCard
                            key={r.id}
                            title={r.hal || r.nomor || 'Laporan gangguan'}
                            subtitle={r.nomor ?? undefined}
                            meta={[
                                ['Tanggal', entryDate(r.tanggal_laporan)],
                                ['Mesin', options.machines.find((m) => m.id === r.machine_id)?.name ?? ''],
                            ]}
                            onClick={() => {
                                selectReport(r);
                                setReportOpen(true);
                            }}
                        />
                    ))}
                </MobileEntryList>
            </>
        );
    }

    return (
        <>
            <Head title="Input Laporan Gangguan" />
            <MobileFrame
                phone={compact}
                className={cn(
                    'flex flex-1 flex-col gap-3 p-3 md:gap-5 md:p-6',
                    compact && 'pb-28',
                )}
                screen={{
                    title: form.id === null ? 'Laporan gangguan baru' : 'Ubah laporan gangguan',
                    subtitle: 'Laporan Gangguan (LH-05)',
                    onBack: () => setReportOpen(false),
                    canWrite: can_write,
                    onSave: () => handleSave(() => setReportOpen(false)),
                    saving,
                    saveDisabled: !isDirty,
                    extraAction:
                        form.id !== null ? (
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => window.open(laporanGangguanRoutes.pdf(form.id!).url, '_blank', 'noopener')}
                                className="gap-1.5"
                            >
                                <Download className="size-4" />
                                PDF
                            </Button>
                        ) : undefined,
                }}
            >
                {!compact && (
                    <PageHeader
                        title="Laporan Gangguan (Kerusakan Unit Pembangkit)"
                        description="Pencatatan laporan kerusakan / gangguan unit pembangkit (Form LH-05): kronologi, analisa penyebab, dampak, dan tindak lanjut perbaikan."
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                {form.id !== null && (
                                    <a
                                        href={
                                            laporanGangguanRoutes.pdf(form.id)
                                                .url
                                        }
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button
                                            variant="outline"
                                            className="gap-2"
                                        >
                                            <Download className="size-4" />
                                            Cetak PDF
                                        </Button>
                                    </a>
                                )}
                                {can_write && (
                                    <>
                                        <Button
                                            variant="outline"
                                            onClick={newReport}
                                            className="gap-1.5"
                                        >
                                            <Plus className="size-4" />
                                            Laporan Baru
                                        </Button>
                                        <Button
                                            onClick={() => handleSave()}
                                            disabled={saving || !isDirty}
                                            className="gap-2"
                                        >
                                            <Save className="size-4" />
                                            {saving
                                                ? 'Menyimpan...'
                                                : 'Simpan Data'}
                                        </Button>
                                    </>
                                )}
                            </div>
                        }
                    />
                )}

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
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
                </div>

                <div className="grid gap-3 lg:grid-cols-[270px_1fr] lg:gap-4">
                    {/* List of reports: the phone list page, sidebar on Desktop */}
                    {compact ? null : (
                        <div className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold tracking-wider text-foreground uppercase">
                                    Daftar Laporan
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    {reports.length} laporan
                                </span>
                            </div>
                            {can_write && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={newReport}
                                    className="w-full justify-center gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Laporan Gangguan
                                </Button>
                            )}
                            <div className="flex flex-col gap-1.5">
                                {reports.length === 0 ? (
                                    <p className="py-4 text-center text-xs text-muted-foreground">
                                        Belum ada laporan gangguan.
                                    </p>
                                ) : (
                                    reports.map((r) => (
                                        <button
                                            type="button"
                                            key={r.id}
                                            onClick={() => selectReport(r)}
                                            className={`flex flex-col gap-0.5 rounded-md border p-2 text-left transition-colors ${
                                                form.id === r.id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:border-primary/40 hover:bg-muted/40'
                                            }`}
                                        >
                                            <span className="line-clamp-1 text-xs font-semibold text-foreground">
                                                {r.hal || 'Tanpa judul'}
                                            </span>
                                            <span className="line-clamp-1 text-[11px] text-muted-foreground">
                                                {r.nomor || '(tanpa nomor)'}
                                            </span>
                                            <span className="text-[11px] text-muted-foreground">
                                                {MONTHS_LABEL(r)}
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    )}

                    {/* Editor: Excel Table on Desktop, Card Layout on Mobile */}
                    <div className="flex min-w-0 flex-col gap-3 md:gap-4">
                        {!compact && (
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-[#ea7315]/10 px-4 py-2.5">
                                <div className="flex items-center gap-2">
                                    <FileWarning className="size-4 text-[#ea7315]" />
                                    <span className="text-xs font-bold tracking-wider text-foreground uppercase">
                                        {form.id === null
                                            ? 'Laporan Gangguan Baru (Form LH-05)'
                                            : 'Edit Laporan Gangguan (Form LH-05)'}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    {hasSelectedMachine && (
                                        <Badge
                                            variant="secondary"
                                            className="text-[11px]"
                                        >
                                            Identitas Mesin Otomatis dari Master
                                            Mesin
                                        </Badge>
                                    )}
                                    {isDirty && (
                                        <span className="animate-pulse text-xs font-medium text-amber-600 dark:text-amber-400">
                                            ● Perubahan belum disimpan
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}

                        {compact ? (
                            /* Mobile Card View (streamlined: no redundant auto machine specs) */
                            <>
                                <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                                    <h3 className="mb-2.5 text-sm font-semibold text-foreground">
                                        Informasi Laporan
                                    </h3>
                                    <div className="grid gap-2.5">
                                        {textField(
                                            'Nomor',
                                            'nomor',
                                            '003/ULPLTD POASIA/LH05/XI/2026',
                                        )}
                                        {textField(
                                            'Hal / Judul',
                                            'hal',
                                            'SHAFT & BEARING GENERATOR',
                                        )}
                                        <div className="grid grid-cols-2 gap-2.5">
                                            <div className="grid gap-1.5">
                                                <Label className="text-xs">
                                                    Tanggal Laporan
                                                </Label>
                                                <Input
                                                    type="date"
                                                    value={form.tanggal_laporan}
                                                    onChange={(e) =>
                                                        set(
                                                            'tanggal_laporan',
                                                            e.target.value,
                                                        )
                                                    }
                                                    disabled={!can_write}
                                                    className="h-9 text-sm"
                                                />
                                            </div>
                                            {textField(
                                                'Form',
                                                'form_code',
                                                'LH - 05',
                                            )}
                                        </div>
                                        {textField(
                                            'Unit Kesatuan',
                                            'unit_kesatuan',
                                            unit.name,
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                                    <h3 className="mb-2.5 text-sm font-semibold text-foreground">
                                        Mesin &amp; Jam Operasi
                                    </h3>
                                    <div className="grid gap-2.5">
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                Mesin Pembangkit
                                            </Label>
                                            <Select
                                                value={
                                                    form.machine_id
                                                        ? String(
                                                              form.machine_id,
                                                          )
                                                        : '__none__'
                                                }
                                                onValueChange={selectMachine}
                                                disabled={!can_write}
                                            >
                                                <SelectTrigger className="h-9 w-full text-sm">
                                                    <SelectValue placeholder="Pilih mesin" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value="__none__"
                                                        className="text-muted-foreground"
                                                    >
                                                        — Pilih mesin —
                                                    </SelectItem>
                                                    {options.machines.map(
                                                        (m) => (
                                                            <SelectItem
                                                                key={m.id}
                                                                value={String(
                                                                    m.id,
                                                                )}
                                                            >
                                                                {m.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2.5">
                                            {textField('RH', 'rh', '24500 Jam')}
                                            {textField(
                                                'JSB',
                                                'jsb',
                                                '1200 Jam',
                                            )}
                                            {textField(
                                                'JSMO',
                                                'jsmo',
                                                '8500 Jam',
                                            )}
                                            {textField(
                                                'JSI Terakhir (MO)',
                                                'jsi_terakhir',
                                                '350 Jam',
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                                    <h3 className="mb-2.5 text-sm font-semibold text-foreground">
                                        Uraian Gangguan
                                    </h3>
                                    <div className="grid gap-2.5">
                                        {URAIAN_ITEMS.map((item) =>
                                            item.rows === 1
                                                ? textField(
                                                      `${item.no}. ${item.label}`,
                                                      item.field,
                                                      item.placeholder,
                                                  )
                                                : areaField(
                                                      `${item.no}. ${item.label}`,
                                                      item.field,
                                                      item.rows,
                                                      item.placeholder,
                                                  ),
                                        )}
                                    </div>
                                </div>

                                {can_write && form.id !== null && (
                                    <ConfirmDeleteDialog
                                        action={laporanGangguanRoutes.destroy.form(
                                            form.id,
                                        )}
                                        title="Hapus Laporan Gangguan"
                                        description={`Hapus laporan "${form.hal || 'ini'}"? Tindakan ini tidak dapat dibatalkan.`}
                                        trigger={
                                            <Button
                                                variant="outline"
                                                className="w-full gap-1.5 text-destructive hover:text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                                Hapus Laporan Ini
                                            </Button>
                                        }
                                    />
                                )}
                            </>
                        ) : (
                            /* Desktop Excel-Style Sheet Layout */
                            <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                                {/* Kop Dokumen Excel */}
                                <table className="w-full border-collapse border-2 border-black text-xs dark:border-white">
                                    <tbody>
                                        <tr>
                                            <td className="w-40 border border-black p-2.5 text-center align-middle dark:border-white/60">
                                                <img
                                                    src="/logo/sidebar-logo.png"
                                                    alt="PLN Nusantara Power"
                                                    className="mx-auto max-h-11 object-contain"
                                                />
                                            </td>
                                            <td className="border border-black px-3 py-2 text-center align-middle font-bold uppercase dark:border-white/60">
                                                <div className="text-[11px] tracking-wider text-muted-foreground">
                                                    JASA PENDUKUNG TEKNIS - 6
                                                    SITE UP KENDARI
                                                </div>
                                                <div className="text-sm text-foreground">
                                                    {unit.name}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    LAPORAN PROJECT
                                                </div>
                                                <div className="mt-0.5 text-sm font-extrabold tracking-tight text-foreground">
                                                    LAPORAN KERUSAKAN / GANGGUAN
                                                    UNIT PEMBANGKIT (LH-05)
                                                </div>
                                            </td>
                                            <td className="w-40 border border-black p-2.5 text-center align-middle dark:border-white/60">
                                                <img
                                                    src="/logo/mkp.jpg"
                                                    alt="Mitra Karya Prima"
                                                    className="mx-auto max-h-11 object-contain"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                {/* A. INFORMASI LAPORAN */}
                                <div className="overflow-x-auto">
                                    <table className="w-full table-fixed border-collapse border-2 border-black text-xs dark:border-white">
                                        <thead>
                                            <tr>
                                                <th
                                                    colSpan={4}
                                                    className={xlSectionTh}
                                                >
                                                    A. INFORMASI LAPORAN
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={cn(xlTh, 'w-44')}
                                                >
                                                    Nomor Laporan
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.nomor}
                                                        onChange={(e) =>
                                                            set(
                                                                'nomor',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="LH05/PLTD-POASIA/11/2026/001"
                                                        disabled={!can_write}
                                                        aria-label="Nomor Laporan"
                                                        className={xlInput}
                                                    />
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={cn(xlTh, 'w-44')}
                                                >
                                                    Hal / Judul
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.hal}
                                                        onChange={(e) =>
                                                            set(
                                                                'hal',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Laporan Gangguan Sistem Pendingin…"
                                                        disabled={!can_write}
                                                        aria-label="Hal / Judul"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Tanggal Laporan
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        type="date"
                                                        value={
                                                            form.tanggal_laporan
                                                        }
                                                        onChange={(e) =>
                                                            set(
                                                                'tanggal_laporan',
                                                                e.target.value,
                                                            )
                                                        }
                                                        disabled={!can_write}
                                                        aria-label="Tanggal Laporan"
                                                        className={xlInput}
                                                    />
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Form
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.form_code}
                                                        onChange={(e) =>
                                                            set(
                                                                'form_code',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="LH - 05"
                                                        disabled={!can_write}
                                                        aria-label="Form"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Unit Kesatuan
                                                </th>
                                                <td
                                                    colSpan={3}
                                                    className={xlTd}
                                                >
                                                    <Input
                                                        value={
                                                            form.unit_kesatuan
                                                        }
                                                        onChange={(e) =>
                                                            set(
                                                                'unit_kesatuan',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={unit.name}
                                                        disabled={!can_write}
                                                        aria-label="Unit Kesatuan"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {/* B. IDENTITAS MESIN */}
                                <div className="overflow-x-auto">
                                    <table className="w-full table-fixed border-collapse border-2 border-black text-xs dark:border-white">
                                        <thead>
                                            <tr>
                                                <th
                                                    colSpan={4}
                                                    className={xlSectionTh}
                                                >
                                                    B. IDENTITAS MESIN (OTOMATIS
                                                    DARI DATA MASTER MESIN)
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={cn(xlTh, 'w-44')}
                                                >
                                                    Pilih Mesin
                                                </th>
                                                <td className={xlTd}>
                                                    <Select
                                                        value={
                                                            form.machine_id
                                                                ? String(
                                                                      form.machine_id,
                                                                  )
                                                                : '__none__'
                                                        }
                                                        onValueChange={
                                                            selectMachine
                                                        }
                                                        disabled={!can_write}
                                                    >
                                                        <SelectTrigger
                                                            aria-label="Pilih Mesin"
                                                            className={cn(
                                                                xlInput,
                                                                'font-semibold focus-visible:ring-0',
                                                            )}
                                                        >
                                                            <SelectValue placeholder="Pilih mesin…" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value="__none__"
                                                                className="text-muted-foreground"
                                                            >
                                                                — Pilih mesin —
                                                            </SelectItem>
                                                            {options.machines.map(
                                                                (m) => (
                                                                    <SelectItem
                                                                        key={
                                                                            m.id
                                                                        }
                                                                        value={String(
                                                                            m.id,
                                                                        )}
                                                                    >
                                                                        {m.name}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={cn(xlTh, 'w-44')}
                                                >
                                                    Merek
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.merek || '—'}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={form.merek}
                                                            onChange={(e) =>
                                                                set(
                                                                    'merek',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Pilih mesin untuk otomatis…"
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="Merek"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Type
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.type || '—'}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={form.type}
                                                            onChange={(e) =>
                                                                set(
                                                                    'type',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="Type"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    No. Seri
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.no_seri ||
                                                                '—'}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={form.no_seri}
                                                            onChange={(e) =>
                                                                set(
                                                                    'no_seri',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="No. Seri"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Daya Terpasang
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.daya_terpasang ||
                                                                '—'}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={
                                                                form.daya_terpasang
                                                            }
                                                            onChange={(e) =>
                                                                set(
                                                                    'daya_terpasang',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="Daya Terpasang"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Daya Mampu
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.daya_mampu ||
                                                                '—'}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={
                                                                form.daya_mampu
                                                            }
                                                            onChange={(e) =>
                                                                set(
                                                                    'daya_mampu',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="Daya Mampu"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    Fungsi Pembangkit
                                                </th>
                                                <td
                                                    className={
                                                        hasSelectedMachine
                                                            ? xlAutoTd
                                                            : xlTd
                                                    }
                                                >
                                                    {hasSelectedMachine ? (
                                                        <span>
                                                            {form.fungsi_pembangkit ||
                                                                unit.name}
                                                        </span>
                                                    ) : (
                                                        <Input
                                                            value={
                                                                form.fungsi_pembangkit
                                                            }
                                                            onChange={(e) =>
                                                                set(
                                                                    'fungsi_pembangkit',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            aria-label="Fungsi Pembangkit"
                                                            className={xlInput}
                                                        />
                                                    )}
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    RH (Running Hours)
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.rh}
                                                        onChange={(e) =>
                                                            set(
                                                                'rh',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Mis. 24500 Jam"
                                                        disabled={!can_write}
                                                        aria-label="RH"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    JSB
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.jsb}
                                                        onChange={(e) =>
                                                            set(
                                                                'jsb',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Mis. 1200 Jam"
                                                        disabled={!can_write}
                                                        aria-label="JSB"
                                                        className={xlInput}
                                                    />
                                                </td>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    JSMO
                                                </th>
                                                <td className={xlTd}>
                                                    <Input
                                                        value={form.jsmo}
                                                        onChange={(e) =>
                                                            set(
                                                                'jsmo',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Mis. 8500 Jam"
                                                        disabled={!can_write}
                                                        aria-label="JSMO"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                            <tr>
                                                <th
                                                    scope="row"
                                                    className={xlTh}
                                                >
                                                    JSI Terakhir (MO)
                                                </th>
                                                <td
                                                    colSpan={3}
                                                    className={xlTd}
                                                >
                                                    <Input
                                                        value={
                                                            form.jsi_terakhir
                                                        }
                                                        onChange={(e) =>
                                                            set(
                                                                'jsi_terakhir',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Mis. 350 Jam"
                                                        disabled={!can_write}
                                                        aria-label="JSI Terakhir (MO)"
                                                        className={xlInput}
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {/* C. URAIAN GANGGUAN (1 s.d. 9) */}
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse border-2 border-black text-xs dark:border-white">
                                        <thead>
                                            <tr>
                                                <th
                                                    colSpan={3}
                                                    className={xlSectionTh}
                                                >
                                                    C. URAIAN GANGGUAN /
                                                    KERUSAKAN
                                                </th>
                                            </tr>
                                            <tr>
                                                <th
                                                    className={cn(
                                                        xlTh,
                                                        'w-12 text-center',
                                                    )}
                                                >
                                                    No
                                                </th>
                                                <th
                                                    className={cn(xlTh, 'w-64')}
                                                >
                                                    Uraian Pemeriksaan & Analisa
                                                </th>
                                                <th className={xlTh}>
                                                    Isian / Keterangan Detail
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {URAIAN_ITEMS.map((item) => (
                                                <tr key={item.field}>
                                                    <td className="border border-black bg-yellow-100/70 px-2 py-1.5 text-center font-bold text-black dark:border-white/60 dark:bg-yellow-950/30 dark:text-yellow-50">
                                                        {item.no}
                                                    </td>
                                                    <th
                                                        scope="row"
                                                        className={xlTh}
                                                    >
                                                        {item.label}
                                                    </th>
                                                    <td className={xlTd}>
                                                        {item.rows === 1 ? (
                                                            <Input
                                                                value={
                                                                    form[
                                                                        item
                                                                            .field
                                                                    ]
                                                                }
                                                                onChange={(e) =>
                                                                    set(
                                                                        item.field,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={
                                                                    item.placeholder
                                                                }
                                                                disabled={
                                                                    !can_write
                                                                }
                                                                aria-label={
                                                                    item.label
                                                                }
                                                                className={
                                                                    xlInput
                                                                }
                                                            />
                                                        ) : (
                                                            <textarea
                                                                value={
                                                                    form[
                                                                        item
                                                                            .field
                                                                    ]
                                                                }
                                                                onChange={(e) =>
                                                                    set(
                                                                        item.field,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={
                                                                    item.placeholder
                                                                }
                                                                rows={
                                                                    item.rows ??
                                                                    2
                                                                }
                                                                disabled={
                                                                    !can_write
                                                                }
                                                                aria-label={
                                                                    item.label
                                                                }
                                                                className={
                                                                    xlTextarea
                                                                }
                                                            />
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Footer actions (Desktop inline footer, StickyActionBar on Mobile) */}
                        {!compact && can_write && (
                            <div className="flex items-center justify-between rounded-lg border border-border bg-card p-3 shadow-xs">
                                <div>
                                    {form.id !== null && (
                                        <ConfirmDeleteDialog
                                            action={laporanGangguanRoutes.destroy.form(
                                                form.id,
                                            )}
                                            title="Hapus Laporan Gangguan"
                                            description={`Hapus laporan "${form.hal || 'ini'}"? Tindakan ini tidak dapat dibatalkan.`}
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    className="gap-1.5 text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="size-4" />
                                                    Hapus
                                                </Button>
                                            }
                                        />
                                    )}
                                </div>
                                <Button
                                    onClick={() => handleSave()}
                                    disabled={saving || !isDirty}
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Data'}
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </MobileFrame>

        </>
    );
}

LaporanGangguanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        {
            title: 'Formulir Laporan Gangguan (LH-05)',
            href: laporanGangguanRoutes.index(),
        },
    ],
};
