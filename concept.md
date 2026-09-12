# Konsep Aplikasi — Digitalisasi Unit Pembangkitan Kendari

> Dokumen ini adalah acuan konsep (living document). Setiap perubahan struktur data,
> role, atau modul wajib direfleksikan di sini sebelum diimplementasikan.

---

## 1. Ringkasan

Aplikasi manajemen pembangkit listrik di wilayah Sulawesi Tenggara di bawah
**Unit Pembangkitan (UP) Kendari**. Aplikasi mendigitalkan pengelolaan data unit
pembangkit, aktivitas operasi & pemeliharaan, project, serta menghasilkan
**laporan unit** dan **laporan project** dalam format **PDF** dan **Excel**.

Prinsip pembangunan:

> **Modular → Scalable → Auditable → Terkontrol**

---

## 2. Prinsip Arsitektur (WAJIB)

Aplikasi **harus modular dan siap dikembangkan lebih lanjut**. Ini bukan preferensi,
melainkan batasan desain yang mengikat seluruh pengembangan berikutnya.

Aturan yang berlaku:

1. **Domain terpisah per modul.** Setiap kapabilitas bisnis (Access Control, Master
   Unit, Report Unit, Project, Report Project, Activity Monitoring) berdiri sebagai
   modul dengan Model, Policy, Controller, Request, Route, Page, dan Test sendiri.
   Menambah modul baru **tidak boleh** memaksa perubahan pada modul lain.
2. **Permission-driven, bukan role-driven.** Kode aplikasi **tidak pernah**
   memeriksa nama role secara langsung. Pemeriksaan selalu melalui permission/policy
   (contoh: `$user->can('report_unit.approve', $unit)`). Dengan begitu penambahan
   role baru cukup dilakukan lewat data, tanpa menyentuh kode.
3. **Scope terpusat.** Penentuan "unit mana yang boleh diakses user" hanya dihitung
   di satu tempat (`App\Services\AccessControl`) dan dikonsumsi ulang oleh seluruh
   modul melalui query scope. Tidak ada duplikasi logika scope di controller.
4. **Katalog permission deklaratif.** Seluruh permission didefinisikan pada
   `App\Enums\PermissionName` dan disemai ke database. Modul baru hanya menambah
   case baru pada enum + mapping role, lalu jalankan seeder — tanpa migrasi baru.
5. **Enum untuk semua nilai terbatas.** Tipe unit, status, scope, nama role, dan
   permission memakai PHP backed enum agar type-safe dan tersinkron ke TypeScript.
6. **Kontrak frontend stabil.** Halaman React menerima props yang disusun eksplisit,
   bukan model mentah, sehingga perubahan skema database tidak langsung merusak UI.
7. **Semua perubahan tercatat.** Aksi yang bersifat mutasi data dicatat ke
   `activity_logs` untuk kebutuhan audit dan pemantauan Super Admin.
8. **Setiap modul wajib bertes.** Tidak ada modul yang dianggap selesai tanpa
   feature test yang menutup happy path, failure path, dan pembatasan akses.

---

## 3. Struktur Organisasi

```text
UP Kendari
 ├── UL PLTD Poasia
 │    ├── PLTD Poasia
 │    └── PLTD Poasia Containerized
 ├── UL PLTD Bau-Bau
 │    ├── PLTD Bau-Bau
 │    ├── PLTD Wangi-Wangi
 │    ├── PLTD Raha
 │    ├── PLTD Ereke
 │    ├── PLTM Rongi
 │    └── PLTM Winning
 ├── UL PLTD Kolaka
 │    ├── PLTD Kolaka
 │    ├── PLTG Kolaka
 │    ├── PLTM Sabilambo
 │    ├── PLTM Mikuasi
 │    └── PLTD Lanipa-Nipa
 └── (belum ditetapkan — dikelola langsung UP Kendari)
      ├── PLTU Moramo
      ├── PLTD Wua-Wua
      ├── PLTD Langara
      ├── PLTM Langara
      └── PLTD Pasarwajo
```

Terminologi dalam kode:

| Istilah bisnis | Entitas | Tabel |
|---|---|---|
| Unit Layanan (UL) | `ServiceUnit` | `service_units` |
| Unit Pembangkit | `Unit` | `units` |

`Unit.service_unit_id` bersifat **nullable** untuk menampung unit yang belum
dipetakan ke UL manapun (lihat Bagian 11 — Catatan Terbuka).

### 3.1 Tipe Unit

| Kode | Keterangan |
|---|---|
| PLTU | Pembangkit Listrik Tenaga Uap |
| PLTD | Pembangkit Listrik Tenaga Diesel |
| PLTG | Pembangkit Listrik Tenaga Gas |
| PLTM | Pembangkit Listrik Tenaga Minihidro |

---

## 4. Role & Cakupan Akses

Terdapat **6 role sistem**. Setiap role memiliki **scope level** yang menentukan
sampai mana datanya terlihat.

| Role | Scope Level | Cakupan Data |
|---|---|---|
| **Super Admin** | Global | Seluruh UP Kendari — seluruh UL, unit, user, dan aktivitas |
| **Manager UL** | Service Unit | Seluruh unit di bawah UL yang ditugaskan |
| **TL Operasi** | Unit | Unit yang ditugaskan |
| **TL Pemeliharaan** | Unit | Unit yang ditugaskan |
| **Site Leader** | Unit | Unit yang ditugaskan |
| **Operator** | Unit | Unit yang ditugaskan |

Catatan penting:

- Satu user **boleh memegang lebih dari satu penugasan** (contoh: Site Leader di dua
  unit). Penugasan disimpan di `role_assignments`, bukan sebagai kolom di tabel `users`.
- Manager UL ditugaskan ke **UL**, bukan ke unit. Aksesnya otomatis mengikuti seluruh
  unit di bawah UL tersebut, termasuk unit yang ditambahkan kemudian.
- Super Admin tidak memerlukan penugasan unit — aksesnya global.

### 4.1 Super Admin

Super Admin adalah pengendali penuh sistem, dengan tanggung jawab:

