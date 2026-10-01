import { Head, router } from '@inertiajs/react';
import { ArrowRight, Info, Save, Search, UserCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import reportSigners from '@/routes/admin/report-signers';

type Cell = { own: string | null; signer: string | null; source_unit: string | null };

type Props = {
    units: { id: number; name: string; is_active: boolean }[];
    positions: { value: string; label: string }[];
    matrix: { unit_id: number; cells: Record<string, Cell> }[];
    delegations: { id: number; unit: string; unit_id: number; position: string; source_unit: string; source_unit_id: number; signer: string | null; granted: number }[];
};

/** Jabatan groups of the overview, so the matrix can show one at a time. */
const POSITION_GROUPS: { key: string; label: string; match: (position: string) => boolean }[] = [
    { key: 'tl', label: 'Team Leader', match: (p) => p.startsWith('Team Leader') },
    { key: 'koordinator', label: 'Koordinator', match: (p) => p.startsWith('Koordinator') },
    { key: 'manager', label: 'Manager & Project Leader', match: (p) => p === 'Manager UL' || p === 'Project Leader' },
    { key: 'office', label: 'Office & PIC', match: (p) => p.startsWith('Office') || p === 'PIC PDM' },
];

export default function ReportSigners({ units, positions, matrix, delegations }: Props) {
    const [unitId, setUnitId] = useState('');
    const [position, setPosition] = useState('Team Leader Pemeliharaan');
    const [sourceUnitId, setSourceUnitId] = useState('');
    const [grantAccess, setGrantAccess] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [group, setGroup] = useState('tl');
    const [search, setSearch] = useState('');

    const unitName = useMemo(() => Object.fromEntries(units.map((u) => [u.id, u.name])), [units]);
    const cellsOf = useMemo(() => Object.fromEntries(matrix.map((row) => [row.unit_id, row.cells])), [matrix]);
    const unitOptions = units.map((u) => ({ value: String(u.id), label: u.is_active ? u.name : `${u.name} (nonaktif)` }));

    const previewSigner = sourceUnitId ? (cellsOf[Number(sourceUnitId)]?.[position]?.own ?? null) : null;
    const currentSigner = unitId ? cellsOf[Number(unitId)]?.[position] : undefined;

    const save = () => {
        setSaving(true);
        router.post(
            reportSigners.store().url,
            { unit_id: unitId ? Number(unitId) : null, position, source_unit_id: sourceUnitId ? Number(sourceUnitId) : null, grant_access: grantAccess },
            {
                preserveScroll: true,
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setErrors({});
                    setSourceUnitId('');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const shownPositions = positions.filter((p) => POSITION_GROUPS.find((g) => g.key === group)?.match(p.value));
    const shownUnits = units.filter((u) => u.name.toLowerCase().includes(search.trim().toLowerCase()));

    return (
        <>
            <Head title="Penanda Tangan Laporan" />
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Penanda Tangan Laporan"
                    description="Atur jabatan yang menandatangani (memeriksa, menyetujui, mengesahkan) Laporan Pembangkit sebuah unit oleh pemegang jabatan di unit lain."
                />

                <div className="flex gap-3 rounded-md border border-sky-500/30 bg-sky-500/5 p-4 text-[13px] text-foreground">
                    <Info className="mt-0.5 size-4 shrink-0 text-sky-600" />
                    <div className="space-y-1">
                        <p>
                            Contoh: laporan <b>PLTD Poasia Containerized</b> — jabatan <b>Team Leader Pemeliharaan</b> — ditangani oleh pemegang jabatan di <b>PLTD Poasia</b>. Maka TL
                            Pemeliharaan PLTD Poasia menerima giliran verifikasi/persetujuan, namanya tercetak di Lembar Pengesahan, dan menerima notifikasinya.
                        </p>
                        <p className="text-muted-foreground">
                            Berlaku untuk laporan yang <b>diajukan setelah</b> pengaturan disimpan (laporan yang sedang berjalan tetap dengan penanda tangan saat diajukan). Unit tanpa
                            pengaturan tetap ditandatangani pegawai unitnya sendiri.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                    <h2 className="text-[15px] font-semibold text-foreground">Tambah / ubah pengaturan</h2>
                    <div className="grid gap-3 md:grid-cols-3">
                        <div>
                            <OperasiSelect label="Laporan unit" className="w-full" value={unitId} onChange={setUnitId} options={unitOptions} />
                            {errors.unit_id && <p className="mt-1 text-[12px] text-destructive">{errors.unit_id}</p>}
                        </div>
                        <div>
                            <OperasiSelect label="Jabatan" className="w-full" value={position} onChange={setPosition} options={positions} />
                            {errors.position && <p className="mt-1 text-[12px] text-destructive">{errors.position}</p>}
                        </div>
                        <div>
                            <OperasiSelect
                                label="Ditangani pemegang jabatan di unit"
                                className="w-full"
                                value={sourceUnitId}
                                onChange={setSourceUnitId}
                                options={unitOptions.filter((u) => u.value !== unitId)}
                            />
                            {errors.source_unit_id && <p className="mt-1 text-[12px] text-destructive">{errors.source_unit_id}</p>}
                        </div>
                    </div>

                    {(unitId || sourceUnitId) && (
                        <div className="flex flex-col gap-1.5 rounded-md bg-muted/50 p-3 text-[13px] sm:flex-row sm:items-center sm:gap-3">
                            <span>
                                Sekarang: <b>{currentSigner?.signer ?? '— belum ada pegawai'}</b>
                                {currentSigner?.source_unit && <span className="text-muted-foreground"> (dari {currentSigner.source_unit})</span>}
                            </span>
                            {sourceUnitId && (
                                <>
                                    <ArrowRight className="hidden size-4 text-muted-foreground sm:block" />
                                    <span>
                                        Menjadi:{' '}
                                        <b className={previewSigner ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'}>
                                            {previewSigner ?? `belum ada ${position} aktif di ${unitName[Number(sourceUnitId)]}`}
                                        </b>
                                    </span>
                                </>
                            )}
                        </div>
                    )}

                    <label className="flex cursor-pointer items-start gap-3 text-[13px]">
                        <Checkbox checked={grantAccess} onCheckedChange={(checked) => setGrantAccess(checked === true)} className="mt-0.5" />
                        <span>
                            <span className="font-medium text-foreground">Beri akun penanda tangan akses ke unit laporan</span>
                            <span className="block text-muted-foreground">
                                Akun mendapat role yang sama di unit tersebut agar bisa membuka & memverifikasi laporannya. Akses ini dicabut otomatis bila pengaturan dihapus.
                            </span>
                        </span>
                    </label>

                    <div className="flex justify-end">
                        <Button onClick={save} disabled={saving || !unitId || !sourceUnitId}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan pengaturan'}
                        </Button>
                    </div>
                </section>

                {/* Active delegations */}
                <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
                    <h2 className="flex items-center gap-2 text-[15px] font-semibold text-foreground">
                        <UserCheck className="size-4 text-primary" />
                        Pengaturan aktif
                        <span className="rounded-sm bg-muted px-1.5 text-[12px] tabular-nums font-medium text-muted-foreground">{delegations.length}</span>
                    </h2>
                    {delegations.length === 0 ? (
                        <p className="text-[13px] text-muted-foreground">Belum ada — semua laporan ditandatangani pegawai unitnya sendiri.</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border">
                            {delegations.map((d) => (
                                <li key={d.id} className="flex flex-col gap-2 py-2.5 sm:flex-row sm:items-center">
                                    <div className="min-w-0 flex-1 text-[13px]">
                                        <p className="font-semibold text-foreground">
                                            {d.unit} · {d.position}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Ditangani {d.position} {d.source_unit}:{' '}
                                            <b className={d.signer ? 'text-foreground' : 'text-amber-700 dark:text-amber-300'}>{d.signer ?? 'belum ada pegawai aktif'}</b>
                                            {d.granted > 0 && <span> · akses unit diberikan ({d.granted} role)</span>}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setUnitId(String(d.unit_id));
                                                setPosition(d.position);
                                                setSourceUnitId(String(d.source_unit_id));
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            }}
                                        >
                                            Ubah
                                        </Button>
                                        <ConfirmDeleteDialog
                                            action={reportSigners.destroy.form(d.id)}
                                            title="Hapus pengaturan penanda tangan?"
                                            description={`${d.position} laporan ${d.unit} kembali ditandatangani pegawai ${d.unit} sendiri${d.granted > 0 ? ', dan akses unit yang diberikan pengaturan ini dicabut' : ''}. Laporan yang sedang berjalan tidak berubah.`}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Overview */}
                <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <h2 className="text-[15px] font-semibold text-foreground">Penanda tangan per unit</h2>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden">
                                {POSITION_GROUPS.map((g) => (
                                    <button
                                        key={g.key}
                                        type="button"
                                        onClick={() => setGroup(g.key)}
                                        className={cn(
                                            'h-8 shrink-0 rounded-md border px-3 text-[12.5px] font-medium whitespace-nowrap',
                                            group === g.key ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-foreground',
                                        )}
                                    >
                                        {g.label}
                                    </button>
                                ))}
                            </div>
                            <span className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari unit…" className="h-8 w-full pl-8 sm:w-44" />
                            </span>
                        </div>
                    </div>
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full border-collapse text-[13px]" data-keep-table>
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-[12px] text-muted-foreground">
                                    <th className="sticky left-0 z-10 min-w-40 bg-muted px-3 py-2 font-semibold">Unit</th>
                                    {shownPositions.map((p) => (
                                        <th key={p.value} className="min-w-44 px-3 py-2 font-semibold">
                                            {p.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {shownUnits.map((unit) => (
                                    <tr key={unit.id} className="border-b border-border last:border-0">
                                        <td className="sticky left-0 z-10 bg-card px-3 py-2 font-medium text-foreground">{unit.name}</td>
                                        {shownPositions.map((p) => {
                                            const cell = cellsOf[unit.id]?.[p.value];

                                            return (
                                                <td key={p.value} className="px-3 py-2 align-top">
                                                    {cell?.signer ? (
                                                        <span className="text-foreground">{cell.signer}</span>
                                                    ) : (
                                                        <span className="text-[12px] font-medium text-amber-700 dark:text-amber-300">Belum ada</span>
                                                    )}
                                                    {cell?.source_unit && <span className="block text-[11.5px] text-sky-700 dark:text-sky-300">↳ dari {cell.source_unit}</span>}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

ReportSigners.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penanda Tangan Laporan', href: reportSigners.index() },
    ],
};
