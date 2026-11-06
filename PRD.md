# PRD — Digitalisasi Unit Pembangkitan Kendari

**Management Pelaporan Pembangkit · PLN UP Kendari**

| | |
|---|---|
| Versi | 1.0 |
| Tanggal | 4 November 2026 |
| Status | Berlaku — satu-satunya dokumen alur & kebutuhan aplikasi |
| Menggantikan | `concept.md`, `modul-operasi.md`, `modul-operasi-addendum-absensi.md`, `modul-operasi-addendum-logsheet-operator.md`, `modul-har.md`, `modul-k3.md`, `modul-logistik-gudang.md`, `modul-pdm-maturity-level.md`, `prompt-implementasi-{operasi,har,k3,absensi,logsheet-operator}.md` |
| Dokumen pendamping | `design.md` (design system UI), `.ai/rules/**` (aturan teknis per folder), `CLAUDE.md` (konvensi pengembangan) |

> **Aturan pemeliharaan dokumen.** Setiap perubahan alur, role, permission, menu,
> atau modul **wajib** direfleksikan di PRD ini pada bagian yang relevan (dan di
> §16 Riwayat Keputusan bila berupa keputusan user). Jangan membuat lagi file
> `modul-*.md` / `prompt-implementasi-*.md` terpisah.

---

## Daftar Isi

