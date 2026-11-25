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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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
    employee: {
        id: number;
        name: string;
        regu: string | null;
        position?: string | null;
    };
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
    izin_used_months?: string[];
    today: string;
};

/** "12 Agu 2026". */
const formatDate = (date: string) => {
    const [year, month, day] = date.split('-').map(Number);

    return `${day} ${OPERASI_MONTHS[month - 1].slice(0, 3)} ${year}`;
};

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

const monthsInRange = (start: string, end: string): string[] => {
    if (!start) {
        return [];
    }

    const [sy, sm] = start.split('-').map(Number);
    const [ey, em] = (end && end >= start ? end : start).split('-').map(Number);

    if (!sy || !sm || !ey || !em) {
        return [];
    }

    const months: string[] = [];
    let year = sy;
    let month = sm;

    while (year < ey || (year === ey && month <= em)) {
        months.push(`${year}-${String(month).padStart(2, '0')}`);
        month += 1;

        if (month > 12) {
            month = 1;
            year += 1;
        }

        if (months.length >= 12) {
            break;
        }
    }

    return months;
};

type Decision = {
    request: AttendanceRequestRow;
    action: 'approve' | 'reject' | 'cancel' | 'decline';
} | null;

/**
 * Pengajuan Absensi: izin, sakit (with the surat dokter), cuti and tukar
 * shift. A tukar shift is confirmed by the pengganti, then operator requests
 * are approved by TL Operasi / Project Leader / Koordinator Operasi and HAR
 * requests (Harmes / Harlist / ASME) by TL Pemeliharaan, which updates the
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
    izin_used_months = [],
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
    const [decision, setDecision] = useState<Decision>(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const tukar = form.data.type === 'tukar_shift';
    const izinQuotaUsed =
        form.data.type === 'izin' &&
        monthsInRange(form.data.start_date, form.data.end_date).some((m) =>
            izin_used_months.includes(m),
        );
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
            swap_date: '',
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
                    description="Ajukan izin, sakit, cuti, atau tukar shift. Tukar shift dikonfirmasi rekan pengganti, lalu pengajuan operator disetujui TL Operasi (atau Project Leader / Koordinator Operasi) dan pengajuan HAR disetujui TL Pemeliharaan — setelah disetujui, Jadwal Shift ikut berubah otomatis."
                />

                <Card className="grid grid-cols-2 gap-3 p-3 py-3 sm:flex sm:flex-row sm:flex-wrap sm:items-end">
                    {sees_all && options.units.length > 1 && (
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(v) => visit({ unit_id: Number(v) })}
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            className="w-full sm:w-56"
                            labelClassName="col-span-2 sm:col-span-1"
                        />
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
                            <InputError message={form.errors.type} />
                            {izinQuotaUsed && (
                                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[12px] text-amber-700 dark:text-amber-300">
                                    Pengajuan izin hanya dapat dilakukan 1 kali
                                    per bulan. Kuota izin pada bulan yang
                                    dipilih sudah terpakai (menunggu atau
                                    disetujui).
                                </p>
                            )}

                            {tukar ? (
                                // Simplified tukar shift (user, 2026-11-24): pick the date and the colleague;
                                // the two shifts that day are exchanged after the colleague and the TL agree.
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 [&>*]:min-w-0">
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Tanggal
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
                                        <span className="text-[12px]">
                                            Jadwal Anda: {codeText(myCode)}
                                        </span>
                                        <InputError
                                            message={form.errors.start_date}
                                        />
                                    </label>
                                    <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                        Tukar dengan
                                        <Select
                                            value={
                                                form.data.substitute_employee_id
                                                    ? String(
                                                          form.data
                                                              .substitute_employee_id,
                                                      )
                                                    : undefined
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'substitute_employee_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className="h-10 w-full min-w-0 text-sm text-foreground"
                                                aria-label="Tukar dengan"
                                            >
                                                <SelectValue placeholder="Pilih rekan…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {colleagues.map((c) => (
                                                    <SelectItem
                                                        key={c.id}
                                                        value={String(c.id)}
                                                    >
                                                        {c.name}
                                                        {c.regu
                                                            ? ` · Regu ${c.regu}`
                                                            : ''}{' '}
                                                        —{' '}
                                                        {codeText(
                                                            c.codes[
                                                                form.data
                                                                    .start_date
                                                            ],
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                form.errors
                                                    .substitute_employee_id
                                            }
                                        />
                                    </label>
                                    {substitute && (
                                        <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-[12.5px] text-foreground sm:col-span-2">
                                            Setelah disetujui: Anda{' '}
                                            <b>
                                                {codeText(myCode)} →{' '}
                                                {codeText(
                                                    substitute.codes[
                                                        form.data.start_date
                                                    ],
                                                )}
                                            </b>
                                            , {substitute.name}{' '}
                                            <b>
                                                {codeText(
                                                    substitute.codes[
                                                        form.data.start_date
                                                    ],
                                                )}{' '}
                                                → {codeText(myCode)}
                                            </b>
                                            . Pengajuan dikirim ke{' '}
                                            {substitute.name} untuk
                                            dikonfirmasi, lalu disetujui TL.
                                        </p>
                                    )}
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
                                    ? 'Catatan (opsional)'
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
                                        izinQuotaUsed ||
                                        (tukar
                                            ? !form.data.substitute_employee_id
                                            : form.data.reason.trim().length <
                                              3)
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
                            {r.employee.regu ? (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (Regu {r.employee.regu})
                                </span>
                            ) : (
                                r.employee.position && (
                                    <span className="font-normal text-muted-foreground">
                                        {' '}
                                        ({r.employee.position})
                                    </span>
                                )
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
