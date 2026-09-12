# Spesifikasi Modul K3 & KEAMANAN — Aplikasi Pelaporan Pembangkit (Multi-Unit)

> Panduan implementasi untuk Claude Code, hasil reverse-engineering 5 file
> Excel laporan K3 (unit **PLTD Poasia**). Modul ini SEJAJAR dengan modul
> OPERASI & HAR — pola & prinsip arsitektur sama, data beda.
>
> **Langkah 0 sebelum coding:** baca `CLAUDE.md` (project pakai **Laravel
> Boost**). Periksa master existing: **Unit Layanan, Mesin, Pegawai** (FK,
> jangan buat ulang). **Pakai ulang** fondasi generik modul Operasi/HAR (RBAC,
> `work_modules`, document template engine, report registry, komponen grid) —
> jangan duplikasi.

---

## 0. Gambaran Besar

Modul K3 & KEAMANAN = laporan kinerja bulanan TL K3L & Keamanan. Dibangun
dari 5 file sumber yang saling melengkapi:

| File sumber | Isi | Peran di aplikasi |
|---|---|---|
| `8_Agustus-lapkin-kirim.xlsx` (LAPKIN) | **Laporan induk** — 28 form K3/keamanan | Struktur utama modul (menu-menu) |
| `8_patrol_check_satpam.xlsx` (PATROL) | Log patroli satpam per hari + kumulatif | Sub-modul patroli keamanan |
| `3__..Emergency_Facility..xlsx` (EMERGENCY) | Kesiapan fasilitas darurat (mingguan+bulanan) | Sub-modul emergency facility |
| `2__..Lampiran_Laporan_K3..xlsx` (LAMPIRAN) | Lampiran bulanan (1-2026 … 8-2026) | Arsip lampiran per bulan |
| `1_1_..Sertifikat_Peralatan..xlsx` (SERTIFIKAT) | Monitoring sertifikasi & pengujian alat | Sub-modul sertifikat peralatan |

### 0.1 Sifat data K3: mayoritas INSPEKSI & INVENTARIS berkala

Berbeda dari Operasi (meter harian) dan HAR (work order). K3 = **checklist
inspeksi, inventaris peralatan, dan log keamanan** dengan periode campuran:
harian (patroli, apel), mingguan (emergency facility), dan bulanan (mayoritas
form). Hampir semua form punya **No. Dokumen ISO** (`SMT-FM-AK3-*`,
`FMZ-*`) — perlakukan tetap per form, editable di setting (pola sama BA/HAR).

### 0.2 Multi-unit & master existing

- Format form **sama antar unit**; hanya data & nama unit beda. Semua tabel
  ber-`unit_id` (FK Unit Layanan). File ini unit **Poasia**.
- Lokasi patroli (POA1–POA14) & titik inspeksi bersifat **per unit** → master
  sendiri, bukan hardcode.
- Penandatangan (Manager, TL K3L & KAM, Ketua Tim inspeksi) dari master
  Pegawai.

---

## 1. Struktur Laporan (dari LAPKIN — 28 form)

Klasifikasi sifat: ISI (input) / CETAK (olahan) / ISI+CETAK.

| # | Form | No. Dokumen | Sifat |
|---|---|---|---|
| 1 | **Time Frame** kinerja K3 (rencana vs realisasi kegiatan) | — | ISI → CETAK |
| 2 | Laporan Kecelakaan (PAK/PAHK) | — | ISI (sering NIHIL) → CETAK |
| 3 | PAK, PAHK per fungsi/instalasi/masyarakat | — | ISI → CETAK |
| 4 | **Daftar Alat Tanggap Darurat** | SMT-FM-AK3-03.03 | ISI → CETAK |
| 5 | **APAR-APAB** (kondisi per tabung, RFID) | SMT-FM-AK3-12.03 | ISI → CETAK |
| 6 | APAT | — | ISI → CETAK |
| 7 | Pilar Hydrant & Form Hydrant | — | ISI → CETAK |
| 8 | **HIRARC** (identifikasi bahaya & risiko) | — | ISI → CETAK |
| 9 | **Inventaris APD** (alat pelindung diri) | SMT-FM-AK3-01.01 | ISI → CETAK |
| 10 | Inspeksi Rambu K3 | — | ISI → CETAK |
| 11 | Inspeksi & Pemeriksaan Kotak/Isi P3K | — | ISI → CETAK |
| 12 | Pemeriksaan Fire Alarm | — | ISI → CETAK |
| 13 | **Apel Keamanan** & **Patroli Keamanan** | — | ISI (harian) → CETAK |
| 14 | Kondisi CCTV | — | ISI → CETAK |
| 15 | Mutasi/Monitoring Tamu Security | — | ISI → CETAK |
| 16 | **Inspeksi Tempat Kerja** (checklist) | SMT-FM-AK3-12-01 | ISI → CETAK |

