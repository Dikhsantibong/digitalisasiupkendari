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

## 9. Roadmap Fase

| Fase | Cakupan | Status |
|---|---|---|
| **1** | Fondasi: master UL & unit, role, permission, penugasan, Super Admin (manajemen akses, pemantauan aktivitas, pemantauan unit) | Sedang dikerjakan |
| **2** | Modul Laporan Unit: input harian/bulanan, alur pengajuan & persetujuan | Berikutnya |
| **3** | Modul Project: perencanaan, progres, milestone | Direncanakan |
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
