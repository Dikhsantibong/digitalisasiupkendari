import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    IdCard,
    KeyRound,
    Lock,
    Mail,
    PenLine,
    Trash2,
    Upload,
    UserCheck,
} from 'lucide-react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SignaturePad } from '@/components/signature-pad';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInMobileShell } from '@/hooks/use-mobile-module';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type AccountInfo = {
    name: string;
    nip: string;
    position: string;
    division?: string | null;
    unit?: string | null;
    service_unit?: string | null;
    regu?: string | null;
    roles: string[];
    signature_url?: string | null;
    has_signature: boolean;
};

type PageProps = {
    auth: Auth;
};

function ReadOnlyField({
    label,
    value,
}: {
    label: string;
    value?: string | null;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label className="text-xs font-medium text-muted-foreground">
                    {label}
                </Label>
                <span className="inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground/80">
                    <Lock className="size-2.5" />
                    Read-only
                </span>
            </div>
            <Input
                value={value && value.trim() !== '' ? value : '-'}
                readOnly
                disabled
                tabIndex={-1}
                className="h-9 cursor-not-allowed bg-muted/60 text-sm font-medium text-foreground opacity-100"
            />
        </div>
    );
}

export default function Profile({
    mustVerifyEmail,
    status,
    passwordRules,
    accountInfo,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    passwordRules?: string;
    accountInfo?: AccountInfo;
}) {
    const { auth } = usePage<PageProps>().props;
    const inMobileShell = useInMobileShell();

    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);

    const info: AccountInfo = accountInfo ?? {
        name: auth.user.name,
        nip: (auth.user.employee_id as string | undefined) ?? '-',
        position: (auth.user.position as string | undefined) ?? '-',
        division: null,
        unit: null,
        service_unit: null,
        regu: null,
        roles: (auth.roles ?? []).map((role) => role.display_name),
        signature_url: null,
        has_signature: false,
    };

    const [sigMode, setSigMode] = useState<'draw' | 'upload'>('draw');
    const [signatureBase64, setSignatureBase64] = useState<string | null>(null);
    const [signatureFile, setSignatureFile] = useState<File | null>(null);
    const [uploadPreview, setUploadPreview] = useState<string | null>(null);
    const [sigProcessing, setSigProcessing] = useState(false);
    const [sigErrors, setSigErrors] = useState<Record<string, string>>({});

    const handleSaveSignature = (e: React.FormEvent) => {
        e.preventDefault();
        setSigErrors({});
        setSigProcessing(true);

        const payload: Record<string, File | string | boolean | null> = {};
        if (sigMode === 'draw') {
            payload.signature_base64 = signatureBase64;
        } else {
            payload.signature = signatureFile;
        }

        router.post('/settings/profile/signature', payload, {
            forceFormData: sigMode === 'upload',
            preserveScroll: true,
            onSuccess: () => {
                setSignatureBase64(null);
                setSignatureFile(null);
                setUploadPreview(null);
            },
            onError: (errors) => {
                setSigErrors(errors);
            },
            onFinish: () => {
                setSigProcessing(false);
            },
        });
    };

    const handleRemoveSignature = () => {
        setSigErrors({});
        setSigProcessing(true);

        router.post(
            '/settings/profile/signature',
            { remove_signature: true },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setSigErrors(errors);
                },
                onFinish: () => {
                    setSigProcessing(false);
                },
            },
        );
    };

    return (
        <>
            <Head title="Profil & Pengaturan" />

            <h1 className="sr-only">Profil & Pengaturan</h1>

            {/* 1. Read-Only Account & Employee Information */}
            <div className="rounded-2xl border border-border bg-card p-4 shadow-xs sm:p-5">
                <div className="flex items-start justify-between gap-3 border-b border-border/70 pb-3.5">
                    <div className="flex items-center gap-3">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <IdCard className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <h2 className="truncate text-base font-semibold text-foreground">
                                Informasi Akun & Kepegawaian
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Data identitas pegawai sesuai akun (tidak dapat
                                diubah sendiri)
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <ReadOnlyField label="Nama Lengkap" value={info.name} />
                    <ReadOnlyField label="NIP / NID" value={info.nip} />
                    <ReadOnlyField label="Jabatan" value={info.position} />
                    <ReadOnlyField
                        label="Role Akun"
                        value={
                            info.roles.length > 0
                                ? info.roles.join(', ')
                                : '-'
                        }
                    />
                    {(info.unit || info.service_unit) && (
                        <>
                            <ReadOnlyField
                                label="Unit Pembangkit"
                                value={info.unit}
                            />
                            <ReadOnlyField
                                label="Unit Layanan"
                                value={info.service_unit}
                            />
                        </>
                    )}
                    {info.division && (
                        <ReadOnlyField label="Divisi" value={info.division} />
                    )}
                    {info.regu && (
                        <ReadOnlyField label="Regu / Shift" value={info.regu} />
                    )}
                </div>

                <div className="mt-3.5 flex items-center gap-2 rounded-xl bg-muted/50 px-3 py-2 text-[11.5px] text-muted-foreground">
                    <Building2 className="size-3.5 shrink-0 text-primary" />
                    <span>
                        Perubahan NIP, jabatan, unit, atau regu shift hanya
                        dapat dilakukan oleh Administrator melalui menu Data
                        Kepegawaian.
                    </span>
                </div>
            </div>

            {/* 2. Edit Email Section */}
            <div className="rounded-2xl border border-border bg-card p-4 shadow-xs sm:p-5">
                <div className="flex items-center gap-3 border-b border-border/70 pb-3.5">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                        <Mail className="size-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-foreground">
                            Alamat Email Akun
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Perbarui alamat email yang digunakan untuk masuk ke
                            aplikasi
                        </p>
                    </div>
                </div>

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="name"
                                value={auth.user.name}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="email">Alamat Email</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="nama@email.com"
                                />

                                <InputError
                                    className="mt-1"
                                    message={errors.email || errors.name}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Alamat email Anda belum
                                            terverifikasi.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Klik di sini untuk mengirim
                                                ulang email verifikasi.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                Tautan verifikasi baru telah
                                                dikirim ke alamat email Anda.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-3">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                    className="w-full sm:w-auto"
                                >
                                    Simpan Email
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {/* 3. Digital Signature Section (Canvas & Upload) */}
            <div className="rounded-2xl border border-border bg-card p-4 shadow-xs sm:p-5">
                <div className="flex items-start justify-between gap-3 border-b border-border/70 pb-3.5">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <PenLine className="size-5" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-base font-semibold text-foreground">
                                    Tanda Tangan Digital
                                </h2>
                                {info.has_signature ? (
                                    <Badge
                                        variant="secondary"
                                        className="gap-1 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                    >
                                        <BadgeCheck className="size-3.5" />
                                        Tersimpan
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="text-xs">
                                        Belum Ada
                                    </Badge>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Gambar tanda tangan pada kanvas untuk digunakan
                                otomatis pada laporan & persetujuan
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mt-4 space-y-4">
                    {/* Current Saved Signature Preview */}
                    {info.signature_url && (
                        <div className="flex flex-col gap-3 rounded-xl border border-border/80 bg-muted/20 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1.5">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Tanda Tangan Saat Ini:
                                </p>
                                <div className="inline-flex items-center justify-center rounded-lg border border-border bg-white p-2.5">
                                    <img
                                        src={info.signature_url}
                                        alt="Tanda tangan tersimpan"
                                        className="h-16 max-w-[240px] object-contain"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={sigProcessing}
                                onClick={handleRemoveSignature}
                                className="gap-1.5 border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 className="size-3.5" />
                                Hapus Tanda Tangan
                            </Button>
                        </div>
                    )}

                    {/* Mode Tabs: Draw on Canvas vs Upload Image */}
                    <div className="flex gap-1.5 rounded-xl bg-muted/60 p-1">
                        <button
                            type="button"
                            onClick={() => setSigMode('draw')}
                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
                                sigMode === 'draw'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <PenLine className="size-3.5" />
                            Gambar di Kanvas
                        </button>
                        <button
                            type="button"
                            onClick={() => setSigMode('upload')}
                            className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
                                sigMode === 'upload'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Upload className="size-3.5" />
                            Upload Gambar
                        </button>
                    </div>

                    <form onSubmit={handleSaveSignature} className="space-y-4">
                        {sigMode === 'draw' ? (
                            <div className="space-y-2">
                                <SignaturePad onChange={setSignatureBase64} />
                                <InputError
                                    message={sigErrors.signature_base64}
                                />
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Input
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0] ?? null;
                                        setSignatureFile(file);
                                        setUploadPreview(
                                            file
                                                ? URL.createObjectURL(file)
                                                : null,
                                        );
                                    }}
                                />
                                <p className="text-[11.5px] text-muted-foreground">
                                    Format PNG transparan disarankan (maksimal
                                    2 MB).
                                </p>
                                {uploadPreview && (
                                    <div className="inline-flex rounded-lg border bg-white p-2">
                                        <img
                                            src={uploadPreview}
                                            alt="Pratinjau upload tanda tangan"
                                            className="h-16 max-w-[220px] object-contain"
                                        />
                                    </div>
                                )}
                                <InputError message={sigErrors.signature} />
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                sigProcessing ||
                                (sigMode === 'draw'
                                    ? !signatureBase64
                                    : !signatureFile)
                            }
                            data-test="save-signature-button"
                            className="w-full sm:w-auto"
                        >
                            <UserCheck className="mr-1.5 size-4" />
                            Simpan Tanda Tangan
                        </Button>
                    </form>
                </div>
            </div>

            {/* 4. Change Password Section */}
            <div className="rounded-2xl border border-border bg-card p-4 shadow-xs sm:p-5">
                <div className="flex items-center gap-3 border-b border-border/70 pb-3.5">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <KeyRound className="size-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-foreground">
                            Ubah Kata Sandi
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Pastikan akun Anda menggunakan kata sandi yang kuat
                            dan aman
                        </p>
                    </div>
                </div>

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="mt-4 space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Kata Sandi Saat Ini
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="block w-full"
                                    autoComplete="current-password"
                                    placeholder="Masukkan kata sandi saat ini"
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Kata Sandi Baru
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="block w-full"
                                    autoComplete="new-password"
                                    placeholder="Masukkan kata sandi baru"
                                    passwordRules={passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Konfirmasi Kata Sandi Baru
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="block w-full"
                                    autoComplete="new-password"
                                    placeholder="Ulangi kata sandi baru"
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                    className="w-full sm:w-auto"
                                >
                                    Simpan Kata Sandi
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {!inMobileShell && <DeleteUser />}
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profil & Pengaturan',
            href: edit(),
        },
    ],
};