> Beberapa form bertanda "(NO)"/"(old)" di Excel = versi lama tidak dipakai →
> abaikan, pakai versi terbaru. Tampilkan daftar final ke user untuk koreksi.

---

## 2. Master Data (dibuat baru oleh modul ini, per unit kecuali disebут global)

```
k3_activity_types     id, code, name, category, default_pic, is_active
   -- untuk Time Frame: Inspeksi P3K, Inspeksi Potensi Bahaya Kebakaran, dst.
emergency_equipments  id, unit_id, group_name, name, location, sort_order
   -- Fire Pump, PMK Mobile, Ambulance, dsb. (untuk laporan Emergency Facility)
apd_categories        id, code, name          -- kategori APD (global)
apd_items             id, unit_id, apd_category_id, name, location
patrol_locations      id, unit_id, code, name  -- POA1..POA14 (per unit)
security_posts        id, unit_id, name         -- pos apel/patroli
equipment_categories  id, code, name (global)   -- Crane, Tangki Timbun, dsb.
fire_extinguishers    id, unit_id, rfid, location, merk, jenis, berat_kg
   -- master APAR/APAB per tabung (dipakai inspeksi berkala)
p3k_boxes             id, unit_id, code, location  -- kotak P3K
inspection_checklists id, unit_id, form_code, item_text, sort_order
   -- item checklist per jenis inspeksi (tempat kerja, rambu, dll.) — generik
```

> Seed dari data file (verifikasi ke user). Lokasi & item inspeksi per unit.

---

## 3. Skema Database — semua ber-`unit_id`

### 3.1 Time Frame (rencana vs realisasi kegiatan K3)
```
k3_activity_plans
  id, unit_id, year, month, k3_activity_type_id (FK), pic,
  plan_days (JSON: tanggal rencana), real_days (JSON: tanggal realisasi),
  status, keterangan, input_by, timestamps
  -- matriks 31 hari RENC/REAL per kegiatan (pola sama Time Frame HAR)
```

### 3.2 Laporan kecelakaan (PAK/PAHK)
```
accident_reports
  id, unit_id, year, month, incident_date (nullable), fungsi, lokasi,
  category (pak|pahk|instalasi|masyarakat),
  luka_ringan (int), luka_berat (int), meninggal (int),
  kerugian_material (decimal), is_nihil (bool), keterangan, input_by, timestamps
  -- bila tak ada kejadian, is_nihil=true (mayoritas bulan NIHIL)
```

### 3.3 Inspeksi & inventaris berkala (generik)
```
inspections            -- header satu sesi inspeksi
  id, unit_id, report_period_id, form_code, inspection_date,
  inspector_team, ketua_tim, keterangan, input_by, timestamps
inspection_results     -- baris hasil inspeksi (checklist)
  id, inspection_id (FK), item_ref, kondisi, tindak_lanjut, nilai (nullable), catatan

fire_extinguisher_checks   -- inspeksi APAR/APAB berkala per tabung
  id, unit_id, report_period_id, fire_extinguisher_id (FK), tgl_periksa,
  kondisi_tabung, kondisi_nozzle, indikator_tekanan, kondisi_pin_segel,
  exp_date, keterangan
emergency_facility_checks  -- kesiapan fasilitas darurat (mingguan/bulanan)
  id, unit_id, year, month, week (nullable 1-4, null=bulanan),
  emergency_equipment_id (FK), jml_total, jml_ready, jml_not_ready,
  persen_kesiapan (auto), kendala, tindak_lanjut, input_by, timestamps
apd_inventories        -- inventaris APD periodik
  id, unit_id, report_period_id, apd_item_id (FK), jumlah, lokasi, keterangan
p3k_inspections        -- inspeksi kotak/isi P3K
  id, unit_id, report_period_id, p3k_box_id (FK), item_name, jumlah,
  kondisi, exp_date, tindak_lanjut
emergency_tools        -- daftar alat tanggap darurat (rekap kondisi)
  id, unit_id, report_period_id, jenis, siap_pakai, kadaluarsa, kosong,
  tgl_isi_kembali, keterangan
```

