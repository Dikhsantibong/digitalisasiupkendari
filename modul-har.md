# Spesifikasi Modul PEMELIHARAAN (HAR) — Aplikasi Pelaporan Pembangkit (Multi-Unit)

> Panduan implementasi untuk Claude Code, hasil reverse-engineering file Excel
> `05__LAPORAN_HAR_AGUSTUS_2026_PLTD_WUA-WUA.xlsx` (LAPORAN PEMELIHARAAN, unit
> **PLTD Wua-Wua**). Modul ini SEJAJAR dengan modul OPERASI (lihat
> `modul-operasi.md`) — pola & prinsip arsitekturnya sama, datanya beda.
>
> **Langkah 0 sebelum coding:** baca `CLAUDE.md` (project pakai **Laravel
> Boost**). Lalu periksa master existing: **Unit Layanan**, **Mesin**,
> **Pegawai** (FK, jangan buat ulang). Jika modul Operasi sudah dibangun,
> **pakai ulang** RBAC, work_modules, document template engine, report
> registry, dan komponen grid yang sudah ada — JANGAN duplikasi.

---

## 0. Gambaran Besar

Modul PEMELIHARAAN (HAR) = laporan bulanan Team Leader Pemeliharaan. Berbeda
mendasar dari modul Operasi:

| | Modul OPERASI | Modul HAR |
|---|---|---|
| Basis data | Pembacaan meter harian (angka) | **Work Order (WO) & Service Request (SR)** |
| Bentuk input | Grid seperti Excel (baris=tanggal) | **Daftar WO/SR + log kegiatan + biaya + foto** |
| Sumber utama | File harian per tanggal | **Sistem WPC PLN** (`192.168.3.85/wpc-ditgas`) |
| Output | Rekap kWh/SFC + Berita Acara | Laporan HAR ber-format dokumen ISO + Executive Summary |

### 0.1 KRITIS — Sumber data WPC (input manual dulu, siapkan koneksi DB)

Di Excel, data WO/SR ditarik dari sistem **WPC** (Work Planning & Control) PLN,
lalu di-screenshot dan ditempel manual. Untuk aplikasi:

- **Fase sekarang: INPUT MANUAL.** TL HAR mengetik WO/SR ke form.
- **Siapkan arsitektur untuk koneksi database WPC langsung nanti.** Caranya:
  - Bungkus semua akses data WO/SR di balik **satu interface/service**
    (mis. `WorkOrderSource`) dengan dua implementasi: `ManualWorkOrderSource`
    (baca dari tabel input lokal, dipakai sekarang) dan placeholder
    `WpcWorkOrderSource` (akan konek ke DB/API WPC nanti). Controller &
    laporan **hanya bergantung pada interface**, tidak langsung ke tabel.
  - Beri kolom asal data di tabel WO (`source` = `manual` | `wpc`) supaya saat
    integrasi WPC aktif, data manual & tarikan otomatis bisa hidup
    berdampingan dan dibedakan.
  - **Jangan** hardcode koneksi ke `192.168.3.85` sekarang. Cukup siapkan
    config/env kosong + interface. Konfirmasi detail WPC (jenis DB, skema,
    kredensial) ke user saat fase integrasi.

### 0.2 KRITIS — Multi-unit & master existing

- Format laporan **sama untuk semua unit**; hanya data mesin & nama unit
  berbeda. Semua tabel modul ini ber-`unit_id` (FK master Unit Layanan).
- **Master Unit Layanan, Mesin, Pegawai sudah ada** → FK, jangan buat ulang.
  Identitas unit (nama, sektor) untuk kop laporan diambil dari Unit Layanan;
  penandatangan dari Pegawai.
- Mesin di file ini: **MAK #1–#5** (MAK = merk mesin di Wua-Wua). Unit lain
  punya mesin sendiri — jangan hardcode daftar mesin.

---

## 1. Struktur Laporan HAR (dari Daftar Isi Excel)

Urutan baku laporan (tiap bagian = satu sheet ber-No. Dokumen ISO
`FMKD-314-...`). Klasifikasi sifat: ISI (input) / CETAK (olahan) / ISI+CETAK.