1. **Manajemen akses** — membuat user, mengatur role, mengubah matriks permission,
   menugaskan user ke UL/unit, menonaktifkan akun.
2. **Pemantauan aktivitas** — melihat seluruh log aktivitas (siapa, aksi apa, objek
   apa, kapan, dari IP mana) lintas unit.
3. **Pemantauan unit** — melihat seluruh UL dan unit pembangkit beserta status dan
   kelengkapan datanya.
4. **Master data** — mengelola data UL dan unit pembangkit.

Implementasi teknis: `Gate::before()` memberi Super Admin bypass penuh, sehingga
setiap permission baru otomatis dimiliki tanpa perlu sinkronisasi ulang.

---

## 5. Katalog Permission

Format penamaan: `modul.aksi`.

### 5.1 Master Data

| Permission | Keterangan |
|---|---|
| `service_unit.view_any` | Melihat daftar UL |
| `service_unit.view` | Melihat detail UL |
| `service_unit.create` | Menambah UL |
| `service_unit.update` | Mengubah UL |
| `service_unit.delete` | Menghapus UL |
| `unit.view_any` | Melihat daftar unit pembangkit |
| `unit.view` | Melihat detail unit |
| `unit.create` | Menambah unit |
| `unit.update` | Mengubah unit |
| `unit.delete` | Menghapus unit |

### 5.2 Manajemen Akses

| Permission | Keterangan |
|---|---|
| `user.view_any` | Melihat daftar pengguna |
| `user.view` | Melihat detail pengguna |
| `user.create` | Menambah pengguna |
| `user.update` | Mengubah pengguna |
| `user.delete` | Menghapus / menonaktifkan pengguna |
| `role.view_any` | Melihat daftar role |
| `role.view` | Melihat detail role & permission-nya |
| `role.create` | Membuat role baru |
| `role.update` | Mengubah role & matriks permission |
| `role.delete` | Menghapus role non-sistem |
| `role.assign` | Menugaskan role ke pengguna pada UL/unit |

### 5.3 Monitoring

| Permission | Keterangan |
|---|---|
| `activity_log.view_any` | Melihat log aktivitas (dibatasi scope) |
| `activity_log.export` | Mengekspor log aktivitas |

### 5.4 Laporan Unit

| Permission | Keterangan |
|---|---|
| `report_unit.view_any` | Melihat daftar laporan unit |
| `report_unit.view` | Melihat detail laporan unit |
| `report_unit.create` | Membuat laporan unit |
| `report_unit.update` | Mengubah laporan unit |
| `report_unit.delete` | Menghapus laporan unit |
| `report_unit.submit` | Mengajukan laporan untuk persetujuan |
| `report_unit.approve` | Menyetujui / menolak laporan |
| `report_unit.export` | Mengekspor laporan (PDF / Excel) |

### 5.5 Project

| Permission | Keterangan |
|---|---|
| `project.view_any` | Melihat daftar project |
| `project.view` | Melihat detail project |
| `project.create` | Membuat project |
| `project.update` | Mengubah project |
| `project.delete` | Menghapus project |
| `project.approve` | Menyetujui perubahan status project |

### 5.6 Laporan Project

| Permission | Keterangan |
|---|---|
| `report_project.view_any` | Melihat daftar laporan project |
| `report_project.view` | Melihat detail laporan project |
| `report_project.create` | Membuat laporan project |
| `report_project.update` | Mengubah laporan project |
| `report_project.delete` | Menghapus laporan project |
| `report_project.submit` | Mengajukan laporan project |
| `report_project.approve` | Menyetujui laporan project |
| `report_project.export` | Mengekspor laporan project (PDF / Excel) |

### 5.7 Sistem

| Permission | Keterangan |
|---|---|
| `setting.manage` | Mengelola pengaturan aplikasi |

### 5.8 Operasi

Modul OPERASI (pencatatan operasi harian pembangkit). Seluruh menu **hanya**
untuk role **TL Operasi** (role lain → 403); Super Admin memperoleh via bypass.

| Permission | Keterangan |
|---|---|
| `operasi.input.view` | Melihat input operasi |
| `operasi.input.write` | Mengisi input operasi (grid harian, Star-Stop, feeder, pasokan, penerimaan BBM) |
| `operasi.laporan.view` | Melihat & mencetak laporan operasi |
| `operasi.berita_acara.view` | Melihat berita acara operasi |
| `operasi.berita_acara.create` | Membuat berita acara operasi |
| `operasi.master.view_any` | Melihat master data operasi (feeder, tangki, pelumas, kode status, faktor kalibrasi) |
| `operasi.master.manage` | Mengelola master data operasi |

### 5.9 Pemeliharaan (HAR)

Modul PEMELIHARAAN (laporan bulanan HAR). **TL Pemeliharaan** = isi + lihat;
**Manager UL** = lihat/cetak (read-only); Super Admin via bypass.

| Permission | Keterangan |
|---|---|
| `har.input.view` | Melihat input pemeliharaan |
| `har.input.write` | Mengisi input pemeliharaan (WO/SR, log kegiatan, biaya, foto) |
| `har.laporan.view` | Melihat & mencetak laporan pemeliharaan |
| `har.executive.view` | Melihat executive summary pemeliharaan |
| `har.master.view_any` | Melihat master data pemeliharaan (types, cycles, status, work group, SR category) |
| `har.master.manage` | Mengelola master data pemeliharaan |

---

## 6. Matriks Role × Permission

Legenda: `✓` dimiliki, kosong tidak dimiliki.
Super Admin memiliki seluruh permission (termasuk yang ditambahkan di masa depan).

