import { Form, Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Building2,
    CheckCircle2,
    Clock,
    FileCheck,
    FileText,
    Flame,
    GitBranch,
    Lock,
    Mail,
    Shield,
    ShieldCheck,
    Wrench,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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

export default function Login({ status, canResetPassword }: Props) {
    const currentYear = new Date().getFullYear();
    const [activeTab, setActiveTab] = useState<0 | 1 | 2>(0);
    const [isPaused, setIsPaused] = useState(false);

    // Otomatis berganti bentuk alur setiap 6 detik
    useEffect(() => {
        if (isPaused) return;
        const timer = setInterval(() => {
            setActiveTab((prev) => ((prev + 1) % 3) as 0 | 1 | 2);
        }, 6000);
        return () => clearInterval(timer);
    }, [isPaused]);

    return (
        <div className="min-h-screen w-full bg-[#F1F7FC] text-[#16323F] lg:grid lg:grid-cols-12">
            <Head title="Masuk ke Akun - Digitalisasi Unit UP Kendari" />

            {/* SISI KIRI: Background Foto Gedung UP Kendari & Flowchart dengan Bentuk Berbeda-Beda */}
            <div className="relative hidden flex-col justify-between overflow-hidden p-8 lg:col-span-7 lg:flex xl:col-span-7 xl:p-10 2xl:col-span-8 2xl:p-12">
                {/* Background Image: Gedung UP Kendari */}
                <div
                    className="absolute inset-0 bg-cover bg-center bg-no-repeat"
                    style={{
                        backgroundImage: "url('/background/bg-login.jpeg')",
                    }}
                />

                {/* Solid Corporate Dark Overlay */}
                <div className="absolute inset-0 bg-[#0A2638]/92" />

                {/* Header Sisi Kiri: Logo Resmi PLN & K3 */}
                <div className="relative z-10 flex items-center justify-between border-b border-white/10 pb-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 transition-opacity hover:opacity-90"
                    >
                        <div className="flex items-center rounded bg-white px-3 py-1.5 border border-slate-200">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="h-6 w-auto object-contain"
                            />
                        </div>
                        <div className="flex items-center rounded bg-white px-2 py-1.5 border border-slate-200">
                            <img
                                src="/logo/k3.png"
                                alt="K3 Logo"
                                className="h-6 w-auto object-contain"
                            />
                        </div>
                    </Link>

                    <div className="text-right">
                        <div className="text-xs font-semibold text-white">
                            PT PLN NUSANTARA POWER
                        </div>
                        <div className="text-[11px] text-slate-300">
                            UP Kendari
                        </div>
                    </div>
                </div>

                {/* Konten Utama: Bentuk Diagram yang Berbeda untuk Setiap Bidang */}
                <div className="relative z-10 my-auto py-3">
                    {/* Header Modul & Tombol Pilihan Bentuk Alur */}
                    <div className="mb-3">
                        <div className="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <span className="text-xs font-semibold text-[#7CC6E8]">
                                ALUR PROSES OPERASIONAL TERPADU
                            </span>

                            {/* Tab Switcher Bentuk Alur */}
                            <div className="inline-flex rounded border border-white/15 bg-black/40 p-0.5">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab(0)}
                                    className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                                        activeTab === 0
                                            ? 'bg-[#0C7DBB] text-white'
                                            : 'text-slate-300 hover:text-white'
                                    }`}
                                >
                                    Alur Operasi
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab(1)}
                                    className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                                        activeTab === 1
                                            ? 'bg-[#0C7DBB] text-white'
                                            : 'text-slate-300 hover:text-white'
                                    }`}
                                >
                                    Alur Pemeliharaan (HAR)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab(2)}
                                    className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                                        activeTab === 2
                                            ? 'bg-[#0C7DBB] text-white'
                                            : 'text-slate-300 hover:text-white'
                                    }`}
                                >
                                    Alur K3 &amp; Keamanan
                                </button>
                            </div>
                        </div>

                        {/* Judul & Penjelasan Singkat */}
                        {activeTab === 0 && (
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-white xl:text-2xl">
                                    Pipeline Kinerja Operasi Pembangkit
                                </h1>
                                <p className="mt-0.5 text-xs text-slate-300">
                                    Bentuk pipeline 4 tahapan berurutan dari perekaman stand meter harian hingga penerbitan Berita Acara.
                                </p>
                            </div>
                        )}

                        {activeTab === 1 && (
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-white xl:text-2xl">
                                    Bagan Alur Cabang Pemeliharaan (HAR)
                                </h1>
                                <p className="mt-0.5 text-xs text-slate-300">
                                    Bentuk alur percabangan Work Order: pemisahan jalur preventif &amp; korektif yang bermuara pada laporan keandalan.
                                </p>
                            </div>
                        )}

                        {activeTab === 2 && (
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-white xl:text-2xl">
                                    Matriks Kepatuhan K3 &amp; Keamanan
                                </h1>
                                <p className="mt-0.5 text-xs text-slate-300">
                                    Bentuk dua pilar pengawasan fisik &amp; sarana proteksi yang mengerucut pada sertifikasi LAPKIN ISO.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* AREA DIAGRAM BERBEDA BENTUK (Bukan 3 kotak berjejer) */}
                    <div
                        className="rounded-md border border-white/15 bg-black/30 p-4"
                        onMouseEnter={() => setIsPaused(true)}
                        onMouseLeave={() => setIsPaused(false)}
                    >
                        {/* ========================================================= */}
                        {/* BENTUK 1: PIPELINE BERURUTAN 4 TAHAP (ALUR OPERASI)      */}
                        {/* ========================================================= */}
                        {activeTab === 0 && (
                            <div className="space-y-3">
                                {/* Rel Penghubung Stepper Horizontal */}
                                <div className="relative flex items-center justify-between px-2 pt-1 pb-2">
                                    <div className="absolute left-6 right-6 top-4 h-0.5 bg-white/20 -translate-y-1/2" />
                                    <div className="absolute left-6 top-4 h-0.5 bg-[#0C7DBB] w-3/4 -translate-y-1/2" />

                                    <div className="relative z-10 flex flex-col items-center">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-[#0C7DBB] text-white text-[11px] font-bold border-2 border-white/30">
                                            1
                                        </span>
                                        <span className="text-[10px] font-medium text-slate-200 mt-1">
                                            Stand Meter
                                        </span>
                                    </div>
                                    <div className="relative z-10 flex flex-col items-center">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-[#0C7DBB] text-white text-[11px] font-bold border-2 border-white/30">
                                            2
                                        </span>
                                        <span className="text-[10px] font-medium text-slate-200 mt-1">
                                            Jam Nyala
                                        </span>
                                    </div>
                                    <div className="relative z-10 flex flex-col items-center">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-[#0C7DBB] text-white text-[11px] font-bold border-2 border-white/30">
                                            3
                                        </span>
                                        <span className="text-[10px] font-medium text-slate-200 mt-1">
                                            Neraca BBM
                                        </span>
                                    </div>
                                    <div className="relative z-10 flex flex-col items-center">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-emerald-600 text-white text-[11px] font-bold border-2 border-white/30">
                                            4
                                        </span>
                                        <span className="text-[10px] font-medium text-emerald-300 mt-1">
                                            Berita Acara
                                        </span>
                                    </div>
                                </div>

                                {/* 4 Kartu Pipeline Horizontal Tipis */}
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 pt-1">
                                    <div className="rounded border border-white/15 bg-white/5 p-2.5">
                                        <div className="flex items-center gap-1.5 text-sky-300 mb-1">
                                            <Activity className="size-3.5" />
                                            <span className="text-[11px] font-semibold text-white">
                                                Logsheet Harian
                                            </span>
                                        </div>
                                        <p className="text-[10px] text-slate-300 leading-snug">
                                            Input stand kWh 24 jam &amp; beban puncak (pagi &amp; malam).
                                        </p>
                                    </div>

                                    <div className="rounded border border-white/15 bg-white/5 p-2.5">
                                        <div className="flex items-center gap-1.5 text-sky-300 mb-1">
                                            <Clock className="size-3.5" />
                                            <span className="text-[11px] font-semibold text-white">
                                                Start-Stop Mesin
                                            </span>
                                        </div>
                                        <p className="text-[10px] text-slate-300 leading-snug">
                                            Jam operasi (OH), gangguan (FO), serta rumus SDR &amp; EFOR.
                                        </p>
                                    </div>

                                    <div className="rounded border border-white/15 bg-white/5 p-2.5">
                                        <div className="flex items-center gap-1.5 text-sky-300 mb-1">
                                            <Zap className="size-3.5" />
                                            <span className="text-[11px] font-semibold text-white">
                                                SFC &amp; Pelumas
                                            </span>
                                        </div>
                                        <p className="text-[10px] text-slate-300 leading-snug">
                                            Stok BBM (HSD/MFO), pelumas per unit, dan efisiensi spesifik.
                                        </p>
                                    </div>

                                    <div className="rounded border border-emerald-500/30 bg-emerald-950/30 p-2.5">
                                        <div className="flex items-center gap-1.5 text-emerald-400 mb-1">
                                            <FileText className="size-3.5" />
                                            <span className="text-[11px] font-semibold text-white">
                                                Dokumen BA
                                            </span>
                                        </div>
                                        <p className="text-[10px] text-emerald-200 leading-snug">
                                            Penerbitan otomatis BA bahan bakar &amp; laporan ke Unit Induk.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================= */}
                        {/* BENTUK 2: BRANCHING TREE FORK & MERGE (ALUR HAR)          */}
                        {/* ========================================================= */}
                        {activeTab === 1 && (
                            <div className="grid grid-cols-1 md:grid-cols-12 gap-2.5 items-center">
                                {/* Kiri: Input Sumber Work Order (4 Kolom) */}
                                <div className="md:col-span-4 rounded border border-white/15 bg-[#0C344E]/80 p-3">
                                    <div className="flex items-center gap-1.5 text-[#7CC6E8] mb-1.5 border-b border-white/10 pb-1.5">
                                        <Wrench className="size-3.5" />
                                        <span className="text-xs font-semibold text-white">
                                            Input Work Order
                                        </span>
                                    </div>
                                    <p className="text-[11px] text-slate-300 leading-relaxed">
                                        Registrasi tiket WO &amp; SR (manual atau WPC). Meliputi penugasan personil dan penentuan klasifikasi mesin.
                                    </p>
                                    <div className="mt-2 text-[10px] font-mono text-sky-300">
                                        Sumber: WO PM / PdM / CM
                                    </div>
                                </div>

                                {/* Tengah: Percabangan Jalur Preventif vs Korektif (5 Kolom) */}
                                <div className="md:col-span-5 space-y-2">
                                    {/* Cabang Atas: Preventif & Siklus */}
                                    <div className="rounded border border-sky-400/30 bg-white/5 p-2 flex items-start gap-2">
                                        <span className="rounded bg-sky-500/20 px-1 py-0.5 text-[9px] font-mono font-semibold text-sky-300 shrink-0">
                                            Jalur A
                                        </span>
                                        <div>
                                            <h5 className="text-[11px] font-semibold text-white">
                                                Siklus Servis (P1, P2, P4, Overhaul)
                                            </h5>
                                            <p className="text-[10px] text-slate-300 leading-tight">
                                                Pemeliharaan terencana berdasarkan jam mesin (250h, 500h).
                                            </p>
                                        </div>
                                    </div>

                                    {/* Cabang Bawah: Korektif & HARMES */}
                                    <div className="rounded border border-amber-400/30 bg-white/5 p-2 flex items-start gap-2">
                                        <span className="rounded bg-amber-500/20 px-1 py-0.5 text-[9px] font-mono font-semibold text-amber-300 shrink-0">
                                            Jalur B
                                        </span>
                                        <div>
                                            <h5 className="text-[11px] font-semibold text-white">
                                                Logbook HARMES &amp; Spare Part
                                            </h5>
                                            <p className="text-[10px] text-slate-300 leading-tight">
                                                Catatan perbaikan teknisi dan alokasi material suku cadang.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Kanan: Konvergensi / Merge Hasil Laporan & Biaya (3 Kolom) */}
                                <div className="md:col-span-3 rounded border border-emerald-500/30 bg-emerald-950/30 p-3">
                                    <div className="flex items-center gap-1.5 text-emerald-400 mb-1 border-b border-white/10 pb-1">
                                        <GitBranch className="size-3.5" />
                                        <span className="text-[11px] font-semibold text-white">
                                            Rekapitulasi
                                        </span>
                                    </div>
                                    <p className="text-[10px] text-emerald-200 leading-snug">
                                        Perhitungan faktor ketersediaan (EAF) &amp; audit realisasi biaya jasa/material.
                                    </p>
                                    <div className="mt-2 text-[9px] font-mono text-emerald-300">
                                        Executive Summary HAR
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================= */}
                        {/* BENTUK 3: MATRIKS DUA PILAR MENUJU LAPKIN ISO (ALUR K3)   */}
                        {/* ========================================================= */}
                        {activeTab === 2 && (
                            <div className="space-y-2.5">
                                {/* Dua Pilar Berjejer */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                    {/* Pilar 1: Keamanan Fisik Unit */}
                                    <div className="rounded border border-white/15 bg-white/5 p-3">
                                        <div className="flex items-center gap-2 border-b border-white/10 pb-1.5 mb-1.5">
                                            <Shield className="size-3.5 text-sky-400" />
                                            <span className="text-xs font-semibold text-white">
                                                Pilar Keamanan Fisik
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-300 leading-relaxed">
                                            Pencatatan patroli satpam titik barcode/RFID POA 1–14, apel kedisiplinan, buku tamu, serta pemantauan kamera CCTV 24/7.
                                        </p>
                                    </div>

                                    {/* Pilar 2: Fasilitas Proteksi & K3L */}
                                    <div className="rounded border border-white/15 bg-white/5 p-3">
                                        <div className="flex items-center gap-2 border-b border-white/10 pb-1.5 mb-1.5">
                                            <Flame className="size-3.5 text-amber-400" />
                                            <span className="text-xs font-semibold text-white">
                                                Pilar Proteksi &amp; Tanggap Darurat
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-300 leading-relaxed">
                                            Inspeksi masa kedaluwarsa tabung APAR/APAB via RFID, kelengkapan kotak P3K, kesiapan hydrant, serta kepatuhan APD teknisi.
                                        </p>
                                    </div>
                                </div>

                                {/* Banner Pengerucutan: Kepatuhan Zero Accident & Dokumen ISO */}
                                <div className="rounded border border-emerald-500/40 bg-emerald-950/40 p-2.5 flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="size-4 text-emerald-400 shrink-0" />
                                        <div>
                                            <span className="text-xs font-semibold text-white">
                                                Verifikasi Zero Accident &amp; Kompilasi LAPKIN Bulanan
                                            </span>
                                            <p className="text-[10px] text-emerald-200">
                                                Tracking kecelakaan kerja (PAK/PAHK) nihil dan penerbitan satu berkas utuh berstandar ISO SMT-FM-AK3.
                                            </p>
                                        </div>
                                    </div>
                                    <span className="rounded bg-emerald-500/20 px-2 py-0.5 font-mono text-[10px] text-emerald-300 shrink-0">
                                        Dokumen ISO Terpadu
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Footer Status Alur */}
                        <div className="mt-3 flex items-center justify-between border-t border-white/10 pt-2 text-[11px] text-slate-400">
                            <span>Sistem Pembangkitan: Operasi &bull; Pemeliharaan &bull; K3</span>
                            <span>{isPaused ? 'Dijeda saat kursor berada di sini' : 'Berganti bentuk alur otomatis'}</span>
                        </div>
                    </div>
                </div>

                {/* Footer Sisi Kiri: Cakupan Unit Layanan */}
                <div className="relative z-10 border-t border-white/10 pt-3">
                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-300">
                        <Building2 className="size-3.5 text-[#7CC6E8]" />
                        <span className="font-medium text-slate-200">
                            Unit Layanan:
                        </span>
                        <span className="text-slate-300">
                            PLTD Poasia &bull; PLTD Wua-Wua &bull; PLTD Kolaka &bull; PLTD Bau-Bau &bull; PLTD Cummins
                        </span>
                    </div>
                </div>
            </div>

            {/* SISI KANAN: Formulir Login Sederhana & Fungsional */}
            <div className="flex min-h-screen flex-col justify-between p-6 sm:p-10 lg:col-span-5 lg:p-12 xl:col-span-5 2xl:col-span-4 bg-[#F1F7FC]">
                {/* Mobile Header (< lg) */}
                <div className="flex items-center justify-between border-b border-[#CFE1EE] pb-4 lg:hidden">
                    <Link href={home()} className="flex items-center gap-2">
                        <div className="flex items-center rounded bg-white p-1 border border-[#CFE1EE]">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="h-6 w-auto object-contain"
                            />
                        </div>
                        <div className="flex items-center rounded bg-white p-1 border border-[#CFE1EE]">
                            <img
                                src="/logo/k3.png"
                                alt="K3"
                                className="h-6 w-auto object-contain"
                            />
                        </div>
                    </Link>
                    <span className="text-xs font-semibold text-[#0C7DBB]">
                        UP Kendari
                    </span>
                </div>

                {/* Container Form (Card Putih Bersih Level 1) */}
                <div className="mx-auto my-auto w-full max-w-sm">
                    <div className="rounded border border-[#CFE1EE] bg-white p-6 sm:p-8">
                        <div className="mb-6 border-b border-[#DCEAF4] pb-4">
                            <h2 className="text-xl font-bold tracking-tight text-[#16323F]">
                                Masuk ke Akun
                            </h2>
                            <p className="mt-1 text-xs text-[#5B7280]">
                                Silakan masukkan email dan kata sandi Anda.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded border border-[#0C7DBB]/30 bg-[#EAF4FB] p-2.5 text-xs text-[#0C4A6B]">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="email"
                                            className="text-xs font-semibold text-[#16323F]"
                                        >
                                            Email <span className="text-red-500">*</span>
                                        </Label>
                                        <div className="relative">
                                            <Mail className="absolute left-3 top-2.5 size-4 text-[#5B7280]" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="nama@pln.co.id"
                                                className="pl-9 h-9 text-xs border-[#CFE1EE] focus-visible:ring-1 focus-visible:ring-[#0C7DBB]"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="password"
                                                className="text-xs font-semibold text-[#16323F]"
                                            >
                                                Kata Sandi <span className="text-red-500">*</span>
                                            </Label>
                                            {canResetPassword && (
                                                <TextLink
                                                    href={request()}
                                                    className="text-xs text-[#0C7DBB] hover:text-[#0A6BA1] hover:underline"
                                                    tabIndex={5}
                                                >
                                                    Lupa kata sandi?
                                                </TextLink>
                                            )}
                                        </div>
                                        <div className="relative">
                                            <Lock className="absolute left-3 top-2.5 size-4 text-[#5B7280] z-10 pointer-events-none" />
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder="••••••••"
                                                className="pl-9 h-9 text-xs border-[#CFE1EE] focus-visible:ring-1 focus-visible:ring-[#0C7DBB]"
                                            />
                                        </div>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center space-x-2 pt-0.5">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                            className="border-[#CFE1EE] data-[state=checked]:bg-[#0C7DBB] data-[state=checked]:border-[#0C7DBB]"
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-xs text-[#40566B] cursor-pointer"
                                        >
                                            Ingat saya
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-2 w-full h-9 bg-[#0C7DBB] hover:bg-[#0A6BA1] text-white font-medium text-xs rounded shadow-none"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing ? (
                                            <>
                                                <Spinner className="mr-2" />
                                                <span>Memproses...</span>
                                            </>
                                        ) : (
                                            <span>Masuk</span>
                                        )}
                                    </Button>
                                </>
                            )}
                        </Form>

                        {/* Catatan Sederhana */}
                        <div className="mt-5 rounded border border-[#CFE1EE] bg-[#EAF4FB] p-2.5 text-[11px] leading-relaxed text-[#40566B]">
                            Akses sistem terbatas untuk pengguna terdaftar PT PLN Nusantara Power UP Kendari.
                        </div>
                    </div>
                </div>

                {/* Footer Kanan */}
                <div className="text-center text-[11px] text-[#5B7280]">
                    &copy; {currentYear} PT PLN Nusantara Power &mdash; UP Kendari
                </div>
            </div>
        </div>
    );
}

// Menghindari wrapper layout sempit agar tampilan split screen aktif penuh
Login.layout = (page: React.ReactNode) => page;