| # | Bagian | No. Dokumen | Sifat |
|---|---|---|---|
| — | Executive Summary | — | CETAK (rangkuman otomatis) |
| I | Istilah & Definisi | — | CETAK (statis) |
| 1 | **Service Request (SR) Summary** | FMKD-314-10.3.3-A8 | ISI (WPC) → CETAK |
| 2 | **WO Summary** | FMKD-314-10.3.3-A9 | ISI (WPC) → CETAK |
| 3 | **Akumulasi Biaya Pemeliharaan** | FMKD-314-10.3.3-A10 | ISI manual → CETAK |
| 4 | Rekapitulasi WO Task | — | CETAK |
| 5 | **WO PM** (Preventive) | FMKD-314-10.3.3-A12 | ISI (WPC) → CETAK |
| 6 | **WO PdM** (Predictive) | — | ISI (WPC) → CETAK |
| 7 | **WO CM** (Corrective) | FMKD-314-10.3.3-A14 | ISI (WPC) → CETAK |
| 8 | **WO ENJI** (Engineering) | — | ISI (WPC) → CETAK |
| 9 | WO Waiting Shutdown | — | ISI → CETAK |
| 10 | WO Waiting Material & Jasa | — | ISI → CETAK |
| — | **Realisasi vs Rencana HAR** (bulan ini & depan) | FMKD-314-10.3.1-A1 | ISI → CETAK |
| — | Realisasi/Rencana Pelumas & Air | — | ISI → CETAK |
| — | **Laporan Kegiatan HARMES** (log harian) | FMKD-314-10.3.3-A3 | **ISI manual (inti)** |
| II | **Lampiran** (#mesin-tanggal, foto) | — | ISI (upload foto) → CETAK |

**No. Dokumen ISO diperlakukan TETAP per jenis sheet** (editable di setting,
bukan auto-generate) — sama pola dengan Berita Acara modul Operasi.

---

## 2. Istilah & Master Data (dibuat baru oleh modul ini)

Master pemeliharaan belum ada di aplikasi → buat dengan CRUD:

```
maintenance_types    id, code, name, category, is_active
  -- PM (Preventive), PdM (Predictive), CM (Corrective),
  --    FLM (First Line Maintenance), ENJI (Engineering)
maintenance_cycles   id, code, name, interval_days, description
  -- P1=7D, P2=14D, P4=84D, dst. (siklus pemeliharaan rutin)
wo_statuses          id, code, name, is_closed (bool)
  -- APPR, CLOSE, WAPPR, INPRG, dll. (minta daftar lengkap; seed placeholder)
work_groups          id, code, name
  -- MECHD (mekanik), ELECD (listrik), dll.
sr_categories        id, code, name
  -- CM, FLM, CANCEL, PDM (kategori Service Request)
```

> Seed nilai di atas sebagai placeholder; sediakan CRUD agar user melengkapi/
> mengoreksi (daftar status WPC lengkap belum final).

---

## 3. Skema Database (Laravel + MySQL) — semua ber-`unit_id`

### 3.1 Work Order & Service Request (input manual, siap sumber WPC)
```
service_requests
  id, unit_id, report_period_id, sr_number, description,
  sr_category_id (FK), status (open|close), engine_id (nullable),
  source (manual|wpc), keterangan, input_by, timestamps

work_orders
  id, unit_id, report_period_id,
  wonum (no WO, mis. WO13258), description,
  maintenance_type_id (FK: PM/PdM/CM/ENJI/FLM),
  engine_id (nullable, FK master mesin), work_group_id (FK),
  wo_status_id (FK),
  report_date (datetime), sched_start (datetime), sched_finish (datetime),
  actual_finish (datetime, nullable),
  cycle_id (nullable, FK maintenance_cycles),  -- P1/P2/P4 jika PM
  waiting_reason (nullable: shutdown|material|jasa),  -- utk WO tertunda
  service_cost (decimal, nullable), material_cost (decimal, nullable),
  source (manual|wpc), input_by, timestamps
  index(unit_id, report_period_id, maintenance_type_id)
```

### 3.2 Biaya Pemeliharaan
```
maintenance_costs   -- akumulasi per bulan per unit
  id, unit_id, year, month,
  service_cost (jasa), material_cost, keterangan,
  input_by, timestamps
  unique(unit_id, year, month)
  -- Default: rinci per WO (jumlah dari work_orders.service_cost+material_cost)
  --   TAPI sediakan juga input total manual per bulan sebagai fallback/override
  --   (di Excel diisi manual per bulan). Simpan keduanya; tandai mana yang dipakai.
```

### 3.3 Log Kegiatan HARMES (inti pekerjaan lapangan — input manual)
```
maintenance_activities
  id, unit_id, report_period_id, activity_date, engine_id (FK master mesin),
  maintenance_type_id (FK), work_result (mis. "Baik"),
  wo_id (nullable, FK work_orders),
  no_lh05, no_sr, no_tug9,     -- nomor dokumen terkait (referensi WPC/administrasi)
  keterangan, input_by, timestamps

maintenance_activity_tasks   -- uraian kegiatan (multi-baris per aktivitas)
  id, activity_id (FK), task_description, sort_order

maintenance_activity_materials  -- material terpakai
  id, activity_id (FK), material_name, part_number (nullable),
  quantity, unit_of_measure
```

### 3.4 Rencana vs Realisasi pemeliharaan rutin
```
maintenance_schedules   -- matriks jadwal per mesin (rencana & realisasi)
  id, unit_id, year, month, engine_id (FK),
  plan_type (rencana|realisasi),
  scope (har|pelumas|air),          -- 3 jenis matriks di Excel
  schedule_data (JSON),              -- struktur per-tanggal/per-item (fleksibel)
  input_by, timestamps
  -- struktur matriks 31 hari × item; simpan JSON agar generik antar scope.
  -- Konfirmasi ke user apakah perlu granular per-hari atau cukup ringkasan.
```

### 3.5 Lampiran foto
```
maintenance_attachments
  id, unit_id, report_period_id,
  wo_id (nullable), activity_id (nullable), engine_id (nullable),
  title, photo_path, caption, taken_date, sort_order, input_by, timestamps
  -- upload foto per pekerjaan penting (sheet #mesin-tanggal di Excel).
  -- pakai storage Laravel; simpan path, bukan blob.
```

---

## 4. Aturan Bisnis & Kalkulasi (backend, per unit)

1. **SR Summary:** hitung jumlah & persentase SR per kategori (CM/FLM/Cancel/
   PdM) dari `service_requests` unit+periode; tampilkan status Open/Close.
2. **WO Summary:** rekap WO terbit vs complete (status `is_closed`) per periode.
3. **Rekap WO per jenis:** kelompokkan `work_orders` per `maintenance_type`
   (PM/PdM/CM/ENJI), tampilkan kolom persis Excel: NO, WONUM, DESCRIPTION,
   REPORT DATE, SCHED START, SCHED FINISH, STATUS, WORK GROUP.
4. **Akumulasi biaya:** default = SUM(service_cost)+SUM(material_cost) dari
   work_orders per bulan; akumulatif berjalan setahun. Jika ada override
   manual di `maintenance_costs`, pakai itu (tandai di UI).
5. **WO tertunda:** filter `waiting_reason` = shutdown / material / jasa.
6. **Executive Summary (otomatis):** rangkum jumlah SR, WO per jenis, % complete,
   total biaya, highlight WO open/tertunda — semua dari data di atas.
7. **Realisasi vs Rencana:** bandingkan matriks rencana vs realisasi per mesin.

---

## 5. Anti-pattern

- ❌ Membuat ulang master unit/mesin/pegawai — FK existing.
- ❌ Mengakses tabel WO langsung dari controller/laporan — WAJIB lewat
  interface `WorkOrderSource` (agar WPC bisa disambung tanpa ubah kode).
- ❌ Hardcode daftar mesin (MAK #1–5), status WO, jenis pemeliharaan — semua
  ke master.
- ❌ Hardcode koneksi WPC / IP `192.168.3.85` sekarang.
- ❌ Menduplikasi RBAC/work_modules/document engine/report registry bila modul
  Operasi sudah membangunnya — pakai ulang.
- ❌ Menyimpan foto sebagai blob di DB — pakai storage, simpan path.

---

## 6. Seed data unit Wua-Wua (verifikasi ke user)

- **Mesin:** MAK #1, #2, #3, #4, #5 (cocokkan ke master mesin existing unit
  Wua-Wua).
- **Jenis pemeliharaan:** PM, PdM, CM, FLM, ENJI.
- **Siklus:** P1 (7D), P2 (14D), P4 (84D) — konfirmasi daftar lengkap.
- **Work group:** MECHD, ELECD.
- **Kategori SR:** CM, FLM, CANCEL, PDM.
- **Status WO:** APPR, CLOSE (+ lainnya dari WPC — minta lengkap).

> Seeder per unit — unit lain punya mesin & data sendiri.