| Permission | Super Admin | Manager UL | TL Operasi | TL Pemeliharaan | Site Leader | Operator |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `service_unit.view_any` | ✓ | ✓ | | | | |
| `service_unit.view` | ✓ | ✓ | | | | |
| `service_unit.create` | ✓ | | | | | |
| `service_unit.update` | ✓ | | | | | |
| `service_unit.delete` | ✓ | | | | | |
| `unit.view_any` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `unit.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `unit.create` | ✓ | | | | | |
| `unit.update` | ✓ | ✓ | | | | |
| `unit.delete` | ✓ | | | | | |
| `user.view_any` | ✓ | ✓ | | | ✓ | |
| `user.view` | ✓ | ✓ | | | ✓ | |
| `user.create` | ✓ | | | | | |
| `user.update` | ✓ | | | | | |
| `user.delete` | ✓ | | | | | |
| `role.view_any` | ✓ | | | | | |
| `role.view` | ✓ | | | | | |
| `role.create` | ✓ | | | | | |
| `role.update` | ✓ | | | | | |
| `role.delete` | ✓ | | | | | |
| `role.assign` | ✓ | | | | | |
| `activity_log.view_any` | ✓ | ✓ | | | ✓ | |
| `activity_log.export` | ✓ | ✓ | | | | |
| `report_unit.view_any` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `report_unit.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `report_unit.create` | ✓ | | ✓ | ✓ | ✓ | ✓ |
| `report_unit.update` | ✓ | | ✓ | ✓ | ✓ | ✓ |
| `report_unit.delete` | ✓ | | ✓ | ✓ | ✓ | |
| `report_unit.submit` | ✓ | | ✓ | ✓ | ✓ | ✓ |
| `report_unit.approve` | ✓ | ✓ | | | ✓ | |
| `report_unit.export` | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `project.view_any` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `project.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `project.create` | ✓ | | ✓ | ✓ | ✓ | |
| `project.update` | ✓ | | ✓ | ✓ | ✓ | |
| `project.delete` | ✓ | | | | ✓ | |
| `project.approve` | ✓ | ✓ | | | | |
| `report_project.view_any` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `report_project.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `report_project.create` | ✓ | | ✓ | ✓ | ✓ | |
| `report_project.update` | ✓ | | ✓ | ✓ | ✓ | |
| `report_project.delete` | ✓ | | | | ✓ | |
| `report_project.submit` | ✓ | | ✓ | ✓ | ✓ | |
| `report_project.approve` | ✓ | ✓ | | | ✓ | |
| `report_project.export` | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `setting.manage` | ✓ | | | | | |
| `operasi.input.view` | ✓ | | ✓ | | | |
| `operasi.input.write` | ✓ | | ✓ | | | |
| `operasi.laporan.view` | ✓ | | ✓ | | | |
| `operasi.berita_acara.view` | ✓ | | ✓ | | | |
| `operasi.berita_acara.create` | ✓ | | ✓ | | | |
| `operasi.master.view_any` | ✓ | | ✓ | | | |
| `operasi.master.manage` | ✓ | | ✓ | | | |
| `har.input.view` | ✓ | | | ✓ | | |
| `har.input.write` | ✓ | | | ✓ | | |
| `har.laporan.view` | ✓ | ✓ | | ✓ | | |
| `har.executive.view` | ✓ | ✓ | | ✓ | | |
| `har.master.view_any` | ✓ | | | ✓ | | |
| `har.master.manage` | ✓ | | | ✓ | | |

Matriks ini adalah **kondisi awal (seed)**. Super Admin dapat mengubahnya dari
antarmuka aplikasi tanpa perlu deploy ulang.

---

## 7. Model Akses

Otorisasi dievaluasi dalam dua lapis yang harus **keduanya** terpenuhi:

```text
1. Lapis Kapabilitas  → Apakah user punya permission untuk aksi ini?
2. Lapis Scope        → Apakah objek berada di dalam cakupan unit user?
```

Contoh: seorang Site Leader PLTD Kolaka memiliki `report_unit.approve`, tetapi
tidak dapat menyetujui laporan PLTD Bau-Bau karena unit tersebut di luar scope-nya.

Resolusi scope:

| Scope Level | Unit yang dapat diakses |
|---|---|
| `global` | Seluruh unit |
| `service_unit` | Seluruh unit di bawah UL yang ditugaskan |
| `unit` | Unit yang ditugaskan saja |

Seluruh perhitungan ini terpusat di `App\Services\AccessControl` dan diterapkan ke
query melalui scope `visibleTo($user)` pada model yang memiliki relasi unit.

---

## 8. Model Data (Fase 1)

```text
service_units
  id, code, name, slug, description, is_active, timestamps

units
  id, service_unit_id (nullable FK), code, name, slug, type,
  installed_capacity_mw, location, status, is_active, timestamps

roles
  id, name, display_name, scope, description, is_system, timestamps

permissions
  id, name, group, display_name, description, timestamps

permission_role
  permission_id, role_id

role_assignments
  id, user_id, role_id, service_unit_id (nullable), unit_id (nullable),
  assigned_by (nullable), timestamps

users  (kolom tambahan)
  employee_id, position, phone, is_active, last_login_at, last_login_ip

activity_logs
  id, user_id (nullable), event, description, subject_type, subject_id,
  service_unit_id, unit_id, properties (json), ip_address, user_agent, created_at
```

Relasi utama:

```text
ServiceUnit    1─n Unit
User           1─n RoleAssignment n─1 Role
RoleAssignment n─1 ServiceUnit | Unit   (salah satu, sesuai scope role)
Role           n─n Permission
ActivityLog    n─1 User, Unit, ServiceUnit
```

---

## 8A. Modul OPERASI (Fase 2)

Modul pencatatan operasi harian pembangkit, hasil reverse-engineering file Excel
MASTER LAPORAN OPERASI. Tiga menu: **Input**, **Laporan**, **Berita Acara**.
Referensi rinci: `modul-operasi.md` & `prompt-implementasi-operasi.md`.

### 8A.1 Prinsip

- **Multi-unit.** Seluruh tabel transaksi & master turunan ber-`unit_id` (FK ke
  `units`). Data tiap unit dipisah `unit_id`; user hanya melihat unit-nya.
- **Master existing dipakai ulang (FK, tidak dibuat ulang):** `unit_id`→`units`,
  `engine_id`→`machines`, `employee_id`→`employees`, identitas kop/BA→`service_units`.
- **Registry modul.** Tabel `work_modules` (seed `operasi`); permission/laporan/
  dokumen menempel ke modul agar modul berikutnya tidak menyentuh modul ini.
