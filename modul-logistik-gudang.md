# Spesifikasi Modul LOGISTIK & GUDANG — Aplikasi Pelaporan Pembangkit (Multi-Unit)

> Panduan implementasi untuk Claude Code. Modul ini menambahkan fungsi kerja
> **Logistik & Gudang** ke aplikasi pelaporan pembangkit multi-unit &
> multi-modul, mengikuti pola modul existing (OPERASI, PEMELIHARAAN/HAR, K3).
>
> **Langkah 0 sebelum coding:** baca `CLAUDE.md` di root project (Laravel Boost),
> lalu `.ai/rules/index.md` dan setiap rule file yang globs-nya cocok dengan path
> yang disentuh. Modul ini paling mirip **HAR** — pakai HAR sebagai template.
>
> **ATURAN PEMELIHARAAN DOKUMEN:** setiap kali modul ini di-update (menu baru,
> controller, model, migration, halaman React), **wajib update file ini** agar
> status tetap akurat.

---

## 0. Status Implementasi

| Bagian | Status |
|---|---|
| RBAC (PermissionGroup, PermissionName, RoleName) | ✅ Selesai |
| Registry modul (`work_modules` seed `logistik`) | ✅ Selesai |
| Routes (`routes/logistik.php`) + include di `web.php` | ✅ Hub + 7 jadwal + 9 input + laporan dokumen |
| Controller hub (`JadwalController`, `InputHubController`, `LaporanController`) | ✅ Selesai |
| Sidebar nav "Logistik & Gudang" (Jadwal + Input + Laporan) | ✅ Selesai |
| Halaman menu Jadwal (`logistik/jadwal/index.tsx`) | ✅ 7 kartu aktif, 2 menyusul |
| Halaman menu Input (`logistik/input/index.tsx`) | ✅ 9 kartu aktif, 2 menyusul (Inventaris Lainnya, PTW) |
| Halaman Laporan (`logistik/laporan/index.tsx`) | ✅ Laporan Logistik & Gudang (dokumen editor + pratinjau PDF, pola K3/PdM) |
| Master data logistik | ⏳ Belum dirancang |
| Migration & Model transaksi | ✅ `logistik_rekomendasis`, `logistik_jadwal_rows`, `logistik_form_rows`, `logistik_document_records` |

> Semua input/jadwal: tanpa tanda tangan (menyusul bersama fitur verifikasi),
> dropdown memakai komponen React (`OperasiSelect`, `PdmCellSelect`), output
> PDF & Excel, dan sebelum disimpan menampilkan isian bawaan halaman.

---

## 1. RBAC — sudah terpasang

- **PermissionGroup:** `Logistik` (`logistik`), label "Logistik & Gudang".
- **PermissionName** (grup Logistik):
  - `logistik.input.view` — melihat input logistik & gudang
  - `logistik.input.write` — mengisi input logistik & gudang
  - `logistik.laporan.view` — melihat & mencetak laporan
  - `logistik.master.view_any` — melihat master data
  - `logistik.master.manage` — mengelola master data
- **RoleName:** `tl_logistik` (**TL Logistik & Gudang**), scope Unit.
  - Default: seluruh `teamLeaderBasePermissions()` + lima permission `logistik.*`.
  - **Manager UL** memperoleh `logistik.laporan.view` (oversight, read-only).
  - Super Admin otomatis punya semua (gate bypass).
- Menambah permission/role hanya perlu edit enum + jalankan
  `php artisan db:seed --class=RolePermissionSeeder` (idempotent, tanpa migration).

---

## 2. Peta Menu (blueprint navigasi)

Mirip HAR: sidebar grup **Logistik & Gudang** berisi **Jadwal**, **Input**, dan
**Laporan** (`logistik.laporan.view`, `logistik/laporan/index.tsx` — landing
placeholder, rekap & cetak menyusul).

### 2.1 Menu JADWAL — `logistik/jadwal/index.tsx`

Mesin sheet (`App\Support\LogistikJadwal`, `Logistik\JadwalSheetController`,
route `logistik.jadwal.sheet.index|store|pdf` = `/logistik/jadwal/{jadwal}`,
halaman `logistik/jadwal/sheet.tsx`, PDF `resources/views/logistik/jadwal/{layout}-pdf`,
Excel `resources/js/lib/logistik-jadwal-excel.ts`, tabel `logistik_jadwal_rows`
— `days` JSON = kolom → kode):

| Key | Jadwal | Layout |
|---|---|---|
| `kegiatan` | Jadwal Kegiatan Logistik & Gudang (I Mesin, II Mingguan, III Bulanan, IV Non Rutin) | kegiatan (R rencana / D realisasi per tanggal) |
| `shift` | Jadwal Shift Operator (P/OF/S/I/C/M + rekap absensi & % kehadiran) | shift |
| `piket` | Jadwal Piket Patrol Check (On Call) | pelaksana (baris Rencana/Realisasi) |
| `5s5r` | Jadwal Pelaksanaan 5S5R | pelaksana |
| `meeting` | Jadwal Meeting | pelaksana |
| `inventarisasi` | Jadwal Inventarisasi Tools & Material Bagian Lainnya | pelaksana |
| `ik` | Jadwal Pembuatan IK (tahunan, kolom JAN–DEC, disimpan `month = 0`) | ik |

Menyusul: Jadwal Kegiatan Pemeliharaan, Jadwal Piket Patrol Check Stock.

### 2.2 Menu INPUT — `logistik/input/index.tsx`

