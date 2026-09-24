# Spesifikasi Modul PdM & MATURITY LEVEL — Aplikasi Pelaporan Pembangkit (Multi-Unit)

> Panduan implementasi untuk Claude Code. Modul ini menambahkan fungsi kerja
> **Predictive Maintenance (PdM) & Maturity Level** ke aplikasi pelaporan
> pembangkit multi-unit & multi-modul, mengikuti pola modul existing (OPERASI,
> PEMELIHARAAN/HAR, K3, LOGISTIK & GUDANG).
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
| Registry modul (`work_modules` seed `pdm`) | ✅ Selesai |
| Routes (`routes/pdm.php`) + include di `web.php` | ✅ Hub + jadwal (harian, patrol check, 5S5R, meeting) + 11 input + laporan (dokumen editor) |
| Controller hub (`JadwalController`, `InputHubController`, `LaporanController`) | ✅ Selesai |
| Sidebar nav "PdM & Maturity Level" (Jadwal + Input + Laporan) | ✅ Selesai |
| Halaman menu Jadwal (`pdm/jadwal/index.tsx`) | ✅ Tombol (isi menyusul) |
| Halaman menu Input (`pdm/input/index.tsx`) | ✅ 9 kartu aktif (lihat 2.2), sisanya menyusul |
| Halaman Laporan (`pdm/laporan/index.tsx`) | ✅ Laporan PdM & Maturity Level Pembangkit (dokumen editor + pratinjau PDF, pola K3) |
| Sub-halaman Jadwal (10 jenis) | ⏳ Belum (menyusul) |
| Sub-halaman Input | ✅ 11 input (lihat 2.2) — ⏳ log sheet |
| Master data & maturity level assessment | ⏳ Belum dirancang |
| Migration & Model transaksi | ✅ Jadwal + `pdm_kesiapan_apds`, `pdm_sample_monitorings` (+`_items`), `pdm_permit_to_works`, `pdm_form_documents` (+`pdm_form_items`), `pdm_realisasi_prediktifs`, `pdm_document_records` |

> Kartu sub-menu yang belum dibangun berlabel "Segera Hadir" dan dinonaktifkan
> sampai halaman isinya dibuat.

---

## 1. RBAC — sudah terpasang

- **PermissionGroup:** `Pdm` (`pdm`), label "PdM & Maturity Level".
- **PermissionName** (grup Pdm): `pdm.input.view`, `pdm.input.write`,
  `pdm.laporan.view`, `pdm.master.view_any`, `pdm.master.manage`.
- **RoleName:** `tl_pdm` (**TL PdM & Maturity Level**), scope Unit.
  - Default: seluruh `teamLeaderBasePermissions()` + lima permission `pdm.*`.
  - **Manager UL** memperoleh `pdm.laporan.view` (oversight, read-only).
  - Super Admin otomatis punya semua (gate bypass).
- Menambah permission/role hanya perlu edit enum + jalankan
  `php artisan db:seed --class=RolePermissionSeeder` (idempotent, tanpa migration).

---

## 2. Peta Menu (blueprint navigasi)

Mirip HAR: sidebar grup **PdM & Maturity Level** berisi **Jadwal**, **Input**,
dan **Laporan** (`pdm.laporan.view`, `pdm/laporan/index.tsx` — lihat 2.3).

### 2.1 Menu JADWAL — `pdm/jadwal/index.tsx`
Filter unit/bulan/tahun + kartu berikut (target route final di kolom kanan):

| No | Jadwal | Target route (rencana) |
|---|---|---|
| 1 | Jadwal Kegiatan Harian | `/pdm/jadwal/harian` |
| 2 | Jadwal Piket On Call | `/pdm/jadwal/piket-on-call` |
| 3 | Jadwal Piket Patrol Check | `/pdm/jadwal/patrol-check` |
| 4 | Jadwal Program 5S 5R | `/pdm/jadwal/program-5s-5r` |
| 5 | Jadwal Meeting PdM KIT | `/pdm/jadwal/meeting` |
| 6 | Jadwal Pembuatan IK | `/pdm/jadwal/pembuatan-ik` |
| 7 | Jadwal Pemeriksaan Instalasi Blackstart | `/pdm/jadwal/blackstart` |
| 8 | Jadwal Commissioning Test Mesin | `/pdm/jadwal/commissioning-test-mesin` |
| 9 | Jadwal Commissioning Test Peralatan Non Mesin | `/pdm/jadwal/commissioning-test-non-mesin` |
| 10 | Jadwal Rencana Operasi (ROT, ROB, ROM) | `/pdm/jadwal/rencana-operasi` |

