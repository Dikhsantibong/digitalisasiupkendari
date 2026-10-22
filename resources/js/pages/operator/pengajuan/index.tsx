import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowRightLeft,
    CalendarCheck,
    CheckCircle2,
    FileText,
    Paperclip,
    Send,
    Undo2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import pengajuan from '@/routes/operator/pengajuan';
import type { IdName, Tone } from '@/types';

type RequestType = 'izin' | 'sakit' | 'cuti' | 'tukar_shift';

type Person = {
    id: number;
    name: string;
    regu: string | null;
    position?: string | null;
    /** Jadwal Shift codes, Y-m-d → code (this month and the next). */
    codes: Record<string, string>;
};

type AttendanceRequestRow = {
    id: number;
    type: RequestType;
    type_label: string;
    employee: { id: number; name: string; regu: string | null };
    substitute: { id: number; name: string; regu: string | null } | null;
    start_date: string;
    end_date: string;
    swap_date: string | null;
    days: number;
    reason: string;
    has_attachment: boolean;
    attachment_url: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    substitute_note: string | null;
    decided_by: string | null;
    decided_at: string | null;
    decision_note: string | null;
    created_at: string | null;
    applied: {
        employee_id: number;
        employee: string | null;
        date: string;
        from: string | null;
        to: string | null;
    }[];
    is_mine: boolean;
    can: {
        respond: boolean;
        approve: boolean;
        reject: boolean;
        cancel: boolean;
    };
};

type Props = {
    unit: IdName;
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
        types: { value: RequestType; label: string; code: string | null }[];
        max_days: number;
    };
    me: Person | null;
    colleagues: Person[];
    requests: AttendanceRequestRow[];
    can_submit: boolean;
    can_approve: boolean;
    sees_all: boolean;
    today: string;
};

/** "12 Agu 2026". */
const formatDate = (date: string) => {
    const [year, month, day] = date.split('-').map(Number);

    return `${day} ${OPERASI_MONTHS[month - 1].slice(0, 3)} ${year}`;
};

const isWorking = (code: string | undefined) =>
    code === 'P' || code === 'S' || code === 'M';

const SHIFT_LABEL: Record<string, string> = {
    P: 'Pagi',
    S: 'Sore',
    M: 'Malam',
    OFF: 'Libur',
};

const codeText = (code: string | undefined) =>
    code
        ? `${code}${SHIFT_LABEL[code] ? ` (${SHIFT_LABEL[code]})` : ''}`
        : 'belum dijadwalkan';

type Decision = {
    request: AttendanceRequestRow;
    action: 'approve' | 'reject' | 'cancel' | 'decline';
} | null;

/**
 * Pengajuan Absensi: izin, sakit (with the surat dokter), cuti and tukar
 * shift. A tukar shift is confirmed by the pengganti, then every request is
 * approved by the Koordinator Operasi / Project Leader, which updates the
 * Jadwal Shift. Card layout, the same on the phone and the desktop.
 */