- **Scope unit** tetap lewat `role_assignments` + `AccessControl` (bukan pivot baru).
  Model turunan memakai trait `App\Models\Concerns\BelongsToUnit`
  (relasi `unit()` + `scopeVisibleTo`).
- **Struktur folder** mengikuti konvensi existing (`app/Http/Controllers/Operasi/`,
  `resources/js/pages/operasi/`), tanpa membuat base folder `app/Modules` baru.
- **Angka dihitung, bukan diketik.** Jam operasi/HAR/gangguan diturunkan dari log
  Star-Stop; stand awal auto-carry; SFC/total/standby hasil kalkulasi — semua di
  satu service kalkulasi yang dipakai grid, laporan, dan berita acara.

### 8A.2 Perubahan master mesin (ALTER)

```text
machines (existing)  + fuel_type ENUM(hsd_mfo|hsd_only) NULL
machine_lubricant_type  (pivot machine_id × lubricant_type_id)
```
Nilai `fuel_type` & pelumas diisi user per mesin lewat form edit mesin
(permission `machine.update` — Manager UL / Super Admin). Belum diasumsikan.

### 8A.3 Master turunan (per unit)

```text
report_periods       id, unit_id, month, year, total_days, total_hours,
                     pic_employee_id (FK employees), locked_at; unique(unit,month,year)
feeders              id, unit_id, name, feeder_type, sort_order, is_active
auxiliary_sources    id, unit_id, name, description, sort_order, is_active
fuel_tanks           id, unit_id, code, name, fuel_type(hsd|mfo),
                     capacity_liter, is_daily_tank, sort_order, is_active
lubricant_types      id, unit_id, code, name, unit_of_measure(drum|liter),
                     sort_order, is_active
calibration_factors  id, unit_id, engine_id (nullable), factor_type(kwh|hsd|mfo),
                     value(decimal 20,10), effective_date, notes
unit_status_codes    id, unit_id (nullable=global), code, label,
                     category(operasi|har|gangguan|standby), is_active
```

### 8A.4 Input harian (per unit)

```text
engine_status_logs     Star-Stop; SUMBER JAM (durasi start→stop per kategori)
daily_engine_reports   1 baris = 1 mesin × 1 tanggal (pembacaan meter);
                       stand awal & jam TIDAK disimpan (auto); unique(engine,date)
daily_feeder_readings  stand akhir feeder harian; unique(feeder,date)
daily_auxiliary_readings stand kWh/BBM pasokan cadangan harian
fuel_receipts          register penerimaan BBM (manual)
lubricant_receipts     register penerimaan pelumas (manual)
physical_stock_takes   opname fisik akhir periode (tangki/pelumas)
```

### 8A.5 Status implementasi

| Bagian | Status |
|---|---|
| Skema DB, model, factory, enum (FuelType, TankFuelType, StatusCodeCategory, LubricantUnit, CalibrationFactorType, StockItemType) | ✅ Selesai |
| Registry `work_modules` + seed `operasi` | ✅ Selesai |
| Permission `operasi.*` + mapping ke TL Operasi | ✅ Selesai |
| ALTER mesin (`fuel_type` + pelumas) + UI form mesin | ✅ Selesai |
| Seeder master Poasia (feeder, pelumas, tangki, kode status) | ✅ Selesai |
| Service kalkulasi (`OperasiCalculator`): carry-over stand awal, faktor kalibrasi, produksi/pemakaian, subtotal periode I/II/III, **rekap jam dari Star-Stop** (operasi/HAR/gangguan + standby = sisa jam) | ✅ Selesai |
| Menu Input — grid harian mesin (react-data-grid, header berkelompok ala-Excel: KWH PRODUKSI, PEMAKAIAN SENDIRI, BEBAN PUNCAK, PELUMAS, BBM HSD/MFO, AIR) + simpan | ✅ Selesai |
| Menu Input — **Star-Stop** (`engine_status_logs`): tambah/hapus entri start-stop, durasi auto, kartu rekap jam operasi/HAR/gangguan/standby | ✅ Selesai |
| Menu Input — **Feeder** (`daily_feeder_readings`): grid stand akhir per feeder × tanggal | ✅ Selesai |
| Menu Input — **Pasokan Cadangan** (`daily_auxiliary_readings`): grid stand kWh & BBM per sumber × tanggal | ✅ Selesai |
| Menu Input — **Penerimaan BBM** (`fuel_receipts`): register tambah/hapus + total HSD/MFO | ✅ Selesai |
| **Report registry** (`app/Services/Operasi/Reports/`: `OperasiReport` kontrak + `ReportRegistry` + `MonthlyEngineReport`) — laporan didaftarkan lewat definisi, satu `LaporanController` merender semuanya | ✅ Selesai |
| Menu Laporan — pilih unit/mesin/periode → satu klik **preview cetak** (`operasi/laporan/monthly-engine`, print-to-PDF via browser): rekap harian + subtotal periode + rekap jam + SFC bruto/netto | ✅ Selesai |
| **Document engine** — `document_templates` (nomor surat tetap per template + override per unit, `DocumentTemplateService`), `document_records` (snapshot arsip), helper `App\Support\Indonesian` (tanggal + terbilang), `BeritaAcaraBuilder` | ✅ Selesai |
| Menu Berita Acara — 3 dokumen (BA HSD, BA MFO, BA Opname Pelumas). Alur: pilih unit/jenis/periode → dokumen di-generate (angka auto) → **diedit di editor** dengan **2 mode yang bisa dipilih**: **Teks/Word (TinyMCE)** atau **Excel/spreadsheet (x-spreadsheet)** → **Simpan** (HTML di `content_html` atau grid di `content_grid`, plus `format`; diarsip di `document_records`, bisa dibuka & diedit lagi) → **Unduh PDF** (dompdf, dari mode terakhir yang disimpan) atau **Unduh Excel** (.xlsx via SheetJS). **Kop surat (logo + org + kotak dokumen)** tampil di kedua editor & PDF dengan format identik: di mode **Teks** kop menyatu inline (bisa diedit), di mode **Excel** kop ditampilkan sebagai **banner di atas grid** (karena x-spreadsheet tidak bisa menaruh gambar di sel) dan ditambahkan otomatis saat render PDF/xlsx. Logo `public/logo/sidebar-logo.png` di-embed data URI saat render PDF. Angka auto (persediaan awal carry-over opname, penerimaan, pemakaian per mesin, administrasi A−B−C, selisih E−D); TTD dari master Pegawai; nomor surat tetap. `DocumentGridBuilder` membangun grid & merender grid→HTML untuk PDF | ✅ Selesai |
| Menu Laporan — mode **Excel** (`operasi/laporan/{report}/excel`): laporan dibuka sebagai spreadsheet, bisa **Unduh Excel** (.xlsx) atau **Cetak/PDF** (halaman print) | ✅ Selesai |
| **CRUD Master Operasi** — satu layar generik `operasi/master/{resource}` (config-driven `OperasiMasterRegistry` + satu `MasterController`; skema field mendorong validasi & form otomatis) untuk feeder, pasokan cadangan, tangki BBM, jenis pelumas, faktor kalibrasi (per unit) & kode status (global). Gating `operasi.master.view_any`/`manage` | ✅ Selesai |
| **CRUD Template BA** — `operasi/document-template`: atur nomor surat / judul / revisi tiap jenis BA; **default global** + **override per unit** (menimpa global saat cetak). Nomor tetap, bukan auto-increment. Gating `operasi.master.manage` | ✅ Selesai |
| **Logsheet Operator** (`operasi/input/logsheet`, addendum) — layer input lapangan **per jam**: role `operator` (permission `operasi.logsheet.write`), TL Operasi & Manager UL `operasi.logsheet.view` (read-only), Super Admin via bypass. Satu lembar per mesin per hari (`operator_logsheets` + `operator_logsheet_readings` model panjang). **UI form sederhana** (bukan grid Excel): tabel baca-saja slot waktu × parameter (header berkelompok: Coolant 1/2, Winding L1–L3, Ampere R/S/T, Flow IN/OUT) + tombol **Isi Data** → **modal** pilih **Jam** + input parameter → **Simpan** langsung mengisi jam tsb (upsert per time_slot, tidak menghapus jam lain). **Shift A–D** (dropdown, tanpa nama operator). Slot 01:00–24:00 + 17:30/18:30/19:30/20:30/21:30. Parameter dari master `logsheet_parameters` (`plant_type=all`, seed 22 param §2; PLTM/PLTG disiapkan belum diisi). **Kirim** mengunci lembar dari edit operator. Auto-agregasi ke `daily_engine_reports` **disiapkan tapi belum aktif**: service kosong `LogsheetAggregator` (TODO) + kolom `daily_engine_reports.source` (manual|logsheet, default manual); alur input manual TL Operasi tidak berubah | ✅ Selesai |

