import { router } from '@inertiajs/react';
import {
    Building2,
    Briefcase,
    CheckCircle2,
    PenLine,
    User,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { SignaturePad } from '@/components/signature-pad';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Fields = { nama: string; asal: string; jabatan: string };

type Errors = Partial<Record<keyof Fields | 'ttd', string>>;

/**
 * Daily Meeting attendance form: nama, asal perusahaan, jabatan and a canvas
 * signature. Used by the public QR page (/hadir/{token}) and by the organiser
 * for an attendee without a phone. Posts {nama, asal, jabatan, ttd (PNG data URL)}.
 */
export function MeetingAttendanceForm({
    action,
    submitLabel = 'Kirim Absensi',
    onSuccess,
}: {
    action: string;
    submitLabel?: string;
    onSuccess?: () => void;
}) {
    const [fields, setFields] = useState<Fields>({
        nama: '',
        asal: '',
        jabatan: '',
    });
    const [ttd, setTtd] = useState<string | null>(null);
    const [padKey, setPadKey] = useState(0);
    const [errors, setErrors] = useState<Errors>({});
    const [sending, setSending] = useState(false);

    const set = (key: keyof Fields, value: string) => {
        setFields((prev) => ({ ...prev, [key]: value }));
        setErrors((prev) => ({ ...prev, [key]: undefined }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const local: Errors = {};

        if (!fields.nama.trim()) {
            local.nama = 'Nama wajib diisi.';
        }

        if (!fields.asal.trim()) {
            local.asal = 'Asal perusahaan wajib diisi.';
        }

        if (!fields.jabatan.trim()) {
            local.jabatan = 'Jabatan wajib diisi.';
        }

        if (!ttd) {
            local.ttd = 'Silakan buat tanda tangan pada kotak di atas.';
        }

        if (Object.keys(local).length > 0) {
            setErrors(local);

            return;
        }

        setSending(true);
        router.post(
            action,
            { ...fields, ttd },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setFields({ nama: '', asal: '', jabatan: '' });
                    setTtd(null);
                    setPadKey((k) => k + 1);
                    onSuccess?.();
                },
                onError: (errs) => setErrors(errs as Errors),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
            <Field
                icon={<User className="size-4" />}
                label="Nama lengkap"
                error={errors.nama}
            >
                <Input
                    value={fields.nama}
                    onChange={(e) => set('nama', e.target.value)}
                    placeholder="mis. Amirullah"
                    autoComplete="name"
                    className="h-11 text-base"
                    aria-invalid={!!errors.nama}
                />
            </Field>
            <Field
                icon={<Building2 className="size-4" />}
                label="Asal perusahaan"
                error={errors.asal}
            >
                <Input
                    value={fields.asal}
                    onChange={(e) => set('asal', e.target.value)}
                    placeholder="mis. PT MKP"
                    autoComplete="organization"
                    className="h-11 text-base"
                    aria-invalid={!!errors.asal}
                />
            </Field>
            <Field
                icon={<Briefcase className="size-4" />}
                label="Jabatan"
                error={errors.jabatan}
            >
                <Input
                    value={fields.jabatan}
                    onChange={(e) => set('jabatan', e.target.value)}
                    placeholder="mis. Mekanik"
                    autoComplete="organization-title"
                    className="h-11 text-base"
                    aria-invalid={!!errors.jabatan}
                />
            </Field>
            <div className="flex flex-col gap-1.5">
                <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                    <PenLine className="size-4 text-muted-foreground" />
                    Tanda tangan
                </span>
                <SignaturePad
                    key={padKey}
                    onChange={(value) => {
                        setTtd(value);
                        setErrors((prev) => ({ ...prev, ttd: undefined }));
                    }}
                />
                {errors.ttd && (
                    <p className="text-sm text-destructive">{errors.ttd}</p>
                )}
            </div>
            <Button
                type="submit"
                disabled={sending}
                className="h-12 gap-2 text-base"
            >
                <CheckCircle2 className="size-5" />
                {sending ? 'Mengirim…' : submitLabel}
            </Button>
        </form>
    );
}

function Field({
    icon,
    label,
    error,
    children,
}: {
    icon: ReactNode;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                <span className="text-muted-foreground">{icon}</span>
                {label}
            </span>
            {children}
            {error && <span className="text-sm text-destructive">{error}</span>}
        </label>
    );
}