| Input | Route / mesin |
|---|---|
| Rekomendasi Logistik & Gudang | `logistik.input.rekomendasi.*` — `Logistik\RekomendasiController`, `logistik_rekomendasis` (portrait) |
| Laporan Patrol Checklist | sheet `patrol-check` (N/T per tanggal) — `/logistik/input/lembar/{jadwal}` (`logistik.input.sheet.*`) |
| Laporan Inspeksi Checklist 5S5R | sheet `inspeksi-5s5r` (15 pelaksanaan, eviden 3 foto/item, akumulatif) |
| Laporan Input Data Aplikasi | sheet `input-aplikasi` |
| Maturity Level Logistik & Gudang | sheet `maturity` (level 0–5, portrait) |
| Laporan Pendukung | form `pendukung` — `/logistik/input/form/{form}` (`logistik.input.form.*`) |
| Laporan Peralatan, Material dan Tools | form `peralatan` (4 pelaksanaan × baik/rusak/hilang, rekap) |
| Laporan Kondisi Stok Tools dan Material | form `kondisi-stok` (stok akhir & ROP dihitung) |
| Laporan Unsafe Action & Unsafe Condition | form `unsafe` (foto sebelum/sesudah, jumlah temuan) |

Mesin form tabel: definisi `App\Support\LogistikForms\*Form` (registry
`LogistikForms::ALL`; kolom text/textarea/number/select/image/computed, section
+ baris bawaan + baris kosong, total, ringkasan, catatan), `Logistik\FormController`,
halaman `logistik/input/form.tsx`, PDF `logistik/input/form-pdf.blade.php`, Excel
`resources/js/lib/logistik-form-excel.ts`, tabel `logistik_form_rows` (`data` JSON).
Menyusul: Laporan Inventaris Lainnya, Laporan Permit To Work.

### 2.3 Menu LAPORAN — `logistik/laporan/index.tsx`

Seperti K3/PdM: kartu "Buka Dokumen" → `logistik/laporan/document.tsx`
(`DocumentEditor`: Teks, Excel, Pratinjau PDF, Simpan, Muat Ulang). Backend
`Logistik\DocumentController` (`logistik.laporan.document.edit|store|regenerate|pdf`),
`logistik_document_records`, `App\Services\Logistik\LogistikDocumentBuilder`:
I. Sampul, II. Lembar Pengesahan (Disahkan Manager, Disetujui TL Pemeliharaan,
Dibuat Officer Logistik & Gudang — nama dari pegawai, tanpa TTD), III. Daftar Isi,
IV. semua jadwal, V. semua input — tiap tabel dari view PDF-nya (`pdfView()`),
CSS scoped (`ScopedHtmlFragment`), portrait/landscape digabung
(`OrientationPdfMerger::renderSections`, section `.lg-section` / `.lg-landscape`).
Mode Excel: `App\Services\Reports\FragmentGridBuilder`.

Tes: `tests/Feature/Logistik/{RekomendasiInput,JadwalSheet,FormInput,LaporanLogistik}Test.php`.

---

## 3. Konvensi implementasi sub-halaman (saat menyusul)

Ikuti pola **HAR** persis — JANGAN buat pola baru:

1. **Scope per unit.** Semua tabel transaksi ber-`unit_id` (FK ke `units`) +
   trait `App\Models\Concerns\BelongsToUnit`. Master global tanpa `unit_id`.
2. **Controller** di `app/Http/Controllers/Logistik/`. Setiap aksi gate dengan
   `abort_unless($user->hasPermissionTo(PermissionName::Logistik*), 403)` dan
   `$user->canAccessUnit($unit)`. `index` (Inertia render), `store`, `pdf`,
   `destroy` sesuai kebutuhan. Log lewat `ActivityLogger`.
3. **Model** di `app/Models/` prefiks `Logistik...`, `$table` eksplisit,
   `#[Fillable([...])]`, `casts()`, relasi `inputUser()`.
4. **Migration** prefiks tabel `logistik_...`, kolom `unit_id`, `year`, `month`,
   `sort_order`, `input_by` (nullOnDelete), index `[unit_id, year, month]`.
5. **Halaman React** di `resources/js/pages/logistik/...`, pakai
   `OperasiSelect`/`OPERASI_MONTHS`, `PageHeader`, komponen `ui/*`, pola grid
   harian (toggle hari, target/realisasi/kinerja) atau tabel input seperti
   `har/input/material-peralatan.tsx`. Reset state saat signature
   `unit-bulan-tahun` berubah. Pola dirty/save.
6. **Wayfinder:** setelah menambah route jalankan
   `php artisan wayfinder:generate --with-form` (WAJIB flag `--with-form`).
7. **PDF** via dompdf (`Pdf::loadView('logistik. ...')`, A4 landscape), kop
   dua logo (`sidebar-logo.png`, `mkp.jpg`) base64, identitas unit dari master.
8. **Pint** `vendor/bin/pint --dirty --format agent` sebelum selesai; tulis
   test feature untuk tiap controller baru.
9. Setelah tiap tombol difungsikan: ganti badge "Segera Hadir" → "Tersedia" dan
   aktifkan tombol di `index.tsx`, lalu **update dokumen ini** (§0 & §2).

---

## 4. Anti-pattern

- ❌ Membuat ulang master unit/mesin/pegawai — WAJIB FK existing.
- ❌ Tabel/kalkulasi tanpa `unit_id`.
- ❌ Branch pada nama role (`hasRole`) untuk otorisasi — pakai permission.
- ❌ Menyimpan hasil kalkulasi (stok akhir, kinerja) sebagai input tanpa
  perhitungan ulang di backend.
- ❌ Menjalankan `wayfinder:generate` tanpa `--with-form`.
- ❌ Lupa update dokumen ini setelah perubahan modul.
