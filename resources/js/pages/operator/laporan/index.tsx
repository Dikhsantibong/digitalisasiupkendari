import { Head, router } from '@inertiajs/react';
import { CalendarRange, FilePenLine } from 'lucide-react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import laporan from '@/routes/operator/laporan';
import absensiDoc from '@/routes/operator/laporan/absensi';
import logsheetDoc from '@/routes/operator/laporan/logsheet';
import type { IdName } from '@/types';

type Filters = {
    unit_id: number;
    engine_id: number | null;
    log_date: string;
    month: number;
    year: number;
    group_type: string;
};

type Props = {
    filters: Filters;
    options: {
        units: IdName[];
        machines: IdName[];
        years: number[];
        group_types: { value: string; label: string }[];
    };
};

export default function OperatorLaporanIndex({ filters, options }: Props) {
    const visit = (patch: Partial<Filters>) => {
        router.get(laporan.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const noEngine = filters.engine_id === null;

    const openLogsheet = () => {
        router.get(
            logsheetDoc.edit({
                query: { unit_id: filters.unit_id, engine_id: filters.engine_id ?? undefined, log_date: filters.log_date },
            }).url,
        );
    };

    const openAbsensi = () => {
        router.get(
            absensiDoc.edit({
                query: { unit_id: filters.unit_id, month: filters.month, year: filters.year, group_type: filters.group_type },
            }).url,
        );
    };

    return (
        <>
            <Head title="Laporan Operator" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Operator"
                    description="Dokumen laporan modul Operator — logsheet harian dan absensi/jadwal shift. Buka untuk dilihat, diedit (teks/Excel), dan dicetak PDF."
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Logsheet report */}
                    <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">Laporan Logsheet Harian</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Logsheet mesin per jam (pembacaan parameter, bearing generator, ringkasan) — per mesin per hari.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-background p-3">
                            <OperasiSelect
                                label="Unit"
                                value={String(filters.unit_id)}
                                onChange={(value) => visit({ unit_id: Number(value), engine_id: null })}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                            />
                            <OperasiSelect
                                label="Mesin"
                                value={filters.engine_id ? String(filters.engine_id) : ''}
                                onChange={(value) => visit({ engine_id: Number(value) })}
                                options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))}
                            />
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="text-muted-foreground">Hari/Tanggal</span>
                                <Input type="date" value={filters.log_date} onChange={(e) => visit({ log_date: e.target.value })} className="w-40" />
                            </label>
                        </div>
                        {noEngine ? (
                            <p className="text-[13px] text-amber-600">Unit ini belum punya mesin aktif.</p>
                        ) : (
                            <Button className="self-start" onClick={openLogsheet}>
                                <FilePenLine className="size-4" />
                                Buka Dokumen (Lihat, Edit &amp; Cetak)
                            </Button>
                        )}
                    </div>

                    {/* Absensi report */}
                    <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">Laporan Absensi &amp; Jadwal Shift</h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Jadwal & kehadiran shift pegawai (kalender bulanan, rekap kode, % kehadiran) — per unit per bulan.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-background p-3">
                            <OperasiSelect
                                label="Unit"
                                value={String(filters.unit_id)}
                                onChange={(value) => visit({ unit_id: Number(value), engine_id: null })}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                            />
                            <OperasiSelect
                                label="Kelompok"
                                value={filters.group_type}
                                onChange={(value) => visit({ group_type: value })}
                                options={options.group_types}
                            />
                            <OperasiSelect
                                label="Bulan"
                                value={String(filters.month)}
                                onChange={(value) => visit({ month: Number(value) })}
                                options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                            />
                            <OperasiSelect
                                label="Tahun"
                                value={String(filters.year)}
                                onChange={(value) => visit({ year: Number(value) })}
                                options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                            />
                        </div>
                        <Button className="self-start" onClick={openAbsensi}>
                            <CalendarRange className="size-4" />
                            Buka Dokumen (Lihat, Edit &amp; Cetak)
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

OperatorLaporanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Laporan Operator', href: laporan.index() },
    ],
};