### 8A.6 Catatan terbuka modul OPERASI

- Rumus **Cummins flow meter IN/OUT** memakai default aman, diisolasi & bertanda
  "asumsi — menunggu verifikasi tim operasi".
- Kapasitas tangki & katalog lengkap kode status Star-Stop belum final (seed
  placeholder, dilengkapi user via CRUD master).
- **Nomor surat BA** tetap per jenis dokumen (bukan auto-increment). Kini
  **editable lewat UI** (`operasi/document-template`): default global + override
  per unit. Keputusan apakah dipakai nomor sama untuk semua unit atau beda per
  unit diserahkan ke user (tinggal isi override bila perlu).
- **Grid Input (menu Input):** kolom hasil (produksi, netto, pakai HSD/MFO) &
  stand awal read-only, dihitung server via `OperasiCalculator` lalu dimuat ulang
  setelah "Simpan" (satu sumber rumus, tidak diduplikasi di frontend). Editing per
  sel + navigasi keyboard sudah jalan; **paste multi-sel dari Excel** & recompute
  live saat mengetik menyusul (penyempurnaan berikutnya). Field MFO hanya tampil &
  disimpan untuk mesin `hsd_mfo`.
- **Paste dari Excel** ✅ — hook `useExcelPaste` (di `components/operasi/grid.tsx`)
  dipakai grid Laporan Harian, Feeder, dan Pasokan Cadangan: salin blok dari Excel,
  klik sel awal, Ctrl+V → mengisi turun & ke kanan ke kolom-kolom yang bisa diedit
  (kolom hasil/otomatis dilewati, tetap sejajar).

---

## 8B. Modul PEMELIHARAAN / HAR (Fase 3)

Laporan bulanan Team Leader Pemeliharaan, hasil reverse-engineering file Excel
LAPORAN HAR (unit PLTD Wua-Wua). **Sejajar** dengan modul OPERASI — pola &
fondasi generik dipakai ulang (RBAC, `work_modules`, `report_periods`, document
engine, report registry, komponen grid). Referensi: `modul-har.md` &
`prompt-implementasi-har.md`.

### 8B.1 Prinsip

- **Berbasis Work Order (WO) & Service Request (SR)**, bukan pembacaan meter.
  Input campuran grid (daftar WO/SR) + form (log kegiatan, foto).
- **Sumber WO/SR di balik satu interface** `App\Services\Har\WorkOrderSource`
  (2 implementasi: `ManualWorkOrderSource` aktif; `WpcWorkOrderSource` placeholder
  untuk koneksi DB WPC PLN nanti). Controller/laporan hanya bergantung interface;
  kolom `source` (manual|wpc) membedakan asal data. Config `config/har.php`
  (WPC) sengaja kosong sampai fase integrasi. **Tidak ada koneksi WPC di-hardcode.**
- **Multi-unit & master existing** (FK, tidak dibuat ulang): `unit_id`→`units`,
  `engine_id`→`machines`, penandatangan→`employees`, identitas→`service_units`.
  Semua tabel transaksi ber-`unit_id` (trait `BelongsToUnit`).
- **Akses:** TL Pemeliharaan (isi+lihat) & Manager UL (lihat saja). Manager UL
  memakai scope service_unit yang sudah ada (lihat unit di bawah UL-nya).
- **No. Dokumen ISO** (FMKD-314-…) tetap per jenis sheet, editable (pola sama BA).

