import { Form, Head, Link } from '@inertiajs/react';
import { Building2, HardHat, Lock, Mail, Wrench, Zap } from 'lucide-react';
import InputError from '@/components/input-error';
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

export default function Login({ status, canResetPassword }: Props) {
    const currentYear = new Date().getFullYear();

    return (
        <div className="min-h-dvh w-full bg-background text-foreground lg:grid lg:grid-cols-12">
            <Head title="Masuk" />

            {/* Identity panel: the UP Kendari building under a solid brand overlay. */}
            <aside className="relative hidden flex-col justify-between overflow-hidden p-10 lg:col-span-7 lg:flex 2xl:col-span-8">
                <div
                    className="absolute inset-0 bg-cover bg-center"
                    style={{
                        backgroundImage: "url('/background/bg-login.jpeg')",
                    }}
                    aria-hidden
                />
                <div className="absolute inset-0 bg-[#0A2638]/90" aria-hidden />

                <div className="relative flex items-center justify-between border-b border-white/15 pb-4">
                    <Link href={home()} className="flex items-center gap-2">
                        <span className="rounded-sm bg-white px-3 py-1.5">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="h-6 w-auto"
                            />
                        </span>
                        <span className="rounded-sm bg-white px-2 py-1.5">
                            <img
                                src="/logo/k3.png"
                                alt="K3"
                                className="h-6 w-auto"
                            />
                        </span>
                    </Link>
                    <div className="text-right leading-tight">
                        <p className="text-[13px] font-semibold text-white">
                            PT PLN Nusantara Power
                        </p>
                        <p className="text-[13px] text-white/70">UP Kendari</p>
                    </div>
                </div>

                <div className="relative max-w-2xl">
                    <p className="text-[13px] font-semibold tracking-wide text-white/70 uppercase">
                        Digitalisasi Unit
                    </p>
                    <h1 className="mt-2 text-[28px] leading-tight font-semibold text-white">
                        Sistem Informasi Operasi, Pemeliharaan & K3 Pembangkit
                    </h1>
                    <p className="mt-2 text-sm text-white/75">
                        Pencatatan harian unit, verifikasi berjenjang, dan
                        laporan bulanan dalam satu aplikasi.
                    </p>

                    <ul className="mt-8 divide-y divide-white/15 border-y border-white/15">
                        {MODULES.map((module) => (
                            <li
                                key={module.title}
                                className="flex items-start gap-3 py-4"
                            >
                                <module.icon className="mt-0.5 size-5 shrink-0 text-white/80" />
                                <div>
                                    <p className="text-sm font-semibold text-white">
                                        {module.title}
                                    </p>
                                    <p className="text-[13px] text-white/70">
                                        {module.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative flex items-center gap-2 border-t border-white/15 pt-4 text-[13px] text-white/70">
                    <Building2 className="size-4 shrink-0" />
                    <span>
                        <span className="font-medium text-white">
                            Unit Layanan:
                        </span>{' '}
                        PLTD Poasia · PLTD Wua-Wua · PLTD Kolaka · PLTD Bau-Bau
                        · PLTD Cummins
                    </span>
                </p>
            </aside>

            <main className="flex min-h-dvh flex-col justify-between p-4 sm:p-10 lg:col-span-5 2xl:col-span-4">
                <div className="flex items-center justify-between border-b border-border pb-4 lg:hidden">
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

                <div className="mx-auto my-10 w-full max-w-sm">
                    <div className="rounded-md border border-border bg-card p-6 sm:p-8">
                        <div className="mb-6 border-b border-border pb-4">
                            <h2 className="text-2xl font-semibold tracking-tight text-foreground">
                                Masuk ke Akun
                            </h2>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Masukkan email dan kata sandi akun Anda.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-md border border-primary/30 bg-secondary px-3 py-2 text-[13px] text-secondary-foreground">
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
                                    <div className="flex flex-col gap-1.5">
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

                                    <div className="flex flex-col gap-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="password">
                                                Kata Sandi{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
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

                                    <Button
                                        type="submit"
                                        className="mt-2 h-10 w-full"
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

                        <p className="mt-5 rounded-md border border-border bg-secondary px-3 py-2 text-[13px] text-muted-foreground">
                            Akses terbatas untuk pengguna terdaftar PT PLN
                            Nusantara Power UP Kendari.
                        </p>
                    </div>
                </div>

                <p className="text-center text-[13px] text-muted-foreground">
                    &copy; {currentYear} PT PLN Nusantara Power — UP Kendari
                </p>
            </main>
        </div>
    );
}

// Full-screen split layout, without the narrow auth wrapper.
Login.layout = (page: React.ReactNode) => page;