### 2.2 Menu INPUT — `pdm/input/index.tsx`

| No | Input | Route | Status |
|---|---|---|---|
| 1 | Kesiapan APD Bagian PdM Pembangkit | `/pdm/input/kesiapan-apd` (`pdm.input.kesiapan-apd.*`) | ✅ |
| 2 | Form Monitoring Pemeriksaan & Pengiriman Sample PdM | `/pdm/input/sample-monitoring` (`pdm.input.sample-monitoring.*`) | ✅ |
| 3 | Laporan Permit to Work (PTW) Pembangkit | `/pdm/input/permit-to-work` (`pdm.input.permit-to-work.*`) | ✅ |
| 4 | Laporan Inspeksi Checklist 5S5R PdM | `/pdm/input/forms/checklist-5s5r` (`pdm.input.forms.*`) | ✅ |
| 5 | Laporan Pengukuran Kualitas Air Pendingin | `/pdm/input/forms/air-pendingin` | ✅ |
| 6 | Laporan Pengukuran Kualitas Pelumas (per mesin) | `/pdm/input/forms/pelumas` | ✅ |
| 7 | Laporan Pengukuran Vibrasi Mesin & Generator (per mesin) | `/pdm/input/forms/vibrasi` | ✅ |
| 8 | Form Kontrol Material, Peralatan & Tools PdM | `/pdm/input/forms/kontrol-material` | ✅ |
| 9 | Realisasi Pemeliharaan Prediktif Bulanan | `/pdm/input/realisasi-prediktif` (`pdm.input.realisasi-prediktif.*`) | ✅ |
| 10 | Patrol Check Predictive Maintenance (PdM) | `/pdm/input/forms/patrol-check-pdm` | ✅ |
| 11 | Laporan Checklist Patrol Check PdM | `/pdm/input/forms/checklist-patrol-check` | ✅ |
| 12 | Log Sheet Predictive Maintenance | `/pdm/input/log-sheet` | ⏳ |

> **Tanda tangan belum dipakai di inputan** (tidak ada pilihan pegawai/nama
> penanda tangan/gambar TTD di halaman, PDF, maupun Excel) — menyusul bersama
> fitur verifikasi. Dropdown di halaman input memakai komponen React
> (`OperasiSelect` untuk filter, `PdmCellSelect` untuk sel tabel), bukan `<select>`.

Input yang sudah jadi — setiap input punya `index` (halaman), `store`, dan `pdf`,
plus unduhan Excel dari halaman (ExcelJS, `resources/js/lib/pdm-input-excel.ts`):

- **Kesiapan APD** — `Pdm\KesiapanApdController`, model `PdmKesiapanApd`
  (satu baris per item inspeksi, dikelompokkan `kelompok`). Kolom penilaian &
  daftar APD awal di `App\Support\PdmKesiapanApdForm` (OPTIONS, DEFAULT_ITEMS).
  Catatan disimpan di `pdm_jadwal_meta` type `kesiapan-apd` (kolom `catatan`). PDF A4 landscape: `resources/views/pdm/input/kesiapan-apd-pdf.blade.php`.
- **Monitoring Pemeriksaan & Pengiriman Sample** — `Pdm\SampleMonitoringController`,
  header `PdmSampleMonitoring` (lokasi, PIC, target rekap per jenis sample, catatan)
  + baris `PdmSampleMonitoringItem` (section `pengiriman` A / `hasil` B / `temuan` D,
  kolom di JSON `data`). Kolom tiap section & rekap C (dihitung dari A & B) di
  `App\Support\PdmSampleMonitoringForm`. PDF A4 landscape 2 halaman (A+B, C+D+E):
  `resources/views/pdm/input/sample-monitoring-pdf.blade.php`.
- **Permit to Work** — `Pdm\PermitToWorkController`, model `PdmPermitToWork`
  (uraian, tanggal, status open/close; min. 30 baris bernomor, total Open/Close).
  PDF A4 portrait sesuai form: `resources/views/pdm/input/permit-to-work-pdf.blade.php`.