### 8B.2 Master pemeliharaan (global, CRUD)

```text
maintenance_types    PM/PdM/CM/FLM/ENJI (code, name, category)
maintenance_cycles   P1=7D, P2=14D, P4=84D (code, name, interval_days)
wo_statuses          APPR/CLOSE/WAPPR/INPRG (code, name, is_closed)
work_groups          MECHD/ELECD (code, name)
sr_categories        CM/FLM/PDM/CANCEL (code, name)
```
Diseed placeholder (`HarMasterSeeder`); dilengkapi user via CRUD.

### 8B.3 Transaksi (per unit)

```text
service_requests   sr_number, description, sr_category_id, status(open|close),
                   engine_id, source(manual|wpc), report_period_id
work_orders        wonum, description, maintenance_type_id, engine_id,
                   work_group_id, wo_status_id, cycle_id, report/sched/actual date,
                   waiting_reason(shutdown|material|jasa), service_cost, material_cost,
                   source, report_period_id
maintenance_costs  akumulasi bulanan per unit (override manual, flag use_manual);
                   default = SUM biaya dari work_orders
maintenance_activities (+ _tasks, _materials)  log kegiatan HARMES (inti, manual)
maintenance_schedules  matriks rencana vs realisasi per mesin (scope har|pelumas|air),
                       schedule_data JSON (granularitas menunggu konfirmasi user)
maintenance_attachments  foto lampiran (storage, simpan path)
har_document_records  dokumen laporan HAR editable per unit+periode (type=bulanan);
                      format(html|grid), content_html, content_grid JSON, snapshot,
                      document_number — pola sama document_records modul Operasi
```

### 8B.4 Status implementasi

| Bagian | Status |
|---|---|
| Registry `work_modules` seed `pemeliharaan` | ✅ Selesai |
| Permission `har.*` + grup Pemeliharaan + mapping (TL Pemeliharaan penuh, Manager UL view) | ✅ Selesai |
| Enum (WorkOrderSource, WoWaitingReason, ServiceRequestStatus, MaintenanceScope, SchedulePlanType) | ✅ Selesai |
| Skema DB + model + factory (master + transaksi) | ✅ Selesai |
| Seed master pemeliharaan placeholder (`HarMasterSeeder`) | ✅ Selesai |
| Interface `WorkOrderSource` + `ManualWorkOrderSource` (aktif) + `WpcWorkOrderSource` (placeholder) + config `har.php` | ✅ Selesai |
| **Master engine generik dibagikan** — `MasterRegistry` (interface) + `MasterFields` (trait skema) + trait controller `ManagesMasterResources` + komponen React `components/master/master-screen.tsx`; dipakai ulang Operasi **dan** HAR (halaman = wrapper tipis) | ✅ Selesai |
| **CRUD master pemeliharaan** — `har/master/{resource}` (jenis pemeliharaan, siklus, status WO, work group, kategori SR — semua global) via engine bersama; gating `har.master.view_any`/`manage` | ✅ Selesai |
| Menu Input — **Work Order** (`har/input/work-order`): grid ala-Excel (react-data-grid + paste), tambah/hapus baris, FK diketik sebagai **kode** (jenis/work group/status/siklus) & nama mesin lalu di-resolve server-side; simpan meng-upsert per (unit, periode, wonum) & menghapus baris yang dibuang; `report_period` auto | ✅ Selesai |
| Menu Input — **Service Request** (`har/input/service-request`): grid ala-Excel + paste, FK (kategori) sebagai kode + nama mesin di-resolve server-side, status open/close; upsert per (unit, periode, no SR) + rekonsiliasi hapus | ✅ Selesai |
| Menu Input — **Log Kegiatan HARMES** (`har/input/activity`): tabel + dialog form (tanggal, mesin, jenis HAR, hasil, no WO/SR/LH-05/TUG-9, keterangan) dengan sub-daftar **uraian kegiatan** & **material** dinamis; create/update (replace sub-daftar) / delete (cascade) | ✅ Selesai |
| Menu Input — **Biaya** (`har/input/cost`): total otomatis dari biaya WO periode + **override manual bulanan** (`maintenance_costs`, flag `use_manual`), kartu efektif (badge sumber) + **akumulasi YTD** | ✅ Selesai |
| **Menu Laporan** (`har/laporan`) — `HarReportBuilder` (baca WO/SR via `WorkOrderSource`): **Laporan Bulanan dokumen penuh** — (1) SR Summary, (2) WO Summary, (3) rekap WO per jenis, (4) WO tertunda, (5) akumulasi biaya, (6) **Rencana vs Realisasi** per lingkup, (7) **Log Kegiatan HARMES** (uraian + material), (8) **Lampiran Foto** — semua di satu halaman print → PDF (window.print) + **Executive Summary** otomatis. Gating `har.laporan.view` / `har.executive.view` (TL Pemeliharaan & Manager UL) | ✅ Selesai |
| Menu Input — **Rencana vs Realisasi** (`har/input/schedule`): matriks mesin × tanggal per lingkup (HAR/pelumas/air) & jenis (rencana/realisasi), grid + paste, disimpan `schedule_data` JSON per mesin | ✅ Selesai |
| Menu Input — **Lampiran Foto** (`har/input/attachment`): unggah foto ke storage (disk public, simpan path), galeri + hapus (file ikut terhapus); kait opsional ke mesin/WO | ✅ Selesai |
| **Dokumen Laporan HAR editable** (`har/laporan/dokumen`) — tombol "Lihat & Edit Dokumen": laporan bulanan penuh terisi otomatis (`HarDocumentBuilder` + `HarDocumentGridBuilder`) lalu diedit **dua mode** — teks (RichText/TinyMCE, ekspor PDF) atau spreadsheet (x-spreadsheet, ekspor Excel .xlsx & PDF), **pakai ulang** komponen bersama `components/document/document-editor.tsx`. Disimpan sbg HTML/grid di `har_document_records` (per unit+periode), PDF via dompdf dari konten tersunting + kop/logo. Gating view `har.laporan.view`, simpan `har.input.write` (Manager UL lihat saja) | ✅ Selesai |
| **No. Dokumen ISO (FMKD-314-…)** — default per bagian dari `config/har.php` (bukan auto-generate), tampil di kop + tiap judul bagian, **editable langsung di dalam dokumen** (tersimpan bersama konten) | ✅ Selesai |
| Integrasi DB WPC (`WpcWorkOrderSource`) | ⏳ Fase lanjut |