export default function OperatorPengajuan({
    unit,
    filters,
    options,
    me,
    colleagues,
    requests,
    can_submit,
    can_approve,
    sees_all,
    today,
}: Props) {
    const form = useForm<{
        type: RequestType;
        start_date: string;
        end_date: string;
        reason: string;
        attachment: File | null;
        substitute_employee_id: string;
        swap_date: string;
    }>({
        type: 'izin',
        start_date: today,
        end_date: today,
        reason: '',
        attachment: null,
        substitute_employee_id: '',
        swap_date: '',
    });
    const [withSwap, setWithSwap] = useState(false);
    const [decision, setDecision] = useState<Decision>(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const tukar = form.data.type === 'tukar_shift';
    const myCode = me?.codes[form.data.start_date];
    const substitute = colleagues.find(
        (c) => String(c.id) === form.data.substitute_employee_id,
    );

    const visit = (patch: Partial<Props['filters']>) =>
        router.get(
            pengajuan.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            end_date: tukar ? data.start_date : data.end_date,
            substitute_employee_id: tukar ? data.substitute_employee_id : '',
            swap_date: tukar && withSwap ? data.swap_date : '',
        }));
        form.post(pengajuan.store().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset(
                    'reason',
                    'attachment',
                    'substitute_employee_id',
                    'swap_date',
                );
                setWithSwap(false);
            },
        });
    };

    const decide = () => {
        if (!decision) {
            return;
        }

        const { request, action } = decision;
        const url =
            action === 'approve'
                ? pengajuan.approve(request.id).url
                : action === 'reject'
                  ? pengajuan.reject(request.id).url
                  : action === 'cancel'
                    ? pengajuan.cancel(request.id).url
                    : pengajuan.respond(request.id).url;
        const data = action === 'decline' ? { accept: false, note } : { note };

        setProcessing(true);
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => {
                setDecision(null);
                setNote('');
            },
            onFinish: () => setProcessing(false),
        });
    };

    const accept = (request: AttendanceRequestRow) =>
        router.post(
            pengajuan.respond(request.id).url,
            { accept: true },
            { preserveScroll: true },
        );

    const actionable = requests.filter((r) => r.can.respond || r.can.approve);
    const others = requests.filter((r) => !(r.can.respond || r.can.approve));

    return (
        <>
            <Head title="Pengajuan Absensi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Pengajuan Absensi"
                    description="Ajukan izin, sakit, cuti, atau tukar shift. Tukar shift dikonfirmasi rekan pengganti, lalu semua pengajuan disetujui Koordinator Operasi / Project Leader — setelah disetujui, Jadwal Shift ikut berubah otomatis."
                />

                <Card className="grid grid-cols-2 gap-3 p-3 py-3 sm:flex sm:flex-row sm:flex-wrap sm:items-end">
                    {sees_all && options.units.length > 1 && (
                        <div className="col-span-2 sm:col-span-1">
                            <OperasiSelect
                                label="Unit"
                                value={String(filters.unit_id)}
                                onChange={(v) => visit({ unit_id: Number(v) })}
                                options={options.units.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                }))}
                                className="w-full sm:w-56"
                            />
                        </div>
                    )}
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) => visit({ month: Number(v) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                        className="w-full sm:w-40"
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) => visit({ year: Number(v) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                        className="w-full sm:w-28"
                    />
                    <p className="col-span-2 self-center text-[12px] text-muted-foreground sm:ml-auto">
                        {unit.name}
                    </p>
                </Card>

                {can_submit && me && (
                    <Card className="gap-0 p-0 py-0">
                        <form
                            onSubmit={submit}
                            className="flex flex-col gap-3 p-4"
                        >
                            <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Send className="size-4 text-primary" />
                                Buat Pengajuan
                            </div>
                            <ChoiceChips
                                options={options.types.map((t) => t.value)}
                                labels={Object.fromEntries(
                                    options.types.map((t) => [
                                        t.value,
                                        t.label,
                                    ]),
                                )}
                                value={form.data.type}
                                onChange={(value) =>
                                    value &&
                                    form.setData('type', value as RequestType)
                                }
                            />

                            {tukar ? (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 [&>*]:min-w-0">
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Tanggal shift yang ditukar
                                        <Input
                                            type="date"
                                            value={form.data.start_date}
                                            onChange={(e) =>
                                                form.setData(
                                                    'start_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <span
                                            className={cn(
                                                'text-[12px]',
                                                isWorking(myCode)
                                                    ? 'text-emerald-600'
                                                    : 'text-amber-600',
                                            )}
                                        >
                                            Jadwal Anda: {codeText(myCode)}
                                            {!isWorking(myCode) &&
                                                ' — pilih tanggal Anda bertugas.'}
                                        </span>
                                        <InputError
                                            message={form.errors.start_date}
                                        />
                                    </label>
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Rekan pengganti
                                        <select
                                            value={
                                                form.data.substitute_employee_id
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'substitute_employee_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9 w-full min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground"
                                        >
                                            <option value="">
                                                Pilih rekan…
                                            </option>
                                            {colleagues.map((c) => {
                                                const code =
                                                    c.codes[
                                                        form.data.start_date
                                                    ];

                                                return (
                                                    <option
                                                        key={c.id}
                                                        value={c.id}
                                                        disabled={isWorking(
                                                            code,
                                                        )}
                                                    >
                                                        {c.name}
                                                        {c.regu
                                                            ? ` · Regu ${c.regu}`
                                                            : ''}{' '}
                                                        —{' '}
                                                        {isWorking(code)
                                                            ? `bertugas ${code}`
                                                            : 'tersedia'}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                        <InputError
                                            message={
                                                form.errors
                                                    .substitute_employee_id
                                            }
                                        />
                                    </label>
                                    <div className="flex flex-col gap-2 sm:col-span-2">
                                        <label className="flex items-center gap-2 text-[13px] text-foreground">
                                            <input
                                                type="checkbox"
                                                checked={withSwap}
                                                onChange={(e) =>
                                                    setWithSwap(
                                                        e.target.checked,
                                                    )
                                                }
                                                className="size-4 accent-primary"
                                            />
                                            Saya membalas dengan menggantikan
                                            shift rekan di tanggal lain (tukar
                                            timbal balik)
                                        </label>
                                        {withSwap && (
                                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground sm:max-w-sm">
                                                Tanggal balasan
                                                <Input
                                                    type="date"
                                                    value={form.data.swap_date}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'swap_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {form.data.swap_date && (
                                                    <span className="text-[12px]">
                                                        Jadwal{' '}
                                                        {substitute?.name ??
                                                            'rekan'}
                                                        :{' '}
                                                        {codeText(
                                                            substitute?.codes[
                                                                form.data
                                                                    .swap_date
                                                            ],
                                                        )}{' '}
                                                        · Jadwal Anda:{' '}
                                                        {codeText(
                                                            me.codes[
                                                                form.data
                                                                    .swap_date
                                                            ],
                                                        )}
                                                    </span>
                                                )}
                                                <InputError
                                                    message={
                                                        form.errors.swap_date
                                                    }
                                                />
                                            </label>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-3 sm:max-w-md">
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Dari tanggal
                                        <Input
                                            type="date"
                                            value={form.data.start_date}
                                            onChange={(e) =>
                                                form.setData((d) => ({
                                                    ...d,
                                                    start_date: e.target.value,
                                                    end_date:
                                                        d.end_date <
                                                        e.target.value
                                                            ? e.target.value
                                                            : d.end_date,
                                                }))
                                            }
                                        />
                                        <InputError
                                            message={form.errors.start_date}
                                        />
                                    </label>
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Sampai tanggal
                                        <Input
                                            type="date"
                                            value={form.data.end_date}
                                            min={form.data.start_date}
                                            onChange={(e) =>
                                                form.setData(
                                                    'end_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.end_date}
                                        />
                                    </label>
                                    <p className="col-span-2 text-[12px] text-muted-foreground">
                                        Hari libur (OFF) di rentang ini tidak
                                        terpotong. Maksimal {options.max_days}{' '}
                                        hari.
                                    </p>
                                </div>
                            )}

                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                {tukar
                                    ? 'Alasan tukar shift'
                                    : 'Alasan / keterangan'}
                                <Textarea
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData('reason', e.target.value)
                                    }
                                    rows={2}
                                    placeholder={
                                        form.data.type === 'sakit'
                                            ? 'Mis. demam, dirawat di puskesmas…'
                                            : 'Tuliskan alasannya…'
                                    }
                                />
                                <InputError message={form.errors.reason} />
                            </label>

                            {!tukar && (
                                <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                    {form.data.type === 'sakit'
                                        ? 'Surat keterangan dokter (foto / PDF)'
                                        : 'Lampiran pendukung (opsional)'}
                                    <Input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        onChange={(e) =>
                                            form.setData(
                                                'attachment',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.attachment}
                                    />
                                </label>
                            )}

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        form.data.reason.trim().length < 3 ||
                                        (tukar &&
                                            (!form.data
                                                .substitute_employee_id ||
                                                !isWorking(myCode)))
                                    }
                                    className="w-full sm:w-auto"
                                >
                                    <Send className="size-4" />
                                    {form.processing
                                        ? 'Mengirim…'
                                        : 'Kirim Pengajuan'}
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}

                {!can_submit && !sees_all && (
                    <p className="rounded-md border border-dashed border-border p-4 text-center text-[13px] text-muted-foreground">
                        Akun Anda belum terdaftar di Jadwal Shift unit ini, jadi
                        belum dapat membuat pengajuan.
                    </p>
                )}

                {actionable.length > 0 && (
                    <section className="flex flex-col gap-2">
                        <h2 className="text-sm font-semibold text-foreground">
                            Perlu tindakan Anda ({actionable.length})
                        </h2>
                        {actionable.map((r) => (
                            <RequestCard
                                key={r.id}
                                request={r}
                                onAccept={accept}
                                onDecide={(action) =>
                                    setDecision({ request: r, action })
                                }
                            />
                        ))}
                    </section>
                )}

                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-semibold text-foreground">
                        {sees_all ? 'Pengajuan unit' : 'Pengajuan saya'} —{' '}
                        {OPERASI_MONTHS[filters.month - 1]} {filters.year}
                    </h2>
                    {others.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                            Belum ada pengajuan pada periode ini.
                        </p>
                    ) : (
                        others.map((r) => (
                            <RequestCard
                                key={r.id}
                                request={r}
                                onAccept={accept}
                                onDecide={(action) =>
                                    setDecision({ request: r, action })
                                }
                            />
                        ))
                    )}
                </section>

                {can_approve && (
                    <p className="text-[12px] text-muted-foreground">
                        Anda dapat menyetujui pengajuan di unit ini. Pengajuan
                        Anda sendiri disetujui oleh atasan lain.
                    </p>
                )}
            </div>

            <Dialog
                open={decision !== null}
                onOpenChange={(open) => !open && setDecision(null)}
            >
                <DialogContent>
                    {decision && (
                        <>
                            <DialogTitle>
                                {decision.action === 'approve' &&
                                    'Setujui pengajuan'}
                                {decision.action === 'reject' &&
                                    'Tolak pengajuan'}
                                {decision.action === 'decline' &&
                                    'Tolak permintaan tukar shift'}
                                {decision.action === 'cancel' &&
                                    (decision.request.status === 'disetujui'
                                        ? 'Batalkan persetujuan'
                                        : 'Batalkan pengajuan')}
                            </DialogTitle>
                            <DialogDescription>
                                {decision.request.type_label}{' '}
                                {decision.request.employee.name} ·{' '}
                                {formatDate(decision.request.start_date)}
                                {decision.request.end_date !==
                                    decision.request.start_date &&
                                    ` – ${formatDate(decision.request.end_date)}`}
                                {decision.action === 'approve' &&
                                    ' — Jadwal Shift akan diperbarui otomatis.'}
                                {decision.action === 'cancel' &&
                                    decision.request.status === 'disetujui' &&
                                    ' — kode Jadwal Shift dikembalikan seperti sebelum disetujui.'}
                            </DialogDescription>
                            {decision.action !== 'cancel' && (
                                <label className="flex flex-col gap-1 text-[13px]">
                                    <span className="font-medium">
                                        Catatan
                                        {decision.action === 'reject' ? (
                                            ''
                                        ) : (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                (opsional)
                                            </span>
                                        )}
                                    </span>
                                    <Textarea
                                        value={note}
                                        onChange={(e) =>
                                            setNote(e.target.value)
                                        }
                                        rows={3}
                                    />
                                </label>
                            )}
                            <DialogFooter className="gap-2">
                                <Button
                                    variant="secondary"
                                    onClick={() => setDecision(null)}
                                >
                                    Kembali
                                </Button>
                                <Button
                                    variant={
                                        decision.action === 'approve'
                                            ? 'default'
                                            : 'destructive'
                                    }
                                    onClick={decide}
                                    disabled={
                                        processing ||
                                        (decision.action === 'reject' &&
                                            note.trim().length < 3)
                                    }
                                >
                                    {processing
                                        ? 'Memproses…'
                                        : decision.action === 'approve'
                                          ? 'Setujui'
                                          : decision.action === 'cancel'
                                            ? 'Batalkan'
                                            : 'Tolak'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function RequestCard({
    request: r,
    onAccept,
    onDecide,
}: {
    request: AttendanceRequestRow;
    onAccept: (request: AttendanceRequestRow) => void;
    onDecide: (action: 'approve' | 'reject' | 'cancel' | 'decline') => void;
}) {
    const tukar = r.type === 'tukar_shift';

    return (
        <Card className="gap-2 p-3 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex min-w-0 items-start gap-2">
                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        {tukar ? (
                            <ArrowRightLeft className="size-4" />
                        ) : (
                            <CalendarCheck className="size-4" />
                        )}
                    </span>
                    <div className="min-w-0">
                        <div className="text-[14px] font-semibold text-foreground">
                            {r.type_label} · {r.employee.name}
                            {r.employee.regu && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (Regu {r.employee.regu})
                                </span>
                            )}
                        </div>
                        <div className="text-[12px] text-muted-foreground">
                            {formatDate(r.start_date)}
                            {r.end_date !== r.start_date &&
                                ` – ${formatDate(r.end_date)} (${r.days} hari)`}
                            {tukar &&
                                r.substitute &&
                                ` · digantikan ${r.substitute.name}`}
                            {tukar &&
                                r.swap_date &&
                                ` · dibalas ${formatDate(r.swap_date)}`}
                        </div>
                    </div>
                </div>
                <StatusBadge tone={r.status_tone}>{r.status_label}</StatusBadge>
            </div>

            <p className="text-[13px] text-foreground">{r.reason}</p>

            {r.attachment_url && (
                <a
                    href={r.attachment_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 self-start text-[12px] text-primary hover:underline"
                >
                    <Paperclip className="size-3.5" />
                    Lihat lampiran
                </a>
            )}

            {(r.substitute_note || r.decision_note || r.decided_by) && (
                <div className="rounded-md bg-muted/50 p-2 text-[12px] text-muted-foreground">
                    {r.substitute_note && (
                        <div>Pengganti: {r.substitute_note}</div>
                    )}
                    {r.decided_by && (
                        <div>
                            Diputuskan {r.decided_by}
                            {r.decided_at && ` · ${r.decided_at}`}
                            {r.decision_note && ` — ${r.decision_note}`}
                        </div>
                    )}
                </div>
            )}

            {r.applied.length > 0 && r.status === 'disetujui' && (
                <div className="flex flex-wrap gap-1.5 text-[11.5px]">
                    <FileText className="size-3.5 text-muted-foreground" />
                    {r.applied.map((cell, i) => (
                        <span
                            key={i}
                            className="rounded bg-emerald-500/10 px-1.5 py-0.5 text-emerald-700 dark:text-emerald-400"
                        >
                            {cell.employee} {formatDate(cell.date)}:{' '}
                            {cell.from ?? '–'} → {cell.to}
                        </span>
                    ))}
                </div>
            )}

            {(r.can.respond ||
                r.can.approve ||
                r.can.reject ||
                r.can.cancel) && (
                <div className="flex flex-wrap justify-end gap-2 pt-1">
                    {r.can.respond && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => onDecide('decline')}
                            >
                                <XCircle className="size-4" />
                                Tidak bersedia
                            </Button>
                            <Button size="sm" onClick={() => onAccept(r)}>
                                <CheckCircle2 className="size-4" />
                                Bersedia menggantikan
                            </Button>
                        </>
                    )}
                    {r.can.cancel && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => onDecide('cancel')}
                        >
                            <Undo2 className="size-4" />
                            {r.status === 'disetujui'
                                ? 'Batalkan persetujuan'
                                : 'Batalkan'}
                        </Button>
                    )}
                    {r.can.reject && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => onDecide('reject')}
                        >
                            <XCircle className="size-4" />
                            Tolak
                        </Button>
                    )}
                    {r.can.approve && (
                        <Button size="sm" onClick={() => onDecide('approve')}>
                            <CheckCircle2 className="size-4" />
                            Setujui
                        </Button>
                    )}
                </div>
            )}
        </Card>
    );
}

OperatorPengajuan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengajuan Absensi', href: pengajuan.index() },
    ],
};