### 3.4 Keamanan (harian)
```
security_patrols       -- patroli satpam per lokasi per hari (file PATROL)
  id, unit_id, patrol_date, patrol_location_id (FK), scan_times (JSON),
  total_scan (int), input_by, timestamps
  -- POA1..POA14 dengan cap waktu scan; total per hari; rekap KOMULATIF bulanan
security_muster        -- apel keamanan
  id, unit_id, muster_date, shift, jml_hadir, petugas (JSON/text), keterangan
guest_logs             -- mutasi/monitoring tamu
  id, unit_id, visit_date, guest_name, instansi, keperluan, jam_masuk,
  jam_keluar, keterangan
cctv_checks            -- kondisi CCTV
  id, unit_id, report_period_id, cctv_name, lokasi, kondisi, keterangan
```

### 3.5 Sertifikasi peralatan (file SERTIFIKAT)
```
equipment_certificates
  id, unit_id, equipment_category_id (FK), jenis, kapasitas, lokasi,
  merk_manufacture, no_seri, regulasi (text),
  ijin_awal_nomor, ijin_awal_tanggal,
  uji_terakhir_nomor, uji_terakhir_tanggal, uji_ulang_tanggal,
  status (aktif|expired|belum), batasan_uji, masa_berlaku_tahun,
  keterangan, input_by, timestamps
  -- status & sisa hari/bulan expired dihitung otomatis dari uji_ulang_tanggal
equipment_test_methods -- metode & hasil pengujian
  id, unit_id, equipment_certificate_id (FK), metode (JSON: visual/fungsi/
  beban/hydro/ndt/ultrasonic/ketahanan), hasil, sertifikasi_terakhir, keterangan
```

### 3.6 Lampiran (file LAMPIRAN — arsip bulanan)
```
k3_attachments
  id, unit_id, year, month, title, file_path, category, input_by, timestamps
  -- upload dokumen/foto lampiran per bulan; storage Laravel, simpan path.
```

---

## 4. Aturan Bisnis & Kalkulasi (backend, per unit)

1. **% Kesiapan emergency:** `jml_ready / jml_total` (tangani kasus non-angka
   spt "Ready"/level air → tampilkan apa adanya, jangan paksa hitung).
2. **Rekap patroli KOMULATIF:** jumlah scan per lokasi (POA1–14) sepanjang
   bulan, dari `security_patrols`.
3. **Status sertifikat:** dari `uji_ulang_tanggal` vs hari ini → aktif/expired/
   akan-expired; hitung sisa hari & bulan. Beri highlight yang mendekati/lewat.
4. **Time Frame:** bandingkan hari RENC vs REAL per kegiatan.
5. **Rekap kecelakaan:** total luka ringan/berat/meninggal/kerugian; NIHIL bila
   kosong.
6. **Executive/ringkasan bulanan:** kompilasi status semua form (jumlah APAR
   siap/kadaluarsa, kesiapan emergency, kecelakaan, patroli, sertifikat
   mendekati expired).

---

## 5. Anti-pattern

- ❌ Membuat ulang master unit/mesin/pegawai — FK existing.
- ❌ Hardcode lokasi patroli (POA1–14), item checklist, kategori alat — master.
- ❌ Satu tabel per form (28 tabel serupa) — pakai pola inspeksi generik
  (`inspections` + `inspection_results`) untuk form checklist yang seragam;
  tabel khusus hanya untuk yang strukturnya benar-benar beda (APAR, emergency,
  sertifikat, patroli).
- ❌ Menduplikasi RBAC/document engine/report registry modul Operasi/HAR.
- ❌ Simpan foto/lampiran sebagai blob — storage, simpan path.
- ❌ Memaksa % kesiapan jadi angka saat sumbernya teks (level air, "Ready").

---

## 6. Seed data unit Poasia (verifikasi ke user)

- **Lokasi patroli:** POA1–POA14.
- **Emergency facility:** Fire Protection Jockey/Electric/Diesel Pump, Sea
  Water Fire Pump, Fire Water Level, PMK Mobile, Emergency Response Team,
  Ambulance.
- **Kategori alat sertifikasi:** Crane (Overhead Traveling Crane 5 Ton),
  Tangki Timbun (Containerized, HSD #1/#2, MFO #1/#2), dst.
- **Alat tanggap darurat:** APAR, APAB, APAT, Pompa Hydrant.
- **No. Dokumen ISO** contoh: SMT-FM-AK3-03.03 / 12.03 / 01.01 / 12-01,
  FMZ-08.2.3.29 — simpan per form (editable).

> Seeder per unit — unit lain punya lokasi & alat sendiri.