### 8B.5 Catatan terbuka modul HAR

- **Cakupan akses Manager UL**: dipakai scope service_unit existing (lihat unit di
  bawah UL). Konfirmasi bila perlu lintas-UL.
- **Daftar lengkap status WO/kategori SR/siklus** dari WPC belum final → seed
  placeholder + CRUD.
- **Granularitas matriks Rencana vs Realisasi** (per-tanggal 31 hari vs ringkasan)
  belum dikonfirmasi → disimpan sebagai JSON fleksibel.
- **Detail teknis WPC** (jenis DB, skema, kredensial) menyusul di fase integrasi.

---

## 8C. Modul K3 & KEAMANAN (Fase 4)

Laporan kinerja bulanan TL K3L & Keamanan (5 file sumber: LAPKIN induk + Patroli
+ Emergency Facility + Lampiran + Sertifikat). SEJAJAR dengan Operasi/HAR —
**pakai ulang** RBAC, `work_modules`, master engine generik, `report_periods`,
document engine, dan komponen grid. Referensi: `modul-k3.md`,
`prompt-implementasi-k3.md`.

### 8C.1 Prinsip

- Multi-unit: format form sama antar unit; data & nama unit beda; semua tabel
  transaksi/master turunan ber-`unit_id` (trait `BelongsToUnit`). Master global
  (jenis kegiatan, kategori APD/alat) tanpa unit.
- Data K3 = mayoritas **inspeksi & inventaris berkala** (harian/mingguan/bulanan)
  + log keamanan. Form checklist seragam pakai pola generik `inspections` +
  `inspection_results` (bukan 28 tabel terpisah) — tabel khusus hanya untuk
  struktur unik (APAR, emergency, sertifikat, patroli).
- **No. Dokumen ISO** (SMT-FM-AK3-*, FMZ-*) tetap per form, editable (bukan
  auto-generate) — pola sama BA/HAR.
- Kadaluarsa sertifikat/APAR = **label/badge** di monitoring (aktif / mendekati
  ≤60 hari / expired), bukan push notification.
- Akses: role baru **TL K3 & Keamanan** (`tl_k3`, isi + lihat penuh); Manager UL
  hanya `k3.laporan.view` + `k3.monitoring.view` (read-only).

### 8C.2 Status implementasi

| Bagian | Status |
|---|---|
| Registry `work_modules` seed `k3` | ✅ Selesai |
| Permission `k3.*` (input/laporan/monitoring/master) + grup **K3 & Keamanan** + role `TeamLeaderK3` penuh, Manager UL view; matrix di seeder | ✅ Selesai |
| **Master engine generik diperluas** — field `relation` kini mendukung sumber tabel & label sembarang + flag unit-scoped (mundur-kompatibel dgn `machines` Operasi), agar `apd_items → apd_categories` (global) bisa dipakai | ✅ Selesai |
| **Master K3 (CRUD)** via engine bersama — global: jenis kegiatan, kategori APD, kategori alat sertifikasi; per-unit: lokasi patroli, pos keamanan, fasilitas darurat, item APD, APAR/APAB, kotak P3K, item checklist inspeksi. Halaman `k3/master/{resource}`, gating `k3.master.view_any`/`manage`; seed placeholder global (`K3MasterSeeder`) | ✅ Selesai |
| **Menu Input — Time Frame** (`k3/input/time-frame`): matriks kegiatan K3 × tanggal per jenis (rencana/realisasi), grid + paste, disimpan `plan_days`/`real_days` JSON di `k3_activity_plans` per (unit,periode,jenis kegiatan) | ✅ Selesai |
| **Menu Input — Laporan Kecelakaan** (`k3/input/accident`): grid PAK/PAHK per periode (replace-all), tombol cepat **Tandai NIHIL**, kategori enum `AccidentCategory` | ✅ Selesai |
| **Menu Input — Inspeksi (engine generik)** (`k3/input/inspection`): `inspections` + `inspection_results`; item checklist diambil dari master `inspection_checklists` per `form_code`, header + grid hasil (kondisi/tindak lanjut/nilai/catatan), upsert 1 sesi per (unit,periode,form) + replace hasil — pola dipakai semua form checklist seragam | ✅ Selesai |
| **Menu Input — Inspeksi APAR/APAB** (`k3/input/apar-check`): grid per tabung (dari master `fire_extinguishers`) — kondisi tabung/nozzle/tekanan/pin-segel, tgl periksa, exp date; upsert per (unit,periode,tabung) | ✅ Selesai |
| **Menu Input — Kesiapan Fasilitas Darurat** (`k3/input/emergency`): grid per fasilitas (dari master), filter periode bulanan/mingguan (M1–M4); Ready/Not Ready/Total + **% kesiapan otomatis** (tidak dipaksa bila sumber teks); upsert per (unit,periode,minggu) | ✅ Selesai |
| **Menu Input — Patroli Keamanan** (`k3/input/patrol`): matriks lokasi (POA…) × tanggal, cap jumlah scan/hari + **total kumulatif otomatis** per lokasi; upsert per (unit,lokasi,tgl), nol menghapus | ✅ Selesai |
| Menu Input — inventaris APD, Kotak/Isi P3K, Alat Tanggap Darurat (bisa pakai engine inspeksi generik) | ⏳ Direncanakan |
| Menu Input — Keamanan lain (apel, mutasi tamu, kondisi CCTV) | ⏳ Direncanakan |
| **Menu Input — Sertifikasi Peralatan** (`k3/input/certificate`): grid registri sertifikat per unit (replace-all), kategori diketik sbg kode & di-resolve ke master global; tanggal ijin/uji/uji-ulang | ✅ Selesai |
| **Menu Monitoring** (`k3/monitoring`) — `K3MonitoringService`: **badge status** sertifikat & APAR (aktif/mendekati ≤`config('k3.expiry_warning_days')`=60h/expired, dihitung dari tgl uji ulang & exp date, sisa hari) + ringkasan bulan (kecelakaan NIHIL/korban, kegiatan terealisasi, total scan patroli, % kesiapan darurat, hitung expired/mendekati). Gating `k3.monitoring.view` (TL K3 & Manager UL). Label sistem, bukan push | ✅ Selesai |
| **Menu Lampiran** (`k3/input/attachment`): unggah dokumen/foto (JPG/PNG/PDF) per periode ke storage (disk public, simpan path), galeri + hapus (file ikut terhapus) | ✅ Selesai |
| **Menu Laporan — Dokumen K3 penuh editable** (`k3/laporan/dokumen`) — `K3ReportBuilder` merangkum semua input (Time Frame, kecelakaan, APAR, kesiapan darurat, patroli kumulatif, sertifikat + status, inspeksi, lampiran) → `K3DocumentBuilder` + `K3DocumentGridBuilder`, diedit **dua mode** (teks/TinyMCE→PDF, spreadsheet/x-spreadsheet→Excel & PDF) via komponen bersama `document-editor.tsx`, disimpan di `k3_document_records`, PDF via dompdf + kop/logo. No. Dokumen ISO (SMT-FM-AK3-*) default `config/k3.php`, editable di dokumen. Gating view `k3.laporan.view`, simpan `k3.input.write` | ✅ Selesai |
| Menu Input pelengkap — inventaris APD, Kotak/Isi P3K, Alat Tanggap Darurat (pakai engine inspeksi generik), Keamanan lain (apel, tamu, CCTV) | ⏳ Opsional lanjut |

