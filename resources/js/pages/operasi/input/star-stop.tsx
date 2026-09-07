import { Form, Head, router } from '@inertiajs/react';
import StarStopController from '@/actions/App/Http/Controllers/Operasi/StarStopController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import starStop from '@/routes/operasi/input/star-stop';
import type { IdName, Tone } from '@/types';

type StatusCode = {
    id: number;
    code: string;
    label: string;
    category: string;
};

type LogRow = {
    id: number;
    report_date: string;
    status_code: string | null;
    status_label: string | null;
    category: string | null;
    operator_name: string | null;
    dispatcher_name: string | null;
    start_datetime: string | null;
    stop_datetime: string | null;
    duration_minutes: number | null;
    keterangan: string | null;
};

type Hours = {
    operasi: number;
    har: number;
    gangguan: number;
    standby: number;
    total: number;
};

type Props = {
    filters: {
        unit_id: number;
        engine_id: number | null;
        month: number;
        year: number;
    };
    logs: LogRow[];
    hours: Hours | null;
    engine: { id: number; name: string } | null;
    options: {
        units: IdName[];
        machines: IdName[];
        status_codes: StatusCode[];
        years: number[];
    };
    can_write: boolean;
};

const CATEGORY_TONE: Record<string, Tone> = {
    operasi: 'success',
    har: 'warning',
    gangguan: 'danger',
    standby: 'neutral',
};

const CATEGORY_LABEL: Record<string, string> = {
    operasi: 'Operasi',
    har: 'HAR',
    gangguan: 'Gangguan',
    standby: 'Standby',
};

const duration = (minutes: number | null) => {
    if (minutes === null) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    return `${hours}j ${mins}m`;
};

export default function StarStopInput({
    filters,
    logs,
    hours,
    engine,
    options,
    can_write,
}: Props) {
    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            starStop.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Input Operasi — Star-Stop" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Star-Stop Mesin"
                    description="Log start/stop mesin. Jam operasi, HAR, gangguan, dan standby dihitung otomatis dari durasi entri ini."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({ unit_id: Number(value), engine_id: null })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Mesin"
                        value={engine ? String(engine.id) : ''}
                        onChange={(value) => visit({ engine_id: Number(value) })}
                        options={options.machines.map((m) => ({
                            value: String(m.id),
                            label: m.name,
                        }))}
                    />
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
                </div>

                {engine === null ? (
                    <EmptyState
                        title="Belum ada mesin"
                        description="Unit ini belum punya mesin aktif. Tambahkan mesin dulu di Master Mesin."
                    />
                ) : (
                    <>
                        {hours && (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <HoursCard label="Operasi" value={hours.operasi} tone="success" />
                                <HoursCard label="HAR" value={hours.har} tone="warning" />
                                <HoursCard label="Gangguan" value={hours.gangguan} tone="danger" />
                                <HoursCard label="Standby" value={hours.standby} tone="neutral" />
                                <HoursCard label="Total Jam" value={hours.total} tone="info" />
                            </div>
                        )}

                        {can_write && (
                            <AddEntryForm
                                engineId={engine.id}
                                unitId={filters.unit_id}
                                statusCodes={options.status_codes}
                            />
                        )}

                        <div className="overflow-hidden rounded-md border border-border bg-card">
                            {logs.length === 0 ? (
                                <EmptyState
                                    title="Belum ada entri"
                                    description="Tambahkan entri start/stop untuk mesin ini pada periode terpilih."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Start</TableHead>
                                            <TableHead>Stop</TableHead>
                                            <TableHead className="text-right">Durasi</TableHead>
                                            <TableHead>Operator</TableHead>
                                            <TableHead>Keterangan</TableHead>
                                            {can_write && (
                                                <TableHead className="w-12" />
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell>{log.report_date}</TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        tone={
                                                            CATEGORY_TONE[
                                                                log.category ?? ''
                                                            ] ?? 'neutral'
                                                        }
                                                    >
                                                        {log.status_code}
                                                        {log.category
                                                            ? ` · ${CATEGORY_LABEL[log.category] ?? log.category}`
                                                            : ''}
                                                    </StatusBadge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {log.start_datetime ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {log.stop_datetime ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {duration(log.duration_minutes)}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {log.operator_name ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {log.keterangan ?? '—'}
                                                </TableCell>
                                                {can_write && (
                                                    <TableCell>
                                                        <ConfirmDeleteDialog
                                                            action={StarStopController.destroy.form(
                                                                log.id,
                                                            )}
                                                            title="Hapus entri?"
                                                            description="Entri Star-Stop ini akan dihapus permanen."
                                                        />
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

function AddEntryForm({
    engineId,
    unitId,
    statusCodes,
}: {
    engineId: number;
    unitId: number;
    statusCodes: StatusCode[];
}) {
    return (
        <Form
            {...StarStopController.store.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="rounded-md border border-border bg-card p-4"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="unit_id" value={unitId} />
                    <input type="hidden" name="engine_id" value={engineId} />
                    <h2 className="mb-3 text-base font-semibold text-foreground">
                        Tambah Entri Start/Stop
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                        <FormField label="Tanggal" htmlFor="report_date" required error={errors.report_date}>
                            <Input id="report_date" name="report_date" type="date" required />
                        </FormField>
                        <FormField label="Kode Status" required error={errors.status_code_id}>
                            <Select name="status_code_id">
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih kode status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusCodes.map((code) => (
                                        <SelectItem key={code.id} value={String(code.id)}>
                                            {code.code} · {code.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Start" htmlFor="start_datetime" required error={errors.start_datetime}>
                            <Input id="start_datetime" name="start_datetime" type="datetime-local" required />
                        </FormField>
                        <FormField label="Stop" htmlFor="stop_datetime" required error={errors.stop_datetime}>
                            <Input id="stop_datetime" name="stop_datetime" type="datetime-local" required />
                        </FormField>
                        <FormField label="Operator" htmlFor="operator_name" error={errors.operator_name}>
                            <Input id="operator_name" name="operator_name" autoComplete="off" />
                        </FormField>
                        <FormField label="Dispatcher" htmlFor="dispatcher_name" error={errors.dispatcher_name}>
                            <Input id="dispatcher_name" name="dispatcher_name" autoComplete="off" />
                        </FormField>
                        <FormField label="Keterangan" htmlFor="keterangan" error={errors.keterangan}>
                            <Input id="keterangan" name="keterangan" autoComplete="off" />
                        </FormField>
                        <div className="flex items-end">
                            <Button type="submit" disabled={processing}>
                                Tambah Entri
                            </Button>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

function HoursCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: Tone;
}) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <div className="flex items-center justify-between">
                <p className="text-[13px] text-muted-foreground">{label}</p>
                <StatusBadge tone={tone}>jam</StatusBadge>
            </div>
            <p className="mt-1 text-lg font-semibold tabular-nums">
                {value.toLocaleString('id-ID', { maximumFractionDigits: 2 })}
            </p>
        </div>
    );
}

StarStopInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Star-Stop', href: starStop.index() },
    ],
};