1. [Ringkasan Produk](#1-ringkasan-produk)
2. [Prinsip Produk & Arsitektur](#2-prinsip-produk--arsitektur)
3. [Ruang Lingkup](#3-ruang-lingkup)
4. [Struktur Organisasi & Unit](#4-struktur-organisasi--unit)
5. [Pengguna, Role & Hak Akses](#5-pengguna-role--hak-akses)
6. [Navigasi Aplikasi](#6-navigasi-aplikasi)
7. [Kebutuhan Fungsional — Umum & Administrasi](#7-kebutuhan-fungsional--umum--administrasi)
8. [Kebutuhan Fungsional — Modul Kerja](#8-kebutuhan-fungsional--modul-kerja)
9. [Laporan & Alur Pengesahan](#9-laporan--alur-pengesahan)
10. [Aturan Bisnis & Kalkulasi](#10-aturan-bisnis--kalkulasi)
11. [Dokumen, Cetak & Ekspor](#11-dokumen-cetak--ekspor)
12. [Kebutuhan Non-Fungsional](#12-kebutuhan-non-fungsional)
13. [Model Data Inti](#13-model-data-inti)
14. [Integrasi & Fase Lanjut](#14-integrasi--fase-lanjut)
15. [Isu Terbuka](#15-isu-terbuka)
16. [Riwayat Keputusan](#16-riwayat-keputusan)
17. [Standar Teknis](#17-standar-teknis)

---

## 1. Ringkasan Produk

### 1.1 Latar belakang

Pelaporan kinerja unit pembangkit di wilayah Sulawesi Tenggara di bawah **PLN UP
Kendari** selama ini dikerjakan dengan puluhan workbook Excel per unit per bulan
(laporan operasi, pemeliharaan/HAR, K3 & keamanan, logistik & gudang, PdM),
logsheet kertas operator, dan berita acara yang diketik ulang. Akibatnya:

- angka dihitung manual berulang kali (kWh, SFC, jam operasi, persediaan BBM),
  rawan selisih antar-sheet;
- format laporan tiap unit tidak seragam dan sulit dikonsolidasi oleh UL/UP;
- tidak ada jejak siapa mengisi apa dan kapan, serta tidak ada alur pengesahan
  yang tercatat;
- pemantauan kelengkapan input dan status laporan lintas unit dilakukan manual.

### 1.2 Tujuan

1. Mendigitalkan seluruh input lapangan dan kantor unit pembangkit ke satu
   aplikasi web multi-unit, dengan tampilan **"terasa seperti Excel"** di desktop
   dan form ringkas di HP untuk petugas lapangan.
2. **Menghitung otomatis** semua angka turunan (rekap, SFC, jam, persediaan,
   kinerja termal) dari satu sumber rumus, bukan diketik ulang.
3. Menghasilkan **laporan bulanan resmi per divisi** (PDF & Excel) sekali klik,
   lengkap dengan kop, No. Dokumen ISO, dan lembar pengesahan.
4. Menyediakan **alur verifikasi & pengesahan digital** (Koordinator → Team
   Leader → Manager UL) dengan tanda tangan dan jejak audit.
5. Memberi kantor induk UP Kendari dan Manager UL **pemantauan real-time**:
   kelengkapan input, status laporan, dan data seluruh unit.

### 1.3 Pengguna utama

| Kelompok | Contoh | Kebutuhan utama |
|---|---|---|
| Petugas lapangan | Operator, Leader Shift, Harmes, Harlist | Absen, isi logsheet/mutasi/patrol dari HP dengan cepat |
| Kantor unit — Akses 1 (Laporan Project) | Koordinator & Office Operasi/Pemeliharaan/K3, Project Leader | Jadwal, input, formulir, susun Laporan Pembangkit |
| Kantor unit — Akses 2 (Pengusahaan) | Team Leader & Staf Operasi/Pemeliharaan/K3 | Input & Laporan Pengusahaan, menyetujui Laporan Pembangkit |
| Logistik & PdM | Koordinator/Office Logistik, Koordinator/PIC PdM | Jadwal, input & laporan divisi |
| Pimpinan UL | Manager UL | Pantau unit di UL-nya, mengesahkan laporan, kelola master mesin/pegawai |
| Kantor induk | Manager UP, TL/Asman Operasi/Pemeliharaan/K3 UP | Pantau seluruh unit (hanya melihat) |
| Pengelola sistem | Super Admin | Akses, master data, pemantauan aktivitas |

### 1.4 Indikator keberhasilan

- 100% input bulanan unit tercatat di aplikasi (terukur di Monitoring →
  Kelengkapan Input).
- Laporan Pembangkit tiap divisi terbit (status **Final**) paling lambat awal
  bulan berikutnya; laporan "macet" (≥ 3 hari di satu tahap) terpantau.
- Tidak ada angka rekap yang diketik manual bila sumbernya tersedia di aplikasi.
- Persentase kehadiran operator dihitung dari presensi berbasis lokasi, bukan
  dari isian jadwal.

---

## 2. Prinsip Produk & Arsitektur

Prinsip pembangunan: **Modular → Scalable → Auditable → Terkontrol.**

1. **Satu sheet Excel = satu menu.** Struktur workbook sumber menjadi blueprint
   navigasi; pengguna mengenali halamannya.
2. **Multi-unit.** Semua data transaksi & master turunan ber-`unit_id`; user
   hanya melihat unit dalam cakupannya.
3. **Master dipakai ulang, tidak dibuat ulang.** Unit (`units`), Unit Layanan
   (`service_units`), Mesin (`machines`), dan Pegawai (`employees`) adalah master
   bersama semua modul.
4. **Permission-driven, bukan role-driven.** Kode tidak pernah memeriksa nama
   role; otorisasi selalu lewat permission/policy. Role baru cukup dibuat lewat
   data (menu Role & Akses), tanpa ubah kode.
5. **Scope terpusat.** Perhitungan unit yang boleh diakses user hanya di
   `App\Services\AccessControl`, diterapkan lewat scope `visibleTo($user)`.
6. **Katalog deklaratif.** Permission di `App\Enums\PermissionName`, default
   role di `App\Enums\RoleName::defaultPermissions()`; modul baru = tambah case
   + mapping + jalankan `RolePermissionSeeder` (tanpa migrasi).
7. **Angka dihitung, bukan diketik.** Rumus hanya di service kalkulasi
   (mis. `OperasiCalculator`, `*Sheet`), dipakai bersama oleh halaman, PDF,
   Excel, dan laporan. Yang disimpan hanya input dan **koreksi** pengguna.
8. **Registry, bukan controller per laporan.** Laporan, master data, form
   generik, dan sheet jadwal didaftarkan lewat definisi (registry) dan dirender
   oleh satu controller generik.
9. **Satu view PDF per tabel.** Setiap halaman jadwal/input menyediakan
   `pdfView()`; laporan bulanan menyisipkan view itu sehingga isi laporan selalu
   sama persis dengan halaman input.
10. **Semua mutasi tercatat** di `activity_logs` (siapa, apa, objek, kapan, IP).
11. **Setiap modul bertes.** Feature test menutup happy path, failure path, dan
    pembatasan akses.
12. **Nomor dokumen tetap.** No. Dokumen ISO dan nomor surat BA tidak
    auto-increment; nilainya tetap per jenis dokumen dan dapat diedit.

---

## 3. Ruang Lingkup

### 3.1 Dalam lingkup (sudah berjalan)

| Area | Isi |
|---|---|
| Fondasi | Master UL & unit, mesin, pegawai; role, permission, penugasan; log aktivitas; impersonasi; notifikasi |
| Operator | Presensi berbasis lokasi, Jadwal Shift, Pengajuan (izin/sakit/cuti/tukar shift), Logsheet Mesin, Start Stop Engine, Lembar Mutasi, Resume Statistik |
| Operasi | Akses 1: Jadwal, Input, Laporan Operasi Pembangkit · Akses 2: Input Pengusahaan (BBM, Pelumas, kWh, Rekap, Jam), Berita Acara, TUG 9, Laporan Pengusahaan |
| Pemeliharaan (HAR) | Akses 1: Jadwal, Input, Formulir, Laporan Pemeliharaan Pembangkit · Akses 2: WO/SR, Rencana & Realisasi, Laporan Kegiatan, 13 formulir teknis, Laporan Pengusahaan |
| K3 & Keamanan | Akses 1: Jadwal, Input, Formulir, Monitoring, Laporan K3 Pembangkit · Akses 2: 19 input + 3 formulir Pengusahaan, Laporan Pengusahaan |
| Logistik & Gudang | Jadwal (9), Input (11), Laporan Logistik & Gudang |
| PdM & Maturity Level | Jadwal (4), Input (11), Laporan PdM & Maturity Level |
| Pemantauan | Dashboard, Monitoring (Ringkasan, Kelengkapan Input, Verifikasi Laporan), Portal Pemantauan UP Kendari |
| Laporan | Editor dokumen (Teks/Excel/Pratinjau PDF), alur pengesahan, delegasi penanda tangan |

### 3.2 Dalam lingkup — belum selesai

- **Log Sheet PdM** — kartu "Segera Hadir".
- Ekspor PDF/Excel dan rekap bulanan untuk **Opname BBM / Pelumas / kWh** (input
  harian sudah berjalan, §8.1).
- Data modul pada **Dashboard** (HAR, K3, Logistik, PdM, dan grafik Operasi)
  masih data contoh (`DashboardDummyData`); angka unit, user, aktivitas, dan
  kelengkapan input sudah nyata.

### 3.3 Di luar lingkup saat ini (fase lanjut)

- Integrasi langsung ke **WPC PLN** (Work Planning & Control) untuk WO/SR.
- Penarikan otomatis logsheet operator ke data harian Operasi (`LogsheetAggregator`).
- Parameter logsheet khusus PLTM/PLTG.
- Modul **Project** dan **Laporan Unit/Project** generik (permission sudah ada,
  UI belum).
- Laporan resmi PLN sheet bernomor 1–14 (EXSUM … REALPROD) dari workbook Operasi.
- Master data Logistik dan asesmen Maturity Level PdM.

---

## 4. Struktur Organisasi & Unit

```text
PLN UP Kendari (kantor induk)
 ├── UL PLTD Poasia
 │    ├── PLTD Poasia
 │    └── PLTD Poasia Containerized
 ├── UL PLTD Bau-Bau
 │    ├── PLTD Bau-Bau        ├── PLTM Rongi
 │    ├── PLTD Wangi-Wangi    ├── PLTM Winning
 │    ├── PLTD Raha           ├── PLTU Baruta
 │    ├── PLTD Ereke          └── PLTMG Bau-Bau
 ├── UL PLTD Kolaka
 │    ├── PLTD Kolaka         ├── PLTM Mikuasi
 │    ├── PLTG Kolaka         └── PLTD Lanipa-Nipa
 │    └── PLTM Sabilambo
 └── (belum dipetakan ke UL — dikelola langsung UP Kendari)
      PLTU Moramo, PLTMG Kendari, PLTD Wua-Wua, PLTD Langara,
      PLTM Langara, PLTD Pasarwajo, PLTD Ladumpi
```

| Istilah bisnis | Entitas | Tabel |
|---|---|---|
| Unit Layanan (UL) | `ServiceUnit` | `service_units` |
| Unit Pembangkit | `Unit` | `units` (`service_unit_id` nullable) |
| Mesin | `Machine` | `machines` |
| Pegawai | `Employee` | `employees` |

- **Tipe unit:** PLTU, PLTD, PLTG, PLTM, PLTMG.
- **Kontrak unit:** 11 KIT atau 6 KIT (diatur di Master Unit).
- **Hak Akses Unit** (Master Unit): saklar per unit, mis. apakah operator/Project
  Leader unit itu mengisi input Pengusahaan Operasi (default aktif; unit
  containerized default nonaktif).
- **Titik absensi** per unit: lintang, bujur, radius (default 300 m).
- Satu unit memiliki jabatan tunggal (singleton) per unit: Manager UL (per UL),
  TL Operasi/Pemeliharaan/K3, Koordinator ×5, Project Leader, Office ×5, PIC PdM.
  Jabatan aktif yang sama tidak boleh dipegang dua pegawai.

---

## 5. Pengguna, Role & Hak Akses

### 5.1 Konsep dua akses

Divisi **Operasi, Pemeliharaan, dan K3** memiliki dua akses terpisah:

| | Akses 1 — Laporan Project | Akses 2 — Pengusahaan |
|---|---|---|
| Pemegang | Koordinator & Office divisi, Project Leader | Team Leader & Staf divisi |
| Isi | Jadwal, Input, Formulir, Master, **Laporan {Divisi} Pembangkit** | Menu Pengusahaan (input & formulir/BA), **Laporan Pengusahaan** |
| Permission | `{m}.input.*`, `{m}.laporan.view`, `{m}.master.*` | `{m}.pengusahaan.view`, `{m}.pengusahaan.write` |

Team Leader tetap memegang `{m}.laporan.view` **hanya untuk menyetujui** Laporan
Pembangkit; TL tidak diberi permission input Akses 1. Kedua laporan dibuka dari
satu pintu: menu **Laporan** modul.

**Logistik & Gudang** dan **PdM & Maturity Level** hanya punya satu akses
(`logistik.*`, `pdm.*`), dipegang role `tl_logistik` dan `tl_pdm`.

### 5.2 Daftar role sistem

| Role (kode) | Scope | Ringkasan akses default |
|---|---|---|
| Super Admin (`super_admin`) | Global | Semua permission (bypass gate, termasuk permission baru) |
| Manager UL (`manager_ul`) | Unit Layanan | Lihat unit/mesin/pegawai di UL-nya, kelola mesin & pegawai, ubah unit, lihat user & log aktivitas, lihat semua laporan modul & Pengusahaan, lihat Operator, Portal & Monitoring, **mengesahkan** laporan |
| TL Operasi (`tl_operasi`) | Unit | Akses 2 Operasi (tulis), menyetujui Laporan Operasi, lihat logsheet/mutasi/jadwal shift operator |
| TL Pemeliharaan (`tl_pemeliharaan`) | Unit | Akses 2 Pemeliharaan (tulis), menyetujui Laporan Pemeliharaan (juga Logistik & PdM), lihat Executive Summary |
| TL K3 & Keamanan (`tl_k3`) | Unit | Akses 2 K3 (tulis), menyetujui Laporan K3, Monitoring K3 |
| Staf Operasi / Pemeliharaan / K3 (`staf_*`) | Unit | Akses 2 divisi (tulis), mengajukan Laporan Pengusahaan |
| Koordinator Operasi (`koordinator_operasi`) | Unit | Akses 1 Operasi penuh + master Operasi; awasi logsheet/mutasi; **kelola Jadwal Shift & absensi** |
| Koordinator Pemeliharaan (`koordinator_pemeliharaan`) | Unit | Akses 1 Pemeliharaan penuh + master HAR + Executive Summary |
| Koordinator K3 (`koordinator_k3`) | Unit | Akses 1 K3 penuh + master K3 + Monitoring K3 |
| TL Logistik & Gudang (`tl_logistik`) | Unit | Modul Logistik penuh (dipakai akun Koordinator/Office Logistik) |
| TL PdM & Maturity Level (`tl_pdm`) | Unit | Modul PdM penuh (dipakai akun Koordinator/PIC PdM) |
| Project Leader (`project_leader_operasi`) | Unit | Semua hak operator + kelola Jadwal Shift & absensi + presensi + input Operasi lapangan + input Pengusahaan Operasi + kelola daftar Material & Peralatan + lihat **semua** Laporan Project (tanda tangan "Mengetahui") + membuat Daily Meeting Pemeliharaan |
| Operator (`operator`) | Unit | Menu Operator (absen, logsheet, start stop, mutasi, pengajuan, resume) + Input Operasi lapangan; **tanpa** Master Data dan **tanpa** input Pengusahaan |
| Harmes / Harlist (`harmes`, `harlist`) | Unit | Absen + halaman Input/Formulir Pemeliharaan lapangan (per halaman) |
| Site Leader (`site_leader`) | Unit | Role warisan Fase 1 (laporan unit/project generik) |
| Manager UP (`manager_up`) | Global | Portal; lihat & unduh seluruh input dan laporan semua modul di semua unit — **hanya melihat** |
| TL Operasi / Pemeliharaan / K3 UP (`tl_*_up`) | Global | Portal; lihat input, monitoring & laporan final bidangnya — hanya melihat |
| Asman Operasi / Pemeliharaan / K3 (`asman_*`) | Global | Sama dengan TL UP bidangnya — hanya melihat |

Catatan:
- Bidang Pemeliharaan di kantor induk mencakup Logistik & PdM.
- Semua role (kecuali Super Admin) memegang permission notifikasi
  (`notifikasi.jadwal|absensi|kejadian|pekerjaan|laporan`).
- Matriks default di atas hanya titik awal. **Super Admin dapat mengubah setiap
  role per menu** di Administrasi → Role & Akses tanpa deploy ulang.
- Satu user boleh memegang lebih dari satu penugasan (`role_assignments`,
  ke UL atau ke unit sesuai scope role).

### 5.3 Katalog permission (148)

| Grup | Permission |
|---|---|
| Master Data | `service_unit.*`, `unit.*`, `machine.*`, `employee.*` (`view_any`, `view`, `create`, `update`, `delete`) |
| Manajemen Akses | `user.*`, `role.*` (+ `role.assign`) |
| Monitoring | `activity_log.view_any`, `activity_log.export`, `monitoring.view` |
| Laporan Unit, Project, Laporan Project | `report_unit.*`, `project.*`, `report_project.*` (termasuk submit/approve/export) |
| Operator | `operator.logsheet.view|write`, `operator.absensi.view|write`, `operator.presensi`, `operator.mutasi.view|write` |
| Operasi — Akses 1 | `operasi.input.view|write`, `operasi.laporan.view`, `operasi.master.view_any|manage`, `operasi.material_peralatan.kelola` |
| Operasi — Akses 2 | `operasi.pengusahaan.view|write|input` |
| Operasi — Input Lapangan | `operasi.lapangan.{daily_report, star_stop, feeder, auxiliary, fuel_receipt, kondisi_abnormal, resource_pembangkit, material_peralatan, permit_to_work, flm_monitoring, patrol_check_mesin, checklist_commissioning, unsafe_condition, program_5s5r}` |
| Pemeliharaan — Akses 1 | `har.input.view|write`, `har.laporan.view`, `har.executive.view`, `har.master.view_any|manage` |
| Pemeliharaan — Akses 2 | `har.pengusahaan.view|write` |
| Pemeliharaan — Input Lapangan | `har.lapangan.{work_order, service_request, activity, schedule, attachment, unsafe_condition, rekap_gangguan, abnormal_gangguan, program_5s5r, patrol_check_pemeliharaan, patrol_check_parameter, prelube_test, hydrotest, timing_injection_pump, crankshaft_deflection, counter_weight, axial_conrod, clearance_valve, combustion_pressure, injector_pressure, motor_current, vibration, lube_quality, battery_voltage, laporan_gangguan, daily_meeting, logbook_mutasi}` |
| K3 — Akses 1 | `k3.input.view|write`, `k3.laporan.view`, `k3.monitoring.view`, `k3.master.view_any|manage` |
| K3 — Akses 2 | `k3.pengusahaan.view|write` |
| Logistik | `logistik.input.view|write`, `logistik.laporan.view`, `logistik.master.view_any|manage` |
| PdM | `pdm.input.view|write`, `pdm.laporan.view`, `pdm.master.view_any|manage` |
| Notifikasi | `notifikasi.jadwal|absensi|kejadian|pekerjaan|laporan` |
| Portal | `portal.view`, `portal.readonly` |
| Sistem | `setting.manage` |

### 5.4 Model otorisasi

Setiap aksi harus lolos **dua lapis**:

1. **Kapabilitas** — user memegang permission aksi tersebut.
2. **Scope** — objek berada di unit yang boleh diakses user
   (`global` = semua unit; `service_unit` = semua unit di bawah UL yang
   ditugaskan, termasuk unit baru; `unit` = unit yang ditugaskan).

Aturan tambahan:
- **Read-only kantor induk.** User dengan `portal.readonly` ditolak untuk semua
  request non-GET, kecuali logout, notifikasi, profil, kata sandi, dan 2FA.
  Halaman menyembunyikan tombol ubah bila `can_write && !isReadOnly()` tidak
  terpenuhi.
- **Saklar per unit.** Hak "operator mengisi Pengusahaan Operasi" ditegakkan dua
  lapis: menu hilang bila tidak ada unit user yang mengizinkan, dan request untuk
  unit yang nonaktif ditolak.
- **Halaman lapangan.** Operator dan Harmes/Harlist tidak memegang
  `{m}.input.*`; mereka dibuka per halaman lewat `{m}.lapangan.{key}`. Pemegang
  izin modul (Koordinator/TL) tetap lolos lewat izin modul.
- **Invariant struktural** (role sistem tidak bisa dihapus, user tidak bisa
  menghapus akunnya sendiri) ditegakkan eksplisit di controller karena Super
  Admin mem-bypass policy.

---

## 6. Navigasi Aplikasi

### 6.1 Sidebar desktop

| Grup | Menu (tampil sesuai permission) |
|---|---|
| Umum | Dashboard |
| Monitoring | Ringkasan Monitoring, Kelengkapan Input, Verifikasi Laporan, Log Aktivitas |
| Master Data | Unit Layanan, Unit Pembangkit, Master Mesin, Master Pegawai, Master Operasi, Master Pemeliharaan, Master K3 & Keamanan |
| Operator | Absensi, Logsheet Operator, Start Stop Engine, Opname BBM, Opname Pelumas, Opname kWh, Lembar Mutasi, Jadwal Shift, Pengajuan Absensi, Resume Statistik |
| Input lapangan | Halaman Operasi/Pemeliharaan yang dibuka untuk petugas lapangan (hanya bila user tidak memegang izin modul penuh) |
| Operasi | Jadwal, Input, Pengusahaan, Laporan |
| Pemeliharaan | Jadwal, Input, Formulir, Pengusahaan, Laporan (Daily Meeting untuk Project Leader) |
| K3 & Keamanan | Jadwal, Input, Formulir, Pengusahaan, Laporan |
| Logistik & Gudang | Jadwal, Input, Laporan |
| PdM & Maturity Level | Jadwal, Input, Laporan |
| Administrasi | Pengguna, Role & Akses, Penanda Tangan Laporan, Lokasi Absensi |

- Menu **Pengusahaan** per modul dibangun dari `resources/js/lib/pengusahaan-menus.ts`
  (hub `{m}.pengusahaan.index`); seksi Pengusahaan hanya **Input** dan
  **Formulir** (Operasi menampilkan Formulir sebagai "Berita Acara").
- Kantor induk & Manager UL diarahkan ke **Portal Pemantauan** (`/pantau`).

### 6.2 Tampilan HP

- Setiap kelompok role memiliki "shell" HP sendiri (`layouts/mobile/modules.ts`):
  operator, operasi-project, operasi-pengusahaan, har-project, har-pengusahaan,
  k3-project, k3-pengusahaan, logistik, pdm. Manager UL tidak punya shell HP
  (memakai aplikasi penuh, baca saja).
- Beranda HP: header biru ringkas → pencarian → chip grup → kartu menu per grup
  (tanpa Akses Cepat — dihapus 2026-11-05).
- Halaman input, jadwal, dan formulir di HP **langsung bisa diisi** (semua modul):
  tanpa deskripsi halaman, kartu ringkasan, maupun filter Unit/Bulan/Tahun — unit
  dari akun, periode = sekarang, shift logsheet otomatis dari regu. Pilihan yang
  memang bagian input (mesin, minggu, periode minggu, hari, lokasi) tetap tampil.
  Berlaku untuk pengguna di dalam shell HP; Manager UL dan Super Admin (tanpa
  shell) tetap bisa memilih unit/periode. Halaman Laporan, Berita Acara, dan TUG 9
  tetap memiliki filter periode. Diatur terpusat di `useSimplifiedMobileInput`
  (`OperasiSelect`, `MetaField`, `PageHeader`, `SummaryCard`, ubin ringkasan).
- Tabel panjang tampil sebagai kartu (15 baris per batch, buka satu baris saat
  diedit); matriks tanggal memakai pita tanggal / timeline.
- Halaman jadwal di HP terbuka dalam **mode lihat**; mengubah lewat tombol
  "Ubah Jadwal".

---

## 7. Kebutuhan Fungsional — Umum & Administrasi

### 7.1 Autentikasi & akun

- Login email + kata sandi; tamu diarahkan ke halaman login (tidak ada landing
  page publik).
- Lupa/reset kata sandi, verifikasi email, **2FA (TOTP + recovery code)**, dan
  **passkey (WebAuthn)** — Laravel Fortify.
- Akun nonaktif ditolak; login terakhir (waktu & IP) dicatat.
- Profil: ubah nama/email, kata sandi, 2FA, passkey, preferensi notifikasi,
  dan perangkat push.

### 7.2 Dashboard

- Satu bagian grafik per modul (Operasi, Pemeliharaan, K3, Logistik, PdM), tampil
  sesuai permission; Super Admin melihat semua.
- Ringkasan nyata: jumlah unit, user, status unit, aktivitas, dan **kelengkapan
  input** per modul/menu, dibatasi unit yang terlihat user.
- Grafik modul saat ini memakai data contoh (ditandai `isDummy`) — lihat §15.

### 7.3 Notifikasi

- Kanal: **in-app** (lonceng + halaman Notifikasi) dan **Web Push** (perangkat
  yang didaftarkan). PWA (`manifest.webmanifest`, `sw.js`) agar dapat dipasang
  di HP.
- Kategori: Jadwal, Absensi, Kejadian, Pekerjaan, Laporan — masing-masing
  permission `notifikasi.*` dan dapat dimatikan per user.
- **Pengingat terjadwal** (`notifications:send-reminders`, tiap 5 menit, butuh
  cron `schedule:run`):
  - Absensi — dari Jadwal Shift + presensi (zona WITA), hanya akun
    `operator.presensi`.
  - Jadwal — ringkasan pagi per modul & unit untuk pemegang `{m}.input.write`
    yang dapat mengakses unit; baris tugas personal ke akun pegawai itu.
  - Dedup per user lewat `reminder_logs`.
- **Notifikasi kejadian** setelah simpan: Kondisi Abnormal baru/berubah, Unsafe
  Action & Condition (temuan baru → K3; ditutup → pelapor), WO/SR baru atau
  status berubah, kecelakaan K3, setiap langkah alur laporan (ke penanda tangan
  berikutnya + pengaju). Penerima = user aktif yang berhak & dapat mengakses unit,
  bukan pelaku dan bukan Super Admin. Tautan hanya diberikan bila penerima boleh
  membuka halamannya.

### 7.4 Master Data

| Menu | Isi | Akses default |
|---|---|---|
| Unit Layanan | Kode, nama, deskripsi, aktif | Super Admin (Manager UL lihat) |
| Unit Pembangkit | Kode, nama, tipe, UL, kapasitas, lokasi, status, kontrak (11/6 KIT), titik absensi, **Hak Akses Unit** | Super Admin; Manager UL ubah |
| Master Mesin | Mesin per unit, jenis BBM (`fuel_type`), jenis pelumas (pivot), data penggerak & generator, daya | Super Admin, Manager UL |
| Master Pegawai | NIP, nama, jabatan (`EmployeePosition`), unit, akun user, **regu A–D**, Leader Shift, tanda tangan | Super Admin, Manager UL |
| Master Operasi | Feeder, Pasokan Cadangan, Tangki BBM (+ **Jenis Tangki**: Storage / Setlink / Transfer / Service), Jenis Pelumas, **kWh Meter** (kategori kWh Produksi / kWh PS / Susut Trafo, opsional mesin), Faktor Kalibrasi, Jenis BBM, Kode Status (global), Kode Absensi/Shift (global), Pola Shift per Regu | `operasi.master.*` |
| Master Pemeliharaan | Jenis Pemeliharaan, Siklus, Status WO, Work Group, Kategori SR (global) | `har.master.*` |
| Master K3 & Keamanan | Jenis Kegiatan K3, Kategori APD, Kategori Alat Sertifikasi (global); Lokasi Patroli, Pos Keamanan, Fasilitas Darurat, Item APD, APAR/APAB, Kotak P3K, Item Checklist Inspeksi (per unit) | `k3.master.*` |

Aturan:
- Satu Leader Shift aktif per unit + regu; leader wajib punya regu.
- Jumlah anggota regu tidak dibatasi.
- Master turunan memakai satu engine generik (skema field → validasi & form
  otomatis).

### 7.5 Administrasi

- **Pengguna** — CRUD user, aktif/nonaktif, penugasan role ke UL/unit, beralih
  akun (impersonasi) oleh Super Admin.
- **Role & Akses** — buat/ubah role non-sistem, matriks permission per menu
  dikelompokkan per grup (§5.3).
- **Penanda Tangan Laporan** — delegasi jabatan penanda tangan suatu unit ke
  pemegang jabatan yang sama di unit lain (satu tingkat, tanpa rantai), mis.
  TL Pemeliharaan PLTD Poasia juga menandatangani PLTD Poasia Containerized.
  Delegasi dapat memberi role unit-scope penanda tangan di unit itu dan
  mencabutnya kembali saat delegasi dihapus.
- **Lokasi Absensi** — titik koordinat & radius per unit (`setting.manage`).
- **Log Aktivitas** — daftar & ekspor aktivitas (dibatasi scope); mencatat
  `impersonated_by` bila aksi dilakukan saat impersonasi.

### 7.6 Impersonasi (beralih akun)

- Hanya Super Admin aktif; tidak ke Super Admin lain, user nonaktif, diri
  sendiri, atau bertingkat. Konfirmasi kata sandi (kecuali baru dikonfirmasi).
- Maksimal 60 menit lalu kembali otomatis; berhenti total bila admin kehilangan
  role/dinonaktifkan.
- Selama impersonasi diblokir: ubah/hapus profil, kata sandi, 2FA, passkey,
  push device, dan switcher.
- UI: banner impersonasi + Account Switcher (Ctrl+Shift+K).

---

## 8. Kebutuhan Fungsional — Modul Kerja

Pola umum setiap halaman jadwal/input:
- Filter **Unit / Bulan / Tahun** (desktop), hanya unit yang boleh diakses.
- Tabel ala-Excel; simpan, dan tombol **PDF** & **Excel**.
- Bila belum ada data, halaman menampilkan **isian bawaan** (default/master) dan
  isian itulah yang tercetak di laporan.
- Setiap halaman menyediakan `pdfView()` untuk laporan bulanan.

### 8.1 Operator (modul `operator`)

Layer input lapangan operator — modul tersendiri (bukan sub-Operasi).

| Menu | Fungsi | Siapa |
|---|---|---|
| **Absensi** (presensi) | Absen masuk/pulang dengan lokasi GPS di dalam radius unit | Operator, Project Leader, Harmes, Harlist |
| **Logsheet Mesin** | Satu lembar per mesin per hari; tabel slot waktu × parameter; tombol **Isi Data** → pilih jam → isi parameter → simpan (upsert per slot); **Kirim** mengunci lembar | Operator & PL tulis; TL/Koordinator/Manager UL lihat |
| **Start Stop Engine** | Catat start/stop mesin beserta penyebab; stop karena gangguan = Jumlah Kali Gangguan | Operator & PL |
| **Opname BBM** | Per unit + tanggal: **Opname Fisik** (tangki Storage & Setlink: cm, liter) dan **Opname Administrasi** (tangki Service: cm, liter; Flowmeter BBM per mesin: stand awal, stand akhir, pemakaian) | Operator & PL tulis; TL/Koordinator/Manager UL lihat |
| **Opname Pelumas** | Per unit + tanggal, tiap jenis pelumas unit: stand awal, stand akhir, sub total | idem |
| **Opname kWh** | Per unit + tanggal: kWh Produksi, kWh PS, Susut Trafo (nama kWh, stand awal, stand akhir, sub total) + ringkasan kWh PSST, kWh netto, SFC bruto & netto (§10.2) | idem |
| **Lembar Mutasi** | Lembar serah terima shift: mesin (level/tambah BBM, pelumas, status operasi/standby/gangguan), tangki, peralatan, kejadian; **Serahkan** + paraf, **Terima** + paraf (regu lain) mengunci lembar | Leader Shift / PL / TL tulis; operator biasa lihat |
| **Jadwal Shift** | Satu lembar per unit + bulan: Kerja Shift (regu A–D) & Non Shift; kuas kode + klik/geser sel; **Isi Otomatis** | Koordinator Operasi, PL (tulis); Operator, TL, Manager UL (lihat) |
| **Pengajuan Absensi** | Izin, sakit, cuti, **tukar shift** (dengan konfirmasi pengganti), lampiran; disetujui pemegang `operator.absensi.write` | Operator & PL ajukan |
| **Resume Statistik** | Kehadiran, tukar shift, dan pekerjaan per pegawai (operator hanya melihat dirinya) | Operator, PL, pengawas |

Detail Logsheet:
- Parameter dari master `logsheet_parameters` (saat ini `plant_type = all`):
  Load, Coolant Temp 1/2, Lube Oil Temp/Press, Winding Temp L1–L3, TEG Battery,
  Ampere R/S/T, Cos Phi, Hour Meter, Freq, TEG, KVAR, kWh Meter, Flow Meter
  IN/OUT, Daily Tank Level, Bearing Generator Temp.
- Slot waktu = nilai: 01:00–24:00 + 17:30, 18:30, 19:30, 20:30, 21:30.
- Shift A–D; di HP shift terisi otomatis dari regu pada Jadwal Shift (atau regu
  pegawai).
- Status draft → submitted; lembar terkirim tidak bisa diubah operator.
- Ekspor PDF & Excel untuk Logsheet, Lembar Mutasi, Jadwal Shift.

Detail Opname (BBM, Pelumas, kWh):
- Nama baris diambil dari **master per unit** (setiap unit berbeda): Tangki BBM
  (dikelompokkan menurut jenis tangki; tanpa jenis = Storage; tangki Transfer
  tidak di-opname), Jenis Pelumas, dan kWh Meter (kWh Produksi & PS memakai nama
  mesin selama unit belum mengisi master kWh Meter; Susut Trafo wajib dari master).
- Stand awal otomatis = stand akhir baris yang sama pada opname sebelumnya;
  sub total = stand akhir − stand awal (dihitung, tidak disimpan).
- Satu lembar per unit + tanggal + jenis (`operator_opnames.data` JSON); lembar
  dikosongkan seluruhnya = dihapus.

### 8.2 Operasi — Akses 1 (Laporan Project)

**Jadwal (10):** Jadwal FLM · Program 5S 5R · Meeting Shift · Inventarisasi Tools
& Material Operasi · Pembuatan IK · Pembuatan Data Teknis Pembangkit · Pemeriksaan
Instalasi Blackstart · Commissioning Test Mesin · Commissioning Test Peralatan Non
Mesin · Pelaksanaan Performance Test Mesin.

**Input (9):** Kondisi Abnormal & Gangguan · Instruksi Kerja (IK) · Material &
Peralatan · Permit to Work (PTW) · Monitoring FLM · Patrol Check Mesin · Checklist
Commissioning Test Mesin · Unsafe Action & Unsafe Condition · Program 5S 5R
Pengoperasian KIT.

Detail penting:
- **Kondisi Abnormal & Gangguan** — indikasi, tanggal, abnormal/gangguan + durasi
  (gangguan: jam mulai–selesai, mesin, peralatan), parameter terkait,
  **kronologi** (khusus gangguan; kosong untuk abnormal), tindak lanjut jangka
  pendek (SR/FLM/lainnya + keterangan) & panjang, dampak sistem & pembangkit,
  dan **satu foto eviden** (abnormal maupun gangguan; foto lama terhapus saat
  diganti/dihapus); desktop = tabel isian, HP = form inline. Data ini juga
  dibaca langsung oleh tabel Abnormal & Gangguan Pemeliharaan (§8.4).
- **Material & Peralatan** — daftar item & stok dikelola Koordinator atau Project
  Leader; operator hanya mengisi **cek kelengkapan** (Lengkap / Tidak Lengkap /
  Tidak Ada) + uraian kondisi (wajib bila tidak lengkap).
- **Monitoring FLM** — mesin/peralatan, tanggal, masalah, kondisi awal &
  akhir, catatan, status Open/Close, foto awal & akhir.
- **Unsafe Action & Condition** — sumber `operasi`; satu tabel bersama dengan HAR
  dan terbaca langsung oleh K3.
- **IK** — editor dokumen IK berbasis template, PDF sesuai format.
- Halaman yang diisi operator dibuka per halaman lewat `operasi.lapangan.*`.

**Laporan Operasi Pembangkit** — lihat §9.

### 8.3 Operasi — Akses 2 (Pengusahaan)

Input dikelompokkan pada hub:

| Grup | Menu |
|---|---|
| Bahan Bakar | Stand Flow Meter BBM · Pemakaian Bahan Bakar · Persediaan Bahan Bakar · Perincian Bahan Bakar · Rekap Bahan Bakar |
| Pelumas | Pemakaian Pelumas · Pelumas Tambah & Ganti · Persediaan Pelumas · Perincian Minyak Pelumas · Rekap Pelumas |
| (Mesin) | **Jam Pembangkit** (OPS · PO · MO · GGN · Standby, total 24 jam) · Beban Tertinggi · Jumlah Kali Gangguan |
| kWh | Stand kWh Harian · Stand kWh Meter untuk TP · kWh Rekap · Energi Dibangkit · Energi Pemakaian Sendiri · kWh/kCal (tara kalor) |
| Rekap | Ikhtisar Sentral · SFC · SFC Netto · Kinerja Unit Mesin · Data Kinerja Pembangkit Termal · Daftar Inventarisasi Mesin |

**Berita Acara (formulir):** Berita Acara (pemakaian BBM & pelumas bulanan) · BA
Pemeriksaan Fisik Pelumas · TUG 9 Pelumas · TUG 9 BBM.

Aturan menu:
- **Jam Pembangkit**: satu tabel, baris = tanggal, tiap mesin kolom OPS | PO |
  MO | GGN | STANDBY | TOTAL; simpan menulis lima record jam sekaligus. Hari yang
  diisi wajib berjumlah **tepat 24 jam** per mesin (hari kosong dilewati); tombol
  "Isi Standby (sisa 24 jam)" mengisi Standby yang kosong dengan sisa jamnya.
- **Beban Tertinggi** tidak diinput lagi: diambil dari LOAD tertinggi logsheet
  operator; hanya daya mampu & catatan yang disimpan.
- **Jumlah Kali Gangguan** dibaca dari Start Stop Engine (read-only).
- **kWh/kCal** diisi manual, tidak ditautkan ke data lain.
- Halaman rekap (kWh Rekap, Energi, SFC, Ikhtisar, Kinerja, Kinerja Termal,
  Inventaris Mesin) dihitung otomatis; pengguna hanya menyimpan **koreksi**
  (sel ditandai dan bisa di-reset).
- Kolom jenis BBM selalu dari master Jenis BBM unit (via tangki), **tidak pernah
  hardcode HSD/MFO**.
- Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, dan Resource Pembangkit
  **dikeluarkan dari menu** (2026-10-07); route & data tetap ada karena dibaca
  sheet lain dan laporan.
- Simpan memerlukan `operasi.pengusahaan.write` (TL & Staf), atau
  `operasi.pengusahaan.input` (Project Leader) bila unitnya mengizinkan.
  Berita Acara & Laporan Pengusahaan hanya TL & Staf.

**Berita Acara BBM/Pelumas** — format persis dokumen asli: kop UIP & UPK Kendari +
SMT, No. Dokumen SMT-FM-EPI-01.04, nomor surat tetap per template (default global
+ override per unit, menu Template BA), kalimat baku hari/tanggal terbilang,
angka otomatis (persediaan awal carry-over, penerimaan, pemakaian per mesin,
administrasi A−B−C, fisik per tangki, selisih E−D), catatan selisih manual,
penanda tangan Manajer & TL Operasi dari master pegawai. Diedit dalam editor dua
mode lalu diunduh PDF/Excel.

**Laporan Pengusahaan Operasi** — buku bab-bab sesuai urutan arsip unit (Ikhtisar
Sentral … TUG 9); tiap bab = PDF menu terkait, portrait & landscape digabung,
Daftar Isi bernomor halaman. Bab tanpa menu (Beban Harian Tertinggi, Indikator,
FJK Mesin, Gangguan Feeder) mencetak halaman placeholder.

### 8.4 Pemeliharaan (HAR) — Akses 1

**Jadwal (8):** Kegiatan Harian Pemeliharaan · Kegiatan Pemeliharaan P0–P5 · Piket
Pemeliharaan (On Call) · Piket Patrol Check Harian · Meeting Pemeliharaan ·
Pembuatan IK Pemeliharaan · Inventarisasi Tools & Material · Pemeriksaan Instalasi
Blackstart.

**Input (12):** Work Order · Service Request · Log Kegiatan (HARMES) · Lampiran Foto ·
Instruksi Kerja (IK) · Unsafe Action & Unsafe Condition · Rekap Laporan Gangguan ·
Laporan Kondisi Abnormal dan Gangguan Pembangkit · Program 5S 5R Pemeliharaan ·
Laporan Patrol Check Pemeliharaan · Patrol Check Parameter Mesin · **Material &
Peralatan** (sama seperti Operasi: daftar & stok disusun Koordinator Pemeliharaan,
kelengkapan dicek Harmes/Harlist lewat `har.lapangan.material_peralatan`; tabel
`har_material_peralatans`, ikut Laporan Pemeliharaan).

**Formulir (3):** Laporan Gangguan (LH-05) · Daily Meeting · Logbook Mutasi Harian.

Detail penting:
- **Work Order** — satu input dengan tab grup: WO PM / PdM / CM / ENJI (jenis
  pemeliharaan) dan WO Waiting Shutdown / Material & Jasa (alasan tunggu). Satu
  tabel `work_orders`; jangan dipecah (menghindari hitung ganda). Kode jenis,
  work group, status, siklus diketik sebagai kode dan di-resolve server. Bidang
  dari work group (ELEC → Listrik, INST/I&C → Kontrol & Instrumen, CIV/SIPIL →
  Sipil, lainnya Mekanik). Rincian material/jasa per WO.
- **Service Request** — no SR, deskripsi, kategori (CM/FLM/PdM/Cancel), mesin,
  status open/close.
- WO & SR adalah satu sumber data untuk Akses 1 dan Akses 2.
- **Log Kegiatan HARMES** — tanggal, mesin, jenis HAR, hasil, no WO/SR/LH-05/TUG-9,
  keterangan + sub-daftar uraian kegiatan & material.
- **Laporan Kondisi Abnormal dan Gangguan Pembangkit** — baris pertama diambil
  otomatis (dibaca langsung, tidak disalin) dari Input Operasi → Kondisi
  Abnormal & Gangguan unit & bulan yang sama, bertanda "Operasi" dan hanya bisa
  diubah di halaman Operasi; Pemeliharaan menambah kejadiannya sendiri di bawahnya
  (dengan jenis Mesin/Electrical/Sipil). PDF, TOTAL, dan Laporan Pemeliharaan
  memuat keduanya.
- **Daily Meeting** — dibuat Koordinator Pemeliharaan atau Project Leader
  (acara, tanggal, waktu, tempat); peserta absen **tanpa login** via QR
  (`/hadir/{token}`): nama, asal, jabatan, tanda tangan kanvas; satu nama sekali;
  form bisa ditutup.
- Halaman lapangan Harmes/Harlist dibuka per halaman (`har.lapangan.*`).

### 8.5 Pemeliharaan — Akses 2 (Pengusahaan)

- **Input:** Work Order · Service Request · **Rencana & Realisasi** · **Laporan
  Kegiatan Pemeliharaan**.
- **Formulir teknis (13):** Checklist Prelube Test · Checklist Hydrotest · Timing
  Injection Pump · Defleksi Crankshaft · Baut Counter Weight · Axial Conrod & Baut
  Conrod · Clearance Valve · Tekanan Pembakaran · Tekanan Pengabutan Injektor ·
  Arus Kerja Elektro Motor · Tekanan Vibrasi · Kualitas Pelumas · Tegangan
  Baterai.

Detail:
- **Rencana & Realisasi** (FMKD-314-10.3.1-A1) — enam tab: HAR realisasi/rencana
  (kode P0–P5 per hari + durasi), Air rencana/realisasi, Pelumas
  rencana/realisasi (tanda v); jam operasi, keterangan, catatan status (mis.
  mesin keluar) menggantikan grid hari. Keenam tab disimpan sekali.
- **Laporan Kegiatan Pemeliharaan** (FMKD-314-10.3.3-A3) — kategori Mesin dan
  Listrik & Kontrol; grup per mesin/judul/jenis HAR/uraian; No. SR atau BA;
  No. WO atau SPKI. Terpisah dari Log Kegiatan HARMES Akses 1.
- **Formulir teknis** — per unit + mesin + tanggal uji; kop statis; penanda
  tangan dihitung ulang tiap simpan dari Manager UL, TL Pemeliharaan, Staf
  Pemeliharaan unit; bisa diisi juga oleh Harmes/Harlist.
- Halaman Pengusahaan tidak memuat input tanda tangan/paraf.

### 8.6 K3 & Keamanan — Akses 1

**Jadwal (6):** On Call K3L · Kegiatan Rutin Harian, Mingguan & Bulanan K3L KIT ·
Pekerjaan Rutin K3L & Lingkungan 11 KIT · Patrol Check Harian K3L & Lingkungan ·
Instruksi Kerja K3L · Pembuatan IK K3.

**Input (20):** Time Frame · Laporan Kecelakaan · Inspeksi Checklist · Inspeksi
APAR/APAB · Patrol Check Sistem Fire Fighting · Inspeksi Hydrant · Daftar CCTV ·
Inspeksi Fire Alarm · Inspeksi Rambu-Rambu K3 & B3 · Pemeriksaan Emergency
Facility · Kesiapan APD · Daftar Inventaris APD · Logbook Pemantauan Air Limbah ·
Dokumen IK K3 · Fasilitas Darurat · Patroli Keamanan · Sertifikasi Peralatan ·
Lampiran K3 · Kondisi K3 (Unsafe Action & Unsafe Condition) · Rekap 5S5R Seluruh
Divisi.

**Formulir (5):** Atribut, Peralatan, Administrasi & Sarana Prasarana · Kontrol K3
Mingguan · Metode Pengujian Peralatan · Pemeliharaan TPS LB3 · Pemeliharaan Oil
Trap.

**Monitoring K3:** badge status sertifikat & APAR — Aktif (hijau), Mendekati
expired ≤ 60 hari (kuning), Expired (merah) + sisa hari; ringkasan bulan
(kecelakaan NIHIL/korban, kegiatan terealisasi, total scan patroli, % kesiapan
darurat, jumlah expired/mendekati). Label di sistem, bukan push.

Detail penting:
- **Laporan Kecelakaan** — PAK/PAHK per periode dengan tombol cepat **Tandai
  NIHIL**.
- **Inspeksi Checklist** — pola generik `inspections` + `inspection_results`
  dengan item dari master per kode form (bukan satu tabel per form).
- **Patroli Keamanan** — matriks lokasi (POA1…) × tanggal, jumlah scan + total
  kumulatif.
- **Kondisi K3** dan **Rekap 5S5R** membaca langsung (tidak menyalin) temuan
  Unsafe & data 5S5R divisi Operasi, HAR, Logistik, PdM.

### 8.7 K3 & Keamanan — Akses 2 (Pengusahaan)

- **Input (19):** Time Frame Pengusahaan · Daftar Sertifikasi Peralatan · Hari &
  Jam Kerja Karyawan · Laporan Bulanan Jam Kerja Karyawan · Pemeriksaan Emergency
  Facility · Kecelakaan Instalasi · Kecelakaan Masyarakat Umum · Daftar Alat
  Tanggap Darurat · Kondisi APAR dan APAB · Alat Pemadam Api Tradisional (APAT) ·
  Pemeriksaan Hydrant · Inventaris APD · Pemeriksaan Peralatan P3K · Pemeriksaan
  Fire Alarm · Laporan Apel Satuan Keamanan · Inspeksi Rambu K3 · Patrol Check
  Security · Laporan Kondisi CCTV · Laporan Mutasi Buku Tamu.
- **Formulir (3):** Metode Pengujian Peralatan · Evaluasi Hasil Pengujian
  Peralatan · Inspeksi Tempat Kerja dan Fasilitas.
- Hari & Jam Kerja Karyawan mengikuti form resmi FMZ-08.4.4.10.

### 8.8 Logistik & Gudang

**Jadwal (9):** Kegiatan Logistik & Gudang (R rencana / D realisasi; I Mesin, II
Mingguan, III Bulanan, IV Non Rutin) · Shift Operator Logistik & Gudang (P/OF/S/I/C/M
+ rekap & % kehadiran) · Piket Patrol Check (On Call) · Pelaksanaan 5S5R · Meeting ·
Inventarisasi Tools dan Material Bagian Lainnya · Pembuatan IK (tahunan, kolom
Jan–Des) · Pemeliharaan Logistik dan Gudang (bawaan: rencana tiap Senin) · Piket
Patrol Check Logistik & Gudang (bawaan Senin & Jumat; libur digeser ke hari kerja
berikutnya).

**Input (11):** Rekomendasi Logistik & Gudang · Kegiatan Patrol Check · Inspeksi
Checklist 5S5R (15 pelaksanaan, 3 foto eviden/item) · Input Data Aplikasi
Pembangkit · Maturity Level Logistik & Gudang (level 0–5) · Inventaris Lainnya
(N/T per tanggal, target, realisasi, kinerja) · Peralatan, Material dan Tools
(4 pelaksanaan × baik/rusak/hilang) · Kondisi Stok Tools dan Material (stok akhir
& ROP dihitung) · Unsafe Action dan Unsafe Condition (foto sebelum/sesudah) ·
Laporan Pendukung · Permit To Work Pembangkit (status OPEN/CLOSE, total).

- Jadwal & input matriks = sheet terdaftar di `LogistikJadwal::SHEETS`; input tabel
  bebas = form di `LogistikForms::ALL`. Tambah halaman lewat definisi, bukan
  controller baru.
- Kolom terhitung (rumus, jumlah terisi, persen) dihitung ulang saat baca, tidak
  disimpan.
- Temuan Unsafe Logistik memberi notifikasi K3 + Logistik sekali per temuan baru.
- Input & jadwal tanpa tanda tangan (menyusul bersama fitur verifikasi).

### 8.9 PdM & Maturity Level

**Jadwal (4):** Kegiatan PdM & MATLEV · Piket Patrol Check PdM KIT · Program 5S 5R ·
Meeting PdM KIT.

**Input (11 + 1 menyusul):** Kesiapan APD Bagian PdM · Monitoring Pemeriksaan &
Pengiriman Sample PdM (A pengiriman, B hasil, C rekap otomatis, D temuan) · Permit
to Work (min. 30 baris, total Open/Close) · Inspeksi Checklist 5S5R PdM · Pengukuran
Kualitas Air Pendingin · Pengukuran Kualitas Pelumas (per mesin) · Pengukuran
Vibrasi Mesin & Generator (per mesin) · Kontrol Material, Peralatan & Tools PdM ·
Realisasi Pemeliharaan Prediktif Bulanan (target/realisasi/kinerja; Sabtu/Minggu/
libur merah) · Patrol Check PdM (28 item, Ya/Tidak/N/A, % sesuai) · Checklist
Patrol Check PdM (area A–E, OK/NOK/N/A, temuan, eviden foto, ringkasan) · *Log Sheet
PdM (segera hadir)*.

- Form sederhana = definisi `PdmForm` di `PdmForms::ALL` (kop, field, section,
  kolom, baris default, total, ringkasan, orientasi, per-mesin) + satu controller
  generik.
- Tipe kolom `check` + `exclusive` = satu centang per baris per grup (ditegakkan
  juga di server).
- Input PdM tanpa tanda tangan; Lembar Pengesahan Laporan PdM tetap ada (nama dari
  pegawai, tanpa TTD).

### 8.10 Monitoring & Portal Pemantauan

- **Ringkasan Monitoring** — gambaran status unit & modul dalam cakupan.
- **Kelengkapan Input** — membaca langsung tabel setiap halaman input lewat
  `InputCatalog` (jenis: bulanan, tahunan, tanggal, harian, harian per mesin,
  periode, jumlah) dengan tautan ke halamannya. Halaman input baru **wajib**
  didaftarkan di katalog.
- **Verifikasi Laporan** — status, umur, dan giliran setiap laporan dari
  `report_workflows`; **macet** = menunggu ≥ 3 hari di satu tahap.
- **Portal Pemantauan** (`/pantau`) — beranda Manager UP, TL/Asman UP, dan
  Manager UL: ringkasan, laporan, dan data input semua unit dalam cakupan,
  difilter per bidang. Untuk role UP bersifat **hanya melihat**.

---

## 9. Laporan & Alur Pengesahan

### 9.1 Jenis laporan

| Laporan | Modul | Penyusun | Isi utama |
|---|---|---|---|
| Laporan Operasi Pembangkit | Operasi (Akses 1) | Koordinator/Office Operasi | I Sampul · II Daftar Isi · III Lembar Pengesahan · IV Resume Statistik (10 kegiatan + grafik batang & lingkaran 3D) · V Laporan Operasi (semua jadwal/input, landscape, termasuk Jadwal Shift Operator) · VI Lampiran |
| Laporan Pemeliharaan Pembangkit | HAR (Akses 1) | Koordinator/Office Pemeliharaan | Executive Summary, SR & WO Summary, rekap WO per jenis, WO tertunda, akumulasi biaya, Rencana vs Realisasi, Log Kegiatan HARMES, lampiran foto, seluruh jadwal/formulir/input |
| Laporan K3 Lingkungan Pembangkit | K3 (Akses 1) | Koordinator/Office K3 | Semua input & formulir (formulir portrait, tabel lain landscape), Dokumen IK, matriks Pembuatan IK tahunan |
| Laporan Logistik & Gudang | Logistik | Office Logistik | I Sampul · II Lembar Pengesahan · III Daftar Isi · IV Jadwal · V Input |
| Laporan PdM & Maturity Level | PdM | PIC PdM | I Sampul · II Daftar Isi · III Lembar Pengesahan · IV Jadwal · V Input |
| Laporan Pengusahaan Operasi/Pemeliharaan/K3 | Akses 2 | Staf divisi | Buku menu Pengusahaan sesuai urutan arsip unit |

Prinsip isi laporan:
- Setiap tabel laporan = view PDF halaman aslinya, disisipkan dengan CSS
  terisolasi; isi laporan selalu sama dengan halaman input.
- Poin tanpa data dan tanpa sumber = tanda **garis merah** (bukan baris "belum
  ada data").
- Portrait & landscape digabung dalam satu PDF; Daftar Isi berisi nomor halaman
  nyata; sampul tanpa nomor.

### 9.2 Editor dokumen

Satu pintu: tombol **Buka Dokumen (Lihat, Edit & Cetak)** di menu Laporan modul.
Editor (`components/document/document-editor.tsx`) memiliki tiga mode:

1. **Teks** (ala-Word, TinyMCE) → PDF.
2. **Excel** (spreadsheet, x-data-spreadsheet) → Excel (.xlsx) & PDF.
3. **Pratinjau PDF** — iframe hasil cetak persis (di HP: tombol buka/unduh).

Tombol **Simpan** dan **Muat Ulang dari Data**. Isi disimpan per unit + jenis +
periode (`*_document_records` / `operasi_report_documents`). No. Dokumen ISO
editable di dalam dokumen.

### 9.3 Alur pengesahan (workflow)

**Laporan Pembangkit (Akses 1)**

```text
Draft ──Ajukan──▶ Diajukan ──Verifikasi (Koordinator)──▶ Verifikasi
      ──Setujui (Team Leader)──▶ Disetujui ──Sahkan (Manager UL)──▶ Final
                    └────────────── Tolak (setiap tahap) ──▶ Ditolak — Perlu Perbaikan
```

- Lembar Pengesahan: Memeriksa (Koordinator divisi) · Menyetujui (Team Leader:
  TL Operasi untuk Operasi, TL K3 untuk K3, TL Pemeliharaan untuk HAR, Logistik,
  PdM) · Mengesahkan (Manager UL).
- Blok tanda tangan di dalam laporan: **Mengetahui** Project Leader · **Dibuat**
  Office divisi; untuk PdM: Koordinator Pemeliharaan + PIC PdM.

**Laporan Pengusahaan (Akses 2)**

- Diajukan oleh Staf divisi → **Menyetujui** Team Leader → **Mengesahkan**
  Manager UL. Tanpa Koordinator, Project Leader, maupun Office.
- Status setelah diajukan: "Diajukan — Menunggu Persetujuan Team Leader".

**Aturan bersama**

- Penanda tangan selalu dicari dari jabatan pegawai unit
  (`ReportSignatories::holder`, sadar-delegasi) — tidak pernah nama hardcode.
- Pegawai penanda tangan dibekukan saat **Ajukan**; perubahan delegasi berlaku
  untuk pengajuan berikutnya.
- Menandatangani hanya oleh akun pegawai pada tahap itu; tidak ada yang
  memproses tahap pertama atas laporan yang ia ajukan sendiri.
- Laporan yang sedang dalam alur terkunci dari edit/muat ulang.
- **Gambar tanda tangan hanya tercetak saat status Final.**
- Setiap langkah tercatat (`report_workflow_logs`) dan menotifikasi penanda
  tangan berikutnya serta pengaju.

---

## 10. Aturan Bisnis & Kalkulasi

### 10.1 Operasi

1. **Carry-over stand awal:** `stand_awal(N) = stand_akhir(N−1)`; tanggal 1 =
   stand akhir hari terakhir bulan lalu (mesin sama). Berlaku untuk kWh produksi,
   kWh pemakaian sendiri, flow meter BBM, air, feeder. Stand awal tidak bisa
   diketik.
2. **kWh produksi** = `(stand_akhir − stand_awal) × faktor kWh`; **kWh netto** =
   produksi − pemakaian sendiri.
3. **Pemakaian BBM** = `(fm_akhir − fm_awal + tambah) × faktor kalibrasi`
   (faktor per unit/mesin, `calibration_factors`).
4. **Cummins flow meter IN/OUT** = `(IN_akhir − IN_awal) − (OUT_akhir − OUT_awal)`
   — rumus terisolasi, menunggu verifikasi tim operasi.
5. **SFC** = liter BBM (semua jenis BBM unit) ÷ kWh produksi (4 desimal); **SFC
   Netto** = liter ÷ kWh netto (3 desimal); 0 bila kWh ≤ 0; total/JMH/unit =
   jumlah liter ÷ jumlah kWh.
6. **Jam:** per mesin per hari OPS + PO + MO + GGN + Standby = **24 jam** (hari
   yang diisi; selain itu ditolak). Jam Siap Operasi = 24 − (OPS + PO + MO + GGN)
   = Standby; tidak disimpan.
7. **Subtotal periode** laporan harian: I = 1–10, II = 11–20, III = 21–akhir.
8. **Persediaan BBM:** periode 1–7, 8–14, 15–22, 23–akhir. Persediaan **pelumas:**
   1–10, 11–20, 21–akhir. Hanya penerimaan, pengiriman (BBM: TUG 8/Kembali Sewa
   SMP, Pinjam THAS; pelumas: TUG 8, TUG 10, Over Flow), dan koreksi saldo awal
   yang disimpan; pemakaian selalu dibaca dari Pemakaian BBM/Pelumas; saldo awal
   dibawa dari saldo akhir bulan lalu.
9. **Administrasi** = awal + terima − pakai − kirim; **selisih** = fisik −
   administrasi. Persediaan awal BA = fisik BA periode lalu.
10. **Pemakaian Pelumas** per sel = tambah + ganti (total dipakai sheet lain).
    Grease = jenis pelumas bersatuan Kg.
11. **Perincian & Rekap BBM** berbagi satu simpanan per jenis BBM sehingga sisa
    selalu sama; BA Pemeriksaan Fisik Pelumas mengambil angka dari Perincian
    Pelumas, liter terhitungnya menjadi default sisa fisik.
12. **Beban Tertinggi** = LOAD (kW) tertinggi per mesin per hari dari logsheet;
    hari tanpa logsheet memakai nilai lama.
13. **Kali Gangguan** = jumlah stop dengan penyebab gangguan (Start Stop Engine).
14. **Tara kalor (kWh/kCal)** = isian manual; total harian, JMH, dan angka unit =
    rata-rata sel terisi (> 0).
15. **Kinerja Termal** (CF, OF, SF, EAF, POF, FOF, FOR, SOF, EFOR, SdOF, EFF) —
    konstanta 860 / 10289 / 0,949 di `KinerjaTermalSheet`.
16. Validasi: field MFO hanya untuk mesin berjenis BBM yang sesuai; stand akhir ≥
    awal kecuali reset meter eksplisit.

### 10.2 Operator

1. **Presensi:** jarak (haversine) ke titik unit ≤ radius; satu absen masuk per
   tanggal kerja (WITA, `Asia/Makassar`); absen pulang menutup absen masuk
   terbuka ≤ 20 jam (shift malam tetap satu record). Absen masuk mencatat kode
   shift dari Jadwal Shift dan menit terlambat; mulai 20:00 dengan jadwal besok
   = M → tanggal kerja besok. Di luar jadwal (OFF/cuti/belum dijadwalkan) absen
   tetap boleh dengan catatan wajib. Tidak ada jendela jam absen.
2. **Kode absensi:** P (Pagi 08–16), S (Sore 16–24), M (Malam 00–08), OFF, C
   (Cuti), SKT (Sakit), I (Izin), A (Alpha). "S" = Sore, berbeda dari "SKT".
3. **Isi Otomatis Jadwal Shift:** pola OFF, OFF, S, S, P, P, M, M; fase default
   A = 0, B = 6, C = 2, D = 4; melanjutkan siklus dari 2 kode terakhir bulan
   lalu. Non-shift: P di hari kerja, OFF Sabtu/Minggu/libur nasional. Kode absen
   (C/SKT/I/A) dan sel hasil pengajuan yang sudah ada tidak ditimpa.
4. **Kode jadwal = rencana; kehadiran = presensi.** Status per hari: hadir,
   terlambat, tidak hadir (kode kerja, tanggal lewat, tanpa absen), menunggu
   (hari ini), di luar jadwal. **% Hadir = hari kerja hadir ÷ hari kerja wajib.**
   Rekap tidak disimpan, dihitung `AttendanceCalculator`.
5. **Pengajuan:** izin/sakit/cuti menulis I/SKT/C pada setiap hari non-OFF; tukar
   shift memindahkan shift (pemohon OFF, pengganti mendapat kode) — pemohon harus
   bekerja pada tanggal itu dan pengganti bebas; opsional tanggal balasan.
   Aturan dicek ulang saat persetujuan; pembatalan pengajuan yang disetujui
   mengembalikan sel.
6. **Lembar Mutasi:** shift pagi 08–16, sore 16–22, malam 22–08 WITA (sebelum
   08:00 = shift malam hari sebelumnya). Lembar baru memuat mesin aktif dan
   membawa tangki, peralatan, dan status dari lembar sebelumnya.
7. **Hari libur** (`holidays`) hanya mewarnai kolom, tidak mengubah shift.
8. **Opname kWh** (rumus user 2026-11-05): kWh PSST = kWh PS + susut trafo;
   kWh netto = total kWh produksi − kWh PSST; SFC bruto = total kWh produksi ÷
   total pemakaian BBM; SFC netto = total kWh netto ÷ total pemakaian BBM
   (kWh/liter). Pemakaian BBM = jumlah pemakaian Flowmeter BBM pada Opname BBM
   tanggal yang sama; SFC kosong bila belum ada.

### 10.3 Pemeliharaan

1. **SR Summary:** jumlah & persentase SR per kategori, status open/close.
2. **WO Summary:** WO terbit vs complete (status `is_closed`).
3. **Rekap WO per jenis** dengan kolom NO, WONUM, DESCRIPTION, REPORT DATE, SCHED
   START, SCHED FINISH, STATUS, WORK GROUP.
4. **Biaya:** default = Σ biaya jasa + material WO per bulan, akumulatif setahun;
   override manual bulanan bila diisi (ditandai di UI).
5. **WO tertunda:** alasan shutdown / material / jasa.
6. **Executive Summary** otomatis: jumlah SR, WO per jenis, % complete, total
   biaya, WO open/tertunda.

### 10.4 K3

1. **% Kesiapan emergency** = ready ÷ total; nilai teks (mis. level air,
   "Ready") ditampilkan apa adanya.
2. **Patroli kumulatif** = jumlah scan per lokasi sepanjang bulan.
3. **Status sertifikat/APAR** dari tanggal uji ulang/exp date: Aktif, Mendekati
   (≤ 60 hari, `config('k3.expiry_warning_days')`), Expired + sisa hari.
4. **Kecelakaan:** total luka ringan/berat/meninggal/kerugian; NIHIL bila kosong.

### 10.5 Logistik & PdM

- Kolom terhitung (stok akhir, ROP, kinerja, persen, jumlah terisi) selalu
  dihitung ulang di backend saat baca.
- Realisasi Prediktif: target, realisasi, dan kinerja dihitung dari hari rencana
  vs realisasi.

---

## 11. Dokumen, Cetak & Ekspor

| Aspek | Ketentuan |
|---|---|
| PDF | dompdf, A4; orientasi mengikuti form (mayoritas landscape) |
| Excel | Dibuat di browser (ExcelJS) dari data halaman, tata letak sama dengan PDF; editor dokumen memakai SheetJS untuk .xlsx. **Setiap halaman input/jadwal/formulir punya Excel**: halaman tanpa ekspor khusus mendapat tombol Excel generik di bilah atas desktop yang mengekspor tabel halaman apa adanya (berkop, sel gabungan, nilai isian & centang) |
| Kop | Logo PLN (`public/logo/sidebar-logo.png`) kiri + logo mitra (`public/logo/mkp.jpg`) kanan, di-embed sebagai data URI base64 (bukan path relatif) |
| Identitas | Nama unit/UL dari master; tidak disalin ke tabel modul |
| No. Dokumen ISO | Tetap per jenis form (mis. FMKD-314-…, SMT-FM-AK3-…, SMT-FM-EPI-01.04, FMZ-…), default dari config, editable di dokumen; tidak auto-generate |
| Nomor surat BA | Tetap per template, default global + override per unit |
| Tanggal | Format Indonesia + terbilang (`App\Support\Indonesian`) |
| Tanda tangan | Dari master pegawai sesuai jabatan; gambar TTD hanya pada laporan Final; input Pengusahaan, Logistik, dan PdM tanpa TTD |

---

## 12. Kebutuhan Non-Fungsional

### 12.1 Keamanan & audit
- Otorisasi dua lapis (permission + scope) di setiap aksi, termasuk endpoint PDF
  & ekspor.
- Middleware read-only untuk role kantor induk; middleware user aktif; saklar per
  unit.
- Rate limit pada aksi sensitif (impersonasi 10/menit, absen meeting QR, uji
  notifikasi).
- Semua mutasi dicatat di `activity_logs`.
- Lampiran & foto di storage (path), bukan blob; lampiran pengajuan di disk
  privat.

### 12.2 Tampilan & pengalaman
- Desktop: tabel input/jadwal bergaya **Excel** — garis hitam (border luar 2px),
  header kuning, input tanpa border dengan fokus kuning muda, tanpa ring biru;
  grid react-data-grid bergaya sama. Tabel kop/dokumen tidak ikut gaya ini.
- Desain korporat datar: radius kecil, tanpa gradien/bayangan kartu, satu aksen
  biru PLN; amber/emerald/merah hanya untuk status. Detail di `design.md`.
- HP: form lapangan ringkas (§6.2); tampilan desktop tidak berubah.
- Mode gelap didukung.
- Bahasa antarmuka: Indonesia.

### 12.3 Kinerja
- Dokumen laporan dimuat sebagai deferred prop dengan skeleton; editor teks dan
  spreadsheet dimuat hanya saat dibutuhkan.
- Tabel panjang di HP dirender bertahap (15 baris per batch).
- SSR Inertia dimatikan di lokal (opt-in `INERTIA_SSR_LOCAL=true`) untuk
  menghindari timeout pengembangan.

### 12.4 Waktu & lokasi
- Zona waktu bisnis absensi & mutasi: **WITA (Asia/Makassar)**; jam aplikasi
  UTC, waktu ditampilkan di klien.

### 12.5 Operasional
- Cron `schedule:run` wajib untuk pengingat; Web Push memerlukan kunci VAPID.
- Deploy: `php artisan migrate`, `npm run build`, seeder role/permission bila
  katalog berubah (`RolePermissionSeeder`, idempoten).

---

## 13. Model Data Inti

```text
service_units   id, code, name, slug, description, is_active
units           id, service_unit_id?, code, name, slug, type, contract, installed_capacity_mw,
                location, status, is_active, latitude, longitude, attendance_radius_m,
                operator_pengusahaan
machines        id, unit_id, name, fuel_type, … (+ machine_lubricant_type)
employees       id, unit_id, user_id?, employee_id (NIP), name, position, division,
                singleton_key, regu?, is_shift_leader, signature_path, is_active
users           … + employee_id (no. pegawai), position, phone, is_active, last_login_at/ip
roles / permissions / permission_role / role_assignments (user, role, service_unit?, unit?)
activity_logs   user_id, event, description, subject, service_unit_id, unit_id, properties, ip, ua
work_modules    registry modul: operasi, pemeliharaan, k3, logistik, pdm, operator
report_workflows (+ _steps, _logs)   status & tanda tangan laporan
report_signer_delegations            delegasi penanda tangan antar-unit
notifications, push_subscriptions, reminder_logs
```

Tabel modul (ringkas, semua ber-`unit_id`):

| Modul | Tabel utama |
|---|---|
| Operator | `operator_logsheets` (+ `_readings`), `logsheet_parameters`, `operator_start_stops`, `operator_mutasis`, `operator_opnames`, `kwh_meters`, `work_schedules` (+ `_entries`), `attendance_codes`, `shift_patterns`, `holidays`, `employee_presences`, `attendance_requests` |
| Operasi | `report_periods`, `feeders`, `auxiliary_sources`, `fuel_tanks`, `bbm_types`, `lubricant_types`, `calibration_factors`, `unit_status_codes`, `daily_engine_reports`, `engine_status_logs`, `daily_feeder_readings`, `daily_auxiliary_readings`, `fuel_receipts`, `operasi_jam_mesin`, `operasi_mesin_harian`, `operasi_persediaan`, `operasi_rekaps`, `operasi_tugs`, `document_templates`, `document_records`, `operasi_report_documents`, tabel jadwal/input `operasi_*` |
| HAR | `maintenance_types`, `maintenance_cycles`, `wo_statuses`, `work_groups`, `sr_categories`, `work_orders`, `service_requests`, `maintenance_costs`, `har_material_peralatans`, `maintenance_activities` (+ tasks, materials), `maintenance_schedules`, `maintenance_attachments`, `har_unsafe_conditions`, `har_laporan_kegiatans`, `har_instruksi_kerjas`, `har_document_records`, tabel formulir/jadwal `har_*` |
| K3 | master per §7.4, `k3_activity_plans`, `accident_reports`, `inspections` (+ results), `fire_extinguisher_checks`, `emergency_facility_checks`, `security_patrols`, `equipment_certificates`, `k3_attachments`, `k3_dokumen_iks`, `k3_document_records`, tabel `k3_*` lainnya |
| Logistik | `logistik_jadwal_rows`, `logistik_form_rows`, `logistik_rekomendasis`, `logistik_document_records` |
| PdM | `pdm_jadwal_harians`, `pdm_jadwal_patrol_checks`, `pdm_jadwal_meetings`, `pdm_jadwal_meta`, `pdm_kesiapan_apds`, `pdm_sample_monitorings` (+ items), `pdm_permit_to_works`, `pdm_form_documents` (+ items), `pdm_realisasi_prediktifs`, `pdm_document_records` |

Aturan data:
- Hasil kalkulasi tidak disimpan sebagai input; yang disimpan hanya koreksi.
- Kolom `date` dibandingkan dengan `whereDate`, bukan string.
- Master global tanpa `unit_id`; transaksi & master turunan memakai trait
  `BelongsToUnit`.

---

## 14. Integrasi & Fase Lanjut

| Item | Kondisi saat ini | Rencana |
|---|---|---|
| WPC PLN (WO/SR) | Akses WO/SR hanya lewat interface `WorkOrderSource`; `ManualWorkOrderSource` aktif, `WpcWorkOrderSource` placeholder; kolom `source` manual/wpc; config WPC kosong | Ganti binding ke WPC setelah detail DB/skema/kredensial diterima — tanpa ubah controller/laporan; jangan hardcode IP |
| Logsheet → data harian Operasi | `LogsheetAggregator` (TODO) + kolom `daily_engine_reports.source` (manual/logsheet) | Tarik beban puncak, stand kWh, jam, flow IN/OUT otomatis — hanya bila diminta |
| Parameter PLTM/PLTG | `logsheet_parameters.plant_type` disiapkan, semua unit pakai `all` | Tambah set parameter per jenis pembangkit |
| Opname BBM, Pelumas, kWh | Input harian berjalan | PDF/Excel, rekap bulanan, dan (bila diminta) tautan ke Persediaan/Stand kWh Pengusahaan |
| Log Sheet PdM | Kartu "Segera Hadir" | Bangun input |
| Dashboard modul | Data contoh | Ganti dengan data nyata per modul |
| Modul Project & Laporan Unit/Project | Permission ada | Perencanaan, progres, milestone, ekspor |
| Laporan resmi PLN 1–14 (Operasi) | Belum | Tambah lewat report registry |
| Master Logistik & asesmen Maturity Level PdM | Belum dirancang | Dirancang bersama user |
| Tanda tangan input PdM/Logistik | Belum | Bersama fitur verifikasi |
| Menu Pengusahaan Operasi yang dikeluarkan | Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource Pembangkit | Akan dibangun ulang oleh user |

---

## 15. Isu Terbuka

1. **Unit tanpa UL** — PLTU Moramo, PLTMG Kendari, PLTD Wua-Wua, PLTD Langara,
   PLTM Langara, PLTD Pasarwajo, PLTD Ladumpi belum dipetakan ke UL.
2. **Kapasitas terpasang, lokasi, dan kode unit resmi** belum lengkap (diisi
   lewat Master Unit).
3. **Akses Project Leader** — apakah Project Leader juga dicabut dari input
   Pengusahaan Operasi dan dari melihat Master Data (seperti Operator,
   2026-10-30)? Menunggu keputusan.
4. **Opname** — (a) arah SFC: Opname kWh memakai rumus user kWh ÷ liter,
   sedangkan SFC Pengusahaan memakai liter ÷ kWh; (b) sub total pelumas dihitung
   stand akhir − stand awal; (c) kWh produksi/PS memakai stand mentah tanpa faktor
   kalibrasi. Perlu konfirmasi.
5. **Rumus Cummins flow meter IN/OUT** — menunggu verifikasi tim operasi.
6. **Katalog kode status Star-Stop & kapasitas tangki** — seed placeholder,
   dilengkapi via master.
7. **Daftar lengkap status WO / kategori SR / siklus dari WPC** — seed
   placeholder.
8. **Ambang "mendekati expired" K3** — default ≤ 60 hari, dapat disesuaikan.
9. **Kalender libur** — data 2026 bersifat indikatif.
10. **Test Jadwal Commissioning di Pemeliharaan** — 8 test lama masih memanggil
    route `har.jadwal.commissioning-test*` yang sudah tidak ada (jadwal
    Commissioning kini di Operasi): hapus atau arahkan ulang? Menunggu
    keputusan.
11. **Bab Laporan Pengusahaan Operasi tanpa menu** — Beban Harian Tertinggi,
    Indikator, FJK Mesin, Gangguan Feeder masih placeholder.

---

## 16. Riwayat Keputusan

| Tanggal | Keputusan |
|---|---|
| 2026-09 (Fase 1–4) | Fondasi akses permission-driven; modul Operasi, HAR, K3 dibangun sejajar memakai ulang RBAC, registry, document engine, dan grid |
| 2026-09-19 | Input PdM tanpa tanda tangan (menyusul fitur verifikasi); dropdown memakai komponen React |
| 2026-09-25 | Menu Laporan Operator dihapus; data absensi tetap dipakai Laporan Operasi |
| 2026-09-30 | Kode Jadwal Shift = rencana; kehadiran & % Hadir dihitung dari presensi |
| 2026-10-01 | Input Harian, Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource Pembangkit, Berita Acara pindah ke Pengusahaan Operasi |
| 2026-10-07 | Seksi Pengusahaan hanya Input & Formulir; kWh & Rekap jadi grup di Input; lima menu Operasi dikeluarkan dari menu (dibangun ulang user); kWh/kCal manual |
| 2026-10-24 | Setiap unit punya operator, koordinator per divisi, project leader & TL; Material & Peralatan dikelola Koordinator/PL, operator hanya mengecek |
| 2026-10-29 | Jam mesin Pengusahaan disatukan jadi **Jam Pembangkit**; HP operator tanpa filter, langsung isi |
| 2026-10-30 | Operator tanpa Master Data dan tanpa input Pengusahaan Operasi; Beban Tertinggi dari logsheet; tiap shift punya Leader Shift yang mengisi/menyerahkan/menerima Lembar Mutasi |
| 2026-11-03 | Menu Operator baru **Opname BBM & kWh** dan **Opname Pelumas** (menu dulu, isi menyusul); semua tabel input/jadwal desktop bergaya Excel (garis hitam, header kuning) |
| 2026-11-04 | Seluruh dokumen alur modul digabung menjadi PRD ini |
| 2026-11-05 | Kondisi Abnormal & Gangguan Operasi: kolom **Kronologi** (khusus gangguan, di antara Parameter Terkait dan TL jangka pendek) dan **Eviden** foto; tabel Abnormal & Gangguan Pemeliharaan mengambil data Operasi + baris tambahan Pemeliharaan |
| 2026-11-06 | Ekspor Excel: Kondisi Abnormal & Gangguan Operasi (khusus, termasuk kronologi & foto eviden) dan tombol Excel generik untuk semua halaman input yang belum punya ekspor (Abnormal & Gangguan HAR, Material & Peralatan, PTW Operasi, Pengusahaan K3/Operasi, Opname, dll.) |
| 2026-11-05 | Akses Cepat di beranda HP dihapus; Material & Peralatan ditambahkan di Input Pemeliharaan; Jam Pembangkit + MO & Standby (total 24 jam); menu Operator Opname BBM, Opname Pelumas, Opname kWh terpisah dengan kolom dari user + master Jenis Tangki & kWh Meter; halaman input HP semua modul tanpa deskripsi, filter unit/periode, dan kartu ringkasan |

---

## 17. Standar Teknis

| Aspek | Keputusan |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Inertia v3 + React 19 + TypeScript |
| Styling | Tailwind CSS v4 + shadcn/ui, token mengikuti `design.md`; kelas global `excel-sheet` untuk tabel input |
| Auth | Laravel Fortify (login, 2FA, passkey, reset & verifikasi email) |
| Otorisasi | Gate + Policy berbasis permission (dibangun sendiri, tanpa paket pihak ketiga) |
| Routing frontend | Laravel Wayfinder (`@/routes`, `@/actions`; generate dengan `--with-form`) |
| Grid | `react-data-grid` + hook paste dari Excel |
| PDF | `barryvdh/laravel-dompdf` + penggabung PDF orientasi campuran |
| Editor dokumen | TinyMCE (self-host) + x-data-spreadsheet; SheetJS & ExcelJS untuk .xlsx |
| QR | bacon/bacon-qr-code (absensi Daily Meeting) |
| Notifikasi | Database + Web Push (VAPID), PWA |
| Database | SQLite (pengembangan), siap MySQL/PostgreSQL |
| Testing | PHPUnit (feature test diutamakan) |
| Code style | Laravel Pint, ESLint, Prettier |
| Aturan teknis rinci | `.ai/rules/**` (dipetakan di `.ai/rules/index.md`) |