### 8C.3 Catatan terbuka modul K3

- **Ambang "mendekati expired"** default ≤60 hari (dari prompt) — bisa disesuaikan.
- **Daftar final form** (beberapa Excel bertanda "(NO)"/"(old)" = versi lama) —
  konfirmasi mana yang aktif saat membangun input/laporan.
- **Seed per-unit** (POA1–14, emergency facility Poasia, dll.) menyusul per unit.

---

## 9. Roadmap Fase

| Fase | Cakupan | Status |
|---|---|---|
| **1** | Fondasi: master UL & unit, role, permission, penugasan, Super Admin (manajemen akses, pemantauan aktivitas, pemantauan unit) | Selesai |
| **2** | Modul OPERASI (lihat 8A): Input (5 tab), Laporan, Berita Acara — **selesai** | Selesai |
| **3** | Modul PEMELIHARAAN/HAR (lihat 8B): master + Input (WO, SR, Log Kegiatan, Biaya, Rencana/Realisasi, Foto), Laporan & Executive Summary — **selesai**; sisa hanya integrasi DB WPC (fase lanjut) | Selesai |
| **4** | Modul K3 & KEAMANAN (lihat 8C): master + Input (Time Frame, Kecelakaan, Inspeksi generik, APAR, Kesiapan Darurat, Patroli, Sertifikat, Lampiran), Monitoring (badge status), Laporan **dokumen penuh editable** — **selesai**; sisa hanya input pelengkap opsional (APD/P3K/tanggap darurat, apel/tamu/CCTV) | Selesai |
| **3b** | Modul Project: perencanaan, progres, milestone | Direncanakan |
| **4** | Modul Laporan Project | Direncanakan |
| **5** | Ekspor PDF & Excel untuk laporan unit dan laporan project | Direncanakan |
| **6** | Dashboard analitik lintas unit & indikator kinerja | Direncanakan |

Fase berikutnya menempel pada fondasi Fase 1 tanpa mengubahnya: cukup menambah
modul baru + case permission baru + mapping role.

---

## 10. Standar Teknis

| Aspek | Keputusan |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Inertia v3 + React 19 + TypeScript |
| Styling | Tailwind CSS v4 + shadcn/ui, token mengikuti `design.md` |
| Auth | Laravel Fortify (login, 2FA, passkey, reset password) |
| Otorisasi | Gate + Policy berbasis permission, tanpa paket eksternal |
| Routing frontend | Laravel Wayfinder (`@/routes`, `@/actions`) |
| Grid input (modul OPERASI) | `react-data-grid` (MIT) — grid ala-Excel per sel |
| PDF (modul OPERASI, Berita Acara) | `barryvdh/laravel-dompdf` |
| Editor dokumen (Berita Acara) | `tinymce` + `@tinymce/tinymce-react` (self-host, lisensi GPL) — WYSIWYG ala-Word |
| Editor spreadsheet (Berita Acara & Laporan) | `x-data-spreadsheet` (MIT) — edit ala-Excel; `xlsx` (SheetJS) untuk unduh .xlsx; `less` (dev, build-time untuk x-spreadsheet) |
| Database | SQLite untuk pengembangan; siap dipindah ke MySQL/PostgreSQL |
| Testing | PHPUnit (feature test diutamakan) |
| Code style | Laravel Pint |

Otorisasi sengaja dibangun sendiri (bukan paket pihak ketiga) agar model scope
UL/unit menyatu langsung dengan permission, dan agar tidak menambah dependensi baru.

---

## 11. Catatan Terbuka

Perlu konfirmasi dari pemilik proses bisnis:

1. **Lima unit belum dipetakan ke UL** — PLTU Moramo, PLTD Wua-Wua, PLTD Langara,
   PLTM Langara, dan PLTD Pasarwajo belum disebutkan berada di bawah UL mana.
   Saat ini disemai sebagai unit yang dikelola langsung UP Kendari
   (`service_unit_id = null`). Mohon dikonfirmasi UL induknya.
2. **PLTD Wua-Wua tercantum dua kali** pada daftar awal (nomor 2 dan 6) dan
   diperlakukan sebagai satu unit.
3. **Kapasitas terpasang, lokasi, dan kode unit resmi** belum tersedia; kolomnya
   sudah disiapkan dan dapat diisi lewat antarmuka master data.
4. **Alur persetujuan laporan** (Operator → Site Leader → Manager UL?) perlu
   dipastikan sebelum Fase 2 dimulai.
