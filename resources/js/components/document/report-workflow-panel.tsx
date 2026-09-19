import { router } from '@inertiajs/react';
import { BadgeCheck, CheckCircle2, ChevronDown, Circle, History, Send, ShieldCheck, Stamp, XCircle } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import reportWorkflow from '@/routes/report-workflow';
import type { Tone } from '@/types';

export type ReportWorkflowStep = {
    stage: 'pengesahan' | 'tanda_tangan';
    sequence: number;
    caption: string;
    position: string;
    name: string | null;
    signed: boolean;
    signed_at: string | null;
    signed_by: string | null;
    current: boolean;
};

export type ReportWorkflowState = {
    module: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    editable: boolean;
    submitted: { by: string | null; at: string } | null;
    verification: { by: string | null; at: string; note: string | null } | null;
    rejection: { by: string | null; at: string; reason: string | null; active: boolean } | null;
    steps: ReportWorkflowStep[];
    logs: { id: number; user: string | null; jabatan: string | null; action: string; from: string | null; to: string; note: string | null; at: string | null }[];
    /** What the backend will accept from the current user — the only source for the buttons. */
    can: { submit: boolean; verify: boolean; approve: boolean; ratify: boolean; reject: boolean };
};

type Action = 'submit' | 'verify' | 'approve' | 'ratify' | 'reject';

const ACTION_LABELS: Record<string, string> = {
    ajukan: 'Ajukan',
    ajukan_kembali: 'Ajukan Kembali',
    verifikasi: 'Verifikasi',
    tolak: 'Tolak',
    setujui: 'Setujui',
    sahkan: 'Sahkan',
    final: 'Final',
    tanda_tangan: 'Tanda Tangani',
};

const formatDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

/**
 * Status and approval chain of a Laporan Pembangkit — Koordinator divisi
 * memeriksa → Team Leader sesuai modul menyetujui → Manager UL mengesahkan —
 * with the actions the current user may take. The buttons come from the backend
 * (`can`), which validates every action again.
 */
