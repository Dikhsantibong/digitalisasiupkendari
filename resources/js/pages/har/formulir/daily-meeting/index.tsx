import { Head, router } from '@inertiajs/react';
import { Download, ImagePlus, Plus, Save, Trash2, Users, X } from 'lucide-react';
import { useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import dailyMeetingRoutes from '@/routes/har/formulir/daily-meeting';
import type { IdName } from '@/types';

type Peserta = { nama: string | null; asal: string | null; jabatan: string | null };
type Meeting = {
    id: number;
    tanggal: string;
    acara: string;
    waktu: string | null;
    tempat: string | null;
    peserta: Peserta[];
    eviden: { path: string; url: string }[];
};

type Props = {
    unit: IdName;
    filters: { unit_id: number; month: number; year: number; meeting_id: number | null };
    options: { units: IdName[]; years: number[]; min_rows: number; max_eviden: number };
    meetings: { id: number; tanggal: string; acara: string; peserta: number; eviden: number }[];
    meeting: Meeting | null;
    signers: { kiri: { jabatan: string; nama: string }; kanan: { jabatan: string; nama: string } };
    can_write: boolean;
};

const cellInput = 'h-8 rounded-none border-0 bg-transparent px-2 text-xs shadow-none focus-visible:ring-1';
const blankPeserta = (): Peserta => ({ nama: null, asal: null, jabatan: null });
const withMinRows = (list: Peserta[], min: number) => [...list, ...Array.from({ length: Math.max(0, min - list.length) }, blankPeserta)];
const formatDate = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);

    return `${d} ${OPERASI_MONTHS[m - 1]} ${y}`;
};

/**
 * Formulir Daily Meeting Pemeliharaan: daftar hadir per meeting (dicetak
 * lembar 1) dan foto eviden (lembar 2). Data juga masuk Laporan Pemeliharaan.
 */