- **Form generik (5S5R, Air Pendingin, Pelumas, Vibrasi, Kontrol Material, Patrol Check PdM, Checklist Patrol Check)** —
  satu `Pdm\FormInputController` (`pdm.input.forms.index|store|pdf`, parameter
  `{form}`), definisi per form di `App\Support\PdmForms\*Form` (registry
  `PdmForms::ALL`: kop, field header/footer, section + kolom + baris default,
  total, ringkasan akumulatif, orientasi, per-mesin). Data di `pdm_form_documents`
  (`subject` = id mesin untuk form per-mesin, `header` JSON, foto di disk public
  `pdm/{form}/{unit}/`) + `pdm_form_items` (section + JSON `data`), service
  `App\Services\Pdm\PdmFormDocuments`. Halaman per form di folder sendiri
  `resources/js/pages/pdm/input/{form}/index.tsx` (tipis: merender komponen
  bersama `components/pdm/form-input-page.tsx` + breadcrumb `pdmFormBreadcrumbs`);
  URL & route tetap `pdm.input.forms.*` (`/pdm/input/forms/{form}`),
  PDF `resources/views/pdm/input/{form}-pdf.blade.php` (layout `layouts/form`).
  Tipe kolom `check` (kotak centang, nilai `1`) + `exclusive` (satu centang per
  baris dalam grupnya, dipaksa juga di server) dipakai Patrol Check PdM untuk
  Ya / Tidak / N/A; PDF mencetak ✓ / ☐, Excel ✓. Patrol Check PdM: A. Identitas
  Patrol (unit, hari/tanggal, waktu, tim, pelaksana), B. 28 item checklist default
  (area/objek, item, standar), rekap Ya/Tidak/N/A & % sesuai, PDF A4 portrait.
  Checklist Patrol Check PdM: identitas, area A–E (turbin, generator, pelumasan,
  pendingin, monitoring PdM) bernomor lanjut 1..n (`continuousNumbering()`),
  status OK/NOK/N/A, temuan, tindak lanjut, kolom eviden + foto eviden (footer),
  ringkasan hasil patroli (total, OK, NOK, N/A, belum diisi, realisasi %). Tanpa TTD.
- **Realisasi Pemeliharaan Prediktif Bulanan** — `Pdm\RealisasiPrediktifController`,
  model `PdmRealisasiPrediktif` (uraian, mesin, hari `rencana`/`realisasi` JSON,
  durasi); target/realisasi/kinerja dihitung; Sabtu/Minggu/libur merah. Header
  dokumen di `pdm_jadwal_meta` type `realisasi-prediktif`. PDF A4 landscape.
- Bersama: trait `App\Http\Controllers\Concerns\HandlesPdmInput` (izin, unit &
  periode, opsi filter), kop & gaya PDF `resources/views/pdm/input/partials`,
  toolbar filter `resources/js/components/pdm/input-toolbar.tsx`, dropdown sel
  `resources/js/components/pdm/cell-select.tsx`. Setiap input punya
  `pdfView()` (nama view + data PDF) yang dipakai juga oleh Laporan PdM.
- Tes: `tests/Feature/Pdm/{KesiapanApd,SampleMonitoring,PermitToWork,PdmForm,RealisasiPrediktif}InputTest.php`.

### 2.3 Menu LAPORAN — `pdm/laporan/index.tsx`

