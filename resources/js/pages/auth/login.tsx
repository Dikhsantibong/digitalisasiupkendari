import { Form, Head, Link } from '@inertiajs/react';
import { Building2, HardHat, Lock, Mail, Wrench, Zap } from 'lucide-react';
import InputError from '@/components/input-error';
import { InstallAppLink } from '@/components/install-app-link';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    serviceUnits?: string[];
};

/** The three bidang the application covers, shown beside the sign-in form. */
const MODULES = [
    {
        icon: Zap,
        title: 'Operasi',
        description:
            'Logsheet harian, start-stop mesin, neraca BBM & pelumas, hingga Berita Acara.',
    },
    {
        icon: Wrench,
        title: 'Pemeliharaan',
        description:
            'Work Order & Service Request, siklus servis mesin, logbook HARMES, dan rekap keandalan.',
    },
    {
        icon: HardHat,
        title: 'K3 & Keamanan',
        description:
            'Patroli, inspeksi APAR/hydrant, kepatuhan APD, dan laporan kinerja K3 bulanan.',
    },
] as const;

export default function Login({
    status,
    canResetPassword,
    serviceUnits = [],
}: Props) {
    const currentYear = new Date().getFullYear();

    return (
        <div className="flex min-h-dvh w-full flex-col bg-background text-foreground lg:grid lg:grid-cols-12">
            <Head title="Masuk" />

            <main className="flex flex-1 flex-col justify-center gap-6 px-4 py-6 sm:gap-8 sm:px-8 sm:py-8 lg:col-span-6 lg:justify-between lg:min-h-dvh lg:px-10 lg:py-8 xl:col-span-5 xl:px-12 xl:py-10">
                <div className="mx-auto flex w-full max-w-md items-center justify-between gap-3 pb-1 lg:border-b lg:border-border lg:pb-4">
                    <Link href={home()} className="flex items-center gap-2">
                        <span className="rounded-sm border border-border bg-card p-1">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="h-6 w-auto"
                            />
                        </span>
                        <span className="rounded-sm border border-border bg-card p-1">
                            <img
                                src="/logo/k3.png"
                                alt="K3"
                                className="h-6 w-auto"
                            />
                        </span>
                    </Link>
                    <span className="text-[13px] font-semibold text-primary">
                        UP Kendari
                    </span>
                </div>

                <div className="mx-auto flex w-full max-w-md items-center lg:flex-1">
                    <div className="w-full rounded-md border border-border bg-card p-5 sm:p-8">
                        <div className="mb-5 border-b border-border pb-4 sm:mb-6">
                            <h2 className="text-2xl font-semibold tracking-tight text-foreground">
                                Masuk ke Akun
                            </h2>
                            <p className="mt-1.5 text-[13px] text-muted-foreground">
                                Masukkan email dan kata sandi akun Anda.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-5 rounded-md border border-primary/30 bg-secondary px-3 py-2 text-[13px] text-secondary-foreground">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="email">
                                            Email{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="nama@pln.co.id"
                                                className="h-10 pl-9"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="password">
                                            Kata Sandi{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <div className="relative">
                                            <Lock className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                className="h-10 pl-9"
                                            />
                                        </div>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                tabIndex={3}
                                            />
                                            <Label
                                                htmlFor="remember"
                                                className="cursor-pointer font-normal text-muted-foreground"
                                            >
                                                Ingat saya
                                            </Label>
                                        </div>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="text-[13px]"
                                                tabIndex={5}
                                            >
                                                Lupa kata sandi?
                                            </TextLink>
                                        )}
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-1 h-10 w-full"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing ? (
                                            <>
                                                <Spinner />
                                                Memproses…
                                            </>
                                        ) : (
                                            'Masuk'
                                        )}
                                    </Button>
                                </>
                            )}
                        </Form>

                        <div className="mt-6 flex flex-col gap-3 border-t border-border pt-5">
                            <InstallAppLink className="mt-0" />

                            {/* Phone: only the sign-in form (user, 2026-11-22). */}
                            <p className="hidden rounded-md border border-border bg-secondary px-3 py-2 text-[13px] text-muted-foreground lg:block">
                                Akses terbatas untuk pengguna terdaftar PT PLN
                                Nusantara Power UP Kendari.
                            </p>
                        </div>
                    </div>
                </div>

                <p className="mx-auto hidden w-full max-w-md border-t border-border pt-4 text-center text-[13px] text-muted-foreground lg:block">
                    &copy; {currentYear} PT PLN Nusantara Power — UP Kendari
                </p>
            </main>

            {/* Identity panel: the UP Kendari building under a solid brand overlay. */}
            <aside className="relative hidden flex-col overflow-hidden lg:flex px-4 py-8 sm:px-8 sm:py-10 lg:col-span-6 lg:min-h-dvh lg:px-10 lg:py-8 xl:col-span-7 xl:px-14 xl:py-10">
                <div
                    className="absolute inset-0 bg-cover bg-center"
                    style={{
                        backgroundImage: "url('/background/bg-login.jpeg')",
                    }}
                    aria-hidden
                />
                <div className="absolute inset-0 bg-[#0A2638]/90" aria-hidden />

                <div className="relative mx-auto flex w-full max-w-md flex-1 flex-col justify-between gap-8 sm:max-w-3xl lg:max-w-2xl">
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-white/15 pb-4 leading-tight">
                        <p className="text-[13px] font-semibold text-white">
                            PT PLN Nusantara Power
                        </p>
                        <p className="text-[13px] text-white/70">UP Kendari</p>
                    </div>

                    <div className="my-auto flex flex-col gap-6 sm:gap-8">
                        <div className="max-w-2xl">
                            <p className="text-[13px] font-semibold tracking-wide text-white/70 uppercase">
                                KIT-SIPP
                            </p>
                            <h1 className="mt-2 text-[28px] leading-tight font-semibold text-white">
                                Sistem Informasi Pelaporan Pembangkit
                            </h1>
                            <p className="mt-2.5 text-sm text-white/75">
                                Pencatatan harian unit, verifikasi berjenjang,
                                dan laporan bulanan dalam satu aplikasi.
                            </p>
                        </div>

                        <ul className="grid grid-cols-1 divide-y divide-white/15 border-y border-white/15 sm:grid-cols-3 sm:gap-6 sm:divide-y-0 sm:py-5 lg:grid-cols-1 lg:gap-0 lg:divide-y lg:py-0">
                            {MODULES.map((module) => (
                                <li
                                    key={module.title}
                                    className="flex items-start gap-3 py-4 sm:py-0 lg:py-4"
                                >
                                    <module.icon className="mt-0.5 size-5 shrink-0 text-white/80" />
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-white">
                                            {module.title}
                                        </p>
                                        <p className="mt-1 text-[13px] text-white/70">
                                            {module.description}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {serviceUnits.length > 0 && (
                        <p className="flex items-start gap-2 border-t border-white/15 pt-4 text-[13px] text-white/70 sm:items-center">
                            <Building2 className="mt-0.5 size-4 shrink-0 sm:mt-0" />
                            <span>
                                <span className="font-medium text-white">
                                    Unit Layanan:
                                </span>{' '}
                                {serviceUnits.join(' · ')}
                            </span>
                        </p>
                    )}
                </div>
            </aside>

        </div>
    );
}

// Full-screen split layout, without the narrow auth wrapper.
Login.layout = (page: React.ReactNode) => page;
