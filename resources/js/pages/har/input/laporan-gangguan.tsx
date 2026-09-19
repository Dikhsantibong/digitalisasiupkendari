import { Head, router } from '@inertiajs/react';
import { Download, FileWarning, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import laporanGangguanRoutes from '@/routes/har/input/laporan-gangguan';
import type { IdName } from '@/types';

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
    options: { units: IdName[]; years: number[]; machines: IdName[] };
    can_write: boolean;
};

const emptyForm = (): FormState => ({
    id: null,
    nomor: '',
    tanggal_laporan: '',
    hal: '',
    form_code: 'LH - 05',
    unit_kesatuan: '',
    machine_id: '',
    merek: '',
    type: '',
    no_seri: '',
    rh: '',
    jsb: '',
    jsmo: '',
    jsi_terakhir: '',
    fungsi_pembangkit: '',
    daya_terpasang: '',
    daya_mampu: '',
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

const toForm = (r: Report): FormState => ({
    id: r.id,
    nomor: r.nomor ?? '',
    tanggal_laporan: r.tanggal_laporan ?? '',
    hal: r.hal ?? '',
    form_code: r.form_code ?? 'LH - 05',
    unit_kesatuan: r.unit_kesatuan ?? '',
    machine_id: r.machine_id ? String(r.machine_id) : '',
    merek: r.merek ?? '',
    type: r.type ?? '',
    no_seri: r.no_seri ?? '',
    rh: r.rh ?? '',
    jsb: r.jsb ?? '',
    jsmo: r.jsmo ?? '',
    jsi_terakhir: r.jsi_terakhir ?? '',
    fungsi_pembangkit: r.fungsi_pembangkit ?? '',
    daya_terpasang: r.daya_terpasang ?? '',
    daya_mampu: r.daya_mampu ?? '',
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
});

const TEXTAREA_CLASS =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const MONTHS_LABEL = (r: Report): string => {
    if (r.tanggal_laporan) return r.tanggal_laporan;
    return '-';
};

export default function LaporanGangguanInput({
    unit,
    filters,
    reports,
    options,
    can_write,
}: Props) {
    const [form, setForm] = useState<FormState>(emptyForm());
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    // Reset editor when the unit/year context changes.
    const signature = `${filters.unit_id}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setForm(emptyForm());
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

    const selectReport = (r: Report) => {
        setForm(toForm(r));
        setIsDirty(false);
    };

    const newReport = () => {
        setForm(emptyForm());
        setIsDirty(false);
    };

    const handleSave = () => {
        if (!can_write) return;
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
                onSuccess: () => {
                    setIsDirty(false);
                    // For a freshly created report, clear the editor; it now
                    // appears in the refreshed list on the left.
                    if (form.id === null) {
                        setForm(emptyForm());
                    }
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const textField = (
        label: string,
        field: StringField,
        placeholder = '',
    ) => (
        <div className="grid gap-1.5">
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
        <div className="grid gap-1.5">
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

    return (
        <>
            <Head title="Input Laporan Gangguan" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Gangguan (Kerusakan Unit Pembangkit)"
                    description="Pencatatan laporan kerusakan / gangguan unit pembangkit (Form LH-05): kronologi, analisa penyebab, dampak, dan tindak lanjut perbaikan."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {form.id !== null && (
                                <a
                                    href={
                                        laporanGangguanRoutes.pdf(form.id).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Button variant="outline" className="gap-2">
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
                                        onClick={handleSave}
                                        disabled={saving || !isDirty}
                                        className="gap-2"
                                    >
                                        <Save className="size-4" />
                                        {saving ? 'Menyimpan...' : 'Simpan Data'}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

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

                <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                    {/* List of reports */}
                    <div className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold uppercase tracking-wider text-foreground">
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

                    {/* Form */}
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between rounded-lg border border-border bg-[#ea7315]/10 px-4 py-2.5">
                            <div className="flex items-center gap-2">
                                <FileWarning className="size-4 text-[#ea7315]" />
                                <span className="text-xs font-bold uppercase tracking-wider text-foreground">
                                    {form.id === null
                                        ? 'Laporan Gangguan Baru'
                                        : 'Edit Laporan Gangguan'}
                                </span>
                            </div>
                            {isDirty && (
                                <span className="animate-pulse text-xs font-medium text-amber-600 dark:text-amber-400">
                                    ● Perubahan belum disimpan
                                </span>
                            )}
                        </div>

                        {/* Informasi Laporan */}
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <h3 className="mb-3 text-sm font-semibold text-foreground">
                                Informasi Laporan
                            </h3>
                            <div className="grid gap-3 md:grid-cols-2">
                                {textField('Nomor', 'nomor', '003/ULPLTD POASIA/LH05/XI/2023')}
                                {textField('Hal / Judul', 'hal', 'SHAFT & BEARING GENERATOR')}
                                {textField('Tanggal Laporan', 'tanggal_laporan')}
                                {textField('Form', 'form_code', 'LH - 05')}
                                {textField('Unit Kesatuan', 'unit_kesatuan', 'CONTAINERIZED SITE POASIA')}
                            </div>
                            <p className="mt-2 text-[11px] text-muted-foreground">
                                * Isi "Tanggal Laporan" dengan format tanggal
                                (mis. 2023-11-21) untuk tampil rapi pada PDF.
                            </p>
                        </div>

                        {/* Identitas Mesin */}
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <h3 className="mb-3 text-sm font-semibold text-foreground">
                                Identitas Mesin
                            </h3>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Mesin (opsional)</Label>
                                    <select
                                        value={form.machine_id}
                                        onChange={(e) => set('machine_id', e.target.value)}
                                        disabled={!can_write}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                                    >
                                        <option value="">— Pilih mesin —</option>
                                        {options.machines.map((m) => (
                                            <option key={m.id} value={String(m.id)}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                {textField('Merek', 'merek', 'Cummins Unit #6')}
                                {textField('Type', 'type', 'KTA-5-G8')}
                                {textField('No. Seri', 'no_seri')}
                                {textField('RH', 'rh', '9003.1 HRS')}
                                {textField('JSB', 'jsb')}
                                {textField('JSMO', 'jsmo')}
                                {textField('JSI Terakhir (MO)', 'jsi_terakhir')}
                                {textField('Fungsi Pembangkit', 'fungsi_pembangkit', 'PLTD POASIA')}
                                {textField('Daya Terpasang', 'daya_terpasang', '11200 kw')}
                                {textField('Daya Mampu', 'daya_mampu', '850 kw')}
                            </div>
                        </div>

                        {/* Uraian Gangguan */}
                        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                            <h3 className="mb-3 text-sm font-semibold text-foreground">
                                Uraian Gangguan
                            </h3>
                            <div className="grid gap-3">
                                {textField('1. Tanggal dan jam kerusakan', 'tanggal_jam_kerusakan', 'Kamis, 21 November 2023 / 23.38')}
                                {areaField('2. Peralatan yang rusak', 'peralatan_rusak', 2)}
                                {areaField('3. Gejala / tanda - tanda', 'gejala', 2)}
                                {areaField('4. Urutan kejadian', 'urutan_kejadian', 5)}
                                {areaField('5. Parameter terkait', 'parameter_terkait', 2)}
                                {areaField('6. Analisa penyebab kerusakan', 'analisa_penyebab', 2)}
                                {areaField('7. Akibat terhadap pembangkit', 'akibat', 2)}
                                {areaField('8a. Tindak lanjut jangka pendek', 'tindak_lanjut_pendek', 2)}
                                {areaField('8b. Tindak lanjut jangka panjang', 'tindak_lanjut_panjang', 2)}
                                {areaField('9. Eviden', 'eviden', 2)}
                            </div>
                        </div>

                        {/* Footer actions */}
                        {can_write && (
                            <div className="flex items-center justify-between rounded-lg border border-border bg-card p-3 shadow-xs">
                                <div>
                                    {form.id !== null && (
                                        <ConfirmDeleteDialog
                                            action={laporanGangguanRoutes.destroy.form(form.id)}
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
                                    onClick={handleSave}
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
            </div>
        </>
    );
}

LaporanGangguanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Laporan Gangguan', href: laporanGangguanRoutes.index() },
    ],
};