Seperti Laporan K3: halaman `pdm/laporan/index.tsx` (filter unit/bulan/tahun +
kartu "Buka Dokumen") → dokumen editor `pdm/laporan/document.tsx` (`DocumentEditor`:
Teks/PDF, Excel, Pratinjau PDF, Simpan, Muat Ulang dari Data). Backend
`Pdm\DocumentController` (`pdm.laporan.document.edit|store|regenerate|pdf`),
disimpan di `pdm_document_records` (naikkan `BODY_VERSION` bila layout berubah).
`App\Services\Pdm\PdmDocumentBuilder` menyusun body `pdm/laporan/document-body`:
I. Sampul, II. Daftar Isi (nomor halaman diisi saat ekspor), III. Lembar Pengesahan
(Disetujui TL HAR, Diperiksa Koordinator Pemeliharaan, Mengetahui Manager — nama
dari pegawai sesuai jabatan, tanpa TTD), IV. seluruh Jadwal, V. seluruh Input.
Setiap tabel = view PDF aslinya (`pdfView()` di tiap controller jadwal/input) yang
disisipkan sebagai fragmen ber-CSS scoped (`App\Services\Reports\ScopedHtmlFragment`),
jadi **selalu tercetak lengkap** (data tersimpan atau isian bawaan halaman — tidak
ada garis merah). Satu `.pdm-section` per tabel (`.pdm-landscape` untuk landscape),
PDF via `OrientationPdfMerger::renderSections`. Mode Excel dari
`App\Services\Reports\FragmentGridBuilder` (tabel HTML → grid). Tes: `tests/Feature/Pdm/LaporanPdmTest.php`.

Urutan Input di laporan = urutan menu Input PdM (Patrol Check PdM & Checklist
Patrol Check sesudah Realisasi Prediktif — `PdmDocumentBuilder::FORMS_AFTER_REALISASI`).
Halaman `pdm/laporan/index.tsx` menampilkan panel **Isi Laporan** dari
`PdmDocumentBuilder::contents()` (judul, orientasi, status Tersimpan / Isian bawaan
per tabel, tanpa merender tabel). Logo: setiap halaman laporan & setiap tabel yang
disisipkan memakai `public/logo/sidebar-logo.png` (kiri) dan `public/logo/mkp.jpg`
(kanan) — controller jadwal/input mengambilnya lewat `App\Support\JadwalPdf::logos()`.

---

## 3. Konvensi implementasi sub-halaman (saat menyusul)

Ikuti pola **HAR** persis — JANGAN buat pola baru:

1. **Scope per unit.** Semua tabel transaksi ber-`unit_id` (FK ke `units`) +
   trait `App\Models\Concerns\BelongsToUnit`. Master global tanpa `unit_id`.
2. **Controller** di `app/Http/Controllers/Pdm/`. Setiap aksi gate dengan
   `abort_unless($user->hasPermissionTo(PermissionName::Pdm*), 403)` dan
   `$user->canAccessUnit($unit)`. `index`/`store`/`pdf`/`destroy` sesuai
   kebutuhan. Log lewat `ActivityLogger`.
3. **Model** di `app/Models/` prefiks `Pdm...`, `$table` eksplisit,
   `#[Fillable([...])]`, `casts()`, relasi `inputUser()`.
4. **Migration** prefiks tabel `pdm_...`, kolom `unit_id`, `year`, `month`,
   `sort_order`, `input_by` (nullOnDelete), index `[unit_id, year, month]`.
5. **Halaman React** di `resources/js/pages/pdm/...`, pakai `OperasiSelect`/
   `OPERASI_MONTHS`, `PageHeader`, komponen `ui/*`, pola grid harian (toggle
   hari) atau tabel input seperti `har/input/material-peralatan.tsx`. Reset
   state saat signature `unit-bulan-tahun` berubah. Pola dirty/save.
6. **Wayfinder:** setelah menambah route jalankan
   `php artisan wayfinder:generate --with-form` (WAJIB flag `--with-form`).
7. **PDF** via dompdf (`Pdf::loadView('pdm. ...')`, A4 landscape), kop dua logo
   (`sidebar-logo.png`, `mkp.jpg`) base64, identitas unit dari master.
8. **Pint** `vendor/bin/pint --dirty --format agent` sebelum selesai; tulis
   test feature untuk tiap controller baru.
9. Setelah tiap tombol difungsikan: ganti badge "Segera Hadir" → "Tersedia" dan
   aktifkan tombol di `index.tsx`, lalu **update dokumen ini** (§0 & §2).

---

## 4. Anti-pattern

- ❌ Membuat ulang master unit/mesin/pegawai — WAJIB FK existing.
- ❌ Tabel/kalkulasi tanpa `unit_id`.
- ❌ Branch pada nama role (`hasRole`) untuk otorisasi — pakai permission.
- ❌ Menjalankan `wayfinder:generate` tanpa `--with-form`.
- ❌ Deskripsi `work_modules` > 255 karakter (kolom string, akan truncate).
- ❌ Lupa update dokumen ini setelah perubahan modul.