export function ReportWorkflowPanel({ workflow, target }: { workflow: ReportWorkflowState; target: Record<string, string | number> }) {
    const [action, setAction] = useState<Action | null>(null);
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const { can } = workflow;
    const resubmit = workflow.status === 'ditolak';

    const chain = workflow.steps.filter((step) => step.stage === 'pengesahan');
    const documentSigners = workflow.steps.filter((step) => step.stage === 'tanda_tangan');
    const tlPosition = chain.find((s) => s.sequence === 2)?.position ?? 'Team Leader';

    const dialogs: Record<Action, { title: string; description: string; confirm: string; field: 'note' | 'reason'; required: boolean }> = {
        submit: {
            title: resubmit ? 'Ajukan Kembali Laporan' : 'Ajukan Laporan',
            description: 'Laporan versi tersimpan akan dikirim untuk diverifikasi dan tidak dapat diubah selama proses berlangsung. Pastikan dokumen sudah disimpan.',
            confirm: resubmit ? 'Ajukan Kembali' : 'Ajukan',
            field: 'note',
            required: false,
        },
        verify: {
            title: 'Verifikasi Laporan',
            description: `Sebagai Koordinator, Anda menyatakan isi laporan telah diperiksa. Laporan diteruskan ke ${tlPosition} untuk disetujui.`,
            confirm: 'Verifikasi',
            field: 'note',
            required: false,
        },
        approve: {
            title: 'Setujui Laporan',
            description: `Sebagai ${tlPosition}, Anda menyetujui laporan yang telah diperiksa Koordinator. Laporan diteruskan ke Manager UL untuk disahkan.`,
            confirm: 'Setujui',
            field: 'note',
            required: false,
        },
        ratify: {
            title: 'Sahkan Laporan',
            description: 'Sebagai Manager UL, Anda memberikan pengesahan terakhir. Laporan menjadi FINAL dan tidak dapat diubah lagi.',
            confirm: 'Sahkan',
            field: 'note',
            required: false,
        },
        reject: {
            title: 'Tolak Laporan',
            description: 'Laporan dikembalikan ke pembuat untuk diperbaiki. Tuliskan alasan penolakan.',
            confirm: 'Tolak',
            field: 'reason',
            required: true,
        },
    };

    const open = (next: Action) => {
        setAction(next);
        setNote('');
        setErrors({});
    };

    const submit = () => {
        if (!action) {
            return;
        }

        const dialog = dialogs[action];
        setProcessing(true);
        router.post(
            reportWorkflow[action](workflow.module).url,
            { ...target, [dialog.field]: note || null },
            {
                preserveScroll: true,
                onSuccess: () => setAction(null),
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const dialog = action ? dialogs[action] : null;
    const errorText = errors.workflow ?? errors.reason ?? errors.note ?? errors.unit_id;

    return (
        <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 text-[13px]">
            <div className="flex flex-wrap items-center gap-2">
                <ShieldCheck className="size-4 text-primary" />
                <span className="font-medium">Status Laporan:</span>
                <StatusBadge tone={workflow.status_tone}>{workflow.status_label}</StatusBadge>
                {!workflow.editable && <span className="text-muted-foreground">Dokumen dikunci selama proses verifikasi & pengesahan.</span>}
                <div className="ml-auto flex flex-wrap gap-2">
                    {can.submit && (
                        <Button size="sm" onClick={() => open('submit')}>
                            <Send className="size-4" />
                            {resubmit ? 'Ajukan Kembali' : 'Ajukan'}
                        </Button>
                    )}
                    {can.verify && (
                        <Button size="sm" onClick={() => open('verify')}>
                            <CheckCircle2 className="size-4" />
                            Verifikasi
                        </Button>
                    )}
                    {can.approve && (
                        <Button size="sm" onClick={() => open('approve')}>
                            <BadgeCheck className="size-4" />
                            Setujui
                        </Button>
                    )}
                    {can.ratify && (
                        <Button size="sm" onClick={() => open('ratify')}>
                            <Stamp className="size-4" />
                            Sahkan
                        </Button>
                    )}
                    {can.reject && (
                        <Button size="sm" variant="destructive" onClick={() => open('reject')}>
                            <XCircle className="size-4" />
                            Tolak
                        </Button>
                    )}
                </div>
            </div>

            {workflow.rejection?.active && (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-2.5">
                    <div className="font-medium text-destructive">Laporan ditolak — perlu perbaikan</div>
                    <div className="mt-0.5">{workflow.rejection.reason}</div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        Oleh {workflow.rejection.by ?? '-'} · {formatDateTime(workflow.rejection.at)}. Perbaiki dokumen, simpan, lalu ajukan kembali.
                    </div>
                </div>
            )}

            <div className="grid gap-3 md:grid-cols-3">
                <div className="rounded-md border border-border p-2.5">
                    <div className="mb-1 font-medium">Pengajuan & Verifikasi</div>
                    <dl className="grid grid-cols-[88px_1fr] gap-x-2 gap-y-0.5 text-xs">
                        <dt className="text-muted-foreground">Diajukan</dt>
                        <dd>{workflow.submitted ? `${workflow.submitted.by ?? '-'} · ${formatDateTime(workflow.submitted.at)}` : 'Belum'}</dd>
                        <dt className="text-muted-foreground">Diverifikasi</dt>
                        <dd>{workflow.verification ? `${workflow.verification.by ?? '-'} · ${formatDateTime(workflow.verification.at)}` : 'Belum'}</dd>
                        <dt className="text-muted-foreground">Catatan</dt>
                        <dd>{workflow.verification?.note || '-'}</dd>
                    </dl>
                </div>
                <div className="rounded-md border border-border p-2.5">
                    <div className="mb-1 font-medium">Pemeriksaan → Persetujuan → Pengesahan</div>
                    <ol className="flex flex-col gap-1 text-xs">
                        {chain.map((step) => (
                            <li key={`${step.stage}-${step.sequence}`} className={`flex items-start gap-1.5 rounded px-1 py-0.5 ${step.current ? 'bg-primary/10' : ''}`}>
                                {step.signed ? <CheckCircle2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600" /> : <Circle className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />}
                                <div className="min-w-0">
                                    <div>
                                        <span className="font-medium">
                                            {step.sequence}. {step.caption}
                                        </span>{' '}
                                        — {step.position}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {step.name ?? <span className="text-destructive">Belum ada pegawai dengan jabatan ini</span>}
                                        {' · '}
                                        {step.signed ? `Sudah (${formatDateTime(step.signed_at)})` : step.current ? 'Menunggu' : 'Belum'}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
                <div className="rounded-md border border-border p-2.5">
                    <div className="mb-1 font-medium">Tanda Tangan Dokumen</div>
                    <ul className="flex flex-col gap-1 text-xs">
                        {documentSigners.map((step) => (
                            <li key={`${step.stage}-${step.sequence}`}>
                                <span className="font-medium">{step.caption}</span> — {step.position}
                                <div className="text-muted-foreground">{step.name ?? <span className="text-destructive">Belum ada pegawai dengan jabatan ini</span>}</div>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-1.5 text-[11px] text-muted-foreground">Tercetak di laporan setelah disahkan Manager UL (FINAL).</p>
                </div>
            </div>

            {workflow.logs.length > 0 && (
                <Collapsible>
                    <CollapsibleTrigger className="group flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                        <History className="size-3.5" />
                        Riwayat proses ({workflow.logs.length})
                        <ChevronDown className="size-3.5 transition-transform group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="mt-2 overflow-x-auto">
                            <table className="w-full min-w-[640px] border-collapse text-xs">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="border border-border px-2 py-1">Waktu</th>
                                        <th className="border border-border px-2 py-1">Pengguna</th>
                                        <th className="border border-border px-2 py-1">Jabatan</th>
                                        <th className="border border-border px-2 py-1">Tindakan</th>
                                        <th className="border border-border px-2 py-1">Status</th>
                                        <th className="border border-border px-2 py-1">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {workflow.logs.map((log) => (
                                        <tr key={log.id}>
                                            <td className="border border-border px-2 py-1 whitespace-nowrap">{formatDateTime(log.at)}</td>
                                            <td className="border border-border px-2 py-1">{log.user ?? '-'}</td>
                                            <td className="border border-border px-2 py-1">{log.jabatan ?? '-'}</td>
                                            <td className="border border-border px-2 py-1">{ACTION_LABELS[log.action] ?? log.action}</td>
                                            <td className="border border-border px-2 py-1">
                                                {log.from ?? '-'} → {log.to}
                                            </td>
                                            <td className="border border-border px-2 py-1">{log.note ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            )}

            <Dialog open={action !== null} onOpenChange={(value) => !value && setAction(null)}>
                <DialogContent>
                    {dialog && (
                        <>
                            <DialogTitle>{dialog.title}</DialogTitle>
                            <DialogDescription>{dialog.description}</DialogDescription>
                            <label className="flex flex-col gap-1 text-[13px]">
                                <span className="font-medium">
                                    {dialog.field === 'reason' ? 'Alasan penolakan' : 'Catatan'}
                                    {!dialog.required && <span className="font-normal text-muted-foreground"> (opsional)</span>}
                                </span>
                                <textarea
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    rows={4}
                                    className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                            </label>
                            {errorText && <p className="text-[13px] text-destructive">{errorText}</p>}
                            <DialogFooter className="gap-2">
                                <Button variant="secondary" type="button" onClick={() => setAction(null)}>
                                    Batal
                                </Button>
                                <Button
                                    variant={action === 'reject' ? 'destructive' : 'default'}
                                    onClick={submit}
                                    disabled={processing || (dialog.required && note.trim().length < 5)}
                                >
                                    {processing ? 'Memproses…' : dialog.confirm}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </section>
    );
}