export default function HarDailyMeetingPage({ unit, filters, options, meetings, meeting, signers, can_write }: Props) {
    const compact = useCompactLayout();
    const { can } = usePermissions();
    const defaultDate = `${filters.year}-${String(filters.month).padStart(2, '0')}-01`;
    const [form, setForm] = useState(() => ({
        tanggal: meeting?.tanggal ?? defaultDate,
        acara: meeting?.acara ?? 'Meeting HAR',
        waktu: meeting?.waktu ?? '',
        tempat: meeting?.tempat ?? '',
    }));
    const [peserta, setPeserta] = useState<Peserta[]>(() => withMinRows(meeting?.peserta ?? [], options.min_rows));
    const [kept, setKept] = useState(meeting?.eviden ?? []);
    const [uploads, setUploads] = useState<File[]>([]);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const room = options.max_eviden - kept.length - uploads.length;

    const touch = () => setDirty(true);

    const go = (query: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(dailyMeetingRoutes.index().url, { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...query });
    };

    const setRow = (index: number, key: keyof Peserta, value: string) => {
        setPeserta((rows) => rows.map((row, i) => (i === index ? { ...row, [key]: value === '' ? null : value } : row)));
        touch();
    };

    const save = () => {
        setSaving(true);
        router.post(
            dailyMeetingRoutes.store().url,
            {
                unit_id: filters.unit_id,
                id: meeting?.id ?? null,
                ...form,
                peserta: peserta.filter((p) => (p.nama ?? '').trim() !== ''),
                keep_eviden: kept.map((photo) => photo.path),
                eviden: uploads,
            },
            {
                forceFormData: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const remove = () => {
        if (meeting && window.confirm(`Hapus daily meeting ${formatDate(meeting.tanggal)} beserta foto evidennya?`)) {
            router.delete(dailyMeetingRoutes.destroy(meeting.id).url);
        }
    };

    return (
        <>
            <Head title={`Formulir Daily Meeting - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Formulir Daily Meeting"
                    description={`Daftar hadir meeting pemeliharaan (lembar 1) dan foto eviden (lembar 2) — ${unit.name} · ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}.`}
                    actions={
                        can('har.input.view') && (
                            <Button variant="outline" onClick={() => router.get(harFormulir.index().url, { unit_id: filters.unit_id })}>
                                Kembali
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => go({ unit_id: Number(v), meeting_id: null })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => go({ month: Number(v), meeting_id: null })} options={OPERASI_MONTHS.map((label, i) => ({ value: String(i + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => go({ year: Number(v), meeting_id: null })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                </div>

                <div className="flex flex-col items-start gap-4 lg:flex-row">
                    <aside className="w-full shrink-0 rounded-md border border-border bg-card lg:w-72" aria-label="Daftar meeting">
                        <div className="flex items-center justify-between border-b border-border p-3">
                            <span className="text-sm font-semibold">Meeting {OPERASI_MONTHS[filters.month - 1]}</span>
                            {can_write && (
                                <Button size="sm" variant="outline" onClick={() => go({ meeting_id: null })} className="h-7 gap-1 text-xs">
                                    <Plus className="size-3.5" />
                                    Baru
                                </Button>
                            )}
                        </div>
                        {meetings.length === 0 ? (
                            <p className="p-3 text-xs text-muted-foreground">Belum ada meeting tersimpan bulan ini.</p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {meetings.map((m) => (
                                    <li key={m.id}>
                                        <button
                                            type="button"
                                            onClick={() => go({ meeting_id: m.id })}
                                            className={`w-full px-3 py-2 text-left text-xs transition-colors hover:bg-muted/40 ${meeting?.id === m.id ? 'bg-primary/10 font-semibold text-primary' : ''}`}
                                        >
                                            <div>{formatDate(m.tanggal)}</div>
                                            <div className="text-muted-foreground">{m.acara} · {m.peserta} peserta · {m.eviden} foto</div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </aside>

                    <section className="flex w-full min-w-0 flex-1 flex-col gap-4 rounded-md border border-border bg-card p-4" aria-label="Editor meeting">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <Users className="size-4 text-primary" />
                                {meeting ? `Meeting ${formatDate(meeting.tanggal)}` : 'Meeting baru'}
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {meeting && (
                                    <Button variant="outline" size="sm" onClick={() => window.open(dailyMeetingRoutes.pdf(meeting.id).url, '_blank')} className="gap-1.5">
                                        <Download className="size-4 text-rose-600" />
                                        PDF
                                    </Button>
                                )}
                                {meeting && can_write && (
                                    <Button variant="outline" size="sm" onClick={remove} className="gap-1.5 text-destructive">
                                        <Trash2 className="size-4" />
                                        Hapus
                                    </Button>
                                )}
                                {can_write && (
                                    <Button size="sm" onClick={save} disabled={saving || (!dirty && meeting !== null)} className="gap-1.5">
                                        <Save className="size-4" />
                                        {saving ? 'Menyimpan…' : 'Simpan'}
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {([
                                ['acara', 'Acara', 'text'],
                                ['tanggal', 'Hari/Tanggal', 'date'],
                                ['waktu', 'Waktu', 'text'],
                                ['tempat', 'Tempat', 'text'],
                            ] as const).map(([key, label, type]) => (
                                <div key={key} className="flex flex-col gap-1.5">
                                    <Label htmlFor={`meeting-${key}`}>{label}</Label>
                                    <Input
                                        id={`meeting-${key}`}
                                        type={type}
                                        value={form[key]}
                                        onChange={(e) => {
                                            setForm((f) => ({ ...f, [key]: e.target.value }));
                                            touch();
                                        }}
                                        placeholder={key === 'waktu' ? '16.00' : undefined}
                                        disabled={!can_write}
                                    />
                                </div>
                            ))}
                        </div>

                        {compact ? (
<MobileRowEditor
    rows={peserta}
    canWrite={can_write}
    title={(row, index) => row.nama || `Peserta ${index + 1}`}
    subtitle={(row) => [row.jabatan, row.asal].filter(Boolean).join(' · ') || 'Tanda tangan manual'}
    onChange={(index, key, value) => setRow(index, key, String(value ?? ''))}
    fields={[
        { key: 'nama', label: 'Nama' },
        { key: 'asal', label: 'Asal / perusahaan' },
        { key: 'jabatan', label: 'Jabatan' },
    ]}
/>
                        ) : (
                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="w-full min-w-[640px] border-collapse text-xs">
                                <thead className="bg-muted/60 text-center font-semibold">
                                    <tr>
                                        <th className="w-10 border border-border p-1.5">No</th>
                                        <th className="border border-border p-1.5">Nama</th>
                                        <th className="border border-border p-1.5">Asal/Perusahaan</th>
                                        <th className="border border-border p-1.5">Jabatan</th>
                                        <th className="w-24 border border-border p-1.5">Tanda Tangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {peserta.map((row, index) => (
                                        <tr key={index}>
                                            <td className="border border-border text-center">{index + 1}</td>
                                            {(['nama', 'asal', 'jabatan'] as const).map((key) => (
                                                <td key={key} className="border border-border p-0">
                                                    <Input value={row[key] ?? ''} onChange={(e) => setRow(index, key, e.target.value)} className={cellInput} disabled={!can_write} aria-label={`${key} peserta ${index + 1}`} />
                                                </td>
                                            ))}
                                            <td className="border border-border text-center text-[10px] text-muted-foreground">ttd manual</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        )}
                        {can_write && (
                            <Button variant="outline" size="sm" onClick={() => {
 setPeserta((rows) => [...rows, blankPeserta()]); touch(); 
}} className="w-fit gap-1 text-xs">
                                <Plus className="size-3.5" />
                                Tambah Baris Peserta
                            </Button>
                        )}

                        <div className="flex flex-col gap-2">
                            <Label>Foto Eviden (lembar 2 PDF, maks. {options.max_eviden})</Label>
                            <div className="flex flex-wrap gap-3">
                                {kept.map((photo) => (
                                    <span key={photo.path} className="relative">
                                        <img src={photo.url} alt="Eviden meeting" className="h-28 w-40 rounded object-cover" />
                                        {can_write && (
                                            <button type="button" onClick={() => {
 setKept((list) => list.filter((p) => p.path !== photo.path)); touch(); 
}} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Hapus foto">
                                                <X className="size-3" />
                                            </button>
                                        )}
                                    </span>
                                ))}
                                {uploads.map((file, i) => (
                                    <span key={`${file.name}-${i}`} className="relative">
                                        <img src={URL.createObjectURL(file)} alt={file.name} className="h-28 w-40 rounded object-cover ring-2 ring-amber-400" />
                                        <button type="button" onClick={() => {
 setUploads((list) => list.filter((_, j) => j !== i)); touch(); 
}} className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white" aria-label="Batalkan foto">
                                            <X className="size-3" />
                                        </button>
                                    </span>
                                ))}
                                {can_write && room > 0 && (
                                    <label className="flex h-28 w-40 cursor-pointer flex-col items-center justify-center gap-1 rounded border border-dashed border-input text-xs text-muted-foreground hover:border-primary hover:text-primary">
                                        <ImagePlus className="size-5" />
                                        Tambah foto ({room})
                                        <input
                                            type="file"
                                            accept="image/*"
                                            multiple
                                            className="sr-only"
                                            onChange={(e) => {
                                                const files = Array.from(e.target.files ?? []).slice(0, room);
                                                setUploads((list) => [...list, ...files]);
                                                touch();
                                                e.target.value = '';
                                            }}
                                        />
                                    </label>
                                )}
                            </div>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Penandatangan lembar: {signers.kiri.jabatan} ({signers.kiri.nama || 'belum ada pegawai'}) · {signers.kanan.jabatan} ({signers.kanan.nama || 'belum ada pegawai'}).
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}

HarDailyMeetingPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Formulir Daily Meeting', href: dailyMeetingRoutes.index() },
    ],
};
